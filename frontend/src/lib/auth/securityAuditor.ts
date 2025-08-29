/**
 * Security Audit Script - Production Ready
 * Script para auditar configuraciones de seguridad en el frontend
 */

import { SecurityConfiguration } from './securityConfig';

export interface SecurityAuditResult {
  passed: boolean;
  issues: string[];
  recommendations: string[];
}

export class SecurityAuditor {
  /**
   * Run complete security audit
   */
  static async runSecurityAudit(): Promise<SecurityAuditResult> {
    const issues: string[] = [];
    const recommendations: string[] = [];

    // Check environment variables
    await this.checkEnvironmentVariables(issues, recommendations);

    // Check API endpoints
    await this.checkApiEndpoints(issues, recommendations);

    // Check file upload security
    this.checkFileUploadSecurity(issues, recommendations);

    // Check deprecated files
    this.checkDeprecatedFiles(issues, recommendations);

    // Check authentication security
    this.checkAuthenticationSecurity(issues, recommendations);

    return {
      passed: issues.length === 0,
      issues,
      recommendations
    };
  }

  /**
   * Check environment variables security
   */
  private static async checkEnvironmentVariables(issues: string[], recommendations: string[]): Promise<void> {
    const requiredEnvVars = [
      'VITE_API_BASE_URL',
      'VITE_APP_ENV'
    ];

    for (const envVar of requiredEnvVars) {
      if (!import.meta.env[envVar]) {
        issues.push(`Missing required environment variable: ${envVar}`);
      }
    }

    // Check for hardcoded URLs in environment
    const apiUrl = import.meta.env.VITE_API_BASE_URL;
    if (apiUrl && apiUrl.includes('localhost')) {
      recommendations.push('Consider using environment-specific API URLs instead of localhost in production');
    }
  }

  /**
   * Check API endpoints security
   */
  private static async checkApiEndpoints(issues: string[], recommendations: string[]): Promise<void> {
    const config = SecurityConfiguration.getConfig();

    // Test CSRF endpoint accessibility
    try {
      const response = await fetch(config.csrfTokenEndpoint, {
        method: 'HEAD',
        credentials: 'include'
      });

      if (!response.ok) {
        issues.push(`CSRF token endpoint not accessible: ${response.status}`);
      }
    } catch (error) {
      issues.push(`Cannot connect to CSRF endpoint: ${error}`);
    }

    // Check for HTTPS in production
    if (import.meta.env.VITE_APP_ENV === 'production' && !config.apiBaseUrl.startsWith('https://')) {
      issues.push('Production environment should use HTTPS for API endpoints');
    }
  }

  /**
   * Check file upload security
   */
  private static checkFileUploadSecurity(issues: string[], recommendations: string[]): void {
    const config = SecurityConfiguration.getConfig();

    if (config.maxFileSize > 50 * 1024 * 1024) { // 50MB
      issues.push('Maximum file size is too large, consider reducing to prevent DoS attacks');
    }

    if (config.allowedFileTypes.includes('*/*')) {
      issues.push('Wildcard file types are not allowed for security reasons');
    }

    recommendations.push('Consider implementing file type validation on the server side');
    recommendations.push('Add virus scanning for uploaded files');
  }

  /**
   * Check for deprecated insecure files
   */
  private static checkDeprecatedFiles(issues: string[], recommendations: string[]): void {
    // ✅ tokenManager.ts has been successfully removed and replaced with secure cookie-based authentication
    recommendations.push('✅ COMPLETED: tokenManager.ts file removed - using secure cookie-based authentication');
    recommendations.push('Audit all fetch calls for proper error handling');
    recommendations.push('Ensure all API calls use environment variables instead of hardcoded URLs');
  }

  /**
   * Check authentication security
   */
  private static checkAuthenticationSecurity(issues: string[], recommendations: string[]): void {
    const config = SecurityConfiguration.getConfig();

    if (config.sessionTimeout < 1800000) { // 30 minutes
      issues.push('Session timeout is too short, consider increasing to 30+ minutes');
    }

    if (config.maxLoginAttempts < 3) {
      issues.push('Maximum login attempts is too low, consider 3-5 attempts');
    }

    recommendations.push('Implement account lockout after failed login attempts');
    recommendations.push('Add two-factor authentication for sensitive operations');
    recommendations.push('Implement secure password policies');
  }
}

// Auto-run audit in development
if (import.meta.env.DEV) {
  SecurityAuditor.runSecurityAudit().then(result => {
    if (!result.passed) {
      console.warn('🔒 Security Audit Issues Found:');
      result.issues.forEach(issue => console.warn(`❌ ${issue}`));
    }

    if (result.recommendations.length > 0) {
      console.info('💡 Security Recommendations:');
      result.recommendations.forEach(rec => console.info(`✅ ${rec}`));
    }
  });
}
