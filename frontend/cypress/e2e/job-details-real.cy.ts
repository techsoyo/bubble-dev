import { seedTestData, setupAPIInterceptors, assertJobDetailsReal, API_CONFIG } from '../support/e2e-helpers';

describe('Job Details - Real Data Integration', () => {
  beforeEach(() => {
    // Preparar datos reales para tests
    seedTestData();

    // Configurar interceptors para datos reales
    setupAPIInterceptors('real');

    // Navegar a la página
    cy.visit(`/jobs/${API_CONFIG.EXPECTED_DATA.test_job_id}`);
  });

  it('should display job requirements from database', () => {
    // Esperar que se carguen los datos reales
    cy.wait('@getJobRequirements');

    // Verificar que se muestran los requirements correctos
    cy.get('[data-testid="job-requirements"]')
      .should('exist')
      .within(() => {
        // Verificar datos específicos que sabemos que existen
        cy.contains('Experiencia mínima 3 años').should('be.visible');
        cy.contains('Dominio de JavaScript').should('be.visible');
        cy.contains('Conocimiento en frameworks modernos').should('be.visible');
      });

    // Verificar cantidad exacta
    cy.get('[data-testid="requirement-item"]')
      .should('have.length', API_CONFIG.EXPECTED_DATA.job_1_requirements_count);
  });

  it('should display job skills from database', () => {
    // Esperar que se carguen los datos reales
    cy.wait('@getJobSkills');

    // Verificar skills específicos
    cy.get('[data-testid="job-skills"]')
      .should('exist')
      .within(() => {
        cy.contains('JavaScript').should('be.visible');
        cy.contains('React').should('be.visible');
        cy.contains('CSS3').should('be.visible');
        cy.contains('Git').should('be.visible');
      });

    // Verificar cantidad exacta
    cy.get('[data-testid="skill-item"]')
      .should('have.length', API_CONFIG.EXPECTED_DATA.job_1_skills_count);
  });

  it('should handle POST requests with CSRF protection', () => {
    // Login real para obtener cookies necesarias
    cy.request('POST', '/backend/public/api/auth/login', {
      email: 'test@cypress.local',
      password: 'test-password'
    });

    // Crear nuevo requirement
    cy.request({
      method: 'POST',
      url: '/backend/public/api/job_requirements.php',
      body: {
        job_id: API_CONFIG.EXPECTED_DATA.test_job_id,
        requirement: 'Cypress Test Requirement'
      }
    }).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.ok).to.be.true;
    });
  });
});

// Test UI aislado (usando stubs)
describe('Job Details - UI Only (@ui-stub)', () => {
  beforeEach(() => {
    // Este test usa datos stubbed para aislar la UI
    setupAPIInterceptors('stubbed');

    cy.visit(`/jobs/1`);
  });

  it('should render loading states correctly', () => {
    // Test de loading, errores, etc. sin depender de BD
    cy.get('[data-testid="loading-spinner"]').should('be.visible');

    cy.wait('@getJobRequirements');

    cy.get('[data-testid="loading-spinner"]').should('not.exist');
    cy.get('[data-testid="job-requirements"]').should('be.visible');
  });
});
