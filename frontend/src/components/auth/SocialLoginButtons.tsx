// src/components/auth/SocialLoginButtons.tsx
import React from 'react';
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
  // Esta es una función simulada para el MVP, en la implementación real
  // utilizaríamos las SDKs de autenticación reales de cada proveedor
  const handleSocialLogin = async (provider: 'google' | 'linkedin' | 'apple') => {
    try {
      // En la implementación real, aquí va el código de autenticación con el proveedor
      // que nos dará un token de acceso
      const mockToken = `mock_${provider}_token_${Date.now()}`;

      // Luego enviamos ese token a nuestro backend
      const response = await socialLogin(provider, mockToken);

      if (response.success) {
        onLoginSuccess && onLoginSuccess(response.data);
      } else {
        onLoginError && onLoginError(response.error);
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
