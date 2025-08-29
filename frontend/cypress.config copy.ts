/// <reference types="node" />
import { defineConfig } from "cypress";

// ⚠️  CONFIGURACIÓN SEGURA DE CYPRESS ⚠️
// NO usar credenciales reales en archivos versionados
// Para desarrollo local, crear archivo .env-cypress.local (ignorado por git)

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

    // Útil cuando el front (3002) y el back (8000) están en distintos orígenes
    chromeWebSecurity: false,

    // Timeouts razonables para SPA + API
    defaultCommandTimeout: 10000,
    requestTimeout: 10000,
    pageLoadTimeout: 20000,
    responseTimeout: 15000,

    env: {
      // API base - usar variable de entorno segura
      apiBaseUrl: process.env.CYPRESS_API_BASEURL || "http://localhost:8000/api",

      // ⚠️  CREDENCIALES DE PRUEBA - SOLO PARA DESARROLLO ⚠️
      // Estas credenciales deben ser de usuarios de TEST exclusivamente
      // Crear usuarios de test en la base de datos de desarrollo

      // Admin credentials (from bubbeTalents_DB.sql)
      adminEmail: process.env.CYPRESS_ADMIN_EMAIL || "copiamedion@gmail.com",
      adminPassword: process.env.CYPRESS_ADMIN_PASSWORD || "admin12345",

      // Recruiter credentials
      recruiterEmail: process.env.CYPRESS_RECRUITER_EMAIL || "recruiter.test@hotmail.com",
      recruiterPassword: process.env.CYPRESS_RECRUITER_PASSWORD || "recruiter12345",

      // Candidate credentials (from database)
      candidateEmail: process.env.CYPRESS_CANDIDATE_EMAIL || "candidato.test2@gmail.com",
      candidatePassword: process.env.CYPRESS_CANDIDATE_PASSWORD || "candi12345",

      // Configuración de tokens y CSRF
      tokenStorageKey: process.env.CYPRESS_TOKEN_STORAGE_KEY || "auth_token",
      csrfCookieName: process.env.CYPRESS_CSRF_COOKIE || "XSRF-TOKEN",
      csrfHeaderName: process.env.CYPRESS_CSRF_HEADER || "X-CSRF-Token",

      // Rutas de autenticación
      loginPath: process.env.CYPRESS_LOGIN_PATH || "/auth/login.php",
      logoutPath: process.env.CYPRESS_LOGOUT_PATH || "/auth/logout.php",
      healthPath: process.env.CYPRESS_HEALTH_PATH || "/health",

      // Database configuration for direct queries (development only)
      dbHost: process.env.CYPRESS_DB_HOST || "localhost",
      dbName: process.env.CYPRESS_DB_NAME || "bubbeTalents_DB",
      dbUser: process.env.CYPRESS_DB_USER || "root",
      dbPassword: process.env.CYPRESS_DB_PASSWORD || "",

      // Test data IDs (from bubbeTalents_DB.sql)
      testJobId: 1, // "Creative Copywriter"
      testCandidateId: 1, // "Lucía Álvarez"
      testApplicationId: 1,

      // CV test files
      testCvFiles: [
        "Curriculum Vitae - daniel-alvarez-DevWeb.pdf",
        "Curriculum Vitae - javier-rodriguez-mkt.pdf",
        "Curriculum Vitae - laura-gomez-mkt.pdf",
        "Curriculum Vitae - pablo-torres-mkt.pdf"
      ],

      // ⚠️  ADVERTENCIA DE SEGURIDAD ⚠️
      // Si usas credenciales reales, crea un archivo .env-cypress.local
      // Este archivo NO debe ser versionado (está en .gitignore)
    },

    setupNodeEvents(on, config) {
      // Configuración segura para desarrollo local
      on("before:browser:launch", (browser, launchOptions) => {
        if (browser.name === "chrome") {
          // ⚠️  SOLO PARA DESARROLLO LOCAL ⚠️
          // Deshabilitar restricciones de CORS para testing
          launchOptions.args.push("--disable-site-isolation-trials");
          launchOptions.args.push("--disable-web-security");
          launchOptions.args.push("--enable-logging");
          launchOptions.args.push("--v=1");
        }
        return launchOptions;
      });

      // Task para queries directas a BD (solo desarrollo)
      on('task', {
        queryDatabase({ query, params = [] }) {
          // ⚠️  SOLO PARA DESARROLLO LOCAL ⚠️
          // En producción, usar API endpoints en su lugar
          const mysql = require('mysql2/promise');

          return (async () => {
            let connection;
            try {
              connection = await mysql.createConnection({
                host: config.env.dbHost,
                user: config.env.dbUser,
                password: config.env.dbPassword,
                database: config.env.dbName
              });

              const [rows] = await connection.execute(query, params);
              return rows;
            } catch (error) {
              console.error('Database query error:', error);
              throw error;
            } finally {
              if (connection) {
                await connection.end();
              }
            }
          })();
        },

        // Task para seed de datos de test
        seedTestData() {
          const { exec } = require('child_process');
          const path = require('path');

          return new Promise((resolve, reject) => {
            const seedPath = path.join(process.cwd(), '..', '..', 'cypress_e2e_seed_final_real.sql');
            const command = `mysql -u${config.env.dbUser} -p${config.env.dbPassword} ${config.env.dbName} < "${seedPath}"`;

            exec(command, (error, stdout, stderr) => {
              if (error) {
                console.error('Seed error:', error);
                reject(error);
              } else {
                console.log('Seed completed successfully');
                resolve(stdout);
              }
            });
          });
        }
      });

      return config;
    },
  },

  // Configuración específica para componentes (si se usan)
  component: {
    devServer: {
      framework: "react",
      bundler: "vite",
    },
  },
});