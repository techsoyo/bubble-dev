/**
 * Centralized Validation System
 * 
 * Provides comprehensive form validation, input sanitization, and business logic
 * validation for production-ready applications.
 * 
 * @package Validation
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import { z } from 'zod';
import { sanitizeText, sanitizeEmail, detectInjectionAttempt } from '../security/xss';

/**
 * Base validation rules
 */
export const ValidationRules = {
    // Text validation
    text: (min = 1, max = 255) => z
        .string()
        .min(min, `Minimum ${min} characters required`)
        .max(max, `Maximum ${max} characters allowed`)
        .refine(
            (val) => !detectInjectionAttempt(val),
            'Invalid characters detected'
        )
        .transform(sanitizeText),

    // Email validation
    email: z
        .string()
        .email('Invalid email format')
        .max(255, 'Email too long')
        .transform(sanitizeEmail)
        .refine((val) => val.length > 0, 'Invalid email'),

    // Password validation
    password: z
        .string()
        .min(8, 'Password must be at least 8 characters')
        .max(128, 'Password too long')
        .regex(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/,
            'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character'),

    // Phone validation
    phone: z
        .string()
        .regex(/^\+?[1-9]\d{1,14}$/, 'Invalid phone number format')
        .transform(sanitizeText),

    // URL validation
    url: z
        .string()
        .url('Invalid URL format')
        .max(2048, 'URL too long'),

    // Number validation
    number: (min?: number, max?: number) => {
        let schema = z.number();
        if (min !== undefined) schema = schema.min(min);
        if (max !== undefined) schema = schema.max(max);
        return schema;
    },

    // Date validation
    date: z
        .string()
        .regex(/^\d{4}-\d{2}-\d{2}$/, 'Invalid date format (YYYY-MM-DD)')
        .refine((date) => !isNaN(Date.parse(date)), 'Invalid date'),

    // File validation
    file: (maxSize = 5 * 1024 * 1024, allowedTypes: string[] = []) => z
        .instanceof(File)
        .refine((file) => file.size <= maxSize, `File size must be less than ${maxSize / 1024 / 1024}MB`)
        .refine(
            (file) => allowedTypes.length === 0 || allowedTypes.includes(file.type),
            `Allowed file types: ${allowedTypes.join(', ')}`
        ),

    // Selection validation
    select: <T extends readonly [string, ...string[]]>(options: T) => z
        .enum(options)
        .refine((val) => options.includes(val), 'Invalid selection'),

    // Boolean validation
    boolean: z.boolean(),

    // Array validation
    array: <T>(schema: z.ZodSchema<T>, min = 0, max = 100) => z
        .array(schema)
        .min(min, `At least ${min} items required`)
        .max(max, `Maximum ${max} items allowed`),
};

/**
 * Job application form validation schema
 */
