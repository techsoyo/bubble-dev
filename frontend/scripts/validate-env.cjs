#!/usr/bin/env node

/**
 * Environment Validation Script
 * Validates environment configuration before build
 */

const fs = require('fs');
const path = require('path');

class EnvironmentValidator {
  constructor() {
    this.errors = [];
    this.warnings = [];
    this.envPath = path.join(process.cwd(), '.env');
    this.envExamplePath = path.join(process.cwd(), '.env.example');
  }

  /**
   * Main validation function
   */
  async validate() {
    console.log('🔍 Validating environment configuration...\n');

    // Check if .env file exists
    if (!fs.existsSync(this.envPath)) {
      this.errors.push('❌ .env file not found. Copy .env.example to .env and configure your environment variables.');
      this.showResults();
      process.exit(1);
    }

    // Load environment variables
    require('dotenv').config({ path: this.envPath });

    // Validate critical variables
    this.validateCriticalVariables();

    // Validate security settings
    this.validateSecuritySettings();

    // Validate API configuration
    this.validateApiConfiguration();

    // Validate production settings
    this.validateProductionSettings();

    // Show results
    this.showResults();

    if (this.errors.length > 0) {
      process.exit(1);
    }
  }

  /**
   * Validate critical environment variables
   */
  validateCriticalVariables() {
    const criticalVars = [
      'VITE_API_BASE_URL',
      'VITE_APP_ENV'
    ];

    criticalVars.forEach(varName => {
      if (!process.env[varName]) {
        this.errors.push(`❌ Missing critical variable: ${varName}`);
      }
    });
  }

  /**
   * Validate security settings
   */
  validateSecuritySettings() {
    const isProduction = process.env.VITE_APP_ENV === 'production';

    // Check HTTPS in production
    if (isProduction && process.env.VITE_API_BASE_URL) {
      if (!process.env.VITE_API_BASE_URL.startsWith('https://')) {
        this.errors.push('❌ Production environment must use HTTPS for VITE_API_BASE_URL');
      }
    }

    // Check debug mode
    if (isProduction && process.env.VITE_DEBUG_MODE === 'true') {
      this.errors.push('❌ Debug mode must be disabled in production');
    }

    // Check test credentials
    if (isProduction && process.env.VITE_SHOW_TEST_CREDENTIALS === 'true') {
      this.errors.push('❌ Test credentials must be hidden in production');
    }

    // Validate file size limits
    if (process.env.VITE_MAX_FILE_SIZE) {
      const maxSize = parseInt(process.env.VITE_MAX_FILE_SIZE);
      if (isProduction && maxSize > 5242880) { // 5MB in production
        this.warnings.push('⚠️  Consider reducing max file size in production (recommended: 5MB)');
      }
    }

    // Validate rate limiting
    if (process.env.VITE_MAX_REQUESTS_PER_MINUTE) {
      const maxRequests = parseInt(process.env.VITE_MAX_REQUESTS_PER_MINUTE);
      if (isProduction && maxRequests > 30) {
        this.warnings.push('⚠️  Consider reducing request rate limit in production (recommended: 30/min)');
      }
    }
  }

  /**
   * Validate API configuration
   */
  validateApiConfiguration() {
    const apiUrl = process.env.VITE_API_BASE_URL;

    if (apiUrl) {
      // Check for localhost in production
      if (process.env.VITE_APP_ENV === 'production' && apiUrl.includes('localhost')) {
        this.errors.push('❌ Cannot use localhost in production environment');
      }

      // Validate URL format
      try {
        new URL(apiUrl);
      } catch {
        this.errors.push('❌ VITE_API_BASE_URL is not a valid URL');
      }
    }
  }

  /**
   * Validate production-specific settings
   */
  validateProductionSettings() {
    const isProduction = process.env.VITE_APP_ENV === 'production';

    if (isProduction) {
      // Check for required production variables
      const productionVars = [
        'VITE_SENTRY_DSN',
        'VITE_GA_TRACKING_ID'
      ];

      productionVars.forEach(varName => {
        if (!process.env[varName]) {
          this.warnings.push(`⚠️  Consider setting ${varName} for production monitoring`);
        }
      });

      // Validate API timeout
      if (process.env.VITE_API_TIMEOUT) {
        const timeout = parseInt(process.env.VITE_API_TIMEOUT);
        if (timeout < 15000) {
          this.warnings.push('⚠️  API timeout might be too short for production (recommended: 15000ms+)');
        }
      }
    }
  }

  /**
   * Show validation results
   */
  showResults() {
    if (this.errors.length > 0) {
      console.log('🚫 Critical Issues Found:');
      this.errors.forEach(error => console.log(`   ${error}`));
      console.log('');
    }

    if (this.warnings.length > 0) {
      console.log('⚠️  Warnings:');
      this.warnings.forEach(warning => console.log(`   ${warning}`));
      console.log('');
    }

    if (this.errors.length === 0 && this.warnings.length === 0) {
      console.log('✅ Environment configuration is valid!\n');
    } else if (this.errors.length === 0) {
      console.log('✅ No critical issues found. Address warnings for optimal security.\n');
    }
  }
}

// Run validation if called directly
if (require.main === module) {
  const validator = new EnvironmentValidator();
  validator.validate().catch(error => {
    console.error('Validation failed:', error);
    process.exit(1);
  });
}

module.exports = EnvironmentValidator;
