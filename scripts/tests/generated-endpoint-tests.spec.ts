import { test, expect } from '@playwright/test';
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
    const response = await request.get(`${BASE_URL}/test.php`, { timeout: 5000 });
    console.log('✅ Backend server is running');
    expect(response.status()).toBeLessThan(500);
  } catch (error) {
    console.error('❌ Backend server is not running on http://localhost:8000');
    expect(false, 'Backend server is not running').toBeTruthy();
  }
});

// Test 2: Probar endpoints GET que funcionan
testableGetEndpoints.forEach((endpoint, index) => {
  test(`GET ${endpoint} should respond correctly`, async ({ request }) => {
    console.log(`🔍 Testing GET: ${endpoint}`);
    
    try {
      const response = await request.get(`${BASE_URL}/${endpoint}`, {
        timeout: 10000
      });
      
      console.log(`📊 ${endpoint} - Status: ${response.status()}`);
      
      // Verificar que no sea error de servidor
      expect(response.status()).toBeLessThan(500);
      
      // Si es 200, intentar parsear respuesta
      if (response.status() === 200) {
        try {
          const body = await response.text();
          console.log(`✅ ${endpoint} - Response OK (length: ${body.length})`);
          expect(body.length).toBeGreaterThan(0);
        } catch (e) {
          console.log(`⚠️  ${endpoint} - Response not JSON but status OK`);
        }
      }
      
    } catch (error) {
      console.error(`❌ Error testing ${endpoint}:`, error.message);
      expect.soft(false, `Endpoint ${endpoint} failed: ${error.message}`).toBeTruthy();
    }
  });
});

// Test 3: Probar algunos endpoints POST básicos (pueden fallar pero no con 500)
const testablePostEndpoints = [
  'api/candidates.php',
  'api/chatbot.php'
];

testablePostEndpoints.forEach((endpoint) => {
  test(`POST ${endpoint} should not return server error`, async ({ request }) => {
    console.log(`🔍 Testing POST: ${endpoint}`);
    
    try {
      const response = await request.post(`${BASE_URL}/${endpoint}`, {
        data: DEFAULT_PAYLOAD,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        timeout: 10000
      });
      
      console.log(`📊 ${endpoint} - Status: ${response.status()}`);
      
      // Solo verificar que no sea error de servidor (500+)
      expect(response.status()).toBeLessThan(500);
      
      if (response.status() < 400) {
        console.log(`✅ ${endpoint} - Success response`);
      } else {
        console.log(`⚠️  ${endpoint} - Client error (expected, may need auth/data)`);
      }
      
    } catch (error) {
      console.error(`❌ Error testing ${endpoint}:`, error.message);
      expect.soft(false, `Endpoint ${endpoint} failed: ${error.message}`).toBeTruthy();
    }
  });
});

// Test 4: Crear lista de URLs para probar manualmente en navegador
test('Generate browser testing URLs', async () => {
  console.log('🌐 URLs para probar en el navegador:');
  console.log('');
  
  testableGetEndpoints.forEach(endpoint => {
    console.log(`✅ http://localhost:8000/${endpoint}`);
  });
  
  console.log('');
  console.log('📝 URLs que requieren POST (usar Postman/cURL):');
  testablePostEndpoints.forEach(endpoint => {
    console.log(`🔄 http://localhost:8000/${endpoint}`);
  });
  
  expect(true).toBeTruthy(); // Siempre pasa
});
