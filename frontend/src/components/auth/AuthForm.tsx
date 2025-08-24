/**
 * Login/Register Form - UPDATED WITH NEW ENDPOINTS
 * ✅ ACTUALIZADO: Soporte para nuevos endpoints
 * - Candidatos: Login y Registro con /auth/register
 * - Staff: Solo Login con /staff/login
 */

import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { formSubmissionLimiter, generateClientFingerprint } from '../../security/xss';
import { InputSanitizer } from '../../lib/auth/secureInputValidator';

interface AuthFormProps {
  className?: string;
  userType: 'candidate' | 'staff'; // ✅ NUEVO: Determina qué endpoint usar
  mode?: 'login' | 'register'; // ✅ NUEVO: Para candidatos, permite alternar
  onSuccess?: (data: any) => void;
}

const AuthForm: React.FC<AuthFormProps> = ({
  className = '',
  userType,
  mode: initialMode = 'login',
  onSuccess
}) => {
  // Estados del formulario
  const [mode, setMode] = useState(initialMode);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [localError, setLocalError] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);

  const navigate = useNavigate();
  const location = useLocation();
  const {
    candidateLogin,
    staffLogin,
    registerCandidate,
    isLoading,
    error: authError,
    clearError
  } = useAuth();

  // ✅ Limpiar errores cuando cambian los inputs
  useEffect(() => {
    if (localError) setLocalError('');
    if (authError) clearError();
  }, [email, password, confirmPassword, firstName, lastName]);

  // ✅ VALIDACIÓN MEJORADA
  const validateInput = () => {
    if (!email.trim()) {
      setLocalError('El email es requerido');
      return false;
    }

    if (!InputSanitizer.sanitizeEmail(email)) {
      setLocalError('Formato de email inválido');
      return false;
    }

    if (!password.trim()) {
      setLocalError('La contraseña es requerida');
      return false;
    }

    if (password.length < 6) {
      setLocalError('La contraseña debe tener al menos 6 caracteres');
      return false;
    }

    // Validaciones específicas para registro
    if (mode === 'register' && userType === 'candidate') {
      if (password !== confirmPassword) {
        setLocalError('Las contraseñas no coinciden');
        return false;
      }

      if (!firstName.trim()) {
        setLocalError('El nombre es requerido');
        return false;
      }

      if (!lastName.trim()) {
        setLocalError('El apellido es requerido');
        return false;
      }
    }

    return true;
  };

  // ✅ MANEJO DE SUBMIT UNIFICADO
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    setLocalError('');
    clearError();

    if (!validateInput()) {
      return;
    }

    // ✅ RATE LIMITING
    const fingerprint = generateClientFingerprint();
    if (!formSubmissionLimiter.isAllowed(fingerprint)) {
      setLocalError('Demasiados intentos. Espera 1 minuto antes de intentarlo de nuevo.');
      return;
    }

    try {
      let authResponse;

      if (mode === 'login') {
        // ✅ LOGIN SEGÚN TIPO DE USUARIO
        if (userType === 'candidate') {
          authResponse = await candidateLogin(email, password);
        } else {
          authResponse = await staffLogin(email, password);
        }
      } else {
        // ✅ REGISTRO (SOLO CANDIDATOS)
        if (userType !== 'candidate') {
          setLocalError('El registro no está disponible para staff');
          return;
        }

        authResponse = await registerCandidate({
          email,
          password,
          first_name: firstName,
          last_name: lastName,
          name: `${firstName} ${lastName}`.trim()
        });
      }

      if (authResponse.success) {
        // ✅ CALLBACK SUCCESS
        onSuccess && onSuccess({
          email,
          user: authResponse.user,
          userType,
          mode
        });

        // ✅ REDIRIGIR SEGÚN CONTEXTO
        const from = (location.state as any)?.from?.pathname || '/';
        navigate(from, { replace: true });

        console.log(`✅ ${mode} exitoso para ${userType}, redirigiendo a:`, from);
      } else {
        setLocalError(authResponse.message || `Error en ${mode}`);
      }
    } catch (err: any) {
      console.error(`❌ Error en ${mode}:`, err);
      setLocalError(err.message || 'Error inesperado');
    }
  };

  // ✅ TÍTULOS DINÁMICOS
  const getTitle = () => {
    const action = mode === 'login' ? 'Iniciar Sesión' : 'Crear Cuenta';
    const type = userType === 'candidate' ? 'Candidatos' : 'Personal/RRHH';
    return `${action} - ${type}`;
  };

  // ✅ CREDENCIALES DE PRUEBA
  const getTestCredentials = () => {
    if (userType === 'candidate') {
      return 'candidate@bubble.com / candidate123';
    } else {
      return 'recruiter@bubble.com / recruiter123 (o admin@bubble.com / admin123)';
    }
  };

  // ✅ MOSTRAR ERROR
  const displayError = localError || authError;

  // ✅ PERMITIR CAMBIO DE MODO SOLO PARA CANDIDATOS
  const canToggleMode = userType === 'candidate';

  return (
    <div className={`auth-form-container ${className}`}>
      <form onSubmit={handleSubmit} className="auth-form">
        <h2 className="auth-title">{getTitle()}</h2>

        {/* ✅ TOGGLE LOGIN/REGISTER PARA CANDIDATOS */}
        {canToggleMode && (
          <div className="mode-toggle">
            <button
              type="button"
              onClick={() => setMode('login')}
              className={mode === 'login' ? 'active' : ''}
              disabled={isLoading}
            >
              Iniciar Sesión
            </button>
            <button
              type="button"
              onClick={() => setMode('register')}
              className={mode === 'register' ? 'active' : ''}
              disabled={isLoading}
            >
              Crear Cuenta
            </button>
          </div>
        )}

        {/* ✅ CAMPOS DE REGISTRO (SOLO CANDIDATOS EN MODO REGISTER) */}
        {mode === 'register' && userType === 'candidate' && (
          <>
            <div className="form-group">
              <label htmlFor="firstName">Nombre:</label>
              <input
                id="firstName"
                type="text"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value.trim())}
                placeholder="Tu nombre"
                required
                autoComplete="given-name"
                disabled={isLoading}
              />
            </div>

            <div className="form-group">
              <label htmlFor="lastName">Apellido:</label>
              <input
                id="lastName"
                type="text"
                value={lastName}
                onChange={(e) => setLastName(e.target.value.trim())}
                placeholder="Tu apellido"
                required
                autoComplete="family-name"
                disabled={isLoading}
              />
            </div>
          </>
        )}

        {/* ✅ EMAIL INPUT */}
        <div className="form-group">
          <label htmlFor="email">Email:</label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value.trim())}
            placeholder="tu@email.com"
            required
            autoComplete="email"
            disabled={isLoading}
            className={displayError && !InputSanitizer.sanitizeEmail(email) ? 'error' : ''}
          />
        </div>

        {/* ✅ PASSWORD INPUT */}
        <div className="form-group">
          <label htmlFor="password">Contraseña:</label>
          <div className="password-input-container">
            <input
              id="password"
              type={showPassword ? 'text' : 'password'}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Tu contraseña"
              required
              autoComplete={mode === 'register' ? 'new-password' : 'current-password'}
              disabled={isLoading}
              className={displayError && password.length < 6 ? 'error' : ''}
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="password-toggle"
              disabled={isLoading}
              aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
            >
              {showPassword ? '👁️‍🗨️' : '👁️'}
            </button>
          </div>
        </div>

        {/* ✅ CONFIRMAR PASSWORD (SOLO REGISTRO) */}
        {mode === 'register' && userType === 'candidate' && (
          <div className="form-group">
            <label htmlFor="confirmPassword">Confirmar Contraseña:</label>
            <input
              id="confirmPassword"
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              placeholder="Confirma tu contraseña"
              required
              autoComplete="new-password"
              disabled={isLoading}
              className={displayError && password !== confirmPassword ? 'error' : ''}
            />
          </div>
        )}

        {/* ✅ REMEMBER ME (SOLO LOGIN STAFF) */}
        {mode === 'login' && userType === 'staff' && (
          <div className="form-group checkbox-group">
            <label className="checkbox-label">
              <input
                type="checkbox"
                checked={rememberMe}
                onChange={(e) => setRememberMe(e.target.checked)}
                disabled={isLoading}
              />
              Recordarme
            </label>
          </div>
        )}

        {/* ✅ CREDENCIALES DE PRUEBA (SOLO LOGIN) */}
        {mode === 'login' && (
          <div className="test-credentials">
            <small>
              <strong>Prueba con:</strong> {getTestCredentials()}
            </small>
          </div>
        )}

        {/* ✅ ERROR DISPLAY */}
        {displayError && (
          <div className="error-message" role="alert">
            <span className="error-icon">⚠️</span>
            {displayError}
          </div>
        )}

        {/* ✅ SUBMIT BUTTON */}
        <button
          type="submit"
          disabled={isLoading || !email || !password}
          className={`submit-button ${isLoading ? 'loading' : ''}`}
        >
          {isLoading ? (
            <>
              <span className="loading-spinner">⏳</span>
              {mode === 'login' ? 'Iniciando sesión...' : 'Creando cuenta...'}
            </>
          ) : (
            mode === 'login' ? 'Entrar' : 'Crear Cuenta'
          )}
        </button>

        {/* ✅ LINKS ADICIONALES */}
        <div className="form-links">
          {mode === 'login' && (
            <a href="/auth/forgot-password" className="forgot-password-link">
              ¿Olvidaste tu contraseña?
            </a>
          )}
        </div>
      </form>

      {/* ✅ ESTILOS CSS ACTUALIZADOS */}
      <style>{`
        .auth-form-container {
          max-width: 450px;
          margin: 2rem auto;
          padding: 2rem;
          border: 1px solid #ddd;
          border-radius: 8px;
          background: white;
          box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .auth-form {
          display: flex;
          flex-direction: column;
          gap: 1rem;
        }
        
        .auth-title {
          text-align: center;
          margin-bottom: 1.5rem;
          color: #333;
          font-size: 1.5rem;
        }
        
        .mode-toggle {
          display: flex;
          gap: 0;
          border: 1px solid #ddd;
          border-radius: 6px;
          overflow: hidden;
          margin-bottom: 1rem;
        }
        
        .mode-toggle button {
          flex: 1;
          padding: 0.75rem;
          background: #f8f9fa;
          border: none;
          cursor: pointer;
          transition: all 0.2s;
        }
        
        .mode-toggle button.active {
          background: #007bff;
          color: white;
        }
        
        .mode-toggle button:hover:not(:disabled):not(.active) {
          background: #e9ecef;
        }
        
        .form-group {
          display: flex;
          flex-direction: column;
          gap: 0.5rem;
        }
        
        .form-group label {
          font-weight: 600;
          color: #555;
        }
        
        .form-group input {
          padding: 0.75rem;
          border: 1px solid #ddd;
          border-radius: 4px;
          font-size: 1rem;
        }
        
        .form-group input:focus {
          outline: none;
          border-color: #007bff;
          box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .form-group input.error {
          border-color: #dc3545;
          box-shadow: 0 0 0 2px rgba(220,53,69,0.25);
        }
        
        .password-input-container {
          position: relative;
          display: flex;
          align-items: center;
        }
        
        .password-toggle {
          position: absolute;
          right: 0.75rem;
          background: none;
          border: none;
          cursor: pointer;
          padding: 0.25rem;
        }
        
        .checkbox-group .checkbox-label {
          flex-direction: row;
          align-items: center;
          gap: 0.5rem;
          font-weight: normal;
        }
        
        .test-credentials {
          text-align: center;
          padding: 0.75rem;
          background-color: #f8f9fa;
          border-radius: 4px;
          border: 1px solid #e9ecef;
        }
        
        .test-credentials small {
          color: #666;
          font-size: 0.875rem;
        }
        
        .error-message {
          padding: 0.75rem;
          background-color: #f8d7da;
          border: 1px solid #f5c6cb;
          color: #721c24;
          border-radius: 4px;
          display: flex;
          align-items: center;
          gap: 0.5rem;
        }
        
        .submit-button {
          padding: 0.75rem 1.5rem;
          background-color: #007bff;
          color: white;
          border: none;
          border-radius: 4px;
          font-size: 1rem;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.2s;
        }
        
        .submit-button:hover:not(:disabled) {
          background-color: #0056b3;
        }
        
        .submit-button:disabled {
          background-color: #6c757d;
          cursor: not-allowed;
        }
        
        .submit-button.loading {
          background-color: #6c757d;
        }
        
        .loading-spinner {
          display: inline-block;
          animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
        
        .form-links {
          text-align: center;
          margin-top: 1rem;
        }
        
        .form-links a {
          color: #007bff;
          text-decoration: none;
          font-size: 0.875rem;
        }
        
        .form-links a:hover {
          text-decoration: underline;
        }
      `}</style>
    </div>
  );
};

export default AuthForm;
