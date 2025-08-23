import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

interface ProtectedRouteProps {
    children: React.ReactNode;
    requiredRole?: 'admin' | 'recruiter' | 'candidate';
    adminOnly?: boolean;
}

export default function ProtectedRoute({
    children,
    requiredRole,
    adminOnly = false
}: ProtectedRouteProps) {
    const { user, isLoggedIn, isLoading } = useAuth();
    const location = useLocation();

    // Mostrar loading mientras verificamos autenticación
    if (isLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-500"></div>
            </div>
        );
    }

    // Si no está autenticado, redirigir según el tipo de dashboard solicitado
    if (!isLoggedIn || !user) {
        // Detectar si está intentando acceder a dashboards de staff o candidato
        const currentPath = location.pathname;

        if (currentPath.includes('/dashboard/hrdashboard') || currentPath.includes('/dashboard/recruiterdashboard') || currentPath.includes('/dashboard/estadisticas') || currentPath.includes('/dashboard/manager') || currentPath.includes('/admin')) {
            // Es staff, redirigir al login de staff
            return <Navigate to="/staff/login" state={{ from: location }} replace />;
        } else if (currentPath.includes('/dashboard/cddashboard')) {
            // Es candidato, redirigir al registro/login de candidatos
            return <Navigate to="/candidates/login" state={{ from: location }} replace />;
        } else {
            // Por defecto, redirigir al login general
            return <Navigate to="/auth/login" state={{ from: location }} replace />;
        }
    }

    // Si se requiere admin y no es admin
    if (adminOnly && user.role !== 'admin') {
        return <Navigate to="/unauthorized" replace />;
    }

    // Si se requiere un rol específico y no lo tiene
    if (requiredRole && user.role !== requiredRole && user.role !== 'admin') {
        return <Navigate to="/unauthorized" replace />;
    }

    // Si todo está bien, mostrar el contenido
    return <>{children}</>;
}

// Componente específico para rutas de admin
export function AdminRoute({ children }: { children: React.ReactNode }) {
    return (
        <ProtectedRoute adminOnly={true}>
            {children}
        </ProtectedRoute>
    );
}

// Componente específico para rutas de reclutadores
export function RecruiterRoute({ children }: { children: React.ReactNode }) {
    return (
        <ProtectedRoute requiredRole="recruiter">
            {children}
        </ProtectedRoute>
    );
}
