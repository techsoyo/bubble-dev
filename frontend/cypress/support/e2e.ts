import "@testing-library/cypress/add-commands";

// selector útil
Cypress.Commands.add("getByCy", (id: string) => cy.get(`[data-cy="${id}"]`));
declare global {
  namespace Cypress {
    interface Chainable {
      getByCy(id: string): Chainable<JQuery<HTMLElement>>;
    }
  }
}
