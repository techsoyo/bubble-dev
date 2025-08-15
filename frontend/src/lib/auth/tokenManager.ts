/**
 * Token Refresh System
 * Implementa renovación automática de tokens de autenticación
 */

export interface TokenData {
  accessToken: string;
  refreshToken: string;
  expiresAt: number;
  tokenType: string;
}

export class TokenManager {
  private static refreshToken: string | null = null;
  private static accessToken: string | null = null;
  private static expiresAt: number = 0;
  private static refreshTimer: NodeJS.Timeout | null = null;
  private static isRefreshing: boolean = false;
  private static refreshPromise: Promise<boolean> | null = null;

  /**
   * Configurar tokens iniciales
   */
  static setTokens(tokenData: TokenData): void {
    this.accessToken = tokenData.accessToken;
    this.refreshToken = tokenData.refreshToken;
    this.expiresAt = tokenData.expiresAt;

    // Programar renovación automática
    this.scheduleTokenRefresh();
  }

  /**
   * Obtener token de acceso actual
   */
  static getAccessToken(): string | null {
    return this.accessToken;
  }

  /**
   * Verificar si el token está expirado
   */
  static isTokenExpired(): boolean {
    if (!this.expiresAt) return true;

    // Considerar expirado si queda menos de 5 minutos
    const bufferTime = 5 * 60 * 1000; // 5 minutos en ms
    return Date.now() >= (this.expiresAt - bufferTime);
  }

  /**
   * Renovar token de acceso usando refresh token
   */
  static async refreshAccessToken(): Promise<boolean> {
    // Si ya está en proceso de renovación, esperar el resultado
    if (this.isRefreshing && this.refreshPromise) {
      return await this.refreshPromise;
    }

    // Si no hay refresh token, no se puede renovar
    if (!this.refreshToken) {
      console.warn('No refresh token available');
      return false;
    }

    this.isRefreshing = true;
    this.refreshPromise = this.performTokenRefresh();

    try {
      const result = await this.refreshPromise;
      return result;
    } finally {
      this.isRefreshing = false;
      this.refreshPromise = null;
    }
  }

  /**
   * Realizar la renovación del token
   */
  private static async performTokenRefresh(): Promise<boolean> {
    try {
      const response = await fetch('/api/auth/refresh', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          refreshToken: this.refreshToken
        }),
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error('Token refresh failed');
      }

      const data = await response.json();

      if (data.success && data.tokens) {
        this.setTokens(data.tokens);
        console.log('Token refreshed successfully');
        return true;
      }

      throw new Error('Invalid refresh response');
    } catch (error) {
      console.error('Token refresh error:', error);

      // Si falla la renovación, limpiar tokens y redirigir al login
      this.clearTokens();

      // Emitir evento para que la app maneje el logout
      window.dispatchEvent(new CustomEvent('token-refresh-failed'));

      return false;
    }
  }

  /**
   * Programar renovación automática del token
   */
  private static scheduleTokenRefresh(): void {
    // Limpiar timer anterior si existe
    if (this.refreshTimer) {
      clearTimeout(this.refreshTimer);
    }

    if (!this.expiresAt) return;

    // Programar renovación 5 minutos antes de la expiración
    const refreshTime = this.expiresAt - Date.now() - (5 * 60 * 1000);

    if (refreshTime > 0) {
      this.refreshTimer = setTimeout(() => {
        this.refreshAccessToken();
      }, refreshTime);

      console.log(`Token refresh scheduled in ${Math.round(refreshTime / 1000 / 60)} minutes`);
    } else {
      // Si ya está cerca de expirar, renovar inmediatamente
      this.refreshAccessToken();
    }
  }

  /**
   * Limpiar todos los tokens
   */
  static clearTokens(): void {
    this.accessToken = null;
    this.refreshToken = null;
    this.expiresAt = 0;

    if (this.refreshTimer) {
      clearTimeout(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  /**
   * Interceptor para requests automáticos con token refresh
   */
  static async authenticatedRequest(
    url: string,
    options: RequestInit = {}
  ): Promise<Response> {
    // Verificar si necesitamos renovar el token
    if (this.isTokenExpired()) {
      const refreshed = await this.refreshAccessToken();
      if (!refreshed) {
        throw new Error('Authentication failed - unable to refresh token');
      }
    }

    // Añadir token de autorización
    const headers = {
      ...options.headers,
      'Authorization': `Bearer ${this.accessToken}`,
    };

    // Realizar request
    const response = await fetch(url, {
      ...options,
      headers,
      credentials: 'include',
    });

    // Si obtenemos 401, intentar renovar token una vez más
    if (response.status === 401 && !this.isRefreshing) {
      const refreshed = await this.refreshAccessToken();
      if (refreshed) {
        // Reintentar request con nuevo token
        const retryHeaders = {
          ...options.headers,
          'Authorization': `Bearer ${this.accessToken}`,
        };

        return fetch(url, {
          ...options,
          headers: retryHeaders,
          credentials: 'include',
        });
      }
    }

    return response;
  }

  /**
   * Verificar si hay tokens válidos
   */
  static hasValidTokens(): boolean {
    return !!this.accessToken && !!this.refreshToken && !this.isTokenExpired();
  }
}

/**
 * Hook React para manejar tokens automáticamente
 */
export function useTokenManager() {
  const [hasValidTokens, setHasValidTokens] = React.useState(
    TokenManager.hasValidTokens()
  );

  React.useEffect(() => {
    const checkTokenStatus = () => {
      setHasValidTokens(TokenManager.hasValidTokens());
    };

    // Verificar estado inicial
    checkTokenStatus();

    // Escuchar eventos de fallo de renovación
    const handleTokenRefreshFailed = () => {
      setHasValidTokens(false);
    };

    window.addEventListener('token-refresh-failed', handleTokenRefreshFailed);

    // Verificar estado cada minuto
    const interval = setInterval(checkTokenStatus, 60000);

    return () => {
      window.removeEventListener('token-refresh-failed', handleTokenRefreshFailed);
      clearInterval(interval);
    };
  }, []);

  return {
    hasValidTokens,
    refreshToken: () => TokenManager.refreshAccessToken(),
    clearTokens: () => {
      TokenManager.clearTokens();
      setHasValidTokens(false);
    },
    authenticatedRequest: TokenManager.authenticatedRequest,
  };
}

// Import React
import React from 'react';
