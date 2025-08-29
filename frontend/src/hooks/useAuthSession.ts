import { useEffect, useState } from 'react';
import { api } from '../lib/api';
import { SecureAuthManager } from '../lib/auth/secureAuthManager';
import { User } from '../lib/auth/secureAuthManager';
import { safeGet, safeSet, safeRemove } from '../utils/safeStorage';

/**
 * Hook personalizado para manejar la sesión de autenticación
 * NOTA: Este hook está en proceso de deprecación. Se recomienda usar useAuth()
 * del contexto de autenticación en su lugar.
 * @deprecated Usar useAuth() de AuthContext.tsx en su lugar
 */
export function useAuthSession() {
    const [isLoading, setIsLoading] = useState(true);
    const [isLoggedIn, setIsLoggedIn] = useState(false);
    const [user, setUser] = useState<User | null>(null);

    // Extraer checkSession fuera del useEffect para poder reutilizarla
    async function checkSession() {
        setIsLoading(true);
        try {
            // Primero intentamos verificar la sesión con cookies HTTP-only
            const { isValid, user: sessionUser } = await SecureAuthManager.verifySession();

            if (isValid && sessionUser) {
                setIsLoggedIn(true);
                setUser(sessionUser);
            } else {
                // Intentamos con la API como respaldo
                const res = await api.get('/api/auth/me');
                if (res.data && res.data.success) {
                    setIsLoggedIn(true);
                    setUser(res.data.data);
                } else {
                    // Los datos de autenticación están en cookies seguras automáticamente
                    setIsLoggedIn(false);
                    setUser(null);
                }
            }
        } catch (err) {
            console.error('Error verificando sesión:', err);

            // 🚨 PRODUCCIÓN: Eliminado localStorage fallback
            // La autenticación solo se basa en cookies httpOnly seguras
            setIsLoggedIn(false);
            setUser(null);
        } finally {
            setIsLoading(false);
        }
    }

    useEffect(() => {
        // Siempre verificamos la sesión al iniciar
        checkSession();
    }, []);

    // Devolvemos la función checkSession como refreshSession para que pueda ser usada
    // después de iniciar sesión o registrarse
    return { isLoading, isLoggedIn, user, refreshSession: checkSession };
}
