/// <reference types="node" />
import { defineConfig } from "cypress";

export default defineConfig({
  video: false,
  screenshotOnRunFailure: true,
  trashAssetsBeforeRuns: true,

  e2e: {
    baseUrl: "http://localhost:3002",
    specPattern: "cypress/e2e/**/*.cy.{ts,tsx}",
    supportFile: "cypress/support/e2e.ts",
    viewportWidth: 1280,
    viewportHeight: 800,
    retries: { runMode: 2, openMode: 0 },

    // Front (3002) y Back (8000) en orígenes distintos
    chromeWebSecurity: false,

    // Timeouts razonables para SPA + API
    defaultCommandTimeout: 10000,
    requestTimeout: 10000,
    pageLoadTimeout: 20000,
    responseTimeout: 15000,

    env: {
      // API base
      apiBaseUrl: process.env.CYPRESS_API_BASEURL || "http://localhost:8000/api",

      // ⚠️ Credenciales SOLO de testing
      adminEmail: process.env.CYPRESS_ADMIN_EMAIL || "copiamedion@gmail.com",
      adminPassword: process.env.CYPRESS_ADMIN_PASSWORD || "admin12345",

      recruiterEmail: process.env.CYPRESS_RECRUITER_EMAIL || "recruiter.test@hotmail.com",
      recruiterPassword: process.env.CYPRESS_RECRUITER_PASSWORD || "recruiter12345",

      candidateEmail: process.env.CYPRESS_CANDIDATE_EMAIL || "candidato.test2@gmail.com",
      candidatePassword: process.env.CYPRESS_CANDIDATE_PASSWORD || "candi12345",

      // CSRF / tokens
      tokenStorageKey: process.env.CYPRESS_TOKEN_STORAGE_KEY || "auth_token",
      csrfCookieName: process.env.CYPRESS_CSRF_COOKIE || "XSRF-TOKEN",
      csrfHeaderName: process.env.CYPRESS_CSRF_HEADER || "X-CSRF-Token",

      // Rutas auth
      loginPath: process.env.CYPRESS_LOGIN_PATH || "/auth/login.php",
      logoutPath: process.env.CYPRESS_LOGOUT_PATH || "/auth/logout.php",
      healthPath: process.env.CYPRESS_HEALTH_PATH || "/health",

      // BD (solo desarrollo)
      dbHost: process.env.CYPRESS_DB_HOST || "localhost",
      dbName: process.env.CYPRESS_DB_NAME || "bubbeTalents_DB",
      dbUser: process.env.CYPRESS_DB_USER || "root",
      dbPassword: process.env.CYPRESS_DB_PASSWORD || "",

      // IDs de prueba
      testJobId: 1,
      testCandidateId: 1,
      testApplicationId: 1,

      // CVs de prueba
      testCvFiles: [
        "Curriculum Vitae - daniel-alvarez-DevWeb.pdf",
        "Curriculum Vitae - javier-rodriguez-mkt.pdf",
        "Curriculum Vitae - laura-gomez-mkt.pdf",
        "Curriculum Vitae - pablo-torres-mkt.pdf"
      ],
    },

    setupNodeEvents(on, config) {
      // Lanzador del navegador
      on("before:browser:launch", (browser, launchOptions) => {
        if (browser.name === "chrome") {
          launchOptions.args.push("--disable-site-isolation-trials");
          launchOptions.args.push("--disable-web-security");
          launchOptions.args.push("--enable-logging");
          launchOptions.args.push("--v=1");
        }
        return launchOptions;
      });

      // Tasks (ESM: usar import dinámico, nada de require)
      on("task", {
        async queryDatabase({ query, params = [] }) {
          // ⚠️ SOLO DESARROLLO. En prod, usar endpoints.
          const mysqlModule = await import("mysql2/promise");
          // compat: algunos bundlers exponen default
          const mysql = (mysqlModule as any).default ?? mysqlModule;

          let connection: any;
          try {
            connection = await mysql.createConnection({
              host: config.env.dbHost,
              user: config.env.dbUser,
              password: config.env.dbPassword,
              database: config.env.dbName,
            });
            const [rows] = await connection.execute(query, params);
            return rows;
          } finally {
            if (connection) await connection.end();
          }
        },

        async seedTestData() {
          const cp = await import("node:child_process");
          const path = await import("node:path");

          return await new Promise((resolve, reject) => {
            const seedPath = path.join(process.cwd(), "..", "..", "cypress_e2e_seed_final_real.sql");
            const command = `mysql -u${config.env.dbUser} -p${config.env.dbPassword} ${config.env.dbName} < "${seedPath}"`;

            cp.exec(command, (error: any, stdout: any) => {
              if (error) return reject(error);
              resolve(stdout);
            });
          });
        },
      });

      return config;
    },
  },

  // Testing de componentes (opcional)
  component: {
    devServer: {
      framework: "react",
      bundler: "vite",
    },
  },
});
