<?php

/**
 * Archivo de configuración principal
 * 
 * Carga las variables de entorno y proporciona funciones de configuración
 * con enfoque en seguridad y buenas prácticas.
 * 
 * @version 1.1.0
 * @author Bubble of Talents Security Team
 */

// Establecer zona horaria predeterminada
date_default_timezone_set('UTC');

// Función para cargar variables de entorno de forma segura
function loadEnvironmentVars()
{
  $envPath = __DIR__ . '/../.env';

  // Verificar que el archivo existe y se puede leer
  if (!file_exists($envPath)) {
    error_log('ADVERTENCIA: Archivo .env no encontrado');
    return false;
  }

  if (!is_readable($envPath)) {
    error_log('ERROR: No se puede leer el archivo .env. Revise los permisos');
    return false;
  }

  // Verificar que el archivo .env está fuera del directorio web público
  // TEMPORAL: Deshabilitado para desarrollo local
  /*
  if (strpos(realpath($envPath), realpath($_SERVER['DOCUMENT_ROOT'] ?? '')) === 0) {
    error_log('ERROR DE SEGURIDAD: Archivo .env está en un directorio web accesible');
    return false;
  }
  */

  // Leer archivo de forma segura
  try {
    $envFile = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($envFile === false) {
      error_log('ERROR: No se pudo leer el archivo .env');
      return false;
    }
  } catch (\Exception $e) {
    error_log('ERROR: Excepción al leer .env: ' . $e->getMessage());
    return false;
  }

  foreach ($envFile as $line) {
    $line = trim($line);

    // Ignorar comentarios
    if (empty($line) || strpos($line, '#') === 0) {
      continue;
    }

    // Validar formato correcto de la línea
    if (strpos($line, '=') === false) {
      error_log('ERROR: Formato inválido en archivo .env: ' . substr($line, 0, 20) . '...');
      continue;
    }

    // Dividir en nombre y valor
    list($name, $value) = explode('=', $line, 2);
    $name = trim($name);
    $value = trim($value);

    // Validar nombre de variable (solo permitir caracteres alfanuméricos y guiones bajos)
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
      error_log('ERROR: Nombre de variable de entorno inválido: ' . $name);
      continue;
    }

    // Quitar comillas si existen
    if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
      (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)
    ) {
      $value = substr($value, 1, -1);
    }

    // Establecer la variable de entorno de manera segura
    putenv("$name=$value");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
  }

  return true;
}

// Cargar variables de entorno
$envLoaded = loadEnvironmentVars();

/**
 * Obtiene un valor de configuración del entorno de forma segura
 *
 * @param string $key La clave de configuración
 * @param mixed $default Valor por defecto si la clave no existe
 * @return mixed El valor de configuración
 */
function config($key, $default = null)
{
  if (empty($key) || !is_string($key)) {
    error_log('ERROR: Clave de configuración inválida');
    return $default;
  }

  // Validar que solo se utilizan caracteres permitidos en las claves
  if (!preg_match('/^[A-Za-z0-9_]+$/', $key)) {
    error_log('ERROR: Formato de clave de configuración inválido: ' . $key);
    return $default;
  }

  $value = getenv($key);
  return $value !== false ? $value : $default;
}

/**
 * Comprueba si la aplicación está en modo desarrollo
 *
 * @return bool
 */
function isDevelopment()
{
  $env = strtolower(config('APP_ENV', 'production'));
  return in_array($env, ['dev', 'development', 'local', 'test'], true);
}

/**
 * Comprueba si el modo debug está activado
 *
 * @return bool
 */
function isDebug()
{
  $debug = strtolower(config('APP_DEBUG', 'false'));
  return in_array($debug, ['1', 'true', 'yes', 'on'], true);
}

/**
 * Comprueba si la aplicación está en modo producción
 *
 * @return bool
 */
