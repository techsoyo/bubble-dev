describe("Calendar integrations", () => {
  it("conecta y desconecta calendario", () => {
    cy.intercept("GET", "**/api/calendar/integrations?*", {
      statusCode: 200,
      body: { success: true, data: [] }
    }).as("getIntegrations");

    cy.intercept("POST", "**/api/calendar/connect", { statusCode: 200, body: { success: true } }).as("connect");
    cy.intercept("POST", "**/api/calendar/disconnect", { statusCode: 200, body: { success: true } }).as("disconnect");

    cy.visit("/ruta/integ-calendar"); // ajusta
    cy.wait("@getIntegrations");

    cy.getByCy("connect-google").click();
    cy.wait("@connect").its("response.statusCode").should("eq", 200);

    cy.getByCy("disconnect-google").click();
    cy.wait("@disconnect").its("response.statusCode").should("eq", 200);
  });
});
