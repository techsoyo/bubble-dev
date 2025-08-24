
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../lib/api';
import { env } from '../config/env';

const LoginPage: React.FC = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const validate = () => {
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
      setError('Email inválido');
      return false;
    }
    if (password.length < 6) {
      setError('La contraseña debe tener al menos 6 caracteres');
      return false;
    }
    setError('');
    return true;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;
    setLoading(true);
    setError('');
    try {
      // MIGRATED: auth.php → /api/auth/login (REST endpoint)
      const res = await api.post('/api/auth/login', { email, password });
      const data = res.data;

      // REST response structure validation
      if (res.data && res.data.success) {
        const { token, user, expires_in } = res.data.data;

        // Guardar token si es necesario
        if (token) {
          localStorage.setItem('auth_token', token);
        }


        // ✅ Redirección inteligente por rol
        const redirectPath = user.role === 'admin' ? '/admin' :
          user.role === 'recruiter' ? '/recruiter' :
            '/dashboard';
        navigate(redirectPath, { replace: true });
      } else {
        setError(res.data?.message || 'Credenciales incorrectas');
      }

      // El backend REST debe establecer cookie JWT httpOnly si corresponde
      navigate('/dashboard');
    } catch (err: any) {
      // Enhanced error handling for REST API
      const errorMessage = err?.response?.data?.message ||
        err?.response?.data?.error ||
        'Error de red o del servidor';
      setError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-page">
      <form onSubmit={handleSubmit} className="login-form">
        <h2>Iniciar sesión</h2>
        <input
          type="email"
          placeholder="Email"
          value={email}
          onChange={e => setEmail(e.target.value)}
          required
        />
        <input
          type="password"
          placeholder="Contraseña"
          value={password}
          onChange={e => setPassword(e.target.value)}
          required
          minLength={6}
        />
        {error && <div className="error">{error}</div>}
        <button type="submit" disabled={loading}>
          {loading ? 'Ingresando...' : 'Ingresar'}
        </button>
      </form>
    </div>
  );
};

export default LoginPage;
