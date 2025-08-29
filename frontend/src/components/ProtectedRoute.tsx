import React, { useEffect, useState } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { SecureAuthManager } from '../lib/auth/secureAuthManager';

interface ProtectedRouteProps {
    children: React.ReactNode;
    requiredRole?: 'admin' | 'recruiter' | 'candidate';
}

export const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
    children,
    requiredRole
}) => {
    const { isLoggedIn, user, isLoading, refreshSession } = useAuth();
    const location = useLocation();
    const [verifyingSession, setVerifyingSession] = useState(true);
    const [isSessionValid, setIsSessionValid] = useState(false);

    useEffect(() => {
        // Verificar la validez de la sesión al cargar el componente
        const verifySession = async () => {
            try {
                setVerifyingSession(true);

                // Verificar la validez de la sesión con el backend (cookies HTTP-only)
                const { isValid } = await SecureAuthManager.verifySession();
                setIsSessionValid(isValid);

                // Si la sesión es inválida pero aún está marcada como logueada, refrescar
                if (!isValid && isLoggedIn) {
                    await refreshSession();
                }
            } catch (error) {
                console.error('Error verifying session:', error);
                setIsSessionValid(false);
            } finally {
                setVerifyingSession(false);
            }
        };

        verifySession();
    }, [isLoggedIn, refreshSession]);

    // Mostrar loading mientras se verifica la autenticación
    if (isLoading || verifyingSession) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-indigo-600"></div>
            </div>
        );
    }

    // Si no está autenticado o la sesión es inválida, redirigir al login
    if (!isLoggedIn || !user || !isSessionValid) {
        return <Navigate to="/auth/register" state={{ from: location }} replace />;
    }

    // Si se requiere un rol específico, verificar que el usuario lo tenga
    if (requiredRole && user.role !== requiredRole) {
        // Redirigir según el rol del usuario
        const redirectPath = user.role === 'admin'
            ? '/admin'
            : user.role === 'recruiter'
                ? '/dashboard/recruiterdashboard'
                : '/dashboard/cddashboard';

        return <Navigate to={redirectPath} replace />;
    }

    // Si todo está bien, mostrar el componente
    return <>{children}</>;
};

// Componentes específicos para roles
export const AdminRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => (
    <ProtectedRoute requiredRole="admin">{children}</ProtectedRoute>
);

export const RecruiterRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => (
    <ProtectedRoute requiredRole="recruiter">{children}</ProtectedRoute>
);

export const CandidateRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => (
    <ProtectedRoute requiredRole="candidate">{children}</ProtectedRoute>
);
