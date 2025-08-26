/// <reference types="node" />
import { defineConfig } from "cypress";
import * as dotenv from "dotenv";

dotenv.config({ path: ".env-cypress" });

function fromEnv(name: string, fallback: string): string {
  const v = process.env[name];
  return typeof v === "string" && v.length > 0 ? v : fallback;
}

export default defineConfig({
  video: false,
  e2e: {
    baseUrl: "http://localhost:3002",
    specPattern: "cypress/e2e/**/*.cy.{ts,tsx}",
    supportFile: "cypress/support/e2e.ts",
    viewportWidth: 1280,
    viewportHeight: 800,
    retries: { runMode: 2, openMode: 0 },

    // Útil cuando el front (3002) y el back (8000) están en distintos orígenes
    chromeWebSecurity: false,

    // Timeouts razonables para SPA + API
    defaultCommandTimeout: 10000,
    requestTimeout: 10000,
    pageLoadTimeout: 20000,

    env: {
      // API base (si usas router /api en backend, incluye /api aquí o en las rutas)
      apiBaseUrl: fromEnv("CYPRESS_API_BASEURL", ""),

      // Credenciales de prueba
      adminEmail: fromEnv("CYPRESS_ADMIN_EMAIL", ""),
      adminPassword: fromEnv("CYPRESS_ADMIN_PASSWORD", ""),
      recruiterEmail: fromEnv("CYPRESS_RECRUITER_EMAIL", ""),
      recruiterPassword: fromEnv("CYPRESS_RECRUITER_PASSWORD", ""),
      candidateEmail: fromEnv("CYPRESS_CANDIDATE_EMAIL", ""),
      candidatePassword: fromEnv("CYPRESS_CANDIDATE_PASSWORD", ""),

      // Si tu UI guarda algún flag, lo centralizas aquí (aunque auth va en cookie HttpOnly)
      tokenStorageKey: fromEnv("CYPRESS_TOKEN_STORAGE_KEY", "auth_token"),

      // Soporte CSRF double-submit (alineado con tests de cookies/CSRF)
      csrfCookieName: fromEnv("CYPRESS_CSRF_COOKIE", "XSRF-TOKEN"),
      csrfHeaderName: fromEnv("CYPRESS_CSRF_HEADER", "X-CSRF-Token"),

      // Rutas útiles de backend para helpers/commands
      loginPath: fromEnv("CYPRESS_LOGIN_PATH", "/auth/login.php"),
      logoutPath: fromEnv("CYPRESS_LOGOUT_PATH", "/auth/logout.php"),
      healthPath: fromEnv("CYPRESS_HEALTH_PATH", "/health"),
    },

    setupNodeEvents(on, config) {
      // Permitir cookies HttpOnly en Chrome en local con orígenes distintos
      on("before:browser:launch", (browser, launchOptions) => {
        if (browser.name === "chrome") {
          launchOptions.args.push("--disable-site-isolation-trials");
          launchOptions.args.push("--disable-web-security");
        }
        return launchOptions;
      });
      return config;
    },
  },
});
