/**
 * CSRF Protection System
 * Implementa tokens CSRF para prevenir ataques cross-site request forgery
 */

export class CSRFProtection {
  private static readonly CSRF_HEADER = 'X-CSRF-Token';
  private static readonly CSRF_COOKIE = 'csrf_token';
  private static csrfToken: string | null = null;

  /**
   * Generar token CSRF seguro
   */
  private static generateCSRFToken(): string {
    const array = new Uint8Array(32);
    crypto.getRandomValues(array);
    return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
  }

  /**
   * Inicializar protección CSRF
   */
  static async initializeCSRF(): Promise<string> {
    try {
      // Solicitar token CSRF del servidor
      const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
      const response = await fetch(`${apiBaseUrl}/api/auth/csrf-token.php`, {
        method: 'GET',
        credentials: 'include',
      });

      if (response.ok) {
        const data = await response.json();
        this.csrfToken = data.token;
        return data.token;
      }

      // Fallback: generar token local para desarrollo
      if (process.env.NODE_ENV === 'development') {
        this.csrfToken = this.generateCSRFToken();
        return this.csrfToken;
      }

      throw new Error('Failed to initialize CSRF protection');
    } catch (error) {
      console.error('CSRF initialization error occurred');
      // Generar token como último recurso
      this.csrfToken = this.generateCSRFToken();
      return this.csrfToken;
    }
  }

  /**
   * Obtener token CSRF actual
   */
  static getCSRFToken(): string | null {
    return this.csrfToken;
  }

  /**
   * Verificar si el token CSRF es válido
   */
  static async validateCSRFToken(token: string): Promise<boolean> {
    try {
      const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
      const response = await fetch(`${apiBaseUrl}/api/auth/validate-csrf.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          [this.CSRF_HEADER]: token,
        },
        body: JSON.stringify({ token }),
        credentials: 'include',
      });

      return response.ok;
    } catch (error) {
      console.error('CSRF validation error occurred');
      return false;
    }
  }

  /**
   * Añadir token CSRF a headers de request
   */
  static addCSRFToHeaders(headers: Record<string, string> = {}): Record<string, string> {
    if (this.csrfToken) {
      headers[this.CSRF_HEADER] = this.csrfToken;
    }
    return headers;
  }

  /**
   * Wrapper para fetch que incluye protección CSRF automáticamente
   */
  static async secureRequest(
    url: string,
    options: RequestInit = {}
  ): Promise<Response> {
    // Asegurar que tenemos un token CSRF
    if (!this.csrfToken) {
      await this.initializeCSRF();
    }

    // Añadir headers CSRF
    const headers = {
      ...options.headers,
      ...this.addCSRFToHeaders(),
    };

    // Para requests que modifican datos, asegurar credentials
    if (['POST', 'PUT', 'DELETE'].includes(options.method?.toUpperCase() || 'GET')) {
      options.credentials = 'include';
    }

    const response = await fetch(url, {
      ...options,
      headers,
    });

    // Para 401 en auth endpoints, no hacer throw para evitar logs de error innecesarios
    if (response.status === 401 && url.includes('/auth/')) {
      // Silently return 401 response for auth endpoints
      return response;
    }

    return response;
  }

  /**
   * Renovar token CSRF
   */
  static async refreshCSRFToken(): Promise<string> {
    this.csrfToken = null;
    return await this.initializeCSRF();
  }

  /**
   * Limpiar token CSRF
   */
  static clearCSRFToken(): void {
    this.csrfToken = null;
  }
}

/**
 * Hook personalizado para usar protección CSRF en componentes React
 */
export function useCSRFProtection() {
  const [csrfToken, setCSRFToken] = React.useState<string | null>(null);
  const [isLoading, setIsLoading] = React.useState(true);

  React.useEffect(() => {
    const initCSRF = async () => {
      try {
        const token = await CSRFProtection.initializeCSRF();
        setCSRFToken(token);
      } catch (error) {
        console.error('Failed to initialize CSRF');
      } finally {
        setIsLoading(false);
      }
    };

    initCSRF();
  }, []);

  const refreshToken = async () => {
    setIsLoading(true);
    try {
      const token = await CSRFProtection.refreshCSRFToken();
      setCSRFToken(token);
    } catch (error) {
      // No loggear el error completo para evitar exponer información sensible
      console.error('CSRF token refresh failed');
    } finally {
      setIsLoading(false);
    }
  };

  return {
    csrfToken,
    isLoading,
    refreshToken,
    secureRequest: CSRFProtection.secureRequest,
  };
}

// Añadir React import
import React from 'react';
