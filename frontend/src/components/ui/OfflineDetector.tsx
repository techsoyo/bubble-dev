/**
 * Componente OfflineDetector
 * 
 * Este componente monitorea el estado de conectividad y muestra
 * notificaciones apropiadas cuando el usuario está sin conexión o
 * cuando la conexión se recupera.
 */

import React, { useEffect, useState } from 'react';
import { useOnlineStatus } from '../../hooks/useOnlineStatus';

// Estilos para las notificaciones
const notificationStyles = {
  container: {
    position: 'fixed',
    bottom: '20px',
    left: '50%',
    transform: 'translateX(-50%)',
    zIndex: 9999,
    display: 'flex',
    flexDirection: 'column',
    gap: '10px',
    width: '90%',
    maxWidth: '400px',
  } as React.CSSProperties,
  notification: {
    padding: '12px 16px',
    borderRadius: '8px',
    boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    animation: 'slideUp 0.3s ease-out forwards',
  } as React.CSSProperties,
  offline: {
    backgroundColor: '#fef2f2',
    color: '#b91c1c',
    border: '1px solid #fee2e2',
  },
  online: {
    backgroundColor: '#ecfdf5',
    color: '#047857',
    border: '1px solid #d1fae5',
  },
  icon: {
    marginRight: '12px',
    fontSize: '1.2rem',
  },
  message: {
    flex: 1,
    fontSize: '0.9rem',
    fontWeight: 500,
  },
  closeButton: {
    background: 'none',
    border: 'none',
    cursor: 'pointer',
    fontSize: '1rem',
    color: 'inherit',
    opacity: 0.7,
  },
};

type Notification = {
  id: string;
  type: 'offline' | 'online';
  message: string;
  autoClose?: boolean;
};

const OfflineDetector: React.FC = () => {
  const { isOnline, wasOffline, connectionQuality } = useOnlineStatus();
  const [notifications, setNotifications] = useState<Notification[]>([]);

  // Añadir una notificación
  const addNotification = (type: 'offline' | 'online', message: string, autoClose = true) => {
    const id = Date.now().toString();
    setNotifications(prev => [...prev, { id, type, message, autoClose }]);

    // Auto-cerrar después de 5 segundos si autoClose es true
    if (autoClose) {
      setTimeout(() => {
        removeNotification(id);
      }, 5000);
    }
  };

  // Eliminar una notificación
  const removeNotification = (id: string) => {
    setNotifications(prev => prev.filter(notification => notification.id !== id));
  };

  // Mostrar notificaciones según el cambio de estado de conexión
  useEffect(() => {
    if (!isOnline) {
      addNotification(
        'offline',
        'Sin conexión a Internet. Algunas funciones pueden no estar disponibles.',
        false
      );
    } else if (wasOffline) {
      addNotification(
        'online',
        'Conexión restablecida. Todas las funciones están disponibles.',
        true
      );
    }
  }, [isOnline, wasOffline]);

  // Mostrar advertencia si la calidad de conexión es mala
  useEffect(() => {
    if (isOnline && connectionQuality === 'poor') {
      addNotification(
        'offline',
        'Conexión a Internet inestable. El rendimiento puede verse afectado.',
        true
      );
    }
  }, [connectionQuality, isOnline]);

  if (notifications.length === 0) {
    return null;
  }

  return (
    <div style={notificationStyles.container}>
      {notifications.map((notification) => (
        <div
          key={notification.id}
          style={{
            ...notificationStyles.notification,
            ...(notification.type === 'offline' ? notificationStyles.offline : notificationStyles.online),
          }}
        >
          <span style={notificationStyles.icon}>
            {notification.type === 'offline' ? '📶' : '✅'}
          </span>
          <span style={notificationStyles.message}>{notification.message}</span>
          <button
            style={notificationStyles.closeButton}
            onClick={() => removeNotification(notification.id)}
            aria-label="Cerrar notificación"
          >
            ×
          </button>
        </div>
      ))}
    </div>
  );
};

export default OfflineDetector;
