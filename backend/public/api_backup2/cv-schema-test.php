<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'GET'; // Para ejecuciÃ³n directa
}



require_once __DIR__ . '/../autoload.php';


require_once $BOOT;

// Fallback si el autoload no funciona
if (!class_exists('Domain\\CvSchema')) {
    require_once __DIR__ . '/../src/Domain/CvSchema.php';
}

use Domain\CvSchema;

// Bloquear en producciÃ³n
if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}

/**
 * Endpoint de prueba para el contrato CV Schema
 *
 * GET /api/cv-schema-test - Obtiene el template del CV
 * POST /api/cv-schema-test - Normaliza y valida datos del CV
 */

function sendJsonResponse($success, $data = null, $error = null)
{
    $response = [
      'success' => $success
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    if ($error !== null) {
        $response['error'] = $error;
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Devolver el template del CV
            sendJsonResponse(true, [
              'template' => CvSchema::TEMPLATE,
              'info' => 'Template base del CV Schema - espejo del contrato frontend'
            ]);
            break;

        case 'POST':
            // Normalizar y validar datos recibidos
            $input = json_decode(file_get_contents('php://input'), true);

            if ($input === null) {
                sendJsonResponse(false, null, [
                  'code' => 'INVALID_JSON',
                  'message' => 'El JSON enviado no es vÃ¡lido'
                ]);
            }

            // Normalizar datos
            $normalizedData = CvSchema::normalize($input);

            // Validar datos mÃ­nimos
            $validationErrors = CvSchema::validateMinimumData($normalizedData);

            if (!empty($validationErrors)) {
                sendJsonResponse(false, [
                  'normalized_data' => $normalizedData
                ], [
                  'code' => 'VALIDATION_FAILED',
                  'message' => 'Los datos no cumplen con los requisitos mÃ­nimos',
                  'details' => $validationErrors
                ]);
            }

            // Todo OK
            sendJsonResponse(true, [
              'normalized_data' => $normalizedData,
              'validation_status' => 'passed',
              'message' => 'Datos normalizados y validados correctamente'
            ]);
            break;

        default:
            sendJsonResponse(false, null, [
              'code' => 'METHOD_NOT_ALLOWED',
              'message' => 'MÃ©todo HTTP no permitido. Use GET o POST.'
            ]);
    }
} catch (Exception $e) {
    sendJsonResponse(false, null, [
      'code' => 'INTERNAL_ERROR',
      'message' => 'Error interno del servidor',
      'details' => $e->getMessage()
    ]);
}

