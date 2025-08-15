/**
 * Secure Cookie Management for Authentication
 * Reemplaza localStorage con cookies HTTP-only para mayor seguridad
 */

export interface CookieOptions {
  expires?: Date;
  maxAge?: number;
  httpOnly?: boolean;
  secure?: boolean;
  sameSite?: 'strict' | 'lax' | 'none';
  path?: string;
}

export class SecureCookieManager {
  /**
   * Configuración por defecto para cookies de autenticación
   */
  private static getDefaultOptions(): CookieOptions {
    return {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production', // Solo HTTPS en producción
      sameSite: 'strict',
      path: '/',
      maxAge: 24 * 60 * 60 * 1000, // 24 horas por defecto
    };
  }

  /**
   * Set cookie segura (solo funciona desde servidor)
   * Para el cliente, enviamos la información al backend
   */
  static async setSecureCookie(
    name: string,
    value: string,
    options: Partial<CookieOptions> = {}
  ): Promise<boolean> {
    const cookieOptions = { ...this.getDefaultOptions(), ...options };

    try {
      // En desarrollo, usar document.cookie como fallback
      if (process.env.NODE_ENV === 'development') {
        const cookieString = this.buildCookieString(name, value, cookieOptions);
        document.cookie = cookieString;
        return true;
      }

      // En producción, enviar al backend para set HTTP-only cookies
      const response = await fetch('/api/auth/set-cookie', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          name,
          value,
          options: cookieOptions,
        }),
        credentials: 'include',
      });

      return response.ok;
    } catch (error) {
      console.error('Error setting secure cookie');
      return false;
    }
  }

  /**
   * Get cookie value (solo accesible si no es HTTP-only)
   */
  static getCookie(name: string): string | null {
    if (typeof document === 'undefined') return null;

    const cookies = document.cookie.split(';');
    for (const cookie of cookies) {
      const [cookieName, cookieValue] = cookie.trim().split('=');
      if (cookieName === name && cookieValue !== undefined) {
        return decodeURIComponent(cookieValue);
      }
    }
    return null;
  }

  /**
   * Remove cookie
   */
  static async removeCookie(name: string): Promise<boolean> {
    try {
      // En desarrollo
      if (process.env.NODE_ENV === 'development') {
        document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
        return true;
      }

      // En producción, solicitar al backend
      const response = await fetch('/api/auth/remove-cookie', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ name }),
        credentials: 'include',
      });

      return response.ok;
    } catch (error) {
      console.error('Error removing cookie');
      return false;
    }
  }

  /**
   * Build cookie string for document.cookie
   */
  private static buildCookieString(
    name: string,
    value: string,
    options: CookieOptions
  ): string {
    let cookieString = `${name}=${encodeURIComponent(value)}`;

    if (options.maxAge) {
      const expirationDate = new Date(Date.now() + options.maxAge);
      cookieString += `; expires=${expirationDate.toUTCString()}`;
    }

    if (options.path) {
      cookieString += `; path=${options.path}`;
    }

    if (options.secure) {
      cookieString += '; secure';
    }

    if (options.sameSite) {
      cookieString += `; samesite=${options.sameSite}`;
    }

    // Nota: HTTP-only no se puede set desde JavaScript client-side
    // Esto debe manejarse en el servidor

    return cookieString;
  }

  /**
   * Check if authentication cookies exist (para verificar estado de login)
   */
  static async checkAuthCookies(): Promise<boolean> {
    try {
      const response = await fetch('/api/auth/check-session', {
        method: 'GET',
        credentials: 'include',
      });

      return response.ok;
    } catch (error) {
      // No loggear el error completo para evitar exponer información sensible
      console.error('Auth cookie verification failed');
      return false;
    }
  }
}
