/// <reference types="vite/client" />
// src/lib/api.ts

import axios from 'axios';
import { env } from '../config/env';
import { PROTECTED_ROUTES } from './protectedRoutes'; // rutas que sí requieren auth


export const api = axios.store({
  baseURL: env.API_BASE_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true
});

// Helper: request con abort y retry exponencial (máx 2 reintentos)
export async function apiRequest(config: any, { signal, retries = 2 }: { signal?: AbortSignal; retries?: number } = {}) {
  let lastError;
  for (let attempt = 0; attempt <= retries; attempt++) {
    try {
      const source = axios.CancelToken.source();
      if (signal) {
        signal.addEventListener('abort', () => source.cancel('Aborted by user'));
      }
      return await api({ ...config, cancelToken: source.token });
    } catch (err) {
      lastError = err;
      if (axios.isCancel(err)) throw err;
      if (attempt < retries) {
        await new Promise(res => setTimeout(res, 300 * Math.pow(2, attempt)));
      }
    }
  }
  throw lastError;
}


// No necesitas token desde localStorage, ya que usas cookies HTTP-only

api.interceptors.request.use(
  (config) => {
    // Puedes añadir lógica extra aquí si necesitas loguear o modificar headers
    return config;
  },
  (error) => Promise.reject(error)
);

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      const currentPath = window.location.pathname;
      const isProtected = PROTECTED_ROUTES.some((route) =>
        currentPath.startsWith(route)
      );
      if (isProtected && currentPath !== '/auth/login') {
        window.location.href = '/auth/login';
      }
    }
    return Promise.reject(error);
  }
);
