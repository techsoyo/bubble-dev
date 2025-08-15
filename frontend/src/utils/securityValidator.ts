/**
 * Security Validation Utilities
 * 
 * Provides comprehensive input validation and sanitization
 * to prevent XSS attacks and ensure data integrity.
 * 
 * @package Security/Validation
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import { sanitizeText, sanitizeHtml, detectInjectionAttempt } from '../security/xss';

/**
 * Validation rule types
 */
export interface ValidationRule {
    required?: boolean;
    minLength?: number;
    maxLength?: number;
    pattern?: RegExp;
    custom?: (value: string) => string | null;
    sanitize?: boolean;
    allowHtml?: boolean;
}

/**
 * Validation result
 */
export interface ValidationResult {
    isValid: boolean;
    errors: string[];
    sanitizedValue: string;
    warnings: string[];
}

/**
 * Security-focused form validator
 */
export class SecurityValidator {
    /**
     * Validate a single field value
     */
    static validateField(
        value: string,
        rules: ValidationRule,
        fieldName: string = 'Field'
    ): ValidationResult {
        const result: ValidationResult = {
            isValid: true,
            errors: [],
            sanitizedValue: value,
            warnings: []
        };

        // Check for potential injection attempts
        if (detectInjectionAttempt(value)) {
            result.errors.push(`${fieldName} contains potentially dangerous content`);
            result.isValid = false;
            result.warnings.push('Potential security threat detected and blocked');
        }

        // Required validation
        if (rules.required && (!value || value.trim().length === 0)) {
            result.errors.push(`${fieldName} is required`);
            result.isValid = false;
        }

        // Skip other validations if value is empty and not required
        if (!value && !rules.required) {
            return result;
        }

        // Length validations
        if (rules.minLength && value.length < rules.minLength) {
            result.errors.push(`${fieldName} must be at least ${rules.minLength} characters`);
            result.isValid = false;
        }

        if (rules.maxLength && value.length > rules.maxLength) {
            result.errors.push(`${fieldName} must be no more than ${rules.maxLength} characters`);
            result.isValid = false;
        }

        // Pattern validation
        if (rules.pattern && !rules.pattern.test(value)) {
            result.errors.push(`${fieldName} format is invalid`);
            result.isValid = false;
        }

        // Custom validation
        if (rules.custom) {
            const customError = rules.custom(value);
            if (customError) {
                result.errors.push(customError);
                result.isValid = false;
            }
        }

        // Sanitization
        if (rules.sanitize !== false) { // Default to true
            if (rules.allowHtml) {
                result.sanitizedValue = sanitizeHtml(value, 'BASIC_HTML');
            } else {
                result.sanitizedValue = sanitizeText(value);
            }

            // Warn if value was modified during sanitization
            if (result.sanitizedValue !== value) {
                result.warnings.push('Input was sanitized for security');
            }
        }

        return result;
    }

    /**
     * Validate multiple fields
     */
    static validateForm(
        data: Record<string, string>,
        rules: Record<string, ValidationRule>
    ): Record<string, ValidationResult> {
        const results: Record<string, ValidationResult> = {};

        Object.keys(rules).forEach(fieldName => {
            const value = data[fieldName] || '';
            const fieldRules = rules[fieldName];
            if (fieldRules) {
                results[fieldName] = this.validateField(value, fieldRules, fieldName);
            }
        });

        return results;
    }

    /**
     * Check if form validation passed
     */
    static isFormValid(results: Record<string, ValidationResult>): boolean {
        return Object.values(results).every(result => result.isValid);
    }

    /**
     * Get all form errors
     */
    static getFormErrors(results: Record<string, ValidationResult>): Record<string, string[]> {
        const errors: Record<string, string[]> = {};

        Object.entries(results).forEach(([field, result]) => {
            if (result.errors.length > 0) {
                errors[field] = result.errors;
            }
        });

        return errors;
    }

    /**
     * Get sanitized form data
     */
    static getSanitizedData(results: Record<string, ValidationResult>): Record<string, string> {
        const sanitizedData: Record<string, string> = {};

        Object.entries(results).forEach(([field, result]) => {
            sanitizedData[field] = result.sanitizedValue;
        });

        return sanitizedData;
    }
}

