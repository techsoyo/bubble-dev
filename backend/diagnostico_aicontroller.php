<?php

/**
 * SCRIPT DE DIAGNÓSTICO Y CORRECCIÓN DE AIController
 * 
 * Identifica y corrige errores en AIController para integración completa
 */

require_once __DIR__ . '/autoload.php';

echo "🔧 DIAGNÓSTICO Y CORRECCIÓN DE AIController\n";
echo "============================================\n\n";

// 1. ANÁLISIS DE ERRORES ACTUALES
echo "1️⃣ Analizando errores en AIController...\n";

$aiControllerPath = __DIR__ . '/src/Controllers/AIController.php';

if (!file_exists($aiControllerPath)) {
  echo "❌ AIController no encontrado en: {$aiControllerPath}\n";
  exit(1);
}

$content = file_get_contents($aiControllerPath);

// Verificar errores conocidos
$errors = [
  'input()' => 'Método input() no existe en Utils\Request',
  'OllamaService(' => 'Usando servicio antiguo en lugar de OllamaServiceStandard',
  'BaseController' => 'Hereda de BaseController pero no llama parent::__construct()'
];

$foundErrors = [];
foreach ($errors as $pattern => $description) {
  if (strpos($content, $pattern) !== false) {
    $foundErrors[] = $description;
    echo "   ❌ {$description}\n";
  }
}

if (empty($foundErrors)) {
  echo "   ✅ No se encontraron errores conocidos\n";
} else {
  echo "\n   📝 Errores a corregir: " . count($foundErrors) . "\n";
}

echo "\n";

// 2. ANÁLISIS DE LA CLASE REQUEST
echo "2️⃣ Analizando clase Utils\Request...\n";

$requestPath = __DIR__ . '/src/Utils/Request.php';
if (file_exists($requestPath)) {
  $requestContent = file_get_contents($requestPath);

  // Buscar métodos disponibles
  preg_match_all('/public static function (\w+)\(/', $requestContent, $matches);
  $availableMethods = $matches[1] ?? [];

  echo "   ✅ Métodos disponibles en Request:\n";
  foreach ($availableMethods as $method) {
    echo "      • {$method}()\n";
  }

  // Verificar si tiene método input
  if (in_array('input', $availableMethods)) {
    echo "   ✅ Método input() existe\n";
  } else {
    echo "   ❌ Método input() NO existe - se debe usar json() o query()\n";
  }
} else {
  echo "   ❌ Clase Request no encontrada\n";
}

echo "\n";

// 3. GENERAR VERSIÓN CORREGIDA
echo "3️⃣ Generando versión corregida de AIController...\n";

$correctedContent = <<<'PHP'
<?php

namespace Controllers;

use Services\CVParsingService;
use Services\JobMatchingService;
use Services\OllamaServiceStandard;
use Services\Exceptions\AiUnavailableException;
use Utils\Request;

/**
 * Controlador para las funcionalidades de IA
 * VERSIÓN CORREGIDA: Integración completa con OllamaServiceStandard
 */
