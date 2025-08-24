job - detail.cy.tsdescribe("Jobs - Detalle", () => {
  it("carga el detalle de una oferta", () => {
    cy.intercept("GET", "**/api/jobs.php?id=1", {
      statusCode: 200,
      body: { success: true, data: require("../fixtures/job_1.json") }
    }).as("getJob");

    cy.visit("/jobs/1");
    cy.wait("@getJob");

    cy.contains("Frontend Dev");
    cy.contains("Madrid");
  });
});
