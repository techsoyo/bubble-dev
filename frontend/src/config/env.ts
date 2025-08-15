// --- TEST FEATURES (solo en desarrollo/local) ---
let TEST_FEATURES: { SHOW_API_TESTER?: boolean } = {};
try {

  TEST_FEATURES = require('./env.test').TEST_FEATURES || {};
} catch { }

/**
 * Environment Configuration for Bubble Talents Frontend
 * 
 * Centraliza todas las variables de entorno y provide type safety
 * 
 * @package Config
 * @author Bubble Talents Development Team
 * @version 1.0.0
 * @since 2025-01-07
 */

// 🔧 CORRECCIÓN: Interfaz tipada para variables de entorno
interface EnvironmentConfig {
  // App Configuration
  NODE_ENV: 'development' | 'production' | 'test';
  MODE: 'development' | 'production';
  DEV: boolean;
  PROD: boolean;

  // API Configuration
  API_BASE_URL: string;
  APP_ENV: string;

  // Development Server
  DEV_PORT: number;
  DEV_HOST: string;
  DEV_OPEN: boolean;

  // Authentication
  JWT_SECRET_KEY: string;
  SESSION_TIMEOUT: number;

  // External Services
  GOOGLE_ANALYTICS_ID?: string;
  SENTRY_DSN?: string;
  HOTJAR_ID?: string;

  // Build Information
  BUILD_VERSION: string;
  BUILD_DATE: string;

  // Feature Flags
  ENABLE_ANALYTICS: boolean;
  ENABLE_ERROR_REPORTING: boolean;
  ENABLE_PERFORMANCE_MONITORING: boolean;

  // Security
  ENABLE_CSP: boolean;
  ENABLE_SECURITY_HEADERS: boolean;
}

// 🔧 CORRECCIÓN: Función para obtener variables de entorno de forma segura
const getEnvVar = (key: string, defaultValue?: string): string => {
  // Priorizar Vite env vars sobre process.env
  const viteValue = import.meta.env[`VITE_${key}`];
  const processValue = (typeof process !== 'undefined' && process.env) ? process.env[key] : undefined;

  return viteValue || processValue || defaultValue || '';
};

const getBooleanEnvVar = (key: string, defaultValue: boolean = false): boolean => {
  const value = getEnvVar(key);
  if (!value) return defaultValue;
  return value.toLowerCase() === 'true' || value === '1';
};

const getNumberEnvVar = (key: string, defaultValue: number): number => {
  const value = getEnvVar(key);
  const parsed = parseInt(value, 10);
  return isNaN(parsed) ? defaultValue : parsed;
};

// 🔧 CORRECCIÓN: Configuración dinámica y robusta de API_BASE_URL
const getApiBaseUrl = (): string => {
  if (import.meta.env.PROD) {
    // Producción: usar VITE_API_BASE_URL o API_BASE_URL de entorno de build
    return getEnvVar('API_BASE_URL', '');
  }
  // Desarrollo: usar puerto 8000 donde corre el backend PHP
  if (typeof window !== 'undefined') {
    const currentHost = window.location.hostname;
    return `http://${currentHost}:8000`;
  }
  // SSR fallback
  return 'http://localhost:8000';
};

// 🔧 CORRECCIÓN: Configuración unificada
export const env: EnvironmentConfig = {
  // App Configuration
  NODE_ENV: (import.meta.env.MODE || 'development') as 'development' | 'production' | 'test',
  MODE: import.meta.env.MODE as 'development' | 'production',
  DEV: import.meta.env.DEV || false,
  PROD: import.meta.env.PROD || false,

  // API Configuration
  API_BASE_URL: getApiBaseUrl(),
  APP_ENV: getEnvVar('APP_ENV', 'development'),

  // Development Server
  DEV_PORT: getNumberEnvVar('DEV_PORT', 3002),
  DEV_HOST: getEnvVar('DEV_HOST', 'localhost'),
  DEV_OPEN: getBooleanEnvVar('DEV_OPEN', true),

  // Authentication
  JWT_SECRET_KEY: getEnvVar('JWT_SECRET_KEY', 'default-secret-key'),
  SESSION_TIMEOUT: getNumberEnvVar('SESSION_TIMEOUT', 3600000),

  // External Services
  GOOGLE_ANALYTICS_ID: getEnvVar('GOOGLE_ANALYTICS_ID'),
  SENTRY_DSN: getEnvVar('SENTRY_DSN'),
  HOTJAR_ID: getEnvVar('HOTJAR_ID'),

  // Build Information
  BUILD_VERSION: getEnvVar('BUILD_VERSION', '1.0.0'),
  BUILD_DATE: getEnvVar('BUILD_DATE', new Date().toISOString().split('T')[0]),

  // Feature Flags
  ENABLE_ANALYTICS: getBooleanEnvVar('ENABLE_ANALYTICS', false),
  ENABLE_ERROR_REPORTING: getBooleanEnvVar('ENABLE_ERROR_REPORTING', false),
  ENABLE_PERFORMANCE_MONITORING: getBooleanEnvVar('ENABLE_PERFORMANCE_MONITORING', true),

  // Security
  ENABLE_CSP: getBooleanEnvVar('ENABLE_CSP', true),
  ENABLE_SECURITY_HEADERS: getBooleanEnvVar('ENABLE_SECURITY_HEADERS', true),
};

// 🔧 CORRECCIÓN: Helpers para development/production
export const isDevelopment = env.NODE_ENV === 'development' || env.DEV;
export const isProduction = env.NODE_ENV === 'production' || env.PROD;
export const isTest = env.NODE_ENV === 'test';

// 🔧 CORRECCIÓN: Validación de variables críticas
export const validateEnv = (): boolean => {
  const requiredVars = [
    'API_BASE_URL',
    'JWT_SECRET_KEY'
  ];

  const missing = requiredVars.filter(key => !getEnvVar(key));

  if (missing.length > 0) {
    console.error('❌ Variables de entorno faltantes:', missing);
    return false;
  }

  return true;
};

// Log de configuración en desarrollo

export default env;

// --- Exportar showApiTester solo al final, después de isDevelopment ---
export const showApiTester = isDevelopment && !!TEST_FEATURES.SHOW_API_TESTER;
