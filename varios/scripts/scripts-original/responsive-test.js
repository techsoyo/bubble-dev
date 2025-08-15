#!/usr/bin/env node

/**
 * Script de testing automatizado de responsividad
 * Genera capturas de pantalla en diferentes viewports y valida el comportamiento responsive
 */

const puppeteer = require('puppeteer');
const fs = require('fs').promises;
const path = require('path');

const BREAKPOINTS = {
  mobile: { width: 320, height: 568, name: 'Mobile' },
  mobileLarge: { width: 425, height: 768, name: 'Mobile Large' },
  tablet: { width: 768, height: 1024, name: 'Tablet' },
  laptop: { width: 1024, height: 768, name: 'Laptop' },
  desktop: { width: 1440, height: 900, name: 'Desktop' },
  wide: { width: 1920, height: 1080, name: 'Wide Screen' }
};

const PAGES_TO_TEST = [
  { url: '/', name: 'home' },
  { url: '/jobs', name: 'jobs' },
  { url: '/auth/login', name: 'login' },
  { url: '/auth/register', name: 'register' }
];

const BASE_URL = 'http://localhost:3000';
const SCREENSHOTS_DIR = './test-results/screenshots/responsive';

class ResponsiveTest {
  constructor() {
    this.browser = null;
    this.results = {
      passed: 0,
      failed: 0,
      issues: []
    };
  }

  async init() {
    console.log('🚀 Iniciando tests de responsividad...');

    // Crear directorio de screenshots
    await fs.mkdir(SCREENSHOTS_DIR, { recursive: true });

    // Lanzar browser
    this.browser = await puppeteer.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
  }

  async testPage(pageConfig, breakpoint) {
    const page = await this.browser.newPage();

    try {
      // Configurar viewport
      await page.setViewport({
        width: breakpoint.width,
        height: breakpoint.height,
        deviceScaleFactor: 1
      });

      // Navegar a la página
      console.log(`📱 Testing ${pageConfig.name} en ${breakpoint.name} (${breakpoint.width}x${breakpoint.height})`);

      await page.goto(`${BASE_URL}${pageConfig.url}`, {
        waitUntil: 'networkidle0',
        timeout: 10000
      });

      // Esperar un momento para que las transiciones CSS terminen
      await page.waitForTimeout(1000);

      // Verificar que no hay scroll horizontal
      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);

      if (scrollWidth > clientWidth + 1) {
        this.results.issues.push({
          page: pageConfig.name,
          breakpoint: breakpoint.name,
          issue: 'Scroll horizontal detectado',
          scrollWidth,
          clientWidth
        });
        this.results.failed++;
      } else {
        this.results.passed++;
      }

      // Verificar que el contenido principal es visible
      const mainContentVisible = await page.evaluate(() => {
        const selectors = ['main', '[role="main"]', 'section', '.container'];
        return selectors.some(selector => {
          const element = document.querySelector(selector);
          if (!element) return false;
          const rect = element.getBoundingClientRect();
          return rect.width > 0 && rect.height > 0;
        });
      });

      if (!mainContentVisible) {
        this.results.issues.push({
          page: pageConfig.name,
          breakpoint: breakpoint.name,
          issue: 'Contenido principal no visible'
        });
        this.results.failed++;
      } else {
        this.results.passed++;
      }

      // Verificar elementos específicos según la página
      if (pageConfig.name === 'home') {
        const heroVisible = await page.evaluate(() => {
          const hero = document.querySelector('section');
          if (!hero) return false;
          const rect = hero.getBoundingClientRect();
          return rect.width > 0 && rect.height > 0;
        });

        if (!heroVisible) {
          this.results.issues.push({
            page: pageConfig.name,
            breakpoint: breakpoint.name,
            issue: 'Sección hero no visible'
          });
          this.results.failed++;
        } else {
          this.results.passed++;
        }
      }

      // Capturar screenshot
      const screenshotPath = path.join(
        SCREENSHOTS_DIR,
        `${pageConfig.name}-${breakpoint.name.toLowerCase().replace(' ', '_')}.png`
      );

      await page.screenshot({
        path: screenshotPath,
        fullPage: true
      });

      // Verificar errores de JavaScript
      const jsErrors = [];
      page.on('console', msg => {
        if (msg.type() === 'error') {
          jsErrors.push(msg.text());
        }
      });

      if (jsErrors.length > 0) {
        this.results.issues.push({
          page: pageConfig.name,
          breakpoint: breakpoint.name,
          issue: 'Errores de JavaScript detectados',
          errors: jsErrors
        });
        this.results.failed++;
      }

    } catch (error) {
      this.results.issues.push({
        page: pageConfig.name,
        breakpoint: breakpoint.name,
        issue: 'Error durante el test',
        error: error.message
      });
      this.results.failed++;
    } finally {
      await page.close();
    }
  }

  async runAllTests() {
    await this.init();

    try {
      for (const pageConfig of PAGES_TO_TEST) {
        for (const [breakpointKey, breakpoint] of Object.entries(BREAKPOINTS)) {
          await this.testPage(pageConfig, breakpoint);
        }
      }
    } finally {
      await this.browser.close();
    }

    return this.generateReport();
  }

  generateReport() {
    const report = {
      timestamp: new Date().toISOString(),
      summary: {
        totalTests: this.results.passed + this.results.failed,
        passed: this.results.passed,
        failed: this.results.failed,
        successRate: `${((this.results.passed / (this.results.passed + this.results.failed)) * 100).toFixed(2)}%`
      },
      issues: this.results.issues,
      screenshotsPath: SCREENSHOTS_DIR
    };

    console.log('\n📊 REPORTE DE RESPONSIVIDAD');
    console.log('='.repeat(50));
    console.log(`Total de tests: ${report.summary.totalTests}`);
    console.log(`✅ Pasaron: ${report.summary.passed}`);
    console.log(`❌ Fallaron: ${report.summary.failed}`);
    console.log(`📈 Tasa de éxito: ${report.summary.successRate}`);

    if (this.results.issues.length > 0) {
      console.log('\n🚨 PROBLEMAS ENCONTRADOS:');
      this.results.issues.forEach((issue, index) => {
        console.log(`${index + 1}. ${issue.page} - ${issue.breakpoint}: ${issue.issue}`);
      });
    }

    console.log(`\n📸 Screenshots guardados en: ${SCREENSHOTS_DIR}`);

    return report;
  }
}

// Ejecutar el test si se llama directamente
if (require.main === module) {
  const test = new ResponsiveTest();
  test.runAllTests()
    .then(report => {
      // Guardar reporte en JSON
      fs.writeFile(
        path.join(SCREENSHOTS_DIR, 'report.json'),
        JSON.stringify(report, null, 2)
      );

      console.log('\n✨ Test completado!');
      process.exit(report.summary.failed > 0 ? 1 : 0);
    })
    .catch(error => {
      console.error('❌ Error durante el test:', error);
      process.exit(1);
    });
}

module.exports = ResponsiveTest;
