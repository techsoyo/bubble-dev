// Script para comprobar que todas las peticiones desde el frontend React están permitidas con CORS

const http = require('http');
const https = require('https');
const { URL } = require('url');

class CORSValidator {
  constructor(baseURL = '', requestTimeout = 10000) {
    this.baseURL = baseURL;
    this.requestTimeout = requestTimeout;
    this.testResults = [];
  }

  // Método principal para validar CORS en múltiples endpoints
  async validateAllEndpoints(endpoints) {
    console.log('🔍 Iniciando validación CORS para todos los endpoints...');
    const results = [];

    for (const endpoint of endpoints) {
      const result = await this.testCORSForEndpoint(endpoint);
      results.push(result);
    }

    this.generateReport(results);
    return results;
  }

  // Probar CORS para un endpoint específico
  async testCORSForEndpoint(endpoint) {
    const url = this.baseURL ? `${this.baseURL}${endpoint.path}` : endpoint.path;
    const method = endpoint.method || 'GET';
    const headers = endpoint.headers || {};

    console.log(`🧪 Probando CORS para: ${method} ${url}`);

    const testResult = {
      endpoint: endpoint.path,
      method: method,
      url: url,
      corsEnabled: false,
      preflightRequired: false,
      preflightPassed: false,
      actualRequestPassed: false,
      errors: [],
      corsHeaders: {},
      timestamp: new Date().toISOString()
    };

    try {
      // 1. Verificar si se requiere preflight
      testResult.preflightRequired = this.requiresPreflight(method, headers);

      // 2. Si requiere preflight, probarlo primero
      if (testResult.preflightRequired) {
        const preflightResult = await this.testPreflight(url, method, headers);
        testResult.preflightPassed = preflightResult.success;
        testResult.corsHeaders = preflightResult.headers;

        if (!preflightResult.success) {
          testResult.errors.push(`Preflight falló: ${preflightResult.error}`);
          return testResult;
        }
      }

      // 3. Realizar la petición real
      this.makeActualRequest(url, method, headers, endpoint.body);
      testResult.actualRequestPassed = actualResult.success;
      testResult.corsEnabled = actualResult.success;

      if (actualResult.headers) {
        testResult.corsHeaders = { ...testResult.corsHeaders, ...actualResult.headers };
      }

      if (!actualResult.success) {
        testResult.errors.push(`Petición real falló: ${actualResult.error}`);
      }

    } catch (error) {
      testResult.errors.push(`Error general: ${error.message}`);
    }

    return testResult;
  }

  // Determinar si se requiere preflight
  requiresPreflight(method, headers) {
    const simpleMethods = ['GET', 'HEAD', 'POST'];
    const simpleHeaders = [
      'accept', 'accept-language', 'content-language', 'content-type'
    ];

    // Si no es un método simple, requiere preflight
    if (!simpleMethods.includes(method.toUpperCase())) {
      return true;
    }

    // Si tiene headers personalizados, requiere preflight
    for (const header in headers) {
      if (!simpleHeaders.includes(header.toLowerCase())) {
        return true;
      }
    }

    // Si Content-Type no es simple, requiere preflight
    const contentType = headers['Content-Type'] || headers['content-type'];
    if (contentType) {
      const simpleContentTypes = [
        'application/x-www-form-urlencoded',
        'multipart/form-data',
        'text/plain'
      ];
      if (!simpleContentTypes.some(type => contentType.includes(type))) {
        return true;
      }
    }

    return false;
  }

