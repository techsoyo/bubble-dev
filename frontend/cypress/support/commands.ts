// Cypress Commands personalizados para Bubble of Talents
// Funciones helper para automatizar pruebas E2E

// =============================================
// AUTENTICACIÓN
// =============================================

Cypress.Commands.add('loginAsCandidate', (email: string, password: string) => {
  cy.visit('/candidates/login')
  cy.get('[data-cy="candidate-email"]').type(email)
  cy.get('[data-cy="candidate-password"]').type(password)
  cy.get('[data-cy="candidate-login-btn"]').click()
  cy.url({ timeout: 10000 }).should('include', '/dashboard/cddashboard')
})

Cypress.Commands.add('loginAsStaff', (email: string, password: string) => {
  cy.visit('/staff/login')
  cy.get('[data-cy="staff-email"]').type(email)
  cy.get('[data-cy="staff-password"]').type(password)
  cy.get('[data-cy="staff-login-btn"]').click()
  cy.url({ timeout: 10000 }).should('include', '/dashboard')
})

Cypress.Commands.add('logout', () => {
  cy.get('[data-cy="user-menu"]').click()
  cy.get('[data-cy="logout-btn"]').click()
  cy.url().should('include', '/')
})

// =============================================
// GESTIÓN DE CVs
// =============================================

Cypress.Commands.add('uploadCV', (cvFileName: string) => {
  cy.get('[data-cy="cv-upload-input"]').selectFile(`cypress/fixtures/test-cvs/${cvFileName}`, { force: true })
  cy.get('[data-cy="cv-upload-btn"]').click()
  cy.get('[data-cy="cv-validation-modal"]').should('be.visible')
})

Cypress.Commands.add('validateCVData', (expectedData: any) => {
  // Verificar que la IA procesó correctamente el CV
  cy.get('[data-cy="cv-validation-modal"]').within(() => {
    cy.get('[data-cy="candidate-name"]').should('contain', expectedData.name)
    cy.get('[data-cy="candidate-email"]').should('contain', expectedData.email)
    cy.get('[data-cy="candidate-phone"]').should('contain', expectedData.phone)
    cy.get('[data-cy="save-cv-btn"]').click()
  })
})

// =============================================
// NAVEGACIÓN Y BÚSQUEDA
// =============================================

Cypress.Commands.add('searchJobs', (query: string) => {
  cy.visit('/jobs')
  cy.get('[data-cy="job-search-input"]').type(query)
  cy.get('[data-cy="search-btn"]').click()
  cy.get('[data-cy="job-results"]').should('be.visible')
})

Cypress.Commands.add('filterJobs', (filters: any) => {
  cy.visit('/jobs')

  if (filters.location) {
    cy.get('[data-cy="location-filter"]').select(filters.location)
  }
  if (filters.department) {
    cy.get('[data-cy="department-filter"]').select(filters.department)
  }
  if (filters.type) {
    cy.get('[data-cy="type-filter"]').select(filters.type)
  }

  cy.get('[data-cy="apply-filters-btn"]').click()
})

Cypress.Commands.add('applyToJob', (jobId: string) => {
  cy.visit(`/jobs/${jobId}`)
  cy.get('[data-cy="apply-job-btn"]').click()
  cy.url().should('include', '/jobs/apply')
})

// =============================================
// DASHBOARDS
// =============================================

Cypress.Commands.add('verifyCandidateDashboard', () => {
  cy.url().should('include', '/dashboard/cddashboard')

  // Verificar elementos principales del dashboard candidato
  cy.get('[data-cy="applications-count"]').should('be.visible')
  cy.get('[data-cy="saved-jobs-count"]').should('be.visible')
  cy.get('[data-cy="profile-completion"]').should('be.visible')
  cy.get('[data-cy="recommended-jobs"]').should('be.visible')
})

Cypress.Commands.add('verifyHRDashboard', () => {
  cy.url().should('include', '/dashboard/hrdashboard')

  // Verificar métricas principales
  cy.get('[data-cy="total-candidates"]').should('be.visible')
  cy.get('[data-cy="active-jobs"]').should('be.visible')
  cy.get('[data-cy="applications-today"]').should('be.visible')
  cy.get('[data-cy="conversion-rate"]').should('be.visible')
})

Cypress.Commands.add('verifyRecruiterDashboard', () => {
  cy.url().should('include', '/dashboard/recruiterdashboard')

  // Verificar elementos del dashboard recruiter
  cy.get('[data-cy="assigned-jobs"]').should('be.visible')
  cy.get('[data-cy="pipeline-candidates"]').should('be.visible')
  cy.get('[data-cy="scheduled-interviews"]').should('be.visible')
})

// =============================================
// GESTIÓN DE CANDIDATOS (HR/RECRUITER)
// =============================================

Cypress.Commands.add('changeApplicationStatus', (applicationId: string, newStatus: string) => {
  cy.visit(`/dashboard/applications/${applicationId}`)
  cy.get('[data-cy="status-dropdown"]').select(newStatus)
  cy.get('[data-cy="save-status-btn"]').click()
  cy.get('[data-cy="status-change-success"]').should('be.visible')
})

Cypress.Commands.add('scheduleInterview', (applicationId: string, interviewData: any) => {
  cy.visit(`/dashboard/applications/${applicationId}`)
  cy.get('[data-cy="schedule-interview-btn"]').click()

  cy.get('[data-cy="interview-date"]').type(interviewData.date)
  cy.get('[data-cy="interview-time"]').type(interviewData.time)
  cy.get('[data-cy="interview-type"]').select(interviewData.type)
  cy.get('[data-cy="interview-duration"]').select(interviewData.duration)

  cy.get('[data-cy="save-interview-btn"]').click()
  cy.get('[data-cy="interview-scheduled-success"]').should('be.visible')
})

