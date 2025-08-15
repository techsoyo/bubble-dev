// src/hooks/useFormErrors.ts
import { useState, useCallback } from 'react';

export interface FormErrors {
  [key: string]: string;
}

export interface UseFormErrorsReturn {
  errors: FormErrors;
  setError: (field: string, message: string) => void;
  clearError: (field: string) => void;
  clearAllErrors: () => void;
  hasError: (field: string) => boolean;
  hasAnyError: () => boolean;
}

export function useFormErrors(initialErrors: FormErrors = {}): UseFormErrorsReturn {
  const [errors, setErrors] = useState<FormErrors>(initialErrors);

  const setError = useCallback((field: string, message: string) => {
    setErrors(prev => ({
      ...prev,
      [field]: message
    }));
  }, []);

  const clearError = useCallback((field: string) => {
    setErrors(prev => {
      const { [field]: removed, ...rest } = prev;
      return rest;
    });
  }, []);

  const clearAllErrors = useCallback(() => {
    setErrors({});
  }, []);

  const hasError = useCallback((field: string) => {
    return field in errors && errors[field] !== '';
  }, [errors]);

  const hasAnyError = useCallback(() => {
    return Object.keys(errors).some(key => errors[key] !== '');
  }, [errors]);

  return {
    errors,
    setError,
    clearError,
    clearAllErrors,
    hasError,
    hasAnyError
  };
}