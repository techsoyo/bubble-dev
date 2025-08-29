import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import {
  Card,
  CardForm,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '../../components/ui/card';
import { Alert, AlertDescription } from '../../components/ui/alert';
import { Separator } from '../../components/ui/separator';
import { useFormErrors } from '../../hooks/useFormErrors';

// Rate limiting para intentos de login
const useRateLimiter = () => {
  const [attempts, setAttempts] = useState<{ count: number; resetTime: number }>({
    count: 0,
    resetTime: 0
  });

  const isAllowed = (): boolean => {
    const now = Date.now();
    const windowMs = 15 * 60 * 1000; // 15 minutos
    const maxAttempts = 5;

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

export default function LoginPage() {
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const { errors, setError, clearAllErrors, hasError } = useFormErrors();

  // Protección CSRF
  const { csrfToken } = useCSRFProtection();

  // Rate limiting
  const { isAllowed, getRemainingTime } = useRateLimiter();

  // Limpiar los campos del formulario cuando se monta el componente
  useEffect(() => {
    // Aseguramos que los campos estén vacíos al cargar el formulario
    setEmail('');
    setPassword('');

    // El contexto de autenticación maneja la sesión de forma segura con cookies
  }, []);

  const validateForm = (): boolean => {
    clearAllErrors();
    let isValid = true;

    if (!email.trim()) {
      setError('email', 'El correo electrónico es obligatorio');
      isValid = false;
    }

    if (!password.trim()) {
      setError('password', 'La contraseña es obligatoria');
      isValid = false;
    }

    return isValid;
  };

  const { login, user, loginWithSocial } = useAuth();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    // Rate limiting check
    if (!isAllowed()) {
      const remainingTime = Math.ceil(getRemainingTime() / 1000 / 60);
      setError('general', `Demasiados intentos de inicio de sesión. Inténtalo nuevamente en ${remainingTime} minutos.`);
      return;
    }

    setIsLoading(true);

    try {
      // Usar nuestro contexto de autenticación
      const success = await login(email, password);

      if (success) {
        // Redirección según el rol del usuario
        const userRole = user?.role || '';

        // Redirección según el rol
        if (userRole === 'hr' || userRole === 'admin') {
          navigate('/dashboard/hrdashboard');
        } else if (userRole === 'recruiter') {
          navigate('/dashboard/recruiterdashboard');
        } else if (userRole === 'candidate') {
          navigate('/dashboard/cddashboard');
        } else {
          navigate('/dashboard/cddashboard');
        }
      } else {
        setError('general', 'Credenciales incorrectas. Verifica tu correo y contraseña.');
      }
    } catch (error) {
      setError('general', 'Error de conexión. Inténtalo nuevamente.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleSocialLogin = async (provider: string) => {
    clearAllErrors();
    setIsLoading(true);

    try {
      // Usar el contexto de autenticación para login social
      const success = await loginWithSocial(provider, {});

      if (success) {
        // Redirección según el rol del usuario
        const userRole = user?.role || '';

        // Redirección según el rol, similar al login normal
        if (userRole === 'hr' || userRole === 'admin') {
          navigate('/dashboard/hrdashboard');
        } else if (userRole === 'recruiter') {
          navigate('/dashboard/recruiterdashboard');
        } else {
          navigate('/dashboard/cddashboard');
        }
      } else {
        setError('general', `No se encontró una cuenta vinculada con ${provider === 'google' ? 'Google' : 'LinkedIn'}`);
      }
    } catch (error) {
      setError('general', 'Error en el login social. Inténtalo nuevamente.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="container mx-auto px-4 py-16 max-w-md">
      <CardForm className="shadow-md">
        <CardHeader className="text-center">
          <CardTitle className="text-2xl">Login</CardTitle>
          <CardDescription>
            Accede a tu cuenta para ver tus postulaciones
          </CardDescription>
        </CardHeader>
        <CardContent>
          {hasError('general') && (
            <Alert variant="destructive" className="mb-6">
              <AlertDescription>{errors.general}</AlertDescription>
            </Alert>
          )}

          <form onSubmit={handleSubmit} className="space-y-4" noValidate style={{ color: 'black' }}>
            {/* CSRF Token Protection */}
            <input type="hidden" name="csrf_token" value={csrfToken} />
            <div className="space-y-2">
              <Label htmlFor="auth-login-email">Correo electrónico</Label>
              <Input
                id="auth-login-email"
                name="email"
                type="email"
                placeholder="tu@email.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                aria-required="true"
                aria-invalid={hasError('email')}
                aria-describedby={hasError('email') ? 'email-error' : undefined}
                autoComplete="email"
              />
              {hasError('email') && (
                <p id="email-error" className="text-sm text-red-600" role="alert">
                  {errors.email}
                </p>
              )}
            </div>

            <div className="space-y-2">
              <div className="flex justify-between">
                <Label htmlFor="password">Contraseña</Label>
                <Link
                  to="#"
                  className="text-sm text-primary hover:underline"
                  onClick={(e) => {
                    e.preventDefault();
                    alert('¿Olvidaste tu contraseña? Esta funcionalidad estará disponible próximamente.');
                  }}
                  aria-label="Recuperar contraseña (funcionalidad próximamente)"
                >
                  ¿Olvidaste tu contraseña?
                </Link>
              </div>
              <Input
                id="auth-login-password"
                name="password"
                type="password"
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                aria-required="true"
                aria-invalid={hasError('password')}
                aria-describedby={hasError('password') ? 'password-error' : undefined}
                autoComplete="current-password"
              />
              {hasError('password') && (
                <p id="password-error" className="text-sm text-red-600" role="alert">
                  {errors.password}
                </p>
              )}
            </div>

            <Button
              type="submit"
              className="w-full bg-primary hover:bg-primary-hover"
              disabled={isLoading}
              aria-describedby={isLoading ? 'loading-message' : undefined}
            >
              {isLoading ? (
                <>
                  <span className="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
                  Iniciando sesión...
                </>
              ) : (
                'Iniciar sesión'
              )}
            </Button>
            {isLoading && (
              <p id="loading-message" className="sr-only" aria-live="polite">
                Procesando inicio de sesión, por favor espera
              </p>
            )}

            <div className="relative">
              <div className="absolute inset-0 flex items-center">
                <span className="w-full border-t" />
              </div>
              <div className="relative flex justify-center text-xs uppercase">
                <span className="bg-white px-2 text-gray-500">O continúa con</span>
              </div>
            </div>

            <div className="flex gap-2">
              <Button variant="outline" className="w-full" onClick={() => handleSocialLogin('google')}>
                <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" viewBox="0 0 24 24">
                  <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4" />
                  <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
                  <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
                  <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
                  <path d="M1 1h22v22H1z" fill="none" />
                </svg>
                Google
              </Button>
              <Button variant="outline" className="w-full" onClick={() => handleSocialLogin('linkedin')}>
                <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" viewBox="0 0 24 24" fill="#0A66C2">
                  <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z" />
                </svg>
                LinkedIn
              </Button>
            </div>
          </form>
        </CardContent>
        <CardFooter className="flex-col">
          <div className="text-center">
            <span className="text-sm text-gray-500">¿No tienes una cuenta? </span>
            <Link to="/candidates/login" className="text-sm text-primary hover:underline">
              Regístrate
            </Link>
          </div>

          {import.meta.env.DEV && import.meta.env.VITE_SHOW_TEST_CREDENTIALS === 'true' && (
            <div className="mt-6 w-full">
              <Separator className="my-4" />
              <h3 className="text-sm font-medium text-center mb-4">Credenciales de prueba (Solo desarrollo)</h3>

              <div className="space-y-2 text-xs text-gray-600">
                <p><strong>Candidato:</strong> test.candidate@email.com / TestPass123!</p>
                <p><strong>HR Admin:</strong> test.admin@email.com / AdminPass123!</p>
                <p><strong>Reclutador:</strong> test.recruiter@email.com / RecruitPass123!</p>
              </div>

              <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                <p className="text-xs text-yellow-800">
                  ⚠️ Estas credenciales solo funcionan en entorno de desarrollo.
                  En producción, usa tus credenciales reales.
                </p>
              </div>
            </div>
          )}
        </CardFooter>
      </CardForm>
    </div>
  );
}
