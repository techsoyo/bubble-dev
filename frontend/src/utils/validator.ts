/**
 * Hook de validación para formularios
 */
import { useState, useCallback } from 'react';

export interface ValidationRule<T = unknown> {
  required?: boolean;
  minLength?: number;
  maxLength?: number;
  pattern?: RegExp;
  custom?: (value: T) => string | null;
}

export interface ValidationErrors {
  [key: string]: string;
}

export interface UseValidatorReturn<T = unknown> {
  errors: ValidationErrors;
  validate: (field: string, value: T, rules: ValidationRule<T>) => boolean;
  validateAll: (data: Record<string, T>, rules: Record<string, ValidationRule<T>>) => boolean;
  clearErrors: () => void;
  clearError: (field: string) => void;
}

export function useValidator<T = unknown>(): UseValidatorReturn<T> {
  const [errors, setErrors] = useState<ValidationErrors>({});

  const validate = useCallback((field: string, value: T, rules: ValidationRule<T>): boolean => {
    let error: string | null = null;

    if (rules.required && (!value || (typeof value === 'string' && value.trim() === ''))) {
      error = 'Este campo es requerido';
    } else if (value && rules.minLength && String(value).length < rules.minLength) {
      error = `Debe tener al menos ${rules.minLength} caracteres`;
    } else if (value && rules.maxLength && String(value).length > rules.maxLength) {
      error = `No puede tener más de ${rules.maxLength} caracteres`;
    } else if (value && rules.pattern && !rules.pattern.test(String(value))) {
      error = 'Formato inválido';
    } else if (rules.custom) {
      error = rules.custom(value);
    }

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
  }, []);

  const validateAll = useCallback((data: Record<string, T>, rules: Record<string, ValidationRule<T>>): boolean => {
    let isValid = true;
    const newErrors: ValidationErrors = {};

    Object.keys(rules).forEach(field => {
      const value = data[field];
      const fieldRules = rules[field];

      if (!validate(field, value, fieldRules)) {
        isValid = false;
      }
    });

    return isValid;
  }, [validate]);

  const clearErrors = useCallback(() => {
    setErrors({});
  }, []);

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
