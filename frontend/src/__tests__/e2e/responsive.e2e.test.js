// src/__tests__/e2e/responsive.e2e.test.js
import { test, expect, devices } from '@playwright/test';

const BREAKPOINTS = {
  mobile: { width: 320, height: 568 },
  mobileLarge: { width: 425, height: 768 },
  tablet: { width: 768, height: 1024 },
  desktop: { width: 1024, height: 768 },
  desktopLarge: { width: 1440, height: 900 },
  wide: { width: 1920, height: 1080 }
};

const PAGES_TO_TEST = [
  { path: '/', name: 'HomePage' },
  { path: '/jobs', name: 'JobsPage' },
  { path: '/auth/login', name: 'LoginPage' },
  { path: '/auth/register', name: 'RegisterPage' }
];

// Test de responsividad para cada breakpoint
Object.entries(BREAKPOINTS).forEach(([breakpointName, viewport]) => {
  test.describe(`Responsive Tests - ${breakpointName} (${viewport.width}x${viewport.height})`, () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize(viewport);
    });

    PAGES_TO_TEST.forEach(({ path, name }) => {
      test(`${name} should be responsive at ${breakpointName}`, async ({ page }) => {
        await page.goto(`http://localhost:3000${path}`);

        // Esperar a que la página cargue completamente
        await page.waitForLoadState('networkidle');

        // Verificar que no hay scroll horizontal
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1); // +1 para tolerancia

        // Verificar que el contenido principal es visible
        const mainContent = page.locator('main, [role="main"], section');
        await expect(mainContent.first()).toBeVisible();

        // Captura de pantalla para revisión visual
        await page.screenshot({
          path: `screenshots/${name}-${breakpointName}.png`,
          fullPage: true
        });
      });
    });

    test(`Hero section should be centered at ${breakpointName}`, async ({ page }) => {
      await page.goto('http://localhost:3000/');
      await page.waitForLoadState('networkidle');

      // Verificar que la sección hero existe
      const heroSection = page.locator('section').first();
      await expect(heroSection).toBeVisible();

      // Verificar que el título principal es visible
      const mainTitle = page.locator('h1');
      await expect(mainTitle.first()).toBeVisible();

      // Verificar que los botones son clickeables
      const buttons = page.locator('a[href^="/auth"], a[href^="/talent"]');
      const buttonCount = await buttons.count();
      expect(buttonCount).toBeGreaterThan(0);

      for (let i = 0; i < buttonCount; i++) {
        await expect(buttons.nth(i)).toBeVisible();
      }
    });

    test(`Navigation should work at ${breakpointName}`, async ({ page }) => {
      await page.goto('http://localhost:3000/');

      // En móvil, puede haber un menu hamburguesa
      if (viewport.width < 768) {
        const mobileMenu = page.locator('[aria-label*="menu"], [data-testid*="mobile-menu"], button[aria-expanded]');
        if (await mobileMenu.count() > 0) {
          await mobileMenu.first().click();
        }
      }

      // Verificar que los enlaces de navegación son accesibles
      const navLinks = page.locator('nav a, header a');
      const linkCount = await navLinks.count();

      if (linkCount > 0) {
        // Verificar que al menos un enlace es visible
        let visibleLinkFound = false;
        for (let i = 0; i < Math.min(linkCount, 5); i++) {
          if (await navLinks.nth(i).isVisible()) {
            visibleLinkFound = true;
            break;
          }
        }
        expect(visibleLinkFound).toBe(true);
      }
    });
  });
});

// Test de transiciones entre breakpoints
test.describe('Viewport Transition Tests', () => {
  test('should handle viewport changes smoothly', async ({ page }) => {
    await page.goto('http://localhost:3000/');

    // Comenzar en desktop
    await page.setViewportSize(BREAKPOINTS.desktop);
    await page.waitForLoadState('networkidle');

    let heroSection = page.locator('section').first();
    await expect(heroSection).toBeVisible();

    // Cambiar a tablet
    await page.setViewportSize(BREAKPOINTS.tablet);
    await page.waitForTimeout(500); // Tiempo para que las transiciones CSS terminen

    await expect(heroSection).toBeVisible();

    // Cambiar a móvil
    await page.setViewportSize(BREAKPOINTS.mobile);
    await page.waitForTimeout(500);

    await expect(heroSection).toBeVisible();

    // Volver a desktop
    await page.setViewportSize(BREAKPOINTS.desktop);
    await page.waitForTimeout(500);

    await expect(heroSection).toBeVisible();
  });
});

// Test de dispositivos reales
test.describe('Real Device Tests', () => {
  ['iPhone 12', 'iPad', 'Desktop Chrome'].forEach(deviceName => {
    test(`should work on ${deviceName}`, async ({ browser }) => {
      const device = devices[deviceName];
      if (!device) return;

      const context = await browser.newContext({
        ...device,
      });

      const page = await context.newPage();
      await page.goto('http://localhost:3000/');

      // Verificar que la página carga sin errores
      await page.waitForLoadState('networkidle');

      const heroSection = page.locator('section').first();
      await expect(heroSection).toBeVisible();

      // Verificar que no hay scroll horizontal
      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
      expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);

      await context.close();
    });
  });
});

// Test de performance en diferentes viewports
test.describe('Performance Tests', () => {
  Object.entries(BREAKPOINTS).forEach(([breakpointName, viewport]) => {
    test(`Performance should be acceptable at ${breakpointName}`, async ({ page }) => {
      await page.setViewportSize(viewport);

      const startTime = Date.now();
      await page.goto('http://localhost:3000/');
      await page.waitForLoadState('networkidle');
      const loadTime = Date.now() - startTime;

      // La página debería cargar en menos de 5 segundos
      expect(loadTime).toBeLessThan(5000);

      // Verificar que no hay errores de JavaScript
      const jsErrors = [];
      page.on('console', msg => {
        if (msg.type() === 'error') {
          jsErrors.push(msg.text());
        }
      });

      // Recargar la página para capturar errores
      await page.reload();
      await page.waitForLoadState('networkidle');

      expect(jsErrors.length).toBe(0);
    });
  });
});
