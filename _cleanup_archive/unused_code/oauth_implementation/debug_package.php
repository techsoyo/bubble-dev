<?php

/**
 * OAuth Status & Debug Tool - OAuth Implementation Package
 * 
 * Herramienta de diagnóstico para verificar la configuración OAuth
 * Usar solo durante desarrollo - NO incluir en producción
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>OAuth Debug - Implementation Package</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 40px; background: #f8f9fa; }
.container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.ok { color: #28a745; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
.info { color: #17a2b8; font-weight: bold; }
code { background: #f8f9fa; padding: 3px 8px; border-radius: 4px; font-family: 'Courier New', monospace; }
pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
h1 { color: #343a40; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
h2 { color: #495057; border-left: 4px solid #007bff; padding-left: 15px; margin-top: 30px; }
.status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
.status-card { border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; }
.btn { display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
.btn:hover { background: #0056b3; }
.installation-note { background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 6px; margin: 20px 0; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔧 OAuth Implementation Package - Debug Tool</h1>";

// Información del paquete
echo "<div class='installation-note'>";
echo "<h3>📦 Información del Paquete OAuth</h3>";
echo "<p><strong>Versión:</strong> 1.0.0</p>";
echo "<p><strong>Fecha:</strong> 15 de agosto 2025</p>";
echo "<p><strong>Ubicación:</strong> oauth_implementation/</p>";
echo "<p><strong>Estado:</strong> Listo para instalación</p>";
echo "</div>";

// Verificar archivos del paquete
echo "<h2>1. Archivos del Paquete OAuth</h2>";
$package_files = [
  'backend/auth/OAuthHandler.php' => 'Clase principal OAuth',
  'backend/auth/oauth/start.php' => 'Endpoint inicio OAuth',
  'backend/auth/google/callback.php' => 'Callback Google',
  'backend/auth/linkedin/callback.php' => 'Callback LinkedIn',
  'frontend/CandidateAuthPage.tsx' => 'Frontend con OAuth',
  'config/.env.oauth.example' => 'Template configuración',
  'docs/OAUTH_SETUP_GUIDE.md' => 'Guía de configuración',
  'docs/OAUTH_TECHNICAL_CHECKLIST.md' => 'Lista verificación',
  'README.md' => 'Documentación principal',
  'install_oauth.ps1' => 'Instalador Windows',
  'install_oauth.sh' => 'Instalador Linux/Mac'
];

echo "<div class='status-grid'>";
foreach ($package_files as $file => $desc) {
  $full_path = __DIR__ . '/' . $file;
  echo "<div class='status-card'>";
  if (file_exists($full_path)) {
    echo "<p class='ok'>✅ {$desc}</p>";
    echo "<code>{$file}</code>";
    $size = filesize($full_path);
    echo "<br><small>Tamaño: " . number_format($size) . " bytes</small>";
  } else {
    echo "<p class='error'>❌ {$desc}</p>";
    echo "<code>{$file}</code> - No encontrado";
  }
  echo "</div>";
}
echo "</div>";

// Estado de instalación en proyecto principal
echo "<h2>2. Estado de Instalación</h2>";
$project_files = [
  '../backend/auth/OAuthHandler.php' => 'OAuth Handler instalado',
  '../backend/auth/oauth/start.php' => 'Start endpoint instalado',
  '../backend/auth/google/callback.php' => 'Google callback instalado',
  '../backend/auth/linkedin/callback.php' => 'LinkedIn callback instalado',
  '../frontend/src/pages/auth/CandidateAuthPage.tsx' => 'Frontend OAuth instalado',
  '../backend/.env.oauth' => 'Configuración OAuth',
  '../backend/.env.oauth.example' => 'Template configuración'
];

foreach ($project_files as $file => $desc) {
  $full_path = __DIR__ . '/' . $file;
  if (file_exists($full_path)) {
    echo "<p class='ok'>✅ {$desc}</p>";
  } else {
    echo "<p class='warning'>⚠️ {$desc} - No instalado</p>";
  }
}

// Verificar configuración OAuth si está instalada
$oauth_config_path = __DIR__ . '/../backend/.env.oauth';
if (file_exists($oauth_config_path)) {
  echo "<h2>3. Configuración OAuth</h2>";
  $env_content = file_get_contents($oauth_config_path);
  $required_vars = [
    'GOOGLE_CLIENT_ID' => 'Google Client ID',
    'GOOGLE_CLIENT_SECRET' => 'Google Client Secret',
    'LINKEDIN_CLIENT_ID' => 'LinkedIn Client ID',
    'LINKEDIN_CLIENT_SECRET' => 'LinkedIn Client Secret'
  ];

  foreach ($required_vars as $var => $desc) {
    if (strpos($env_content, $var . '=') !== false) {
      $value = trim(explode($var . '=', $env_content)[1] ?? '');
      $value = explode("\n", $value)[0] ?? '';
      if (!empty($value) && !str_contains($value, 'your_') && !str_contains($value, 'tu_')) {
        echo "<p class='ok'>✅ {$desc} configurado</p>";
      } else {
        echo "<p class='error'>❌ {$desc} pendiente de configurar</p>";
      }
    } else {
      echo "<p class='error'>❌ {$desc} no encontrado en .env.oauth</p>";
    }
  }
}

// URLs de prueba y herramientas
echo "<h2>4. Herramientas y URLs de Prueba</h2>";

if (file_exists(__DIR__ . '/../backend/debug_oauth.php')) {
  echo "<p class='ok'>✅ Debug tool disponible</p>";
  echo "<a href='../backend/debug_oauth.php' target='_blank' class='btn'>🔧 Abrir Debug Tool del Proyecto</a>";
} else {
  echo "<p class='warning'>⚠️ Debug tool no instalado</p>";
}

echo "<h3>URLs útiles (después de instalar):</h3>";
echo "<ul>";
echo "<li><a href='http://localhost:8000/debug_oauth.php' target='_blank'>Debug OAuth del Proyecto</a></li>";
echo "<li><a href='http://localhost:3002/auth/register' target='_blank'>Página de Auth con botones OAuth</a></li>";
echo "<li><a href='http://localhost:8000/auth/oauth/start.php?provider=google' target='_blank'>Test Google OAuth</a></li>";
echo "<li><a href='http://localhost:8000/auth/oauth/start.php?provider=linkedin' target='_blank'>Test LinkedIn OAuth</a></li>";
echo "</ul>";

// Instrucciones de instalación
echo "<h2>5. Instrucciones de Instalación</h2>";
echo "<div class='installation-note'>";
echo "<h4>🚀 Instalación Automática:</h4>";
echo "<pre><code># Windows PowerShell (desde raíz del proyecto)
.\\oauth_implementation\\install_oauth.ps1

# Linux/Mac (desde raíz del proyecto)  
./oauth_implementation/install_oauth.sh</code></pre>";

echo "<h4>📖 Instalación Manual:</h4>";
echo "<ol>";
echo "<li>Lee la documentación: <code>docs/README.md</code></li>";
echo "<li>Copia archivos backend a tu carpeta <code>backend/auth/</code></li>";
echo "<li>Copia archivo frontend a <code>frontend/src/pages/auth/</code></li>";
echo "<li>Configura OAuth siguiendo <code>docs/OAUTH_SETUP_GUIDE.md</code></li>";
echo "</ol>";
echo "</div>";

// Próximos pasos
echo "<h2>6. Próximos Pasos</h2>";
if (!file_exists(__DIR__ . '/../backend/auth/OAuthHandler.php')) {
  echo "<p class='info'>🔹 <strong>Paso 1:</strong> Instalar archivos OAuth usando los scripts de instalación</p>";
  echo "<p class='info'>🔹 <strong>Paso 2:</strong> Seguir la guía de configuración OAuth</p>";
} else {
  echo "<p class='ok'>✅ <strong>OAuth instalado</strong> - Ahora configura las credenciales</p>";
  echo "<p class='info'>🔹 <strong>Siguiente:</strong> Completa el archivo .env.oauth con tus credenciales</p>";
  echo "<p class='info'>🔹 <strong>Después:</strong> Prueba los botones OAuth en la página de auth</p>";
}

echo "<h3>📚 Documentación:</h3>";
echo "<ul>";
echo "<li><strong>Guía principal:</strong> oauth_implementation/README.md</li>";
echo "<li><strong>Configuración paso a paso:</strong> docs/OAUTH_SETUP_GUIDE.md</li>";
echo "<li><strong>Lista de verificación:</strong> docs/OAUTH_TECHNICAL_CHECKLIST.md</li>";
echo "</ul>";

echo "<p style='margin-top: 40px; text-align: center; color: #6c757d;'>";
echo "<small>OAuth Implementation Package v1.0.0 - 15 de agosto 2025</small>";
echo "</p>";

echo "</div></body></html>";
