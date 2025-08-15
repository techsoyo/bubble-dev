<?php

namespace Utils;

class ResponseHelper
{
    /**
     * Obtiene y decodifica el cuerpo JSON de la petición actual
     * @return array|null
     */
    public static function getJsonInput(): ?array
    {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return null;
        }
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $data;
    }

    /**
     * Registra un mensaje en el log del sistema
     * @param string $level Nivel ('info', 'error', etc.)
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $contextStr = $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        error_log("[ResponseHelper][$level] $message $contextStr");
    }
    public static function success(string $message = 'OK', $data = null, int $status = 200, array $extraHeaders = []): void
    {
        self::send($status, ['success' => true, 'message' => $message, 'data' => $data], $extraHeaders);
    }

    public static function error(string $message = 'Error', int $status = 400, array $payload = [], array $extraHeaders = []): void
    {
        $body = array_merge(['success' => false, 'message' => $message], $payload);
        self::send($status, $body, $extraHeaders);
    }

    public static function exception(\Throwable $e, int $status = 500, ?bool $includeTrace = null): void
    {
        if ($includeTrace === null) {
            $env = getenv('APP_ENV') ?: 'prod';
            $includeTrace = ($env === 'dev' || $env === 'local');
        }

        $body = [
          'ok'      => false,
          'message' => $e->getMessage(),
        ];

        if ($includeTrace) {
            $body['type']  = get_class($e);
            $body['file']  = $e->getFile();
            $body['line']  = $e->getLine();
            $body['trace'] = explode("\n", $e->getTraceAsString());
        }

        self::send($status, $body);
    }

    private static function send(int $status, array $body, array $extraHeaders = []): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            foreach ($extraHeaders as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
