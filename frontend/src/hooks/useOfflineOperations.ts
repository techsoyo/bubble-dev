/**
 * Hook para manejar operaciones offline en la aplicación
 * Proporciona funciones para cachear datos, manejar solicitudes offline
 * y sincronizar datos cuando se recupera la conexión.
 */

import { useState, useEffect } from 'react';
import { useOnlineStatus } from './useOnlineStatus';
import { offlineDataManager } from '../services/OfflineDataManager';

interface UseOfflineOperationsOptions {
  automaticSync?: boolean;
  syncInterval?: number;
}

export function useOfflineOperations(options: UseOfflineOperationsOptions = {}) {
  const { automaticSync = true, syncInterval = 60000 } = options;
  const { isOnline } = useOnlineStatus();
  const [isSyncing, setIsSyncing] = useState(false);
  const [lastSyncTime, setLastSyncTime] = useState<Date | null>(null);
  const [pendingOperations, setPendingOperations] = useState<number>(0);

  // Función para cargar datos con soporte offline
  const fetchWithOfflineSupport = async <T>(
    url: string,
    options?: RequestInit & { cacheTTL?: number }
  ): Promise<T> => {
    const cacheTTL = options?.cacheTTL || 60; // Default 60 minutes

    try {
      // Si estamos online, intentar obtener datos frescos
      if (isOnline) {
        const response = await fetch(url, options);
        if (!response.ok) {
          throw new Error(`Error fetching data: ${response.status}`);
        }

        const data = await response.json();

        // Guardar en caché para uso offline
        await offlineDataManager.cacheApiResponse(url, data, cacheTTL);

        return data;
      } else {
        // Si estamos offline, intentar obtener datos de la caché
        const cachedData = await offlineDataManager.getCachedApiResponse(url);
        if (cachedData) {
          return cachedData as T;
        }

        throw new Error('No hay conexión y no se encontraron datos en caché');
      }
    } catch (error) {
      // Si hay un error al obtener datos frescos, intentar usar caché
      const cachedData = await offlineDataManager.getCachedApiResponse(url);
      if (cachedData) {
        return cachedData as T;
      }

      throw error;
    }
  };

  // Función para enviar datos con soporte offline
  const sendWithOfflineSupport = async <T>(
    url: string,
    method: string,
    data: any,
    options?: RequestInit
  ): Promise<T | null> => {
    const requestOptions: RequestInit = {
      method,
      headers: {
        'Content-Type': 'application/json',
        ...(options?.headers || {})
      },
      body: JSON.stringify(data),
      ...options
    };

    try {
      // Si estamos online, enviar inmediatamente
      if (isOnline) {
        const response = await fetch(url, requestOptions);

        if (!response.ok) {
          throw new Error(`Error sending data: ${response.status}`);
        }

        return await response.json();
      } else {
        // Si estamos offline, guardar para enviar más tarde
        await offlineDataManager.addPendingRequest({
          url,
          method,
          body: data,
          headers: requestOptions.headers as Record<string, string>
        });

        // Actualizar contador de operaciones pendientes
        setPendingOperations(prev => prev + 1);

        return null;
      }
    } catch (error) {
      // Si hay un error al enviar, guardar para intentar más tarde
      await offlineDataManager.addPendingRequest({
        url,
        method,
        body: data,
        headers: requestOptions.headers as Record<string, string>
      });

      // Actualizar contador de operaciones pendientes
      setPendingOperations(prev => prev + 1);

      throw error;
    }
  };

  // Función para guardar borrador de formulario
  const saveFormDraft = async (formId: string, formData: any): Promise<void> => {
    await offlineDataManager.saveFormDraft(formId, formData);
  };

  // Función para recuperar borrador de formulario
  const getFormDraft = async (formId: string): Promise<any> => {
    return await offlineDataManager.getFormDraft(formId);
  };

  // Función para eliminar borrador de formulario
  const deleteFormDraft = async (formId: string): Promise<void> => {
    await offlineDataManager.deleteFormDraft(formId);
  };

  // Función para sincronizar datos pendientes
  const syncPendingOperations = async (): Promise<void> => {
    if (!isOnline || isSyncing) {
      return;
    }

    setIsSyncing(true);

    try {
      // Obtener todas las operaciones pendientes
      const pendingRequests = await offlineDataManager.getPendingRequests();
      let successCount = 0;

      // Intentar enviar cada operación pendiente
      for (const request of pendingRequests) {
        try {
          const response = await fetch(request.url, {
            method: request.method,
            headers: request.headers || { 'Content-Type': 'application/json' },
            body: request.body ? JSON.stringify(request.body) : null
          });

          if (response.ok && request.id) {
            // Si la operación se completó con éxito, eliminarla de pendientes
            await offlineDataManager.removePendingRequest(request.id);
            successCount++;
          }
        } catch (error) {
          console.error(`Error al sincronizar operación pendiente:`, error);
        }
      }

      // Actualizar contador de operaciones pendientes
      const remaining = pendingRequests.length - successCount;
      setPendingOperations(remaining);

      // Actualizar último tiempo de sincronización
      setLastSyncTime(new Date());

    } catch (error) {
      console.error('Error en sincronización de datos:', error);
    } finally {
      setIsSyncing(false);
    }
  };

  // Sincronización automática cuando se recupera la conexión
  useEffect(() => {
    if (isOnline && automaticSync && pendingOperations > 0) {
      syncPendingOperations();
    }
  }, [isOnline, automaticSync, pendingOperations]);

  // Sincronización periódica si está habilitada
  useEffect(() => {
    if (!automaticSync || syncInterval <= 0) {
      return undefined;
    }

    const intervalId = setInterval(() => {
      if (isOnline && pendingOperations > 0) {
        syncPendingOperations();
      }
    }, syncInterval);

    return () => clearInterval(intervalId);
  }, [automaticSync, syncInterval, isOnline, pendingOperations]);

  // Limpiar caché expirada periódicamente
  useEffect(() => {
    const cleanupInterval = setInterval(() => {
      offlineDataManager.cleanExpiredCache();
    }, 30 * 60 * 1000); // Cada 30 minutos

    return () => clearInterval(cleanupInterval);
  }, []);

  return {
    fetchWithOfflineSupport,
    sendWithOfflineSupport,
    saveFormDraft,
    getFormDraft,
    deleteFormDraft,
    syncPendingOperations,
    isSyncing,
    pendingOperations,
    lastSyncTime
  };
}
