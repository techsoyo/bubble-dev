<?php

declare(strict_types=1);

namespace Services;

use RuntimeException;

/**
 * Servicio de extracción de texto desde PDFs con doble estrategia:
 *  A) Texto "nativo" (pdftotext) + heurística de calidad
 *  B) OCR (pdftoppm + tesseract) si habilitado y falla A
 *
 * Diseñado para el flujo de /api/cv/parse (currículums) garantizando:
 *  - Validaciones tempranas (existencia, tamaño, MIME, antivirus opcional)
 *  - Limpieza rigurosa de temporales
 *  - No exposición de rutas internas en excepciones
 *  - Logs mínimos estructurados sin contenido sensible
 */
class PdfTextService
{
    /** @var int */
    private $maxBytes;
    /** @var bool */
    private $ocrEnabled;
    /** @var string|null */
    private $binPdftotext;
    /** @var string|null */
    private $binPdftoppm;
    /** @var string|null */
    private $binTesseract;
    /** @var string|null */
    private $clamScanCmd;

    public function __construct()
    {
        $this->maxBytes     = (int)($this->env('CV_MAX_UPLOAD_BYTES', '5242880')); // 5MB por defecto
        $this->ocrEnabled   = $this->env('CV_ENABLE_OCR', 'true') === 'true';
        $this->binPdftotext = $this->resolveBinary($this->env('PDFTOTEXT_BIN', 'pdftotext'));
        $this->binPdftoppm  = $this->resolveBinary($this->env('PDFTOPPM_BIN', 'pdftoppm'));
        $this->binTesseract = $this->resolveBinary($this->env('TESSERACT_BIN', 'tesseract'));
        $this->clamScanCmd  = $this->env('CLAMAV_CMD', '');
    }

    /**
     * Extrae texto usando heurísticas y OCR fallback.
     * @throws PdfSecurityException|PdfTextEmptyException|InfectedFileException
     */
    public function extract(string $pdfPath): string
    {
        $t0 = microtime(true);
        $this->guardFile($pdfPath);
        $this->scanIfEnabled($pdfPath);
        $mime = $this->detectMime($pdfPath);
        if ($mime !== 'application/pdf') {
            throw new PdfSecurityException('E_PDF_BAD_MIME');
        }

        // Intento A: texto "nativo"
        $native = null;
        if ($this->binPdftotext) {
            $native = $this->extractNative($pdfPath);
            if ($native !== null) {
                $score = $this->scoreTextQuality($native);
                $this->logStep('native_attempt', [
                  'duration_ms'    => $this->ms($t0),
                  'chars'          => $score['length_total'],
                  'useful_ratio'   => $score['ratio_useful'],
                  'blank_ratio'    => $score['ratio_blank_lines'],
                  'pass'           => $score['pass']
                ]);
                if ($score['pass']) {
                    return $this->normalizeUtfBlocks($native);
                }
            }
        } else {
            $this->logStep('native_skipped', ['reason' => 'pdftotext_missing', 'duration_ms' => $this->ms($t0)]);
        }

        // Intento B: OCR (si habilitado)
        if ($this->ocrEnabled && $this->binPdftoppm && $this->binTesseract) {
            $ocrText = $this->extractViaOcr($pdfPath, $t0);
            if ($ocrText !== null) {
                $scoreOcr = $this->scoreTextQuality($ocrText);
                $this->logStep('ocr_quality', [
                  'duration_ms'    => $this->ms($t0),
                  'chars'          => $scoreOcr['length_total'],
                  'useful_ratio'   => $scoreOcr['ratio_useful'],
                  'blank_ratio'    => $scoreOcr['ratio_blank_lines'],
                  'pass'           => $scoreOcr['pass']
                ]);
                if ($scoreOcr['pass']) {
                    return $this->normalizeUtfBlocks($ocrText);
                }
            }
        } else {
            if ($this->ocrEnabled) {
                $this->logStep('ocr_skipped', ['reason' => 'missing_bins', 'duration_ms' => $this->ms($t0)]);
            } else {
                $this->logStep('ocr_disabled', ['duration_ms' => $this->ms($t0)]);
            }
        }

        // Ninguna estrategia útil
        throw new PdfTextEmptyException('E_PDF_TEXT_EMPTY');
    }

    /* =================== Estrategias =================== */

