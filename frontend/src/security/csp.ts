/**
 * Content Security Policy Configuration
 *
 * Implements CSP headers and security policies for production deployment.
 * Prevents XSS attacks, clickjacking, and other security vulnerabilities.
 *
 * SECURITY NOTES:
 * - Production config removes all 'unsafe-inline' and 'unsafe-eval'
 * - All external URLs are validated before inclusion
 * - CSP violations are reported and rate-limited
 * - Environment variables are validated for security
 *
 * @package Security
 * @author Bubble of Talents Development Team
 * @version 2.0.0 - Security Enhanced
 * @since 2025-01-05
 * @security-reviewed 2025-08-29
 */

export interface CSPDirectives {
    'default-src'?: string[];
    'script-src'?: string[];
    'style-src'?: string[];
    'img-src'?: string[];
    'font-src'?: string[];
    'connect-src'?: string[];
    'media-src'?: string[];
    'object-src'?: string[];
    'frame-src'?: string[];
    'worker-src'?: string[];
    'manifest-src'?: string[];
    'base-uri'?: string[];
    'form-action'?: string[];
    'frame-ancestors'?: string[];
    'report-uri'?: string[]; // Added for CSP violation reporting
    'upgrade-insecure-requests'?: boolean;
    'block-all-mixed-content'?: boolean;
}

/**
 * Validate API URL for security
 */
function validateApiUrl(url: string): boolean {
    if (!url) return false;

    try {
        const urlObj = new URL(url);
        // Only allow HTTPS in production
        if (import.meta.env.MODE === 'production' && urlObj.protocol !== 'https:') {
            return false;
        }
        // Allow localhost for development
        if (import.meta.env.MODE === 'development' && urlObj.hostname === 'localhost') {
            return true;
        }
        // Only allow trusted domains
        const allowedDomains = [
            'bubble-talents.com',
            'api.bubble-talents.com'
        ];
        return allowedDomains.some(domain =>
            urlObj.hostname === domain || urlObj.hostname.endsWith(`.${domain}`)
        );
    } catch {
        return false;
    }
}

/**
 * Production CSP configuration
 */
export const PRODUCTION_CSP: CSPDirectives = {
    'default-src': ["'self'"],
    // En producción no se debe permitir código en línea. Elimine 'unsafe-inline'
    // y confíe en nonces/hashes para scripts generados dinámicamente.
    'script-src': [
        "'self'",
        'https://cdn.jsdelivr.net',
        'https://unpkg.com',
    ],
    'style-src': [
        "'self'",
        // REMOVED: "'unsafe-inline'" - Security vulnerability
        'https://fonts.googleapis.com',
        'https://cdn.jsdelivr.net',
    ],
    'img-src': [
        "'self'",
        'data:',
        'blob:',
        'https:',
        'https://images.unsplash.com',
        'https://via.placeholder.com',
    ],
    'font-src': [
        "'self'",
        'data:',
        'https://fonts.gstatic.com',
        'https://cdn.jsdelivr.net',
    ],
    'connect-src': [
        "'self'",
        // Secure API URL validation
        validateApiUrl(import.meta.env.VITE_API_URL) ?
            import.meta.env.VITE_API_URL : 'https://api.bubble-talents.com',
        'https://api.bubble-talents.com',
    ],
    'media-src': ["'self'", 'data:', 'blob:'],
    'object-src': ["'none'"],
    'frame-src': ["'none'"],
    'worker-src': ["'self'", 'blob:'],
    'manifest-src': ["'self'"],
    'base-uri': ["'self'"],
    'form-action': ["'self'"],
    'frame-ancestors': ["'none'"],
    // Critical: Add CSP violation reporting
    'report-uri': ['/api/csp-report'],
    'upgrade-insecure-requests': true,
    'block-all-mixed-content': true,
};

/**
 * Development CSP configuration (more restrictive than before)
 */
export const DEVELOPMENT_CSP: CSPDirectives = {
    'default-src': ["'self'"],
    'script-src': [
        "'self'",
        // Keep unsafe-inline only for development tools, remove unsafe-eval
        "'unsafe-inline'",
        'http://localhost:*',
        'https://cdn.jsdelivr.net',
        // Allow Vite dev server
        'http://127.0.0.1:*',
    ],
    'style-src': [
        "'self'",
        "'unsafe-inline'", // Required for Vite HMR
        'https://fonts.googleapis.com',
        'http://localhost:*',
    ],
    'img-src': [
        "'self'",
        'data:',
        'blob:',
        'http:',
        'https:',
    ],
    'font-src': [
        "'self'",
        'data:',
        'https://fonts.gstatic.com',
        'http://localhost:*',
    ],
    'connect-src': [
        "'self'",
        'http://localhost:*',
        'ws://localhost:*',
        'wss://localhost:*',
        // Allow Vite dev server connections
        'http://127.0.0.1:*',
        'ws://127.0.0.1:*',
        // Safe API URL validation for development
        validateApiUrl(import.meta.env.VITE_API_URL) ?
            import.meta.env.VITE_API_URL : 'http://localhost:8000',
    ],
    'media-src': ["'self'", 'data:', 'blob:'],
    'object-src': ["'none'"],
    'worker-src': ["'self'", 'blob:'],
    // Add development reporting endpoint
    'report-uri': ['http://localhost:8000/api/csp-report'],
};

