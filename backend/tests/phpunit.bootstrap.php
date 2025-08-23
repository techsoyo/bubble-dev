<?php

declare(strict_types=1);

// Definir BASE_PATH al directorio backend
if (!defined('BASE_PATH')) {
  define('BASE_PATH', realpath(__DIR__ . '/..'));
}

// Autoloader de Composer
$autoload = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
  fwrite(STDERR, "Autoloader no encontrado: $autoload\n");
  exit(1);
}
require_once $autoload;

// Cargar config mínima y DB (sin headers ni CORS ni handlers web)
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';

// Aumentar memory_limit para pruebas intensivas
@ini_set('memory_limit', '512M');

// Entorno de pruebas
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

// Prefijo de tablas opcional (ajusta si tu DB no usa prefijo)
if (!getenv('DB_TABLE_PREFIX')) {
  putenv('DB_TABLE_PREFIX=bt_');
  $_ENV['DB_TABLE_PREFIX'] = 'bt_';
  $_SERVER['DB_TABLE_PREFIX'] = 'bt_';
}

// Verificar conexión DB temprana para fallar rápido
$__db = getDbConnection();
if (!$__db) {
  fwrite(STDERR, "No se pudo conectar a la base de datos para pruebas. Revisa config/database.php y .env\n");
  exit(1);
}
