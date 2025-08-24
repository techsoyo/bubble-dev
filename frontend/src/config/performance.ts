/**
 * Performance Configuration
 * Configuración centralizada para todas las optimizaciones
 */

export const PERFORMANCE_CONFIG = {
  // Bundle size limits (KB)
  BUNDLE_LIMITS: {
    MAIN: 200,
    VENDOR: 150,
    CHUNK: 100,
    TOTAL: 800,
  },
  
  // Web Vitals thresholds
  WEB_VITALS: {
    LCP: { good: 2500, poor: 4000 },
    FID: { good: 100, poor: 300 },
    CLS: { good: 0.1, poor: 0.25 },
    FCP: { good: 1800, poor: 3000 },
    TTFB: { good: 800, poor: 1800 },
  },
  
  // Lazy loading patterns
  LAZY_LOADING: {
    HEAVY_COMPONENTS: [
      'UploadCV',
      'Estadisticas',
      'AdminPanel',
      'PDFProcessor',
    ],
    VENDOR_CHUNKS: [
      'charts',
      'pdf',
      'forms',
      'ui',
      'utils',
    ],
  },
  
  // Image optimization
  IMAGE_OPTIMIZATION: {
    FORMATS: ['webp', 'avif', 'jpeg'],
    QUALITY: 85,
    LAZY_LOADING: true,
    RESPONSIVE: true,
  },
  
  // Cache strategies
  CACHE_STRATEGIES: {
    STATIC_ASSETS: '1y',
    API_RESPONSES: '5m',
    VENDOR_BUNDLES: '1y',
    APP_BUNDLES: '1w',
  },
};

export default PERFORMANCE_CONFIG;
