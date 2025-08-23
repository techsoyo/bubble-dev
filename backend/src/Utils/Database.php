<?php

namespace Utils;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        // Cargar variables de entorno antes de acceder a ellas
        if (!function_exists('loadEnvironmentVars')) {
            require_once __DIR__ . '/../../config/config.php';
        }
        if (function_exists('loadEnvironmentVars')) {
            loadEnvironmentVars();
        }

        $dsn = getenv('DB_DSN');
        if (!$dsn) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_NAME') ?: 'bubble';
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        }
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';

        // DEBUG: Log para diagnosticar el problema
        error_log("DEBUG Database connection attempt:");
        error_log("- DSN: $dsn");
        error_log("- User: $user");
        error_log("- Pass: " . ($pass ? '[SET]' : '[EMPTY]'));

        // Validación de credenciales críticas
        if (empty($user)) {
            throw new \Exception('DB connection failed: DB_USER is not configured');
        }

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
            ]);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new \Exception('DB connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public function insert(string $table, array $data): bool
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ':' . $f, $fields);
        $sql = "INSERT INTO `$table` (" . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        return $stmt->execute();
    }

    // Puedes agregar métodos select/update/delete según necesidad
}
