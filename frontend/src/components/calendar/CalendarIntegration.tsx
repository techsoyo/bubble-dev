// src/components/calendar/CalendarIntegration.tsx
import React, { useState, useEffect } from 'react';
import {
  getCalendarIntegrations,
  connectCalendar,
  disconnectCalendar,
  getCalendarEvents
} from '../../lib/apiService';

interface CalendarIntegrationProps {
  userId: string;
  userType: 'candidate' | 'recruiter' | 'admin';
  onCalendarConnected?: (provider: string, connected: boolean) => void;
  className?: string;
}

const CalendarIntegration: React.FC<CalendarIntegrationProps> = ({
  userId,
  userType,
  onCalendarConnected,
  className = '',
}) => {
  const [integrations, setIntegrations] = useState<{
    google: boolean;
    microsoft: boolean;
  }>({
    google: false,
    microsoft: false,
  });

  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Cargar las integraciones actuales al inicializar el componente
  useEffect(() => {
    const loadIntegrations = async () => {
      try {
        const response = await getCalendarIntegrations(userId, userType);

        if (response.success) {
          setIntegrations({
            google: response.data.google || false,
            microsoft: response.data.microsoft || false,
          });
        } else {
          setError(response.error || 'Error al cargar las integraciones');
        }
      } catch (error) {
        console.error('Error al cargar integraciones de calendario:', error);
        setError('Error de conexión, intenta más tarde');
      } finally {
        setIsLoading(false);
      }
    };

    loadIntegrations();
  }, [userId, userType]);

  // Función para conectar un calendario
  const handleConnectCalendar = async (provider: 'google' | 'microsoft') => {
    try {
      setIsLoading(true);

      // En una implementación real, esta función abriría una ventana para la autorización OAuth2
      // En el MVP, simularemos que se ha completado correctamente
      const response = await connectCalendar(userId, userType, provider);

      if (response.success) {
        setIntegrations(prev => ({
          ...prev,
          [provider]: true,
        }));

        onCalendarConnected && onCalendarConnected(provider, true);
      } else {
        setError(response.error || `Error al conectar con ${provider}`);
      }
    } catch (error) {
      console.error(`Error al conectar calendario ${provider}:`, error);
      setError('Error de conexión, intenta más tarde');
    } finally {
      setIsLoading(false);
    }
  };

  // Función para desconectar un calendario
  const handleDisconnectCalendar = async (provider: 'google' | 'microsoft') => {
    try {
      setIsLoading(true);

      const response = await disconnectCalendar(userId, userType, provider);

      if (response.success) {
        setIntegrations(prev => ({
          ...prev,
          [provider]: false,
        }));

        onCalendarConnected && onCalendarConnected(provider, false);
      } else {
        setError(response.error || `Error al desconectar ${provider}`);
      }
    } catch (error) {
      console.error(`Error al desconectar calendario ${provider}:`, error);
      setError('Error de conexión, intenta más tarde');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className={`space-y-6 ${className}`}>
      <h3 className="text-lg font-medium text-gray-900">Integración de calendarios</h3>

      {error && (
        <div className="bg-red-50 p-3 rounded-md text-red-600 text-sm mb-4">
          {error}
        </div>
      )}

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <div className="p-6 space-y-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <img
                src="/images/google-calendar-icon.png"
                alt="Google Calendar"
                className="w-8 h-8"
              />
              <div>
                <h4 className="text-sm font-medium text-gray-900">Google Calendar</h4>
                <p className="text-xs text-gray-500">Sincroniza eventos con tu calendario de Google</p>
              </div>
            </div>

            <button
              type="button"
              disabled={isLoading}
              onClick={() => integrations.google
                ? handleDisconnectCalendar('google')
                : handleConnectCalendar('google')
              }
              className={`px-4 py-2 text-sm font-medium rounded-md ${integrations.google
                  ? 'text-red-700 bg-red-50 hover:bg-red-100'
                  : 'text-white bg-[#F24495] hover:bg-[#E13385]'
                }`}
            >
              {isLoading ? 'Procesando...' : integrations.google ? 'Desconectar' : 'Conectar'}
            </button>
          </div>

          <div className="border-t border-gray-200 pt-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-3">
                <img
                  src="/images/microsoft-calendar-icon.png"
                  alt="Microsoft Calendar"
                  className="w-8 h-8"
                />
                <div>
                  <h4 className="text-sm font-medium text-gray-900">Microsoft 365 Calendar</h4>
                  <p className="text-xs text-gray-500">Sincroniza eventos con tu calendario de Microsoft 365</p>
                </div>
              </div>

              <button
                type="button"
                disabled={isLoading}
                onClick={() => integrations.microsoft
                  ? handleDisconnectCalendar('microsoft')
                  : handleConnectCalendar('microsoft')
                }
                className={`px-4 py-2 text-sm font-medium rounded-md ${integrations.microsoft
                    ? 'text-red-700 bg-red-50 hover:bg-red-100'
                    : 'text-white bg-[#F24495] hover:bg-[#E13385]'
                  }`}
              >
                {isLoading ? 'Procesando...' : integrations.microsoft ? 'Desconectar' : 'Conectar'}
              </button>
            </div>
          </div>
        </div>

        <div className="bg-gray-50 px-6 py-3 text-xs text-gray-500">
          Tus datos están seguros. Solo accedemos a la información necesaria para programar entrevistas.
        </div>
      </div>
    </div>
  );
};

export default CalendarIntegration;
