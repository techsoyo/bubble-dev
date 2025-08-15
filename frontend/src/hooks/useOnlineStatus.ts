/**
 * Hook personalizado para manejar el estado de conectividad a internet
 * Proporciona información en tiempo real sobre si el usuario está online/offline
 * y expone métodos para verificar la calidad de la conexión.
 */

import { useState, useEffect, useCallback } from 'react';

interface UseOnlineStatusResult {
  isOnline: boolean;
  wasOffline: boolean;
  connectionQuality: 'unknown' | 'poor' | 'good' | 'excellent';
  checkServerConnection: () => Promise<boolean>;
  lastOnlineTime: Date | null;
  lastOfflineTime: Date | null;
}

export function useOnlineStatus(): UseOnlineStatusResult {
  const [isOnline, setIsOnline] = useState<boolean>(navigator.onLine);
  const [wasOffline, setWasOffline] = useState<boolean>(false);
  const [connectionQuality, setConnectionQuality] = useState<'unknown' | 'poor' | 'good' | 'excellent'>('unknown');
  const [lastOnlineTime, setLastOnlineTime] = useState<Date | null>(isOnline ? new Date() : null);
  const [lastOfflineTime, setLastOfflineTime] = useState<Date | null>(!isOnline ? new Date() : null);

  // Función para comprobar la calidad de conexión actual
  const checkConnectionQuality = useCallback(async () => {
    if (!navigator.onLine) {
      setConnectionQuality('poor');
      return;
    }

    try {
      const startTime = performance.now();
      // Pequeña imagen para medir la velocidad (1x1 pixel transparent GIF)
      const response = await fetch('/assets/images/pixel.gif?' + Date.now(), { cache: 'no-store' });
      if (!response.ok) {
        setConnectionQuality('poor');
        return;
      }

      const endTime = performance.now();
      const duration = endTime - startTime;

      // Clasificar la calidad de conexión según el tiempo de respuesta
      if (duration < 100) {
        setConnectionQuality('excellent');
      } else if (duration < 500) {
        setConnectionQuality('good');
      } else {
        setConnectionQuality('poor');
      }
    } catch (error) {
      console.error('Error al verificar la calidad de conexión:', error);
      setConnectionQuality('poor');
    }
  }, []);

  // Función para verificar la conexión con el servidor
  const checkServerConnection = useCallback(async (): Promise<boolean> => {
    try {
      // Verificar ping a la API
      const response = await fetch('/api/ping', {
        method: 'GET',
        headers: { 'Cache-Control': 'no-cache' },
        // Agregar un timeout de 5 segundos
        signal: AbortSignal.timeout(5000)
      });

      return response.ok;
    } catch (error) {
      console.warn('Error al verificar conexión con el servidor:', error);
      return false;
    }
  }, []);

  useEffect(() => {
    // Handler para cuando la conexión se recupera
    const handleOnline = () => {
      setIsOnline(true);
      setWasOffline(true);
      setLastOnlineTime(new Date());
      // Actualizar el atributo data-connection en el documento
      document.documentElement.setAttribute('data-connection', 'online');
      // Verificar la calidad de la conexión
      checkConnectionQuality();
    };

    // Handler para cuando la conexión se pierde
    const handleOffline = () => {
      setIsOnline(false);
      setLastOfflineTime(new Date());
      // Actualizar el atributo data-connection en el documento
      document.documentElement.setAttribute('data-connection', 'offline');
      setConnectionQuality('poor');
    };

    // Registrar listeners para eventos online/offline
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Verificar calidad de conexión inicial y cada minuto
    checkConnectionQuality();
    const intervalId = setInterval(checkConnectionQuality, 60000);

    // Escuchar el evento personalizado de cambio de conexión
    window.addEventListener('connectionChange', ((event: CustomEvent) => {
      const { online } = event.detail;
      setIsOnline(online);
      if (online) {
        setWasOffline(true);
        setLastOnlineTime(new Date());
      } else {
        setLastOfflineTime(new Date());
      }
    }) as EventListener);

    // Limpiar listeners al desmontar
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
      window.removeEventListener('connectionChange', (() => { }) as EventListener);
      clearInterval(intervalId);
    };
  }, [checkConnectionQuality]);

  return {
    isOnline,
    wasOffline,
    connectionQuality,
    checkServerConnection,
    lastOnlineTime,
    lastOfflineTime
  };
}
