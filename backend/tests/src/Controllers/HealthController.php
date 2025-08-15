<?php

namespace Controllers;

/**
 * Health Check Controller
 *
 * Proporciona endpoints para verificar el estado del sistema
 */

class HealthController
{
    /**
     * Comprueba si el servidor está funcionando correctamente
     */
    public function check()
    {
        // Verificar conexión a la base de datos (opcional)
        // try {
        //     // Realizar una consulta simple para verificar la conexión
        //     $dbConfig = require_once __DIR__ . '/../config/database.php';
        //     $db = new PDO(
        //         "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']}",
        //         $dbConfig['username'],
        //         $dbConfig['password']
        //     );
        //     $stmt = $db->query("SELECT 1");
        //     $dbStatus = $stmt->fetchColumn() ? true : false;
        // } catch (Exception $e) {
        //     $dbStatus = false;
        // }

        // Respuesta simple para verificar que el servidor está en funcionamiento
        header('Content-Type: application/json');
        echo json_encode([
          'status' => 'ok',
          'timestamp' => date('Y-m-d H:i:s'),
          'version' => '1.0.0',
          // 'database' => $dbStatus ? 'connected' : 'disconnected',
        ]);
        exit;
    }
}
