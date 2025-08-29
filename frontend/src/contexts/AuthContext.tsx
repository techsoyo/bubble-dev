
/**
 * Auth Context - UPDATED WITH NEW ENDPOINTS
 * ✅ ACTUALIZADO: Integración con endpoints actualizados
 * - Candidatos: /auth/register (login y registro)
 * - Staff: /staff/login
 */

import { createContext, useState, useContext, useEffect, ReactNode } from 'react';
import { SecureAuthManager, User, LoginCredentials, AuthResponse } from '../lib/auth/secureAuthManager';
import { safeRemove } from '../utils/safeStorage';
import { InputSanitizer } from '../lib/auth/secureInputValidator';
import { logAuthEvent, logSessionEvent } from '../security/securityLogger';

interface SocialAuthData {
    token?: string;
    access_token?: string;
    id_token?: string;
    provider_user_id?: string;
    email?: string;
    name?: string;
    picture?: string;
}

interface RegistrationData {
    email: string;
    password: string;
    first_name?: string;
    last_name?: string;
    name?: string;
}

interface AuthContextType {
    // Estado
    isLoggedIn: boolean;
    user: User | null;
    isLoading: boolean;
    isInitialized: boolean;
    error: string | null;
    userType: 'candidate' | 'staff' | 'unknown';

    // Acciones de Login
    login: (email: string, password: string, rememberMe?: boolean) => Promise<AuthResponse>;
    candidateLogin: (email: string, password: string) => Promise<AuthResponse>;
    staffLogin: (email: string, password: string) => Promise<AuthResponse>;
    loginWithSocial: (provider: string, userData: SocialAuthData) => Promise<AuthResponse>;

    // ✅ NUEVO: Registro
    registerCandidate: (registrationData: RegistrationData) => Promise<AuthResponse>;

