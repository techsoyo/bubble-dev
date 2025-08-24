import { test, expect } from '@playwright/test';

/**
 * Test E2E para la migración del endpoint de login de staff
 * Legacy: auth/staff-login.php → REST: /api/auth/staff-login
 */
test.describe('Staff Login Migration - auth/staff-login.php → /api/auth/staff-login', () => {
  const API_BASE = 'http://localhost:8000';
  const FRONTEND_BASE = 'http://localhost:3002';

  // Datos de test válidos (email corporativo)
  const validStaffCredentials = {
    email: 'ana.torres@bubblegum.agency',
    password: 'BubbleAdmin2025!'
  };

  // Datos de test inválidos
  const invalidCredentials = {
    email: 'invalid@gmail.com', // No es corporativo
    password: 'wrongpassword'
  };

  const nonCorporateEmail = {
    email: 'user@gmail.com', // Email válido pero no corporativo
    password: 'ValidPass123!'
  };

  test.beforeEach(async ({ page }) => {
    // Limpiar cookies y storage antes de cada test
    await page.context().clearCookies();
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });
  });

  test('should successfully login staff via REST API endpoint', async ({ page }) => {
    // Interceptar la llamada al endpoint REST
    let apiResponse: any = null;

    await page.route(`${API_BASE}/api/auth/staff-login`, async (route, request) => {
      const response = await route.fetch();
      apiResponse = await response.json();
      await route.fulfill({ response });
    });

    // 1. Navegar a la página de staff login
    await page.goto(`${FRONTEND_BASE}/staff/login`);

    // Esperar a que la página cargue completamente
    await expect(page).toHaveTitle(/Bubble of Talents/);

    // 2. Verificar que estamos en la página correcta
    await expect(page).toHaveURL(/\/staff\/login/);

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

    await emailInput.fill(validStaffCredentials.email);
    await passwordInput.fill(validStaffCredentials.password);

    // 4. Enviar el formulario
    await submitButton.click();

    // 5. Verificar que se hizo la llamada al endpoint REST
    await page.waitForTimeout(3000); // Esperar respuesta del servidor

    expect(apiResponse).not.toBeNull();
    expect(apiResponse.success).toBe(true);
    expect(apiResponse.user).toBeDefined();
    expect(apiResponse.user.user_type).toBe('staff');
    expect(apiResponse.user.email).toBe(validStaffCredentials.email);
    expect(apiResponse.token).toBeDefined();
    expect(apiResponse.session_token).toBeDefined();

    // 6. Verificar redirección al dashboard apropiado (HR o Recruiter)
    await expect(page).toHaveURL(/\/dashboard\/(hrdashboard|recruiterdashboard)/, { timeout: 10000 });

    // 7. Verificar que las cookies de sesión se establecieron
    const cookies = await page.context().cookies();
    const sessionCookie = cookies.find(c =>
      c.name.includes('PHPSESSID') || c.name.includes('session')
    );
    expect(sessionCookie).toBeDefined();
  });

  test('should reject non-corporate email addresses', async ({ page }) => {
    // Interceptar la llamada al endpoint REST
    let apiResponse: any = null;

    await page.route(`${API_BASE}/api/auth/staff-login`, async (route, request) => {
      const response = await route.fetch();
      apiResponse = await response.json();
      await route.fulfill({ response });
    });

    await page.goto(`${FRONTEND_BASE}/staff/login`);

    // Completar con email no corporativo
    await page.locator('input[type="email"]').fill(nonCorporateEmail.email);
    await page.locator('input[type="password"]').fill(nonCorporateEmail.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForTimeout(2000);

    // Verificar respuesta de error del endpoint REST
    expect(apiResponse).not.toBeNull();
    expect(apiResponse.success).toBe(false);
    expect(apiResponse.message).toContain('bubblegum.agency');

    // Verificar que NO se redirige (se queda en la página de login)
    await expect(page).toHaveURL(/\/staff\/login/, { timeout: 5000 });
  });

  test('should handle invalid staff credentials gracefully', async ({ page }) => {
    // Interceptar la llamada al endpoint REST
    let apiResponse: any = null;

    await page.route(`${API_BASE}/api/auth/staff-login`, async (route, request) => {
      const response = await route.fetch();
      apiResponse = await response.json();
      await route.fulfill({ response });
    });

    await page.goto(`${FRONTEND_BASE}/staff/login`);

    // Completar con credenciales inválidas pero email corporativo válido
    const invalidStaffCreds = {
      email: 'nonexistent@bubblegum.agency',
      password: 'wrongpassword'
    };

    await page.locator('input[type="email"]').fill(invalidStaffCreds.email);
    await page.locator('input[type="password"]').fill(invalidStaffCreds.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForTimeout(2000);

    // Verificar respuesta de error del endpoint REST
    expect(apiResponse).not.toBeNull();
    expect(apiResponse.success).toBe(false);
    expect(apiResponse.message).toMatch(/invalid|incorrect|not found|inactiv/i);

    // Verificar que NO se redirige (se queda en la página de login)
    await expect(page).toHaveURL(/\/staff\/login/, { timeout: 5000 });

    // Verificar que se muestra mensaje de error en UI
    const errorMessage = page.locator('[data-testid="error-message"]').or(
      page.locator('.error, .alert-danger').filter({ hasText: /invalid|error|incorrect/i })
    );

    if (await errorMessage.count() > 0) {
      await expect(errorMessage).toBeVisible();
    }
  });

  test('should validate required fields', async ({ page }) => {
    await page.goto(`${FRONTEND_BASE}/staff/login`);

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

  test('should test direct REST API endpoint functionality', async ({ request }) => {
    // Test directo del endpoint REST sin UI

    // 1. Test con credenciales válidas de staff
    const validResponse = await request.post(`${API_BASE}/api/auth/staff-login`, {
      headers: {
        'Content-Type': 'application/json',
      },
      data: validStaffCredentials
    });

    expect(validResponse.status()).toBe(200);
    const validData = await validResponse.json();
    expect(validData.success).toBe(true);
    expect(validData.user.user_type).toBe('staff');
    expect(validData.user.email.endsWith('@bubblegum.agency')).toBe(true);
    expect(validData.token).toBeDefined();
    expect(validData.session_token).toBeDefined();

    // 2. Test con email no corporativo
    const nonCorporateResponse = await request.post(`${API_BASE}/api/auth/staff-login`, {
      headers: {
        'Content-Type': 'application/json',
      },
      data: nonCorporateEmail
    });

    expect(nonCorporateResponse.status()).toBe(403);
    const nonCorporateData = await nonCorporateResponse.json();
    expect(nonCorporateData.success).toBe(false);
    expect(nonCorporateData.message).toContain('bubblegum.agency');

    // 3. Test con credenciales inválidas
    const invalidResponse = await request.post(`${API_BASE}/api/auth/staff-login`, {
      headers: {
        'Content-Type': 'application/json',
      },
      data: invalidCredentials
    });

    expect(invalidResponse.status()).toBe(403); // O 401 dependiendo de la implementación
    const invalidData = await invalidResponse.json();
    expect(invalidData.success).toBe(false);

    // 4. Test con datos faltantes
    const incompleteResponse = await request.post(`${API_BASE}/api/auth/staff-login`, {
      headers: {
        'Content-Type': 'application/json',
      },
      data: { email: 'test@bubblegum.agency' } // falta password
    });

    expect(incompleteResponse.status()).toBe(400);
    const incompleteData = await incompleteResponse.json();
    expect(incompleteData.success).toBe(false);
    expect(incompleteData.message).toContain('required');

    // 5. Test método HTTP incorrecto
    const wrongMethodResponse = await request.get(`${API_BASE}/api/auth/staff-login`);
    expect(wrongMethodResponse.status()).toBe(405);
  });

  test('should preserve staff session and cookies correctly', async ({ page, context }) => {
    // Interceptar para capturar respuesta completa
    await page.route(`${API_BASE}/api/auth/staff-login`, async (route, request) => {
      const response = await route.fetch();
      await route.fulfill({ response });
    });

    await page.goto(`${FRONTEND_BASE}/staff/login`);

    // Login exitoso
    await page.locator('input[type="email"]').fill(validStaffCredentials.email);
    await page.locator('input[type="password"]').fill(validStaffCredentials.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForTimeout(3000);

    // Verificar cookies después del login
    const cookies = await context.cookies();
    expect(cookies.length).toBeGreaterThan(0);

    // Verificar que la sesión persiste en navegación
    const expectedDashboard = /\/dashboard\/(hrdashboard|recruiterdashboard)/;
    await expect(page).toHaveURL(expectedDashboard);

    // Verificar que se mantiene autenticado navegando a otra página
    await page.goto(`${FRONTEND_BASE}/dashboard/hrdashboard`);
    await expect(page).toHaveURL(/\/dashboard\/hrdashboard/);

    // Verificar indicadores de usuario autenticado
    const userIndicator = page.locator('[data-testid="user-menu"]').or(
      page.locator('.user-name, .user-email, .staff-indicator').first()
    );

    if (await userIndicator.count() > 0) {
      await expect(userIndicator).toBeVisible();
    }
  });

  test('should handle different staff roles correctly', async ({ page }) => {
    // Test para verificar que diferentes roles redirigen correctamente
    let apiResponse: any = null;

    await page.route(`${API_BASE}/api/auth/staff-login`, async (route, request) => {
      const response = await route.fetch();
      apiResponse = await response.json();
      await route.fulfill({ response });
    });

    await page.goto(`${FRONTEND_BASE}/staff/login`);

    await page.locator('input[type="email"]').fill(validStaffCredentials.email);
    await page.locator('input[type="password"]').fill(validStaffCredentials.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForTimeout(3000);

    if (apiResponse && apiResponse.success) {
      const userRole = apiResponse.user.role;

      // Verificar redirección según el rol
      if (userRole === 'hr' || userRole === 'admin') {
        await expect(page).toHaveURL(/\/dashboard\/hrdashboard/);
      } else if (userRole === 'recruiter') {
        await expect(page).toHaveURL(/\/dashboard\/recruiterdashboard/);
      } else {
        // Rol desconocido, debería redirigir a algún dashboard por defecto
        await expect(page).toHaveURL(/\/dashboard\//);
      }
    }
  });
});
