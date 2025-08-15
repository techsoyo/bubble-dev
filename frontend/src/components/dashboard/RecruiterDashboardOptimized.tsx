/**
 * Optimized Recruiter Dashboard Component
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
import { getRecruiterDashboardStats } from '../../lib/apiService';
import { Link } from 'react-router-dom';

interface RecruiterDashboardStats {
    assignedJobs: number;
    pendingApplications: number;
    interviewsScheduled: number;
    candidatesInProcess: number;
    conversionRate: number;
    upcomingInterviews: Array<{
        id: string;
        candidateName: string;
        jobTitle: string;
        dateTime: string;
        status: 'pending' | 'confirmed' | 'rescheduled' | 'cancelled';
    }>;
    recentApplications: Array<{
        id: string;
        candidateName: string;
        jobTitle: string;
        appliedDate: string;
        status: string;
    }>;
    applicationsByStage: Array<{
        stage: string;
        count: number;
    }>;
    activityTimeline: Array<{
        id: string;
        date: string;
        activity: string;
        candidateName?: string;
        jobTitle?: string;
    }>;
}

interface RecruiterDashboardProps {
    recruiterId: string;
}

// Memoized skeleton components
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

const TimelineItemSkeleton: React.FC = React.memo(() => (
    <div className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group animate-pulse">
        <div className="w-10 h-10 bg-gray-200 rounded-full shrink-0"></div>
        <div className="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-3 bg-gray-50 rounded-md">
            <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
            <div className="h-3 bg-gray-200 rounded w-1/2 mb-1"></div>
            <div className="h-3 bg-gray-200 rounded w-1/3"></div>
        </div>
    </div>
));

// Memoized status badge component
const StatusBadge: React.FC<{ status: string }> = React.memo(({ status }) => {
    const getStatusColor = useCallback((status: string) => {
        switch (status) {
            case 'confirmed':
                return 'bg-green-100 text-green-800';
            case 'pending':
                return 'bg-yellow-100 text-yellow-800';
            case 'rescheduled':
                return 'bg-blue-100 text-blue-800';
            case 'cancelled':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    }, []);

    const getStatusText = useCallback((status: string) => {
        switch (status) {
            case 'confirmed':
                return 'Confirmada';
            case 'pending':
                return 'Pendiente';
            case 'rescheduled':
                return 'Reprogramada';
            case 'cancelled':
                return 'Cancelada';
            default:
                return status;
        }
    }, []);

    return (
        <div className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(status)}`}>
            {getStatusText(status)}
        </div>
    );
});

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

// Memoized interview item component
const InterviewItem: React.FC<{
    interview: {
        id: string;
        candidateName: string;
        jobTitle: string;
        dateTime: string;
        status: 'pending' | 'confirmed' | 'rescheduled' | 'cancelled';
    };
}> = React.memo(({ interview }) => (
    <div className="p-3 bg-gray-50 rounded-md">
        <div className="flex justify-between items-start">
            <div>
                <p className="text-sm font-medium text-gray-900">{interview.candidateName}</p>
                <p className="text-xs text-gray-500">{interview.jobTitle}</p>
            </div>
            <StatusBadge status={interview.status} />
        </div>
        <div className="mt-2 flex items-center">
            <svg className="w-4 h-4 text-gray-400 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p className="text-xs text-gray-600">
                {new Date(interview.dateTime).toLocaleString()}
            </p>
        </div>
    </div>
));

// Memoized application item component
const ApplicationItem: React.FC<{
    application: {
        id: string;
        candidateName: string;
        jobTitle: string;
        appliedDate: string;
        status: string;
    };
}> = React.memo(({ application }) => (
    <div className="p-3 border-l-2 border-[#F24495] bg-gray-50 rounded-r-md">
        <div className="flex justify-between items-start">
            <div>
                <p className="text-sm font-medium text-gray-900">{application.candidateName}</p>
                <p className="text-xs text-gray-500">{application.jobTitle}</p>
            </div>
            <p className="text-xs text-gray-400">
                {new Date(application.appliedDate).toLocaleDateString()}
            </p>
        </div>
        <div className="mt-2">
            <span className="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">
                {application.status}
            </span>
        </div>
    </div>
));

// Memoized activity timeline item
const ActivityTimelineItem: React.FC<{
    activity: {
        id: string;
        date: string;
        activity: string;
        candidateName?: string;
        jobTitle?: string;
    };
}> = React.memo(({ activity }) => (
    <div className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
        <div className="flex items-center justify-center w-10 h-10 rounded-full border border-white bg-[#F24495] text-white shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2">
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <div className="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-3 bg-gray-50 rounded-md shadow-sm">
            <div className="font-medium text-gray-900 text-sm">{activity.activity}</div>
            {(activity.candidateName || activity.jobTitle) && (
                <div className="text-xs text-gray-500 mt-1">
                    {activity.candidateName && <span>{activity.candidateName}</span>}
                    {activity.candidateName && activity.jobTitle && <span> - </span>}
                    {activity.jobTitle && <span>{activity.jobTitle}</span>}
                </div>
            )}
            <div className="text-xs text-gray-400 mt-1">
                {new Date(activity.date).toLocaleString()}
            </div>
        </div>
    </div>
));

// Memoized stage item component
const StageItem: React.FC<{
    item: {
        stage: string;
        count: number;
    };
}> = React.memo(({ item }) => (
    <div className="p-4 bg-gray-50 rounded-md text-center">
        <p className="text-xl font-semibold text-gray-900">{item.count}</p>
        <p className="text-sm text-gray-600 mt-1">{item.stage}</p>
    </div>
));

const RecruiterDashboard: React.FC<RecruiterDashboardProps> = ({ recruiterId }) => {
    const [stats, setStats] = useState<RecruiterDashboardStats | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [refreshKey, setRefreshKey] = useState(0);

    // Memoized fetch function
    const loadDashboardStats = useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);
            const response = await getRecruiterDashboardStats(recruiterId);

            if (response.success && response.data) {
                setStats(response.data as RecruiterDashboardStats);
            } else {
                setError(response.error || 'Error al cargar estadísticas del dashboard');

                // Fallback with default data
                setStats({
                    assignedJobs: 3,
                    pendingApplications: 12,
                    interviewsScheduled: 5,
                    candidatesInProcess: 18,
                    conversionRate: 65,
                    upcomingInterviews: [],
                    recentApplications: [],
                    applicationsByStage: [
                        { stage: 'Received', count: 8 },
                        { stage: 'Under Review', count: 5 },
                        { stage: 'Interview', count: 3 },
                        { stage: 'Hired', count: 2 }
                    ],
                    activityTimeline: []
                });
            }
        } catch (error) {
            console.error('Error al cargar el dashboard de reclutador:', error);
            setError('Error de conexión, intenta más tarde');
        } finally {
            setIsLoading(false);
        }
    }, [recruiterId]);

    // Memoized refresh function
    const handleRefresh = useCallback(() => {
        setRefreshKey(prev => prev + 1);
        loadDashboardStats();
    }, [loadDashboardStats]);

    // Effect for loading data
    useEffect(() => {
        if (recruiterId) {
            loadDashboardStats();
        }
    }, [recruiterId, loadDashboardStats, refreshKey]);

    // Memoized stat cards data
    const statCards = useMemo(() => {
        if (!stats) return [];

        return [
            {
                title: 'Vacantes Asignadas',
                value: stats.assignedJobs,
                icon: (
                    <svg className="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                ),
                link: '/recruiter/jobs',
                linkText: 'Ver mis vacantes',
                color: 'bg-blue-50'
            },
            {
                title: 'Postulaciones Pendientes',
                value: stats.pendingApplications,
                icon: (
                    <svg className="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                ),
                link: '/recruiter/applications',
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
                link: '/recruiter/calendar',
                linkText: 'Ver mi calendario',
                color: 'bg-purple-50'
            },
            {
                title: 'Candidatos en Proceso',
                value: stats.candidatesInProcess,
                icon: (
                    <svg className="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                ),
                link: '/recruiter/candidates',
                linkText: 'Ver todos los candidatos',
                color: 'bg-green-50'
            }
        ];
    }, [stats]);

    // Memoized conversion rate width
    const conversionRateWidth = useMemo(() => {
        return stats ? Math.min(stats.conversionRate, 100) : 0;
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

                {/* Skeleton for conversion rate */}
                <div className="bg-white p-6 rounded-lg shadow-sm mb-8 animate-pulse">
                    <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
                    <div className="h-8 bg-gray-200 rounded w-1/4 mb-4"></div>
                    <div className="h-3 bg-gray-200 rounded"></div>
                </div>

                {/* Skeleton for grid sections */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div className="bg-white p-6 rounded-lg shadow-sm animate-pulse">
                        <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
                        <div className="space-y-3">
                            {[...Array(3)].map((_, i) => (
                                <div key={i} className="h-16 bg-gray-200 rounded"></div>
                            ))}
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-lg shadow-sm animate-pulse">
                        <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
                        <div className="space-y-3">
                            {[...Array(3)].map((_, i) => (
                                <div key={i} className="h-16 bg-gray-200 rounded"></div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Skeleton for bottom sections */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-white p-6 rounded-lg shadow-sm animate-pulse">
                        <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
                        <div className="grid grid-cols-2 gap-4">
                            {[...Array(4)].map((_, i) => (
                                <div key={i} className="h-16 bg-gray-200 rounded"></div>
                            ))}
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-lg shadow-sm animate-pulse">
                        <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
                        <div className="space-y-6">
                            {[...Array(3)].map((_, i) => (
                                <TimelineItemSkeleton key={i} />
                            ))}
                        </div>
                    </div>
                </div>
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
                <h1 className="text-2xl font-bold text-gray-900">Dashboard de Reclutador</h1>
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

            {/* Tasa de conversión */}
            <div className="bg-white p-6 rounded-lg shadow-sm mb-8">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Tasa de conversión</h3>
                <div className="flex items-end mb-4">
                    <p className="text-3xl font-bold text-[#F24495]">{stats.conversionRate}%</p>
                    <p className="ml-2 text-sm text-gray-500">este mes</p>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2.5">
                    <div
                        className="bg-[#F24495] h-2.5 rounded-full transition-all duration-300"
                        style={{ width: `${conversionRateWidth}%` }}
                    ></div>
                </div>
                <p className="mt-2 text-sm text-gray-600">
                    Porcentaje de candidatos que pasan a la siguiente etapa del proceso
                </p>
            </div>

            {/* Próximas entrevistas y postulaciones recientes */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div className="bg-white p-6 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Próximas Entrevistas</h3>
                    <div className="space-y-4">
                        {stats.upcomingInterviews.length > 0 ? (
                            stats.upcomingInterviews.map(interview => (
                                <InterviewItem key={interview.id} interview={interview} />
                            ))
                        ) : (
                            <p className="text-sm text-gray-500">No hay entrevistas próximas programadas</p>
                        )}
                    </div>
                    <div className="mt-4">
                        <Link to="/recruiter/interviews" className="text-sm text-[#F24495] hover:underline">
                            Ver todas las entrevistas →
                        </Link>
                    </div>
                </div>

                <div className="bg-white p-6 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Postulaciones Recientes</h3>
                    <div className="space-y-4">
                        {stats.recentApplications.length > 0 ? (
                            stats.recentApplications.map(application => (
                                <ApplicationItem key={application.id} application={application} />
                            ))
                        ) : (
                            <p className="text-sm text-gray-500">No hay postulaciones recientes</p>
                        )}
                    </div>
                    <div className="mt-4">
                        <Link to="/recruiter/applications" className="text-sm text-[#F24495] hover:underline">
                            Ver todas las postulaciones →
                        </Link>
                    </div>
                </div>
            </div>

            {/* Aplicaciones por etapa y línea de tiempo */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="bg-white p-6 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Postulaciones por Etapa</h3>
                    <div className="grid grid-cols-2 gap-4">
                        {stats.applicationsByStage.map(item => (
                            <StageItem key={item.stage} item={item} />
                        ))}
                    </div>
                </div>

                <div className="bg-white p-6 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Actividad Reciente</h3>
                    <div className="space-y-6 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-gray-300 before:to-transparent">
                        {stats.activityTimeline.length > 0 ? (
                            stats.activityTimeline.map(activity => (
                                <ActivityTimelineItem key={activity.id} activity={activity} />
                            ))
                        ) : (
                            <p className="text-sm text-gray-500">No hay actividad reciente</p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default React.memo(RecruiterDashboard);
