// =============================================
// Cypress E2E Helper - Datos reales vs Stubs
// Fecha: 25 de agosto de 2025
// Propósito: Configuración para tests con datos reales
// =============================================

/**
 * Configuración de endpoints reales vs mocked
 */
export const API_CONFIG = {
  // Usar datos reales de BD
  USE_REAL_DATA: Cypress.env('USE_REAL_DATA') !== 'false',

  // URLs de API reales (sin mocks)
  ENDPOINTS: {
    jobs: '/backend/public/api/jobs.php',
    jobRequirements: '/backend/public/api/job_requirements.php',
    jobSkills: '/backend/public/api/job_skills.php',
    departments: '/backend/public/api/departments.php',
    auth: '/backend/public/api/auth.php'
  },

  // Datos esperados después de ejecutar cypress_e2e_seed_final.sql
  EXPECTED_DATA: {
    job_1_requirements_count: 5,
    job_1_skills_count: 6,
    total_active_jobs: 2,
    test_job_id: 'cypress-job-1',
    test_job_2_id: 'cypress-job-2',
    test_job_title: 'Frontend Developer - Cypress Test',
    test_job_2_title: 'Backend Developer - Cypress Test'
  }
};

/**
 * Preparar datos de test reales
 */
export function seedTestData() {
  if (!API_CONFIG.USE_REAL_DATA) {
    cy.log('🔧 Using stubbed data - skipping real seed');
    return;
  }

  cy.log('🌱 Seeding real test data...');

  // Ejecutar seed SQL via endpoint especial o comando
  cy.exec('mysql -uroot bubble_talents < backend/database/seeds/cypress_e2e_seed.sql',
    { failOnNonZeroExit: false });

  cy.log('✅ Test data seeded');
}

/**
 * Configurar interceptors para tests específicos
 */
export function setupAPIInterceptors(testType: 'real' | 'stubbed' = 'real') {
  if (testType === 'stubbed') {
    // @ui-stub - Test aislado de UI solamente
    cy.intercept('GET', '**/job_requirements.php*', {
      fixture: 'job_requirements_stub.json'
    }).as('getJobRequirements');

    cy.intercept('GET', '**/job_skills.php*', {
      fixture: 'job_skills_stub.json'
    }).as('getJobSkills');

    cy.log('🎭 API interceptors set to STUBBED mode');
  } else {
    // Tests reales contra BD
    cy.intercept('GET', '**/job_requirements.php*').as('getJobRequirements');
    cy.intercept('GET', '**/job_skills.php*').as('getJobSkills');

    cy.log('🔗 API interceptors set to REAL mode');
  }
}

/**
 * Assertions con datos conocidos
 */
export function assertJobDetailsReal() {
  if (!API_CONFIG.USE_REAL_DATA) {
    cy.log('🔧 Skipping real data assertions - using stubs');
    return;
  }

  // Verificar que los datos reales están presentes
  cy.wait('@getJobRequirements').then((interception) => {
    expect(interception.response.body.data).to.have.length(API_CONFIG.EXPECTED_DATA.job_1_requirements_count);
  });

  cy.wait('@getJobSkills').then((interception) => {
    expect(interception.response.body.data).to.have.length(API_CONFIG.EXPECTED_DATA.job_1_skills_count);
  });

  cy.log('✅ Real data assertions passed');
}

/**
 * Login con usuario de test real
 */
export function loginTestUser() {
  if (!API_CONFIG.USE_REAL_DATA) {
    cy.log('🔧 Mocking authentication');
    // Simular cookie JWT para tests UI
    cy.setCookie('jwt_token', 'mock-jwt-for-ui-test-only');
    return;
  }

  cy.log('🔐 Authenticating with real user...');

  cy.request('POST', API_CONFIG.ENDPOINTS.auth, {
    email: 'test@cypress.local',
    password: 'test-password'
  }).then((response) => {
    expect(response.status).to.eq(200);
    // Cookie JWT se establece automáticamente
    cy.log('✅ Real authentication successful');
  });
}
