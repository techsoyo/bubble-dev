// src/pages/auth/LoginPage.tsx
import React from 'react';
import { useNavigate } from 'react-router-dom';
import LoginForm from '../../components/auth/LoginForm';
import { useAuth } from '../../contexts/AuthContext';

const LoginPage: React.FC = () => {
  const navigate = useNavigate();
  const { user } = useAuth();

  const handleLoginSuccess = (userData: any) => {
    // Redirección según el tipo de usuario
    if (user?.role === 'candidate') {
      navigate('/candidate/dashboard');
    } else if (user?.role === 'recruiter') {
      navigate('/recruiter/dashboard');
    } else if (user?.role === 'admin') {
      navigate('/admin');
    } else {
      navigate('/');
    }
  };

  const handleSocialLoginSuccess = (userData: any) => {
    // Similar al login normal, pero podría tener lógica diferente
    localStorage.setItem('user', JSON.stringify(userData));

    // Verificar si el usuario ya tiene perfil completo
    if (!userData.profileComplete) {
      navigate('/candidate/profile/complete');
      return;
    }

    // Redirección según el tipo de usuario
    if (userData.userType === 'candidate') {
      navigate('/candidate/dashboard');
    } else {
      navigate('/dashboard');
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <img
          src="/images/bb_azul.png"
          alt="Bubble of Talents Logo"
          className="mx-auto h-16 w-auto"
        />
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
          <LoginForm
            onLoginSuccess={handleLoginSuccess}
            onSocialLoginSuccess={handleSocialLoginSuccess}
          />
        </div>
      </div>

      <div className="mt-6 text-center">
        <p className="text-sm text-gray-600">
          Bubble of Talents &copy; {new Date().getFullYear()}
        </p>
      </div>
    </div>
  );
};

export default LoginPage;