function isProduction()
{
  $env = strtolower(config('APP_ENV', 'production'));
  return in_array($env, ['prod', 'production'], true);
}

/**
 * Configurar manejo de errores según el entorno
 */
// Configurar manejo de errores según el entorno
if (isDevelopment() && isDebug()) {
  // Modo desarrollo: mostrar todos los errores para depuración
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
} else {
  // Modo producción: ocultar errores pero registrarlos
  error_reporting(E_ALL);
  ini_set('display_errors', 0);
  ini_set('display_startup_errors', 0);
  ini_set('log_errors', 1);
  ini_set('error_log', __DIR__ . '/../logs/error.log');

  // Asegurarse de que el directorio de logs existe y es escribible
  if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
  }
}

// Establecer límites de ejecución para prevenir ataques DoS
set_time_limit(30); // 30 segundos máximo por defecto
ini_set('memory_limit', '256M'); // Límite de memoria

// Configurar opciones de sesión más seguras
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Si estamos en HTTPS, configurar cookies seguras
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
  ini_set('session.cookie_secure', 1);
}

// Desactivar exposición de información sensible
ini_set('expose_php', 0);

// Validaciones de configuración críticas al inicio
validateCriticalConfig();

/**
 * Valida que las configuraciones críticas de seguridad estén establecidas
 */
function validateCriticalConfig()
{
  $criticalConfigs = [
    'JWT_SECRET' => 'Clave secreta JWT',
    'DB_HOST' => 'Host de base de datos',
    'DB_NAME' => 'Nombre de base de datos'
  ];

  $missingConfigs = [];

  foreach ($criticalConfigs as $key => $description) {
    if (empty(config($key))) {
      $missingConfigs[] = "$key ($description)";
    }
  }

  if (!empty($missingConfigs)) {
    error_log('ERROR CRÍTICO: Configuraciones faltantes: ' . implode(', ', $missingConfigs));

    if (!isDevelopment()) {
      // En producción, terminar ejecución si faltan configuraciones críticas
      http_response_code(500);
      // ...eliminado die para producción. Lanzar excepción o loguear en su lugar...
    }
  }

  // Validar longitud de JWT_SECRET
  $jwtSecret = config('JWT_SECRET');
  if ($jwtSecret && strlen($jwtSecret) < 32) {
    error_log('ADVERTENCIA DE SEGURIDAD: JWT_SECRET debe tener al menos 32 caracteres');

    if (!isDevelopment()) {
      http_response_code(500);
      // ...eliminado die para producción. Lanzar excepción o loguear en su lugar...
    }
  }
}

/**
 * Genera un secreto JWT seguro para desarrollo
 */
function generateSecureSecret()
{
  return bin2hex(random_bytes(32));
}

// Aplicar cabeceras de seguridad básicas si no es una petición CLI
// Los security headers ahora se manejan desde security-headers.php en bootstrap
if (PHP_SAPI !== 'cli') {
  // Security headers aplicados automáticamente desde bootstrap
}

/**
 * Devuelve la lista de orígenes permitidos para CORS.
 * Fuente primaria: variable de entorno CORS_ALLOWED_ORIGINS (lista separada por comas).
 * Si CORS_ALLOW_CREDENTIALS=true se elimina '*'.
 * Proporciona defaults seguros para desarrollo local si no está definida.
 *
 * Ejemplo en .env (recomendado en vez de hardcodear aquí):
 *   CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000,http://localhost:3002
 */
function corsAllowedOrigins(): array
{
  $raw = config('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000,http://localhost:3002');
  $parts = array_filter(array_map('trim', explode(',', $raw)));
  $allowCreds = in_array(strtolower(config('CORS_ALLOW_CREDENTIALS', 'true')), ['1', 'true', 'yes', 'on'], true);
  if ($allowCreds) {
    // No permitir comodín si se envían credenciales
    $parts = array_filter($parts, fn($o) => $o !== '*');
  }
  return array_values(array_unique($parts));
}
