// scripts/migrate-endpoints.mjs
import fs from 'fs';
import path from 'path';

// 🔹 Endpoints reales que existen en el backend
const endpoints = [
  // Endpoints que funcionan con GET
  { legacy: 'api/health.php', rest: '/api/health', method: 'GET', testable: true },
  { legacy: 'test.php', rest: '/api/test', method: 'GET', testable: true },
  { legacy: 'api/departments.php', rest: '/api/departments', method: 'GET', testable: true },
  { legacy: 'api/culture.php', rest: '/api/culture', method: 'GET', testable: true },
  { legacy: 'api/news.php', rest: '/api/news', method: 'GET', testable: true },
  { legacy: 'api/notifications.php', rest: '/api/notifications', method: 'GET', testable: true },
  { legacy: 'api/social_logins.php', rest: '/api/social-logins', method: 'GET', testable: true },
  { legacy: 'api/interviews.php', rest: '/api/interviews', method: 'GET', testable: true },
  { legacy: 'api/job_benefits.php', rest: '/api/jobs/benefits', method: 'GET', testable: true },
  { legacy: 'api/job_requirements.php', rest: '/api/jobs/requirements', method: 'GET', testable: true },
  { legacy: 'api/job_skills.php', rest: '/api/jobs/skills', method: 'GET', testable: true },

  // Endpoints que requieren POST (pueden fallar sin datos)
  { legacy: 'api/candidates.php', rest: '/api/candidates', method: 'POST', testable: true },
  { legacy: 'api/save-candidate.php', rest: '/api/candidates/save', method: 'POST', testable: true },
  { legacy: 'api/candidate-experiences.php', rest: '/api/candidates/experiences', method: 'POST', testable: true },
  { legacy: 'api/candidate_skills.php', rest: '/api/candidates/skills', method: 'POST', testable: true },
  { legacy: 'api/candidate-applications.php', rest: '/api/candidates/applications', method: 'POST', testable: true },
  { legacy: 'api/candidate-notifications.php', rest: '/api/candidates/notifications', method: 'POST', testable: true },
  { legacy: 'api/chatbot.php', rest: '/api/ai/chatbot', method: 'POST', testable: true },
  { legacy: 'test_chatbot_crud.php', rest: '/api/ai/chatbot-test', method: 'POST', testable: true },
  { legacy: 'api/chatbot_decision_tree.php', rest: '/api/ai/chatbot-decision-tree', method: 'POST', testable: true },
  { legacy: 'api/chatbot_analytics.php', rest: '/api/ai/chatbot-analytics', method: 'POST', testable: true },
  { legacy: 'api/change-password.php', rest: '/api/users/change-password', method: 'POST', testable: true },
  { legacy: 'api/get-notification-preferences.php', rest: '/api/notifications/preferences', method: 'GET', testable: true },
  { legacy: 'api/save-notification-preferences.php', rest: '/api/notifications/preferences/save', method: 'POST', testable: true },

  // Endpoints de autenticación (pueden requerir datos específicos)
  { legacy: 'auth/candidate-login.php', rest: '/api/auth/candidate-login', method: 'POST', testable: false },
  { legacy: 'auth/staff-login.php', rest: '/api/auth/staff-login', method: 'POST', testable: false },
  { legacy: 'auth/verify-session.php', rest: '/api/auth/verify-session', method: 'POST', testable: false },
  { legacy: 'auth/logout.php', rest: '/api/auth/logout', method: 'POST', testable: false },
  { legacy: 'auth/oauth/start.php', rest: '/api/auth/oauth/start', method: 'GET', testable: false },
  { legacy: 'api/auth/csrf-token.php', rest: '/api/auth/csrf-token', method: 'GET', testable: false },
  { legacy: 'api/auth/validate-csrf.php', rest: '/api/auth/validate-csrf', method: 'POST', testable: false },
  { legacy: 'api/auth/social-callback.php', rest: '/api/auth/social-callback', method: 'POST', testable: false },
];

// 🔹 Directorios frontend (ajustado a tu estructura real)
const frontendDirs = [
  path.resolve('../frontend/src'), // Desde scripts hacia frontend
  path.resolve('../oauth_implementation/frontend')
];

