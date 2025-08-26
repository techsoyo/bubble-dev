<?php declare(strict_types=1);
require_once __DIR__ . '/database.php';

try {
    $pdo = getDbConnection();
    echo "ConexiÃ³n exitosa a la base de datos.";
} catch (PDOException $e) {
    echo "Error de conexiÃ³n: " . $e->getMessage();
}