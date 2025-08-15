import { test, expect } from '@playwright/test';

test.describe('CDDashboard - Tabs en Header', () => {
  test('debe mostrar las pestañas en el header superior derecho', async ({ page }) => {
    // Ir al dashboard (asumiendo que ya hay una sesión activa)
    await page.goto('http://localhost:3002/dashboard/cddashboard');

    // Esperar a que cargue la página
    await page.waitForLoadState('networkidle');

    // Verificar que el header principal existe
    const header = page.locator('.bg-\\[\\#FF4785\\]').first();
    await expect(header).toBeVisible();

    // Verificar que el título del dashboard está presente
    await expect(page.locator('h1').filter({ hasText: 'Candidate Dashboard' })).toBeVisible();

    // Verificar que las 4 pestañas están en el header superior derecho
    const tabsList = page.locator('[role="tablist"]');
    await expect(tabsList).toBeVisible();

    // Verificar que las pestañas específicas están presentes
    const postulacionesTab = page.locator('[role="tab"]').filter({ hasText: 'Postulaciones' });
    const profileTab = page.locator('[role="tab"]').filter({ hasText: 'Profile' });
    const notificationsTab = page.locator('[role="tab"]').filter({ hasText: 'Notifications' });
    const securityTab = page.locator('[role="tab"]').filter({ hasText: 'Security' });

    await expect(postulacionesTab).toBeVisible();
    await expect(profileTab).toBeVisible();
    await expect(notificationsTab).toBeVisible();
    await expect(securityTab).toBeVisible();

    // Verificar que las pestañas están posicionadas en la parte derecha del header
    const headerContainer = page.locator('.bg-\\[\\#FF4785\\] .flex.items-center.gap-4').last();
    await expect(headerContainer).toContainText('Postulaciones');

    // Probar que las pestañas funcionan
    console.log('✅ Probando navegación entre pestañas...');

    // Click en Profile
    await profileTab.click();
    await page.waitForTimeout(500);

    // Verificar que el contenido de Profile se muestra
    const profileContent = page.locator('text="Personal Information"');
    await expect(profileContent).toBeVisible();

    // Click en Notifications
    await notificationsTab.click();
    await page.waitForTimeout(500);

    // Verificar que el contenido de Notifications se muestra
    const notificationsContent = page.locator('text="Recent Notifications"');
    await expect(notificationsContent).toBeVisible();

    // Click en Security
    await securityTab.click();
    await page.waitForTimeout(500);

    // Verificar que el contenido de Security se muestra
    const securityContent = page.locator('text="Change Password"');
    await expect(securityContent).toBeVisible();

    // Click en Postulaciones
    await postulacionesTab.click();
    await page.waitForTimeout(500);

    console.log('✅ Todas las pestañas funcionan correctamente');

    // Tomar screenshot para verificar el diseño
    await page.screenshot({
      path: 'dashboard-tabs-header.png',
      fullPage: true
    });
    console.log('📸 Screenshot guardado: dashboard-tabs-header.png');
  });

  test('debe tener estilos correctos en las pestañas del header', async ({ page }) => {
    await page.goto('http://localhost:3002/dashboard/cddashboard');
    await page.waitForLoadState('networkidle');

    // Verificar estilos de las pestañas
    const tabsList = page.locator('[role="tablist"]');

    // Verificar que tiene el backdrop blur y transparencia
    await expect(tabsList).toHaveClass(/bg-white\/20/);
    await expect(tabsList).toHaveClass(/backdrop-blur-sm/);

    // Verificar colores de las pestañas inactivas (texto blanco)
    const inactiveTab = page.locator('[role="tab"]').filter({ hasText: 'Profile' });
    await expect(inactiveTab).toHaveClass(/text-white/);

    // Click en una pestaña y verificar que se activa correctamente
    await inactiveTab.click();
    await page.waitForTimeout(300);

    // Verificar que la pestaña activa tiene los estilos correctos
    await expect(inactiveTab).toHaveClass(/data-\[state=active\]:bg-white/);

    console.log('✅ Estilos de pestañas verificados correctamente');
  });
});
