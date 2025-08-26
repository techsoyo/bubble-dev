<?php declare(strict_types=1);
namespace Utils;

/**
 * Clase para la gestiÃƒÆ’Ã‚Â³n de traducciones en el backend
 */
class TranslationService
{
    /**
     * Idioma actual
     * @var string
     */
    private static $currentLanguage = 'es'; // Idioma por defecto

    /**
     * Traducciones disponibles
     * @var array
     */
    private static $translations = [
      'es' => [
        // JobMatchingService
        'iniciando_prueba' => 'Iniciando diagnÃƒÆ’Ã‚Â³stico de matching de candidatos con empleos...',
        'calculando_coincidencia' => 'Calculando coincidencia entre candidato y empleo...',
        'resultado_matching' => 'Resultado del matching:',
        'generando_explicacion' => 'Generando explicaciÃƒÆ’Ã‚Â³n detallada...',
        'explicacion' => 'ExplicaciÃƒÆ’Ã‚Â³n:',
        'prueba_completada' => 'DiagnÃƒÆ’Ã‚Â³stico completado.',
        'coincidencia_excelente' => 'El candidato muestra una excelente coincidencia con la oferta de trabajo. Sus habilidades principales (%s) se alinean perfectamente con los requisitos del puesto. Su experiencia en %s proporciona el conocimiento necesario para desempeÃƒÆ’Ã‚Â±ar las funciones requeridas.',
        'coincidencia_buena' => 'El candidato muestra una buena coincidencia con la oferta de trabajo, aunque hay ÃƒÆ’Ã‚Â¡reas de mejora. Tiene experiencia relevante y algunas de las habilidades clave requeridas, pero podrÃƒÆ’Ã‚Â­a necesitar formaciÃƒÆ’Ã‚Â³n adicional en algunos aspectos especÃƒÆ’Ã‚Â­ficos del puesto.',
        'coincidencia_baja' => 'El candidato no muestra una coincidencia ÃƒÆ’Ã‚Â³ptima con esta oferta de trabajo. Aunque tiene algunas habilidades ÃƒÆ’Ã‚Âºtiles, le faltan competencias clave como %s. Se recomienda considerar otros perfiles o proporcionar formaciÃƒÆ’Ã‚Â³n significativa.',
        'habilidades_relevantes' => 'Habilidades relevantes',
        'el_sector' => 'el sector',
        'habilidades_especificas' => 'habilidades especÃƒÆ’Ã‚Â­ficas'
      ],
      'en' => [
        // JobMatchingService
        'iniciando_prueba' => 'Starting candidate-job matching diagnostics...',
        'calculando_coincidencia' => 'Calculating match between candidate and job...',
        'resultado_matching' => 'Matching result:',
        'generando_explicacion' => 'Generating detailed explanation...',
        'explicacion' => 'Explanation:',
        'prueba_completada' => 'Diagnostics completed.',
        'coincidencia_excelente' => 'The candidate shows an excellent match with the job offer. Their main skills (%s) align perfectly with the job requirements. Their experience in %s provides the necessary knowledge to perform the required functions.',
        'coincidencia_buena' => 'The candidate shows a good match with the job offer, although there are areas for improvement. They have relevant experience and some of the key skills required, but may need additional training in some specific aspects of the position.',
        'coincidencia_baja' => 'The candidate does not show an optimal match with this job offer. Although they have some useful skills, they lack key competencies such as %s. It is recommended to consider other profiles or provide significant training.',
        'habilidades_relevantes' => 'Relevant skills',
        'el_sector' => 'the sector',
        'habilidades_especificas' => 'specific skills'
      ]
    ];

    /**
     * Establece el idioma actual
     *
     * @param string $language CÃƒÆ’Ã‚Â³digo de idioma ('es', 'en')
     * @return void
     */
    public static function setLanguage($language)
    {
        if (isset(self::$translations[$language])) {
            self::$currentLanguage = $language;
        }
    }

    /**
     * Obtiene el idioma actual
     *
     * @return string CÃƒÆ’Ã‚Â³digo de idioma actual
     */
    public static function getLanguage()
    {
        return self::$currentLanguage;
    }

    /**
     * Traduce una clave a texto segÃƒÆ’Ã‚Âºn el idioma actual
     *
     * @param string $key Clave de traducciÃƒÆ’Ã‚Â³n
     * @param array $params ParÃƒÆ’Ã‚Â¡metros para interpolaciÃƒÆ’Ã‚Â³n (opcional)
     * @return string Texto traducido
     */
    public static function translate($key, $params = [])
    {
        // Verificar si existe la clave para el idioma actual
        if (isset(self::$translations[self::$currentLanguage][$key])) {
            $text = self::$translations[self::$currentLanguage][$key];

            // Aplicar parÃƒÆ’Ã‚Â¡metros si existen
            if (!empty($params)) {
                return vsprintf($text, $params);
            }

            return $text;
        }

        // Si no existe la traducciÃƒÆ’Ã‚Â³n para el idioma actual, intentar con el idioma por defecto
        if (self::$currentLanguage !== 'es' && isset(self::$translations['es'][$key])) {
            $text = self::$translations['es'][$key];

            // Aplicar parÃƒÆ’Ã‚Â¡metros si existen
            if (!empty($params)) {
                return vsprintf($text, $params);
            }

            return $text;
        }

        // Si no hay traducciÃƒÆ’Ã‚Â³n, devolver la clave
        return $key;
    }

    /**
     * Alias corto para translate()
     *
     * @param string $key Clave de traducciÃƒÆ’Ã‚Â³n
     * @param array $params ParÃƒÆ’Ã‚Â¡metros para interpolaciÃƒÆ’Ã‚Â³n (opcional)
     * @return string Texto traducido
     */
    public static function t($key, $params = [])
    {
        return self::translate($key, $params);
    }
}
