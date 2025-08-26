// cypress/support/index.d.ts
export { }

declare global {
  namespace Cypress {
    type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

    interface Chainable {
      // Selectores
      getByCy(id: string): Chainable<JQuery<HTMLElement>>

      // CSRF helpers
      getCsrfToken(): Chainable<string>
      withCsrf(options?: any): Chainable<any>

      // Requests
      api(method: HttpMethod, url: string, body?: any, options?: any): Chainable<Cypress.Response<any>>

      // Auth (devolvemos la Response para poder asertar si quieres)
      loginViaAPI(email: string, password: string): Chainable<Cypress.Response<any>>
      logoutViaAPI(): Chainable<Cypress.Response<any>>

      // Utilidades
      sessionLogin(role?: 'admin' | 'recruiter' | 'candidate'): Chainable<void>
      checkHttpOnlyCookies(): Chainable<void>
    }
  }
}
