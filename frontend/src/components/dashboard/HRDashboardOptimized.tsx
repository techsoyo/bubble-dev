/**
 * Optimized HR Dashboard Component
 * 
 * Performance improvements implemented as per Section 8.3:
 * - Loading states with skeleton UI
 * - Memoization with useMemo and useCallback
 * - Component optimization and error boundaries
 * 
 * @package Components/Dashboard
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-01-05
 */

import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { getHRDashboardStats } from '../../lib/apiService';
import { Link } from 'react-router-dom';

interface DashboardStats {
    totalCandidates: number;
    activeJobs: number;
    pendingApplications: number;
    interviewsScheduled: number;
    hiringRate: number;
    timeToHire: number;
    topRecruiters: Array<{
        id: string;
        name: string;
        hiredCandidates: number;
        activeProcesses: number;
    }>;
    recentHires: Array<{
        id: string;
        name: string;
        position: string;
        hireDate: string;
    }>;
    candidatesByStatus: Array<{
        status: string;
        count: number;
    }>;
}

// Memoized skeleton component for loading states
const StatCardSkeleton: React.FC = React.memo(() => (
    <div className="bg-white p-6 rounded-lg shadow-sm animate-pulse">
        <div className="flex items-center justify-between">
            <div className="flex-1">
                <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                <div className="h-8 bg-gray-200 rounded w-1/2"></div>
            </div>
            <div className="w-12 h-12 bg-gray-200 rounded-full"></div>
        </div>
        <div className="mt-4">
            <div className="h-3 bg-gray-200 rounded w-1/2"></div>
        </div>
    </div>
));

const ChartSkeleton: React.FC = React.memo(() => (
    <div className="bg-white p-6 rounded-lg shadow-sm animate-pulse">
        <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
        <div className="space-y-3">
            <div className="h-4 bg-gray-200 rounded"></div>
            <div className="h-4 bg-gray-200 rounded w-3/4"></div>
            <div className="h-4 bg-gray-200 rounded w-1/2"></div>
        </div>
    </div>
));

const ListSkeleton: React.FC = React.memo(() => (
    <div className="bg-white p-6 rounded-lg shadow-sm animate-pulse">
        <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
        <div className="space-y-3">
            {[...Array(3)].map((_, i) => (
                <div key={i} className="flex items-center justify-between p-3 bg-gray-50 rounded-md">
                    <div className="flex items-center space-x-3">
                        <div className="w-8 h-8 bg-gray-200 rounded-full"></div>
                        <div className="space-y-1">
                            <div className="h-3 bg-gray-200 rounded w-20"></div>
                            <div className="h-2 bg-gray-200 rounded w-16"></div>
                        </div>
                    </div>
                    <div className="space-y-1">
                        <div className="h-3 bg-gray-200 rounded w-8"></div>
                        <div className="h-2 bg-gray-200 rounded w-12"></div>
                    </div>
                </div>
            ))}
        </div>
    </div>
));

// Memoized stat card component
const StatCard: React.FC<{
    title: string;
    value: number;
    icon: React.ReactNode;
    link: string;
    linkText: string;
    color: string;
}> = React.memo(({ title, value, icon, link, linkText, color }) => (
    <div className="bg-white p-6 rounded-lg shadow-sm">
        <div className="flex items-center justify-between">
            <div>
                <p className="text-sm font-medium text-gray-500">{title}</p>
                <p className="text-2xl font-semibold text-gray-900">{value.toLocaleString()}</p>
            </div>
            <div className={`p-3 ${color} rounded-full`}>
                {icon}
            </div>
        </div>
        <div className="mt-4">
            <Link to={link} className="text-sm text-[#F24495] hover:underline">
                {linkText} →
            </Link>
        </div>
    </div>
));

