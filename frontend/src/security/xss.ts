/**
 * XSS Protection and Input Sanitization
 * 
 * Provides comprehensive protection against Cross-Site Scripting (XSS) attacks
 * through input sanitization, output encoding, and content validation.
 * 
 * @package Security
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import DOMPurify from 'dompurify';

/**
 * DOMPurify configuration for different contexts
 */
export const SANITIZE_CONFIG = {
    // Strict configuration for user input
    STRICT: {
        ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'br'] as string[],
        ALLOWED_ATTR: [] as string[],
        KEEP_CONTENT: false,
        ALLOW_DATA_ATTR: false,
    },

    // Basic HTML configuration for rich text
    BASIC_HTML: {
        ALLOWED_TAGS: [
            'p', 'br', 'strong', 'b', 'i', 'em', 'u', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'blockquote', 'a'
        ] as string[],
        ALLOWED_ATTR: ['href', 'title', 'target'] as string[],
        KEEP_CONTENT: true,
        ALLOW_DATA_ATTR: false,
    },

    // Text-only configuration
    TEXT_ONLY: {
        ALLOWED_TAGS: [] as string[],
        ALLOWED_ATTR: [] as string[],
        KEEP_CONTENT: true,
        ALLOW_DATA_ATTR: false,
    },
};

/**
 * Sanitize HTML content to prevent XSS attacks
 */
export function sanitizeHtml(
    content: string,
    config: keyof typeof SANITIZE_CONFIG = 'STRICT'
): string {
    if (!content || typeof content !== 'string') {
        return '';
    }

    return DOMPurify.sanitize(content, SANITIZE_CONFIG[config]);
}

/**
 * Sanitize plain text input
 */
export function sanitizeText(input: string): string {
    if (!input || typeof input !== 'string') {
        return '';
    }

    return input
        .trim()
        .replace(/[\u0000-\u001F\u007F-\u009F]/g, '') // Remove control characters
        .replace(/[\u2000-\u200F\u2028-\u202F\u205F-\u206F\uFEFF]/g, '') // Remove unicode spaces
        .replace(/\s+/g, ' ') // Normalize whitespace
        .slice(0, 10000); // Limit length
}

/**
 * Validate and sanitize URL
 */
export function sanitizeUrl(url: string): string {
    if (!url || typeof url !== 'string') {
        return '';
    }

    try {
        const urlObj = new URL(url);

        // Only allow http and https protocols
        if (!['http:', 'https:'].includes(urlObj.protocol)) {
            return '';
        }

        return urlObj.toString();
    } catch {
        return '';
    }
}

/**
 * Sanitize email address
 */
export function sanitizeEmail(email: string): string {
    if (!email || typeof email !== 'string') {
        return '';
    }

    const sanitized = email.toLowerCase().trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    return emailRegex.test(sanitized) ? sanitized : '';
}

/**
 * Escape HTML characters for safe output
 */