// 🔹 Función principal
async function migrateEndpoints() {
  console.log('🚀 Iniciando análisis de endpoints...\n');

  // 🔹 Paso 1: Analizar uso
  console.log('🔍 Analizando uso de endpoints...');
  const usageSummary = [];

  for (const ep of endpoints) {
    const foundFiles = [];

    const searchDir = (dir) => {
      if (!fs.existsSync(dir)) {
        console.log(`⚠️  Directorio no existe: ${dir}`);
        return;
      }

      try {
        const files = fs.readdirSync(dir, { withFileTypes: true });
        for (const file of files) {
          const fullPath = path.join(dir, file.name);
          if (file.isDirectory() && !file.name.includes('node_modules')) {
            searchDir(fullPath);
          } else if (fullPath.match(/\.(ts|tsx|js|jsx)$/)) {
            try {
              const content = fs.readFileSync(fullPath, 'utf-8');
              if (content.includes(ep.legacy)) {
                foundFiles.push(fullPath);
                console.log(`📍 Encontrado ${ep.legacy} en: ${path.relative(process.cwd(), fullPath)}`);
              }
            } catch (err) {
              // Ignorar errores de lectura silenciosamente
            }
          }
        }
      } catch (err) {
        console.log(`⚠️  Error accediendo al directorio ${dir}:`, err.message);
      }
    };

    frontendDirs.forEach(dir => searchDir(dir));
    usageSummary.push({ legacy: ep.legacy, rest: ep.rest, files: foundFiles });
  }

  // 🔹 Guardar resumen
  const summaryFile = path.resolve('./endpoint-usage-summary.json');
  try {
    fs.writeFileSync(summaryFile, JSON.stringify(usageSummary, null, 2));
    console.log(`\n✅ Resumen generado: ${summaryFile}`);
  } catch (err) {
    console.error('❌ Error guardando resumen:', err);
    process.exit(1);
  }

  // 🔹 Mostrar estadísticas
  console.log('\n📊 ESTADÍSTICAS:');
  const usedEndpoints = usageSummary.filter(ep => ep.files.length > 0);
  const totalFiles = usageSummary.reduce((acc, ep) => acc + ep.files.length, 0);

  console.log(`📈 Total endpoints: ${endpoints.length}`);
  console.log(`🎯 Endpoints en uso: ${usedEndpoints.length}`);
  console.log(`📄 Archivos afectados: ${totalFiles}`);
  console.log(`🔄 Pendientes migración: ${totalFiles}`);

  // 🔹 Mostrar endpoints en uso
  if (usedEndpoints.length > 0) {
    console.log('\n📝 ENDPOINTS EN USO:');
    usedEndpoints.forEach(ep => {
      console.log(`\n🔄 ${ep.legacy} → ${ep.rest}`);
      ep.files.forEach(file => {
        console.log(`   📄 ${path.relative(process.cwd(), file)}`);
      });
    });
  }

  // 🔹 Paso 2: Generar tests Playwright
  const testDir = path.resolve('./tests');
  try {
    if (!fs.existsSync(testDir)) {
      fs.mkdirSync(testDir, { recursive: true });
    }

    const testFile = path.join(testDir, 'generated-endpoint-tests.spec.ts');
    const testFileContent = `import { test, expect } from '@playwright/test';
import usageSummary from '../endpoint-usage-summary.json';

// Configuración base
const BASE_URL = 'http://localhost:8000';

// Payload por defecto para tests
const DEFAULT_PAYLOAD = { 
  email: 'test@example.com', 
  password: '123456',
  message: 'test message',
  user_id: 1
};

// Endpoints que sabemos que funcionan con GET
const testableGetEndpoints = [
  'api/health.php',
  'test.php', 
  'api/departments.php',
  'api/culture.php',
  'api/news.php',
  'api/notifications.php'
];

console.log('🧪 Testing legacy endpoints that actually exist in backend...');

// Test 1: Verificar que el servidor esté corriendo
test('Backend server should be running', async ({ request }) => {
  try {
    const response = await request.get(\`\${BASE_URL}/test.php\`, { timeout: 5000 });
    console.log('✅ Backend server is running');
    expect(response.status()).toBeLessThan(500);
  } catch (error) {
    console.error('❌ Backend server is not running on http://localhost:8000');
    expect(false, 'Backend server is not running').toBeTruthy();
  }
});

// Test 2: Probar endpoints GET que funcionan
testableGetEndpoints.forEach((endpoint, index) => {
  test(\`GET \${endpoint} should respond correctly\`, async ({ request }) => {
    console.log(\`🔍 Testing GET: \${endpoint}\`);
    
    try {
      const response = await request.get(\`\${BASE_URL}/\${endpoint}\`, {
        timeout: 10000
      });
      
      console.log(\`📊 \${endpoint} - Status: \${response.status()}\`);
      
      // Verificar que no sea error de servidor
      expect(response.status()).toBeLessThan(500);
      
      // Si es 200, intentar parsear respuesta
      if (response.status() === 200) {
        try {
          const body = await response.text();
          console.log(\`✅ \${endpoint} - Response OK (length: \${body.length})\`);
          expect(body.length).toBeGreaterThan(0);
        } catch (e) {
          console.log(\`⚠️  \${endpoint} - Response not JSON but status OK\`);
        }
      }
      
    } catch (error) {
      console.error(\`❌ Error testing \${endpoint}:\`, error.message);
      expect.soft(false, \`Endpoint \${endpoint} failed: \${error.message}\`).toBeTruthy();
    }
  });
});

// Test 3: Probar algunos endpoints POST básicos (pueden fallar pero no con 500)
const testablePostEndpoints = [
  'api/candidates.php',
  'api/chatbot.php'
];

testablePostEndpoints.forEach((endpoint) => {
  test(\`POST \${endpoint} should not return server error\`, async ({ request }) => {
    console.log(\`🔍 Testing POST: \${endpoint}\`);
    
    try {
      const response = await request.post(\`\${BASE_URL}/\${endpoint}\`, {
        data: DEFAULT_PAYLOAD,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        timeout: 10000
      });
      
      console.log(\`📊 \${endpoint} - Status: \${response.status()}\`);
      
      // Solo verificar que no sea error de servidor (500+)
      expect(response.status()).toBeLessThan(500);
      
      if (response.status() < 400) {
        console.log(\`✅ \${endpoint} - Success response\`);
      } else {
        console.log(\`⚠️  \${endpoint} - Client error (expected, may need auth/data)\`);
      }
      
    } catch (error) {
      console.error(\`❌ Error testing \${endpoint}:\`, error.message);
      expect.soft(false, \`Endpoint \${endpoint} failed: \${error.message}\`).toBeTruthy();
    }
  });
});

// Test 4: Crear lista de URLs para probar manualmente en navegador
test('Generate browser testing URLs', async () => {
  console.log('🌐 URLs para probar en el navegador:');
  console.log('');
  
  testableGetEndpoints.forEach(endpoint => {
    console.log(\`✅ http://localhost:8000/\${endpoint}\`);
  });
  
  console.log('');
  console.log('📝 URLs que requieren POST (usar Postman/cURL):');
  testablePostEndpoints.forEach(endpoint => {
    console.log(\`🔄 http://localhost:8000/\${endpoint}\`);
  });
  
  expect(true).toBeTruthy(); // Siempre pasa
});
`;

    fs.writeFileSync(testFile, testFileContent);
    console.log(`\n✅ Tests Playwright generados en: ${testFile}`);

    // 🔹 Crear archivo de configuración para los tests
    const configFile = path.join(testDir, 'test-config.json');
    const configContent = {
      baseUrl: 'http://localhost:8000',
      totalEndpoints: endpoints.length,
      endpointsWithUsage: usedEndpoints.length,
      generatedAt: new Date().toISOString(),
      summary: {
        totalFiles: totalFiles,
        endpoints: usageSummary.map(ep => ({
          legacy: ep.legacy,
          rest: ep.rest,
          usageCount: ep.files.length
        })).filter(ep => ep.usageCount > 0)
      }
    };

    fs.writeFileSync(configFile, JSON.stringify(configContent, null, 2));
    console.log(`✅ Configuración de tests guardada en: ${configFile}`);

  } catch (err) {
    console.error('❌ Error generando tests:', err);
    process.exit(1);
  }

  console.log('\n🎉 Script completado exitosamente!');
  console.log('\n📋 PRÓXIMOS PASOS:');
  console.log('1. 📄 Revisar: endpoint-usage-summary.json');
  console.log('2. 🧪 Ejecutar tests: npm run test:endpoints');
  console.log('3. 🔄 Aplicar migraciones manualmente si es necesario');
  console.log('4. 🚀 Comprobar que el backend esté en http://localhost:8000');
}

// Ejecutar el script
migrateEndpoints().catch(error => {
  console.error('💥 Error fatal:', error);
  process.exit(1);
});