/**
 * Generate CSP header string from directives
 */
export function generateCSPHeader(directives: CSPDirectives): string {
    const policies: string[] = [];

    Object.entries(directives).forEach(([directive, values]) => {
        if (Array.isArray(values)) {
            policies.push(`${directive} ${values.join(' ')}`);
        } else if (typeof values === 'boolean' && values) {
            policies.push(directive);
        }
    });

    return policies.join('; ');
}

/**
 * Get appropriate CSP configuration based on environment
 */
export function getCSPConfig(): CSPDirectives {
    return import.meta.env.MODE === 'production' ? PRODUCTION_CSP : DEVELOPMENT_CSP;
}

/**
 * Validate CSP configuration for security compliance
 */
export function validateCSPConfig(config: CSPDirectives): { isValid: boolean; errors: string[] } {
    const errors: string[] = [];

    // Check for dangerous directives in production
    if (import.meta.env.MODE === 'production') {
        const dangerousDirectives = ['script-src', 'style-src', 'default-src'];

        dangerousDirectives.forEach(directive => {
            const values = config[directive as keyof CSPDirectives] as string[];
            if (values && Array.isArray(values)) {
                if (values.includes("'unsafe-inline'")) {
                    errors.push(`${directive} contains 'unsafe-inline' in production`);
                }
                if (values.includes("'unsafe-eval'")) {
                    errors.push(`${directive} contains 'unsafe-eval' in production`);
                }
            }
        });
    }

    // Check for required security directives
    if (!config['report-uri']) {
        errors.push('Missing report-uri directive for CSP violation reporting');
    }

    // Validate connect-src URLs
    const connectSrc = config['connect-src'] as string[];
    if (connectSrc && Array.isArray(connectSrc)) {
        connectSrc.forEach(url => {
            if (url.startsWith('http://') && import.meta.env.MODE === 'production') {
                errors.push(`HTTP URL in connect-src is not allowed in production: ${url}`);
            }
        });
    }

    return {
        isValid: errors.length === 0,
        errors
    };
}

/**
 * Get validated CSP configuration with security checks
 */
export function getValidatedCSPConfig(): { config: CSPDirectives; validation: { isValid: boolean; errors: string[] } } {
    const config = getCSPConfig();
    const validation = validateCSPConfig(config);

    if (!validation.isValid) {
        console.error('CSP Configuration Security Issues:', validation.errors);

        // In production, throw error for critical issues
        if (import.meta.env.MODE === 'production') {
            const criticalErrors = validation.errors.filter(error =>
                error.includes('unsafe-inline') || error.includes('unsafe-eval')
            );

            if (criticalErrors.length > 0) {
                throw new Error(`Critical CSP security issues: ${criticalErrors.join(', ')}`);
            }
        }
    }

    return { config, validation };
}/**
 * Generate nonce for inline scripts (production use)
 */
export function generateNonce(): string {
    const array = new Uint8Array(16);
    crypto.getRandomValues(array);
    return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
}

/**
 * Security headers configuration
 */
export const SECURITY_HEADERS = {
    'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'DENY',
    'X-XSS-Protection': '1; mode=block',
    'Referrer-Policy': 'strict-origin-when-cross-origin',
    'Permissions-Policy': 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
    'Strict-Transport-Security': 'max-age=31536000; includeSubDomains; preload',
    // Modern security headers
    'Cross-Origin-Embedder-Policy': 'require-corp',
    'Cross-Origin-Opener-Policy': 'same-origin',
    'Cross-Origin-Resource-Policy': 'cross-origin',
    'Origin-Agent-Cluster': '?1',
} as const;

/**
 * Validate and sanitize external URLs
 */
export function isValidExternalUrl(url: string): boolean {
    if (!url || typeof url !== 'string') {
        return false;
    }

    try {
        const urlObj = new URL(url);

        // Enhanced protocol validation
        const allowedProtocols = ['https:'];
        if (import.meta.env.MODE === 'development') {
            allowedProtocols.push('http:');
        }

        if (!allowedProtocols.includes(urlObj.protocol)) {
            return false;
        }

        // More restrictive domain validation
        const allowedDomains = [
            'fonts.googleapis.com',
            'fonts.gstatic.com',
            'cdn.jsdelivr.net',
            'images.unsplash.com',
            'via.placeholder.com',
        ];

        // In production, only allow HTTPS and trusted domains
        if (import.meta.env.MODE === 'production') {
            if (urlObj.protocol !== 'https:') {
                return false;
            }
            allowedDomains.push('api.bubble-talents.com');
        } else {
            // Development allows localhost
            allowedDomains.push('localhost', '127.0.0.1');
        }

        return allowedDomains.some(domain =>
            urlObj.hostname === domain ||
            urlObj.hostname.endsWith(`.${domain}`)
        );
    } catch {
        return false;
    }
}