// Memoized recruiter item component
const RecruiterItem: React.FC<{
    recruiter: {
        id: string;
        name: string;
        hiredCandidates: number;
        activeProcesses: number;
    };
}> = React.memo(({ recruiter }) => (
    <div className="flex items-center justify-between p-3 bg-gray-50 rounded-md">
        <div className="flex items-center">
            <div className="w-8 h-8 bg-[#F24495] text-white rounded-full flex items-center justify-center font-medium">
                {recruiter.name.charAt(0)}
            </div>
            <div className="ml-3">
                <p className="text-sm font-medium text-gray-900">{recruiter.name}</p>
                <p className="text-xs text-gray-500">{recruiter.activeProcesses} procesos activos</p>
            </div>
        </div>
        <div className="text-right">
            <p className="text-sm font-semibold text-gray-900">{recruiter.hiredCandidates}</p>
            <p className="text-xs text-gray-500">contrataciones</p>
        </div>
    </div>
));

// Memoized hire item component
const HireItem: React.FC<{
    hire: {
        id: string;
        name: string;
        position: string;
        hireDate: string;
    };
}> = React.memo(({ hire }) => (
    <div className="p-3 border-l-2 border-[#F24495] bg-gray-50 rounded-r-md">
        <p className="text-sm font-medium text-gray-900">{hire.name}</p>
        <p className="text-xs text-gray-500">{hire.position}</p>
        <p className="text-xs text-gray-400 mt-1">
            Contratado el {new Date(hire.hireDate).toLocaleDateString()}
        </p>
    </div>
));

// Memoized status item component
const StatusItem: React.FC<{
    item: {
        status: string;
        count: number;
    };
}> = React.memo(({ item }) => (
    <div className="p-4 bg-gray-50 rounded-md text-center">
        <p className="text-xl font-semibold text-gray-900">{item.count}</p>
        <p className="text-sm text-gray-600 mt-1">{item.status}</p>
    </div>
));

