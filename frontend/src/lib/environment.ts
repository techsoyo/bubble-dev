/**
 * Environment Configuration and Validation
 * 
 * Centralized environment variable management with validation
 * and type safety for the Bubble of Talents application.
 * 
 * @package Environment
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

/**
 * Environment variable schema
 */
interface EnvironmentConfig {
    // API Configuration
    apiBaseUrl: string;
    appEnv: 'development' | 'staging' | 'production';

    // Authentication
    jwtSecretKey: string;
    sessionTimeout: number;

    // External Services
    googleAnalyticsId?: string;
    sentryDsn?: string;
    hotjarId?: string;

    // Feature Flags
    enableAnalytics: boolean;
    enableErrorReporting: boolean;
    enablePerformanceMonitoring: boolean;
    enableAccessibilityTools: boolean;

    // Upload Configuration
    maxFileSize: number;
    allowedFileTypes: string[];

    // Security
    enableCSP: boolean;
    enableXSSProtection: boolean;

    // Development
    mockApi: boolean;
    debugMode: boolean;
    showPerformanceMetrics: boolean;

    // Build Information
    buildVersion: string;
    buildDate: string;
}

/**
 * Required environment variables
 */
const REQUIRED_ENV_VARS = [
    'VITE_API_BASE_URL',
    'VITE_APP_ENV',
    'VITE_JWT_SECRET_KEY'
] as const;

/**
 * Validate environment variables
 */
function validateEnvironmentVariables(): void {
    const missingVars: string[] = [];

    REQUIRED_ENV_VARS.forEach(varName => {
        if (!import.meta.env[varName]) {
            missingVars.push(varName);
        }
    });

    if (missingVars.length > 0) {
        throw new Error(
            `Missing required environment variables: ${missingVars.join(', ')}\n` +
            'Please check your .env file and ensure all required variables are set.'
        );
    }
}

/**
 * Parse boolean environment variable
 */
function parseBoolean(value: string | undefined, defaultValue: boolean = false): boolean {
    if (value === undefined) return defaultValue;
    return value.toLowerCase() === 'true';
}

/**
 * Parse number environment variable
 */
function parseNumber(value: string | undefined, defaultValue: number): number {
    if (value === undefined) return defaultValue;
    const parsed = parseInt(value, 10);
    return isNaN(parsed) ? defaultValue : parsed;
}

/**
 * Parse array environment variable
 */
function parseArray(value: string | undefined, defaultValue: string[] = []): string[] {
    if (value === undefined) return defaultValue;
    return value.split(',').map(item => item.trim()).filter(Boolean);
}

/**
 * Get environment configuration
 */
function getEnvironmentConfig(): EnvironmentConfig {
    // Validate required variables first
    validateEnvironmentVariables();

    const config: EnvironmentConfig = {
        // API Configuration
        apiBaseUrl: import.meta.env.VITE_API_BASE_URL,
        appEnv: import.meta.env.VITE_APP_ENV as 'development' | 'staging' | 'production',

        // Authentication
        jwtSecretKey: import.meta.env.VITE_JWT_SECRET_KEY,
        sessionTimeout: parseNumber(import.meta.env.VITE_SESSION_TIMEOUT, 3600000),

        // External Services
        googleAnalyticsId: import.meta.env.VITE_GOOGLE_ANALYTICS_ID,
        sentryDsn: import.meta.env.VITE_SENTRY_DSN,
        hotjarId: import.meta.env.VITE_HOTJAR_ID,

        // Feature Flags
        enableAnalytics: parseBoolean(import.meta.env.VITE_ENABLE_ANALYTICS),
        enableErrorReporting: parseBoolean(import.meta.env.VITE_ENABLE_ERROR_REPORTING),
        enablePerformanceMonitoring: parseBoolean(import.meta.env.VITE_ENABLE_PERFORMANCE_MONITORING, true),
        enableAccessibilityTools: parseBoolean(import.meta.env.VITE_ENABLE_ACCESSIBILITY_TOOLS, true),

        // Upload Configuration
        maxFileSize: parseNumber(import.meta.env.VITE_MAX_FILE_SIZE, 5242880), // 5MB default
        allowedFileTypes: parseArray(import.meta.env.VITE_ALLOWED_FILE_TYPES, ['pdf', 'doc', 'docx']),

        // Security
        enableCSP: parseBoolean(import.meta.env.VITE_ENABLE_CSP, true),
        enableXSSProtection: parseBoolean(import.meta.env.VITE_ENABLE_XSS_PROTECTION, true),

        // Development
        mockApi: parseBoolean(import.meta.env.VITE_MOCK_API),
        debugMode: parseBoolean(import.meta.env.VITE_DEBUG_MODE),
        showPerformanceMetrics: parseBoolean(import.meta.env.VITE_SHOW_PERFORMANCE_METRICS),

        // Build Information
        buildVersion: import.meta.env.VITE_BUILD_VERSION || '1.0.0',
        buildDate: import.meta.env.VITE_BUILD_DATE || new Date().toISOString()
    };

    // Validate environment-specific configurations
    validateEnvironmentConfig(config);

    return config;
}

