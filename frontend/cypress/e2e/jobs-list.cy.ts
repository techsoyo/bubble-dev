describe("Jobs - Listado", () => {
  it("muestra el listado de ofertas y permite navegar al detalle", () => {
    // Stub del endpoint público
    cy.intercept("GET", "**/api/jobs.php*", {
      statusCode: 200,
      body: { success: true, data: require("../fixtures/jobs.json") }
    }).as("getJobs");

    cy.visit("/jobs");        // o la ruta que uses para el listado
    cy.wait("@getJobs");

    cy.getByCy("job-link").should("have.length.at.least", 1);
    cy.contains("Frontend Dev");
    cy.getByCy("job-link").first().click();

    // Verificamos que cambia a /jobs/1 (ancla o navegación)
    cy.url().should("match", /\/jobs\/\d+$/);
  });
});
