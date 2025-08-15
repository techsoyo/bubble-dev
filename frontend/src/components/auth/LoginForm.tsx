import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

interface LoginFormProps {
  onLoginSuccess?: (data: any) => void;
  onSocialLoginSuccess?: (data: any) => void;
  className?: string;
}

const LoginForm: React.FC<LoginFormProps> = ({
  onLoginSuccess,
  onSocialLoginSuccess,
  className = '',
}) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const navigate = useNavigate();
  const { login } = useAuth();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsLoading(true);

    try {
      const success = await login(email, password);

      if (success) {
        onLoginSuccess && onLoginSuccess({ email });
        navigate('/');
      } else {
        setError('Email o contraseña incorrectos');
      }
    } catch (err: any) {
      setError(err.message || 'Error al iniciar sesión');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <h2>Iniciar sesión</h2>

      <label>Email:</label>
      <input
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        required
      />

      <label>Contraseña:</label>
      <input
        type="password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        required
      />

      <p style={{ fontSize: '0.8rem', color: '#666' }}>
        Credenciales de prueba: admin@bubble.com/admin123, recruiter@bubble.com/recruiter123, candidate@bubble.com/candidate123
      </p>

      {error && <p style={{ color: 'red' }}>{error}</p>}

      <button type="submit" disabled={isLoading}>
        {isLoading ? 'Iniciando sesión...' : 'Entrar'}
      </button>
    </form>
  );
};

export default LoginForm;