/**
 * Validate environment-specific configurations
 */
function validateEnvironmentConfig(config: EnvironmentConfig): void {
    // Production validations
    if (config.appEnv === 'production') {
        if (config.debugMode) {
            console.warn('Debug mode is enabled in production environment');
        }

        if (!config.enableErrorReporting) {
            console.warn('Error reporting is disabled in production environment');
        }

        if (config.mockApi) {
            throw new Error('Mock API cannot be enabled in production environment');
        }
    }

    // API URL validation
    try {
        new URL(config.apiBaseUrl);
    } catch {
        throw new Error(`Invalid API base URL: ${config.apiBaseUrl}`);
    }

    // File size validation
    if (config.maxFileSize <= 0) {
        throw new Error('Max file size must be greater than 0');
    }

    // Session timeout validation
    if (config.sessionTimeout <= 0) {
        throw new Error('Session timeout must be greater than 0');
    }
}

/**
 * Environment utilities
 */
export const environment = {
    /**
     * Get environment configuration
     */
    getConfig: getEnvironmentConfig,

    /**
     * Check if running in development
     */
    isDevelopment: (): boolean => {
        return import.meta.env.DEV || import.meta.env.VITE_APP_ENV === 'development';
    },

    /**
     * Check if running in production
     */
    isProduction: (): boolean => {
        return import.meta.env.PROD || import.meta.env.VITE_APP_ENV === 'production';
    },

    /**
     * Check if running in staging
     */
    isStaging: (): boolean => {
        return import.meta.env.VITE_APP_ENV === 'staging';
    },

    /**
     * Get build information
     */
    getBuildInfo: () => ({
        version: import.meta.env.VITE_BUILD_VERSION || '1.0.0',
        date: import.meta.env.VITE_BUILD_DATE || 'unknown',
        mode: import.meta.env.MODE,
        env: import.meta.env.VITE_APP_ENV || 'development'
    }),

    /**
     * Log environment information (development only)
     */
    logInfo: (): void => {
        if (environment.isDevelopment()) {
            const config = getEnvironmentConfig();
            console.group('🌍 Environment Configuration');
            console.log('Environment:', config.appEnv);
            console.log('API Base URL:', config.apiBaseUrl);
            console.log('Build Version:', config.buildVersion);
            console.log('Build Date:', config.buildDate);
            console.log('Feature Flags:', {
                analytics: config.enableAnalytics,
                errorReporting: config.enableErrorReporting,
                performanceMonitoring: config.enablePerformanceMonitoring,
                accessibilityTools: config.enableAccessibilityTools
            });
            console.groupEnd();
        }
    }
};

// Export configuration instance
export const config = getEnvironmentConfig();

// Export types
export type { EnvironmentConfig };

// Initialize environment logging in development
if (environment.isDevelopment()) {
    environment.logInfo();
}
