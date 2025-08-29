import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Label } from '../components/ui/label';
import { Input } from '../components/ui/input';
import { Button } from '../components/ui/button';
import { CardForm, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '../components/ui/card';
import { Alert, AlertDescription } from '../components/ui/alert';
import { Separator } from '../components/ui/separator';
import { useFormErrors } from '../hooks/useFormErrors';

// Rate limiting para intentos de login de staff
const useRateLimiter = () => {
  const [attempts, setAttempts] = useState<{ count: number; resetTime: number }>({
    count: 0,
    resetTime: 0
  });

  const isAllowed = (): boolean => {
    const now = Date.now();
    const windowMs = 15 * 60 * 1000; // 15 minutos
    const maxAttempts = 3; // Más restrictivo para staff

    if (now > attempts.resetTime) {
      // Reset window
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

// Hook simple para protección CSRF
const useCSRFProtection = () => {
  const generateCSRFToken = (): string => {
    const array = new Uint8Array(32);
    crypto.getRandomValues(array);
    return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
  };

  const [csrfToken] = useState(() => generateCSRFToken());

  return { csrfToken };
};

export default function StaffLogin() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [loginSuccess, setLoginSuccess] = useState(false);
  const navigate = useNavigate();
  const { staffLogin, user } = useAuth();

  // Inicializar hooks de seguridad
  const rateLimiter = useRateLimiter();
  const { csrfToken } = useCSRFProtection();
  const [fieldErrors, setFieldErrors] = useState<{ email?: string; password?: string }>({});

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
      default:
        return true;
    }
  };

  // Validación de email corporativo
  const validateEmail = (email: string): boolean => {
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    const corporateDomain = email.toLowerCase().includes('@bubblegum.agency');

    if (!emailRegex.test(email)) {
      setFieldErrors({ email: 'Formato de email inválido' });
      return false;
    }

    if (!corporateDomain) {
      setFieldErrors({ email: 'Solo se permiten emails corporativos de @bubblegum.agency' });
      return false;
    }

    clearFieldError('email');
    return true;
  };

  // Validación de contraseña
  const validatePassword = (password: string): boolean => {
    if (password.length < 8) {
      setFieldErrors({ password: 'La contraseña debe tener al menos 8 caracteres' });
      return false;
    }

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

    clearFieldError('password');
    return true;
  };

  // 🔧 SOLUCIÓN: useEffect que escucha cambios en user después del login
  useEffect(() => {
    if (loginSuccess && user && user.role) {

      // Redirección según el rol del usuario
      if (user.role === 'admin' || user.role === 'hr') {
        navigate('/dashboard/hrdashboard');
      } else if (user.role === 'recruiter') {
        navigate('/dashboard/recruiterdashboard');
      } else {
        navigate('/');
      }

      // Reset del flag
      setLoginSuccess(false);
    }
  }, [user, loginSuccess, navigate]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setError('');
    setFieldErrors({});

    try {
      // Verificar rate limiting
      if (!rateLimiter.isAllowed()) {
        const remainingTime = Math.ceil(rateLimiter.getRemainingTime() / 1000 / 60);
        setError(`Demasiados intentos fallidos. Intenta de nuevo en ${remainingTime} minutos.`);
        setIsLoading(false);
        return;
      }

      // Validar campos
      const isEmailValid = validateEmail(email);
      const isPasswordValid = validatePassword(password);

      if (!isEmailValid || !isPasswordValid) {
        setIsLoading(false);
        return;
      }

      // Sanitizar inputs
      const sanitizedEmail = email.trim().toLowerCase();
      const sanitizedPassword = password.trim();

      // Preparar headers con CSRF token
      const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      };

      // Usar el contexto de autenticación específico para staff
      const success = await staffLogin(sanitizedEmail, sanitizedPassword);

      if (success) {
        // 🔧 SOLUCIÓN: Activar el useEffect que escucha cambios en user
        setLoginSuccess(true);
      } else {
        setError('Credenciales incorrectas. Verifica tu email y contraseña.');
      }
    } catch (error) {
      console.error('Staff login error:', error);
      // Error genérico para evitar información sensible
      setError('Error de conexión. Intenta de nuevo.');
    } finally {
      setIsLoading(false);
    }
  }; return (
    <div className="min-h-screen flex items-center justify-center px-4" style={{
      overflow: 'hidden'
    }}>
      <div className="w-full max-w-md relative" style={{
        perspective: '1000px'
      }}>
        <CardForm className="shadow-lg" style={{ background: '#2f2f2f' }}>
          <CardHeader className="text-center">
            <CardTitle className="text-2xl font-bold text-white">Personal de la Empresa</CardTitle>
            <CardDescription className="text-gray-300">
              Acceso para empleados de Bubblegum Agency
            </CardDescription>
          </CardHeader>

          <CardContent>
            {error && (
              <Alert variant="destructive" className="mb-6">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}

            <form onSubmit={handleSubmit} className="space-y-4" noValidate style={{ color: 'black' }}>
              <div className="space-y-2">
                <Label htmlFor="staff-login-email" className="text-white">Email corporativo</Label>
                <Input
                  id="staff-login-email"
                  type="email"
                  placeholder="tu.email@bubblegum.agency"
                  value={email}
                  onChange={(e) => {
                    setEmail(e.target.value);
                    if (fieldErrors.email) {
                      validateField('email', e.target.value);
                    }
                  }}
                  onBlur={(e) => validateField('email', e.target.value)}
                  required
                  className={fieldErrors.email ? 'border-red-500' : ''}
                />
                {fieldErrors.email && (
                  <p className="text-sm text-red-500 mt-1">{fieldErrors.email}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="staff-login-password" className="text-white">Contraseña</Label>
                <Input
                  id="staff-login-password"
                  type="password"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => {
                    setPassword(e.target.value);
                    if (fieldErrors.password) {
                      validateField('password', e.target.value);
                    }
                  }}
                  onBlur={(e) => validateField('password', e.target.value)}
                  required
                  className={fieldErrors.password ? 'border-red-500' : ''}
                />
                {fieldErrors.password && (
                  <p className="text-sm text-red-500 mt-1">{fieldErrors.password}</p>
                )}
              </div>

              <Button
                type="submit"
                className="w-full bg-primary hover:bg-primary-hover"
                disabled={isLoading}
              >
                {isLoading ? 'Iniciando sesión...' : 'Iniciar Sesión'}
              </Button>
            </form>
          </CardContent>

          <CardFooter className="flex-col">
            <Separator className="mb-4" />
            <div className="text-center">
              <button
                type="button"
                onClick={() => navigate('/')}
                className="text-sm font-medium hover:underline"
                style={{ color: 'var(--color-primary)' }}
              >
                Volver al inicio
              </button>
            </div>
          </CardFooter>
        </CardForm>
      </div>
    </div>
  );
}