    // Otras acciones
    logout: () => Promise<void>;
    refreshSession: () => Promise<void>;
    changePassword: (currentPassword: string, newPassword: string) => Promise<AuthResponse>;
    clearError: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export { AuthContext };

export function AuthProvider({ children }: { children: ReactNode }) {
    const [isLoggedIn, setIsLoggedIn] = useState<boolean>(false);
    const [user, setUser] = useState<User | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [isInitialized, setIsInitialized] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const [userType, setUserType] = useState<'candidate' | 'staff' | 'unknown'>('unknown');

    useEffect(() => {
        initializeAuth();
    }, []);

    /**
     * ✅ ACTUALIZADO: Inicialización con determinación de tipo de usuario
     */
    const initializeAuth = async () => {
        try {
            setIsLoading(true);
            setError(null);

            // Limpiar datos legacy de localStorage para migración completa
            const legacyKeys = [
                'isLoggedIn', 'userEmail', 'userId', 'userRole',
                'userName', 'userFirstName', 'userLastName'
            ];
            legacyKeys.forEach(key => safeRemove(key));

            // Verificar sesión activa
            const { isValid, user: sessionUser } = await SecureAuthManager.verifySession();

            if (isValid && sessionUser) {
                setUser(sessionUser);
                setIsLoggedIn(true);
                setUserType(SecureAuthManager.getUserType());
            } else {
                setUser(null);
                setIsLoggedIn(false);
                setUserType('unknown');
            }

            setIsInitialized(true);
        } catch (err) {
            console.error('❌ Error inicializando auth:', err);
            setError('Error inicializando autenticación');
            setUser(null);
            setIsLoggedIn(false);
            setUserType('unknown');
            setIsInitialized(true);
        } finally {
            setIsLoading(false);
        }
    };

    /**
     * ✅ ACTUALIZADO: Login genérico (usa candidateLogin por defecto)
     */
    const login = async (email: string, password: string, rememberMe = false): Promise<AuthResponse> => {
        return candidateLogin(email, password);
    };

    /**
     * ✅ ACTUALIZADO: Candidate login con endpoint actualizado
     */
    const candidateLogin = async (email: string, password: string): Promise<AuthResponse> => {
        let sanitizedEmail = '';

        try {
            setIsLoading(true);
            setError(null);

            sanitizedEmail = InputSanitizer.sanitizeEmail(email);
            if (!sanitizedEmail) {
                const errorResponse = { success: false, message: 'Email inválido' };
                setError(errorResponse.message);
                logAuthEvent('CANDIDATE_LOGIN_FAILED', { reason: 'invalid_email', email: email.substring(0, 3) + '***' });
                return errorResponse;
            }

            const credentials: LoginCredentials = {
                email: sanitizedEmail,
                password,
                rememberMe: false
            };

            const authResponse = await SecureAuthManager.candidateLogin(credentials);

            if (authResponse.success && authResponse.user) {
                setUser(authResponse.user);
                setIsLoggedIn(true);
                setUserType('candidate');
                logAuthEvent('CANDIDATE_LOGIN_SUCCESS', { userId: authResponse.user.id, email: sanitizedEmail.substring(0, 3) + '***' }, authResponse.user.id);
                logSessionEvent('SESSION_STARTED', authResponse.user.id, { userType: 'candidate' });
            } else {
                setError(authResponse.message || 'Candidate login falló');
                logAuthEvent('CANDIDATE_LOGIN_FAILED', { reason: 'invalid_credentials', email: sanitizedEmail.substring(0, 3) + '***' });
            }

            return authResponse;
        } catch (err) {
            const errorMessage = 'Error en candidate login';
            setError(errorMessage);
            console.error('❌ Error candidate login:', err);
            logAuthEvent('CANDIDATE_LOGIN_ERROR', { error: err instanceof Error ? err.message : 'Unknown error', email: sanitizedEmail?.substring(0, 3) + '***' });
            return { success: false, message: errorMessage };
        } finally {
            setIsLoading(false);
        }
    };

    /**
     * ✅ ACTUALIZADO: Staff login con endpoint actualizado
     */
    const staffLogin = async (email: string, password: string): Promise<AuthResponse> => {
        try {
            setIsLoading(true);
            setError(null);

            const sanitizedEmail = InputSanitizer.sanitizeEmail(email);
            if (!sanitizedEmail) {
                const errorResponse = { success: false, message: 'Email inválido' };
                setError(errorResponse.message);
                return errorResponse;
            }

            const credentials: LoginCredentials = {
                email: sanitizedEmail,
                password,
                rememberMe: false
            };

            const authResponse = await SecureAuthManager.staffLogin(credentials);

            if (authResponse.success && authResponse.user) {
                setUser(authResponse.user);
                setIsLoggedIn(true);
                setUserType('staff');
            } else {
                setError(authResponse.message || 'Staff login falló');
            }

            return authResponse;
        } catch (err) {
            const errorMessage = 'Error en staff login';
            setError(errorMessage);
            console.error('❌ Error staff login:', err);
            return { success: false, message: errorMessage };
        } finally {
            setIsLoading(false);
        }
    };

    /**
     * ✅ NUEVO: Registro de candidatos
     */
    const registerCandidate = async (registrationData: RegistrationData): Promise<AuthResponse> => {
        try {
            setIsLoading(true);
            setError(null);

            const sanitizedEmail = InputSanitizer.sanitizeEmail(registrationData.email);
            if (!sanitizedEmail) {
                const errorResponse = { success: false, message: 'Email inválido' };
                setError(errorResponse.message);
                return errorResponse;
            }

            const sanitizedData = {
                ...registrationData,
                email: sanitizedEmail,
                first_name: registrationData.first_name ? InputSanitizer.sanitizeName(registrationData.first_name) : undefined,
                last_name: registrationData.last_name ? InputSanitizer.sanitizeName(registrationData.last_name) : undefined,
                name: registrationData.name ? InputSanitizer.sanitizeName(registrationData.name) : undefined,
            };

            const authResponse = await SecureAuthManager.registerCandidate(sanitizedData);

            if (authResponse.success && authResponse.user) {
                setUser(authResponse.user);
                setIsLoggedIn(true);
                setUserType('candidate');
            } else {
                setError(authResponse.message || 'Registro falló');
            }

            return authResponse;
        } catch (err) {
            const errorMessage = 'Error en registro';
            setError(errorMessage);
            console.error('❌ Error registro:', err);
            return { success: false, message: errorMessage };
        } finally {
            setIsLoading(false);
        }
    };

    /**
     * ✅ PLACEHOLDER: Social login 
     */
    const loginWithSocial = async (provider: string, userData: SocialAuthData): Promise<AuthResponse> => {
        try {
            setIsLoading(true);
            setError(null);


            const errorResponse = { success: false, message: 'Social login no disponible' };
            setError(errorResponse.message);
            return errorResponse;
        } catch (err) {
            const errorMessage = 'Error en social login';
            setError(errorMessage);
            return { success: false, message: errorMessage };
        } finally {
            setIsLoading(false);
        }
    };

    /**
     * ✅ ACTUALIZADO: Logout con endpoints actualizados
     */
    const logout = async (): Promise<void> => {
        const currentUserId = user?.id;

        try {
            setIsLoading(true);
            setError(null);

            const success = await SecureAuthManager.logout();

            // Limpiar estado local
            setUser(null);
            setIsLoggedIn(false);
            setUserType('unknown');

            if (success) {
                logAuthEvent('LOGOUT_SUCCESS', { userId: currentUserId }, currentUserId);
                logSessionEvent('SESSION_ENDED', currentUserId, { reason: 'user_logout' });
            } else {
                logAuthEvent('LOGOUT_FAILED', { userId: currentUserId }, currentUserId);
            }
        } catch (err) {
            console.error('❌ Error en logout:', err);
            // Forzar limpieza incluso con error
            setUser(null);
            setIsLoggedIn(false);
            setUserType('unknown');
            SecureAuthManager.clearSession();
            logAuthEvent('LOGOUT_ERROR', { error: err instanceof Error ? err.message : 'Unknown error', userId: currentUserId }, currentUserId);
            logSessionEvent('SESSION_ENDED', currentUserId, { reason: 'logout_error' });
        } finally {
            setIsLoading(false);
        }
    };

    /**
     * ✅ ACTUALIZADO: Refresh session con tipo de usuario
     */
    const refreshSession = async (): Promise<void> => {
        try {
            setError(null);

            const { isValid, user: sessionUser } = await SecureAuthManager.verifySession();

            if (isValid && sessionUser) {
                setUser(sessionUser);
                setIsLoggedIn(true);
                setUserType(SecureAuthManager.getUserType());
            } else {
                await logout();
            }
        } catch (err) {
            console.error('❌ Error actualizando sesión:', err);
            setError('Error actualizando sesión');
            await logout();
        }
    };

    /**
     * ✅ ACTUALIZADO: Change password 
     */
    const changePassword = async (currentPassword: string, newPassword: string): Promise<AuthResponse> => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await SecureAuthManager.changePassword(currentPassword, newPassword);

            if (!response.success) {
                setError(response.message || 'Error cambiando contraseña');
            }

            return response;
        } catch (err) {
            const errorMessage = 'Error cambiando contraseña';
            setError(errorMessage);
            return { success: false, message: errorMessage };
        } finally {
            setIsLoading(false);
        }
    };

    /**
     * ✅ Clear error
     */
    const clearError = () => {
        setError(null);
    };

    const value: AuthContextType = {
        // Estado
        isLoggedIn,
        user,
        isLoading,
        isInitialized,
        error,
        userType,

        // Acciones
        login,
        candidateLogin,
        staffLogin,
        loginWithSocial,
        registerCandidate,
        logout,
        refreshSession,
        changePassword,
        clearError
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

// ✅ Hook mejorado con validación
export function useAuth() {
    const context = useContext(AuthContext);
    if (context === undefined) {
        throw new Error('useAuth debe usarse dentro de AuthProvider');
    }
    return context;
}

export default AuthContext;
