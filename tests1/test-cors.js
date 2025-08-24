#!/usr/bin/env node
/**
 * Script avanzado para probar exhaustivamente y eficientemente CORS en tus endpoints,
 * con validaciones ampliadas para cabeceras, manejo de tokens/sesiones y reporte JSON.
 */

const http = require('http');
const https = require('https');
const { URL } = require('url');
const fs = require('fs');
const path = require('path');

// Configuraciones generales
const CONFIG = {
  baseUrl: process.env.API_BASE_URL || 'http://localhost/bubble_of_talents_1.0/backend/public',
  timeout: 5000,
  origin: 'http://localhost:3000',
  concurrencyLimit: 5,
  simulateAuth: true,
  authToken: 'Bearer token_de_prueba',
  outputJsonReport: path.join(__dirname, 'cors-test-report.json'),
};


// Lista de endpoints actualizada con todos los métodos y paths proporcionados
const ENDPOINTS = [
  // Aplicaciones
  { path: '/api/applications', method: 'POST', needsPreflight: true },
  { path: '/api/applications', method: 'GET', needsPreflight: false },
  { path: '/api/applications/{candidato_id}/status', method: 'PATCH', needsPreflight: true },
  { path: '/api/applications/table-data', method: 'GET', needsPreflight: false },
  { path: '/api/applications/save-partial', method: 'POST', needsPreflight: true },
  { path: '/api/applications/{id}', method: 'PUT', needsPreflight: true },
  { path: '/api/applications/{id}', method: 'DELETE', needsPreflight: true },
  { path: '/api/applications', method: 'PATCH', needsPreflight: true }, // bulk update
  { path: '/api/applications', method: 'DELETE', needsPreflight: true }, // bulk delete

  // Autenticación
  { path: '/api/auth/social-login', method: 'POST', needsPreflight: true },
  { path: '/api/auth/login', method: 'POST', needsPreflight: true },
  { path: '/api/auth/me', method: 'GET', needsPreflight: false },
  { path: '/api/auth/refresh', method: 'POST', needsPreflight: true },
  { path: '/api/auth/set-cookie', method: 'POST', needsPreflight: true },
  { path: '/api/auth/remove-cookie', method: 'POST', needsPreflight: true },
  { path: '/api/auth/check-session', method: 'GET', needsPreflight: false },
  { path: '/auth.php?action=login', method: 'POST', needsPreflight: true },

  // Candidatos
  { path: '/api/candidates/register', method: 'POST', needsPreflight: true },
  { path: '/api/candidates', method: 'GET', needsPreflight: false },
  { path: '/api/candidates', method: 'POST', needsPreflight: true },
  { path: '/api/candidates/{id}', method: 'GET', needsPreflight: false },
  { path: '/api/candidates/{id}', method: 'PUT', needsPreflight: true },
  { path: '/api/candidates/{id}', method: 'DELETE', needsPreflight: true },
  { path: '/api/save-candidate.php', method: 'POST', needsPreflight: true },
  { path: '/api/candidates/save_v2.php', method: 'POST', needsPreflight: true },

  // Análisis de CV
  { path: '/api/analyze_cv.php', method: 'POST', needsPreflight: true },
  { path: '/api/cv/parse.php', method: 'POST', needsPreflight: true },
  { path: '/api/parse-cv', method: 'POST', needsPreflight: true },

  // Trabajos
  { path: '/jobs.php', method: 'GET', needsPreflight: false },
  { path: '/jobs.php', method: 'POST', needsPreflight: true },
  { path: '/api/jobs.php', method: 'PUT', needsPreflight: true },
  { path: '/api/jobs.php', method: 'DELETE', needsPreflight: true },
  { path: '/api/jobs?action=translate', method: 'GET', needsPreflight: false },
  { path: '/api/jobs?action=translate', method: 'POST', needsPreflight: true },

  // Dashboard
  { path: '/api/hr/dashboard-stats', method: 'GET', needsPreflight: false },
  { path: '/api/recruiter/{recruiter_id}/dashboard-stats', method: 'GET', needsPreflight: false },
  { path: '/api/me', method: 'GET', needsPreflight: false },

  // Notificaciones
  { path: '/api/notifications', method: 'POST', needsPreflight: true },
  { path: '/api/notifications/templates', method: 'GET', needsPreflight: false },
  { path: '/api/get-notification-preferences.php', method: 'GET', needsPreflight: false },
  { path: '/api/save-notification-preferences.php', method: 'POST', needsPreflight: true },
  { path: '/api/candidate-notifications.php', method: 'GET', needsPreflight: false },

  // Calendario
  { path: '/api/calendar/integrations', method: 'GET', needsPreflight: false },
  { path: '/api/calendar/connect', method: 'POST', needsPreflight: true },
  { path: '/api/calendar/disconnect', method: 'POST', needsPreflight: true },
  { path: '/api/calendar/events', method: 'GET', needsPreflight: false },
  { path: '/api/calendar/schedule-interview', method: 'POST', needsPreflight: true },
  { path: '/api/calendar/available-slots', method: 'GET', needsPreflight: false },

  // Archivos
  { path: '/api/files/upload-cv', method: 'POST', needsPreflight: true },

  // Habilidades y datos auxiliares
  { path: '/api/skills.php', method: 'GET', needsPreflight: false },
  { path: '/api/skills.php', method: 'POST', needsPreflight: true },
  { path: '/api/skills.php', method: 'PUT', needsPreflight: true },
  { path: '/api/skills.php', method: 'DELETE', needsPreflight: true },
  { path: '/api/skills', method: 'GET', needsPreflight: false },
  { path: '/api/recruiters.php', method: 'GET', needsPreflight: false },
  { path: '/api/recruiters', method: 'GET', needsPreflight: false },
  { path: '/api/departments.php', method: 'GET', needsPreflight: false },
  { path: '/api/departments', method: 'GET', needsPreflight: false },

  // Datos específicos
  { path: '/api/candidate_experiences.php', method: 'GET', needsPreflight: false },
  { path: '/api/candidate-experiences.php', method: 'GET', needsPreflight: false },
  { path: '/api/candidate_skills.php', method: 'GET', needsPreflight: false },
  { path: '/api/candidate-applications.php', method: 'GET', needsPreflight: false },
  { path: '/api/application_notes.php', method: 'GET', needsPreflight: false },
  { path: '/api/social_logins.php', method: 'GET', needsPreflight: false },
  { path: '/api/interviews.php', method: 'GET', needsPreflight: false },
  { path: '/api/job_benefits.php', method: 'GET', needsPreflight: false },
  { path: '/api/job_requirements.php', method: 'GET', needsPreflight: false },
  { path: '/api/job_skills.php', method: 'GET', needsPreflight: false },

  // Contenido y cultura
  { path: '/api/culture.php', method: 'GET', needsPreflight: false },
  { path: '/api/news.php', method: 'GET', needsPreflight: false },

  // Chatbot e IA
  { path: '/api/chatbot.php', method: 'POST', needsPreflight: true },
  { path: '/chatbot_decision_tree.php', method: 'POST', needsPreflight: true },
  { path: '/chatbot_analytics.php', method: 'POST', needsPreflight: true },

  // Monitoreo y sistema
  { path: '/api/web-vitals', method: 'POST', needsPreflight: true },
  { path: '/api/csp-violation', method: 'POST', needsPreflight: true },
  { path: '/api/sync', method: 'POST', needsPreflight: true },
  { path: '/api/ping', method: 'GET', needsPreflight: false },
  { path: '/api/info', method: 'GET', needsPreflight: false },

  // Validación
  { path: '/api/users/check-email', method: 'GET', needsPreflight: false },
  { path: '/api/users/check-username', method: 'GET', needsPreflight: false },

  // Asignación y gestión
  { path: '/api/assign-candidate', method: 'POST', needsPreflight: true },

  // Hardcodeados y desarrollo
  { path: 'http://localhost:8000/api/change-password.php', method: 'POST', needsPreflight: true },
  { path: 'http://localhost:8000/test_chatbot_crud.php', method: 'GET', needsPreflight: false },
  { path: 'http://localhost:8000/test_chatbot_crud.php', method: 'POST', needsPreflight: true },
  { path: 'http://localhost:8000/test_chatbot_crud.php', method: 'PUT', needsPreflight: true },
  { path: 'http://localhost:8000/test_chatbot_crud.php', method: 'DELETE', needsPreflight: true },
  { path: 'http://localhost:8000/auth/oauth/start.php', method: 'GET', needsPreflight: false },

  // CSRF y seguridad
  { path: '/api/auth/csrf-token.php', method: 'GET', needsPreflight: false },
  { path: '/api/auth/validate-csrf.php', method: 'POST', needsPreflight: true },

  // SecureAuth
  { path: '/auth/verify-session.php', method: 'POST', needsPreflight: true },
  { path: '/auth/candidate-login.php', method: 'POST', needsPreflight: true },
  { path: '/auth/staff-login-simple.php', method: 'POST', needsPreflight: true },
  { path: '/auth/logout.php', method: 'POST', needsPreflight: true },

  // Idiomas
  { path: '/language.php', method: 'POST', needsPreflight: true },
  { path: '/language.php', method: 'GET', needsPreflight: false },
  { path: '/api/language', method: 'GET', needsPreflight: false }
];


