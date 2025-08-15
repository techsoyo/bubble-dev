<?php

namespace Controllers;

use Services\PDFExtractorService;

class PDFController
{
    private $extractor;

    public function __construct()
    {
        $this->extractor = new PDFExtractorService();
    }

    public function parse()
    {
        header('Content-Type: application/json');

        // Captura errores fatales y los devuelve como JSON
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error fatal: ' . $error['message']
                ]);
                exit;
            }
        });

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => "Error: $errstr en $errfile:$errline"
            ]);
            exit;
        });

        try {
            if (!isset($_FILES['pdf_file'])) {
                throw new \Exception('Archivo no enviado.');
            }
            $file = $_FILES['pdf_file'];
            $this->extractor->validateFile($file);

            // Guardar el PDF original en uploads/cvs/ PRIMERO
            $originalPdfPath = $this->extractor->saveOriginalPdf($file);

            // Copiar el PDF original a uploads/textos/ para extracción temporal
            $tempFilePath = $this->extractor->copyToTextos($originalPdfPath);

            $text = $this->extractor->extractText($tempFilePath);
            // Usar el nombre original del PDF para los archivos de salida
            $originalName = $file['name'] ?? null;
            $files = $this->extractor->saveTextFiles($text, $originalName);

            // Extraer datos estructurados del texto del CV
            $structuredData = $this->extractor->extractStructuredData($text, basename($originalPdfPath));



            // Eliminar el archivo temporal después de la extracción
            $this->extractor->deleteFile($tempFilePath);

            echo json_encode([
                'success' => true,
                'original_pdf' => basename($originalPdfPath),
                'txt_file' => $files['txt'],
                'json_file' => $files['json'],
                'structured_data' => $structuredData,
                // "structured_json_file" => $jsonFilename,
                'message' => 'Texto extraído y datos estructurados generados correctamente.'
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}
