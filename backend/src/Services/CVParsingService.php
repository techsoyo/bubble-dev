<?php

declare(strict_types=1);

namespace Services;

/**
 * Servicio para el procesamiento bí¡sico de CVs (sin IA)
 * Se enfoca en extracción de texto y generación de archivos para procesamiento posterior
 */
class CVParsingService
{
  /**
   * Servicio de extracción de PDF
   * @var PDFExtractorService
   */
  private $pdfExtractor;

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->pdfExtractor = new \Services\PDFExtractorService();
  }

  /**
   * Procesa un archivo de CV: extrae texto y guarda en formato .txt
   *
   * @param string $filePath Ruta al archivo del CV (PDF)
   * @return array Información del procesamiento
   */
  public function processCV($filePath)
  {
    try {
      // 1. Extraer texto del PDF
      $extractedText = $this->pdfExtractor->extractText($filePath);

      if (empty($extractedText)) {
        return [
          'success' => false,
          'error' => 'No se pudo extraer texto del PDF',
          'text_file' => null
        ];
      }

      // 2. Generar archivo de texto
      $textFilePath = $this->saveTextFile($extractedText, $filePath);

      // 3. Estadí­sticas bí¡sicas
      $stats = [
        'word_count' => str_word_count($extractedText),
        'char_count' => strlen($extractedText),
        'created_at' => date('c')
      ];

      return [
        'success' => true,
        'text_file' => $textFilePath,
        'extracted_text' => $extractedText,
        'stats' => $stats
      ];
    } catch (\Exception $e) {
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'text_file' => null
      ];
    }
  }

  /**
   * Extrae habilidades bí¡sicas del texto (sin IA)
   *
   * @param string $cvText Texto del CV
   * @return array Lista de habilidades detectadas
   */
  public function extractBasicSkills($cvText)
  {
    // Lista bí¡sica de habilidades comunes
    $commonSkills = [
      'php',
      'javascript',
      'python',
      'java',
      'react',
      'vue',
      'angular',
      'html',
      'css',
      'mysql',
      'postgresql',
      'mongodb',
      'git',
      'adobe',
      'photoshop',
      'illustrator',
      'figma',
      'sketch',
      'marketing',
      'seo',
      'google ads',
      'facebook ads',
      'project management',
      'scrum',
      'agile',
      'branding',
      'diseí±o'
    ];

    $detectedSkills = [];
    $textLower = strtolower($cvText);

    foreach ($commonSkills as $skill) {
      if (strpos($textLower, strtolower($skill)) !== false) {
        $detectedSkills[] = $skill;
      }
    }

    return $detectedSkills;
  }

  /**
   * Guarda el texto extraí­do en un archivo .txt
   *
   * @param string $text Texto extraí­do
   * @param string $originalFile Archivo original
   * @return string Ruta al archivo de texto guardado
   */
  private function saveTextFile($text, $originalFile)
  {
    $filename = pathinfo($originalFile, PATHINFO_FILENAME);
    $timestamp = date('Y-m-d_H-i-s');
    $textFilename = $filename . '_' . $timestamp . '.txt';

    $textDir = __DIR__ . '/../../uploads/textos/';
    if (!is_dir($textDir)) {
      mkdir($textDir, 0755, true);
    }

    $textFilePath = $textDir . $textFilename;

    // Guardar como JSON con metadatos (como ya lo hací­as)
    $data = [
      'filename' => $textFilename,
      'word_count' => str_word_count($text),
      'char_count' => strlen($text),
      'created_at' => date('c'),
      'text' => $text
    ];

    file_put_contents($textFilePath . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $textFilePath . '.json';
  }

  /**
   * Método legacy para mantener compatibilidad (DEPRECATED)
   * @deprecated Usar processCV
   */
  public function parseCV($cvText)
  {
    // Solo devolver habilidades bí¡sicas para mantener compatibilidad
    return [
      'skills' => $this->extractBasicSkills($cvText),
      'ai_enabled' => false,
      'message' => 'Usando extracción bí¡sica - IA separada en módulo independiente'
    ];
  }

  /**
   * Método legacy para mantener compatibilidad (DEPRECATED)
   * @deprecated Usar extractBasicSkills
   */
  public function extractSkills($cvText)
  {
    return $this->extractBasicSkills($cvText);
  }
}
