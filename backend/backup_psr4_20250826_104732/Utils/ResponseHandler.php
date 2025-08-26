<?php declare(strict_types=1);

namespace Utils\ResponseHandler.php\Utils;



// backend/src/Utils/ResponseHandler.php

namespace Utils;

/**
 * Clase para manejar respuestas de API de forma consistente
 */
class ResponseHandler
{
    /**
     * Generar respuesta de Ã©xito
     */
    public function success($data = [], string $message = 'OperaciÃ³n exitosa', int $code = 200): string
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
     * Generar respuesta de validaciÃ³n
     */
    public function validationError(array $errors, string $message = 'Datos de entrada invÃ¡lidos'): string
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
    public function conflict(string $message = 'Conflicto en la operaciÃ³n'): string
    {
        return $this->error($message, 409);
    }

    /**
     * Generar respuesta de lÃ­mite excedido
     */
    public function tooManyRequests(string $message = 'Demasiadas solicitudes'): string
    {
        return $this->error($message, 429);
    }

    /**
     * Respuesta de Ã©xito para creaciÃ³n
     */
    public function created($data = [], string $message = 'Recurso creado exitosamente'): string
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Respuesta de Ã©xito para actualizaciÃ³n
     */
    public function updated($data = [], string $message = 'Recurso actualizado exitosamente'): string
    {
        return $this->success($data, $message, 200);
    }

    /**
     * Respuesta de Ã©xito para eliminaciÃ³n
     */
    public function deleted(string $message = 'Recurso eliminado exitosamente'): string
    {
        return $this->success([], $message, 200);
    }

    /**
     * Respuesta para contenido vacÃ­o
     */
    public function noContent(): string
    {
        http_response_code(204);
        return '';
    }

    /**
     * Enviar respuesta y terminar ejecuciÃ³n
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
     * Configurar headers CORS - DEPRECATED
     * CORS ahora se configura automÃ¡ticamente en bootstrap.php
     */
    public function setCorsHeaders(): void
    {
        // CORS ya configurado en bootstrap.php - mÃ©todo mantenido por compatibilidad
        if (function_exists('error_log')) {
            error_log('DEPRECATION WARNING: ResponseHandler::setCorsHeaders() ya no es necesario. CORS se configura automÃ¡ticamente en bootstrap.php');
        }
    }

    /**
     * Manejar peticiÃ³n OPTIONS para CORS
     */
    public function handleOptions(): void
    {
        $this->setCorsHeaders();
        http_response_code(200);
        exit;
    }
}
