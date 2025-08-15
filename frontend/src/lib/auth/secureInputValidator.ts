/**
 * Input Sanitization System - Production Ready
 * Sanitiza y valida entradas de usuario para prevenir ataques XSS e inyección SQL
 */

import DOMPurify from 'dompurify';

export class InputSanitizer {
  /**
   * Sanitizar texto general (previene XSS)
   */
  static sanitizeText(input: string): string {
    if (!input || typeof input !== 'string') {
      return '';
    }

    // Usar DOMPurify para sanitizar contenido HTML/XSS
    return DOMPurify.sanitize(input, {
      ALLOWED_TAGS: [], // No permitir tags HTML
      ALLOWED_ATTR: [] // No permitir atributos
    }).trim();
  }

  /**
   * Sanitizar email
   */
  static sanitizeEmail(email: string): string {
    if (!email || typeof email !== 'string') {
      return '';
    }

    // Remover espacios y convertir a minúsculas
    const cleaned = email.trim().toLowerCase();

    // Sanitizar para prevenir XSS
    const sanitized = this.sanitizeText(cleaned);

    // Validar formato básico de email
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    return emailRegex.test(sanitized) ? sanitized : '';
  }

  /**
   * Sanitizar nombre/texto de usuario
   */
  static sanitizeName(name: string): string {
    if (!name || typeof name !== 'string') {
      return '';
    }

    // Permitir solo letras, espacios, guiones y acentos
    const cleaned = name.replace(/[^a-zA-ZÀ-ÿ\u0100-\u017F\s\-']/g, '').trim();

    // Capitalizar primera letra de cada palabra
    return cleaned.replace(/\b\w/g, l => l.toUpperCase());
  }

  /**
   * Sanitizar input para búsquedas (previene SQL injection)
   */
  static sanitizeSearchTerm(search: string): string {
    if (!search || typeof search !== 'string') {
      return '';
    }

    // Remover caracteres peligrosos para SQL
    const dangerous = /['";\\<>{}]/g;
    const cleaned = search.replace(dangerous, '').trim();

    // Limitar longitud
    return cleaned.substring(0, 100);
  }

  /**
   * Validar y sanitizar objeto JSON
   */
  static sanitizeFormData<T extends Record<string, unknown>>(data: T): Partial<T> {
    const sanitized: Partial<T> = {};

    for (const [key, value] of Object.entries(data)) {
      const cleanKey = this.sanitizeText(key) as keyof T;

      if (typeof value === 'string') {
        sanitized[cleanKey] = this.sanitizeText(value) as unknown as T[keyof T];
      } else if (typeof value === 'number' || typeof value === 'boolean') {
        sanitized[cleanKey] = value as T[keyof T];
      }
      // Ignorar otros tipos por seguridad
    }

    return sanitized;
  }

  /**
   * Rate limiting para formularios (previene spam)
   */
  private static submissionTimes: Map<string, number[]> = new Map();

  static checkRateLimit(identifier: string, maxAttempts: number = 5, timeWindow: number = 300000): boolean {
    const now = Date.now();
    const attempts = this.submissionTimes.get(identifier) || [];

    // Filtrar intentos dentro de la ventana de tiempo
    const recentAttempts = attempts.filter(time => now - time < timeWindow);

    if (recentAttempts.length >= maxAttempts) {
      return false; // Rate limit exceeded
    }

    // Agregar intento actual
    recentAttempts.push(now);
    this.submissionTimes.set(identifier, recentAttempts);

    return true;
  }

  /**
   * Validación adicional para campos críticos
   */
  static validateCriticalField(value: string, fieldType: 'password' | 'email' | 'username'): {
    isValid: boolean;
    message?: string;
    sanitized?: string;
  } {
    if (!value || typeof value !== 'string') {
      return { isValid: false, message: 'Field is required' };
    }

    switch (fieldType) {
      case 'email':
        const email = this.sanitizeEmail(value);
        if (!email) {
          return { isValid: false, message: 'Invalid email format' };
        }
        return { isValid: true, sanitized: email };

      case 'username':
        const username = this.sanitizeName(value);
        if (username.length < 2) {
          return { isValid: false, message: 'Username must be at least 2 characters' };
        }
        if (username.length > 50) {
          return { isValid: false, message: 'Username too long' };
        }
        return { isValid: true, sanitized: username };

      case 'password':
        // No sanitizar passwords, solo validar longitud y complejidad
        if (value.length < 8) {
          return { isValid: false, message: 'Password must be at least 8 characters' };
        }
        if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(value)) {
          return { isValid: false, message: 'Password must contain uppercase, lowercase and number' };
        }
        return { isValid: true, sanitized: value };

      default:
        return { isValid: false, message: 'Unknown field type' };
    }
  }
}
