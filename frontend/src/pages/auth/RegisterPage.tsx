// src/pages/auth/RegisterPage.tsx
import React from 'react';
import { useNavigate } from 'react-router-dom';
import RegisterForm from '../../components/auth/RegisterForm';

const RegisterPage: React.FC = () => {
  const navigate = useNavigate();

  const handleRegisterSuccess = (userData: any) => {
    // Guardar datos del usuario en localStorage o context
    localStorage.setItem('user', JSON.stringify(userData));

    // Redirigir al usuario a la página de inicio o a completar perfil
    navigate('/candidate/profile/complete');
  };

  const handleSocialLoginSuccess = (userData: any) => {
    // Similar al registro normal, pero podría tener lógica diferente
    localStorage.setItem('user', JSON.stringify(userData));

    // Verificar si el usuario ya tiene perfil completo o necesita completarlo
    if (userData.profileComplete) {
      navigate('/dashboard');
    } else {
      navigate('/candidate/profile/complete');
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <img
          src="/images/bb_azul.png"
          alt="Bubble Talents Logo"
          className="mx-auto h-16 w-auto"
        />
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
          <RegisterForm
            onRegisterSuccess={handleRegisterSuccess}
            onSocialLoginSuccess={handleSocialLoginSuccess}
          />
        </div>
      </div>

      <div className="mt-6 text-center">
        <p className="text-sm text-gray-600">
          Bubble Talents &copy; {new Date().getFullYear()}
        </p>
      </div>
    </div>
  );
};

export default RegisterPage;
