import './commands'

// Configuración global para tests E2E
beforeEach(() => {
  // Limpiar localStorage y sessionStorage
  cy.window().then((win) => {
    win.localStorage.clear()
    win.sessionStorage.clear()
  })

  // Configurar viewport consistente
  cy.viewport(1280, 720)

  // Desactivar animaciones para tests más rápidos
  cy.window().then((win) => {
    const style = win.document.createElement('style')
    style.innerHTML = `
      * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }
    `
    win.document.head.appendChild(style)
  })
})

// Configuración para manejo de errores no capturados
Cypress.on('uncaught:exception', (err, runnable) => {
  // Retornar false para prevenir que Cypress falle por errores no relacionados con el test
  if (err.message.includes('Script error') ||
    err.message.includes('Loading chunk') ||
    err.message.includes('Network Error')) {
    return false
  }
  // Para otros errores, permitir que fallen los tests
  return true
})

// Configuración para manejo de redirecciones y navegación
Cypress.on('before:window:load', (win) => {
  // Interceptar y manejar redirecciones OAuth
  const originalFetch = win.fetch
  win.fetch = function (...args: any[]) {
    if (args[0] && args[0].includes('oauth')) {
      // Mock OAuth responses para tests
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ success: true, token: 'mock_oauth_token' })
      })
    }
    return originalFetch.apply(this, args)
  }
})
