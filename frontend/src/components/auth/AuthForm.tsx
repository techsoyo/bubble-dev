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

// Rate limiting mejorado para AuthForm
const useEnhancedRateLimiter = (userType: 'candidate' | 'staff') => {
  const [attempts, setAttempts] = useState<{ count: number; resetTime: number }>({
    count: 0,
    resetTime: 0
  });

  const isAllowed = (): boolean => {
    const now = Date.now();
    const windowMs = userType === 'staff' ? 15 * 60 * 1000 : 5 * 60 * 1000; // 15 min para staff, 5 min para candidatos
    const maxAttempts = userType === 'staff' ? 3 : 5; // Más restrictivo para staff

    if (now > attempts.resetTime) {
      setAttempts({ count: 1, resetTime: now + windowMs });
      return true;
    }

    if (attempts.count >= maxAttempts) {
      return false;
    }

    setAttempts(prev => ({ ...prev, count: prev.count + 1 }));
    return true;
  };

  const getRemainingTime = (): number => {
    const now = Date.now();
    return Math.max(0, attempts.resetTime - now);
  };

  return { isAllowed, getRemainingTime };
};

// Hook para protección CSRF
const useCSRFProtection = () => {
  const generateCSRFToken = (): string => {
    const array = new Uint8Array(32);
    crypto.getRandomValues(array);
    return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
  };

  const [csrfToken] = useState(() => generateCSRFToken());

  return { csrfToken };
};

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
  const [fieldErrors, setFieldErrors] = useState<{ email?: string; password?: string; confirmPassword?: string }>({});

  const navigate = useNavigate();
  const location = useLocation();

  // Inicializar hooks de seguridad
  const rateLimiter = useEnhancedRateLimiter(userType);
  const { csrfToken } = useCSRFProtection();

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

  // Función para limpiar errores de campo específico
  const clearFieldError = (field: string) => {
    setFieldErrors(prev => ({ ...prev, [field]: undefined }));
  };

  // Función para validar campo individual
  const validateField = (field: string, value: string): boolean => {
    switch (field) {
      case 'email':
        return validateEmail(value);
      case 'password':
        return validatePassword(value);
      case 'confirmPassword':
        return validateConfirmPassword(value);
      default:
        return true;
    }
  };

  // ✅ VALIDACIÓN MEJORADA DE EMAIL
  const validateEmail = (email: string): boolean => {
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    const corporateDomain = userType === 'staff' ? email.toLowerCase().includes('@bubblegum.agency') : true;

    if (!emailRegex.test(email)) {
      setFieldErrors({ email: 'Formato de email inválido' });
      return false;
    }

    if (userType === 'staff' && !corporateDomain) {
      setFieldErrors({ email: 'Solo se permiten emails corporativos de @bubblegum.agency' });
      return false;
    }

    clearFieldError('email');
    return true;
  };

  // ✅ VALIDACIÓN MEJORADA DE CONTRASEÑA
  const validatePassword = (password: string): boolean => {
    if (password.length < 8) {
      setFieldErrors({ password: 'La contraseña debe tener al menos 8 caracteres' });
      return false;
    }

    if (userType === 'staff') {
      const hasUpperCase = /[A-Z]/.test(password);
      const hasLowerCase = /[a-z]/.test(password);
      const hasNumbers = /\d/.test(password);
      const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);

      if (!hasUpperCase || !hasLowerCase || !hasNumbers || !hasSpecialChar) {
        setFieldErrors({
          password: 'La contraseña debe contener mayúsculas, minúsculas, números y caracteres especiales'
        });
        return false;
      }
    }

    clearFieldError('password');
    return true;
  };

  // ✅ VALIDACIÓN DE CONFIRMACIÓN DE CONTRASEÑA
  const validateConfirmPassword = (confirmPassword: string): boolean => {
    if (password !== confirmPassword) {
      setFieldErrors({ confirmPassword: 'Las contraseñas no coinciden' });
      return false;
    }

    clearFieldError('confirmPassword');
    return true;
  };

  // ✅ MANEJO DE SUBMIT UNIFICADO CON SEGURIDAD MEJORADA
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    setLocalError('');
    setFieldErrors({});
    clearError();

    // Validar campos
    const isEmailValid = validateEmail(email);
    const isPasswordValid = validatePassword(password);
    let isConfirmPasswordValid = true;

    if (mode === 'register' && userType === 'candidate') {
      isConfirmPasswordValid = validateConfirmPassword(confirmPassword);

      if (!firstName.trim()) {
        setFieldErrors(prev => ({ ...prev, firstName: 'El nombre es requerido' }));
        return;
      }

      if (!lastName.trim()) {
        setFieldErrors(prev => ({ ...prev, lastName: 'El apellido es requerido' }));
        return;
      }
    }

    if (!isEmailValid || !isPasswordValid || !isConfirmPasswordValid) {
      return;
    }

    // ✅ RATE LIMITING MEJORADO
    if (!rateLimiter.isAllowed()) {
      const remainingTime = Math.ceil(rateLimiter.getRemainingTime() / 1000 / 60);
      setLocalError(`Demasiados intentos fallidos. Intenta de nuevo en ${remainingTime} minutos.`);
      return;
    }

    // ✅ RATE LIMITING ADICIONAL (LEGACY)
    const fingerprint = generateClientFingerprint();
    if (!formSubmissionLimiter.isAllowed(fingerprint)) {
      setLocalError('Demasiados intentos. Espera 1 minuto antes de intentarlo de nuevo.');
      return;
    }

    try {
      let authResponse;

      // Sanitizar inputs
      const sanitizedEmail = email.trim().toLowerCase();
      const sanitizedPassword = password.trim();

      if (mode === 'login') {
        // ✅ LOGIN SEGÚN TIPO DE USUARIO
        if (userType === 'candidate') {
          authResponse = await candidateLogin(sanitizedEmail, sanitizedPassword);
        } else {
          authResponse = await staffLogin(sanitizedEmail, sanitizedPassword);
        }
      } else {
        // ✅ REGISTRO (SOLO CANDIDATOS)
        if (userType !== 'candidate') {
          setLocalError('El registro no está disponible para staff');
          return;
        }

        authResponse = await registerCandidate({
          email: sanitizedEmail,
          password: sanitizedPassword,
          first_name: firstName.trim(),
          last_name: lastName.trim(),
          name: `${firstName.trim()} ${lastName.trim()}`
        });
      }

      if (authResponse.success) {
        // ✅ CALLBACK SUCCESS
        onSuccess && onSuccess({
          email: sanitizedEmail,
          user: authResponse.user,
          userType,
          mode
        });

        // ✅ REDIRIGIR SEGÚN CONTEXTO
        const from = (location.state as any)?.from?.pathname || '/';
        navigate(from, { replace: true });

      } else {
        setLocalError(authResponse.message || `Error en ${mode}`);
      }
    } catch (err: any) {
      console.error(`❌ Error en ${mode}:`, err);
      // Error genérico para evitar información sensible
      setLocalError('Error de conexión. Intenta de nuevo.');
    }
  };

  // ✅ TÍTULOS DINÁMICOS
  const getTitle = () => {
    const action = mode === 'login' ? 'Iniciar Sesión' : 'Crear Cuenta';
    const type = userType === 'candidate' ? 'Candidatos' : 'Personal/RRHH';
    return `${action} - ${type}`;
  };

  // ✅ CREDENCIALES DE PRUEBA ACTUALIZADAS
  const getTestCredentials = () => {
    if (userType === 'candidate') {
      return 'candidate@bubble.com / candidate123';
    } else {
      return 'recruiter@bubblegum.agency / Recruiter123! (debe incluir mayúsculas, minúsculas, números y caracteres especiales)';
    }
  };

  // ✅ REQUISITOS DE CONTRASEÑA
  const getPasswordRequirements = () => {
    if (userType === 'staff') {
      return 'La contraseña debe tener al menos 8 caracteres e incluir mayúsculas, minúsculas, números y caracteres especiales.';
    }
    return 'La contraseña debe tener al menos 8 caracteres.';
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

        {/* ✅ EMAIL INPUT CON VALIDACIÓN MEJORADA */}
        <div className="form-group">
          <label htmlFor="email">Email:</label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => {
              setEmail(e.target.value);
              if (fieldErrors.email) {
                validateField('email', e.target.value);
              }
            }}
            onBlur={(e) => validateField('email', e.target.value)}
            placeholder={userType === 'staff' ? "tu.email@bubblegum.agency" : "tu@email.com"}
            required
            autoComplete="email"
            disabled={isLoading}
            className={fieldErrors.email ? 'error' : ''}
          />
          {fieldErrors.email && (
            <p className="field-error" role="alert">{fieldErrors.email}</p>
          )}
        </div>

        {/* ✅ PASSWORD INPUT CON VALIDACIÓN MEJORADA */}
        <div className="form-group">
          <label htmlFor="password">Contraseña:</label>
          <div className="password-input-container">
            <input
              id="password"
              type={showPassword ? 'text' : 'password'}
              value={password}
              onChange={(e) => {
                setPassword(e.target.value);
                if (fieldErrors.password) {
                  validateField('password', e.target.value);
                }
              }}
              onBlur={(e) => validateField('password', e.target.value)}
              placeholder="Tu contraseña"
              required
              autoComplete={mode === 'register' ? 'new-password' : 'current-password'}
              disabled={isLoading}
              className={fieldErrors.password ? 'error' : ''}
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
          {fieldErrors.password && (
            <p className="field-error" role="alert">{fieldErrors.password}</p>
          )}
        </div>

        {/* ✅ CONFIRMAR PASSWORD CON VALIDACIÓN MEJORADA */}
        {mode === 'register' && userType === 'candidate' && (
          <div className="form-group">
            <label htmlFor="confirmPassword">Confirmar Contraseña:</label>
            <input
              id="confirmPassword"
              type="password"
              value={confirmPassword}
              onChange={(e) => {
                setConfirmPassword(e.target.value);
                if (fieldErrors.confirmPassword) {
                  validateField('confirmPassword', e.target.value);
                }
              }}
              onBlur={(e) => validateField('confirmPassword', e.target.value)}
              placeholder="Confirma tu contraseña"
              required
              autoComplete="new-password"
              disabled={isLoading}
              className={fieldErrors.confirmPassword ? 'error' : ''}
            />
            {fieldErrors.confirmPassword && (
              <p className="field-error" role="alert">{fieldErrors.confirmPassword}</p>
            )}
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

        {/* ✅ CREDENCIALES DE PRUEBA Y REQUISITOS */}
        {mode === 'login' && (
          <div className="test-credentials">
            <small>
              <strong>Prueba con:</strong> {getTestCredentials()}
            </small>
          </div>
        )}

        {/* ✅ REQUISITOS DE CONTRASEÑA PARA STAFF */}
        {userType === 'staff' && (
          <div className="password-requirements">
            <small className="requirements-text">
              <strong>Requisitos de contraseña:</strong> {getPasswordRequirements()}
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
