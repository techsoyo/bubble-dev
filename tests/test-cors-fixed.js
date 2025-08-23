const http = require('http');
const https = require('https');
const { URL } = require('url');

// 🎯 Configuración
const BASE_URL = 'http://localhost/bubble_of_talents_1.0/backend/public';
const ORIGIN = 'http://localhost:3002'; // Origen simulado del frontend
const TIMEOUT = 5000;

console.log('🚀 Iniciando test de CORS MEJORADO...');
console.log(`📡 Base URL: ${BASE_URL}`);
console.log(`🌐 Origin simulado: ${ORIGIN}`);
console.log(`⏱️  Timeout: ${TIMEOUT}ms\n`);

// Lista optimizada de endpoints reales que existen
const endpoints = [
    // Auth
    { path: '/api/auth.php', methods: ['GET', 'POST'] },
    { path: '/api/auth/auth.php', methods: ['POST'] },
    { path: '/api/change-password.php', methods: ['POST'] },
    
    // Applications
    { path: '/api/applications.php', methods: ['GET', 'POST'] },
    { path: '/api/application_notes.php', methods: ['GET', 'POST'] },
    
    // Candidates
    { path: '/api/candidates.php', methods: ['GET', 'POST'] },
    { path: '/api/save-candidate.php', methods: ['POST'] },
    { path: '/api/candidate_skills.php', methods: ['GET'] },
    { path: '/api/candidate-applications.php', methods: ['GET'] },
    { path: '/api/candidate-experiences.php', methods: ['GET'] },
    
    // Jobs
    { path: '/api/jobs.php', methods: ['GET', 'POST'] },
    { path: '/api/job_benefits.php', methods: ['GET'] },
    { path: '/api/job_requirements.php', methods: ['GET'] },
    { path: '/api/job_skills.php', methods: ['GET'] },
    
    // Analysis
    { path: '/api/analyze_cv.php', methods: ['POST'] },
    { path: '/api/calculate_matching.php', methods: ['POST'] },
    
    // Notifications
    { path: '/api/notifications.php', methods: ['GET', 'POST'] },
    { path: '/api/get-notification-preferences.php', methods: ['GET'] },
    { path: '/api/save-notification-preferences.php', methods: ['POST'] },
    { path: '/api/candidate-notifications.php', methods: ['GET'] },
    
    // Data
    { path: '/api/departments.php', methods: ['GET'] },
    { path: '/api/recruiters.php', methods: ['GET'] },
    { path: '/api/social_logins.php', methods: ['GET'] },
    { path: '/api/interviews.php', methods: ['GET', 'POST'] },
    
    // Content
    { path: '/api/culture.php', methods: ['GET'] },
    { path: '/api/news.php', methods: ['GET'] },
    
    // Chatbot
    { path: '/api/chatbot.php', methods: ['POST'] },
    { path: '/chatbot_decision_tree.php', methods: ['GET', 'POST'] },
    { path: '/chatbot_analytics.php', methods: ['GET'] },
    
    // Health & Monitoring
    { path: '/api/health.php', methods: ['GET'] },
    { path: '/api/health-check.php', methods: ['GET'] },
    
    // Language
    { path: '/api/language.php', methods: ['GET', 'POST'] },
    { path: '/language.php', methods: ['GET', 'POST'] }
];

