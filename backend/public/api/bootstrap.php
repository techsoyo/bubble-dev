<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 1);
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;


// Bootstrap puente para endpoints bajo public/api/*
// Redirige a config/bootstrap.php real
$real = __DIR__ . '/../../../config/bootstrap.php';
if (file_exists($real)) {
    require_once $real;
}
// Inicializar RequestId temprano
if (class_exists('Utils\\RequestId')) {
    Utils\RequestId::init();
}
