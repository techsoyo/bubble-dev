<?php

require_once __DIR__ . '/../bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../../src/Utils/ResponseHelper.php';
require_once __DIR__ . '/../../src/Parsers/CVTextParser.php';

use Utils\CVTextParser;
use Utils\ResponseHelper;

try {
    $input = ResponseHelper::getJsonInput();

    if (!isset($input['text_file_path']) && !isset($input['cv_text'])) {
        ResponseHelper::error('Se requiere text_file_path o cv_text', 400);
        exit;
    }

    $cvText = '';
    if (isset($input['text_file_path'])) {
        $filePath = $input['text_file_path'];

        if (!file_exists($filePath)) {
            ResponseHelper::error('Archivo no encontrado: ' . $filePath, 404);
            exit;
        }

        $cvText = file_get_contents($filePath);
        if ($cvText === false) {
            ResponseHelper::error('No se pudo leer el archivo', 500);
            exit;
        }
    } else {
        $cvText = $input['cv_text'];
    }

    $parser = new CVTextParser($cvText);
    $parsedData = $parser->parse();

    // Guardar resultado como JSON
    $filename = basename($filePath ?? ('cv_' . time() . '.txt'), '.txt') . '.json';
    $savePath = __DIR__ . '/../uploads/json/' . $filename;
    file_put_contents($savePath, json_encode([$parsedData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    ResponseHelper::success('CV analizado y guardado exitosamente', [
      'data' => $parsedData,
      'file' => $filename
    ]);
} catch (Exception $e) {
    ResponseHelper::log('error', 'Error en analyze_cv: ' . $e->getMessage());
    ResponseHelper::error('Error procesando el CV: ' . $e->getMessage(), 500);
}