// Función mejorada para hacer peticiones HTTP
async function makeRequest(url, method = 'GET', timeout = TIMEOUT) {
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const isHttps = parsedUrl.protocol === 'https:';
        const httpModule = isHttps ? https : http;

        const options = {
            hostname: parsedUrl.hostname,
            port: parsedUrl.port || (isHttps ? 443 : 80),
            path: parsedUrl.pathname + parsedUrl.search,
            method: method,
            headers: {
                'Origin': ORIGIN,
                'User-Agent': 'CORS-Test/1.0',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            timeout: timeout
        };

        const req = httpModule.request(options, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                const headers = res.headers;
                resolve({
                    statusCode: res.statusCode,
                    statusMessage: res.statusMessage,
                    headers: headers,
                    data: data,
                    hasCors: !!(headers['access-control-allow-origin']),
                    corsOrigin: headers['access-control-allow-origin'] || null,
                    corsCredentials: headers['access-control-allow-credentials'] === 'true',
                    corsMethods: headers['access-control-allow-methods'] || null,
                    corsHeaders: headers['access-control-allow-headers'] || null
                });
            });
        });

        req.on('error', (err) => {
            reject(new Error(`Request error: ${err.message}`));
        });

        req.on('timeout', () => {
            req.destroy();
            reject(new Error('Request timeout'));
        });

        req.setTimeout(timeout);
        req.end();
    });
}

// Función para probar CORS preflight
async function testPreflight(endpoint) {
    try {
        const fullUrl = `${BASE_URL}${endpoint.path}`;
        console.log(`🧪 Probando PREFLIGHT OPTIONS: ${endpoint.path}`);
        
        const result = await makeRequest(fullUrl, 'OPTIONS');
        
        if (result.statusCode === 204 || result.statusCode === 200) {
            if (result.hasCors && (result.corsOrigin === ORIGIN || result.corsOrigin === '*')) {
                console.log(`   ✅ Preflight OK: ${endpoint.path} (Status: ${result.statusCode}, CORS: ${result.corsOrigin})`);
                return { success: true, status: result.statusCode, cors: result.corsOrigin };
            } else {
                console.log(`   ⚠️  Preflight sin CORS válido: ${endpoint.path} (Status: ${result.statusCode}, CORS: ${result.corsOrigin || 'none'})`);
                return { success: false, status: result.statusCode, cors: result.corsOrigin, issue: 'invalid_cors' };
            }
        } else {
            console.log(`   ❌ Preflight falló: ${endpoint.path} (Status: ${result.statusCode})`);
            return { success: false, status: result.statusCode, issue: 'preflight_error' };
        }
    } catch (error) {
        console.log(`   ❌ Error en preflight: ${endpoint.path} - ${error.message}`);
        return { success: false, error: error.message, issue: 'network_error' };
    }
}

// Función para probar petición real
async function testRealRequest(endpoint, method) {
    try {
        const fullUrl = `${BASE_URL}${endpoint.path}`;
        console.log(`🧪 Probando petición real: ${method} ${endpoint.path}`);
        
        const result = await makeRequest(fullUrl, method);
        
        if (result.statusCode >= 200 && result.statusCode < 400) {
            if (result.hasCors && (result.corsOrigin === ORIGIN || result.corsOrigin === '*')) {
                console.log(`   ✅ ${method} OK: ${endpoint.path} (Status: ${result.statusCode}, CORS: ${result.corsOrigin})`);
                return { success: true, status: result.statusCode, cors: result.corsOrigin };
            } else {
                console.log(`   ⚠️  ${method} sin CORS: ${endpoint.path} (Status: ${result.statusCode})`);
                return { success: false, status: result.statusCode, cors: result.corsOrigin, issue: 'no_cors' };
            }
        } else {
            console.log(`   ❌ ${method} falló: ${endpoint.path} (Status: ${result.statusCode})`);
            return { success: false, status: result.statusCode, issue: 'http_error' };
        }
    } catch (error) {
        console.log(`   ❌ Error en ${method}: ${endpoint.path} - ${error.message}`);
        return { success: false, error: error.message, issue: 'network_error' };
    }
}