/**
 * Common validation rules
 */
export const CommonValidationRules = {
    email: {
        pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        maxLength: 254,
        custom: (value: string) => {
            // Additional email security checks
            const suspiciousPatterns = [
                /javascript:/i,
                /data:/i,
                /vbscript:/i,
                /<script/i,
                /on\w+=/i
            ];

            for (const pattern of suspiciousPatterns) {
                if (pattern.test(value)) {
                    return 'Email contains invalid characters';
                }
            }
            return null;
        }
    },

    password: {
        minLength: 8,
        maxLength: 128,
        pattern: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/,
        custom: (value: string) => {
            const hasLower = /[a-z]/.test(value);
            const hasUpper = /[A-Z]/.test(value);
            const hasDigit = /\d/.test(value);
            const hasSpecial = /[@$!%*?&]/.test(value);

            if (!hasLower || !hasUpper || !hasDigit || !hasSpecial) {
                return 'Password must contain uppercase, lowercase, number, and special character';
            }
            return null;
        }
    },

    phone: {
        pattern: /^[\+]?[1-9][\d]{0,15}$/,
        custom: (value: string) => {
            // Remove common phone separators for validation
            const cleaned = value.replace(/[\s\-\(\)\.]/g, '');
            if (cleaned.length < 10 || cleaned.length > 15) {
                return 'Phone number must be between 10 and 15 digits';
            }
            return null;
        }
    },

    url: {
        pattern: /^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)$/,
        custom: (value: string) => {
            try {
                const url = new URL(value);
                // Only allow HTTP and HTTPS protocols
                if (!['http:', 'https:'].includes(url.protocol)) {
                    return 'Only HTTP and HTTPS URLs are allowed';
                }
                return null;
            } catch {
                return 'Invalid URL format';
            }
        }
    },

    name: {
        pattern: /^[a-zA-ZÀ-ÿ\s\-'\.]+$/,
        minLength: 1,
        maxLength: 100,
        custom: (value: string) => {
            // Check for suspicious patterns in names
            if (/[<>{}[\]\\\/]/.test(value)) {
                return 'Name contains invalid characters';
            }
            return null;
        }
    },

    text: {
        maxLength: 1000,
        sanitize: true,
        allowHtml: false
    },

    richText: {
        maxLength: 5000,
        sanitize: true,
        allowHtml: true
    }
};

/**
 * Form security middleware
 */
export class FormSecurityMiddleware {
    private static rateLimiter = new Map<string, { count: number; timestamp: number }>();
    private static readonly RATE_LIMIT = 10; // requests per minute
    private static readonly RATE_WINDOW = 60000; // 1 minute

    /**
     * Check if form submission is rate limited
     */
    static checkRateLimit(identifier: string): boolean {
        const now = Date.now();
        const key = identifier;
        const record = this.rateLimiter.get(key);

        if (!record) {
            this.rateLimiter.set(key, { count: 1, timestamp: now });
            return true;
        }

        // Reset if window expired
        if (now - record.timestamp > this.RATE_WINDOW) {
            this.rateLimiter.set(key, { count: 1, timestamp: now });
            return true;
        }

        // Check rate limit
        if (record.count >= this.RATE_LIMIT) {
            return false;
        }

        // Increment count
        record.count++;
        return true;
    }

    /**
     * Clean up expired rate limit records
     */
    static cleanupRateLimit(): void {
        const now = Date.now();
        this.rateLimiter.forEach((record, key) => {
            if (now - record.timestamp > this.RATE_WINDOW) {
                this.rateLimiter.delete(key);
            }
        });
    }

    /**
     * Log security event
     */
    static logSecurityEvent(event: {
        type: 'validation_failed' | 'injection_attempt' | 'rate_limit_exceeded';
        field?: string;
        value?: string;
        ip?: string;
        userAgent?: string;
        timestamp: Date;
    }): void {
        // In production, send to monitoring service
        if (process.env.NODE_ENV === 'production') {
            // Send to logging service (implement based on your setup)
            console.warn('Security event:', event);
        } else {
            console.warn('Security event (dev):', event);
        }
    }
}

export default SecurityValidator;
