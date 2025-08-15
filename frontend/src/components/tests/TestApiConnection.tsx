// src/components/tests/TestApiConnection.tsx
import { useState, useEffect } from 'react';
import { testBackendConnection } from '../../lib/apiService';

const TestApiConnection = () => {
  const [connectionStatus, setConnectionStatus] = useState<{
    loading: boolean;
    success?: boolean;
    message?: string;
    error?: string;
  }>({ loading: true });

  useEffect(() => {
    const checkConnection = async () => {
      try {
        const result = await testBackendConnection();
        setConnectionStatus({
          loading: false,
          success: result.success,
          message: result.message || '',
          error: result.success ? '' : (result.message || 'Connection failed')
        });
      } catch (error) {
        setConnectionStatus({
          loading: false,
          success: false,
          error: 'Error al comprobar la conexión'
        });
      }
    };

    checkConnection();
  }, []);

  const handleRetry = async () => {
    setConnectionStatus({ loading: true });
    try {
      const result = await testBackendConnection();
      setConnectionStatus({
        loading: false,
        success: result.success,
        message: result.message || '',
        error: result.success ? '' : (result.message || 'Connection failed')
      });
    } catch (error) {
      setConnectionStatus({
        loading: false,
        success: false,
        error: 'Error al comprobar la conexión'
      });
    }
  };

  return (
    <div className="p-6 max-w-md mx-auto bg-white rounded-lg shadow-md">
      <h2 className="text-xl font-bold mb-4">Prueba de conexión con el backend</h2>

      {connectionStatus.loading ? (
        <div className="flex items-center justify-center">
          <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-[#FF4785]"></div>
          <span className="ml-2">Comprobando conexión...</span>
        </div>
      ) : connectionStatus.success ? (
        <div className="text-green-600 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
          </svg>
          <span>{connectionStatus.message || "Conexión exitosa con el backend"}</span>
        </div>
      ) : (
        <div className="text-red-600 flex items-start">
          <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div>
            <p className="font-medium">Error de conexión</p>
            <p className="text-sm">{connectionStatus.error}</p>
          </div>
        </div>
      )}

      <div className="mt-4">
        <button
          onClick={handleRetry}
          className="px-4 py-2 bg-[#F24495] text-white rounded hover:bg-[#E13385] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#F24495]"
        >
          Reintentar
        </button>
      </div>
    </div>
  );
};

export default TestApiConnection;
