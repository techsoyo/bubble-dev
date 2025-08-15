// src/pages/TalentLogin.tsx
import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

export default function TalentLogin() {
  const navigate = useNavigate();

  useEffect(() => {
    // Redirigir al login de staff en lugar de SSO
    navigate('/staff/login');
  }, [navigate]);

  return (
    <div className="min-h-screen flex items-center justify-center">
      <p>Redirigiendo al sistema de personal...</p>
    </div>
  );
}
