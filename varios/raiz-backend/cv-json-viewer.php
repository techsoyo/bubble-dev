<?php

/**
 * CV JSON Results Viewer
 * 
 * Este script permite visualizar y descargar los archivos JSON
 * generados por el procesamiento de CVs con OpenAI.
 * 
 * @package Backend\Tools
 * @version 1.0.0
 * @since 2025-01-11
 */

declare(strict_types=1);
$ROOT = __DIR__;
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;


// Bloquear en producción: herramienta de soporte solo para entornos no productivos
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

try {
  $action = $_GET['action'] ?? 'list';
  $jsonDir = __DIR__ . '/uploads/json';

  // Crear directorio si no existe
  if (!is_dir($jsonDir)) {
    mkdir($jsonDir, 0755, true);
  }

  switch ($action) {
    case 'list':
      // Listar todos los archivos JSON disponibles
      $files = [];
      $jsonFiles = glob($jsonDir . '/*.json');

      foreach ($jsonFiles as $file) {
        $filename = basename($file);
        $filesize = filesize($file);
        $modified = filemtime($file);

        // Intentar leer y parsear el contenido para obtener metadatos
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        $files[] = [
          'filename' => $filename,
          'size' => $filesize,
          'size_human' => formatBytes($filesize),
          'modified' => date('Y-m-d H:i:s', $modified),
          'candidate_name' => $data['nombre'] ?? 'No disponible',
          'candidate_email' => $data['email'] ?? 'No disponible',
          'processed_with' => $data['processed_with'] ?? 'Unknown',
          'processed_at' => $data['processed_at'] ?? 'Unknown',
          'download_url' => "?action=download&file=" . urlencode($filename),
          'view_url' => "?action=view&file=" . urlencode($filename)
        ];
      }

      echo json_encode([
        'success' => true,
        'total_files' => count($files),
        'files' => $files
      ], JSON_PRETTY_PRINT);
      break;

    case 'view':
      // Ver el contenido de un archivo JSON específico
      $filename = $_GET['file'] ?? '';
      $filePath = $jsonDir . '/' . basename($filename);

      if (!$filename || !file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Archivo no encontrado']);
        break;
      }

      $content = file_get_contents($filePath);
      $data = json_decode($content, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al parsear JSON']);
        break;
      }

      echo json_encode([
        'success' => true,
        'filename' => $filename,
        'data' => $data
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      break;

    case 'download':
      // Descargar un archivo JSON específico
      $filename = $_GET['file'] ?? '';
      $filePath = $jsonDir . '/' . basename($filename);

      if (!$filename || !file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Archivo no encontrado']);
        break;
      }

      // Configurar headers para descarga
      header('Content-Type: application/octet-stream');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      header('Content-Length: ' . filesize($filePath));

      // Enviar el archivo
      readfile($filePath);
      exit;

    case 'template':
      // Generar un archivo JSON de plantilla con la estructura completa
      $templateData = [
        "nombre" => "Juan Pérez García",
        "email" => "juan.perez@email.com",
        "telefono" => "+34 666 123 456",
        "ubicacion_actual" => "Madrid, España",
        "fecha_nacimiento" => "1990-05-15",
        "portfolio" => "https://juanperez.dev",
        "linkedin" => "https://linkedin.com/in/juanperez",
        "otras_redes" => [
          "GitHub: https://github.com/juanperez",
          "Twitter: @juanperez_dev"
        ],
        "resumen_profesional" => "Desarrollador Full Stack con 5 años de experiencia en tecnologías web modernas. Especializado en React, Node.js y bases de datos SQL/NoSQL. Passionate about clean code and agile methodologies.",
        "soft_skills" => [
          "Liderazgo de equipos",
          "Comunicación efectiva",
          "Resolución de problemas",
          "Trabajo en equipo",
          "Adaptabilidad",
          "Pensamiento crítico"
        ],
        "hard_skills" => [
          "JavaScript/TypeScript",
          "React.js/Next.js",
          "Node.js/Express",
          "Python/Django",
          "MySQL/PostgreSQL",
          "MongoDB",
          "Docker/Kubernetes",
          "AWS/Azure",
          "Git/GitHub"
        ],
        "idiomas" => [
          ["idioma" => "Español", "nivel" => "Nativo"],
          ["idioma" => "Inglés", "nivel" => "Avanzado (C1)"],
          ["idioma" => "Francés", "nivel" => "Intermedio (B2)"]
        ],
        "intereses" => [
          "Inteligencia Artificial",
          "Desarrollo de videojuegos",
          "Fotografía",
          "Escalada",
          "Lectura de ciencia ficción"
        ],
        "referencias" => [
          [
            "nombre" => "María González",
            "cargo" => "Tech Lead",
            "empresa" => "TechCorp SL",
            "email" => "maria.gonzalez@techcorp.com",
            "telefono" => "+34 611 987 654"
          ]
        ],
        "disponibilidad" => "Inmediata",
        "puestos_anteriores" => [
          [
            "puesto" => "Senior Full Stack Developer",
            "empresa" => "TechCorp SL",
            "fecha_inicio" => "2022-01-15",
            "fecha_fin" => "2024-12-31",
            "descripcion" => "Desarrollo de aplicaciones web empresariales usando React y Node.js",
            "responsabilidades" => [
              "Liderazgo técnico de equipo de 4 desarrolladores",
              "Arquitectura y desarrollo de microservicios",
              "Implementación de CI/CD pipelines",
              "Mentoring de desarrolladores junior"
            ]
          ],
          [
            "puesto" => "Full Stack Developer",
            "empresa" => "StartupXYZ",
            "fecha_inicio" => "2020-06-01",
            "fecha_fin" => "2021-12-31",
            "descripcion" => "Desarrollo de MVP para startup de fintech",
            "responsabilidades" => [
              "Desarrollo frontend con React",
              "APIs REST con Node.js",
              "Integración con servicios de pago",
              "Testing automatizado"
            ]
          ]
        ],
        "educacion" => [
          [
            "titulo" => "Ingeniería Informática",
            "institucion" => "Universidad Politécnica de Madrid",
            "fecha_inicio" => "2012-09-01",
            "fecha_fin" => "2018-06-30",
            "descripcion" => "Especialización en Ingeniería del Software. Proyecto final: Sistema de gestión hospitalaria con React y Spring Boot."
          ],
          [
            "titulo" => "Máster en Desarrollo Web",
            "institucion" => "CESDE",
            "fecha_inicio" => "2018-09-01",
            "fecha_fin" => "2019-06-30",
            "descripcion" => "Especialización en tecnologías web modernas y metodologías ágiles."
          ]
        ],
        "certificaciones" => [
          "AWS Certified Solutions Architect (2023)",
          "MongoDB Developer Certification (2022)",
          "Scrum Master Certified (2021)",
          "Google Analytics Certified (2020)"
        ],
        "proyectos" => [
          [
            "nombre" => "E-commerce Platform",
            "descripcion" => "Plataforma de comercio electrónico desarrollada con Next.js y Stripe",
            "tecnologias" => ["Next.js", "TypeScript", "Stripe", "PostgreSQL"],
            "url" => "https://github.com/juanperez/ecommerce-platform",
            "fecha" => "2023-11-01"
          ],
          [
            "nombre" => "Task Management App",
            "descripcion" => "Aplicación de gestión de tareas con autenticación y colaboración en tiempo real",
            "tecnologias" => ["React", "Socket.io", "Node.js", "MongoDB"],
            "url" => "https://github.com/juanperez/task-manager",
            "fecha" => "2023-06-01"
          ]
        ],
        "processed_with" => "OpenAI GPT-4",
        "processed_at" => date('Y-m-d H:i:s'),
        "extraction_metadata" => [
          "confidence_score" => 0.95,
          "processing_time_seconds" => 12.5,
          "tokens_used" => 1850,
          "language_detected" => "Spanish/English",
          "cv_format" => "PDF",
          "cv_pages" => 2
        ]
      ];

      echo json_encode($templateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      break;

    default:
      http_response_code(400);
      echo json_encode(['error' => 'Acción no válida']);
      break;
  }
} catch (Exception $e) {
  error_log("CV JSON Viewer Error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'error' => 'Error interno del servidor',
    'message' => $e->getMessage()
  ]);
}

/**
 * Formatea bytes en formato legible
 */
function formatBytes($size, $precision = 2)
{
  $units = ['B', 'KB', 'MB', 'GB', 'TB'];

  for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
    $size /= 1024;
  }

  return round($size, $precision) . ' ' . $units[$i];
}
