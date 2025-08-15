<?php

// backend/src/Utils/ResponseHandler.php

namespace Utils;

/**
 * Clase para manejar respuestas de API de forma consistente
 */
class ResponseHandler
{
    /**
     * Generar respuesta de éxito
     */
    public function success($data = [], string $message = 'Operación exitosa', int $code = 200): string
    {
        $response = [
          'success' => true,
          'code' => $code,
          'message' => $message,
          'data' => $data,
          'timestamp' => date('Y-m-d H:i:s')
        ];

        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');

        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Generar respuesta de error
     */
    public function error(string $message = 'Error interno', int $code = 500, $details = null): string
    {
        $response = [
          'success' => false,
          'code' => $code,
          'message' => $message,
          'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($details !== null) {
            $response['details'] = $details;
        }

        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');

        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Generar respuesta de validación
     */
    public function validationError(array $errors, string $message = 'Datos de entrada inválidos'): string
    {
        return $this->error($message, 422, ['validation_errors' => $errors]);
    }

    /**
     * Generar respuesta de recurso no encontrado
     */
    public function notFound(string $resource = 'Recurso'): string
    {
        return $this->error("$resource no encontrado", 404);
    }

    /**
     * Generar respuesta de no autorizado
     */
    public function unauthorized(string $message = 'No autorizado'): string
    {
        return $this->error($message, 401);
    }

    /**
     * Generar respuesta de prohibido
     */
    public function forbidden(string $message = 'Acceso prohibido'): string
    {
        return $this->error($message, 403);
    }

    /**
     * Generar respuesta de conflicto
     */
    public function conflict(string $message = 'Conflicto en la operación'): string
    {
        return $this->error($message, 409);
    }

    /**
     * Generar respuesta de límite excedido
     */
    public function tooManyRequests(string $message = 'Demasiadas solicitudes'): string
    {
        return $this->error($message, 429);
    }

    /**
     * Respuesta de éxito para creación
     */
    public function created($data = [], string $message = 'Recurso creado exitosamente'): string
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Respuesta de éxito para actualización
     */
    public function updated($data = [], string $message = 'Recurso actualizado exitosamente'): string
    {
        return $this->success($data, $message, 200);
    }

    /**
     * Respuesta de éxito para eliminación
     */
    public function deleted(string $message = 'Recurso eliminado exitosamente'): string
    {
        return $this->success([], $message, 200);
    }

    /**
     * Respuesta para contenido vacío
     */
    public function noContent(): string
    {
        http_response_code(204);
        return '';
    }

    /**
     * Enviar respuesta y terminar ejecución
     */
    public function send(string $response): void
    {
        echo $response;
        exit;
    }

    /**
     * Respuesta paginada
     */
    public function paginated(array $data, int $total, int $page, int $perPage, string $message = 'Datos obtenidos exitosamente'): string
    {
        $totalPages = ceil($total / $perPage);
        $hasNextPage = $page < $totalPages;
        $hasPrevPage = $page > 1;

        $paginationData = [
          'items' => $data,
          'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage
          ]
        ];

        return $this->success($paginationData, $message);
    }

    /**
     * Configurar headers CORS
     */
    public function setCorsHeaders(): void
    {
        if (defined('CORS_APPLIED') || PHP_SAPI === 'cli') {
            return;
        }
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', realpath(__DIR__ . '/../../'));
        }
        require_once BASE_PATH . '/cors.php';
    }

    /**
     * Manejar petición OPTIONS para CORS
     */
    public function handleOptions(): void
    {
        $this->setCorsHeaders();
        http_response_code(200);
        exit;
    }
}
