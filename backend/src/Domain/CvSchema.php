<?php

declare(strict_types=1);

namespace Domain;

/**
 * Schema unificado para CV con template y normalización
 *
 * Proporciona:
 * - TEMPLATE: estructura base para el formulario
 * - normalize(): método para limpiar y validar datos de entrada
 * - Contrato espejo con frontend TypeScript
 */
class CvSchema
{
  /**
   * Template base del CV - espejo del contrato frontend
   */
  public const TEMPLATE = [
    // bt_candidates - Campos principales
    'nombre' => '',
    'email' => '',
    'telefono' => '',
    'ubicacion_actual' => '',
    'fecha_nacimiento' => '',
    'portfolio' => '',
    'linkedin' => '',
    'otras_redes' => [],
    'resumen_profesional' => '',
    'soft_skills' => [],
    'hard_skills' => [],
    'idiomas' => [],
    'intereses' => [],
    'referencias' => '',
    'disponibilidad' => '',
    'cv_original_file' => '',
    'cv_text_file' => '',
    'cv_json_file' => '',
    'data_source' => 'manual_entry', // 'ai_processing' | 'manual_entry' | 'hybrid'

    // bt_candidate_experiences
    'puestos_anteriores' => [],

    // bt_candidate_education
    'educacion' => [],

    // bt_candidate_certifications - Detalles expandidos
    'certificaciones' => [],
    'certificaciones_detalle' => [],

    // bt_candidate_languages - Detalles expandidos
    'idiomas_detalle' => [],

    // bt_candidate_references - Detalles expandidos
    'referencias_detalle' => [],

    // bt_candidate_skills - Habilidades adicionales
    'habilidades_adicionales' => [],

    // bt_candidate_routing - Información de enrutamiento
    'routing' => [
      'categoria_departamento_id' => null,
      'departamento_id' => null,
      'reclutador_id' => null,
      'fuente' => 'manual', // 'ai' | 'manual'
      'razon' => '',
      'fecha_asignacion' => ''
    ]
  ];

  /**
   * Plantilla mí­nima para prompts de IA
   */
  public const PROMPT_MINIMAL = [
    'nombre' => 'Juan Pérez Garcí­a',
    'email' => 'juan.perez@email.com',
    'telefono' => '+34 123 456 789',
    'ubicacion_actual' => 'Madrid, Espaí±a',
    'fecha_nacimiento' => '1990-05-15',
    'portfolio' => 'https://juanperez.dev',
    'linkedin' => 'https://linkedin.com/in/juanperez',
    'otras_redes' => ['https://github.com/juanperez'],
    'resumen_profesional' => 'Desarrollador Full Stack con 5 aí±os de experiencia en tecnologí­as web modernas.',
    'soft_skills' => ['Trabajo en equipo', 'Comunicación', 'Liderazgo'],
    'hard_skills' => ['PHP', 'JavaScript', 'MySQL', 'React'],
    'idiomas' => [
      ['idioma' => 'Espaí±ol', 'nivel' => 'Nativo'],
      ['idioma' => 'Inglés', 'nivel' => 'Avanzado']
    ],
    'intereses' => ['Tecnologí­a', 'Deportes', 'Lectura'],
    'referencias' => [],
    'disponibilidad' => 'Inmediata',
    'puestos_anteriores' => [
      [
        'puesto' => 'Desarrollador Senior',
        'empresa' => 'TechCorp S.L.',
        'fecha_inicio' => '2020-03',
        'fecha_fin' => '2023-12',
        'descripcion' => 'Desarrollo de aplicaciones web con tecnologí­as modernas.',
        'responsabilidades' => ['Liderazgo técnico', 'Mentoring de desarrolladores junior']
      ]
    ],
    'educacion' => [
      [
        'titulo' => 'Ingenierí­a Informí¡tica',
        'institucion' => 'Universidad Complutense Madrid',
        'fecha_inicio' => '2014-09',
        'fecha_fin' => '2018-06',
        'descripcion' => 'Especialización en desarrollo web y bases de datos'
      ]
    ],
    'certificaciones' => [
      [
        'nombre' => 'AWS Solutions Architect',
        'organizacion' => 'Amazon Web Services',
        'fecha' => '2022-08',
        'descripcion' => 'Certificación en arquitectura cloud'
      ]
    ],
    'proyectos' => [
      [
        'nombre' => 'Sistema de gestión de talento',
        'descripcion' => 'Plataforma web para gestión de recursos humanos',
        'tecnologias' => ['PHP', 'React', 'MySQL'],
        'fecha_inicio' => '2023-01',
        'fecha_fin' => '2023-06',
        'url' => 'https://github.com/juanperez/talent-system'
      ]
    ]
  ];