export function escapeHtml(text: string): string {
    if (!text || typeof text !== 'string') {
        return '';
    }

    const htmlEscapes: Record<string, string> = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#x27;',
        '/': '&#x2F;',
    };

    return text.replace(/[&<>"'\/]/g, (match) => htmlEscapes[match] || match);
}

/**
 * Validate and sanitize file name
 */
export function sanitizeFileName(fileName: string): string {
    if (!fileName || typeof fileName !== 'string') {
        return '';
    }

    return fileName
        .trim()
        .replace(/[^a-zA-Z0-9\-_\.\s]/g, '') // Remove special characters
        .replace(/\s+/g, '_') // Replace spaces with underscores
        .replace(/\.+/g, '.') // Remove multiple dots
        .slice(0, 255); // Limit length
}

/**
 * Validate if content is safe for rendering
 */
export function isContentSafe(content: string): boolean {
    if (!content || typeof content !== 'string') {
        return true;
    }

    // Check for common XSS patterns
    const dangerousPatterns = [
        /<script[\s\S]*?>[\s\S]*?<\/script>/gi,
        /javascript:/gi,
        /vbscript:/gi,
        /data:text\/html/gi,
        /on\w+\s*=/gi,
        /<iframe[\s\S]*?>/gi,
        /<object[\s\S]*?>/gi,
        /<embed[\s\S]*?>/gi,
        /<form[\s\S]*?>/gi,
    ];

    return !dangerousPatterns.some(pattern => pattern.test(content));
}

/**
 * Create safe innerHTML replacement
 */
export function createSafeInnerHTML(content: string): { __html: string } {
    return {
        __html: sanitizeHtml(content, 'BASIC_HTML')
    };
}

/**
 * Validate and sanitize form data
 */
export function sanitizeFormData<T extends Record<string, unknown>>(
    data: T
): Partial<T> {
    const sanitized: Partial<T> = {};

    Object.entries(data).forEach(([key, value]) => {
        if (typeof value === 'string') {
            sanitized[key as keyof T] = sanitizeText(value) as T[keyof T];
        } else if (typeof value === 'number' && !isNaN(value)) {
            sanitized[key as keyof T] = value as T[keyof T];
        } else if (typeof value === 'boolean') {
            sanitized[key as keyof T] = value as T[keyof T];
        } else if (Array.isArray(value)) {
            sanitized[key as keyof T] = value
                .filter(item => typeof item === 'string')
                .map(item => sanitizeText(item as string)) as T[keyof T];
        }
    });

    return sanitized;
}

/**
 * Validate input against common injection patterns
 */
export function detectInjectionAttempt(input: string): boolean {
    if (!input || typeof input !== 'string') {
        return false;
    }

    const injectionPatterns = [
        // SQL injection patterns
        /('|\\')|(;)|(\s*(union|select|insert|update|delete|drop|create|alter|exec|execute)\s+)/gi,

        // NoSQL injection patterns
        /(\$where|\$ne|\$gt|\$lt|\$gte|\$lte|\$in|\$nin|\$exists|\$regex)/gi,

        // Command injection patterns
        /(;|\||&|`|\$\(|\$\{)/gi,

        // Path traversal patterns
        /(\.\.\/|\.\.\\|\.\.|%2e%2e|%2f|%5c)/gi,

        // XSS patterns
        /(<script|<iframe|<object|<embed|javascript:|vbscript:|data:)/gi,
    ];

    return injectionPatterns.some(pattern => pattern.test(input));
}

/**
 * Rate limiting based on client fingerprint
 */
class RateLimiter {
    private attempts = new Map<string, { count: number; timestamp: number }>();
    private readonly maxAttempts: number;
    private readonly windowMs: number;

    constructor(maxAttempts = 10, windowMs = 60000) {
        this.maxAttempts = maxAttempts;
        this.windowMs = windowMs;
    }

    isAllowed(identifier: string): boolean {
        const now = Date.now();
        const attempt = this.attempts.get(identifier);

        if (!attempt) {
            this.attempts.set(identifier, { count: 1, timestamp: now });
            return true;
        }

        if (now - attempt.timestamp > this.windowMs) {
            this.attempts.set(identifier, { count: 1, timestamp: now });
            return true;
        }

        if (attempt.count >= this.maxAttempts) {
            return false;
        }

        attempt.count++;
        return true;
    }

    reset(identifier: string): void {
        this.attempts.delete(identifier);
    }

    cleanup(): void {
        const now = Date.now();
        const keysToDelete: string[] = [];
        this.attempts.forEach((attempt, key) => {
            if (now - attempt.timestamp > this.windowMs) {
                keysToDelete.push(key);
            }
        });
        keysToDelete.forEach(key => this.attempts.delete(key));
    }
}

// Global rate limiter instances
export const formSubmissionLimiter = new RateLimiter(5, 60000); // 5 submissions per minute
export const apiRequestLimiter = new RateLimiter(100, 60000); // 100 requests per minute

/**
 * Generate client fingerprint for rate limiting
 */
export function generateClientFingerprint(): string {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    if (ctx) {
        ctx.textBaseline = 'top';
        ctx.font = '14px Arial';
        ctx.fillText('Bubble of Talents', 2, 2);
    }

    const fingerprint = [
        navigator.userAgent,
        navigator.language,
        screen.width + 'x' + screen.height,
        new Date().getTimezoneOffset().toString(),
        canvas.toDataURL(),
    ].join('|');

    // Simple hash function
    let hash = 0;
    for (let i = 0; i < fingerprint.length; i++) {
        const char = fingerprint.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash; // Convert to 32-bit integer
    }

    return Math.abs(hash).toString(36);
}

/**
 * Initialize XSS protection
 */
export function initXSSProtection(): void {
    // Set up CSP violation reporting
    document.addEventListener('securitypolicyviolation', (event) => {
        console.warn('CSP Violation:', event.violatedDirective, event.blockedURI);
    });

    // Clean up rate limiters periodically
    setInterval(() => {
        formSubmissionLimiter.cleanup();
        apiRequestLimiter.cleanup();
    }, 300000); // Every 5 minutes
}
