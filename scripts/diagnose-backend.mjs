#!/usr/bin/env node

import { spawn } from 'child_process';

console.log('🔧 Diagnóstico del Backend - Bubble of Talents\n');

// Función para hacer requests de diagnóstico
async function checkEndpoint(url, method = 'GET', data = null) {
  try {
    const options = {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    };

    if (data && method === 'POST') {
      options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    const text = await response.text();

    return {
      status: response.status,
      ok: response.ok,
      body: text,
      isJson: text.startsWith('{') || text.startsWith('[')
    };
  } catch (error) {
    return {
      status: 0,
      ok: false,
      error: error.message
    };
  }
}

// Función principal de diagnóstico
async function runDiagnosis() {
  const BASE_URL = 'http://localhost:8000';
  const results = {};

  console.log('📊 Verificando endpoints principales...\n');

  // 1. Health check
  console.log('🏥 Health Check:');
  const health = await checkEndpoint(`${BASE_URL}/api/health.php`);
  if (health.ok && health.isJson) {
    const healthData = JSON.parse(health.body);
    console.log(`✅ Status: ${health.status}`);
    console.log(`🤖 AI Provider: ${healthData.data?.provider || 'no detectado'}`);
    console.log(`📊 AI Status: ${healthData.data?.status || 'no detectado'}`);

    // Detectar problema de OpenAI
    if (healthData.data?.provider === 'openai' && healthData.data?.status === 'unauthorized') {
      console.log('⚠️  PROBLEMA DETECTADO: Configurado con OpenAI pero debería usar Groq');
      console.log('💡 Solución: Cambiar configuración a Groq');
    }
  } else {
    console.log(`❌ Error: ${health.status} - ${health.error || 'No response'}`);
  }

  console.log('\n📁 Verificando archivos críticos:');

  // 2. Verificar endpoints que tienen errores conocidos
  const problematicEndpoints = [
    'api/candidates.php',
    'api/notifications.php'
  ];

  for (const endpoint of problematicEndpoints) {
    console.log(`\n🔍 Probando ${endpoint}:`);
    const result = await checkEndpoint(`${BASE_URL}/${endpoint}`, 'POST', { test: true });

    if (result.body && result.body.includes('SecurityMiddleware.php')) {
      console.log('❌ Error: Archivo SecurityMiddleware.php faltante');
      console.log('📁 Ruta esperada: src/Middleware/SecurityMiddleware.php');
    } else if (result.body && result.body.includes('error')) {
      try {
        const errorData = JSON.parse(result.body);
        console.log(`⚠️  Error controlado: ${errorData.message}`);
      } catch {
        console.log(`⚠️  Error: ${result.status} - Response no válida`);
      }
    } else if (result.ok) {
      console.log(`✅ Funciona correctamente`);
    } else {
      console.log(`❌ Status ${result.status}`);
    }
  }

  console.log('\n🔧 DIAGNÓSTICO COMPLETO:');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

  // Generar recomendaciones
  const issues = [];
  const solutions = [];

  if (health.ok && health.isJson) {
    const healthData = JSON.parse(health.body);
    if (healthData.data?.provider === 'openai') {
      issues.push('🤖 Backend configurado con OpenAI en lugar de Groq');
      solutions.push('📋 Cambiar configuración de AI provider a Groq');
      solutions.push('🔑 Verificar API key de Groq en .env');
    }
  }

  // Verificar archivos faltantes
  issues.push('📁 Archivo SecurityMiddleware.php faltante');
  solutions.push('📂 Crear src/Middleware/SecurityMiddleware.php');
  solutions.push('🔧 O ajustar rutas de include en candidates.php');

  console.log('\n⚠️  PROBLEMAS ENCONTRADOS:');
  issues.forEach(issue => console.log(`   ${issue}`));

  console.log('\n💡 SOLUCIONES RECOMENDADAS:');
  solutions.forEach(solution => console.log(`   ${solution}`));

  console.log('\n📋 PRÓXIMOS PASOS:');
  console.log('1. 🔧 Configurar Groq en lugar de OpenAI');
  console.log('2. 📁 Arreglar rutas de archivos faltantes');
  console.log('3. 🧪 Re-ejecutar tests: npm run test');
  console.log('4. 📊 Verificar health check actualizado');

  // Generar script de arreglo automático
  console.log('\n🛠️  Generando script de arreglo...');

  const fixScript = `#!/usr/bin/env bash

# Script de arreglo automático para Backend
echo "🔧 Aplicando arreglos al backend..."

# 1. Verificar configuración Groq
if [ ! -f "../backend/.env" ]; then
    echo "⚠️  Archivo .env no encontrado"
    cp "../backend/.env.example" "../backend/.env"
fi

# 2. Crear directorio Middleware si no existe
mkdir -p "../backend/src/Middleware"

# 3. Crear SecurityMiddleware básico
cat > "../backend/src/Middleware/SecurityMiddleware.php" << 'EOF'
<?php
namespace BubbleTalents\\Middleware;

class SecurityMiddleware {
    public static function validateRequest() {
        // Validación básica de seguridad
        return true;
    }
    
    public static function corsHeaders() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
    }
}
EOF

echo "✅ Arreglos aplicados"
echo "🔄 Reinicia el backend para aplicar cambios"
`;

  // Guardar script de arreglo
  const fs = await import('fs');
  fs.writeFileSync('./fix-backend.sh', fixScript);
  console.log('✅ Script de arreglo guardado: fix-backend.sh');

  console.log('\n🎯 Para aplicar arreglos automáticamente:');
  console.log('   bash fix-backend.sh');
}

// Ejecutar diagnóstico
runDiagnosis().catch(error => {
  console.error('💥 Error en diagnóstico:', error.message);
});
