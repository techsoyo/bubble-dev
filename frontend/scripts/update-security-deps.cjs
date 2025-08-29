#!/usr/bin/env node

/**
 * Security Dependencies Update Script
 * Updates security-related dependencies and checks for vulnerabilities
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

class SecurityUpdater {
  constructor() {
    this.packageJsonPath = path.join(process.cwd(), 'package.json');
    this.securityDeps = [
      'dompurify',
      'helmet',
      'express-rate-limit',
      'joi',
      'jsonwebtoken',
      'bcrypt',
      'crypto-js',
      'axios',
      'react',
      'typescript',
      '@typescript-eslint/eslint-plugin',
      '@typescript-eslint/parser'
    ];
  }

  /**
   * Main update function
   */
  async update() {
    console.log('🔒 Updating Security Dependencies...\n');

    try {
      // Check for vulnerabilities first
      console.log('1. Checking for known vulnerabilities...');
      this.checkVulnerabilities();

      // Update security dependencies
      console.log('2. Updating security-related packages...');
      this.updateSecurityPackages();

      // Run security audit
      console.log('3. Running security audit...');
      this.runSecurityAudit();

      // Update lockfile
      console.log('4. Updating lockfile...');
      this.updateLockfile();

      console.log('✅ Security dependencies update completed!\n');
      console.log('📋 Recommendations:');
      console.log('   • Review updated packages for breaking changes');
      console.log('   • Test authentication flows after updates');
      console.log('   • Run full test suite to ensure compatibility');
      console.log('   • Update production deployment with new versions');

    } catch (error) {
      console.error('❌ Security update failed:', error.message);
      process.exit(1);
    }
  }

  /**
   * Check for known vulnerabilities
   */
  checkVulnerabilities() {
    try {
      console.log('   Running npm audit...');
      execSync('npm audit --audit-level=moderate', { stdio: 'inherit' });
    } catch (error) {
      console.log('   ⚠️  Vulnerabilities found. Attempting to fix...');
      try {
        execSync('npm audit fix', { stdio: 'inherit' });
      } catch (fixError) {
        console.log('   ❌ Some vulnerabilities could not be auto-fixed');
        console.log('   🔍 Run "npm audit" manually to review issues');
      }
    }
  }

  /**
   * Update security-related packages
   */
  updateSecurityPackages() {
    const packageJson = JSON.parse(fs.readFileSync(this.packageJsonPath, 'utf8'));
    const deps = { ...packageJson.dependencies, ...packageJson.devDependencies };

    const securityPackagesToUpdate = [];

    for (const dep of this.securityDeps) {
      if (deps[dep]) {
        securityPackagesToUpdate.push(dep);
      }
    }

    if (securityPackagesToUpdate.length > 0) {
      console.log(`   Updating ${securityPackagesToUpdate.length} security packages...`);
      const updateCommand = `pnpm update ${securityPackagesToUpdate.join(' ')}`;
      execSync(updateCommand, { stdio: 'inherit' });
    } else {
      console.log('   No security packages found to update');
    }
  }

  /**
   * Run security audit
   */
  runSecurityAudit() {
    try {
      console.log('   Running pnpm audit...');
      execSync('pnpm audit', { stdio: 'inherit' });
    } catch (error) {
      console.log('   ⚠️  Security audit completed with warnings/issues');
    }
  }

  /**
   * Update lockfile
   */
  updateLockfile() {
    console.log('   Updating pnpm-lock.yaml...');
    execSync('pnpm install', { stdio: 'inherit' });
  }

  /**
   * Show outdated security packages
   */
  showOutdated() {
    console.log('🔍 Checking for outdated security packages...\n');

    try {
      const result = execSync('pnpm outdated', { encoding: 'utf8' });
      const lines = result.split('\n');

      console.log('📦 Outdated Security Packages:');
      let foundOutdated = false;

      for (const line of lines.slice(1)) { // Skip header
        if (line.trim()) {
          const parts = line.split(/\s+/);
          if (parts.length >= 2 && this.securityDeps.includes(parts[0])) {
            console.log(`   📦 ${parts[0]}: ${parts[1]} → ${parts[2] || 'latest'}`);
            foundOutdated = true;
          }
        }
      }

      if (!foundOutdated) {
        console.log('   ✅ All security packages are up to date!');
      }

    } catch (error) {
      console.log('   ❌ Could not check outdated packages');
    }
  }
}

// CLI interface
const command = process.argv[2];

if (command === 'outdated') {
  new SecurityUpdater().showOutdated();
} else if (command === 'update' || !command) {
  new SecurityUpdater().update();
} else {
  console.log('Usage:');
  console.log('  node scripts/update-security-deps.js          # Update security dependencies');
  console.log('  node scripts/update-security-deps.js outdated # Show outdated security packages');
}

module.exports = SecurityUpdater;
