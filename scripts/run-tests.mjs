#!/usr/bin/env node

console.log('🧪 Ejecutando tests de endpoints migrados...\n');

import { spawn } from 'child_process';

// Verificar que el backend esté corriendo
async function checkBackend() {
  try {
    const response = await fetch('http://localhost:8000/api/health');
    return response.ok;
  } catch {
    return false;
  }
}

async function runTests() {
  // Verificar backend
  const backendRunning = await checkBackend();
  if (!backendRunning) {
    console.log('⚠️  El backend no está disponible en http://localhost:8000');
    console.log('💡 Inicia el backend con: pnpm dev:be (desde la raíz del proyecto)');
    console.log('🔄 Continuando con los tests de todas formas...\n');
  } else {
    console.log('✅ Backend disponible en http://localhost:8000\n');
  }

  // Ejecutar tests
  const testProcess = spawn('npx', ['playwright', 'test', 'tests/generated-endpoint-tests.spec.ts', '--reporter=line'], {
    stdio: 'inherit',
    shell: true,
    cwd: process.cwd()
  });

  testProcess.on('close', (code) => {
    if (code === 0) {
      console.log('\n🎉 Tests completados exitosamente!');
      console.log('\n📋 Archivos generados:');
      console.log('   📄 endpoint-usage-summary.json - Resumen de endpoints encontrados');
      console.log('   🧪 tests/generated-endpoint-tests.spec.ts - Tests de Playwright');
      console.log('   ⚙️  tests/test-config.json - Configuración de tests');
    } else {
      console.log('\n⚠️  Algunos tests fallaron. Revisa el output anterior.');
      console.log('\n💡 Posibles causas:');
      console.log('   • Backend no está corriendo');
      console.log('   • Endpoints no implementados aún');
      console.log('   • Cambios necesarios en la API');
    }
  });

  testProcess.on('error', (error) => {
    console.error('❌ Error ejecutando tests:', error.message);
  });
}

runTests();
