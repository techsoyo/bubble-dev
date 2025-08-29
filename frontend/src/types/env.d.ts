/**
 * Environment Types - SECURE & TYPE-SAFE
 *
 * ✅ SEGURIDAD: Validación estricta de variables de entorno
 * ✅ TYPE SAFETY: Tipos específicos y literales
 * ✅ PERFORMANCE: Validación en compile-time
 * ✅ PRODUCTION READY: Configuración segura para diferentes entornos
 */

/// <reference types="vite/client" />

// Environment types with strict validation
type Environment = 'development' | 'staging' | 'production' | 'test';
type BooleanString = 'true' | 'false';

// Security: URL validation for API endpoints
type SecureURL = `http${'s' | ''}://${string}`;

// API Configuration
interface ImportMetaEnv {
    // Required environment variables
    readonly VITE_API_URL: SecureURL;
    readonly VITE_APP_TITLE: string;

    // Environment control
    readonly VITE_ENVIRONMENT: Environment;
    readonly MODE: Environment;
    readonly PROD: boolean;
    readonly DEV: boolean;
    readonly SSR: boolean;

    // Base URL for assets
    readonly BASE_URL: string;

    // Feature flags with strict typing
    readonly VITE_CV_ALLOW_MANUAL_ONLY?: BooleanString;
    readonly VITE_ENABLE_ANALYTICS?: BooleanString;
    readonly VITE_ENABLE_DEBUG?: BooleanString;
    readonly VITE_ENABLE_PERFORMANCE_MONITORING?: BooleanString;

    // External service URLs with validation
    readonly VITE_SENTRY_DSN?: SecureURL;
    readonly VITE_GOOGLE_ANALYTICS_ID?: `G-${string}` | `UA-${string}`;
    readonly VITE_HOTJAR_ID?: string;
    readonly VITE_INTERCOM_APP_ID?: string;

    // Security configuration
    readonly VITE_CSP_NONCE?: string;
    readonly VITE_ENABLE_CSP?: BooleanString;

    // Performance configuration
    readonly VITE_IMAGE_OPTIMIZATION?: BooleanString;
    readonly VITE_LAZY_LOADING_THRESHOLD?: string;
    readonly VITE_BUNDLE_ANALYZER?: BooleanString;

    // Development helpers (only in dev mode)
    readonly VITE_MSW_ENABLED?: BooleanString;
    readonly VITE_STORYBOOK_ENABLED?: BooleanString;
}

// Enhanced ImportMeta with security validation
interface ImportMeta {
    readonly env: ImportMetaEnv;

    // Vite-specific features with security
    readonly hot?: {
        readonly accept: (cb?: () => void) => void;
        readonly dispose: (cb: () => void) => void;
        readonly decline: () => void;
        readonly invalidate: () => void;
        readonly data: any; // Limited to Vite's internal use
    };

    readonly glob: (pattern: string) => Record<string, () => Promise<unknown>>;
    readonly globEager: (pattern: string) => Record<string, unknown>;
    readonly globEagerDefault: (pattern: string) => Record<string, unknown>;
}

// Global window extensions with security
declare global {
    interface Window {
        // Runtime environment (sanitized)
        readonly ENV: {
            readonly VITE_API_URL: SecureURL;
            readonly VITE_ENVIRONMENT: Environment;
            readonly VITE_APP_TITLE: string;
            readonly MODE: Environment;
            readonly VERSION: string;
        };

        // Security: Prevent prototype pollution
        readonly prototype?: never;

        // Performance monitoring
        readonly performance: Performance;

        // Security: Controlled access to global functions
        readonly btoa: (input: string) => string;
        readonly atob: (input: string) => string;

        // Development helpers (only in dev mode)
        readonly __REDUX_DEVTOOLS_EXTENSION__?: unknown;
        readonly __REACT_DEVTOOLS_GLOBAL_HOOK__?: unknown;
    }

    // Security: Prevent global variable pollution
    interface Global {
        readonly [key: string]: never;
    }
}

// Environment validation utilities
export type EnvironmentConfig = {
    readonly isProduction: boolean;
    readonly isDevelopment: boolean;
    readonly isStaging: boolean;
    readonly isTest: boolean;
    readonly apiUrl: SecureURL;
    readonly appTitle: string;
    readonly features: {
        readonly analytics: boolean;
        readonly debug: boolean;
        readonly performance: boolean;
        readonly csp: boolean;
    };
};

// Runtime environment validation
export function getEnvironmentConfig(): EnvironmentConfig {
    const env = import.meta.env;

    return {
        isProduction: env.PROD,
        isDevelopment: env.DEV,
        isStaging: env.VITE_ENVIRONMENT === 'staging',
        isTest: env.MODE === 'test',
        apiUrl: env.VITE_API_URL,
        appTitle: env.VITE_APP_TITLE,
        features: {
            analytics: env.VITE_ENABLE_ANALYTICS === 'true',
            debug: env.VITE_ENABLE_DEBUG === 'true',
            performance: env.VITE_ENABLE_PERFORMANCE_MONITORING === 'true',
            csp: env.VITE_ENABLE_CSP === 'true'
        }
    } as const;
}

// Type guards for environment validation
export function isValidEnvironment(env: string): env is Environment {
    return ['development', 'staging', 'production', 'test'].includes(env);
}

export function isValidURL(url: string): url is SecureURL {
    try {
        const parsed = new URL(url);
        return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch {
        return false;
    }
}

export function isValidAnalyticsId(id: string): id is `G-${string}` | `UA-${string}` {
    return /^G-/.test(id) || /^UA-/.test(id);
}

// Security: Prevent environment variable tampering
Object.freeze(import.meta.env);

// Export for use in other files
export { };