// Ejecutar tests
async function runTests() {
    const results = {
        totalTests: 0,
        successCount: 0,
        corsValidCount: 0,
        failedEndpoints: [],
        corsIssues: []
    };

    for (const endpoint of endpoints) {
        console.log(`\n📋 Testing endpoint: ${endpoint.path}`);
        
        // Test OPTIONS preflight
        results.totalTests++;
        const preflightResult = await testPreflight(endpoint);
        
        if (preflightResult.success) {
            results.successCount++;
            results.corsValidCount++;
        } else {
            results.failedEndpoints.push({
                endpoint: endpoint.path,
                method: 'OPTIONS',
                issue: preflightResult.issue || 'unknown',
                status: preflightResult.status
            });
        }

        // Test each method for the endpoint
        for (const method of endpoint.methods.filter(m => m !== 'OPTIONS')) {
            results.totalTests++;
            const requestResult = await testRealRequest(endpoint, method);
            
            if (requestResult.success) {
                results.successCount++;
                results.corsValidCount++;
            } else {
                results.failedEndpoints.push({
                    endpoint: endpoint.path,
                    method: method,
                    issue: requestResult.issue || 'unknown',
                    status: requestResult.status
                });

                if (requestResult.issue === 'no_cors') {
                    results.corsIssues.push(`${method} ${endpoint.path}`);
                }
            }
        }
        
        // Pausa pequeña entre endpoints
        await new Promise(resolve => setTimeout(resolve, 100));
    }

    // Reporte final
    console.log('\n============================================================');
    console.log('📊 REPORTE FINAL DE CORS - VERSIÓN MEJORADA');
    console.log('============================================================');
    console.log(`Total tests ejecutados: ${results.totalTests}`);
    console.log(`✅ Tests exitosos: ${results.successCount}`);
    console.log(`🌐 Con CORS válidos: ${results.corsValidCount}`);
    console.log(`❌ Tests fallidos: ${results.totalTests - results.successCount}`);

    if (results.failedEndpoints.length > 0) {
        console.log('\n❌ ENDPOINTS CON PROBLEMAS:');
        results.failedEndpoints.forEach(failed => {
            console.log(`   - ${failed.method} ${failed.endpoint}: ${failed.issue} (Status: ${failed.status || 'N/A'})`);
        });
    }

    if (results.corsIssues.length > 0) {
        console.log('\n⚠️  ENDPOINTS SIN CORS (pero funcionando):');
        results.corsIssues.forEach(endpoint => {
            console.log(`   - ${endpoint}`);
        });
    }

    // Recommendations
    console.log('\n💡 RECOMENDACIONES:');
    
    if (results.corsIssues.length > 0) {
        console.log('   - Algunos endpoints funcionan pero no tienen headers CORS');
        console.log('   - Verificar que bootstrap.php se esté cargando en todos los endpoints');
    }
    
    if (results.failedEndpoints.some(f => f.issue === 'http_error')) {
        console.log('   - Verificar configuración de base de datos y permisos');
        console.log('   - Revisar logs de error del servidor web');
    }
    
    if (results.failedEndpoints.some(f => f.issue === 'preflight_error')) {
        console.log('   - Verificar configuración de CORS en .env');
        console.log('   - Asegurar que CORS_ALLOWED_ORIGINS incluye: ' + ORIGIN);
    }

    console.log('\n🔧 CONFIGURACIÓN CORS ESPERADA:');
    console.log('   Access-Control-Allow-Origin: ' + ORIGIN);
    console.log('   Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
    console.log('   Access-Control-Allow-Headers: Content-Type,Authorization,X-Requested-With');
    
    return results;
}

// Ejecutar los tests
runTests()
.then(results => {
    const successRate = Math.round((results.successCount / results.totalTests) * 100);
    console.log(`\n🎯 Tasa de éxito: ${successRate}% (${results.successCount}/${results.totalTests})`);
    
    if (successRate >= 80) {
        console.log('🎉 ¡Configuración CORS en buen estado!');
        process.exit(0);
    } else if (successRate >= 60) {
        console.log('⚠️  Configuración CORS necesita mejoras');
        process.exit(1);
    } else {
        console.log('❌ Configuración CORS requiere atención urgente');
        process.exit(2);
    }
})
.catch(error => {
    console.error('\n💥 Error general en el test:', error.message);
    process.exit(3);
});
