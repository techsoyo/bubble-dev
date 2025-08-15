import React, { useState } from 'react';
// Ajustamos el path para importar correctamente el contexto de autenticación.
// Este componente está ubicado en src/components, por lo que solo hay que subir
// un nivel para acceder a src/contexts/AuthContext.tsx.
import { useAuth } from '../contexts/AuthContext';
import { useNavigate, useLocation } from 'react-router-dom';
import { InputSanitizer } from '../lib/auth/secureInputValidator';

const Login: React.FC = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [rememberMe, setRememberMe] = useState(false);

    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();

    // Obtener la ruta a la que se quería acceder antes del login
    const from = location.state?.from?.pathname || '/';

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setLoading(true);

        try {
            // Validar email con el sanitizador
            const emailValidation = InputSanitizer.validateCriticalField(email, 'email');
            const passwordValidation = InputSanitizer.validateCriticalField(password, 'password');

            if (!emailValidation.isValid) {
                setError(emailValidation.message || 'Email inválido');
                setLoading(false);
                return;
            }

            if (!passwordValidation.isValid) {
                setError(passwordValidation.message || 'Contraseña inválida');
                setLoading(false);
                return;
            }

            // Verificar rate limiting para prevenir ataques de fuerza bruta
            const rateLimitOk = InputSanitizer.checkRateLimit(`login_${email}`);
            if (!rateLimitOk) {
                setError('Demasiados intentos. Por favor, intenta de nuevo más tarde.');
                setLoading(false);
                return;
            }

            const success = await login(email, password, rememberMe);

            if (success) {
                // Redirigir a la página que intentaba acceder o al dashboard
                navigate(from, { replace: true });
            } else {
                setError('Email o contraseña incorrectos');
            }
        } catch (err) {
            setError('Error al iniciar sesión. Intenta de nuevo.');
        } finally {
            setLoading(false);
        }
    };

    const demoCredentials = [
        { email: 'admin@bubble.com', password: 'admin123', role: 'Admin' },
        { email: 'recruiter@bubble.com', password: 'recruiter123', role: 'Recruiter' },
        { email: 'candidate@bubble.com', password: 'candidate123', role: 'Candidate' }
    ];

    const fillDemo = (email: string, password: string) => {
        setEmail(email);
        setPassword(password);
        setRememberMe(true); // Activar "recordarme" para credenciales de demo
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <div className="max-w-md w-full space-y-8">
                <div>
                    <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
                        Iniciar Sesión
                    </h2>
                    <p className="mt-2 text-center text-sm text-gray-600">
                        Bubble of Talents - Sistema de Reclutamiento
                    </p>
                </div>

                <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
                    {error && (
                        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative">
                            {error}
                        </div>
                    )}

                    <div className="rounded-md shadow-sm -space-y-px">
                        <div>
                            <label htmlFor="email" className="sr-only">
                                Email
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="email"
                                required
                                className="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                                placeholder="Email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor="password" className="sr-only">
                                Contraseña
                            </label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                required
                                className="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                                placeholder="Contraseña"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-between">
                        <div className="flex items-center">
                            <input
                                id="remember-me"
                                name="remember-me"
                                type="checkbox"
                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                checked={rememberMe}
                                onChange={(e) => setRememberMe(e.target.checked)}
                            />
                            <label htmlFor="remember-me" className="ml-2 block text-sm text-gray-900">
                                Recordarme
                            </label>
                        </div>
                    </div>

                    <div>
                        <button
                            type="submit"
                            disabled={loading}
                            className="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            {loading ? 'Iniciando sesión...' : 'Iniciar Sesión'}
                        </button>
                    </div>
                </form>

                {/* Credenciales de demo */}
                <div className="mt-6">
                    <div className="text-center">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">
                            Credenciales de Demo
                        </h3>
                        <div className="space-y-2">
                            {demoCredentials.map((cred, index) => (
                                <button
                                    key={index}
                                    onClick={() => fillDemo(cred.email, cred.password)}
                                    className="w-full text-left px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
                                >
                                    <div className="font-medium">{cred.role}</div>
                                    <div className="text-sm text-gray-600">{cred.email}</div>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Login;
