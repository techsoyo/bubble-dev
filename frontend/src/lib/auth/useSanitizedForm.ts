/**
 * Hook personalizado para sanitización de formularios
 * Proporciona una forma fácil de validar y sanitizar inputs
 */

import { useState, useCallback } from 'react';
import { InputSanitizer } from './secureInputValidator';

export interface ValidationErrors {
  [key: string]: string;
}

export function useSanitizedForm<T extends Record<string, unknown>>(initialState: T) {
  const [formData, setFormData] = useState<T>(initialState);
  const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});
  const [touchedFields, setTouchedFields] = useState<Record<string, boolean>>({});

  // Actualizar un campo con sanitización automática
  const updateField = useCallback((field: keyof T, value: T[keyof T]) => {
    // Marcar el campo como tocado
    setTouchedFields(prev => ({ ...prev, [field]: true }));

    // Validar y sanitizar el valor
    let sanitizedValue = value;
    let error: string | undefined;

    if (typeof value === 'string') {
      // Aplicar sanitización específica según el nombre del campo
      if (field === 'email' || String(field).includes('email')) {
        const validation = InputSanitizer.validateCriticalField(value, 'email');
        sanitizedValue = (validation.sanitized || '') as T[keyof T];
        error = validation.isValid ? undefined : validation.message;
      }
      else if (field === 'password' || String(field).includes('password')) {
        const validation = InputSanitizer.validateCriticalField(value, 'password');
        sanitizedValue = (validation.sanitized || '') as T[keyof T];
        error = validation.isValid ? undefined : validation.message;
      }
      else if (field === 'username' || field === 'name' ||
        String(field).includes('name') || String(field).includes('user')) {
        const validation = InputSanitizer.validateCriticalField(value, 'username');
        sanitizedValue = (validation.sanitized || '') as T[keyof T];
        error = validation.isValid ? undefined : validation.message;
      }
      else {
        // Sanitización genérica para otros campos de texto
        sanitizedValue = InputSanitizer.sanitizeText(value) as T[keyof T];
      }
    }

    // Actualizar errores
    setValidationErrors(prev => {
      const newErrors = { ...prev };
      if (error) {
        newErrors[String(field)] = error;
      } else {
        delete newErrors[String(field)];
      }
      return newErrors;
    });

    // Actualizar datos del formulario
    setFormData(prev => ({ ...prev, [field]: sanitizedValue }));
  }, []);

  // Validar todos los campos
  const validateAll = useCallback(() => {
    const newErrors: ValidationErrors = {};
    let isValid = true;

    // Marcar todos los campos como tocados
    const allTouched: Record<string, boolean> = {};

    // Validar cada campo
    Object.keys(formData).forEach(key => {
      allTouched[key] = true;
      const value = formData[key];

      if (typeof value === 'string') {
        if (key === 'email' || key.includes('email')) {
          const validation = InputSanitizer.validateCriticalField(value, 'email');
          if (!validation.isValid) {
            newErrors[key] = validation.message || 'Invalid email';
            isValid = false;
          }
        }
        else if (key === 'password' || key.includes('password')) {
          const validation = InputSanitizer.validateCriticalField(value, 'password');
          if (!validation.isValid) {
            newErrors[key] = validation.message || 'Invalid password';
            isValid = false;
          }
        }
        else if (key === 'username' || key === 'name' || key.includes('name') || key.includes('user')) {
          const validation = InputSanitizer.validateCriticalField(value, 'username');
          if (!validation.isValid) {
            newErrors[key] = validation.message || 'Invalid name';
            isValid = false;
          }
        }
        else if (value.trim() === '' && !key.includes('optional')) {
          newErrors[key] = 'This field is required';
          isValid = false;
        }
      }
    });

    setTouchedFields(allTouched);
    setValidationErrors(newErrors);
    return isValid;
  }, [formData]);

  // Obtener todos los datos sanitizados
  const getSanitizedData = useCallback(() => {
    const sanitizedData: Record<string, any> = {};

    Object.entries(formData).forEach(([key, value]) => {
      if (typeof value === 'string') {
        if (key === 'email' || key.includes('email')) {
          sanitizedData[key] = InputSanitizer.sanitizeEmail(value);
        }
        else if (key === 'password' || key.includes('password')) {
          sanitizedData[key] = value; // No sanitizamos passwords
        }
        else if (key === 'username' || key === 'name' || key.includes('name') || key.includes('user')) {
          sanitizedData[key] = InputSanitizer.sanitizeName(value);
        }
        else {
          sanitizedData[key] = InputSanitizer.sanitizeText(value);
        }
      } else {
        sanitizedData[key] = value;
      }
    });

    return sanitizedData as T;
  }, [formData]);

  // Verificar si hay errores
  const hasErrors = Object.keys(validationErrors).length > 0;

  // Resetear el formulario
  const resetForm = useCallback(() => {
    setFormData(initialState);
    setValidationErrors({});
    setTouchedFields({});
  }, [initialState]);

  return {
    formData,
    validationErrors,
    touchedFields,
    updateField,
    validateAll,
    getSanitizedData,
    hasErrors,
    resetForm
  };
}
