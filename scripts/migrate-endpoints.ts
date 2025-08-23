// scripts/migrate-endpoints.ts
import fs from 'fs';
import path from 'path';

// 🔹 Interfaces
interface Endpoint { legacy: string; rest: string; }
interface EndpointUsage { legacy: string; rest: string; files: string[]; }

// 🔹 Endpoints
const endpoints: Endpoint[] = [
  { legacy: 'auth/candidate-login.php', rest: '/api/auth/candidate-login' },
  { legacy: 'auth/staff-login.php', rest: '/api/auth/staff-login' },
  { legacy: 'auth/verify-session.php', rest: '/api/auth/verify-session' },
  { legacy: 'auth/logout.php', rest: '/api/auth/logout' },
  { legacy: 'auth/oauth/start.php', rest: '/api/auth/oauth/start' },
  { legacy: 'api/auth/csrf-token.php', rest: '/api/auth/csrf-token' },
  { legacy: 'api/auth/validate-csrf.php', rest: '/api/auth/validate-csrf' },
  { legacy: 'api/auth/social-callback.php', rest: '/api/auth/social-callback' },
  { legacy: 'candidates.php', rest: '/api/candidates' },
  { legacy: 'api/save-candidate.php', rest: '/api/candidates/save' },
  { legacy: 'api/candidates/save_v2.php', rest: '/api/candidates/save-v2' },
  { legacy: 'api/candidate-experiences.php', rest: '/api/candidates/experiences' },
  { legacy: 'candidate_skills.php', rest: '/api/candidates/skills' },
  { legacy: 'api/candidate-applications.php', rest: '/api/candidates/applications' },
  { legacy: 'api/candidate-notifications.php', rest: '/api/candidates/notifications' },
  { legacy: 'skills.php', rest: '/api/skills' },
  { legacy: 'departments.php', rest: '/api/departments' },
  { legacy: 'application_notes.php', rest: '/api/application-notes' },
  { legacy: 'notifications.php', rest: '/api/notifications' },
  { legacy: 'social_logins.php', rest: '/api/social-logins' },
  { legacy: 'interviews.php', rest: '/api/interviews' },
  { legacy: 'job_benefits.php', rest: '/api/jobs/benefits' },
  { legacy: 'job_requirements.php', rest: '/api/jobs/requirements' },
  { legacy: 'job_skills.php', rest: '/api/jobs/skills' },
  { legacy: 'culture.php', rest: '/api/culture' },
  { legacy: 'news.php', rest: '/api/news' },
  { legacy: 'chatbot.php', rest: '/api/ai/chatbot' },
  { legacy: 'test_chatbot_crud.php', rest: '/api/ai/chatbot-test' },
  { legacy: 'chatbot_decision_tree.php', rest: '/api/ai/chatbot-decision-tree' },
  { legacy: 'chatbot_analytics.php', rest: '/api/ai/chatbot-analytics' },
  { legacy: 'api/change-password.php', rest: '/api/users/change-password' },
  { legacy: 'api/get-notification-preferences.php', rest: '/api/notifications/preferences' },
  { legacy: 'api/save-notification-preferences.php', rest: '/api/notifications/preferences/save' },
  { legacy: 'health.php', rest: '/api/health' },
  { legacy: 'test.php', rest: '/api/test' },
];

// 🔹 Directorios frontend
const frontendDirs = [
  path.resolve('./frontend/src'), // Ajustado a tu estructura real
  path.resolve('./oauth_implementation/frontend')
];

// 🔹 Paso 1: Analizar uso
console.log('🔍 Analizando uso de endpoints...');
const usageSummary: EndpointUsage[] = [];

endpoints.forEach(ep => {
  const foundFiles: string[] = [];

  const searchDir = (dir: string) => {
    if (!fs.existsSync(dir)) {
      console.log(`⚠️  Directorio no existe: ${dir}`);
      return;
    }

    try {
      const files = fs.readdirSync(dir, { withFileTypes: true });
      for (const file of files) {
        const fullPath = path.join(dir, file.name);
        if (file.isDirectory()) {
          searchDir(fullPath);
        } else if (fullPath.endsWith('.ts') || fullPath.endsWith('.tsx') || fullPath.endsWith('.js') || fullPath.endsWith('.jsx')) {
          try {
            const content = fs.readFileSync(fullPath, 'utf-8');
            if (content.includes(ep.legacy)) {
              foundFiles.push(fullPath);
              console.log(`📍 Encontrado ${ep.legacy} en: ${fullPath}`);
            }
          } catch (err) {
            console.log(`⚠️  Error leyendo ${fullPath}:`, err);
          }
        }
      }
    } catch (err) {
      console.log(`⚠️  Error accediendo al directorio ${dir}:`, err);
    }
  };

  frontendDirs.forEach(dir => searchDir(dir));
  usageSummary.push({ legacy: ep.legacy, rest: ep.rest, files: foundFiles });
});

