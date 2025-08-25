<?php

declare(strict_types=1);

/**
 * Bootstrap puente para endpoints bajo public/api/*
 *
 * - Carga el bootstrap principal (autoloader, .env, CORS, manejo global).
 * - Define alias de compatibilidad para middlewares si algún endpoint legacy los invoca
 *   con espacio de nombres distinto.
 * - NO ejecuta middlewares (JWT/CSRF) ni bloquea Authorization aquí.
 *   Eso se hace en cada endpoint o en el router antes de despachar.
 */

if (!defined('API_BOOTSTRAPPED')) {
    define('API_BOOTSTRAPPED', true);

    // 1) Cargar SIEMPRE el bootstrap principal (define BASE_PATH, CORS, autoloader, etc.)
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

    // 2) Compatibilidad de nombres para JWTMiddleware
    $hasUtilsJwt  = class_exists('\Utils\JWTMiddleware', true);
    $hasGlobalJwt = class_exists('\JWTMiddleware', false);
    if ($hasUtilsJwt && !$hasGlobalJwt) {
        class_alias('\Utils\JWTMiddleware', '\JWTMiddleware');
    } elseif ($hasGlobalJwt && !class_exists('\Utils\JWTMiddleware', false)) {
        class_alias('\JWTMiddleware', '\Utils\JWTMiddleware');
    }

    // 3) Compatibilidad de nombres para CsrfMiddleware
    if (class_exists('\Security\CsrfMiddleware', true) && !class_exists('\CsrfMiddleware', false)) {
        class_alias('\Security\CsrfMiddleware', '\CsrfMiddleware');
    }

    // 4) Preflight OPTIONS:
    //    El bootstrap principal ya debería haber emitido CORS adecuados.
    //    Aquí sólo respondemos 204 si llega un OPTIONS directo a /api/*.
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}