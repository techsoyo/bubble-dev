<?php

declare(strict_types=1);
/**
 * Bootstrap puente para endpoints bajo public/api/*
 *
 * - Carga el bootstrap principal (autoloader, .env, CORS, manejo global).
 * - Carga vendor/autoload.php por si el principal no lo hizo todavía.
 * - Define alias de compatibilidad para middlewares legacy.
 * - Ejecuta CSRF centralizado solo en métodos mutadores.
 */

if (!defined('API_BOOTSTRAPPED')) {
    define('API_BOOTSTRAPPED', true);

    // 0) Asegurar autoloader de Composer (idempotente)
    $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (is_file($vendorAutoload)) {
        require_once $vendorAutoload;
    }

    // 1) Cargar SIEMPRE el bootstrap principal (define BASE_PATH, CORS, env, etc.)
    $mainBootstrap = __DIR__ . '/../../config/bootstrap.php';
    if (!is_file($mainBootstrap)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Bootstrap principal no encontrado',
            'data'    => null,
        ]);
        exit;
    }
    require_once $mainBootstrap;

    // 1.b) Preflight OPTIONS: responder y salir (evita aplicar CSRF a preflight)
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // 2) Compatibilidad de nombres para JWTMiddleware (legacy <-> actual)
    $hasUtilsJwt  = class_exists('\Utils\JWTMiddleware', true);
    $hasGlobalJwt = class_exists('\JWTMiddleware', false);
    if ($hasUtilsJwt && !$hasGlobalJwt) {
        class_alias('\Utils\JWTMiddleware', '\JWTMiddleware');
    } elseif ($hasGlobalJwt && !class_exists('\Utils\JWTMiddleware', false)) {
        class_alias('\JWTMiddleware', '\Utils\JWTMiddleware');
    }

    // 3) Asegurar disponibilidad de CsrfMiddleware
    //    - Si Composer aún no la autoload, fuerza require del archivo real.
    if (!class_exists('\Middleware\CsrfMiddleware', false)) {
        $csrfFile = __DIR__ . '/../../src/Middleware/CsrfMiddleware.php';
        if (is_file($csrfFile)) {
            require_once $csrfFile;
        }
    }
    //    - Aliases de compatibilidad (Security\*, \CsrfMiddleware)
    if (class_exists('\Middleware\CsrfMiddleware', false)) {
        if (!class_exists('\CsrfMiddleware', false)) {
            class_alias('\Middleware\CsrfMiddleware', '\CsrfMiddleware');
        }
        if (!class_exists('\Security\CsrfMiddleware', false)) {
            class_alias('\Middleware\CsrfMiddleware', '\Security\CsrfMiddleware');
        }
    }

    // 4) CSRF centralizado (solo HTTP, no CLI) y solo métodos mutadores
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
            if (class_exists('\Middleware\CsrfMiddleware', false) && method_exists('\Middleware\CsrfMiddleware', 'ensureToken')) {
                \Middleware\CsrfMiddleware::ensureToken();
            } elseif (class_exists('\CsrfMiddleware', false) && method_exists('\CsrfMiddleware', 'ensureToken')) {
                \CsrfMiddleware::ensureToken();
            }
        }
    }
}
