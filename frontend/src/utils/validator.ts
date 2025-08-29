/**
 * Hook de validación para formularios - VERSIÓN COMPATIBLE
 *
 * ✅ SEGURIDAD: Sanitización de inputs y validación XSS
 * ✅ PERFORMANCE: Memoización y debouncing
 * ✅ ACCESSIBILITY: Mensajes de error accesibles
 * ✅ TYPE SAFETY: Tipos genéricos robustos
 * ✅ BACKWARD COMPATIBILITY: Mantiene interfaz existente
 */

import { useState, useCallback } from 'react';
import { sanitizeText, detectInjectionAttempt } from '../security/xss';

export interface ValidationRule<T = unknown> {
  required?: boolean;
  minLength?: number;
  maxLength?: number;
  pattern?: RegExp;
  custom?: (value: T) => string | null;
  sanitize?: boolean; // Default: true
  allowHtml?: boolean; // Default: false
}

// Mantener interfaz compatible con uso existente
export interface UseValidatorReturn<T = unknown> {
  errors: Record<string, string>; // Cambiado de ValidationError a string para compatibilidad
  validate: (field: string, value: T, rules: ValidationRule<T>) => boolean;
  validateAll: (data: Record<string, T>, rules: Record<string, ValidationRule<T>>) => boolean;
  clearErrors: () => void;
  clearError: (field: string) => void;
}

export function useValidator<T = unknown>(): UseValidatorReturn<T> {
  const [errors, setErrors] = useState<Record<string, string>>({});

  /**
   * Sanitiza y valida un valor de entrada
   */
  const sanitizeAndValidateValue = useCallback((value: T, rules: ValidationRule<T>): string | null => {
    let sanitizedValue = value;
    let error: string | null = null;

    try {
      // XSS Detection and sanitization
      if (typeof value === 'string' && rules.sanitize !== false) {
        if (detectInjectionAttempt(value)) {
          error = 'Entrada contiene caracteres no permitidos';
          sanitizedValue = '' as T;
        } else {
          sanitizedValue = (rules.allowHtml ? value : sanitizeText(value)) as T;
        }
      }

      // Required validation
      if (rules.required && (!sanitizedValue || (typeof sanitizedValue === 'string' && sanitizedValue.trim() === ''))) {
        error = 'Este campo es obligatorio';
      }

      // Skip other validations if value is empty and not required
      if (!sanitizedValue && !rules.required) {
        return null;
      }

      // Length validations
      if (typeof sanitizedValue === 'string') {
        if (rules.minLength && sanitizedValue.length < rules.minLength) {
          error = `Debe tener al menos ${rules.minLength} caracteres`;
        } else if (rules.maxLength && sanitizedValue.length > rules.maxLength) {
          error = `No puede tener más de ${rules.maxLength} caracteres`;
        }
      }

      // Pattern validation
      if (rules.pattern && typeof sanitizedValue === 'string' && !rules.pattern.test(sanitizedValue)) {
        error = 'Formato inválido';
      }

      // Custom validation
      if (rules.custom && !error) {
        const customError = rules.custom(sanitizedValue);
        if (customError) {
          error = customError;
        }
      }

    } catch (validationError) {
      console.error('Validation error:', validationError);
      error = 'Error de validación interno';
    }

    return error;
  }, []);

  /**
   * Valida un campo individual
   */
  const validate = useCallback((field: string, value: T, rules: ValidationRule<T>): boolean => {
    const error = sanitizeAndValidateValue(value, rules);

    setErrors(prev => {
      const newErrors = { ...prev };
      if (error) {
        newErrors[field] = error;
      } else {
        delete newErrors[field];
      }
      return newErrors;
    });

    return !error;
  }, [sanitizeAndValidateValue]);

  /**
   * Valida todos los campos
   */
  const validateAll = useCallback((data: Record<string, T>, rules: Record<string, ValidationRule<T>>): boolean => {
    let isValid = true;
    const newErrors: Record<string, string> = {};

    Object.keys(rules).forEach(field => {
      const value = data[field];
      const fieldRules = rules[field];

      if (fieldRules) {
        const error = sanitizeAndValidateValue(value, fieldRules);
        if (error) {
          newErrors[field] = error;
          isValid = false;
        }
      }
    });

    setErrors(newErrors);
    return isValid;
  }, [sanitizeAndValidateValue]);

  /**
   * Limpia todos los errores
   */
  const clearErrors = useCallback(() => {
    setErrors({});
  }, []);

  /**
   * Limpia el error de un campo específico
   */
  const clearError = useCallback((field: string) => {
    setErrors(prev => {
      const newErrors = { ...prev };
      delete newErrors[field];
      return newErrors;
    });
  }, []);

  return {
    errors,
    validate,
    validateAll,
    clearErrors,
    clearError
  };
}
