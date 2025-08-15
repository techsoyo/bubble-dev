<?php

namespace Controllers;

use Utils\ResponseHelper;
use Utils\TranslationService;

/**
 * Controlador para la gestión de idiomas
 */
class LanguageController extends BaseController
{
    /**
     * Obtiene el idioma actual
     *
     * @return array
     */
    public function getLanguage()
    {
        return ResponseHelper::success('Idioma obtenido correctamente', [
          'language' => TranslationService::getLanguage()
        ]);
    }

    /**
     * Establece el idioma
     *
     * @return array
     */
    public function setLanguage()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $language = $data['language'] ?? 'es';

        if ($language !== 'es' && $language !== 'en') {
            return ResponseHelper::error('Idioma no soportado', 400);
        }

        TranslationService::setLanguage($language);

        // Guardar en cookie
        setcookie('language', $language, time() + 60 * 60 * 24 * 30, '/');

        return ResponseHelper::success('Idioma establecido correctamente', [
          'language' => $language
        ]);
    }
}