// Validar headers CORS con extensiones
function validateCorsHeaders(headers, method) {
  const origin = headers['access-control-allow-origin'];
  const methods = headers['access-control-allow-methods'] || '';
  const allowHeaders = headers['access-control-allow-headers'] || '';
  const credentials = headers['access-control-allow-credentials'];

  if (!origin || origin === '') return false;
  if (!methods.toUpperCase().includes(method.toUpperCase())) return false;

  if (CONFIG.simulateAuth) {
    if (!allowHeaders.toUpperCase().includes('AUTHORIZATION')) return false;
    if (!allowHeaders.toUpperCase().includes('CONTENT-TYPE')) return false;
    if (!credentials || credentials.toLowerCase() !== 'true') return false;
  }

  // Validar header Vary
  if (!('vary' in headers) || !headers.vary.toLowerCase().includes('origin')) {
    return false; // Es buena práctica que el header Vary incluya 'Origin'
  }

  // Validar respuesta a credenciales que no debe ser '*' cuando credentials es true
  if (credentials && credentials.toLowerCase() === 'true' && origin === '*') {
    return false; // No debe usar '*' si permite credenciales
  }

  return true;
}

// Hacer request HTTP/HTTPS
function makeRequest(url, method = 'GET', isPreflight = false) {
  return new Promise((resolve) => {
    const urlObj = new URL(url);
    const isHttps = urlObj.protocol === 'https:';
    const client = isHttps ? https : http;

    const headers = {
      Origin: CONFIG.origin,
      'User-Agent': 'Advanced-CORS-Test/1.0',
      Accept: 'application/json, text/plain, */*',
    };

    if (isPreflight) {
      headers['Access-Control-Request-Method'] = method;
      headers['Access-Control-Request-Headers'] = 'Content-Type, Authorization, X-Custom-Header';
    } else {
      if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
        headers['Content-Type'] = 'application/json';
        if (CONFIG.simulateAuth) {
          headers['Authorization'] = CONFIG.authToken;
          // Puedes agregar más headers personalizados y simular sesiones aquí si es necesario
          headers['X-Custom-Header'] = 'ValorPersonalizado';
        }
      }
    }

    const options = {
      hostname: urlObj.hostname,
      port: urlObj.port || (isHttps ? 443 : 80),
      path: urlObj.pathname + urlObj.search,
      method: isPreflight ? 'OPTIONS' : method,
      headers,
      timeout: CONFIG.timeout,
    };

    const req = client.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => (data += chunk));
      res.on('end', () => {
        const corsHeaders = {
          'access-control-allow-origin': res.headers['access-control-allow-origin'] || '',
          'access-control-allow-methods': res.headers['access-control-allow-methods'] || '',
          'access-control-allow-headers': res.headers['access-control-allow-headers'] || '',
          'access-control-allow-credentials': res.headers['access-control-allow-credentials'] || '',
          vary: res.headers['vary'] || '',
        };

        const hasCors = !!corsHeaders['access-control-allow-origin'];
        const corsValid = hasCors && validateCorsHeaders(corsHeaders, method);

        resolve({
          url,
          method: isPreflight ? 'OPTIONS (preflight)' : method,
          status: res.statusCode,
          statusText: res.statusMessage,
          success: res.statusCode >= 200 && res.statusCode < 300,
          hasCors,
          corsValid,
          corsHeaders,
          data: data.length > 1000 ? data.substring(0, 1000) + '...' : data,
        });
      });
    });

    req.on('error', (error) => resolve({ url, method, success: false, error: error.message }));
    req.on('timeout', () => {
      req.destroy();
      resolve({ url, method, success: false, error: 'Timeout' });
    });

    if (!isPreflight && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
      req.write('{}');
    }

    req.end();
  });
}

