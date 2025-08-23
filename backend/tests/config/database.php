<?php

/**
 * Archivo de configuración de la base de datos
 * 
 * Proporciona la conexión PDO a la base de datos MySQL
 */

require_once __DIR__ . '/config.php';

/**
 * Obtiene una conexión PDO a la base de datos
 *
 * @return PDO
 */

function getDbConnection()
{
  static $pdo = null;
  if ($pdo !== null) {
    return $pdo;
  }

  $host = config('DB_HOST', 'localhost');
  $port = config('DB_PORT', '3306');
  $dbname = config('DB_NAME', 'bubble_talents_db');
  $username = config('DB_USER', 'root');
  $password = config('DB_PASSWORD', '');

  $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 5, // segundos
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
  ];

  try {
    $pdo = new PDO($dsn, $username, $password, $options);
  } catch (PDOException $e) {
    $msg = "Error de conexión a la base de datos.";
    if (isDevelopment()) {
      throw new PDOException($msg . " Detalle: " . $e->getMessage(), $e->getCode());
    } else {
      // En producción, no mostrar detalles ni credenciales
      error_log($msg . " Código: " . $e->getCode());
      throw new PDOException($msg . " Por favor, contacte al administrador.", 500);
    }
  }
  return $pdo;
}

/**
 * Helper global para obtener el nombre de tabla con prefijo
 * @param string $name
 * @return string
 */
if (!function_exists('T')) {
  function T($name)
  {
    $prefix = config('DB_TABLE_PREFIX', '');
    if ($prefix && strpos($name, $prefix) !== 0) {
      return $prefix . $name;
    }
    return $name;
  }
}