export const JobApplicationSchema = z.object({
    firstName: ValidationRules.text(2, 50),
    lastName: ValidationRules.text(2, 50),
    email: ValidationRules.email,
    phone: ValidationRules.phone,
    coverLetter: ValidationRules.text(50, 2000),
    experience: ValidationRules.number(0, 50),
    skills: ValidationRules.array(ValidationRules.text(2, 30), 1, 20),
    portfolio: ValidationRules.url.optional(),
    availability: ValidationRules.select(['immediate', 'two-weeks', 'month', 'flexible'] as const),
    salary: ValidationRules.number(0, 1000000).optional(),
    resume: ValidationRules.file(10 * 1024 * 1024, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']),
});

/**
 * User registration validation schema
 */
export const UserRegistrationSchema = z.object({
    firstName: ValidationRules.text(2, 50),
    lastName: ValidationRules.text(2, 50),
    email: ValidationRules.email,
    password: ValidationRules.password,
    confirmPassword: ValidationRules.text(),
    phone: ValidationRules.phone.optional(),
    agreeToTerms: z.boolean().refine(val => val === true, 'You must agree to the terms'),
}).refine((data) => data.password === data.confirmPassword, {
    message: "Passwords don't match",
    path: ["confirmPassword"],
});

/**
 * User login validation schema
 */
export const UserLoginSchema = z.object({
    email: ValidationRules.email,
    password: ValidationRules.text(1, 128),
    rememberMe: ValidationRules.boolean.optional(),
});

/**
 * Profile update validation schema
 */
export const ProfileUpdateSchema = z.object({
    firstName: ValidationRules.text(2, 50),
    lastName: ValidationRules.text(2, 50),
    phone: ValidationRules.phone.optional(),
    bio: ValidationRules.text(0, 1000).optional(),
    website: ValidationRules.url.optional(),
    linkedin: ValidationRules.url.optional(),
    github: ValidationRules.url.optional(),
    skills: ValidationRules.array(ValidationRules.text(2, 30), 0, 50),
    experience: ValidationRules.number(0, 50).optional(),
});

/**
 * Job posting validation schema
 */
export const JobPostingSchema = z.object({
    title: ValidationRules.text(5, 100),
    description: ValidationRules.text(50, 5000),
    requirements: ValidationRules.array(ValidationRules.text(5, 200), 1, 20),
    benefits: ValidationRules.array(ValidationRules.text(5, 200), 0, 15),
    salaryMin: ValidationRules.number(0, 1000000).optional(),
    salaryMax: ValidationRules.number(0, 1000000).optional(),
    location: ValidationRules.text(2, 100),
    remote: ValidationRules.boolean,
    type: ValidationRules.select(['full-time', 'part-time', 'contract', 'internship'] as const),
    department: ValidationRules.text(2, 50),
    experienceLevel: ValidationRules.select(['entry', 'mid', 'senior', 'executive'] as const),
    skills: ValidationRules.array(ValidationRules.text(2, 30), 1, 30),
}).refine((data) => {
    if (data.salaryMin && data.salaryMax) {
        return data.salaryMin <= data.salaryMax;
    }
    return true;
}, {
    message: "Minimum salary cannot be greater than maximum salary",
    path: ["salaryMax"],
});

/**
 * Contact form validation schema
 */
export const ContactFormSchema = z.object({
    name: ValidationRules.text(2, 100),
    email: ValidationRules.email,
    subject: ValidationRules.text(5, 200),
    message: ValidationRules.text(20, 2000),
    company: ValidationRules.text(0, 100).optional(),
});

/**
 * Custom validation hook
 */
export interface ValidationError {
    field: string;
    message: string;
}

export interface ValidationResult<T> {
    success: boolean;
    data?: T;
    errors: ValidationError[];
}

/**
 * Generic validation function
 */
export function validateData<T>(
    schema: z.ZodSchema<T>,
    data: unknown
): ValidationResult<T> {
    try {
        const result = schema.parse(data);
        return {
            success: true,
            data: result,
            errors: [],
        };
    } catch (error) {
        if (error instanceof z.ZodError) {
            return {
                success: false,
                errors: error.errors.map(err => ({
                    field: err.path.join('.'),
                    message: err.message,
                })),
            };
        }

        return {
            success: false,
            errors: [{ field: 'general', message: 'Validation failed' }],
        };
    }
}

/**
 * Async validation function for server-side checks
 */
export async function validateDataAsync<T>(
    schema: z.ZodSchema<T>,
    data: unknown,
    customValidators?: Array<(data: T) => Promise<string | null>>
): Promise<ValidationResult<T>> {
    // First, run sync validation
    const syncResult = validateData(schema, data);
    if (!syncResult.success) {
        return syncResult;
    }

    // Run custom async validators
    if (customValidators && syncResult.data) {
        const asyncErrors: ValidationError[] = [];

        for (const validator of customValidators) {
            try {
                const error = await validator(syncResult.data);
                if (error) {
                    asyncErrors.push({ field: 'async', message: error });
                }
            } catch (error) {
                asyncErrors.push({
                    field: 'async',
                    message: error instanceof Error ? error.message : 'Validation error'
                });
            }
        }

        if (asyncErrors.length > 0) {
            return {
                success: false,
                errors: asyncErrors,
            };
        }
    }

    return syncResult;
}

/**
 * Business logic validators
 */
export const BusinessValidators = {
    // Check if email is already registered
    emailAvailable: async (email: string): Promise<string | null> => {
        try {
            const response = await fetch(`/api/users/check-email?email=${encodeURIComponent(email)}`);
            const data = await response.json();
            return data.available ? null : 'Email is already registered';
        } catch {
            return 'Unable to verify email availability';
        }
    },

    // Check if username is available
    usernameAvailable: async (username: string): Promise<string | null> => {
        try {
            const response = await fetch(`/api/users/check-username?username=${encodeURIComponent(username)}`);
            const data = await response.json();
            return data.available ? null : 'Username is already taken';
        } catch {
            return 'Unable to verify username availability';
        }
    },

    // Validate file content (beyond basic file validation)
    validateFileContent: async (file: File): Promise<string | null> => {
        // For PDFs, check if they're valid
        if (file.type === 'application/pdf') {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const content = e.target?.result as ArrayBuffer;
                    const uint8Array = new Uint8Array(content);
                    const signature = Array.from(uint8Array.slice(0, 4)).map(byte => byte.toString(16)).join('');

                    if (signature === '25504446') { // PDF signature
                        resolve(null);
                    } else {
                        resolve('Invalid PDF file');
                    }
                };
                reader.onerror = () => resolve('Unable to read file');
                reader.readAsArrayBuffer(file);
            });
        }

        return null;
    },
};

