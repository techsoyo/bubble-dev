<?php declare(strict_types=1);

namespace Services\FileService.php\Services;

use Utils\Logger;

/**
 * Servicio seguro para manejo de archivos y uploads
 *
 * Implementa mÃºltiples capas de seguridad para prevenir:
 * - Upload de archivos maliciosos
 * - Path traversal attacks
 * - EjecuciÃ³n de cÃ³digo
 * - Ataques de tipo MIME sniffing
 *
 * @version 2.0.0
 * @author Bubble of Talents Security Team
 */
class FileService
{
    /**
     * Tipos MIME permitidos por categorÃ­a
     */
    private static $allowedMimeTypes = [
        'images' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ],
        'documents' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ],
        'cv' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ]
    ];

    /**
     * Extensiones permitidas por categorÃ­a
     */
    private static $allowedExtensions = [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'documents' => ['pdf', 'doc', 'docx', 'txt'],
        'cv' => ['pdf', 'doc', 'docx']
    ];

    /**
     * TamaÃ±os mÃ¡ximos por categorÃ­a (en bytes)
     */
    private static $maxSizes = [
        'images' => 5242880,    // 5MB
        'documents' => 10485760, // 10MB
        'cv' => 10485760        // 10MB
    ];

    /**
     * Directorio base de uploads
     */
    private static $uploadBasePath = null;

    /**
     * Inicializa el servicio de archivos
     */
    public static function init()
    {
        self::$uploadBasePath = realpath(__DIR__ . '/../../uploads') ?: __DIR__ . '/../../uploads';

        // Crear directorios si no existen
        $directories = ['cvs', 'images', 'documents', 'temp', 'quarantine'];
        foreach ($directories as $dir) {
            $fullPath = self::$uploadBasePath . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0750, true);
            }
        }
    }

    /**
     * Sube un archivo de manera segura
     *
     * @param array $file Array $_FILES del archivo
     * @param string $category CategorÃ­a del archivo
     * @param string|null $customName Nombre personalizado (opcional)
     * @return array Resultado del upload
     */
    public static function uploadFile($file, $category, $customName = null)
    {
        try {
            self::init();

            // Validaciones bÃ¡sicas
            if (!self::validateBasicFile($file)) {
                return ['success' => false, 'error' => 'Archivo invÃ¡lido'];
            }

            // Validar categorÃ­a
            if (!isset(self::$allowedMimeTypes[$category])) {
                return ['success' => false, 'error' => 'CategorÃ­a de archivo no vÃ¡lida'];
            }

            // Validaciones de seguridad
            $validationResult = self::validateFileSecurely($file, $category);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            // Generar nombre seguro
            $safeFileName = self::generateSafeFileName($file, $customName);

            // Determinar ruta de destino
            $destinationPath = self::$uploadBasePath . DIRECTORY_SEPARATOR . $category . 's' . DIRECTORY_SEPARATOR . $safeFileName;

            // Mover archivo con validaciÃ³n adicional
            if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
                Logger::error('Error al mover archivo uploaded', [
                    'file' => $file['name'],
                    'destination' => $destinationPath
                ]);
                return ['success' => false, 'error' => 'Error al guardar archivo'];
            }

            // Establecer permisos seguros
            chmod($destinationPath, 0644);

            // ValidaciÃ³n post-upload
            if (!self::validateUploadedFile($destinationPath, $category)) {
                unlink($destinationPath);
                return ['success' => false, 'error' => 'Archivo fallÃ³ validaciÃ³n post-upload'];
            }

            // Generar informaciÃ³n del archivo
            $fileInfo = [
                'original_name' => $file['name'],
                'safe_name' => $safeFileName,
                'size' => $file['size'],
                'mime_type' => mime_content_type($destinationPath),
                'category' => $category,
                'path' => $destinationPath,
                'url' => self::getFileUrl($category, $safeFileName),
                'uploaded_at' => date('Y-m-d H:i:s')
            ];

            Logger::info('Archivo subido exitosamente', [
                'file' => $safeFileName,
                'category' => $category,
                'size' => $file['size']
            ]);

            return ['success' => true, 'file' => $fileInfo];
        } catch (\Exception $e) {
            Logger::error('Error en upload de archivo', [], $e);
            return ['success' => false, 'error' => 'Error interno al subir archivo'];
        }
    }

    /**
     * Descarga un archivo de manera segura
     *
     * @param string $fileName Nombre del archivo
     * @param string $category CategorÃ­a del archivo
     * @return bool True si la descarga fue exitosa
     */
    public static function downloadFile($fileName, $category)
    {
        try {
            self::init();

            // Validar entrada
            if (!self::isValidFileName($fileName) || !isset(self::$allowedMimeTypes[$category])) {
                Logger::security('Intento de descarga con parÃ¡metros invÃ¡lidos', [
                    'file' => $fileName,
                    'category' => $category
                ]);
                return false;
            }

            $filePath = self::$uploadBasePath . DIRECTORY_SEPARATOR . $category . 's' . DIRECTORY_SEPARATOR . $fileName;

            // Verificar que el archivo existe y estÃ¡ en el directorio correcto
            if (!file_exists($filePath) || !self::isPathSafe($filePath, $category)) {
                Logger::security('Intento de acceso a archivo no vÃ¡lido', [
                    'file' => $fileName,
                    'path' => $filePath
                ]);
                return false;
            }

            // Obtener informaciÃ³n del archivo
            $mimeType = mime_content_type($filePath);
            $fileSize = filesize($filePath);

            // Establecer headers de seguridad para descarga
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . $fileSize);
            header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Leer y enviar archivo
            readfile($filePath);

            Logger::info('Archivo descargado', [
                'file' => $fileName,
                'category' => $category
            ]);

            return true;
        } catch (\Exception $e) {
            Logger::error('Error en descarga de archivo', ['file' => $fileName], $e);
            return false;
        }
    }

    /**
     * Elimina un archivo de manera segura
     *
     * @param string $fileName Nombre del archivo
     * @param string $category CategorÃ­a del archivo
     * @return bool True si se eliminÃ³ correctamente
     */
    public static function deleteFile($fileName, $category)
    {
        try {
            self::init();

            if (!self::isValidFileName($fileName) || !isset(self::$allowedMimeTypes[$category])) {
                return false;
            }

            $filePath = self::$uploadBasePath . DIRECTORY_SEPARATOR . $category . 's' . DIRECTORY_SEPARATOR . $fileName;

            if (!file_exists($filePath) || !self::isPathSafe($filePath, $category)) {
                return false;
            }

            if (unlink($filePath)) {
                Logger::info('Archivo eliminado', [
                    'file' => $fileName,
                    'category' => $category
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Error al eliminar archivo', ['file' => $fileName], $e);
            return false;
        }
    }

    /**
     * Valida un archivo bÃ¡sicamente
     *
     * @param array $file Array del archivo
     * @return bool True si es vÃ¡lido
     */
    private static function validateBasicFile($file)
    {
        // Verificar que no hay errores de upload
        if (!isset($file['error']) || is_array($file['error'])) {
            return false;
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return false;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return false;
            default:
                return false;
        }

        // Verificar que el archivo fue subido vÃ­a HTTP POST
        if (!is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        return true;
    }

    /**
     * Valida un archivo de manera segura
     *
     * @param array $file Array del archivo
     * @param string $category CategorÃ­a
     * @return array Resultado de validaciÃ³n
     */
    private static function validateFileSecurely($file, $category)
    {
        // Validar tamaÃ±o
        if ($file['size'] > self::$maxSizes[$category]) {
            $maxSizeMB = round(self::$maxSizes[$category] / 1024 / 1024, 2);
            return ['success' => false, 'error' => "Archivo excede el tamaÃ±o mÃ¡ximo de {$maxSizeMB}MB"];
        }

        // Validar extensiÃ³n
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::$allowedExtensions[$category])) {
            return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
        }

        // Validar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::$allowedMimeTypes[$category])) {
            Logger::security('Intento de upload con MIME type no vÃ¡lido', [
                'file' => $file['name'],
                'detected_mime' => $mimeType,
                'category' => $category
            ]);
            return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
        }

        // ValidaciÃ³n adicional de contenido
        if (!self::validateFileContent($file['tmp_name'], $mimeType)) {
            return ['success' => false, 'error' => 'Contenido del archivo no vÃ¡lido'];
        }

        return ['success' => true];
    }

    /**
     * Valida el contenido del archivo
     *
     * @param string $filePath Ruta del archivo
     * @param string $mimeType Tipo MIME
     * @return bool True si es vÃ¡lido
     */
    private static function validateFileContent($filePath, $mimeType)
    {
        // Leer los primeros bytes para verificar magic numbers
        $fileHandle = fopen($filePath, 'rb');
        if (!$fileHandle) {
            return false;
        }

        $header = fread($fileHandle, 8);
        fclose($fileHandle);

        $magicNumbers = [
            'image/jpeg' => ["\xFF\xD8\xFF"],
            'image/png' => ["\x89\x50\x4E\x47"],
            'image/gif' => ["\x47\x49\x46\x38"],
            'application/pdf' => ["\x25\x50\x44\x46"]
        ];

        if (isset($magicNumbers[$mimeType])) {
            foreach ($magicNumbers[$mimeType] as $magic) {
                if (strpos($header, $magic) === 0) {
                    return true;
                }
            }
            return false;
        }

        // Para tipos no especÃ­ficos, verificar que no contenga cÃ³digo ejecutable
        $content = file_get_contents($filePath, false, null, 0, 1024);

        // Buscar patrones sospechosos
        $suspiciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                Logger::security('Contenido sospechoso detectado en archivo', [
                    'pattern' => $pattern,
                    'mime_type' => $mimeType
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Valida un archivo despuÃ©s del upload
     *
     * @param string $filePath Ruta del archivo
     * @param string $category CategorÃ­a
     * @return bool True si es vÃ¡lido
     */
    private static function validateUploadedFile($filePath, $category)
    {
        // Verificar que el archivo se subiÃ³ correctamente
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            return false;
        }

        // Re-validar tipo MIME
        $mimeType = mime_content_type($filePath);
        if (!in_array($mimeType, self::$allowedMimeTypes[$category])) {
            return false;
        }

        // Validar que el path es seguro
        return self::isPathSafe($filePath, $category);
    }

    /**
     * Genera un nombre de archivo seguro
     *
     * @param array $file Array del archivo
     * @param string|null $customName Nombre personalizado
     * @return string Nombre seguro
     */
    private static function generateSafeFileName($file, $customName = null)
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($customName) {
            // Sanitizar nombre personalizado
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $customName);
            $safeName = substr($safeName, 0, 100); // Limitar longitud
        } else {
            // Generar nombre Ãºnico
            $safeName = bin2hex(random_bytes(16));
        }

        return $safeName . '.' . $extension;
    }

    /**
     * Verifica si un nombre de archivo es vÃ¡lido
     *
     * @param string $fileName Nombre del archivo
     * @return bool True si es vÃ¡lido
     */
    private static function isValidFileName($fileName)
    {
        // Verificar caracteres peligrosos
        if (preg_match('/[^\w\-.]/', $fileName)) {
            return false;
        }

        // Verificar path traversal
        if (strpos($fileName, '..') !== false || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si un path es seguro
     *
     * @param string $filePath Ruta del archivo
     * @param string $category CategorÃ­a
     * @return bool True si es seguro
     */
    private static function isPathSafe($filePath, $category)
    {
        $realPath = realpath($filePath);
        $expectedDir = realpath(self::$uploadBasePath . DIRECTORY_SEPARATOR . $category . 's');

        return $realPath && $expectedDir && strpos($realPath, $expectedDir) === 0;
    }

    /**
     * Obtiene la URL de un archivo
     *
     * @param string $category CategorÃ­a
     * @param string $fileName Nombre del archivo
     * @return string URL del archivo
     */
    private static function getFileUrl($category, $fileName)
    {
        $baseUrl = config('UPLOADS_BASE_URL', '/uploads');
        return $baseUrl . '/' . $category . 's/' . $fileName;
    }

    /**
     * LEGACY METHOD: Mantener compatibilidad con cÃ³digo existente
     * Sube un archivo al servidor (mÃ©todo legacy)
     *
     * @param array $file InformaciÃ³n del archivo ($_FILES)
     * @param string $destinationPath Ruta de destino
     * @param array $allowedTypes Tipos MIME permitidos
     * @param int $maxSize TamaÃ±o mÃ¡ximo en bytes
     * @return array InformaciÃ³n del archivo subido
     * @throws \Exception Si hay un error en la subida
     */
    public function uploadFile_legacy($file, $destinationPath, $allowedTypes = [], $maxSize = 5242880)
    {
        // Redirigir al mÃ©todo seguro
        $category = 'documents'; // CategorÃ­a por defecto
        $result = self::uploadFile($file, $category);

        if (!$result['success']) {
            throw new \Exception($result['error']);
        }

        return $result['file'];
    }

    /**
     * LEGACY METHOD: Elimina un archivo
     *
     * @param string $filePath Ruta del archivo
     * @return bool True si se eliminÃ³ correctamente, false en caso contrario
     */
    public function deleteFile_legacy($filePath)
    {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}
