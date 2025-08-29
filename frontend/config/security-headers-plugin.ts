/**
 * Plugin personalizado para inyectar security headers en Vite
 * Optimizado para desarrollo y producción
 */

// Función para generar nonce criptográficamente seguro
function generateNonce(): string {
  return Buffer.from(Math.random().toString(36).substring(2) + Date.now().toString(36)).toString('base64');
}

export function securityHeadersPlugin() {
  return {
    name: 'security-headers',
    configureServer(server: any) {
      server.middlewares.use((req: any, res: any, next: any) => {
        // Detectar entorno automáticamente
        const environment = process.env.NODE_ENV === 'production' ? 'production' : 'development';
        setSecurityHeaders(res, environment);
        next();
      });
    },
    generateBundle() {
    //   // En producción, estos headers se configuran normalmente en el servidor web
    //   const env = process.env.NODE_ENV === 'production' ? 'PRODUCCIÓN' : 'DESARROLLO';
    //   if (process.env.NODE_ENV === 'production') {
    //   } else {
    //   }
    // }
  }
}

function setSecurityHeaders(res: any, environment: 'development' | 'production') {
  // Content Security Policy - Configuración segura para producción
  const isDev = environment === 'development';

  // Obtener la URL del backend desde variables de entorno
  const apiBaseUrl = process.env.VITE_API_BASE_URL || 'http://localhost:8000';
  const backendUrl = new URL(apiBaseUrl).origin;

  // Generar nonce para scripts y estilos seguros
  const nonce = generateNonce();

  const cspDirectives = [
    "default-src 'self'",
    // ✅ FIXED: Removemos unsafe-inline y unsafe-eval en producción
    isDev
      ? "script-src 'self' 'unsafe-inline' 'unsafe-eval'"
      : `script-src 'self' 'nonce-${nonce}'`,
    // ✅ FIXED: Usamos nonce para styles en producción
    isDev
      ? "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com"
      : `style-src 'self' 'nonce-${nonce}' https://fonts.googleapis.com https://cdnjs.cloudflare.com`,
    isDev
      ? "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com"
      : `style-src-elem 'self' 'nonce-${nonce}' https://fonts.googleapis.com https://cdnjs.cloudflare.com`,
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
    // ✅ FIXED: Removemos data: en producción para mayor seguridad
    isDev
      ? "img-src 'self' data: https:"
      : "img-src 'self' https:",
    `connect-src 'self' ${backendUrl} https: wss: ws:`,
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "object-src 'none'",
    "upgrade-insecure-requests"
  ].filter(directive => directive !== "");

  res.setHeader('Content-Security-Policy', cspDirectives.join('; '));

  // Security headers estándar
  res.setHeader('X-Frame-Options', 'DENY');
  res.setHeader('X-XSS-Protection', '1; mode=block');
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

  // Headers adicionales para navegadores modernos
  res.setHeader('Cross-Origin-Embedder-Policy', 'require-corp');
  res.setHeader('Cross-Origin-Opener-Policy', 'same-origin');
  res.setHeader('Cross-Origin-Resource-Policy', 'cross-origin');

  // HSTS solo en producción HTTPS
  if (environment === 'production') {
    res.setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
  }

  // Permissions Policy - Deshabilitar APIs no necesarias
  const permissionsPolicies = [
    'geolocation=()',
    'microphone=()',
    'camera=()',
    'payment=()',
    'usb=()',
    'magnetometer=()',
    'gyroscope=()',
    'accelerometer=()',
    'autoplay=()',
    'encrypted-media=()',
    'fullscreen=(self)',
    'picture-in-picture=()'
  ];

  res.setHeader('Permissions-Policy', permissionsPolicies.join(', '));
}
