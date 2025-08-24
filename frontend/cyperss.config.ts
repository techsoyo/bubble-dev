import { defineConfig } from "cypress";

export default defineConfig({
  e2e: {
    baseUrl: "http://localhost:3002", // ajusta si tu dev corre en otro puerto
    specPattern: "cypress/e2e/**/*.cy.{ts,tsx}",
    supportFile: "cypress/support/e2e.ts",
    video: false,
    viewportWidth: 1280,
    viewportHeight: 800,
  },
});
