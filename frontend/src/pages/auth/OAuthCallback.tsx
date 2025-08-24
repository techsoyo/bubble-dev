// src/pages/auth/OAuthCallback.tsx
import * as React from 'react';
import { useEffect, useState } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { socialLogin } from '../../services/ApiService';
import { useAuth } from '../../contexts/AuthContext';

const OAuthCallback: React.FC = () => {
  const { provider } = useParams<{ provider: string }>();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { login } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const handleOAuthCallback = async () => {
      try {
        const code = searchParams.get('code');
        const state = searchParams.get('state');
        const errorParam = searchParams.get('error');

        // Verificar si hay errores del proveedor OAuth
        if (errorParam) {
          throw new Error(`Error de autenticación: ${errorParam}`);
        }

        // Verificar que tenemos el código de autorización
        if (!code) {
          throw new Error('No se recibió el código de autorización');
        }

        // Verificar que el estado coincide (medida de seguridad)
        if (!state || state !== provider) {
          throw new Error('Estado de autenticación inválido');
        }

        // Enviar el código al backend para intercambiarlo por tokens
        const response = await socialLogin(provider as 'google' | 'linkedin' | 'apple', code);

        if (response.success && response.data) {
          // Usar el contexto de autenticación para establecer la sesión
          await login(response.data.user, response.data.token);

          // Redirigir según el rol del usuario
          const userRole = response.data.user?.role || 'candidate';
          switch (userRole) {
            case 'admin':
            case 'hr':
              navigate('/dashboard/hrdashboard');
              break;
            case 'recruiter':
              navigate('/dashboard/recruiterdashboard');
              break;
            case 'manager':
              navigate('/dashboard/managerdashboard');
              break;
            default:
              navigate('/dashboard/cddashboard');
          }
        } else {
          throw new Error(response.error || 'Error en la autenticación');
        }

      } catch (err: any) {
        setError(err.message || 'Error desconocido en la autenticación');
        console.error('Error en OAuth callback:', err);
      } finally {
        setLoading(false);
      }
    };

    if (provider) {
      handleOAuthCallback();
    } else {
      setError('Proveedor de autenticación no especificado');
      setLoading(false);
    }
  }, [provider, searchParams, navigate, login]);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#FF4785] mx-auto mb-4"></div>
          <h2 className="text-xl font-semibold text-gray-900 mb-2">Autenticando...</h2>
          <p className="text-gray-600">Procesando tu inicio de sesión con {provider}</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center max-w-md">
          <div className="bg-red-100 rounded-full h-12 w-12 flex items-center justify-center mx-auto mb-4">
            <svg className="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h2 className="text-xl font-semibold text-gray-900 mb-2">Error de Autenticación</h2>
          <p className="text-gray-600 mb-6">{error}</p>
          <button
            onClick={() => navigate('/login')}
            className="bg-[#FF4785] hover:bg-[#FF3575] text-white font-bold py-2 px-4 rounded"
          >
            Volver al Inicio de Sesión
          </button>
        </div>
      </div>
    );
  }

  // Esta parte normalmente no se debería alcanzar
  return null;
};

export default OAuthCallback;
