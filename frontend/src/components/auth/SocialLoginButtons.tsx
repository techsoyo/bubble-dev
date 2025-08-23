// src/components/auth/SocialLoginButtons.tsx
import * as React from 'react';
import { FaGoogle, FaLinkedin, FaApple } from 'react-icons/fa';
import { socialLogin } from '../../lib/apiService';

interface SocialLoginButtonsProps {
  onLoginSuccess?: (data: any) => void;
  onLoginError?: (error: any) => void;
  className?: string;
}

const SocialLoginButtons: React.FC<SocialLoginButtonsProps> = ({
  onLoginSuccess,
  onLoginError,
  className = '',
}) => {
  // Implementación real de OAuth para proveedores sociales
  const handleSocialLogin = async (provider: 'google' | 'linkedin' | 'apple') => {
    try {
      let authUrl = '';

      // Configurar URLs de OAuth para cada proveedor
      switch (provider) {
        case 'google':
          authUrl = `https://accounts.google.com/o/oauth2/v2/auth?` +
            `client_id=${import.meta.env.VITE_GOOGLE_CLIENT_ID}&` +
            `redirect_uri=${encodeURIComponent(window.location.origin + '/auth/callback/google')}&` +
            `response_type=code&` +
            `scope=openid email profile&` +
            `state=google`;
          break;

        case 'linkedin':
          authUrl = `https://www.linkedin.com/oauth/v2/authorization?` +
            `client_id=${import.meta.env.VITE_LINKEDIN_CLIENT_ID}&` +
            `redirect_uri=${encodeURIComponent(window.location.origin + '/auth/callback/linkedin')}&` +
            `response_type=code&` +
            `scope=r_liteprofile r_emailaddress&` +
            `state=linkedin`;
          break;

        case 'apple':
          // Apple Sign-In requiere configuración más compleja
          // Para el MVP, mantenemos una implementación básica
          console.warn('Apple Sign-In requiere configuración adicional del dominio');
          throw new Error('Apple Sign-In no está disponible en esta versión');
      }

      if (authUrl) {
        // Redirigir al usuario a la página de OAuth del proveedor
        window.location.href = authUrl;
      }

    } catch (error) {
      onLoginError && onLoginError(error);
      console.error(`Error en login con ${provider}:`, error);
    }
  };

  return (
    <div className={`flex flex-col space-y-3 w-full ${className}`}>
      <button
        type="button"
        onClick={() => handleSocialLogin('google')}
        className="flex items-center justify-center gap-2 w-full py-2.5 px-4 border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors"
      >
        <FaGoogle className="text-[#4285F4]" />
        <span>Continuar con Google</span>
      </button>

      <button
        type="button"
        onClick={() => handleSocialLogin('linkedin')}
        className="flex items-center justify-center gap-2 w-full py-2.5 px-4 border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors"
      >
        <FaLinkedin className="text-[#0077B5]" />
        <span>Continuar con LinkedIn</span>
      </button>

      <button
        type="button"
        onClick={() => handleSocialLogin('apple')}
        className="flex items-center justify-center gap-2 w-full py-2.5 px-4 border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors"
      >
        <FaApple className="text-black" />
        <span>Continuar con Apple</span>
      </button>

      <div className="relative flex items-center justify-center my-2">
        <div className="border-t border-gray-300 w-full absolute"></div>
        <span className="bg-white px-3 relative text-sm text-gray-500">o</span>
      </div>
    </div>
  );
};

export default SocialLoginButtons;
