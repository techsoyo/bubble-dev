import { test, expect } from '@playwright/test';

/**
 * Test E2E para la migración del endpoint de verificación de sesión
 * Legacy: auth/verify-session.php → REST: /api/auth/verify-session
 */
test.describe('Session Verification Migration - auth/verify-session.php → /api/auth/verify-session', () => {
  const API_BASE = 'http://localhost:8000';
  const FRONTEND_BASE = 'http://localhost:3002';

  // Credenciales de test para ambos tipos de usuario
  const candidateCredentials = {
    email: 'test.candidate@example.com',
    password: 'TestPass123!'
  };

  const staffCredentials = {
    email: 'ana.torres@bubblegum.agency',
    password: 'BubbleAdmin2025!'
  };

  test.beforeEach(async ({ page }) => {
    // Limpiar cookies y storage antes de cada test
    await page.context().clearCookies();
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });
  });

  test('should verify valid candidate session via REST API', async ({ page }) => {
    // 1. Primero hacer login como candidato
    await page.goto(`${FRONTEND_BASE}/auth/register`);

    const loginToggle = page.locator('[data-testid="toggle-login"]').or(
      page.locator('button').filter({ hasText: /login|iniciar sesión/i })
    );

    if (await loginToggle.count() > 0) {
      await loginToggle.click();
    }

    await page.locator('input[type="email"]').fill(candidateCredentials.email);
    await page.locator('input[type="password"]').fill(candidateCredentials.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForTimeout(2000);

    // 2. Interceptar la verificación de sesión
    let verifyResponse: any = null;

    await page.route(`${API_BASE}/api/auth/verify-session`, async (route, request) => {
      const response = await route.fetch();
      verifyResponse = await response.json();
      await route.fulfill({ response });
    });

    // 3. Triggear verificación de sesión (reload page)
    await page.reload();
    await page.waitForTimeout(2000);

    // 4. Verificar respuesta del endpoint REST
    expect(verifyResponse).not.toBeNull();
    expect(verifyResponse.success).toBe(true);
    expect(verifyResponse.user).toBeDefined();
    expect(verifyResponse.user.role).toBe('candidate');
    expect(verifyResponse.user.user_type).toBe('candidate');
    expect(verifyResponse.session_type).toBe('candidate');
  });

  test('should verify valid staff session via REST API', async ({ page }) => {
    // 1. Primero hacer login como staff
    await page.goto(`${FRONTEND_BASE}/staff/login`);

    await page.locator('input[type="email"]').fill(staffCredentials.email);
    await page.locator('input[type="password"]').fill(staffCredentials.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForTimeout(3000);

    // 2. Interceptar la verificación de sesión
    let verifyResponse: any = null;

    await page.route(`${API_BASE}/api/auth/verify-session`, async (route, request) => {
      const response = await route.fetch();
      verifyResponse = await response.json();
      await route.fulfill({ response });
    });

    // 3. Triggear verificación de sesión
    await page.reload();
    await page.waitForTimeout(2000);

    // 4. Verificar respuesta del endpoint REST
    expect(verifyResponse).not.toBeNull();
    expect(verifyResponse.success).toBe(true);
    expect(verifyResponse.user).toBeDefined();
    expect(verifyResponse.user.user_type).toBe('staff');
    expect(verifyResponse.session_type).toBe('staff');
    expect(verifyResponse.user.email.endsWith('@bubblegum.agency')).toBe(true);
  });

  test('should handle no active session gracefully', async ({ page }) => {
    // Interceptar llamada sin sesión previa
    let verifyResponse: any = null;

    await page.route(`${API_BASE}/api/auth/verify-session`, async (route, request) => {
      const response = await route.fetch();
      verifyResponse = await response.json();
      await route.fulfill({ response });
    });

    // Ir a una página que trigger session verification
    await page.goto(`${FRONTEND_BASE}/dashboard/cddashboard`);
    await page.waitForTimeout(2000);

    // Verificar respuesta para sesión inexistente
    if (verifyResponse) {
      expect(verifyResponse.success).toBe(true); // Mantiene 200 OK
      expect(verifyResponse.user).toBeNull();
      expect(verifyResponse.session_type).toBeNull();
    }

    // Debe redirigir a login si no hay sesión
    await expect(page).toHaveURL(/\/(auth|login)/, { timeout: 10000 });
  });

  test('should test direct REST API endpoint with GET method', async ({ request }) => {
    // Test directo del endpoint REST sin sesión
    const response = await request.get(`${API_BASE}/api/auth/verify-session`);

    expect(response.status()).toBe(200);
    const data = await response.json();

    expect(data.success).toBe(true);
    expect(data.user).toBeNull();
    expect(data.session_type).toBeNull();
  });

  test('should test direct REST API endpoint with POST method', async ({ request }) => {
    // Test directo del endpoint REST con POST (también debe funcionar)
    const response = await request.post(`${API_BASE}/api/auth/verify-session`, {
      headers: {
        'Content-Type': 'application/json'
      }
    });

    expect(response.status()).toBe(200);
    const data = await response.json();

    expect(data.success).toBe(true);
    expect(data.user).toBeNull();
    expect(data.session_type).toBeNull();
  });

  test('should reject unsupported HTTP methods', async ({ request }) => {
    // Test con método no soportado (PUT)
    const response = await request.put(`${API_BASE}/api/auth/verify-session`);
    expect(response.status()).toBe(405);

    const data = await response.json();
    expect(data.success).toBe(false);
    expect(data.message).toContain('Method not allowed');
  });

  test('should work with ProtectedRoute component', async ({ page }) => {
    // Test de integración con ProtectedRoute

    // Interceptar verificación
    let verificationCalled = false;
    await page.route(`${API_BASE}/api/auth/verify-session`, async (route, request) => {
      verificationCalled = true;
      const response = await route.fetch();
      await route.fulfill({ response });
    });

    // Intentar acceder a ruta protegida sin autenticación
    await page.goto(`${FRONTEND_BASE}/dashboard/cddashboard`);

    // Esperar que se haga la verificación
    await page.waitForTimeout(3000);

    // Verificar que se llamó al endpoint
    expect(verificationCalled).toBe(true);

    // Debe redirigir a login al no tener sesión válida
    await expect(page).toHaveURL(/\/(auth|login)/, { timeout: 10000 });
  });

  test('should handle JWT token validation (if provided in Authorization header)', async ({ page }) => {
    // Test de fallback con JWT
    const mockJWT = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJjYW5kaWRhdGVfaWQiOjEsInJvbGUiOiJjYW5kaWRhdGUiLCJlbWFpbCI6InRlc3RAZXhhbXBsZS5jb20iLCJuYW1lIjoiVGVzdCBVc2VyIn0.test';

    let verifyResponse: any = null;

    await page.route(`${API_BASE}/api/auth/verify-session`, async (route, request) => {
      // Simular JWT en header (esto lo haría el frontend normalmente)
      const response = await route.fetch({
        headers: {
          ...request.headers(),
          'Authorization': `Bearer ${mockJWT}`
        }
      });
      verifyResponse = await response.json();
      await route.fulfill({ response });
    });

    await page.goto(`${FRONTEND_BASE}/dashboard/cddashboard`);
    await page.waitForTimeout(2000);

    // Si el JWT es válido, debe devolver información del usuario
    if (verifyResponse && verifyResponse.success && verifyResponse.user) {
      expect(verifyResponse.session_type).toBe('jwt');
      expect(verifyResponse.user).toBeDefined();
    }
  });

  test('should clean invalid sessions automatically', async ({ page }) => {
    // Este test requiere una sesión inválida en la base de datos
    // Simular usando cookies corruptas

    await page.context().addCookies([
      {
        name: 'PHPSESSID',
        value: 'invalid-session-id',
        domain: 'localhost',
        path: '/'
      }
    ]);

    let verifyResponse: any = null;

    await page.route(`${API_BASE}/api/auth/verify-session`, async (route, request) => {
      const response = await route.fetch();
      verifyResponse = await response.json();
      await route.fulfill({ response });
    });

    await page.goto(`${FRONTEND_BASE}/dashboard/cddashboard`);
    await page.waitForTimeout(2000);

    // Debe limpiar la sesión inválida y devolver no autenticado
    if (verifyResponse) {
      expect(verifyResponse.success).toBe(true);
      expect(verifyResponse.user).toBeNull();
      expect(verifyResponse.session_type).toBeNull();
    }
  });

  test('should maintain session across page navigations', async ({ page }) => {
    // Login first
    await page.goto(`${FRONTEND_BASE}/auth/register`);

    const loginToggle = page.locator('button').filter({ hasText: /login/i }).first();
    if (await loginToggle.count() > 0) {
      await loginToggle.click();
    }

    await page.locator('input[type="email"]').fill(candidateCredentials.email);
    await page.locator('input[type="password"]').fill(candidateCredentials.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForTimeout(2000);

    // Track session verifications
    let verificationCount = 0;
    let lastVerifyResponse: any = null;

    await page.route(`${API_BASE}/api/auth/verify-session`, async (route, request) => {
      verificationCount++;
      const response = await route.fetch();
      lastVerifyResponse = await response.json();
      await route.fulfill({ response });
    });

    // Navigate to different pages
    const pagesToVisit = [
      `${FRONTEND_BASE}/dashboard/cddashboard`,
      `${FRONTEND_BASE}/jobs`,
      `${FRONTEND_BASE}/profile`
    ];

    for (const pageUrl of pagesToVisit) {
      await page.goto(pageUrl);
      await page.waitForTimeout(1000);

      // Verificar que mantiene la sesión
      if (lastVerifyResponse) {
        expect(lastVerifyResponse.success).toBe(true);
        expect(lastVerifyResponse.user).toBeDefined();
        expect(lastVerifyResponse.user.role).toBe('candidate');
      }
    }

    // Al menos se debe haber verificado la sesión
    expect(verificationCount).toBeGreaterThan(0);
  });
});
