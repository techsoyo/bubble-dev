import { useEffect, useState } from 'react';
import { api } from '../lib/api';
import { SecureAuthManager } from '../lib/auth/secureAuthManager';
import { User } from '../lib/auth/secureAuthManager';

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
                    // Como último recurso, verificamos localStorage (en fase de migración)
                    const localIsLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
                    if (localIsLoggedIn) {
                        // Si tenemos datos en localStorage pero la API falló, usamos los datos locales
                        console.warn('Usando datos de autenticación legacy (localStorage). Actualice a cookies seguras.');
                        setIsLoggedIn(true);
                        setUser({
                            id: localStorage.getItem('userId') ?? '',
                            email: localStorage.getItem('userEmail') ?? '',
                            role: localStorage.getItem('userRole') || undefined,
                            ...(localStorage.getItem('userName') && { name: localStorage.getItem('userName') })
                        } as User);
                    } else {
                        setIsLoggedIn(false);
                        setUser(null);
                    }
                }
            }
        } catch (err) {
            console.error('Error verificando sesión:', err);

            // Verificar localStorage como último recurso
            const localIsLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
            if (localIsLoggedIn) {
                console.warn('Usando datos de autenticación legacy (localStorage) debido a error. Actualice a cookies seguras.');
                setIsLoggedIn(true);
                setUser({
                    id: localStorage.getItem('userId') ?? '',
                    email: localStorage.getItem('userEmail') ?? '',
                    role: localStorage.getItem('userRole') || undefined,
                    ...(localStorage.getItem('userName') && { name: localStorage.getItem('userName') })
                } as User);
            } else {
                setIsLoggedIn(false);
                setUser(null);
            }
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
