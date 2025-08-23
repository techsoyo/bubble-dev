/**
 * Secure Authentication System - Production Ready
 * Sistema basado en cookies HTTP-only para candidatos
 */

import { InputSanitizer } from './secureInputValidator';

export interface User {
  id: string;
  email: string;
  name?: string;
  role?: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
  rememberMe?: boolean;
}

import { env } from '@/config/env';

export interface AuthResponse {
  success: boolean;
  user?: User;
  message?: string;
}

export class SecureAuthManager {
  private static readonly API_BASE_URL = env.API_BASE_URL;
  private static currentUser: User | null = null;

  /**
   * Verificar sesión actual (cookies HTTP-only)
   */
  static async verifySession(): Promise<{ isValid: boolean; user?: User }> {
    try {
      // Usar el archivo PHP directo que sí funciona
      const response = await fetch(`${this.API_BASE_URL}/auth/verify-session.php`, {
        method: 'GET',
        credentials: 'include'
      });

      if (response.ok) {
        const data = await response.json();

        if (data.success && data.user) {
          this.currentUser = data.user;
          return {
            isValid: true,
            user: data.user
          };
        }
      }

      return { isValid: false };
    } catch (error) {
      console.error('Session verification error occurred');
      return { isValid: false };
    }
  }

  /**
   * Login específico para candidatos
   */
  static async candidateLogin(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      const sanitizedCredentials = {
        email: InputSanitizer.sanitizeEmail(credentials.email),
        password: credentials.password,
      };

      const response = await fetch(`${this.API_BASE_URL}/auth/candidate-login.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(sanitizedCredentials)
      });

      const data = await response.json();

      if (response.ok && data.success) {
        this.currentUser = data.user;

        return {
          success: true,
          user: data.user,
          message: data.message
        };
      }

      return {
        success: false,
        message: data.message || 'Candidate login failed'
      };

    } catch (error) {
      console.error('Candidate login error occurred');
      return {
        success: false,
        message: 'Network error occurred'
      };
    }
  }

  /**
   * Login genérico (usa candidateLogin por defecto)
   */
  static async login(credentials: LoginCredentials): Promise<AuthResponse> {
    return this.candidateLogin(credentials);
  }

  /**
   * Login específico para staff
   */
  static async staffLogin(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      const sanitizedCredentials = {
        email: InputSanitizer.sanitizeEmail(credentials.email),
        password: credentials.password
      };

      const response = await fetch(`${this.API_BASE_URL}/auth/staff-login.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(sanitizedCredentials)
      });

      const data = await response.json();

      if (response.ok && data.success) {
        this.currentUser = data.user;

        return {
          success: true,
          user: data.user,
          message: data.message
        };
      }

      return {
        success: false,
        message: data.message || 'Staff login failed'
      };

    } catch (error) {
      console.error('Staff login error occurred');
      return {
        success: false,
        message: 'Network error occurred'
      };
    }
  }

  /**
   * Logout seguro
   */
  static async logout(): Promise<boolean> {
    try {
      await fetch(`${this.API_BASE_URL}/auth/logout.php`, {
        method: 'POST',
        credentials: 'include'
      });

      this.currentUser = null;
      return true;
    } catch (error) {
      console.error('Logout error occurred');
      return false;
    }
  }

  /**
   * Obtener usuario actual
   */
  static getCurrentUser(): User | null {
    return this.currentUser;
  }

  /**
   * Verificar si usuario está autenticado
   */
  static isAuthenticated(): boolean {
    return this.currentUser !== null;
  }

  /**
   * Limpiar sesión local
   */
  static clearSession(): void {
    this.currentUser = null;
  }
}