  /**
   * Normaliza y valida datos de entrada
   *
   * @param array $input Datos de entrada (pueden venir de IA o formulario)
   * @return array Datos normalizados y validados
   */
  public static function normalize(array $input): array
  {
    $normalized = self::TEMPLATE;

    // Sanitizar campos bí¡sicos de texto
    $textFields = [
      'nombre',
      'email',
      'telefono',
      'ubicacion_actual',
      'fecha_nacimiento',
      'portfolio',
      'linkedin',
      'resumen_profesional',
      'referencias',
      'disponibilidad',
      'cv_original_file',
      'cv_text_file',
      'cv_json_file'
    ];

    foreach ($textFields as $field) {
      if (isset($input[$field])) {
        // Solo sanitizar si es string, si es array dejarlo como estí¡
        if (is_string($input[$field])) {
          $normalized[$field] = self::sanitizeText($input[$field]);
        } else {
          $normalized[$field] = $input[$field];
        }
      }
    }

    // Validar y normalizar data_source
    if (
      isset($input['data_source']) &&
      in_array($input['data_source'], ['ai_processing', 'manual_entry', 'hybrid'])
    ) {
      $normalized['data_source'] = $input['data_source'];
    }

    // Normalizar arrays de strings
    $arrayFields = [
      'otras_redes',
      'soft_skills',
      'hard_skills',
      'intereses',
      'certificaciones',
      'habilidades_adicionales'
    ];

    foreach ($arrayFields as $field) {
      if (isset($input[$field]) && is_array($input[$field])) {
        $normalized[$field] = array_map(
          [self::class, 'sanitizeText'],
          array_filter($input[$field])
        );
      }
    }

    // Normalizar idiomas simples
    if (isset($input['idiomas']) && is_array($input['idiomas'])) {
      $normalized['idiomas'] = array_map(function ($idioma) {
        if (is_array($idioma)) {
          return [
            'idioma' => self::sanitizeText($idioma['idioma'] ?? ''),
            'nivel' => self::sanitizeText($idioma['nivel'] ?? '')
          ];
        }
        return ['idioma' => self::sanitizeText($idioma), 'nivel' => ''];
      }, $input['idiomas']);
    }

    // Normalizar experiencias laborales
    if (isset($input['puestos_anteriores']) && is_array($input['puestos_anteriores'])) {
      $normalized['puestos_anteriores'] = array_map(function ($puesto) {
        return [
          'puesto' => self::sanitizeText($puesto['puesto'] ?? ''),
          'empresa' => self::sanitizeText($puesto['empresa'] ?? ''),
          'fecha_inicio' => self::sanitizeDate($puesto['fecha_inicio'] ?? ''),
          'fecha_fin' => self::sanitizeDate($puesto['fecha_fin'] ?? ''),
          'descripcion' => self::sanitizeText($puesto['descripcion'] ?? ''),
          'responsabilidades' => isset($puesto['responsabilidades']) && is_array($puesto['responsabilidades'])
            ? array_map([self::class, 'sanitizeText'], $puesto['responsabilidades'])
            : [],
          'ubicacion' => self::sanitizeText($puesto['ubicacion'] ?? ''),
          'actual' => (bool)($puesto['actual'] ?? false)
        ];
      }, $input['puestos_anteriores']);
    }

    // Normalizar educación
    if (isset($input['educacion']) && is_array($input['educacion'])) {
      $normalized['educacion'] = array_map(function ($edu) {
        return [
          'titulo' => self::sanitizeText($edu['titulo'] ?? ''),
          'campo_estudio' => self::sanitizeText($edu['campo_estudio'] ?? ''),
          'institucion' => self::sanitizeText($edu['institucion'] ?? ''),
          'fecha_inicio' => self::sanitizeDate($edu['fecha_inicio'] ?? ''),
          'fecha_fin' => self::sanitizeDate($edu['fecha_fin'] ?? ''),
          'nivel_educativo' => self::sanitizeText($edu['nivel_educativo'] ?? ''),
          'descripcion' => self::sanitizeText($edu['descripcion'] ?? '')
        ];
      }, $input['educacion']);
    }

    // Normalizar certificaciones detalle
    if (isset($input['certificaciones_detalle']) && is_array($input['certificaciones_detalle'])) {
      $normalized['certificaciones_detalle'] = array_map(function ($cert) {
        return [
          'nombre_certificacion' => self::sanitizeText($cert['nombre_certificacion'] ?? ''),
          'emisor' => self::sanitizeText($cert['emisor'] ?? ''),
          'fecha_emision' => self::sanitizeDate($cert['fecha_emision'] ?? ''),
          'fecha_expiracion' => self::sanitizeDate($cert['fecha_expiracion'] ?? '')
        ];
      }, $input['certificaciones_detalle']);
    }

    // Normalizar idiomas detalle
    if (isset($input['idiomas_detalle']) && is_array($input['idiomas_detalle'])) {
      $normalized['idiomas_detalle'] = array_map(function ($idioma) {
        return [
          'idioma' => self::sanitizeText($idioma['idioma'] ?? ''),
          'nivel_competencia' => self::sanitizeText($idioma['nivel_competencia'] ?? '')
        ];
      }, $input['idiomas_detalle']);
    }

    // Normalizar proyectos
    if (isset($input['proyectos']) && is_array($input['proyectos'])) {
      $normalized['proyectos'] = array_map(function ($proyecto) {
        return [
          'nombre' => self::sanitizeText($proyecto['nombre'] ?? ''),
          'descripcion' => self::sanitizeText($proyecto['descripcion'] ?? ''),
          'tecnologias' => isset($proyecto['tecnologias']) && is_array($proyecto['tecnologias'])
            ? array_map([self::class, 'sanitizeText'], $proyecto['tecnologias'])
            : [],
          'fecha_inicio' => self::sanitizeDate($proyecto['fecha_inicio'] ?? ''),
          'fecha_fin' => self::sanitizeDate($proyecto['fecha_fin'] ?? ''),
          'url' => self::sanitizeUrl($proyecto['url'] ?? '')
        ];
      }, $input['proyectos']);
    }

    // Normalizar referencias detalle
    if (isset($input['referencias_detalle']) && is_array($input['referencias_detalle'])) {
      $normalized['referencias_detalle'] = array_map(function ($ref) {
        return [
          'nombre_referencia' => self::sanitizeText($ref['nombre_referencia'] ?? ''),
          'empresa_referencia' => self::sanitizeText($ref['empresa_referencia'] ?? ''),
          'email_referencia' => self::sanitizeEmail($ref['email_referencia'] ?? ''),
          'telefono_referencia' => self::sanitizeText($ref['telefono_referencia'] ?? ''),
          'notas' => self::sanitizeText($ref['notas'] ?? '')
        ];
      }, $input['referencias_detalle']);
    }

    // Normalizar routing
    if (isset($input['routing']) && is_array($input['routing'])) {
      $routing = $input['routing'];
      $normalized['routing'] = [
        'categoria_departamento_id' => isset($routing['categoria_departamento_id'])
          ? (int)$routing['categoria_departamento_id'] : null,
        'departamento_id' => isset($routing['departamento_id'])
          ? (int)$routing['departamento_id'] : null,
        'reclutador_id' => self::sanitizeText($routing['reclutador_id'] ?? ''),
        'fuente' => in_array($routing['fuente'] ?? '', ['ai', 'manual'])
          ? $routing['fuente'] : 'manual',
        'razon' => self::sanitizeText($routing['razon'] ?? ''),
        'fecha_asignacion' => self::sanitizeDateTime($routing['fecha_asignacion'] ?? '')
      ];
    }

    return $normalized;
  }

