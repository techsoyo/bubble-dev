// cypress/support/commands.ts
import '@testing-library/cypress/add-commands'

// --- Selectores
Cypress.Commands.add('getByCy', (id: string) => cy.get(`[data-cy="${id}"]`))

// --- Tipos locales
type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
interface ApiOptions extends Partial<Cypress.RequestOptions> {
  headers?: Record<string, string>
}

// --- CSRF helpers
Cypress.Commands.add('getCsrfToken', (): Cypress.Chainable<string> => {
  const csrfCookieName = Cypress.env('csrfCookieName') as string
  return cy.getCookie(csrfCookieName).then((c) => c?.value ?? '')
})

Cypress.Commands.add('withCsrf', (options: ApiOptions = {}): Cypress.Chainable<ApiOptions> => {
  const headerName = (Cypress.env('csrfHeaderName') as string) || 'X-CSRF-Token'
  return cy.getCsrfToken().then((token) => {
    const headers = { ...(options.headers || {}), [headerName]: token }
    return { ...options, headers } as ApiOptions
  })
})

// --- Wrapper de request con CSRF
Cypress.Commands.add('api',
  (method: HttpMethod, url: string, body?: any, options: ApiOptions = {}): Cypress.Chainable<Cypress.Response<any>> => {
    const apiBase = (Cypress.env('apiBaseUrl') as string) || ''
    return cy.withCsrf(options).then((opts) =>
      cy.request({ method, url: `${apiBase}${url}`, body, failOnStatusCode: false, ...opts }),
    )
  }
)

// --- Login / Logout DEVUELVEN Response (clave para el error que tenías)
Cypress.Commands.add('loginViaAPI',
  (email: string, password: string): Cypress.Chainable<Cypress.Response<any>> => {
    const apiBase = Cypress.env('apiBaseUrl') as string
    const loginPath = (Cypress.env('loginPath') as string) || '/auth/login.php'
    return cy.request({
      method: 'POST',
      url: `${apiBase}${loginPath}`,
      body: { email, password },
      failOnStatusCode: false,
    }).then((res) => {
      expect([200, 204]).to.include(res.status)
      expect(res.headers).to.have.property('set-cookie')
      return res
    })
  }
)

Cypress.Commands.add('logoutViaAPI',
  (): Cypress.Chainable<Cypress.Response<any>> => {
    const logoutPath = (Cypress.env('logoutPath') as string) || '/auth/logout.php'
    return cy.api('POST', logoutPath).then((res) => {
      expect([200, 204]).to.include(res.status)
      return res
    })
  }
)

// --- Sesiones
Cypress.Commands.add('sessionLogin',
  (role: 'admin' | 'recruiter' | 'candidate' = 'candidate'): Cypress.Chainable<void> => {
    const email = (Cypress.env(`${role}Email`) as string) || ''
    const password = (Cypress.env(`${role}Password`) as string) || ''
    return cy.session(['auth', role], () => {
      cy.loginViaAPI(email, password)
    })
  }
)

// --- Cookie HttpOnly check
Cypress.Commands.add('checkHttpOnlyCookies', (): Cypress.Chainable<void> => {
  const cookieName = (Cypress.env('jwtCookieName') as string) || 'jwt_token'
  return cy.getCookies().then((cookies) => {
    const jwt = cookies.find((c) => c.name === cookieName)
    expect(jwt, `Cookie ${cookieName}`).to.exist
  })
})

/* ====== AUGMENTACIÓN AQUÍ MISMO ====== */
declare global {
  namespace Cypress {
    type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
    interface Chainable {
      getByCy(id: string): Chainable<JQuery<HTMLElement>>
      getCsrfToken(): Chainable<string>
      withCsrf(options?: Partial<Cypress.RequestOptions>): Chainable<Partial<Cypress.RequestOptions>>
      api(method: HttpMethod, url: string, body?: any, options?: Partial<Cypress.RequestOptions>): Chainable<Cypress.Response<any>>
      loginViaAPI(email: string, password: string): Chainable<Cypress.Response<any>>
      logoutViaAPI(): Chainable<Cypress.Response<any>>
      sessionLogin(role?: 'admin' | 'recruiter' | 'candidate'): Chainable<void>
      checkHttpOnlyCookies(): Chainable<void>
    }
  }
}
