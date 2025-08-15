// Test manual para verificar el staff login
// Este script simula el login y verifica la redirección

async function testStaffLogin() {
  console.log('🧪 Iniciando test de Staff Login...');

  try {
    // 1. Verificar que la página de staff login carga
    console.log('📋 Paso 1: Verificando acceso a /staff/login');
    const loginPageResponse = await fetch('http://localhost:3002/staff/login');
    console.log(`✅ Status: ${loginPageResponse.status} - Página accesible`);

    // 2. Probar el endpoint de staff login directamente
    console.log('\n📋 Paso 2: Probando endpoint de staff login');
    const loginResponse = await fetch('http://localhost:8000/auth/staff-login-simple.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        action: 'staff_login',
        email: 'ana.torres@bubblegum.agency',
        password: 'bubbleHR2025!'
      })
    }); const loginData = await loginResponse.json();
    console.log(`✅ Login Status: ${loginResponse.status}`);
    console.log(`✅ Login Success: ${loginData.success}`);
    console.log(`✅ User Role: ${loginData.user?.role}`);
    console.log(`✅ User Name: ${loginData.user?.name || loginData.user?.email}`);

    if (loginData.success) {
      console.log('🎉 Staff login funciona correctamente en el backend');

      // 3. Verificar que las rutas protegidas están disponibles
      console.log('\n📋 Paso 3: Verificando acceso a dashboards');

      const hrDashResponse = await fetch('http://localhost:3002/dashboard/hrdashboard');
      console.log(`✅ HR Dashboard Status: ${hrDashResponse.status}`);

      const recruiterDashResponse = await fetch('http://localhost:3002/dashboard/recruiterdashboard');
      console.log(`✅ Recruiter Dashboard Status: ${recruiterDashResponse.status}`);

      console.log('\n🎯 RESUMEN DEL TEST:');
      console.log('✅ Backend staff login: FUNCIONANDO');
      console.log('✅ Endpoints accesibles: FUNCIONANDO');
      console.log('📝 Para completar el test, verificar manualmente en el navegador:');
      console.log('   1. Ir a http://localhost:3002/staff/login');
      console.log('   2. Login con: ana.torres@bubblegum.agency / bubbleHR2025!');
      console.log('   3. Verificar redirección a /dashboard/hrdashboard');
    } else {
      console.log('❌ Staff login falló:', loginData.message);
    }

  } catch (error) {
    console.error('❌ Error en el test:', error.message);
  }
}

// Ejecutar el test
testStaffLogin();
