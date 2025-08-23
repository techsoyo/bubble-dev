import { test, expect } from '@playwright/test';

/**
 * Test E2E para la migración del endpoint de login de candidatos
 * Legacy: auth/candidate-login.php → REST: /api/auth/candidate-login
 */
test.describe('Candidate Login Migration - auth/candidate-login.php → /api/auth/candidate-login', () => {
  const API_BASE = 'http://localhost:8000';
  const FRONTEND_BASE = 'http://localhost:3002';

  // Datos de test válidos
  const validCredentials = {
    email: 'test.candidate@example.com',
    password: 'TestPass123!'
  };

  // Datos de test inválidos
  const invalidCredentials = {
    email: 'invalid@example.com',
    password: 'wrongpassword'
  };

  test.beforeEach(async ({ page }) => {
    // Limpiar cookies y storage antes de cada test
    await page.context().clearCookies();
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });
  });

  test('should successfully login candidate via REST API endpoint', async ({ page }) => {
    // Interceptar la llamada al endpoint REST
    let apiResponse: any = null;

    await page.route(`${API_BASE}/api/auth/candidate-login`, async (route, request) => {
      const response = await route.fetch();
      apiResponse = await response.json();
      await route.fulfill({ response });
    });

    // 1. Navegar a la página de autenticación de candidatos
    await page.goto(`${FRONTEND_BASE}/auth/register`);

    // Esperar a que la página cargue completamente
    await expect(page).toHaveTitle(/Bubble of Talents/);

    // 2. Cambiar a modo login (si existe toggle)
    const loginToggle = page.locator('[data-testid="toggle-login"]').or(
      page.locator('button').filter({ hasText: /login|iniciar sesión/i })
    );

    if (await loginToggle.count() > 0) {
      await loginToggle.click();
      await page.waitForTimeout(500); // Esperar animación
    }

    // 3. Completar el formulario de login
    const emailInput = page.locator('input[type="email"]').or(
      page.locator('input[name="email"]')
    );
    const passwordInput = page.locator('input[type="password"]').or(
      page.locator('input[name="password"]')
    );
    const submitButton = page.locator('button[type="submit"]').or(
      page.locator('button').filter({ hasText: /login|entrar|iniciar/i })
    );

    await emailInput.fill(validCredentials.email);
    await passwordInput.fill(validCredentials.password);

    // 4. Enviar el formulario
    await submitButton.click();

    // 5. Verificar que se hizo la llamada al endpoint REST
    await page.waitForTimeout(2000); // Esperar respuesta del servidor

    expect(apiResponse).not.toBeNull();
    expect(apiResponse.success).toBe(true);
    expect(apiResponse.user).toBeDefined();
    expect(apiResponse.user.role).toBe('candidate');
    expect(apiResponse.user.email).toBe(validCredentials.email);
    expect(apiResponse.token).toBeDefined();

    // 6. Verificar redirección al dashboard de candidatos
    await expect(page).toHaveURL(/\/dashboard\/cddashboard/, { timeout: 10000 });

    // 7. Verificar que las cookies de sesión se establecieron
    const cookies = await page.context().cookies();
    const sessionCookie = cookies.find(c =>
      c.name.includes('PHPSESSID') || c.name.includes('session')
    );
    expect(sessionCookie).toBeDefined();
  });

  test('should handle invalid credentials gracefully', async ({ page }) => {
    // Interceptar la llamada al endpoint REST
    let apiResponse: any = null;

    await page.route(`${API_BASE}/api/auth/candidate-login`, async (route, request) => {
      const response = await route.fetch();
      apiResponse = await response.json();
      await route.fulfill({ response });
    });

    // 1. Navegar a la página de login
    await page.goto(`${FRONTEND_BASE}/auth/register`);

    // 2. Intentar cambiar a modo login
    const loginToggle = page.locator('[data-testid="toggle-login"]').or(
      page.locator('button').filter({ hasText: /login|iniciar sesión/i })
    );

    if (await loginToggle.count() > 0) {
      await loginToggle.click();
      await page.waitForTimeout(500);
    }

    // 3. Completar con credenciales inválidas
    const emailInput = page.locator('input[type="email"]').or(
      page.locator('input[name="email"]')
    );
    const passwordInput = page.locator('input[type="password"]').or(
      page.locator('input[name="password"]')
    );
    const submitButton = page.locator('button[type="submit"]').or(
      page.locator('button').filter({ hasText: /login|entrar|iniciar/i })
    );

    await emailInput.fill(invalidCredentials.email);
    await passwordInput.fill(invalidCredentials.password);

    // 4. Enviar el formulario
    await submitButton.click();

    // 5. Verificar respuesta de error del endpoint REST
    await page.waitForTimeout(2000);

    expect(apiResponse).not.toBeNull();
    expect(apiResponse.success).toBe(false);
    expect(apiResponse.message).toContain('Invalid credentials');

    // 6. Verificar que NO se redirige (se queda en la página de login)
    await expect(page).toHaveURL(/\/auth\/register/, { timeout: 5000 });

    // 7. Verificar que se muestra mensaje de error en UI
    const errorMessage = page.locator('[data-testid="error-message"]').or(
      page.locator('.error, .alert-danger').filter({ hasText: /invalid|inválid|error/i })
    );

    if (await errorMessage.count() > 0) {
      await expect(errorMessage).toBeVisible();
    }
  });

  test('should validate required fields', async ({ page }) => {
    await page.goto(`${FRONTEND_BASE}/auth/register`);

    // Cambiar a modo login si es posible
    const loginToggle = page.locator('[data-testid="toggle-login"]').or(
      page.locator('button').filter({ hasText: /login|iniciar sesión/i })
    );

    if (await loginToggle.count() > 0) {
      await loginToggle.click();
      await page.waitForTimeout(500);
    }

    // Intentar enviar formulario vacío
    const submitButton = page.locator('button[type="submit"]').or(
      page.locator('button').filter({ hasText: /login|entrar|iniciar/i })
    );

    await submitButton.click();

    // Verificar que se muestran errores de validación
    const emailError = page.locator('[data-testid="email-error"]').or(
      page.locator('input[type="email"] + .error, input[name="email"] + .error')
    );

    const passwordError = page.locator('[data-testid="password-error"]').or(
      page.locator('input[type="password"] + .error, input[name="password"] + .error')
    );

    // Al menos uno de los errores debe estar visible
    const hasEmailError = await emailError.count() > 0 && await emailError.isVisible();
    const hasPasswordError = await passwordError.count() > 0 && await passwordError.isVisible();

    expect(hasEmailError || hasPasswordError).toBe(true);
  });

  test('should test direct API endpoint functionality', async ({ request }) => {
    // Test directo del endpoint REST sin UI

    // 1. Test con credenciales válidas
    const validResponse = await request.post(`${API_BASE}/api/auth/candidate-login`, {
      headers: {
        'Content-Type': 'application/json',
      },
      data: validCredentials
    });

    expect(validResponse.status()).toBe(200);
    const validData = await validResponse.json();
    expect(validData.success).toBe(true);
    expect(validData.user.role).toBe('candidate');
    expect(validData.token).toBeDefined();

    // 2. Test con credenciales inválidas
    const invalidResponse = await request.post(`${API_BASE}/api/auth/candidate-login`, {
      headers: {
        'Content-Type': 'application/json',
      },
      data: invalidCredentials
    });

    expect(invalidResponse.status()).toBe(401);
    const invalidData = await invalidResponse.json();
    expect(invalidData.success).toBe(false);
    expect(invalidData.message).toContain('Invalid credentials');

    // 3. Test con datos faltantes
    const incompleteResponse = await request.post(`${API_BASE}/api/auth/candidate-login`, {
      headers: {
        'Content-Type': 'application/json',
      },
      data: { email: 'test@test.com' } // falta password
    });

    expect(incompleteResponse.status()).toBe(400);
    const incompleteData = await incompleteResponse.json();
    expect(incompleteData.success).toBe(false);
    expect(incompleteData.message).toContain('required');

    // 4. Test método HTTP incorrecto
    const wrongMethodResponse = await request.get(`${API_BASE}/api/auth/candidate-login`);
    expect(wrongMethodResponse.status()).toBe(405);
  });

  test('should preserve session and cookies correctly', async ({ page, context }) => {
    // Interceptar para capturar cookies
    await page.route(`${API_BASE}/api/auth/candidate-login`, async (route, request) => {
      const response = await route.fetch();
      await route.fulfill({ response });
    });

    await page.goto(`${FRONTEND_BASE}/auth/register`);

    // Login exitoso
    const loginToggle = page.locator('[data-testid="toggle-login"]').or(
      page.locator('button').filter({ hasText: /login|iniciar sesión/i })
    );

    if (await loginToggle.count() > 0) {
      await loginToggle.click();
    }

    await page.locator('input[type="email"]').fill(validCredentials.email);
    await page.locator('input[type="password"]').fill(validCredentials.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForTimeout(2000);

    // Verificar cookies después del login
    const cookies = await context.cookies();
    expect(cookies.length).toBeGreaterThan(0);

    // Verificar que la sesión persiste en navegación
    await page.goto(`${FRONTEND_BASE}/dashboard/cddashboard`);
    await expect(page).toHaveURL(/\/dashboard\/cddashboard/);

    // Verificar que se mantiene autenticado
    const userIndicator = page.locator('[data-testid="user-menu"]').or(
      page.locator('.user-name, .user-email').first()
    );

    if (await userIndicator.count() > 0) {
      await expect(userIndicator).toBeVisible();
    }
  });
});
