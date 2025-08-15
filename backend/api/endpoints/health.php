<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 2); // endpoints → api → backend
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;
require_once __DIR__ . '/../../src/Utils/ResponseHelper.php';

use Utils\ResponseHelper as Res;

try {
    // Leer configuración de entorno
    $apiKey = getenv('OPENAI_API_KEY') ?: (function_exists('config') ? config('OPENAI_API_KEY') : null);
    $baseUrl = getenv('OPENAI_BASE') ?: (function_exists('config') ? config('OPENAI_BASE', 'https://api.openai.com') : 'https://api.openai.com');
    if (!$baseUrl) {
        $baseUrl = 'https://api.openai.com';
    }
    $baseUrl = rtrim($baseUrl, '/');

    $tempPath = sys_get_temp_dir();
    $processedPath = sys_get_temp_dir();

    if (!$apiKey) {
        Res::error('Falta OPENAI_API_KEY en entorno', 500, [
          'provider' => 'openai',
          'status' => 'blocked',
        ]);
    }

    $url = $baseUrl . '/v1/models';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . $apiKey,
      'Content-Type: application/json',
    ]);
    // Permitir ignorar verificación SSL solo en local/desarrollo
    $appEnv = getenv('APP_ENV') ?: (function_exists('config') ? config('APP_ENV', 'production') : 'production');
    if (in_array(strtolower($appEnv), ['dev', 'development', 'local'])) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $start = microtime(true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $durationMs = (int)((microtime(true) - $start) * 1000);
    curl_close($ch);

    $status = 'error';
    $details = [];
    if ($response === false) {
        $status = 'unreachable';
        $details = [
          'curl_error' => $curlError,
          'http_code' => $httpCode,
        ];
    } elseif ($httpCode === 200) {
        $status = 'connected';
        $json = json_decode($response, true);
        $modelCount = isset($json['data']) && is_array($json['data']) ? count($json['data']) : null;
        $details = [
          'http_code' => $httpCode,
          'model_count' => $modelCount,
        ];
    } elseif ($httpCode === 401) {
        $status = 'unauthorized';
        $details = [
          'http_code' => $httpCode,
          'body' => mb_substr($response, 0, 512),
        ];
    } else {
        $status = 'error';
        $details = [
          'http_code' => $httpCode,
          'body' => mb_substr($response, 0, 512),
        ];
    }

    Res::success('Health OK', [
      'module' => 'ai_module',
      'version' => '1.0.0',
      'provider' => 'openai',
      'base_url' => $baseUrl,
      'status' => $status,
      'details' => $details,
      'storage_paths' => [
        'temp' => $tempPath,
        'processed' => $processedPath,
      ],
      'response_time_ms' => $durationMs,
    ]);
} catch (\Throwable $e) {
    Res::error('Error en health check: ' . $e->getMessage(), 500, [
      'provider' => 'openai',
    ]);
}
