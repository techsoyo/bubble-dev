// src/pages/auth/RegisterCompletePage.tsx
import React from 'react';
import { useNavigate } from 'react-router-dom';
import RegisterFormComplete from '../../components/auth/RegisterFormComplete';

const RegisterCompletePage: React.FC = () => {
  const navigate = useNavigate();

  const handleRegisterSuccess = (userData: any) => {
    // Guardar datos del usuario en localStorage o context
    localStorage.setItem('user', JSON.stringify(userData));

    // Redirigir al usuario a la página de inicio o dashboard
    navigate('/dashboard/cddashboard');
  };

  const handleSocialLoginSuccess = (userData: any) => {
    // Similar al registro normal, pero podría tener lógica diferente
    localStorage.setItem('user', JSON.stringify(userData));

    // Verificar si el usuario ya tiene perfil completo o necesita completarlo
    if (userData.profileComplete) {
      navigate('/dashboard/cddashboard');
    } else {
      navigate('/dashboard/cddashboard');
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-6 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <img
          src="/images/bb_azul.png"
          alt="Bubble of Talents Logo"
          className="mx-auto h-16 w-auto"
        />
        <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Registro Completo
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          Crea tu perfil detallado para maximizar tus oportunidades
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
          <RegisterFormComplete
            onRegisterSuccess={handleRegisterSuccess}
            onSocialLoginSuccess={handleSocialLoginSuccess}
          />
        </div>
      </div>
    </div>
  );
};

export default RegisterCompletePage;
