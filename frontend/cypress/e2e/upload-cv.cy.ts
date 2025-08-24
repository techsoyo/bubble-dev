describe("Upload CV", () => {
  it("sube un CV y muestra confirmación", () => {
    // Stub de la respuesta del backend
    cy.intercept("POST", "**/api/analyze_cv.php", {
      statusCode: 200,
      body: { success: true, data: { id: "cand-123" }, message: "CV subido exitosamente" }
    }).as("uploadCV");

    cy.visit("/ruta/que/tiene/upload"); // ajusta

    // Simula selección de archivo
    cy.getByCy("cv-file").selectFile(
      { contents: Cypress.Buffer.from("fake-pdf-content"), fileName: "cv.pdf", mimeType: "application/pdf", lastModified: Date.now() },
      { force: true }
    );

    // A veces hay botón separado:
    cy.getByCy("upload-cv-button").click({ force: true });

    cy.wait("@uploadCV").its("response.statusCode").should("eq", 200);
    cy.contains(/CV subido exitosamente|Subida completada/i);
  });
});