    private function extractNative(string $pdfPath): ?string
    {
        $tmpTxt = $this->tempFile('native_', '.txt');
        $cmd = sprintf(
            '%s -layout %s %s 2>&1',
            escapeshellcmd($this->binPdftotext),
            escapeshellarg($pdfPath),
            escapeshellarg($tmpTxt)
        );
        $out = $this->execCommand($cmd, 30);
        if (!is_file($tmpTxt)) {
            $this->logStep('native_fail', ['exit' => $out['exit'], 'stderr_len' => strlen($out['output'])]);
            return null;
        }
        $data = @file_get_contents($tmpTxt) ?: '';
        @unlink($tmpTxt);
        $clean = $this->lightSanitize($data);
        return trim($clean) === '' ? null : $clean;
    }

    private function extractViaOcr(string $pdfPath, float $t0): ?string
    {
        $langs = $this->env('CV_OCR_LANGS', '') ?: 'eng';
        $workspace = $this->buildTempWorkspace();
        $prefix = $workspace . DIRECTORY_SEPARATOR . 'pg';
        $cmdConv = sprintf(
            '%s -r 220 -png %s %s 2>&1',
            escapeshellcmd($this->binPdftoppm),
            escapeshellarg($pdfPath),
            escapeshellarg($prefix)
        );
        $conv = $this->execCommand($cmdConv, 60);
        $pngs = glob($prefix . '-*.png') ?: [];
        if (empty($pngs)) {
            $this->logStep('ocr_convert_empty', ['exit' => $conv['exit'], 'duration_ms' => $this->ms($t0)]);
            $this->cleanupDir($workspace);
            return null;
        }
        // Orden natural por sufijo numérico
        usort($pngs, function ($a, $b) {
            return $this->extractPageNum($a) <=> $this->extractPageNum($b);
        });
        $all = [];
        $pageCount = 0;
        foreach ($pngs as $img) {
            $outBase = $workspace . DIRECTORY_SEPARATOR . 'ocr_' . $this->extractPageNum($img);
            $cmdOcr = sprintf(
                '%s %s %s -l %s 2>&1',
                escapeshellcmd($this->binTesseract),
                escapeshellarg($img),
                escapeshellarg($outBase),
                escapeshellarg($langs)
            );
            $this->execCommand($cmdOcr, 90); // OCR por página
            $txtFile = $outBase . '.txt';
            if (is_file($txtFile)) {
                $seg = @file_get_contents($txtFile) ?: '';
                $seg = $this->lightSanitize($seg);
                if ($seg !== '') {
                    $all[] = $seg;
                }
                @unlink($txtFile);
            }
            $pageCount++;
        }
        $this->logStep('ocr_pages_done', [
          'pages_ocr'   => $pageCount,
          'duration_ms' => $this->ms($t0)
        ]);
        $this->cleanupDir($workspace);
        if (!$all) {
            return null;
        }
        return implode("\n\n", $all);
    }

    /* =================== Heurística =================== */

    private function scoreTextQuality(string $txt): array
    {
        $total = mb_strlen($txt, 'UTF-8');
        if ($total === 0) {
            return [
              'length_total'       => 0,
              'length_useful'      => 0,
              'ratio_useful'       => 0.0,
              'ratio_blank_lines'  => 1.0,
              'pass'               => false
            ];
        }
        // Caracteres útiles: letras (incluye acentos) y dígitos
        $useful = preg_replace('/[^0-9A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/u', '', $txt);
        $lenUseful = mb_strlen($useful, 'UTF-8');
        $lines = preg_split('/\R/u', $txt) ?: [];
        $blank = 0;
        foreach ($lines as $ln) {
            if (trim($ln) === '') {
                $blank++;
            }
        }
        $ratioUseful = $total > 0 ? $lenUseful / $total : 0;
        $ratioBlank = (count($lines) > 0) ? $blank / count($lines) : 1;
        $pass = $lenUseful >= 60 && $ratioUseful >= 0.25 && $ratioBlank < 0.6;
        return [
          'length_total'       => $total,
          'length_useful'      => $lenUseful,
          'ratio_useful'       => $ratioUseful,
          'ratio_blank_lines'  => $ratioBlank,
          'pass'               => $pass
        ];
    }

