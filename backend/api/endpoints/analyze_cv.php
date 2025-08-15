<?php

require_once __DIR__ . '/../bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php

/**
 * Endpoint: Análisis completo de CV con seguridad mejorada
 * POST /ai/analyze-cv
 *
 * Este endpoint permite analizar un CV tanto desde un archivo subido
 * como desde texto plano proporcionado en formato JSON.
 *
 * @version 1.1.0
 * @author Bubble of Talents Security Team
 */

// Control de acceso inicial
// Restringir métodos HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit;
}

// Cargar dependencias
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Utils/ResponseHelper.php';

use Utils\ResponseHelper;

// Configuración de seguridad para este endpoint
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB máximo para archivos
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'txt']); // Extensiones permitidas
define('MAX_TEXT_LENGTH', 1024 * 1024); // 1MB máximo para texto plano

/**
 * Valida y procesa un archivo subido
 *
 * @param array $fileData Datos del archivo ($_FILES['campo'])
 * @return array [status, message, data]
 */
function validateUploadedFile($fileData)
{
    // Comprobar errores de subida
    if ($fileData['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP.',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido por el formulario.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente.',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal.',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo.',
            UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la subida.',
        ];
        $errorMsg = isset($errors[$fileData['error']]) ?
            $errors[$fileData['error']] :
            'Error desconocido en la subida.';
        return ['status' => false, 'message' => $errorMsg, 'data' => null];
    }

    // Validar tamaño
    if ($fileData['size'] > MAX_FILE_SIZE) {
        return [
            'status' => false,
            'message' => 'El archivo excede el tamaño máximo permitido (' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB).',
            'data' => null
        ];
    }

    // Validar extensión
    $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return [
            'status' => false,
            'message' => 'Tipo de archivo no permitido. Extensiones aceptadas: ' . implode(', ', ALLOWED_EXTENSIONS),
            'data' => null
        ];
    }

    // Validar MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fileData['tmp_name']);
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain'
    ];

    if (!in_array($mime, $allowedMimes)) {
        return [
            'status' => false,
            'message' => 'Tipo de contenido no permitido.',
            'data' => null
        ];
    }

    // Mover a ubicación segura con nombre aleatorio para evitar colisiones
    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeFilename = md5(uniqid() . $fileData['name']) . '.' . $extension;
    $destination = $uploadDir . $safeFilename;

    if (!move_uploaded_file($fileData['tmp_name'], $destination)) {
        return [
            'status' => false,
            'message' => 'Error al guardar el archivo subido.',
            'data' => null
        ];
    }

    return [
        'status' => true,
        'message' => 'Archivo subido correctamente.',
        'data' => [
            'path' => $destination,
            'name' => htmlspecialchars($fileData['name'], ENT_QUOTES, 'UTF-8'),
            'type' => $mime,
            'size' => $fileData['size']
        ]
    ];
}