// 🔹 Guardar resumen
const summaryFile = path.resolve('./scripts/endpoint-usage-summary.json');
try {
  fs.writeFileSync(summaryFile, JSON.stringify(usageSummary, null, 2));
  console.log(`✅ Resumen generado: ${summaryFile}`);
} catch (err) {
  console.error('❌ Error guardando resumen:', err);
  process.exit(1);
}

// 🔹 Paso 2: Migrar endpoints (Solo mostrar, no modificar)
console.log('\n📝 Migraciones pendientes:');
let totalMigrations = 0;

usageSummary.forEach(ep => {
  if (ep.files.length > 0) {
    console.log(`\n🔄 ${ep.legacy} → ${ep.rest}`);
    ep.files.forEach(file => {
      console.log(`   📄 ${file}`);
      totalMigrations++;
    });
  }
});

console.log(`\n📊 Total de migraciones encontradas: ${totalMigrations}`);

// 🔹 Paso 3: Generar tests Playwright
const testDir = path.resolve('./scripts/tests');
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
  password: '123456' 
};

// Solo testear endpoints que tienen uso en el frontend
const endpointsToTest = usageSummary.filter(ep => ep.files.length > 0);

console.log(\`🧪 Testing \${endpointsToTest.length} endpoints that are used in frontend...\`);

endpointsToTest.forEach((ep, index) => {
  test(\`[\${index + 1}/\${endpointsToTest.length}] \${ep.rest} should respond correctly\`, async ({ request }) => {
    console.log(\`🔍 Testing: \${ep.rest}\`);
    
    try {
      const response = await request.post(\`\${BASE_URL}\${ep.rest}\`, { 
        data: DEFAULT_PAYLOAD,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      });
      
      // Verificar que la respuesta sea válida
      expect(response.status()).toBeLessThan(500); // No errores de servidor
      
      // Intentar parsear JSON
      const body = await response.json().catch(() => ({}));
      console.log(\`✅ \${ep.rest} - Status: \${response.status()}\`);
      
      // Verificación flexible: buscar propiedades comunes de respuesta exitosa
      const hasValidResponse = body.hasOwnProperty('success') || 
                              body.hasOwnProperty('data') || 
                              body.hasOwnProperty('message') ||
                              response.status() === 200;
      
      expect(hasValidResponse).toBeTruthy();
      
    } catch (error) {
      console.error(\`❌ Error testing \${ep.rest}:\`, error.message);
      // Usar soft assertion para continuar con otros tests
      expect.soft(false, \`Endpoint \${ep.rest} failed: \${error.message}\`).toBeTruthy();
    }
  });
});

// Test adicional para verificar que el servidor esté corriendo
test('Backend server should be running', async ({ request }) => {
  const response = await request.get(\`\${BASE_URL}/api/health\`).catch(() => null);
  if (!response) {
    console.error('❌ Backend server is not running on http://localhost:8000');
    expect(false, 'Backend server is not running').toBeTruthy();
  } else {
    console.log('✅ Backend server is running');
    expect(response.status()).toBeLessThan(500);
  }
});
`;

  fs.writeFileSync(testFile, testFileContent);
  console.log(`✅ Tests Playwright generados en: ${testFile}`);

  // 🔹 Crear archivo de configuración adicional para los tests
  const configFile = path.join(testDir, 'test-config.json');
  const configContent = {
    baseUrl: 'http://localhost:8000',
    totalEndpoints: endpoints.length,
    endpointsWithUsage: usageSummary.filter(ep => ep.files.length > 0).length,
    generatedAt: new Date().toISOString(),
    summary: {
      totalFiles: usageSummary.reduce((acc, ep) => acc + ep.files.length, 0),
      endpoints: usageSummary.map(ep => ({
        legacy: ep.legacy,
        rest: ep.rest,
        usageCount: ep.files.length
      }))
    }
  };

  fs.writeFileSync(configFile, JSON.stringify(configContent, null, 2));
  console.log(`✅ Configuración de tests guardada en: ${configFile}`);

} catch (err) {
  console.error('❌ Error generando tests:', err);
  process.exit(1);
}

console.log('\n🎉 Script completado exitosamente!');
console.log('\n📋 Próximos pasos:');
console.log('1. Revisar el archivo endpoint-usage-summary.json');
console.log('2. Ejecutar los tests con: pnpm test:endpoints');
console.log('3. Aplicar las migraciones manualmente si es necesario');