  // Probar preflight request
  async testPreflight(url, method, headers) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.requestTimeout);

    try {
      const preflightHeaders = {
        'Access-Control-Request-Method': method,
        'Origin': window.location.origin
      };

      // Agregar headers personalizados al preflight
      const customHeaders = Object.keys(headers).filter(h =>
        !['accept', 'accept-language', 'content-language'].includes(h.toLowerCase())
      );

      if (customHeaders.length > 0) {
        preflightHeaders['Access-Control-Request-Headers'] = customHeaders.join(', ');
      }

      const response = await fetch(url, {
        method: 'OPTIONS',
        headers: preflightHeaders,
        signal: controller.signal
      });

      clearTimeout(timeoutId);

      return {
        success: response.ok,
        headers: this.extractCORSHeaders(response),
        error: response.ok ? null : `Estado HTTP: ${response.status}`
      };

    } catch (error) {
      clearTimeout(timeoutId);
      return {
        success: false,
        headers: {},
        error: error.message
      };
    }
  }

  // Realizar la petición real
  async makeActualRequest(url, method, headers, body) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.requestTimeout);

    try {
      const requestOptions = {
        method: method,
        headers: {
          'Origin': window.location.origin,
          ...headers
        },
        signal: controller.signal
      };

      if (body && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
        requestOptions.body = typeof body === 'string' ? body : JSON.stringify(body);
      }

      const response = await fetch(url, requestOptions);
      clearTimeout(timeoutId);

      return {
        success: true,
        headers: this.extractCORSHeaders(response),
        status: response.status,
        error: null
      };

    } catch (error) {
      clearTimeout(timeoutId);

      // Verificar si es un error CORS específico
      const isCORSError = error.message.includes('CORS') ||
        error.message.includes('cross-origin') ||
        error.message.includes('Access-Control-Allow-Origin');

      return {
        success: false,
        headers: {},
        error: error.message,
        isCORSError: isCORSError
      };
    }
  }

  // Extraer headers CORS relevantes
  extractCORSHeaders(response) {
    const corsHeaders = {};
    const relevantHeaders = [
      'access-control-allow-origin',
      'access-control-allow-methods',
      'access-control-allow-headers',
      'access-control-allow-credentials',
      'access-control-max-age',
      'access-control-expose-headers'
    ];

    relevantHeaders.forEach(header => {
      const value = response.headers.get(header);
      if (value !== null) {
        corsHeaders[header] = value;
      }
    });

    return corsHeaders;
  }

  // Generar reporte de resultados
  generateReport(results) {
    console.log('\n📊 REPORTE DE VALIDACIÓN CORS');
    console.log('='.repeat(50));

    const successful = results.filter(r => r.corsEnabled).length;
    const failed = results.length - successful;

    console.log(`✅ Endpoints con CORS habilitado: ${successful}`);
    console.log(`❌ Endpoints con problemas CORS: ${failed}`);
    console.log('');

    // Detalles por endpoint
    results.forEach(result => {
      const status = result.corsEnabled ? '✅' : '❌';
      console.log(`${status} ${result.method} ${result.endpoint}`);

      if (!result.corsEnabled) {
        result.errors.forEach(error => {
          console.log(`   🔸 ${error}`);
        });
      }

      // Mostrar headers CORS encontrados
      const corsHeadersCount = Object.keys(result.corsHeaders).length;
      if (corsHeadersCount > 0) {
        console.log(`   📋 Headers CORS: ${corsHeadersCount} encontrados`);
        Object.entries(result.corsHeaders).forEach(([key, value]) => {
          console.log(`      ${key}: ${value}`);
        });
      }
      console.log('');
    });

    // Recomendaciones
    console.log('💡 RECOMENDACIONES:');
    console.log('-'.repeat(30));

    const failedEndpoints = results.filter(r => !r.corsEnabled);
    if (failedEndpoints.length > 0) {
      console.log('• Configurar Access-Control-Allow-Origin en el servidor');
      console.log('• Verificar que los métodos HTTP estén permitidos');
      console.log('• Revisar headers personalizados en Access-Control-Allow-Headers');

      const hasPreflightIssues = failedEndpoints.some(r => r.preflightRequired && !r.preflightPassed);
      if (hasPreflightIssues) {
        console.log('• Configurar manejo de peticiones OPTIONS (preflight)');
      }
    } else {
      console.log('• ¡Todas las configuraciones CORS están correctas! 🎉');
    }
  }

  // Método de utilidad para probar un endpoint individual rápidamente
  async quickTest(url, method = 'GET') {
    console.log(`⚡ Prueba rápida CORS: ${method} ${url}`);

    try {
      const response = await fetch(url, {
        method: method,
        headers: {
          'Origin': window.location.origin
        }
      });

      const corsHeaders = this.extractCORSHeaders(response);
      const hasAllowOrigin = corsHeaders['access-control-allow-origin'];

      console.log(`✅ Estado: ${response.status}`);
      console.log(`🌐 CORS habilitado: ${hasAllowOrigin ? 'Sí' : 'No'}`);

      if (hasAllowOrigin) {
        console.log(`🎯 Allow-Origin: ${corsHeaders['access-control-allow-origin']}`);
      }

      return {
        success: response.ok && hasAllowOrigin,
        status: response.status,
        corsHeaders: corsHeaders
      };

    } catch (error) {
      console.log(`❌ Error: ${error.message}`);
      return {
        success: false,
        error: error.message
      };
    }
  }
}

// Función helper para uso fácil
function createCORSValidator(baseURL = '') {
  return new CORSValidator(baseURL);
}

// Ejemplo de uso
function ejemploDeUso() {
  // Crear instancia del validador
  const validator = createCORSValidator('http://localhost:3002');

  // Definir endpoints a probar
  const endpoints = [
    { path: '/api/users', method: 'GET' },
    { path: '/api/users', method: 'POST', headers: { 'Content-Type': 'application/json' } },
    { path: '/api/users/1', method: 'PUT', headers: { 'Authorization': 'Bearer token' } },
    { path: '/api/users/1', method: 'DELETE' }
  ];

  // Ejecutar validación
  validator.validateAllEndpoints(endpoints)
    .then(results => {
      console.log('Validación completada:', results);
    });

  // O para una prueba rápida individual:
  // validator.quickTest('/api/health', 'GET');
}

// Exportar para uso en módulos
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { CORSValidator, createCORSValidator };
}

// Hacer disponible globalmente en el navegador
if (typeof window !== 'undefined') {
  window.CORSValidator = CORSValidator;
  window.createCORSValidator = createCORSValidator;
}

console.log('✨ Script de validación CORS cargado correctamente');
console.log('💡 Uso: const validator = createCORSValidator("http://localhost:3002");');

// Ejecutar ejemplo si se ejecuta directamente
if (require.main === module) {
  ejemploDeUso();
}