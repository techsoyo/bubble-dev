/**
 * Content Security Policy Configuration
 * 
 * Implements CSP headers and security policies for production deployment.
 * Prevents XSS attacks, clickjacking, and other security vulnerabilities.
 * 
 * @package Security
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
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
    'upgrade-insecure-requests'?: boolean;
    'block-all-mixed-content'?: boolean;
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
        "'unsafe-inline'", // Required for Tailwind CSS
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
        import.meta.env.VITE_API_URL || 'http://localhost:8000',
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
    'upgrade-insecure-requests': true,
    'block-all-mixed-content': true,
};

/**
 * Development CSP configuration (more permissive)
 */
export const DEVELOPMENT_CSP: CSPDirectives = {
    'default-src': ["'self'"],
    'script-src': [
        "'self'",
        "'unsafe-inline'",
        "'unsafe-eval'", // Required for development
        'http://localhost:*',
        'https://cdn.jsdelivr.net',
    ],
    'style-src': [
        "'self'",
        "'unsafe-inline'",
        'https://fonts.googleapis.com',
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
    ],
    'connect-src': [
        "'self'",
        'http://localhost:*',
        'ws://localhost:*',
        'wss://localhost:*',
    ],
    'media-src': ["'self'", 'data:', 'blob:'],
    'object-src': ["'none'"],
    'worker-src': ["'self'", 'blob:'],
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
} as const;

/**
 * Validate and sanitize external URLs
 */
export function isValidExternalUrl(url: string): boolean {
    try {
        const urlObj = new URL(url);
        const allowedProtocols = ['https:', 'http:'];
        const allowedDomains = [
            'api.bubble-talents.com',
            'fonts.googleapis.com',
            'fonts.gstatic.com',
            'cdn.jsdelivr.net',
            'images.unsplash.com',
        ];

        return (
            allowedProtocols.includes(urlObj.protocol) &&
            allowedDomains.some(domain => urlObj.hostname === domain || urlObj.hostname.endsWith(`.${domain}`))
        );
    } catch {
        return false;
    }
}

/**
 * CSP violation reporter
 */
export function handleCSPViolation(event: SecurityPolicyViolationEvent): void {
    const violation = {
        documentURI: event.documentURI,
        violatedDirective: event.violatedDirective,
        blockedURI: event.blockedURI,
        sourceFile: event.sourceFile,
        lineNumber: event.lineNumber,
        columnNumber: event.columnNumber,
        timestamp: new Date().toISOString(),
    };

    // Log to console in development
    if (import.meta.env.MODE === 'development') {
        console.warn('CSP Violation:', violation);
    }

    // Send to monitoring service in production
    if (import.meta.env.MODE === 'production') {
        // Replace with your monitoring service
        fetch('/api/csp-violation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(violation),
        }).catch(error => {
            console.error('Failed to report CSP violation:', error);
        });
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
 * Get safe external link properties with security attributes
 */
export function getSafeExternalLinkProps(url: string) {
    const isValid = isValidExternalUrl(url);

    if (!isValid) {
        console.warn(`Potentially unsafe external URL blocked: ${url}`);
        return {
            href: '#',
            onClick: (e: Event) => e.preventDefault(),
            'aria-label': 'Blocked unsafe link'
        };
    }

    try {
        const urlObj = new URL(url);
        return {
            href: url,
            target: '_blank',
            rel: 'noopener noreferrer',
            'aria-label': `External link to ${urlObj.hostname} (opens in new tab)`
        };
    } catch {
        return {
            href: '#',
            onClick: (e: Event) => e.preventDefault(),
            'aria-label': 'Invalid link'
        };
    }
}
