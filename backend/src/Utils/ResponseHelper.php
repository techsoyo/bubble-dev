<?php

namespace Utils;

class ResponseHelper
{
    public static function success(string $message = 'OK', array $data = [], int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
        return true;
    }

    public static function fail(string $message = 'Bad Request', int $status = 400, array $extra = [])
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array_merge([
            'success' => false,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE);
        return false;
    }

    public static function error(string $message = 'Internal Server Error', \Throwable $e = null, int $status = 500)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');

        $payload = [
            'success' => false,
            'message' => $message,
        ];

        $isDev = (getenv('APP_ENV') && strtolower(getenv('APP_ENV')) !== 'production') || getenv('APP_DEBUG') === 'true';
        if ($e && $isDev) {
            $payload['error'] = [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        return false;
    }

    /**
     * Manejo de excepciones con logging y respuesta de error
     */
    public static function exception(\Throwable $e, int $status = 500)
    {
        // Log de la excepción
        error_log('[EXCEPTION] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        // Responder con error
        return self::error('Error interno del servidor', $e, $status);
    }
}
