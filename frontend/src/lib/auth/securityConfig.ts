/**
 * Security Configuration - Production Ready
 * Configuración centralizada de seguridad para la aplicación
 */

export interface SecurityConfig {
  // API Configuration
  apiBaseUrl: string;
  csrfTokenEndpoint: string;
  validateCsrfEndpoint: string;

  // Authentication
  loginEndpoint: string;
  logoutEndpoint: string;
  refreshTokenEndpoint: string;

  // File Upload
  maxFileSize: number;
  allowedFileTypes: string[];
  uploadEndpoint: string;

  // Rate Limiting
  maxRequestsPerMinute: number;
  maxLoginAttempts: number;

  // Security Headers
  enableCSP: boolean;
  enableHSTS: boolean;
  enableXFrameOptions: boolean;

  // Session Management
  sessionTimeout: number;
  tokenRefreshThreshold: number;
}

export class SecurityConfiguration {
  private static config: SecurityConfig;

  /**
   * Initialize security configuration
   */
  static initialize(): void {
    const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost/bubble_of_talents_1.0/backend/public';

    this.config = {
      // API Configuration
      apiBaseUrl,
      csrfTokenEndpoint: `${apiBaseUrl}/api/auth/csrf-token.php`,
      validateCsrfEndpoint: `${apiBaseUrl}/api/auth/validate-csrf.php`,

      // Authentication
      loginEndpoint: `${apiBaseUrl}/api/auth/login`,
      logoutEndpoint: `${apiBaseUrl}/api/auth/logout`,
      refreshTokenEndpoint: `${apiBaseUrl}/api/auth/refresh`,

      // File Upload
      maxFileSize: 10 * 1024 * 1024, // 10MB
      allowedFileTypes: ['application/pdf', 'image/jpeg', 'image/png'],
      uploadEndpoint: `${apiBaseUrl}/api/upload`,

      // Rate Limiting
      maxRequestsPerMinute: 60,
      maxLoginAttempts: 5,

      // Security Headers
      enableCSP: true,
      enableHSTS: true,
      enableXFrameOptions: true,

      // Session Management
      sessionTimeout: 3600000, // 1 hour
      tokenRefreshThreshold: 300000, // 5 minutes
    };
  }

  /**
   * Get security configuration
   */
  static getConfig(): SecurityConfig {
    if (!this.config) {
      this.initialize();
    }
    return this.config;
  }

  /**
   * Get API base URL
   */
  static getApiBaseUrl(): string {
    return this.getConfig().apiBaseUrl;
  }

  /**
   * Get endpoint URL
   */
  static getEndpoint(endpoint: keyof Pick<SecurityConfig,
    'csrfTokenEndpoint' | 'validateCsrfEndpoint' | 'loginEndpoint' |
    'logoutEndpoint' | 'refreshTokenEndpoint' | 'uploadEndpoint'>): string {
    return this.getConfig()[endpoint];
  }

  /**
   * Validate file upload
   */
  static validateFileUpload(file: File): { isValid: boolean; error?: string } {
    const config = this.getConfig();

    if (file.size > config.maxFileSize) {
      return {
        isValid: false,
        error: `File size exceeds maximum allowed size of ${config.maxFileSize / (1024 * 1024)}MB`
      };
    }

    if (!config.allowedFileTypes.includes(file.type)) {
      return {
        isValid: false,
        error: `File type ${file.type} is not allowed. Allowed types: ${config.allowedFileTypes.join(', ')}`
      };
    }

    return { isValid: true };
  }
}

// Initialize configuration on module load
SecurityConfiguration.initialize();