  /**
   * Sanitiza texto plano
   */
  private static function sanitizeText(string $text): string
  {
    return trim(htmlspecialchars(strip_tags($text), ENT_QUOTES, 'UTF-8'));
  }

  /**
   * Sanitiza email
   */
  private static function sanitizeEmail(string $email): string
  {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
  }

  /**
   * Sanitiza URL
   */
  private static function sanitizeUrl(string $url): string
  {
    $url = filter_var($url, FILTER_SANITIZE_URL);
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
  }

  /**
   * Sanitiza fecha (YYYY-MM-DD)
   */
  private static function sanitizeDate(string $date): string
  {
    if (empty($date)) {
      return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
      return '';
    }

    return date('Y-m-d', $timestamp);
  }

  /**
   * Sanitiza fecha y hora (YYYY-MM-DD HH:MM:SS)
   */
  private static function sanitizeDateTime(string $datetime): string
  {
    if (empty($datetime)) {
      return '';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
      return '';
    }

    return date('Y-m-d H:i:s', $timestamp);
  }

  /**
   * Valida estructura y contenido del CV según el contrato
   *
   * @param array $data Datos del CV a validar
   * @return array Array de errores, vací­o si todo estí¡ correcto ['campo'=>'motivo']
   */
  public static function validate(array $data): array
  {
    $errors = [];

    // Validar nombre obligatorio
    if (empty($data['nombre'])) {
      $errors['nombre'] = 'El nombre es obligatorio';
    }

    // Validar email RFC
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'Formato de email inví¡lido';
    }

