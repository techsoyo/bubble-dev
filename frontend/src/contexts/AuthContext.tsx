import { createContext, useState, useContext, useEffect, ReactNode } from 'react';
import { SecureAuthManager, User, LoginCredentials } from '../lib/auth/secureAuthManager';
import { InputSanitizer } from '../lib/auth/secureInputValidator';

// Interfaz para datos de autenticación social
interface SocialAuthData {
    token?: string;
    access_token?: string;
    id_token?: string;
    provider_user_id?: string;
    email?: string;
    name?: string;
    picture?: string;
}

// Extendemos la interfaz User importada para mantener compatibilidad
interface AuthContextType {
    isLoggedIn: boolean;
    user: User | null;
    isLoading: boolean;
    login: (email: string, password: string, rememberMe?: boolean) => Promise<boolean>;
    candidateLogin: (email: string, password: string) => Promise<boolean>;
    staffLogin: (email: string, password: string) => Promise<boolean>;
    loginWithSocial: (provider: string, userData: SocialAuthData) => Promise<boolean>;
    logout: () => Promise<void>;
    refreshSession: () => Promise<void>;
    isInitialized: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

// Exportar el contexto explícitamente
export { AuthContext };

export function AuthProvider({ children }: { children: ReactNode }) {
    const [isLoggedIn, setIsLoggedIn] = useState<boolean>(false);
    const [user, setUser] = useState<User | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [isInitialized, setIsInitialized] = useState<boolean>(false);

    useEffect(() => {
        initializeAuth();
    }, []);

    const initializeAuth = async () => {
        try {
            setIsLoading(true);

            // Limpiar cualquier dato de localStorage (migración a producción)
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('userEmail');
            localStorage.removeItem('userId');
            localStorage.removeItem('userRole');
            localStorage.removeItem('userName');
            localStorage.removeItem('userFirstName');
            localStorage.removeItem('userLastName');

            // Intentar verificar sesión con cookies HTTP-only
            const { isValid, user: sessionUser } = await SecureAuthManager.verifySession();

            if (isValid && sessionUser) {
                setUser(sessionUser);
                setIsLoggedIn(true);
                console.log('Session restored from secure cookies');
            } else {
                setUser(null);
                setIsLoggedIn(false);
                console.log('No valid session found');
            }

            setIsInitialized(true);
        } catch (error) {
            console.error('Auth initialization error:', error);
            setUser(null);
            setIsLoggedIn(false);
            setIsInitialized(true);
        } finally {
            setIsLoading(false);
        }
    };

    const login = async (email: string, password: string, rememberMe = false): Promise<boolean> => {
        try {
            setIsLoading(true);

            // Sanitizar email
            const sanitizedEmail = InputSanitizer.sanitizeEmail(email);
            if (!sanitizedEmail) {
                console.error('Login failed: invalid email format');
                return false;
            }

            // Intentar login seguro
            const credentials: LoginCredentials = {
                email: sanitizedEmail,
                password, // No sanitizamos la contraseña
                rememberMe
            };

            const authResponse = await SecureAuthManager.login(credentials);

            if (authResponse.success && authResponse.user) {
                setUser(authResponse.user);
                setIsLoggedIn(true);

                // Limpiar localStorage completamente
                localStorage.removeItem('isLoggedIn');
                localStorage.removeItem('userEmail');
                localStorage.removeItem('userId');
                localStorage.removeItem('userRole');
                localStorage.removeItem('userName');
                localStorage.removeItem('userFirstName');
                localStorage.removeItem('userLastName');

                console.log('Secure login successful');
                return true;
            } else {
                console.error('Login failed:', authResponse.message);
                return false;
            }
        } catch (error) {
            console.error('Login error:', error);
            return false;
        } finally {
            setIsLoading(false);
        }
    };

    const candidateLogin = async (email: string, password: string): Promise<boolean> => {
        try {
            setIsLoading(true);

            // Sanitizar email
            const sanitizedEmail = InputSanitizer.sanitizeEmail(email);
            if (!sanitizedEmail) {
                console.error('Candidate login failed: invalid email format');
                return false;
            }

            // Intentar candidate login usando endpoint específico
            const credentials: LoginCredentials = {
                email: sanitizedEmail,
                password, // No sanitizamos la contraseña
                rememberMe: false
            };

            const authResponse = await SecureAuthManager.candidateLogin(credentials);

            if (authResponse.success && authResponse.user) {
                setUser(authResponse.user);
                setIsLoggedIn(true);

                console.log('Secure candidate login successful');
                return true;
            } else {
                console.error('Candidate login failed:', authResponse.message);
                return false;
            }
        } catch (error) {
            console.error('Candidate login error:', error);
            return false;
        } finally {
            setIsLoading(false);
        }
    };

    const staffLogin = async (email: string, password: string): Promise<boolean> => {
        try {
            setIsLoading(true);

            // Sanitizar email
            const sanitizedEmail = InputSanitizer.sanitizeEmail(email);
            if (!sanitizedEmail) {
                console.error('Staff login failed: invalid email format');
                return false;
            }

            // Intentar staff login usando endpoint específico
            const credentials: LoginCredentials = {
                email: sanitizedEmail,
                password, // No sanitizamos la contraseña
                rememberMe: false
            };

            const authResponse = await SecureAuthManager.staffLogin(credentials);

            if (authResponse.success && authResponse.user) {
                setUser(authResponse.user);
                setIsLoggedIn(true);

                console.log('Secure staff login successful');
                return true;
            } else {
                console.error('Staff login failed:', authResponse.message);
                return false;
            }
        } catch (error) {
            console.error('Staff login error:', error);
            return false;
        } finally {
            setIsLoading(false);
        }
    };

    const loginWithSocial = async (provider: string, userData: SocialAuthData): Promise<boolean> => {
        try {
            setIsLoading(true);

            // Por ahora, social login no está implementado en producción
            console.log('Social login not implemented yet');
            return false;

        } catch (error) {
            console.error('Social login error:', error);
            return false;
        } finally {
            setIsLoading(false);
        }
    };

    const logout = async (): Promise<void> => {
        try {
            setIsLoading(true);

            // Usar método seguro de logout
            await SecureAuthManager.logout();

            // Limpiar estado local
            setUser(null);
            setIsLoggedIn(false);

            // Asegurar que localStorage también esté limpio
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('userEmail');
            localStorage.removeItem('userId');
            localStorage.removeItem('userRole');
            localStorage.removeItem('userName');

            console.log('Secure logout successful');
        } catch (error) {
            console.error('Logout error:', error);
            // Forzar limpieza local incluso si hay error
            setUser(null);
            setIsLoggedIn(false);
        } finally {
            setIsLoading(false);
        }
    };

    const refreshSession = async (): Promise<void> => {
        try {
            console.log('Refreshing session...');

            // Verificar sesión actual
            const { isValid, user: sessionUser } = await SecureAuthManager.verifySession();

            if (isValid && sessionUser) {
                setUser(sessionUser);
                setIsLoggedIn(true);
                console.log('Session refreshed successfully');
            } else {
                console.log('Session refresh failed, logging out');
                await logout();
            }
        } catch (error) {
            console.error('Session refresh error:', error);
            await logout();
        }
    };

    const value: AuthContextType = {
        isLoggedIn,
        user,
        isLoading,
        login,
        candidateLogin,
        staffLogin,
        loginWithSocial,
        logout,
        refreshSession,
        isInitialized
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

// Hook personalizado para usar el contexto
export function useAuth() {
    const context = useContext(AuthContext);
    if (context === undefined) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}

// Export por defecto
export default AuthContext;
