<?php declare(strict_types=1);


/**
 * database.php
 * ConexiÃ³n PDO segura y singleton
 */

require_once __DIR__ . '/config.php';

function getDbConnection(): PDO
{
  static $pdo = null;
  if ($pdo !== null) return $pdo;

  $host = config('DB_HOST', '127.0.0.1');
  $port = config('DB_PORT', '3306');
  $dbname = config('DB_NAME', '');
  $user = config('DB_USER', 'root');
  $pass = config('DB_PASSWORD', '');
  $charset = 'utf8mb4';

  $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
  ];

  try {
    $pdo = new PDO($dsn, $user, $pass, $options);
  } catch (\PDOException $e) {
    $msg = "Error de conexiÃ³n a la base de datos.";
    if (isDevelopment()) {
      throw new \PDOException($msg . " Detalle: " . $e->getMessage(), (int)$e->getCode());
    } else {
      error_log($msg . " CÃ³digo: " . $e->getCode());
      throw new \PDOException($msg . " Por favor contacte al administrador.", 500);
    }
  }
  return $pdo;
}

function T(string $name): string
{
  $prefix = config('DB_TABLE_PREFIX', '');
  return ($prefix && !str_starts_with($name, $prefix)) ? $prefix . $name : $name;
}
