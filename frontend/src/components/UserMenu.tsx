import React from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useNavigate } from 'react-router-dom';

const UserMenu: React.FC = () => {
    const { user, logout, isLoggedIn } = useAuth();
    const navigate = useNavigate();

    const handleLogout = () => {
        logout();
        navigate('/');
    };

    if (!isLoggedIn || !user) {
        return (
            <div className="flex space-x-4">
                <button
                    onClick={() => navigate('/auth/login')}
                    className="text-white hover:text-gray-300 px-3 py-2 rounded-md text-sm font-medium"
                >
                    Iniciar Sesión
                </button>
                <button
                    onClick={() => navigate('/candidates/login')}
                    className="bg-white text-indigo-600 hover:bg-gray-100 px-3 py-2 rounded-md text-sm font-medium"
                >
                    Registrarse
                </button>
            </div>
        );
    }

    const getRoleDisplayName = (role: string) => {
        switch (role) {
            case 'admin':
                return 'Administrador';
            case 'recruiter':
                return 'Reclutador';
            case 'candidate':
                return 'Candidato';
            default:
                return role;
        }
    };

    const getDashboardRoute = () => {
        switch (user?.role) {
            case 'admin':
                return '/admin';
            case 'recruiter':
                return '/dashboard/recruiterdashboard';
            case 'candidate':
                return '/dashboard/cddashboard';
            default:
                return '/';
        }
    };

    return (
        <div className="flex items-center space-x-4">
            <span className="text-white text-sm">
                {user.name || user.email} ({getRoleDisplayName(user.role || 'candidate')})
            </span>
            <button
                onClick={() => navigate(getDashboardRoute())}
                className="text-white hover:text-gray-300 px-3 py-2 rounded-md text-sm font-medium"
            >
                Dashboard
            </button>
            <button
                onClick={handleLogout}
                className="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium"
            >
                Cerrar Sesión
            </button>
        </div>
    );
};

export default UserMenu;
