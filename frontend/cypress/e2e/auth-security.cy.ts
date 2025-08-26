describe('Auth + CSRF + HttpOnly (TS)', () => {
  it('login por API y acceso a dashboard según rol', () => {
    cy.sessionLogin('candidate')
    cy.visit('/dashboard')
    cy.getByCy('user-menu').should('exist')
  })

  it('CSRF double-submit en POST protegido', () => {
    cy.sessionLogin('admin')
    cy.api('POST', '/protected/echo', { ping: 'pong' }).then((res) => {
      expect(res.status).to.be.oneOf([200, 201])
    })
  })

  it('logout por API y acceso bloqueado', () => {
    cy.sessionLogin('recruiter')
    cy.logoutViaAPI()
    cy.visit('/dashboard', { failOnStatusCode: false })
    cy.location('pathname').should('match', /auth\/login|staff\/login|talent\/login/i)
  })
})
