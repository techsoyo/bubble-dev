<?php

namespace Middleware;

use Utils\TranslationService;

/**
 * Middleware para la gestión de idiomas
 */
class LanguageMiddleware
{
    /**
     * Procesa la solicitud y establece el idioma adecuado
     *
     * @param \Utils\Request $request Objeto Request
     * @param callable $next Siguiente middleware
     * @return mixed
     */
    public function handle($request, $next)
    {
        // Orden de prioridad para obtener el idioma:
        // 1. Parámetro en la URL (?lang=es|en)
        // 2. Header Accept-Language
        // 3. Cookie
        // 4. Idioma por defecto (es)

        $language = 'es'; // Valor por defecto

        // Comprobar si hay un parámetro de idioma en la URL
        if (isset($_GET['lang'])) {
            $reqLang = strtolower($_GET['lang']);
            if ($reqLang === 'es' || $reqLang === 'en') {
                $language = $reqLang;
            }
        }
        // Comprobar si hay un header Accept-Language
        elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $acceptLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if ($acceptLang === 'es' || $acceptLang === 'en') {
                $language = $acceptLang;
            }
        }
        // Comprobar si hay una cookie de idioma
        elseif (isset($_COOKIE['language'])) {
            $cookieLang = $_COOKIE['language'];
            if ($cookieLang === 'es' || $cookieLang === 'en') {
                $language = $cookieLang;
            }
        }

        // Establecer el idioma en el servicio de traducción
        TranslationService::setLanguage($language);

        // Guardar el idioma en una cookie para futuras solicitudes
        if (!isset($_COOKIE['language']) || $_COOKIE['language'] !== $language) {
            setcookie('language', $language, time() + 60 * 60 * 24 * 30, '/'); // 30 días
        }

        // Continuar con el siguiente middleware
        return $next($request);
    }
}
