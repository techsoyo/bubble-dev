/**
 * OfflineAwareSuspense
 * 
 * Un componente que envuelve a Suspense para manejar mejor estados de carga
 * cuando la aplicación está sin conexión. Muestra un fallback específico para
 * el modo sin conexión cuando es necesario.
 */

import React, { Suspense, useEffect, useState } from 'react';
import { useOnlineStatus } from '../../hooks/useOnlineStatus';
import LoadingSpinner from '../ui/LoadingSpinner';

interface OfflineAwareSuspenseProps {
  children: React.ReactNode;
  fallback?: React.ReactNode;
  offlineFallback?: React.ReactNode;
  resourceUrl?: string; // URL opcional para verificar si el recurso está en caché
}

const OfflineAwareSuspense: React.FC<OfflineAwareSuspenseProps> = ({
  children,
  fallback = <LoadingSpinner size="large" />,
  offlineFallback,
  resourceUrl
}) => {
  const { isOnline } = useOnlineStatus();
  const [isResourceCached, setIsResourceCached] = useState<boolean | null>(null);

  // Verificar si el recurso está en caché
  useEffect(() => {
    const checkCache = async () => {
      if (!isOnline && resourceUrl) {
        try {
          // Verificar si la URL está en caché
          const cache = await caches.open('bubble-talents-dynamic-v1');
          const cachedResponse = await cache.match(resourceUrl);
          setIsResourceCached(!!cachedResponse);
        } catch (error) {
          console.error('Error al verificar caché:', error);
          setIsResourceCached(false);
        }
      } else {
        setIsResourceCached(null); // Reset estado si estamos online
      }
    };

    checkCache();
  }, [isOnline, resourceUrl]);

  // Si estamos offline y el recurso no está en caché, mostrar el fallback offline
  if (!isOnline && isResourceCached === false && offlineFallback) {
    return <>{offlineFallback}</>;
  }

  // En otros casos, usar Suspense normal
  return <Suspense fallback={fallback}>{children}</Suspense>;
};

// Componente de fallback para mostrar cuando estamos offline
export const OfflineFallback: React.FC<{ message?: string }> = ({
  message = 'Este contenido no está disponible sin conexión.'
}) => {
  return (
    <div style={{
      padding: '2rem',
      textAlign: 'center',
      backgroundColor: '#f9fafb',
      borderRadius: '0.5rem',
      margin: '1rem 0'
    }}>
      <div style={{
        fontSize: '3rem',
        marginBottom: '1rem',
        color: '#9ca3af'
      }}>
        📶
      </div>
      <h3 style={{
        fontSize: '1.5rem',
        fontWeight: 'bold',
        marginBottom: '1rem',
        color: '#4b5563'
      }}>
        Sin conexión
      </h3>
      <p style={{
        color: '#6b7280',
        marginBottom: '1.5rem'
      }}>
        {message}
      </p>
      <button
        onClick={() => window.location.reload()}
        style={{
          backgroundColor: '#3b82f6',
          color: 'white',
          border: 'none',
          padding: '0.75rem 1.5rem',
          borderRadius: '0.375rem',
          fontWeight: 500,
          cursor: 'pointer'
        }}
      >
        Intentar de nuevo
      </button>
    </div>
  );
};

export default OfflineAwareSuspense;