// =============================================
// CHATBOT
// =============================================

Cypress.Commands.add('interactWithChatbot', (conversation: any[]) => {
  cy.get('[data-cy="chatbot-toggle"]').click()

  conversation.forEach((step) => {
    if (step.userInput) {
      cy.get('[data-cy="chatbot-input"]').type(step.userInput)
      cy.get('[data-cy="chatbot-send"]').click()
    }

    if (step.expectedResponse) {
      cy.get('[data-cy="chatbot-messages"]').should('contain', step.expectedResponse)
    }

    if (step.expectedOptions) {
      step.expectedOptions.forEach((option: string) => {
        cy.get('[data-cy="chatbot-options"]').should('contain', option)
      })
    }
  })
})

// =============================================
// NOTIFICACIONES
// =============================================

Cypress.Commands.add('verifyNotification', (notificationData: any) => {
  cy.get('[data-cy="notifications-bell"]').click()
  cy.get('[data-cy="notifications-list"]').should('contain', notificationData.title)
  cy.get('[data-cy="notifications-list"]').should('contain', notificationData.message)
})

Cypress.Commands.add('clearNotifications', () => {
  cy.get('[data-cy="notifications-bell"]').click()
  cy.get('[data-cy="clear-all-notifications"]').click()
  cy.get('[data-cy="notifications-empty"]').should('be.visible')
})

// =============================================
// UTILIDADES GENERALES
// =============================================

Cypress.Commands.add('waitForLoading', () => {
  cy.get('[data-cy="loading-spinner"]', { timeout: 10000 }).should('not.exist')
})

Cypress.Commands.add('verifyPageTitle', (expectedTitle: string) => {
  cy.get('title').should('contain', expectedTitle)
})

Cypress.Commands.add('takeScreenshot', (name: string) => {
  cy.screenshot(name, { capture: 'fullPage' })
})

// =============================================
// API HELPERS
// =============================================

Cypress.Commands.add('apiLogin', (email: string, password: string, userType: 'candidate' | 'staff') => {
  const endpoint = userType === 'candidate' ? '/api/auth/candidates-login' : '/api/auth/staff-login'

  cy.request('POST', `${Cypress.env('API_BASE_URL')}${endpoint}`, {
    email,
    password
  }).then((response) => {
    expect(response.status).to.eq(200)
    expect(response.body).to.have.property('token')

    // Guardar token para futuras requests
    cy.window().then((win) => {
      win.localStorage.setItem('auth_token', response.body.token)
    })
  })
})

Cypress.Commands.add('apiRequest', (method: string, endpoint: string, body?: any) => {
  cy.window().then((win) => {
    const token = win.localStorage.getItem('auth_token')

    const config: any = {
      method,
      url: `${Cypress.env('API_BASE_URL')}${endpoint}`,
      headers: {
        'Authorization': token ? `Bearer ${token}` : undefined,
        'Content-Type': 'application/json'
      }
    }

    if (body) {
      config.body = body
    }

    cy.request(config)
  })
})

// =============================================
// VALIDACIONES DE BASE DE DATOS
// =============================================

Cypress.Commands.add('verifyDatabaseState', (table: string, conditions: any) => {
  // Helper para verificar estado de BD después de operaciones
  cy.task('queryDatabase', {
    query: `SELECT * FROM ${table} WHERE ${Object.keys(conditions).map(key => `${key} = ?`).join(' AND ')}`,
    params: Object.values(conditions)
  }).then((results: any) => {
    expect(results).to.have.length.greaterThan(0)
  })
})

// =============================================
// MOCKS PARA TESTS ESPECÍFICOS
// =============================================

Cypress.Commands.add('mockAIResponse', (responseData: any) => {
  cy.intercept('POST', '**/api/ai/*', {
    statusCode: 200,
    body: responseData
  }).as('aiResponse')
})

Cypress.Commands.add('mockEmailService', () => {
  cy.intercept('POST', '**/api/notifications/email', {
    statusCode: 200,
    body: { success: true, messageId: 'mock_message_id' }
  }).as('emailSent')
})

// Declarar tipos para TypeScript
declare global {
  namespace Cypress {
    interface Chainable {
      loginAsCandidate(email: string, password: string): Chainable<void>
      loginAsStaff(email: string, password: string): Chainable<void>
      logout(): Chainable<void>
      uploadCV(cvFileName: string): Chainable<void>
      validateCVData(expectedData: any): Chainable<void>
      searchJobs(query: string): Chainable<void>
      filterJobs(filters: any): Chainable<void>
      applyToJob(jobId: string): Chainable<void>
      verifyCandidateDashboard(): Chainable<void>
      verifyHRDashboard(): Chainable<void>
      verifyRecruiterDashboard(): Chainable<void>
      changeApplicationStatus(applicationId: string, newStatus: string): Chainable<void>
      scheduleInterview(applicationId: string, interviewData: any): Chainable<void>
      interactWithChatbot(conversation: any[]): Chainable<void>
      verifyNotification(notificationData: any): Chainable<void>
      clearNotifications(): Chainable<void>
      waitForLoading(): Chainable<void>
      verifyPageTitle(expectedTitle: string): Chainable<void>
      takeScreenshot(name: string): Chainable<void>
      apiLogin(email: string, password: string, userType: 'candidate' | 'staff'): Chainable<void>
      apiRequest(method: string, endpoint: string, body?: any): Chainable<void>
      verifyDatabaseState(table: string, conditions: any): Chainable<void>
      mockAIResponse(responseData: any): Chainable<void>
      mockEmailService(): Chainable<void>
    }
  }
}

export { }