try {
    // Verificar autenticación (token bearer)
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
        ResponseHelper::error('No autorizado. Se requiere autenticación Bearer.', 401);
        exit;
    }

    $token = trim(substr($authHeader, 7));
    $validToken = getenv('API_TOKEN');

    if (empty($validToken) || !hash_equals($validToken, $token)) {
        ResponseHelper::error('Token de autenticación inválido.', 401);
        exit;
    }

    // Procesar según tipo de solicitud
    if (isset($_FILES['cv'])) {
        // Validar y procesar archivo subido
        $fileResult = validateUploadedFile($_FILES['cv']);

        if (!$fileResult['status']) {
            ResponseHelper::error($fileResult['message'], 400);
            exit;
        }

        // Extraer contenido según tipo de archivo
        $filePath = $fileResult['data']['path'];
        $fileType = $fileResult['data']['type'];

        // Aquí se procesaría según tipo (PDF, DOC, etc.)
        // Por ahora, solo leemos el contenido de texto plano como ejemplo
        if ($fileType === 'text/plain') {
            $cvText = file_get_contents($filePath);
        } else {
            // Para otros formatos, se asumirá una extracción básica de texto
            $cvText = 'Contenido extraído del archivo: ' . $fileResult['data']['name'];
        }
    } else {
        // Procesar entrada JSON
        $input = ResponseHelper::getJsonInput(MAX_TEXT_LENGTH);

        if ($input === null) {
            // El método getJsonInput ya envía la respuesta de error apropiada
            exit;
        }

        // Validar campos requeridos
        if (!isset($input['text_file_path']) && !isset($input['cv_text'])) {
            ResponseHelper::error('Se requiere text_file_path o cv_text', 400);
            exit;
        }

        // Procesar según tipo de entrada
        if (isset($input['text_file_path'])) {
            // Validar ruta de archivo
            $filePath = $input['text_file_path'];

            // Validación de seguridad: prevenir path traversal
            $realPath = realpath($filePath);
            $uploadsDir = realpath(__DIR__ . '/../../uploads');

            if ($realPath === false || strpos($realPath, $uploadsDir) !== 0) {
                ResponseHelper::error('Ruta de archivo no permitida', 403);
                exit;
            }

            // Verificar existencia y permisos
            if (!file_exists($filePath) || !is_readable($filePath)) {
                ResponseHelper::error('Archivo no encontrado o sin permisos: ' . basename($filePath), 404);
                exit;
            }

            // Leer contenido con límite
            $cvText = file_get_contents($filePath, false, null, 0, MAX_TEXT_LENGTH);
            if ($cvText === false) {
                ResponseHelper::error('No se pudo leer el archivo', 500);
                exit;
            }
        } else {
            // Validar y sanear texto del CV
            $cvText = $input['cv_text'];

            // Verificar longitud
            if (strlen($cvText) > MAX_TEXT_LENGTH) {
                ResponseHelper::error('El texto del CV excede el tamaño máximo permitido', 413);
                exit;
            }

            // Sanitizar texto (eliminar caracteres de control)
            $cvText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cvText);
        }
    }

    // Análisis de CV con límites de procesamiento y timeouts
    // Establecer límite de tiempo para el procesamiento
    set_time_limit(30);

    // Registrar inicio de procesamiento para monitoreo
    $startTime = microtime(true);
    $requestId = uniqid('CV-');

    try {
        // Extraer información mediante expresiones regulares seguras con timeout
        // Prevenir ataques de ReDOS (Regex Denial of Service) con límites de tiempo

        // Función para ejecutar regex con timeout
        function pregMatchWithTimeout($pattern, $subject, &$matches, $timeout = 1)
        {
            // Configurar gestión de errores para evitar bloqueos
            $previousHandler = set_error_handler(function ($severity, $message, $file, $line) {
                throw new \RuntimeException($message);
            });

            // Configurar límite de tiempo
            $previousTimeout = ini_get('max_execution_time');
            set_time_limit($timeout);

            $result = false;
            try {
                // Ejecutar la expresión regular
                $result = preg_match($pattern, $subject, $matches);
            } catch (\Exception $e) {
                error_log('Error en regex: ' . $e->getMessage());
            } finally {
                // Restaurar configuración
                set_time_limit($previousTimeout);
                set_error_handler($previousHandler);
            }

            return $result;
        }

        // Inicializar resultados
        $name = null;
        $email = null;
        $location = null;
        $phone = null;

        // Patrones de expresiones regulares optimizados y seguros
        $nameMatches = [];
        if (pregMatchWithTimeout('/Nombre[:\s]+([A-Za-zÁÉÍÓÚáéíóúñÑ\s]{2,50})\b/u', $cvText, $nameMatches, 1)) {
            $name = trim($nameMatches[1]);
            // Sanitizar: eliminar caracteres no permitidos
            $name = preg_replace('/[^\p{L}\p{M}\s\-\.]/u', '', $name);
            // Limitar longitud
            $name = substr($name, 0, 100);
        }

        $emailMatches = [];
        if (pregMatchWithTimeout('/\b([A-Za-z0-9._%+-]{1,64}@[A-Za-z0-9.-]{1,255}\.[A-Za-z]{2,6})\b/', $cvText, $emailMatches, 1)) {
            $potentialEmail = $emailMatches[1];
            // Validación adicional con filter_var
            if (filter_var($potentialEmail, FILTER_VALIDATE_EMAIL)) {
                $email = $potentialEmail;
            }
        }

        $locationMatches = [];
        if (pregMatchWithTimeout('/Ubicación[:\s]+([A-Za-zÁÉÍÓÚáéíóúñÑ\s,]{2,50})\b/u', $cvText, $locationMatches, 1)) {
            $location = trim($locationMatches[1]);
            // Sanitizar: eliminar caracteres no permitidos
            $location = preg_replace('/[^\p{L}\p{M}\s\-\.,]/u', '', $location);
            // Limitar longitud
            $location = substr($location, 0, 100);
        }

        $phoneMatches = [];
        if (pregMatchWithTimeout('/\b(\+?[\d\s\-\(\)]{8,20})\b/', $cvText, $phoneMatches, 1)) {
            $phone = trim($phoneMatches[1]);
            // Sanitizar: solo permitir dígitos, +, espacio, paréntesis y guiones
            $phone = preg_replace('/[^\d\+\s\-\(\)]/u', '', $phone);
            // Limitar longitud
            $phone = substr($phone, 0, 20);
        }

        // Registrar tiempo de procesamiento
        $processingTime = microtime(true) - $startTime;

        // Construir resultado
        $result = [
            'personal_info' => [
                'name' => $name,
                'email' => $email,
                'location' => $location,
                'phone' => $phone,
            ],
            'meta' => [
                'request_id' => $requestId,
                'processing_time' => round($processingTime, 3) . 's',
                'text_length' => strlen($cvText),
            ]
        ];

        // En modo debug/desarrollo, incluir texto procesado para depuración
        if (isDevelopment() && isDebug()) {
            $result['debug'] = [
                'raw_text_preview' => substr($cvText, 0, 500) . (strlen($cvText) > 500 ? '...' : '')
            ];
        } else {
            // En producción, no incluir el texto original por privacidad y seguridad
            $result['debug'] = null;
        }

        // Registrar análisis exitoso
        error_log("[{$requestId}] Análisis de CV completado en {$processingTime}s");

        // Devolver resultado
        ResponseHelper::success('CV analizado correctamente', $result);
    } catch (Throwable $e) {
        // Capturar cualquier error (Exception o Error)
        error_log("[{$requestId}] Error en análisis de CV: " . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());

        // Limpiar cualquier archivo temporal creado en caso de error
        if (isset($filePath) && file_exists($filePath) && strpos($filePath, __DIR__ . '/../../uploads/') === 0) {
            @unlink($filePath);
        }

        // Respuesta de error genérica para producción
        ResponseHelper::error(
            'Error al procesar el CV. Por favor, intente nuevamente o contacte soporte.',
            500,
            isDevelopment() ? ['exception' => $e->getMessage()] : ['request_id' => $requestId]
        );
    }
} catch (Throwable $e) {
    // Captura de errores a nivel global
    $errorId = uniqid('ERR-');
    error_log("[{$errorId}] Error global: " . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());

    // Respuesta de error genérica y segura
    ResponseHelper::error(
        'Ha ocurrido un error en el servidor. Por favor, intente más tarde.',
        500,
        ['error_id' => $errorId]
    );
}
