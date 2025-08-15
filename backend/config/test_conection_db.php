<?php
require_once __DIR__ . '/database.php';

try {
    $pdo = getDbConnection();
    echo "Conexión exitosa a la base de datos.";
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}