import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Label } from '../components/ui/label';
import { Input } from '../components/ui/input';
import { Button } from '../components/ui/button';
import { CardForm, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '../components/ui/card';
import { Alert, AlertDescription } from '../components/ui/alert';
import { Separator } from '../components/ui/separator';

export default function StaffLogin() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [loginSuccess, setLoginSuccess] = useState(false);
  const navigate = useNavigate();
  const { staffLogin, user } = useAuth();

  // 🔧 SOLUCIÓN: useEffect que escucha cambios en user después del login
  useEffect(() => {
    if (loginSuccess && user && user.role) {
      console.log('✅ User actualizado después del login, role:', user.role);

      // Redirección según el rol del usuario
      if (user.role === 'admin' || user.role === 'hr') {
        navigate('/dashboard/hrdashboard');
      } else if (user.role === 'recruiter') {
        navigate('/dashboard/recruiterdashboard');
      } else {
        console.log('Unknown role, redirecting to home');
        navigate('/');
      }

      // Reset del flag
      setLoginSuccess(false);
    }
  }, [user, loginSuccess, navigate]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    console.log('Form submitted, preventDefault called');
    setIsLoading(true);
    setError('');

    try {
      // Usar el contexto de autenticación específico para staff
      const success = await staffLogin(email, password);

      if (success) {
        console.log('Staff login successful, activating useEffect listener');
        // 🔧 SOLUCIÓN: Activar el useEffect que escucha cambios en user
        setLoginSuccess(true);
      } else {
        setError('Credenciales incorrectas. Verifica tu email y contraseña.');
      }
    } catch (error) {
      console.error('Staff login error:', error);
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
                  onChange={(e) => setEmail(e.target.value)}
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="staff-login-password" className="text-white">Contraseña</Label>
                <Input
                  id="staff-login-password"
                  type="password"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                />
              </div>

              <Button
                type="submit"
                className="w-full bg-[#FF4785] hover:bg-[#FF3575]"
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
                style={{ color: '#FF4785' }}
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
