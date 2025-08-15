#!/usr/bin/env php
<?php
/**
 * Script de Verificación de Seguridad Automática
 * 
 * Verifica que todas las correcciones de seguridad se han aplicado correctamente
 * antes del despliegue en producción.
 * 
 * Uso: php security_check.php
 * 
 * @author Bubble of Talents Security Team
 * @version 1.0.0
 */

// Colors for console output
class Colors
{
  const RED = "\033[31m";
  const GREEN = "\033[32m";
  const YELLOW = "\033[33m";
  const BLUE = "\033[34m";
  const RESET = "\033[0m";
}

class SecurityChecker
{
  private array $checks = [];
  private int $passed = 0;
  private int $failed = 0;
  private int $warnings = 0;

  public function run(): void
  {
    $this->printHeader();

    // Ejecutar todas las verificaciones
    $this->checkEnvironmentConfig();
    $this->checkFilePermissions();
    $this->checkGitignore();
    $this->checkDangerousFiles();
    $this->checkSecurityHeaders();
    $this->checkDatabaseConfig();
    $this->checkFrontendSecurity();

    $this->printSummary();
  }

  private function printHeader(): void
  {
    echo Colors::BLUE . str_repeat("=", 60) . Colors::RESET . "\n";
    echo Colors::BLUE . "🔒 VERIFICACIÓN DE SEGURIDAD - BUBBLE OF TALENTS" . Colors::RESET . "\n";
    echo Colors::BLUE . str_repeat("=", 60) . Colors::RESET . "\n\n";
  }

  private function checkEnvironmentConfig(): void
  {
    echo "🔧 Verificando configuración de entorno...\n";

    // Verificar .env
    $envPath = __DIR__ . '/backend/.env';
    if (!file_exists($envPath)) {
      $this->fail("Archivo .env no encontrado");
      return;
    }

    $envContent = file_get_contents($envPath);

    // Verificar APP_ENV
    if (strpos($envContent, 'APP_ENV=production') !== false) {
      $this->pass("APP_ENV configurado para producción");
    } else {
      $this->fail("APP_ENV debe ser 'production' para despliegue");
    }

    // Verificar APP_DEBUG
    if (strpos($envContent, 'APP_DEBUG=false') !== false) {
      $this->pass("APP_DEBUG deshabilitado");
    } else {
      $this->fail("APP_DEBUG debe ser 'false' en producción");
    }

    // Verificar que no hay clave OpenAI real
    if (strpos($envContent, 'sk-proj-') !== false || strpos($envContent, 'sk-') !== false) {
      $this->fail("❌ CRÍTICO: Clave OpenAI real encontrada en .env");
    } else {
      $this->pass("Clave OpenAI placeholder configurada correctamente");
    }

    // Verificar JWT secret
    if (preg_match('/JWT_SECRET=.{32,}/', $envContent)) {
      $this->pass("JWT secret tiene longitud segura");
    } else {
      $this->warning("JWT secret debería tener al menos 32 caracteres");
    }

    echo "\n";
  }

  private function checkFilePermissions(): void
  {
    echo "📁 Verificando permisos de archivos...\n";

    $sensitiveFiles = [
      'backend/.env',
      'backend/config/config.php',
      'backend/uploads/',
      'backend/logs/'
    ];

    foreach ($sensitiveFiles as $file) {
      $fullPath = __DIR__ . '/' . $file;
      if (file_exists($fullPath)) {
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        if (is_dir($fullPath)) {
          if ($perms === '0755' || $perms === '0755') {
            $this->pass("Permisos de directorio $file: $perms ✓");
          } else {
            $this->warning("Permisos de directorio $file: $perms (recomendado: 755)");
          }
        } else {
          if ($perms === '0644') {
            $this->pass("Permisos de archivo $file: $perms ✓");
          } else {
            $this->warning("Permisos de archivo $file: $perms (recomendado: 644)");
          }
        }
      } else {
        $this->warning("Archivo/directorio no encontrado: $file");
      }
    }

    echo "\n";
  }

  private function checkGitignore(): void
  {
    echo "📝 Verificando .gitignore...\n";

    $gitignorePath = __DIR__ . '/.gitignore';
    if (!file_exists($gitignorePath)) {
      $this->fail("Archivo .gitignore no encontrado");
      return;
    }

    $gitignoreContent = file_get_contents($gitignorePath);

    $requiredEntries = [
      '.env',
      'backend/.env',
      '/backend/uploads/',
      '/backend/logs/',
      'node_modules/',
      '*.log'
    ];

    foreach ($requiredEntries as $entry) {
      if (strpos($gitignoreContent, $entry) !== false) {
        $this->pass("Entrada .gitignore encontrada: $entry");
      } else {
        $this->fail("Entrada .gitignore faltante: $entry");
      }
    }

    echo "\n";
  }

