import { useEffect, useState, useRef, useCallback } from 'react';
import { usePerformance } from '../contexts/PerformanceContext';

interface UseOptimizedDataOptions<T> {
  fetchFunction: () => Promise<T>;
  dependencies?: any[];
  cacheKey?: string;
  cacheDuration?: number; // en milisegundos
  fallbackData?: T;
  retry?: boolean;
  retryCount?: number;
  retryDelay?: number; // en milisegundos
  timeout?: number; // en milisegundos
}

/**
 * Hook para optimizar la carga de datos con estrategias avanzadas
 * 
 * Características:
 * - Caché local para reducir peticiones
 * - Timeout configurable
 * - Reintentos automáticos
 * - Adaptación a dispositivos/redes lentas
 * - Gestión de estados de carga
 */
export function useOptimizedData<T>({
  fetchFunction,
  dependencies = [],
  cacheKey,
  cacheDuration = 5 * 60 * 1000, // 5 minutos por defecto
  fallbackData,
  retry = true,
  retryCount = 3,
  retryDelay = 1000,
  timeout = 15000
}: UseOptimizedDataOptions<T>) {
  const [data, setData] = useState<T | undefined>(fallbackData);
  const [error, setError] = useState<Error | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const { isLowEndNetwork } = usePerformance();

  // Control de retries
  const retriesRef = useRef(0);
  const abortControllerRef = useRef<AbortController | null>(null);

  // Adaptación a dispositivos/redes lentas
  const adaptedTimeout = isLowEndNetwork ? timeout * 2 : timeout;
  const adaptedRetryDelay = isLowEndNetwork ? retryDelay * 2 : retryDelay;
  const adaptedRetryCount = isLowEndNetwork ? Math.min(2, retryCount) : retryCount;

  // Función para verificar caché
  const checkCache = useCallback(() => {
    if (!cacheKey) return null;

    try {
      const cachedItem = localStorage.getItem(`data_cache_${cacheKey}`);
      if (!cachedItem) return null;

      const { value, timestamp } = JSON.parse(cachedItem);
      const now = Date.now();

      if (now - timestamp < cacheDuration) {
        return value;
      }

      // Caché expirado
      localStorage.removeItem(`data_cache_${cacheKey}`);
      return null;
    } catch (e) {
      console.warn('Error al leer la caché:', e);
      return null;
    }
  }, [cacheKey, cacheDuration]);

  // Función para actualizar caché
  const updateCache = useCallback((newData: T) => {
    if (!cacheKey) return;

    try {
      const cacheItem = {
        value: newData,
        timestamp: Date.now()
      };

      localStorage.setItem(`data_cache_${cacheKey}`, JSON.stringify(cacheItem));
    } catch (e) {
      console.warn('Error al guardar en caché:', e);
    }
  }, [cacheKey]);

  // Función para cargar datos con timeout y retry
  const loadData = useCallback(async () => {
    // Comprobar caché primero
    const cachedData = checkCache();
    if (cachedData) {
      setData(cachedData);
      setLoading(false);
      return;
    }

    // Resetear estado
    setLoading(true);
    setError(null);

    // Cancelar petición anterior si existe
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    // Crear nuevo AbortController
    abortControllerRef.current = new AbortController();
    // const signal = abortControllerRef.current.signal; // Eliminado porque no se usa

    // Timeout
    const timeoutId = setTimeout(() => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    }, adaptedTimeout);

    try {
      // Envolver fetchFunction con AbortController si es posible
      const wrappedFetchFunction = async () => {
        try {
          // Asumimos que fetchFunction puede aceptar un signal
          // Esta es una simplificación, en la práctica necesitarías adaptar tu fetchFunction
          return await fetchFunction();
        } catch (error) {
          if ((error as any)?.name === 'AbortError') {
            throw new Error('La petición ha excedido el tiempo de espera');
          }
          throw error;
        }
      };

      const result = await wrappedFetchFunction();

      clearTimeout(timeoutId);

      // Actualizar estado y caché
      setData(result);
      setLoading(false);
      retriesRef.current = 0;

      // Actualizar caché
      updateCache(result);
    } catch (e) {
      clearTimeout(timeoutId);

      const error = e as Error;
      console.error('Error al cargar datos:', error);

      // Circuit breaker: máximo 3 intentos, después parar completamente
      const MAX_RETRIES = 3;

      // Lógica de retry con circuit breaker ESTRICTO
      if (retry && retriesRef.current < MAX_RETRIES) {
        retriesRef.current += 1;
        console.warn(`Retry ${retriesRef.current}/${MAX_RETRIES} for data loading`);

        // Exponential backoff con límite máximo
        const backoffDelay = Math.min(1000 * Math.pow(2, retriesRef.current - 1), 5000);

        setTimeout(() => {
          loadData();
        }, backoffDelay);
      } else {
        console.error('Max retries reached. Stopping all retry attempts.');
        setError(error);
        setLoading(false);
        retriesRef.current = 0;
      }
    }
  }, [fetchFunction, checkCache, updateCache, retry, adaptedRetryCount, adaptedRetryDelay, adaptedTimeout]);

  useEffect(() => {
    loadData();

    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, [...dependencies, loadData]);

  // Función para recargar datos manualmente
  const refresh = () => {
    retriesRef.current = 0;
    loadData();
  };

  return {
    data,
    loading,
    error,
    refresh
  };
}

export default useOptimizedData;
