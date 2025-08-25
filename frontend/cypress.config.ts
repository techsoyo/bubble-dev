import { defineConfig } from "cypress";
import * as dotenv from "dotenv";

dotenv.config({ path: ".env-cypress" });

function fromEnv(name: string, fallback: string): string {
  const v = process.env[name];
  return (typeof v === "string" && v.length > 0) ? v : fallback;
}

export default defineConfig({
  e2e: {
    baseUrl: "http://localhost:3002",
    specPattern: "cypress/e2e/**/*.cy.{ts,tsx}",
    supportFile: "cypress/support/e2e.ts",
    viewportWidth: 1280,
    viewportHeight: 800,
    video: false,
    retries: { runMode: 2, openMode: 0 },

    env: {
      apiBaseUrl: fromEnv("CYPRESS_API_BASEURL", ""),
      adminEmail: fromEnv("CYPRESS_ADMIN_EMAIL", ""),
      adminPassword: fromEnv("CYPRESS_ADMIN_PASSWORD", ""),
      recruiterEmail: fromEnv("CYPRESS_RECRUITER_EMAIL", ""),
      recruiterPassword: fromEnv("CYPRESS_RECRUITER_PASSWORD", ""),
      candidateEmail: fromEnv("CYPRESS_CANDIDATE_EMAIL", ""),
      candidatePassword: fromEnv("CYPRESS_CANDIDATE_PASSWORD", ""),
      tokenStorageKey: fromEnv("CYPRESS_TOKEN_STORAGE_KEY", "auth_token"),
    }
  }
});