  private function checkDangerousFiles(): void
  {
    echo "⚠️  Verificando archivos peligrosos...\n";

    $dangerousPatterns = [
      'test_*.php',
      'debug_*.php',
      '*_test.php',
      'phpinfo.php',
      'info.php'
    ];

    foreach ($dangerousPatterns as $pattern) {
      $files = glob(__DIR__ . '/backend/' . $pattern);
      if (!empty($files)) {
        foreach ($files as $file) {
          $this->fail("Archivo peligroso encontrado: " . basename($file));
        }
      } else {
        $this->pass("No se encontraron archivos: $pattern");
      }
    }

    echo "\n";
  }

  private function checkSecurityHeaders(): void
  {
    echo "🛡️  Verificando headers de seguridad...\n";

    $headersFile = __DIR__ . '/backend/config/security-headers.php';
    if (file_exists($headersFile)) {
      $this->pass("Archivo de headers de seguridad existe");

      $content = file_get_contents($headersFile);
      $requiredHeaders = [
        'X-XSS-Protection',
        'X-Content-Type-Options',
        'X-Frame-Options',
        'Content-Security-Policy'
      ];

      foreach ($requiredHeaders as $header) {
        if (strpos($content, $header) !== false) {
          $this->pass("Header de seguridad configurado: $header");
        } else {
          $this->warning("Header de seguridad faltante: $header");
        }
      }
    } else {
      $this->fail("Archivo de headers de seguridad no encontrado");
    }

    echo "\n";
  }

  private function checkDatabaseConfig(): void
  {
    echo "🗄️  Verificando configuración de base de datos...\n";

    $dbFile = __DIR__ . '/backend/config/database.php';
    if (file_exists($dbFile)) {
      $content = file_get_contents($dbFile);

      if (strpos($content, 'PDO::ATTR_EMULATE_PREPARES => false') !== false) {
        $this->pass("Prepared statements reales habilitados");
      } else {
        $this->warning("Verificar configuración de prepared statements");
      }

      if (strpos($content, 'PDO::ERRMODE_EXCEPTION') !== false) {
        $this->pass("Modo de error de excepción configurado");
      } else {
        $this->warning("Verificar configuración de errores PDO");
      }
    } else {
      $this->fail("Archivo de configuración de BD no encontrado");
    }

    echo "\n";
  }

  private function checkFrontendSecurity(): void
  {
    echo "🎨 Verificando seguridad del frontend...\n";

    // Verificar analytics.ts
    $analyticsFile = __DIR__ . '/frontend/src/utils/analytics.ts';
    if (file_exists($analyticsFile)) {
      $content = file_get_contents($analyticsFile);

      if (strpos($content, 'textContent') !== false && strpos($content, 'innerHTML') === false) {
        $this->pass("Vulnerabilidad XSS en analytics.ts corregida");
      } else {
        $this->fail("Posible vulnerabilidad XSS en analytics.ts");
      }
    }

    // Verificar ESLint
    $eslintFile = __DIR__ . '/frontend/.eslintrc.json';
    if (file_exists($eslintFile)) {
      $content = file_get_contents($eslintFile);
      if (strpos($content, 'security') !== false) {
        $this->pass("ESLint con reglas de seguridad configurado");
      } else {
        $this->warning("Verificar reglas de seguridad en ESLint");
      }
    }

    echo "\n";
  }

  private function pass(string $message): void
  {
    echo Colors::GREEN . "✓ " . $message . Colors::RESET . "\n";
    $this->passed++;
  }

  private function fail(string $message): void
  {
    echo Colors::RED . "✗ " . $message . Colors::RESET . "\n";
    $this->failed++;
  }

  private function warning(string $message): void
  {
    echo Colors::YELLOW . "⚠ " . $message . Colors::RESET . "\n";
    $this->warnings++;
  }

  private function printSummary(): void
  {
    echo str_repeat("=", 60) . "\n";
    echo Colors::BLUE . "📊 RESUMEN DE VERIFICACIÓN" . Colors::RESET . "\n";
    echo str_repeat("=", 60) . "\n";

    echo Colors::GREEN . "✓ Pasaron: " . $this->passed . Colors::RESET . "\n";
    echo Colors::RED . "✗ Fallaron: " . $this->failed . Colors::RESET . "\n";
    echo Colors::YELLOW . "⚠ Advertencias: " . $this->warnings . Colors::RESET . "\n\n";

    if ($this->failed === 0) {
      echo Colors::GREEN . "🎉 ¡VERIFICACIÓN EXITOSA! Listo para despliegue." . Colors::RESET . "\n";
      exit(0);
    } else {
      echo Colors::RED . "❌ VERIFICACIÓN FALLIDA. Corregir errores antes del despliegue." . Colors::RESET . "\n";
      exit(1);
    }
  }
}

// Ejecutar verificación
$checker = new SecurityChecker();
$checker->run();
