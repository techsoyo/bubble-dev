/**
 * EJEMPLO: Página de Login Segura
 * Muestra cómo usar el nuevo sistema de autenticación seguro
 */

import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { useSanitizedForm } from '../../lib/auth/useSanitizedForm';
import { useCSRFProtection } from '../../lib/auth/csrfProtection';

interface LoginFormData {
  email: string;
  password: string;
  [key: string]: unknown; // Firma de índice para compatibilidad
}

export function SecureLoginPage() {
  const navigate = useNavigate();
  const { login, isLoading } = useAuth();
  const { csrfToken, isLoading: csrfLoading } = useCSRFProtection();

  // Usar hook de sanitización automática
  const {
    formData,
    validationErrors,
    updateField,
    validateAll,
    getSanitizedData,
    hasErrors
  } = useSanitizedForm<LoginFormData>({
    email: '',
    password: ''
  });

  const [loginError, setLoginError] = useState<string>('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Limpiar errores previos
    setLoginError('');

    // Validar formulario
    if (!validateAll()) {
      setLoginError('Please correct the validation errors');
      return;
    }

    // Verificar que tenemos protección CSRF
    if (!csrfToken) {
      setLoginError('Security error: Please refresh the page');
      return;
    }

    try {
      // Obtener datos sanitizados
      const sanitizedData = getSanitizedData();

      // Intentar login
      const success = await login(sanitizedData.email, sanitizedData.password);

      if (success) {
        // Redirigir después del login exitoso
        navigate('/dashboard');
      } else {
        setLoginError('Invalid email or password');
      }
    } catch (error) {
      console.error('Login error:', error);
      setLoginError('Login failed. Please try again.');
    }
  };

  // Mostrar loading si CSRF o auth están cargando
  if (csrfLoading || isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-2 text-gray-600">Loading secure login...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="max-w-md w-full space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
            Secure Sign In
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            Protected with CSRF, HTTP-only cookies, and input sanitization
          </p>
        </div>

        <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
          {/* CSRF Token Hidden Field */}
          <input type="hidden" name="csrf_token" value={csrfToken || ''} />

          <div className="space-y-4">
            {/* Email Field */}
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                Email address
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                required
                value={formData.email}
                onChange={(e) => updateField('email', e.target.value)}
                className={`mt-1 appearance-none rounded-md relative block w-full px-3 py-2 border 
                  ${validationErrors.email ? 'border-red-300' : 'border-gray-300'} 
                  placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 
                  focus:border-blue-500 focus:z-10 sm:text-sm`}
                placeholder="Enter your email"
              />
              {validationErrors.email && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.email}</p>
              )}
            </div>

            {/* Password Field */}
            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                Password
              </label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                value={formData.password}
                onChange={(e) => updateField('password', e.target.value)}
                className={`mt-1 appearance-none rounded-md relative block w-full px-3 py-2 border 
                  ${validationErrors.password ? 'border-red-300' : 'border-gray-300'} 
                  placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 
                  focus:border-blue-500 focus:z-10 sm:text-sm`}
                placeholder="Enter your password"
              />
              {validationErrors.password && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.password}</p>
              )}
            </div>
          </div>

          {/* Error Display */}
          {loginError && (
            <div className="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded">
              {loginError}
            </div>
          )}

          {/* Submit Button */}
          <div>
            <button
              type="submit"
              disabled={isLoading || hasErrors || !csrfToken}
              className={`group relative w-full flex justify-center py-2 px-4 border border-transparent 
                text-sm font-medium rounded-md text-white 
                ${isLoading || hasErrors || !csrfToken
                  ? 'bg-gray-400 cursor-not-allowed'
                  : 'bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500'
                }`}
            >
              {isLoading ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                  Signing in...
                </>
              ) : (
                'Sign in'
              )}
            </button>
          </div>

          {/* Security Info */}
          <div className="mt-4 text-xs text-gray-500 text-center">
            🔒 This form is protected by CSRF tokens, input sanitization,<br />
            and uses HTTP-only cookies for enhanced security
          </div>
        </form>
      </div>
    </div>
  );
}
