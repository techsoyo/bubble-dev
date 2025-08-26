<?php declare(strict_types=1);
namespace Services\Exceptions;

/**
 * ExcepciÃƒÂ³n para errores del servicio de IA
 */
class AiUnavailableException extends \Exception
{
    public function __construct($message = 'AI service unavailable', $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
