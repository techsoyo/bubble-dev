<?php declare(strict_types=1);
namespace Services;

use Exception;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Utils\Database;

class ResumeService
{
    /**
     * Procesa un CV PDF o DOCX, extrae texto, consulta IA y guarda resultado
     * @param string $filePath Ruta absoluta al archivo
     * @return array Resultado del anÃƒÆ’Ã‚Â¡lisis
     * @throws Exception
     */
    public function processResume(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $text = '';
        if ($ext === 'pdf') {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
        } elseif ($ext === 'docx') {
            $phpWord = WordIOFactory::load($filePath, 'Word2007');
            foreach ($phpWord->getSections() as $section) {
                $text .= $this->extractTextFromElements($section->getElements());
            }
        } else {
            throw new Exception('Tipo de archivo no soportado');
        }
        if (trim($text) === '') {
            throw new Exception('El archivo estÃƒÆ’Ã‚Â¡ vacÃƒÆ’Ã‚Â­o o no se pudo extraer texto');
        }
        // Llamada a API de IA (ejemplo OpenAI)
        $aiResult = $this->analyzeWithAI($text);
        // Guardar en base de datos
        $this->storeResult($filePath, $aiResult);
        return $aiResult;
    }

    /**
     * Extrae texto de elementos de PHPWord de forma recursiva
     * @param array $elements
     * @return string
     */
    private function extractTextFromElements(array $elements): string
    {
        $text = '';
        foreach ($elements as $element) {
            if (method_exists($element, 'getText')) {
                $t = $element->getText();
                if ($t) {
                    $text .= $t . "\n";
                }
            }
            if (method_exists($element, 'getElements')) {
                $text .= $this->extractTextFromElements($element->getElements());
            }
        }
        return $text;
    }

    private function analyzeWithAI(string $text): array
    {
        // ImplementaciÃƒÆ’Ã‚Â³n real pendiente: invocar proveedor de IA y procesar la respuesta
        throw new Exception('analyzeWithAI no implementado');
    }

    private function storeResult(string $filePath, array $result): void
    {
        $db = Database::getInstance();
        $db->insert('resume_analysis', [
          'file_path' => $filePath,
          'score' => $result['score'],
          'tags' => json_encode($result['tags']),
          'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
