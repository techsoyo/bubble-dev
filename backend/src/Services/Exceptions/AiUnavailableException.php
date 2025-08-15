<?php

namespace Services\Exceptions;

/**
 * Excepción para errores del servicio de IA
 */
class AiUnavailableException extends \Exception
{
    public function __construct($message = 'AI service unavailable', $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