const HRDashboard: React.FC = () => {
    const [stats, setStats] = useState<DashboardStats | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [refreshKey, setRefreshKey] = useState(0);

    // Memoized fetch function
    const loadDashboardStats = useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);
            const response = await getHRDashboardStats();

            if (response.success && response.data) {
                setStats(response.data);
            } else {
                setError(response.error || 'Error al cargar estadísticas del dashboard');
            }
        } catch (error) {
            console.error('Error al cargar el dashboard de RRHH:', error);
            setError('Error de conexión, intenta más tarde');
        } finally {
            setIsLoading(false);
        }
    }, []);

    // Memoized refresh function
    const handleRefresh = useCallback(() => {
        setRefreshKey(prev => prev + 1);
        loadDashboardStats();
    }, [loadDashboardStats]);

    // Effect for loading data
    useEffect(() => {
        loadDashboardStats();
    }, [loadDashboardStats, refreshKey]);

    // Memoized stat cards data
    const statCards = useMemo(() => {
        if (!stats) return [];

        return [
            {
                title: 'Total Candidatos',
                value: stats.totalCandidates,
                icon: (
                    <svg className="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                ),
                link: '/hr/candidates',
                linkText: 'Ver todos los candidatos',
                color: 'bg-blue-50'
            },
            {
                title: 'Vacantes Activas',
                value: stats.activeJobs,
                icon: (
                    <svg className="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                ),
                link: '/hr/jobs',
                linkText: 'Gestionar vacantes',
                color: 'bg-green-50'
            },
            {
                title: 'Postulaciones Pendientes',
                value: stats.pendingApplications,
                icon: (
                    <svg className="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                ),
                link: '/hr/applications',
                linkText: 'Revisar postulaciones',
                color: 'bg-yellow-50'
            },
            {
                title: 'Entrevistas Programadas',
                value: stats.interviewsScheduled,
                icon: (
                    <svg className="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                ),
                link: '/hr/calendar',
                linkText: 'Ver calendario',
                color: 'bg-purple-50'
            }
        ];
    }, [stats]);

    // Memoized progress bar width
    const hiringRateWidth = useMemo(() => {
        return stats ? Math.min(stats.hiringRate, 100) : 0;
    }, [stats]);

    // Loading state
    if (isLoading) {
        return (
            <div className="min-h-screen bg-gray-50 p-6">
                <div className="flex items-center justify-between mb-6">
                    <div className="h-8 bg-gray-200 rounded w-64 animate-pulse"></div>
                    <div className="h-10 bg-gray-200 rounded w-20 animate-pulse"></div>
                </div>

                {/* Skeleton for stat cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    {[...Array(4)].map((_, i) => (
                        <StatCardSkeleton key={i} />
                    ))}
                </div>

                {/* Skeleton for charts */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <ChartSkeleton />
                    <ChartSkeleton />
                </div>

                {/* Skeleton for lists */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <ListSkeleton />
                    <ListSkeleton />
                </div>

                <ChartSkeleton />
            </div>
        );
    }

    // Error state
    if (error || !stats) {
        return (
            <div className="min-h-screen bg-gray-50 p-6">
                <div className="bg-red-50 p-4 rounded-md text-red-600 mb-4">
                    {error || 'No se pudieron cargar los datos del dashboard'}
                </div>
                <button
                    onClick={handleRefresh}
                    className="px-4 py-2 bg-[#F24495] text-white rounded-md shadow-sm hover:bg-[#E13578] transition-colors"
                >
                    Reintentar
                </button>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 p-6">
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Dashboard de Recursos Humanos</h1>
                <button
                    onClick={handleRefresh}
                    className="px-4 py-2 bg-[#F24495] text-white rounded-md shadow-sm hover:bg-[#E13578] transition-colors"
                >
                    Actualizar
                </button>
            </div>

            {/* Tarjetas de resumen - Memoizadas */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                {statCards.map((card, index) => (
                    <StatCard key={index} {...card} />
                ))}
            </div>

            {/* Métricas de contratación */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div className="bg-white p-6 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Tasa de contratación</h3>
                    <div className="flex items-end mb-4">
                        <p className="text-3xl font-bold text-[#F24495]">{stats.hiringRate}%</p>
                        <p className="ml-2 text-sm text-gray-500">este mes</p>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2.5">
                        <div
                            className="bg-[#F24495] h-2.5 rounded-full transition-all duration-300"
                            style={{ width: `${hiringRateWidth}%` }}
                        ></div>
                    </div>
                    <p className="mt-2 text-sm text-gray-600">
                        Porcentaje de candidatos contratados sobre el total de entrevistados
                    </p>
                </div>

                <div className="bg-white p-6 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Tiempo promedio de contratación</h3>
                    <div className="flex items-end mb-4">
                        <p className="text-3xl font-bold text-[#F24495]">{stats.timeToHire}</p>
                        <p className="ml-2 text-sm text-gray-500">días</p>
                    </div>
                    <div className="flex items-center mt-2">
                        <svg className="w-4 h-4 text-gray-400 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p className="text-sm text-gray-600">
                            Desde la primera entrevista hasta la oferta aceptada
                        </p>
                    </div>
                </div>
            </div>

            {/* Top reclutadores y contrataciones recientes */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div className="bg-white p-6 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Top Reclutadores</h3>
                    <div className="space-y-4">
                        {stats.topRecruiters.map(recruiter => (
                            <RecruiterItem key={recruiter.id} recruiter={recruiter} />
                        ))}
                    </div>
                    <div className="mt-4">
                        <Link to="/hr/recruiters" className="text-sm text-[#F24495] hover:underline">
                            Ver todos los reclutadores →
                        </Link>
                    </div>
                </div>

                <div className="bg-white p-6 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Contrataciones Recientes</h3>
                    <div className="space-y-4">
                        {stats.recentHires.map(hire => (
                            <HireItem key={hire.id} hire={hire} />
                        ))}
                    </div>
                    <div className="mt-4">
                        <Link to="/hr/hires" className="text-sm text-[#F24495] hover:underline">
                            Ver historial de contrataciones →
                        </Link>
                    </div>
                </div>
            </div>

            {/* Distribución de candidatos por estado */}
            <div className="bg-white p-6 rounded-lg shadow-sm">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Candidatos por Estado</h3>
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    {stats.candidatesByStatus.map(item => (
                        <StatusItem key={item.status} item={item} />
                    ))}
                </div>
            </div>
        </div>
    );
};

export default React.memo(HRDashboard);
