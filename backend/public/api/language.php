<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__);             // api -> backend/
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;


try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Return default language
            $response = [
              'success' => true,
              'data' => [
                'language' => 'es'
              ],
              'message' => 'Language retrieved successfully'
            ];
            break;

        case 'POST':
            // Set language (could be saved to session or database)
            $input = json_decode(file_get_contents('php://input'), true);
            $language = $input['language'] ?? 'es';

            // Validate language
            if (!in_array($language, ['es', 'en'])) {
                $language = 'es';
            }

            // Save to session (or database as needed)
            session_start();
            $_SESSION['language'] = $language;

            $response = [
              'success' => true,
              'data' => [
                'language' => $language
              ],
              'message' => 'Language set successfully'
            ];
            break;

        default:
            $response = [
              'success' => false,
              'message' => 'Method not allowed',
              'error' => 'Only GET and POST methods are allowed'
            ];
            http_response_code(405);
            break;
    }
} catch (Exception $e) {
    $response = [
      'success' => false,
      'message' => 'Server error',
      'error' => $e->getMessage()
    ];
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