// Control de concurrencia
async function runConcurrent(tasks, limit) {
  const results = [];
  const executing = [];

  for (const task of tasks) {
    const p = task();
    results.push(p);

    if (limit <= tasks.length) {
      const e = p.then(() => executing.splice(executing.indexOf(e), 1));
      executing.push(e);
      if (executing.length >= limit) {
        await Promise.race(executing);
      }
    }
  }
  return Promise.all(results);
}

// Testear endpoint con preflight y reemplazos dinámicos
async function testEndpoint(endpoint) {
  let path = endpoint.path.replace(/\{[^}]+\}/g, '1'); // Reemplazar dinámicos con '1'
  let url = path.startsWith('http') ? path : `${CONFIG.baseUrl}${path}`;

  // Preflight
  if (endpoint.needsPreflight) {
    const preflight = await makeRequest(url, endpoint.method, true);
    if (!preflight.success) {
      return preflight;
    }
  }

  const real = await makeRequest(url, endpoint.method, false);
  return real;
}

// Principal - pruebas concurrentes y JSON report
async function testAllEndpoints() {
  console.log('🚀 Iniciando test avanzado de CORS para endpoints...');
  console.log(`📡 Base URL: ${CONFIG.baseUrl}`);
  console.log(`🌐 Origin simulado: ${CONFIG.origin}`);
  console.log(`⏱️  Timeout: ${CONFIG.timeout}ms`);
  console.log(`🔄 Concurrencia límite: ${CONFIG.concurrencyLimit}\n`);

  const tasks = ENDPOINTS.map((endpoint) => () => testEndpoint(endpoint));
  const results = await runConcurrent(tasks, CONFIG.concurrencyLimit);

  let successful = 0,
    corsValidCount = 0,
    failed = 0;

  const report = [];

  for (const r of results) {
    const statusLine = r.success
      ? r.corsValid
        ? '✅ CORS válidos'
        : r.hasCors
          ? '⚠️ CORS inválidos o incompletos'
          : '⚠️ Sin headers CORS'
      : `❌ Error: ${r.error || 'Desconocido'}`;

    console.log(`[${r.method}] ${r.url} - Status ${r.status || 'N/A'} - ${statusLine}`);

    if (r.success) {
      successful++;
      if (r.corsValid) corsValidCount++;
    } else {
      failed++;
    }

    report.push({
      url: r.url,
      method: r.method,
      status: r.status,
      success: r.success,
      hasCors: r.hasCors,
      corsValid: r.corsValid,
      error: r.error ?? null,
      corsHeaders: r.corsHeaders,
    });
  }

  console.log('\n' + '='.repeat(60));
  console.log('📊 REPORTE FINAL');
  console.log('='.repeat(60));
  console.log(`Total endpoints probados: ${ENDPOINTS.length}`);
  console.log(`✅ Peticiones exitosas: ${successful}`);
  console.log(`✅ Peticiones con CORS válidos: ${corsValidCount}`);
  console.log(`❌ Peticiones fallidas o sin CORS válido: ${failed}`);

  if (failed > 0) {
    console.log('\n➡️ Revisa el backend para corregir errores y ajustar headers CORS');
  } else {
    console.log('\n🎉 Todos los endpoints funcionan correctamente con CORS!');
  }

  // Guardar reporte JSON
  try {
    fs.writeFileSync(CONFIG.outputJsonReport, JSON.stringify(report, null, 2), 'utf-8');
    console.log(`\n📝 Reporte JSON guardado en: ${CONFIG.outputJsonReport}`);
  } catch (err) {
    console.error('⚠️ Error guardando reporte JSON:', err);
  }

  process.exit(failed > 0 ? 1 : 0);
}

// Ejecutar si es archivo principal
if (require.main === module) {
  testAllEndpoints().catch(console.error);
}

module.exports = { testAllEndpoints, makeRequest, ENDPOINTS };