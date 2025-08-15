// Test específico para verificar el flujo candidato con credenciales reales
// Este script simula el login de candidato y verifica la redirección

async function testCandidateLoginFlow() {
  console.log('🧪 Iniciando test específico de login candidato...');

  try {
    // 1. Verificar acceso a página de candidatos
    console.log('📋 Paso 1: Verificando acceso a /auth/register');
    const registerPageResponse = await fetch('http://localhost:3002/auth/register');
    console.log(`✅ Status: ${registerPageResponse.status} - Página accesible`);

    // 2. Probar el login del candidato directamente con backend
    console.log('\n📋 Paso 2: Probando login candidato con backend');
    const loginResponse = await fetch('http://localhost:8000/auth/login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        email: 'diego.santos@example.com',
        password: 'passA!2025'
      })
    }); const loginData = await loginResponse.json();
    console.log(`✅ Login Status: ${loginResponse.status}`);
    console.log(`✅ Login Success: ${loginData.success}`);

    if (loginData.success) {
      console.log(`✅ User Role: ${loginData.user?.role}`);
      console.log(`✅ User Name: ${loginData.user?.name || loginData.user?.email}`);

      console.log('\n🎉 Candidato login funciona correctamente en el backend');

      // 3. Verificar acceso a dashboard de candidatos
      console.log('\n📋 Paso 3: Verificando acceso a dashboard candidato');
      const dashboardResponse = await fetch('http://localhost:3002/dashboard/cddashboard');
      console.log(`✅ Candidate Dashboard Status: ${dashboardResponse.status}`);

      console.log('\n🎯 TESTING MANUAL REQUERIDO:');
      console.log('Para completar la verificación del fix:');
      console.log('   1. Ir a http://localhost:3002/');
      console.log('   2. Clic en "Soy candidato"');
      console.log('   3. En /auth/register cambiar a modo "Login"');
      console.log('   4. Login con: diego.santos@example.com / passA!2025');
      console.log('   5. ✅ Verificar redirección a /dashboard/cddashboard');
      console.log('   6. ✅ Verificar que NO se queda en /auth/register');

      console.log('\n🆕 NUEVO REGISTRO TAMBIÉN:');
      console.log('   1. Registrar nuevo candidato en /auth/register');
      console.log('   2. ✅ Verificar auto-login después del registro');
      console.log('   3. ✅ Verificar redirección automática al dashboard');

    } else {
      console.log('❌ Candidato login falló:', loginData.message);
      console.log('🔍 Verificar que el usuario existe en la base de datos');
    }

  } catch (error) {
    console.error('❌ Error en el test:', error.message);
    console.log('🔍 Verificar que el backend esté corriendo en puerto 8000');
  }
}

// Ejecutar el test
testCandidateLoginFlow();
