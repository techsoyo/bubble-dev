/**
 * Hook para manejar configuración de URLs de forma inteligente
 * Se adapta automáticamente al puerto actual del frontend
 */

import { useEffect, useState } from 'react';
import { env } from '../config/env';

interface UseApiConfigReturn {
  apiBaseUrl: string;
  isReady: boolean;
}

export const useApiConfig = (): UseApiConfigReturn => {
  const [apiBaseUrl, setApiBaseUrl] = useState<string>(env.API_BASE_URL);
  const [isReady, setIsReady] = useState(false);

  useEffect(() => {
    setApiBaseUrl(env.API_BASE_URL);
    setIsReady(true);
  }, []);

  return {
    apiBaseUrl,
    isReady
  };
};

// Export para compatibilidad con código existente
export const getApiBaseUrl = (): string => env.API_BASE_URL;