/**
 * Rate limiting for CSP violation reports
 */
let violationCount = 0;
let lastViolationTime = 0;
const VIOLATION_RATE_LIMIT = 10; // Max violations per minute
const VIOLATION_WINDOW = 60000; // 1 minute in milliseconds

/**
 * CSP violation reporter with rate limiting and enhanced validation
 */
export function handleCSPViolation(event: SecurityPolicyViolationEvent): void {
    const now = Date.now();

    // Rate limiting
    if (now - lastViolationTime > VIOLATION_WINDOW) {
        violationCount = 0;
        lastViolationTime = now;
    }

    if (violationCount >= VIOLATION_RATE_LIMIT) {
        console.warn('CSP violation rate limit exceeded, ignoring report');
        return;
    }

    violationCount++;

    // Enhanced violation data validation
    const violation = {
        documentURI: event.documentURI || '',
        violatedDirective: event.violatedDirective || '',
        blockedURI: event.blockedURI || '',
        sourceFile: event.sourceFile || '',
        lineNumber: event.lineNumber || 0,
        columnNumber: event.columnNumber || 0,
        originalPolicy: event.originalPolicy || '',
        timestamp: new Date().toISOString(),
        userAgent: navigator.userAgent,
        environment: import.meta.env.MODE,
    };

    // Validate required fields
    if (!violation.violatedDirective || !violation.documentURI) {
        console.warn('Invalid CSP violation data, missing required fields');
        return;
    }

    // Log to console in development
    if (import.meta.env.MODE === 'development') {
        console.warn('CSP Violation:', violation);
    }

    // Send to monitoring service in production with retry logic
    if (import.meta.env.MODE === 'production') {
        sendCSPViolationReport(violation).catch(error => {
            console.error('Failed to report CSP violation after retries:', error);
        });
    }
}

/**
 * Send CSP violation report with retry logic
 */
async function sendCSPViolationReport(violation: any, retries = 3): Promise<void> {
    const reportUrl = '/api/csp-report';

    for (let attempt = 1; attempt <= retries; attempt++) {
        try {
            const response = await fetch(reportUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(violation),
            });

            if (response.ok) {
                return; // Success
            }

            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        } catch (error) {
            if (attempt === retries) {
                throw error;
            }

            // Exponential backoff
            await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempt) * 1000));
        }
    }
}

/**
 * Initialize CSP violation reporting
 */
export function initCSPReporting(): void {
    if (typeof window !== 'undefined') {
        document.addEventListener('securitypolicyviolation', handleCSPViolation);
    }
}

/**
 * Get safe external link properties with enhanced security attributes
 */
export function getSafeExternalLinkProps(url: string) {
    // Input validation
    if (!url || typeof url !== 'string' || url.trim() === '') {
        console.warn('Invalid URL provided to getSafeExternalLinkProps');
        return {
            href: '#',
            onClick: (e: Event) => e.preventDefault(),
            'aria-label': 'Invalid link',
            'data-blocked': 'true'
        };
    }

    const isValid = isValidExternalUrl(url);

    if (!isValid) {
        console.warn(`Potentially unsafe external URL blocked: ${url}`);
        return {
            href: '#',
            onClick: (e: Event) => e.preventDefault(),
            'aria-label': 'Blocked unsafe link',
            'data-blocked': 'true'
        };
    }

    try {
        const urlObj = new URL(url);

        // Additional security checks
        if (urlObj.hostname.length > 253) {
            console.warn('URL hostname too long, blocking');
            return {
                href: '#',
                onClick: (e: Event) => e.preventDefault(),
                'aria-label': 'Blocked unsafe link',
                'data-blocked': 'true'
            };
        }

        return {
            href: url,
            target: '_blank',
            rel: 'noopener noreferrer nofollow', // Enhanced security
            'aria-label': `External link to ${urlObj.hostname} (opens in new tab)`,
            'data-external': 'true',
            // Prevent reverse tabnabbing
            onClick: (e: Event) => {
                const target = e.target as HTMLAnchorElement;
                if (target && target.ownerDocument) {
                    // Prevent reverse tabnabbing by ensuring no opener
                    const newWindow = target.ownerDocument.defaultView?.open('', '_blank');
                    if (newWindow) {
                        newWindow.opener = null;
                    }
                }
            }
        };
    } catch (error) {
        console.warn('Error parsing URL in getSafeExternalLinkProps:', error);
        return {
            href: '#',
            onClick: (e: Event) => e.preventDefault(),
            'aria-label': 'Invalid link',
            'data-blocked': 'true'
        };
    }
}
