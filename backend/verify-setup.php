#!/usr/bin/env php
<?php

/**
 * Script de verificación del entorno de desarrollo y producción
 * Verifica que la configuración esté correcta para ambos entornos
 * 
 * Uso: php verify-setup.php
 */

echo "🔍 VERIFICACIÓN DEL ENTORNO - Bubble of Talents API\n";
echo "═══════════════════════════════════════════════════\n\n";

$errors = [];
$warnings = [];
$success = [];

// Verificar PHP y extensiones
echo "📋 Verificando PHP y extensiones...\n";
if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
  $success[] = "✅ PHP " . PHP_VERSION . " (requerido: ≥8.4.0)";
} else {
  $errors[] = "❌ PHP " . PHP_VERSION . " es muy antigua (requerido: ≥8.4.0)";
}

$required_extensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'mbstring'];
foreach ($required_extensions as $ext) {
  if (extension_loaded($ext)) {
    $success[] = "✅ Extensión $ext cargada";
  } else {
    $errors[] = "❌ Extensión $ext NO cargada";
  }
}

// Verificar archivos críticos
echo "\n📁 Verificando archivos y estructura...\n";
$critical_files = [
  'router.php' => 'Front-controller principal',
  'vendor/autoload.php' => 'Autoloader de Composer',
  'config/bootstrap.php' => 'Configuración bootstrap',
  'Router/AppRouter.php' => 'Router principal',
  'public/.htaccess' => 'Configuración Apache',
  'config/nginx.conf' => 'Configuración Nginx'
];

foreach ($critical_files as $file => $description) {
  if (file_exists($file)) {
    $success[] = "✅ $file ($description)";
  } else {
    $errors[] = "❌ $file NO encontrado ($description)";
  }
}

// Verificar permisos
echo "\n🔐 Verificando permisos...\n";
$writable_dirs = [
  'public/uploads' => 'Subida de archivos',
  'logs' => 'Logs del sistema (si existe)'
];

foreach ($writable_dirs as $dir => $description) {
  if (is_dir($dir)) {
    if (is_writable($dir)) {
      $success[] = "✅ $dir es escribible ($description)";
    } else {
      $errors[] = "❌ $dir NO es escribible ($description)";
    }
  } else {
    $warnings[] = "⚠️  $dir no existe ($description)";
  }
}

// Verificar configuración router
echo "\n⚙️  Verificando configuración del router...\n";
if (file_exists('router.php')) {
  $router_content = file_get_contents('router.php');

  if (strpos($router_content, 'AltoRouter') !== false || strpos($router_content, 'AppRouter') !== false) {
    $success[] = "✅ Router configurado con AltoRouter";
  } else {
    $errors[] = "❌ Router NO configurado correctamente";
  }

  if (strpos($router_content, 'CORS') !== false || strpos($router_content, 'Access-Control') !== false) {
    $success[] = "✅ Configuración CORS detectada";
  } else {
    $warnings[] = "⚠️  Configuración CORS no detectada en router";
  }
}

// Verificar .htaccess
echo "\n🌐 Verificando configuración web...\n";
if (file_exists('public/.htaccess')) {
  $htaccess_content = file_get_contents('public/.htaccess');

  if (strpos($htaccess_content, 'RewriteEngine On') !== false) {
    $success[] = "✅ .htaccess configurado con RewriteEngine";
  } else {
    $errors[] = "❌ .htaccess SIN RewriteEngine habilitado";
  }

  if (strpos($htaccess_content, '../router.php') !== false) {
    $success[] = "✅ .htaccess redirige a ../router.php";
  } else {
    $errors[] = "❌ .htaccess NO redirige correctamente al router";
  }

  if (strpos($htaccess_content, 'Access-Control') !== false) {
    $success[] = "✅ .htaccess incluye headers CORS";
  } else {
    $warnings[] = "⚠️  .htaccess sin headers CORS (dependiendo del router)";
  }
} else {
  $warnings[] = "⚠️  .htaccess no encontrado (necesario para Apache)";
}

// Verificar scripts de desarrollo
echo "\n🚀 Verificando scripts de desarrollo...\n";
$dev_scripts = [
  'start-server.ps1' => 'Script PowerShell',
  'start-server.sh' => 'Script Bash',
  'start-server.bat' => 'Script Batch'
];

foreach ($dev_scripts as $script => $description) {
  if (file_exists($script)) {
    $content = file_get_contents($script);
    if (strpos($content, '-t public') !== false || strpos($content, '-t "public"') !== false) {
      $success[] = "✅ $script usa -t public ($description)";
    } else {
      $warnings[] = "⚠️  $script no usa -t public ($description)";
    }
  } else {
    $warnings[] = "⚠️  $script no encontrado ($description)";
  }
}

// Mostrar resultados
echo "\n📊 RESUMEN DE VERIFICACIÓN\n";
echo "══════════════════════════\n";

if (!empty($success)) {
  echo "\n✅ ÉXITOS (" . count($success) . "):\n";
  foreach ($success as $item) {
    echo "   $item\n";
  }
}

if (!empty($warnings)) {
  echo "\n⚠️  ADVERTENCIAS (" . count($warnings) . "):\n";
  foreach ($warnings as $item) {
    echo "   $item\n";
  }
}

if (!empty($errors)) {
  echo "\n❌ ERRORES (" . count($errors) . "):\n";
  foreach ($errors as $item) {
    echo "   $item\n";
  }
} else {
  echo "\n🎉 ¡No se encontraron errores críticos!\n";
}

// Comando de prueba recomendado
echo "\n🧪 COMANDOS DE PRUEBA RECOMENDADOS:\n";
echo "══════════════════════════════════\n";
echo "# Arrancar servidor de desarrollo:\n";
echo "php -S localhost:8000 -t public router.php\n\n";
echo "# O usar script de ayuda:\n";
echo ".\\start-server.ps1\n\n";
echo "# Probar health endpoint:\n";
echo "curl http://localhost:8000/api/health\n\n";
echo "# Probar archivo estático:\n";
echo "curl http://localhost:8000/hello.txt\n\n";

// Exit code basado en errores
exit(empty($errors) ? 0 : 1);