    // Validar fechas
    $dateFields = ['fecha_nacimiento'];
    foreach ($dateFields as $field) {
      if (!empty($data[$field]) && !self::isValidDate($data[$field])) {
        $errors[$field] = 'Formato de fecha inví¡lido (use YYYY-MM-DD)';
      }
    }

    // Validar URLs
    $urlFields = ['portfolio', 'linkedin'];
    foreach ($urlFields as $field) {
      if (!empty($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_URL)) {
        $errors[$field] = 'URL inví¡lida';
      }
    }

    // Validar longitudes de strings
    $stringLimits = [
      'nombre' => 100,
      'telefono' => 20,
      'ubicacion_actual' => 100,
      'resumen_profesional' => 2000,
      'referencias' => 2000,
      'disponibilidad' => 100
    ];

    foreach ($stringLimits as $field => $limit) {
      if (!empty($data[$field]) && strlen($data[$field]) > $limit) {
        $errors[$field] = "El campo excede el lí­mite de {$limit} caracteres";
      }
    }

    // Validar arrays no excedan lí­mites
    $arrayLimits = [
      'otras_redes' => 10,
      'soft_skills' => 50,
      'hard_skills' => 50,
      'idiomas' => 20,
      'intereses' => 30,
      'puestos_anteriores' => 20,
      'educacion' => 10,
      'certificaciones' => 20,
      'proyectos' => 15
    ];

    foreach ($arrayLimits as $field => $limit) {
      if (!empty($data[$field]) && is_array($data[$field]) && count($data[$field]) > $limit) {
        $errors[$field] = "El campo excede el lí­mite de {$limit} elementos";
      }
    }

    // Validar fechas en experiencias laborales
    if (!empty($data['puestos_anteriores']) && is_array($data['puestos_anteriores'])) {
      foreach ($data['puestos_anteriores'] as $index => $puesto) {
        if (!empty($puesto['fecha_inicio']) && !self::isValidDateYearMonth($puesto['fecha_inicio'])) {
          $errors["puestos_anteriores.{$index}.fecha_inicio"] = 'Formato de fecha inví¡lido (use YYYY-MM)';
        }
        if (!empty($puesto['fecha_fin']) && !self::isValidDateYearMonth($puesto['fecha_fin'])) {
          $errors["puestos_anteriores.{$index}.fecha_fin"] = 'Formato de fecha inví¡lido (use YYYY-MM)';
        }
      }
    }

    // Validar fechas en educación
    if (!empty($data['educacion']) && is_array($data['educacion'])) {
      foreach ($data['educacion'] as $index => $edu) {
        if (!empty($edu['fecha_inicio']) && !self::isValidDateYearMonth($edu['fecha_inicio'])) {
          $errors["educacion.{$index}.fecha_inicio"] = 'Formato de fecha inví¡lido (use YYYY-MM)';
        }
        if (!empty($edu['fecha_fin']) && !self::isValidDateYearMonth($edu['fecha_fin'])) {
          $errors["educacion.{$index}.fecha_fin"] = 'Formato de fecha inví¡lido (use YYYY-MM)';
        }
      }
    }

    return $errors;
  }

  /**
   * Valida fecha completa YYYY-MM-DD
   */
  private static function isValidDate(string $date): bool
  {
    if (empty($date)) {
      return true;
    }
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
  }

  /**
   * Valida fecha aí±o-mes YYYY-MM
   */
  private static function isValidDateYearMonth(string $date): bool
  {
    if (empty($date)) {
      return true;
    }
    return (bool)preg_match('/^\d{4}-\d{2}$/', $date);
  }

  /**
   * Validación de datos mí­nimos requeridos para un CV
   */
  public static function validateMinimumData(array $data): array
  {
    $errors = [];

    // Validar campos mí­nimos obligatorios
    if (empty($data['nombre'])) {
      $errors['nombre'] = 'El nombre es obligatorio';
    }

    if (empty($data['email'])) {
      $errors['email'] = 'El email es obligatorio';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'Formato de email inví¡lido';
    }

    // Validar que tenga al menos una forma de contacto
    if (empty($data['telefono']) && empty($data['email'])) {
      $errors['contacto'] = 'Debe proporcionar al menos email o teléfono';
    }

    return $errors;
  }
}
