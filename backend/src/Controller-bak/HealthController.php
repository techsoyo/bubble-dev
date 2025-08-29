<?php

declare(strict_types=1);

namespace Controllers;

/**
 * Health Check Controller
 *
 * Proporciona endpoints para verificar el estado del sistema
 * Implementa manejo robusto de errores y validación
 * 
 * @package Controllers
 * @version 2.0.0
 */
class HealthController extends BaseController
{
  /**
   * Comprueba si el servidor estí¡ funcionando correctamente
   * 
   * Este endpoint nunca debe fallar y siempre debe retornar una respuesta ví¡lida
   * 
   * @param array $params Parí¡metros de la ruta (no usados)
   * @return void
   */
  public function check($params = []): void
  {
    try {
      // Validar que el método sea GET
      if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $this->error('Health check only accepts GET requests', null, 405);
        return;
      }

      // Información bí¡sica del sistema que siempre debe estar disponible
      $healthData = [
        'status' => 'healthy',
        'service' => 'Bubble of Talents API',
        'version' => '1.0.0',
        'timestamp' => date('c'),
        'environment' => getenv('APP_ENV') ?: 'development',
        'router' => 'AltoRouter',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
        'php_version' => PHP_VERSION,
        'memory_usage' => [
          'current' => memory_get_usage(true),
          'peak' => memory_get_peak_usage(true)
        ]
      ];

      // Verificar conexión a la base de datos de forma segura
      try {
        $database = \Utils\Database::getInstance();
        $pdo = $database->getConnection();

        // Realizar consulta simple y segura
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();

        $healthData['database'] = [
          'status' => 'connected',
          'test_query' => $result['test'] === 1 ? 'passed' : 'failed'
        ];
      } catch (\PDOException $e) {
        // Error de base de datos no es crí­tico para health check
        $healthData['database'] = [
          'status' => 'disconnected',
          'error' => 'Database connection failed'
        ];

        // Log el error pero no fallar el health check
        error_log("Health check DB error: " . $e->getMessage());
      } catch (\Exception $e) {
        $healthData['database'] = [
          'status' => 'error',
          'error' => 'Database check failed'
        ];

        error_log("Health check general error: " . $e->getMessage());
      }

      // Verificar componentes adicionales del sistema
      $healthData['components'] = [
        'composer_autoload' => file_exists(__DIR__ . '/../../vendor/autoload.php'),
        'config_files' => file_exists(__DIR__ . '/../config/bootstrap.php'),
        'upload_directory' => is_dir(__DIR__ . '/../../public/uploads') && is_writable(__DIR__ . '/../../public/uploads'),
        'temp_directory' => sys_get_temp_dir() && is_writable(sys_get_temp_dir())
      ];

      // Respuesta exitosa con toda la información
      $this->success('API is running correctly', $healthData, 200);
    } catch (\Throwable $e) {
      // Capturar cualquier error inesperado
      error_log("Critical health check error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

      // Respuesta mí­nima de emergencia - NUNCA debe fallar
      try {
        $this->error('Health check failed', [
          'error' => 'Internal system error',
          'timestamp' => date('c')
        ], 500);
      } catch (\Throwable $emergencyError) {
        // Respuesta de último recurso si todo falla
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
          'status' => 'error',
          'message' => 'Critical system failure',
          'timestamp' => date('c')
        ]);
        exit;
      }
    }
  }
}
