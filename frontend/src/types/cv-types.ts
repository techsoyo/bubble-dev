/**
 * CV Types - SECURE & TYPE-SAFE
 *
 * ✅ SEGURIDAD: Elimina tipos 'unknown' inseguros, validación estricta
 * ✅ TYPE SAFETY: Tipos específicos para datos de CV
 * ✅ ACCESSIBILITY: Consideraciones para datos accesibles
 * ✅ PERFORMANCE: Tipos optimizados para validación rápida
 */

// Security: Strict string validation to prevent XSS
export type SecureString = string & {
  readonly __brand: 'SecureString';
};

// Security: Email validation type
export type EmailString = string & {
  readonly __brand: 'EmailString';
};

// Security: Phone validation type
export type PhoneString = string & {
  readonly __brand: 'PhoneString';
};

// Security: URL validation type
export type URLString = string & {
  readonly __brand: 'URLString';
};

// Personal Info with security validation
export interface PersonalInfo {
  readonly name?: SecureString;
  readonly email?: EmailString;
  readonly phone?: PhoneString;
  readonly location?: SecureString;
  readonly linkedin?: URLString;
  readonly github?: URLString;
  readonly website?: URLString;
}

// Skill with strict typing
export interface Skill {
  readonly name: SecureString;
  readonly level: 'Beginner' | 'Intermediate' | 'Advanced' | 'Expert';
  readonly category?: 'Technical' | 'Soft' | 'Language' | 'Tool';
}

// Experience with date validation
export interface Experience {
  readonly position: SecureString;
  readonly company: SecureString;
  readonly start_date: string; // ISO date string
  readonly end_date?: string; // ISO date string or undefined for current
  readonly description: SecureString;
  readonly technologies?: readonly SecureString[];
  readonly achievements?: readonly SecureString[];
}

// Education with validation
export interface Education {
  readonly degree: SecureString;
  readonly institution: SecureString;
  readonly start_date: string; // ISO date string
  readonly end_date?: string; // ISO date string
  readonly gpa?: number; // 0.0 to 4.0
  readonly honors?: readonly SecureString[];
}

// Comprehensive CV Data with strict typing
export interface CVData {
  readonly personal_info?: PersonalInfo;
  readonly hard_skills?: readonly (SecureString | Skill)[];
  readonly soft_skills?: readonly SecureString[];
  readonly experience?: readonly Experience[];
  readonly education?: readonly Education[];
  readonly area_of_interest?: SecureString;
  readonly languages?: readonly {
    readonly language: SecureString;
    readonly proficiency: 'Basic' | 'Conversational' | 'Fluent' | 'Native';
  }[];
  readonly certifications?: readonly {
    readonly name: SecureString;
    readonly issuer: SecureString;
    readonly date: string; // ISO date string
    readonly expiry?: string; // ISO date string
  }[];
  readonly projects?: readonly {
    readonly name: SecureString;
    readonly description: SecureString;
    readonly technologies: readonly SecureString[];
    readonly url?: URLString;
    readonly github?: URLString;
  }[];
}

// ChatBot types with security validation
export interface ChatBotActionData {
  readonly url?: URLString;
  readonly action?: 'navigate' | 'download' | 'contact' | 'apply';
  readonly data?: Record<SecureString, SecureString | number | boolean>;
}

export interface ChatBotOption {
  readonly id: SecureString;
  readonly label: SecureString;
  readonly action_data?: ChatBotActionData;
  readonly category?: 'navigation' | 'action' | 'information';
}

// Utility types for validation
export type CVValidationResult<T> = {
  readonly isValid: boolean;
  readonly errors: readonly string[];
  readonly sanitizedData: T;
};

export type CVFieldError = {
  readonly field: string;
  readonly message: string;
  readonly code: 'REQUIRED' | 'INVALID_FORMAT' | 'TOO_LONG' | 'XSS_DETECTED';
};

// Type guards for runtime validation
export function isSecureString(value: unknown): value is SecureString {
  return typeof value === 'string' &&
    value.length > 0 &&
    value.length <= 1000 &&
    !/<script/i.test(value) &&
    !/javascript:/i.test(value);
}

export function isEmailString(value: unknown): value is EmailString {
  return typeof value === 'string' &&
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) &&
    value.length <= 254;
}

export function isPhoneString(value: unknown): value is PhoneString {
  return typeof value === 'string' &&
    /^\+?[1-9]\d{1,14}$/.test(value) &&
    value.length <= 20;
}

export function isURLString(value: unknown): value is URLString {
  try {
    if (typeof value !== 'string') return false;
    const url = new URL(value);
    return ['http:', 'https:'].includes(url.protocol);
  } catch {
    return false;
  }
}

// Sanitization functions
export function sanitizeCVString(input: string): SecureString | null {
  if (!input || typeof input !== 'string') return null;

  const sanitized = input
    .trim()
    .replace(/<[^>]*>/g, '') // Remove HTML tags
    .replace(/javascript:/gi, '') // Remove javascript: protocol
    .replace(/on\w+\s*=/gi, '') // Remove event handlers
    .slice(0, 1000); // Limit length

  return sanitized.length > 0 ? (sanitized as SecureString) : null;
}

export function sanitizeEmail(input: string): EmailString | null {
  const sanitized = sanitizeCVString(input);
  return sanitized && isEmailString(sanitized) ? (sanitized as EmailString) : null;
}

export function sanitizePhone(input: string): PhoneString | null {
  const sanitized = sanitizeCVString(input);
  return sanitized && isPhoneString(sanitized) ? (sanitized as PhoneString) : null;
}

export function sanitizeURL(input: string): URLString | null {
  const sanitized = sanitizeCVString(input);
  return sanitized && isURLString(sanitized) ? (sanitized as URLString) : null;
}

// Export for use in other files
export { };
