<?php

declare(strict_types=1);
/**
 * Bootstrap puente para endpoints bajo public/api/*
 *
 * - Carga vendor/autoload y config/bootstrap
 * - Responde OPTIONS 204
 * - Define alias de compatibilidad legacy
 * - Centraliza CSRF en métodos mutadores
 */

if (!defined('API_BOOTSTRAPPED')) {
    define('API_BOOTSTRAPPED', true);

    // 0) Autoload Composer
    $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (is_file($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'vendor/autoload.php no encontrado']);
        exit;
    }

    // 1) Bootstrap principal (env, DB, CORS, etc.)
    $mainBootstrap = __DIR__ . '/../../config/bootstrap.php';
    if (!is_file($mainBootstrap)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'config/bootstrap.php no encontrado']);
        exit;
    }
    require_once $mainBootstrap;

    // 1.b) Preflight OPTIONS
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // 2) Aliases legacy (si algún endpoint viejo usa otros namespaces)
    if (class_exists('\Middleware\CsrfMiddleware', true)) {
        if (!class_exists('\CsrfMiddleware', false)) {
            class_alias('\Middleware\CsrfMiddleware', '\CsrfMiddleware');
        }
        if (!class_exists('\Security\CsrfMiddleware', false)) {
            class_alias('\Middleware\CsrfMiddleware', '\Security\CsrfMiddleware');
        }
    }

    // 3) CSRF centralizado (solo HTTP / mutadores)
    if (PHP_SAPI !== 'cli') {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $appEnv = $_ENV['APP_ENV'] ?? 'production';

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (class_exists('\Middleware\CsrfMiddleware', false)) {
                \Middleware\CsrfMiddleware::protect();
            } elseif (class_exists('\CsrfMiddleware', false)) {
                \CsrfMiddleware::protect();
            }
        } elseif ($appEnv !== 'production') {
            if (
                class_exists('\Middleware\CsrfMiddleware', false)
                && method_exists('\Middleware\CsrfMiddleware', 'ensureToken')
            ) {
                \Middleware\CsrfMiddleware::ensureToken();
            } elseif (
                class_exists('\CsrfMiddleware', false)
                && method_exists('\CsrfMiddleware', 'ensureToken')
            ) {
                \CsrfMiddleware::ensureToken();
            }
        }
    }
}