/**
 * Form field validation state
 */
export interface FieldValidationState {
    value: string;
    error: string | null;
    touched: boolean;
    validating: boolean;
}

/**
 * Form validation state manager
 */
export class FormValidator<T extends Record<string, unknown>> {
    private schema: z.ZodSchema<T>;
    private customValidators: Record<string, (value: unknown) => Promise<string | null>> = {};

    constructor(schema: z.ZodSchema<T>) {
        this.schema = schema;
    }

    addCustomValidator(field: string, validator: (value: unknown) => Promise<string | null>): void {
        this.customValidators[field] = validator;
    }

    async validateField(field: string, value: unknown): Promise<string | null> {
        try {
            // Extract field schema (simplified approach)
            const fieldSchema = (this.schema as any).shape?.[field];
            if (fieldSchema) {
                fieldSchema.parse(value);
            }

            // Run custom validator if available
            const customValidator = this.customValidators[field];
            if (customValidator) {
                return await customValidator(value);
            }

            return null;
        } catch (error) {
            if (error instanceof z.ZodError) {
                return error.errors[0]?.message || 'Validation error';
            }
            return 'Validation error';
        }
    }

    async validateAll(data: T): Promise<ValidationResult<T>> {
        return validateData(this.schema, data);
    }
}

/**
 * Real-time validation debouncer
 */
export class ValidationDebouncer {
    private timers: Record<string, NodeJS.Timeout> = {};

    debounce<T extends unknown[]>(
        key: string,
        fn: (...args: T) => void,
        delay = 300
    ): (...args: T) => void {
        return (...args: T) => {
            if (this.timers[key]) {
                clearTimeout(this.timers[key]);
            }

            this.timers[key] = setTimeout(() => {
                fn(...args);
                delete this.timers[key];
            }, delay);
        };
    }

    clear(key?: string): void {
        if (key) {
            if (this.timers[key]) {
                clearTimeout(this.timers[key]);
                delete this.timers[key];
            }
        } else {
            Object.values(this.timers).forEach(timer => clearTimeout(timer));
            this.timers = {};
        }
    }
}

// Export commonly used validation schemas
export type JobApplicationData = z.infer<typeof JobApplicationSchema>;
export type UserRegistrationData = z.infer<typeof UserRegistrationSchema>;
export type UserLoginData = z.infer<typeof UserLoginSchema>;
export type ProfileUpdateData = z.infer<typeof ProfileUpdateSchema>;
export type JobPostingData = z.infer<typeof JobPostingSchema>;
export type ContactFormData = z.infer<typeof ContactFormSchema>;
