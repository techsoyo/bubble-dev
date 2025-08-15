# 🔧 OAuth Debug & Test Helper

<?php
// Archivo para debugging OAuth - NO usar en producción

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>OAuth Debug Helper</title>";
echo "<style>body{font-family:Arial;margin:40px;} .ok{color:green;} .error{color:red;} .info{color:blue;} code{background:#f5f5f5;padding:2px 5px;}</style></head><body>";

echo "<h1>🔧 OAuth Configuration Debug</h1>";

// Verificar archivo .env.oauth
echo "<h2>1. Archivo de configuración</h2>";
$env_file = __DIR__ . '/../.env.oauth';
if (file_exists($env_file)) {
  echo "<p class='ok'>✅ Archivo .env.oauth existe</p>";

  $env_content = file_get_contents($env_file);
  $required_vars = [
    'GOOGLE_CLIENT_ID',
    'GOOGLE_CLIENT_SECRET',
    'LINKEDIN_CLIENT_ID',
    'LINKEDIN_CLIENT_SECRET',
    'OAUTH_BASE_URL',
    'OAUTH_SECRET_KEY'
  ];

  foreach ($required_vars as $var) {
    if (strpos($env_content, $var . '=') !== false) {
      $value = trim(explode($var . '=', $env_content)[1] ?? '');
      $value = explode("\n", $value)[0] ?? '';
      if (!empty($value) && $value !== 'tu_valor_aqui') {
        echo "<p class='ok'>✅ {$var} configurado</p>";
      } else {
        echo "<p class='error'>❌ {$var} vacío o con valor por defecto</p>";
      }
    } else {
      echo "<p class='error'>❌ {$var} no encontrado</p>";
    }
  }
} else {
  echo "<p class='error'>❌ Archivo .env.oauth no existe. Copia .env.oauth.example</p>";
}

// Verificar archivos OAuth
echo "<h2>2. Archivos OAuth</h2>";
$oauth_files = [
  'auth/OAuthHandler.php' => 'Clase principal OAuth',
  'auth/oauth/start.php' => 'Endpoint inicio OAuth',
  'auth/google/callback.php' => 'Callback Google',
  'auth/linkedin/callback.php' => 'Callback LinkedIn'
];

foreach ($oauth_files as $file => $desc) {
  $full_path = __DIR__ . '/../' . $file;
  if (file_exists($full_path)) {
    echo "<p class='ok'>✅ {$desc}: <code>{$file}</code></p>";
  } else {
    echo "<p class='error'>❌ {$desc}: <code>{$file}</code> - No existe</p>";
  }
}

// Test de URLs
echo "<h2>3. URLs de prueba</h2>";
$base_url = 'http://localhost:8000';
echo "<p><strong>Test rápido:</strong></p>";
echo "<ul>";
echo "<li><a href='{$base_url}/auth/oauth/start.php?provider=google' target='_blank'>🔗 Probar Google OAuth</a></li>";
echo "<li><a href='{$base_url}/auth/oauth/start.php?provider=linkedin' target='_blank'>🔗 Probar LinkedIn OAuth</a></li>";
echo "<li><a href='{$base_url}/auth/oauth/start.php?provider=google&job=123' target='_blank'>🔗 Google con Job ID</a></li>";
echo "</ul>";

// Información de PHP
echo "<h2>4. Configuración PHP</h2>";
$php_requirements = [
  'curl' => extension_loaded('curl'),
  'json' => extension_loaded('json'),
  'openssl' => extension_loaded('openssl'),
  'session' => function_exists('session_start')
];

foreach ($php_requirements as $req => $status) {
  if ($status) {
    echo "<p class='ok'>✅ {$req} disponible</p>";
  } else {
    echo "<p class='error'>❌ {$req} no disponible - REQUERIDO</p>";
  }
}

// Base de datos
echo "<h2>5. Base de datos</h2>";
try {
  require_once __DIR__ . '/../config/database.php';

  // Verificar si bt_candidates tiene campos OAuth
  $query = "SHOW COLUMNS FROM bt_candidates LIKE 'oauth_%'";
  $result = $conn->query($query);

  if ($result && $result->num_rows > 0) {
    echo "<p class='ok'>✅ Campos OAuth encontrados en bt_candidates:</p>";
    while ($row = $result->fetch_assoc()) {
      echo "<p class='info'>   - {$row['Field']}</p>";
    }
  } else {
    echo "<p class='error'>❌ Campos OAuth no encontrados. Ejecutar:</p>";
    echo "<code>";
    echo "ALTER TABLE bt_candidates ADD COLUMN oauth_provider VARCHAR(20) NULL;<br>";
    echo "ALTER TABLE bt_candidates ADD COLUMN oauth_provider_id VARCHAR(100) NULL;<br>";
    echo "ALTER TABLE bt_candidates ADD COLUMN profile_picture VARCHAR(500) NULL;";
    echo "</code>";
  }
} catch (Exception $e) {
  echo "<p class='error'>❌ Error de conexión a BD: " . $e->getMessage() . "</p>";
}

// Frontend check
echo "<h2>6. Frontend</h2>";
$frontend_file = __DIR__ . '/../../frontend/src/pages/auth/CandidateAuthPage.tsx';
if (file_exists($frontend_file)) {
  $content = file_get_contents($frontend_file);
  if (strpos($content, 'handleSocialLogin') !== false) {
    echo "<p class='ok'>✅ CandidateAuthPage.tsx tiene función OAuth</p>";
  } else {
    echo "<p class='error'>❌ CandidateAuthPage.tsx no tiene función OAuth</p>";
  }

  if (strpos($content, 'LinkedIn') !== false) {
    echo "<p class='ok'>✅ Botón LinkedIn presente</p>";
  } else {
    echo "<p class='error'>❌ Botón LinkedIn no encontrado</p>";
  }
} else {
  echo "<p class='error'>❌ Frontend no encontrado</p>";
}

echo "<h2>📝 Próximos pasos</h2>";
echo "<ol>";
echo "<li>Si ves errores arriba, revisa <code>OAUTH_SETUP_GUIDE.md</code></li>";
echo "<li>Configura Google Cloud Console siguiendo la guía</li>";
echo "<li>Configura LinkedIn Developer siguiendo la guía</li>";
echo "<li>Completa el archivo <code>.env.oauth</code></li>";
echo "<li>Prueba los enlaces de arriba</li>";
echo "</ol>";

echo "<p><strong>⚠️ IMPORTANTE:</strong> Elimina este archivo antes de subir a producción.</p>";

echo "</body></html>";
?>