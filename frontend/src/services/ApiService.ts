/**
 * API Service - Servicio centralizado para peticiones HTTP
 * 
 * Utiliza la configuración centralizada de env.ts y maneja
 * CORS, credentials y headers de forma unificada
 * 
 * @package Services
 * @author Bubble Talents Development Team
 * @version 1.0.0
 * @since 2025-08-20
 */

import { env } from '@/config/env';

export interface ApiRequestOptions extends RequestInit {
  useCredentials?: boolean;
  timeout?: number;
}

export class ApiService {
  private static readonly baseUrl = env.API_BASE_URL;
  private static readonly defaultTimeout = 30000; // 30 seconds

  /**
   * Construye URL completa para endpoint API
   */
  static buildUrl(endpoint: string): string {
    // Remover slash inicial si existe
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;

    // Si el baseUrl ya incluye /api, no duplicar
    if (this.baseUrl.endsWith('/api')) {
      return `${this.baseUrl}/${cleanEndpoint}`;
    }

    // Si el endpoint ya incluye api/, no duplicar
    if (cleanEndpoint.startsWith('api/')) {
      return `${this.baseUrl}/${cleanEndpoint}`;
    }

    return `${this.baseUrl}/api/${cleanEndpoint}`;
  }

  /**
   * Realiza petición HTTP con configuración CORS adecuada
   */
  static async request<T = any>(
    endpoint: string,
    options: ApiRequestOptions = {}
  ): Promise<T> {
    const {
      useCredentials = true,
      timeout = this.defaultTimeout,
      headers = {},
      ...fetchOptions
    } = options;

    const url = this.buildUrl(endpoint);

    const requestHeaders: HeadersInit = {
      'Content-Type': 'application/json',
      ...headers,
    };

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);

    try {
      const response = await fetch(url, {
        ...fetchOptions,
        headers: requestHeaders,
        credentials: useCredentials ? 'include' : 'omit',
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return await response.json();
    } catch (error) {
      clearTimeout(timeoutId);

      if (error instanceof Error) {
        if (error.name === 'AbortError') {
          throw new Error(`Request timeout after ${timeout}ms`);
        }
        throw error;
      }

      throw new Error('Unknown error occurred');
    }
  }

  /**
   * GET request
   */
  static get<T = any>(endpoint: string, options: Omit<ApiRequestOptions, 'method' | 'body'> = {}): Promise<T> {
    return this.request<T>(endpoint, { ...options, method: 'GET' });
  }

  /**
   * POST request
   */
  static post<T = any>(endpoint: string, data: any, options: Omit<ApiRequestOptions, 'method'> = {}): Promise<T> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  /**
   * PUT request  
   */
  static put<T = any>(endpoint: string, data: any, options: Omit<ApiRequestOptions, 'method'> = {}): Promise<T> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  /**
   * DELETE request
   */
  static delete<T = any>(endpoint: string, options: Omit<ApiRequestOptions, 'method' | 'body'> = {}): Promise<T> {
    return this.request<T>(endpoint, { ...options, method: 'DELETE' });
  }

  /**
   * Upload file with FormData
   */
  static async upload<T = any>(
    endpoint: string,
    formData: FormData,
    options: Omit<ApiRequestOptions, 'method' | 'body' | 'headers'> = {}
  ): Promise<T> {
    const url = this.buildUrl(endpoint);
    const { useCredentials = true, timeout = this.defaultTimeout, ...fetchOptions } = options;

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);

    try {
      const response = await fetch(url, {
        ...fetchOptions,
        method: 'POST',
        body: formData,
        credentials: useCredentials ? 'include' : 'omit',
        signal: controller.signal,
        // No setting Content-Type for FormData - let browser set it
      });

      clearTimeout(timeoutId);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return await response.json();
    } catch (error) {
      clearTimeout(timeoutId);

      if (error instanceof Error) {
        if (error.name === 'AbortError') {
          throw new Error(`Upload timeout after ${timeout}ms`);
        }
        throw error;
      }

      throw new Error('Unknown upload error occurred');
    }
  }

  /**
   * Health check endpoint
   */
  static async healthCheck(): Promise<{ status: string; timestamp: number }> {
    return this.get('health.php');
  }
}

// Export convenience methods
export const api = ApiService;
export default ApiService;