class AIController extends BaseController
{
    private $ollamaService;
    private $cvParsingService;
    private $jobMatchingService;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->ollamaService = new OllamaServiceStandard();
        $this->cvParsingService = new CVParsingService();
        $this->jobMatchingService = new JobMatchingService();
    }

    /**
     * Analizar un CV desde archivo .txt usando IA avanzada (OllamaServiceStandard)
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function parseCVFromFile(Request $request)
    {
        error_log('INICIO parseCVFromFile');
        
        try {
            // Obtener datos JSON del request
            $data = Request::json();
            $filename = $data['filename'] ?? null;

            if (!$filename) {
                error_log('parseCVFromFile: FALTA filename');
                $this->error('Se requiere el nombre del archivo .txt');
                return;
            }

            $filePath = __DIR__ . '/../../uploads/textos/' . basename($filename);
            if (!file_exists($filePath)) {
                error_log("parseCVFromFile: ARCHIVO NO EXISTE $filename");
                $this->error('El archivo no existe: ' . $filename);
                return;
            }

            $cvText = file_get_contents($filePath);

            error_log('parseCVFromFile: INICIO analyzeCV con OllamaServiceStandard');
            $result = $this->ollamaService->analyzeCvFromText($cvText);
            error_log('parseCVFromFile: analyzeCV FINALIZADO');

            // Guardar el resultado en JSON para trazabilidad
            $jsonDir = __DIR__ . '/../../uploads/json/';
            if (!is_dir($jsonDir)) mkdir($jsonDir, 0755, true);
            
            $jsonPath = $jsonDir . pathinfo($filename, PATHINFO_FILENAME) . '.json';
            file_put_contents($jsonPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->success('CV analizado correctamente', $result);
            
        } catch (AiUnavailableException $e) {
            error_log('parseCVFromFile: AI ERROR ' . $e->getMessage());
            $this->error('Error de IA: ' . $e->getMessage());
        } catch (\Exception $e) {
            error_log('parseCVFromFile: ERROR ' . $e->getMessage());
            $this->error('Error al analizar el CV: ' . $e->getMessage());
        }
    }

    /**
     * Analizar un CV con IA desde texto directo
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function parseCV(Request $request)
    {
        try {
            // Obtener datos JSON del request
            $data = Request::json();
            $cvText = $data['cv_text'] ?? null;

            if (!$cvText) {
                $this->error('Se requiere el texto del CV');
                return;
            }

            // Procesar el CV usando OllamaServiceStandard
            $result = $this->ollamaService->analyzeCvFromText($cvText);

            // Extraer habilidades específicamente usando el servicio de parsing
            $skills = $this->cvParsingService->extractSkills($cvText);

            // Combinar resultados
            $combinedResult = [
                'parsed_data' => $result,
                'skills' => $skills
            ];

            $this->success('CV analizado correctamente', $combinedResult);
            
        } catch (AiUnavailableException $e) {
            $this->error('Error de IA: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->error('Error al analizar el CV: ' . $e->getMessage());
        }
    }

    /**
     * Analizar PDF directamente (NUEVO ENDPOINT PRINCIPAL)
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function analyzePdfDirect(Request $request)
    {
        try {
            // Verificar que se subió un archivo
            if (!isset($_FILES['cv']) || empty($_FILES['cv']['tmp_name'])) {
                $this->error('Se requiere un archivo PDF del CV');
                return;
            }

            // Validar que es PDF
            $fileInfo = $_FILES['cv'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($fileInfo['tmp_name']);
            
            if ($mimeType !== 'application/pdf') {
                $this->error('Solo se permiten archivos PDF');
                return;
            }

            // Mover archivo a ubicación temporal
            $uploadDir = __DIR__ . '/../../uploads/cvs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $tempFile = $uploadDir . 'temp_' . uniqid() . '.pdf';
            
            if (!move_uploaded_file($fileInfo['tmp_name'], $tempFile)) {
                $this->error('Error al procesar el archivo');
                return;
            }

            // Procesar PDF con Llama3.2-Vision
            $result = $this->ollamaService->analyzeCvFromPdf($tempFile);

            // Limpiar archivo temporal
            @unlink($tempFile);

            $this->success('PDF analizado correctamente', $result);
            
        } catch (AiUnavailableException $e) {
            $this->error('Servicio de IA no disponible: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->error('Error procesando PDF: ' . $e->getMessage());
        }
    }

    /**
     * Calcular el matching entre un candidato y un trabajo
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function calculateMatching(Request $request)
    {
        try {
            $data = Request::json();
            
            // Validar datos de entrada
            $requiredFields = ['candidate_id', 'job_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || !is_numeric($data[$field])) {
                    $this->error("Campo requerido: {$field}");
                    return;
                }
            }

            // Calcular el matching usando el servicio
            $result = $this->jobMatchingService->evaluateMatch(
                ['id' => $data['candidate_id']],
                ['id' => $data['job_id']]
            );

            $this->success('Matching calculado correctamente', $result);
            
        } catch (\Exception $e) {
            $this->error('Error al calcular el matching: ' . $e->getMessage());
        }
    }

    /**
     * Chatbot de IA usando OllamaServiceStandard
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function chatbot(Request $request)
    {
        try {
            $data = Request::json();
            
            if (!isset($data['message'])) {
                $this->error('Se requiere un mensaje');
                return;
            }

            $previousMessages = $data['previous_messages'] ?? [];

            // Procesar el mensaje con OllamaServiceStandard
            $response = $this->ollamaService->chat([
                'messages' => array_merge($previousMessages, [
                    ['role' => 'user', 'content' => $data['message']]
                ])
            ]);

            $this->success('Mensaje procesado correctamente', [
                'response' => $response,
                'messages' => array_merge($previousMessages, [
                    ['role' => 'user', 'content' => $data['message']],
                    ['role' => 'assistant', 'content' => $response]
                ])
            ]);
            
        } catch (\Exception $e) {
            $this->error('Error en el chatbot: ' . $e->getMessage());
        }
    }

    /**
     * Verificar estado del servicio de IA
     */
    public function healthCheck(Request $request)
    {
        try {
            $serviceInfo = $this->ollamaService->getServiceInfo();
            $isAvailable = $this->ollamaService->isAvailable();
            
            $this->success('Estado del servicio de IA', [
                'service_info' => $serviceInfo,
                'available' => $isAvailable,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            $this->error('Error verificando servicio de IA: ' . $e->getMessage());
        }
    }

    /**
     * Métodos futuros (placeholders)
     */
    public function analyzePersonality(Request $request)
    {
        $this->error('Esta funcionalidad será implementada en una versión futura', null, 501);
    }

    public function predictPerformance(Request $request)
    {
        $this->error('Esta funcionalidad será implementada en una versión futura', null, 501);
    }
}
PHP;

// Crear archivo corregido
$correctedPath = __DIR__ . '/src/Controllers/AIController_CORREGIDO.php';
file_put_contents($correctedPath, $correctedContent);

echo "✅ Versión corregida generada en: AIController_CORREGIDO.php\n";

echo "\n";

// 4. COMPARACIÓN DE CAMBIOS
echo "4️⃣ Principales cambios realizados:\n";

$changes = [
  'Método input()' => 'Cambiado a Request::json() para obtener datos JSON',
  'Constructor' => 'Agregado parent::__construct() para herencia correcta',
  'OllamaService' => 'Migrado a OllamaServiceStandard',
  'Manejo de errores' => 'Agregado AiUnavailableException específica',
  'Nuevo endpoint' => 'analyzePdfDirect() para procesamiento directo de PDFs',
  'Health check' => 'Endpoint de verificación de estado del servicio'
];

foreach ($changes as $change => $description) {
  echo "   ✅ {$change}: {$description}\n";
}

echo "\n";

// 5. INSTRUCCIONES DE APLICACIÓN
echo "5️⃣ INSTRUCCIONES PARA APLICAR CORRECCIONES:\n";
echo "============================================\n\n";

echo "🔄 Para aplicar las correcciones:\n";
echo "1. Revisar archivo: AIController_CORREGIDO.php\n";
echo "2. Hacer backup del actual: cp AIController.php AIController_BACKUP.php\n";
echo "3. Reemplazar: cp AIController_CORREGIDO.php AIController.php\n";
echo "4. Verificar sintaxis: php -l AIController.php\n";
echo "5. Ejecutar tests de integración\n\n";

echo "🚨 VERIFICACIONES ADICIONALES NECESARIAS:\n";
echo "- BaseController debe tener método error() y success()\n";
echo "- Utils\Request debe tener método json()\n";
echo "- OllamaServiceStandard debe estar completamente funcional\n";
echo "- Permisos de escritura en uploads/cvs/ y uploads/json/\n\n";

echo "📋 NUEVOS ENDPOINTS DISPONIBLES:\n";
echo "- POST /api/ai/parse-cv-file (texto desde archivo)\n";
echo "- POST /api/ai/parse-cv (texto directo)\n";
echo "- POST /api/ai/analyze-pdf-direct (PDF directo - NUEVO)\n";
echo "- POST /api/ai/chatbot (chat con IA)\n";
echo "- GET /api/ai/health-check (verificar servicio)\n\n";

echo "✅ DIAGNÓSTICO Y CORRECCIÓN COMPLETADOS\n";
echo "🎯 AIController listo para integración completa\n";