    private function normalizeUtfBlocks(string $txt): string
    {
        // 1. Unificar saltos
        $txt = preg_replace("/\r\n?/", '\n', $txt);
        // 2. Quitar/control chars excepto tab y newline
        $txt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $txt);
        // 3. Colapsar espacios
        $txt = preg_replace('/[ \t]+/u', ' ', $txt);
        // 4. Trim por línea
        $lines = array_map(static fn ($l) => trim($l), explode("\n", $txt));
        // 5. Limitar bloques de líneas vacías consecutivas a 2
        $out = [];
        $emptySeq = 0;
        foreach ($lines as $l) {
            if ($l === '') {
                $emptySeq++;
                if ($emptySeq <= 2) {
                    $out[] = '';
                }
            } else {
                $emptySeq = 0;
                $out[] = $l;
            }
        }
        return trim(implode("\n", $out));
    }

    private function lightSanitize(string $txt): string
    {
        // Remover bytes no imprimibles excepto saltos
        $txt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', $txt);
        return $txt;
    }

    /* =================== Seguridad & utilidades =================== */

    private function guardFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new PdfSecurityException('E_PDF_NOT_FOUND');
        }
        $size = filesize($path);
        if ($size === false || $size > $this->maxBytes) {
            throw new PdfSecurityException('E_PDF_TOO_LARGE');
        }
    }

    private function detectMime(string $path): string
    {
        $f = new \finfo(FILEINFO_MIME_TYPE);
        return $f->file($path) ?: 'application/octet-stream';
    }

    private function scanIfEnabled(string $path): void
    {
        if (!$this->clamScanCmd) {
            return;
        } // opcional
        $cmd = $this->clamScanCmd . ' ' . escapeshellarg($path) . ' 2>&1';
        $res = $this->execCommand($cmd, 45);
        if ($res['exit'] !== 0) {
            // ClamAV típico: exit 1 => infectado / otros códigos pueden variar, tratamos cualquiera !=0 como sospechoso
            throw new InfectedFileException('INFECTED_FILE');
        }
    }

    private function resolveBinary(string $bin): ?string
    {
        if ($bin === '') {
            return null;
        }
        // Si es ruta absoluta y existe
        if ((str_starts_with($bin, '/') || preg_match('/^[A-Za-z]:\\\\/', $bin)) && is_file($bin)) {
            return $bin;
        }
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $probe = $isWin
          ? sprintf('where %s 2>NUL', escapeshellarg($bin))
          : sprintf('command -v %s 2>/dev/null', escapeshellarg($bin));
        $out = [];
        @exec($probe, $out, $code);
        if ($code === 0 && !empty($out[0])) {
            return trim($out[0]);
        }
        return null; // no encontrado
    }

    private function execCommand(string $cmd, int $timeoutSec): array
    {
        // Implementación simple (sin proc_open complejo) dado entorno controlado.
        $start = microtime(true);
        $output = [];
        exec($cmd, $output, $exit); // Limitaciones: sin timeout duro nativo.
        $duration = (int)round((microtime(true) - $start) * 1000);
        $this->logStep('proc', ['cmd_hash' => sha1($cmd), 'exit' => $exit, 'ms' => $duration]);
        return ['exit' => $exit, 'output' => implode("\n", $output), 'ms' => $duration];
    }

    private function tempFile(string $prefix, string $suffix): string
    {
        $name = tempnam(sys_get_temp_dir(), $prefix);
        if ($name === false) {
            throw new RuntimeException('E_TMP_CREATE_FAIL');
        }
        // Asegurar sufijo si requerido
        if ($suffix && substr($name, -strlen($suffix)) !== $suffix) {
            $withSuffix = $name . $suffix;
            if (!@rename($name, $withSuffix)) {
                throw new RuntimeException('E_TMP_RENAME_FAIL');
            }
            $name = $withSuffix;
        }
        return $name;
    }

    private function buildTempWorkspace(): string
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cvocr_' . bin2hex(random_bytes(5));
        if (!@mkdir($base, 0700, true)) {
            throw new RuntimeException('E_OCR_WS_FAIL');
        }
        return $base;
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = glob($dir . DIRECTORY_SEPARATOR . '*');
        if ($items) {
            foreach ($items as $it) {
                if (is_file($it)) {
                    @unlink($it);
                }
            }
        }
        @rmdir($dir);
    }

    private function extractPageNum(string $file): int
    {
        if (preg_match('/-(\d+)\.png$/', $file, $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    private function ms(float $t0): int
    {
        return (int)round((microtime(true) - $t0) * 1000);
    }

    private function logStep(string $step, array $data = []): void
    {
        // Logging opcional; si no hay logger global se usa error_log
        $payload = json_encode(['step' => $step] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        error_log('[PDF_TEXT] ' . $payload);
    }

    private function env(string $k, string $default): string
    {
        return $_ENV[$k] ?? getenv($k) ?? $default;
    }
}

/* =================== Excepciones específicas =================== */

class PdfTextEmptyException extends RuntimeException
{
}
class PdfSecurityException extends RuntimeException
{
}
class InfectedFileException extends RuntimeException
{
}
