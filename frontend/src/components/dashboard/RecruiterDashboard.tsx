// src/components/dashboard/RecruiterDashboard.tsx
import React, { useState, useEffect } from 'react';
import { getRecruiterDashboardStats } from '../../services/ApiService';

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

const RecruiterDashboard: React.FC<RecruiterDashboardProps> = ({ recruiterId }) => {
  const [stats, setStats] = useState<RecruiterDashboardStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadDashboardStats = async () => {
      try {
        setIsLoading(true);
        const response = await getRecruiterDashboardStats(recruiterId);

        if (response.success && response.data) {
          setStats(response.data);
        } else {
          setError(response.error || 'Error al cargar estadísticas del dashboard');
        }
      } catch (error) {
        console.error('Error al cargar el dashboard de reclutador:', error);
        setError('Error de conexión, intenta más tarde');

        // Fallback con datos por defecto
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
      } finally {
        setIsLoading(false);
      }
    };

    if (recruiterId) {
      loadDashboardStats();
    }
  }, [recruiterId]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 p-6">
        <div className="text-center">Cargando dashboard...</div>
      </div>
    );
  }

  if (error || !stats) {
    return (
      <div className="min-h-screen bg-gray-50 p-6">
        <div className="bg-red-50 p-4 rounded-md text-red-600 mb-4">
          {error || 'No se pudieron cargar los datos del dashboard'}
        </div>
        <button
          onClick={() => window.location.reload()}
          className="px-4 py-2 bg-[#F24495] text-white rounded-md shadow-sm"
        >
          Reintentar
        </button>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Dashboard de Reclutador</h1>

      {/* Tarjetas de resumen */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div className="bg-white p-6 rounded-lg shadow-sm">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500">Vacantes Asignadas</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.assignedJobs}</p>
            </div>
            <div className="p-3 bg-blue-50 rounded-full">
              <svg className="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
          <div className="mt-4">
            <Link to="/recruiter/jobs" className="text-sm text-[#F24495] hover:underline">
              Ver mis vacantes →
            </Link>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500">Postulaciones Pendientes</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.pendingApplications}</p>
            </div>
            <div className="p-3 bg-yellow-50 rounded-full">
              <svg className="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
          </div>
          <div className="mt-4">
            <Link to="/recruiter/applications" className="text-sm text-[#F24495] hover:underline">
              Revisar postulaciones →
            </Link>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500">Entrevistas Programadas</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.interviewsScheduled}</p>
            </div>
            <div className="p-3 bg-purple-50 rounded-full">
              <svg className="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
          <div className="mt-4">
            <Link to="/recruiter/calendar" className="text-sm text-[#F24495] hover:underline">
              Ver mi calendario →
            </Link>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500">Candidatos en Proceso</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.candidatesInProcess}</p>
            </div>
            <div className="p-3 bg-green-50 rounded-full">
              <svg className="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
          </div>
          <div className="mt-4">
            <Link to="/recruiter/candidates" className="text-sm text-[#F24495] hover:underline">
              Ver todos los candidatos →
            </Link>
          </div>
        </div>
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
            className="bg-[#F24495] h-2.5 rounded-full"
            style={{ width: `${stats.conversionRate}%` }}
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
                <div key={interview.id} className="p-3 bg-gray-50 rounded-md">
                  <div className="flex justify-between items-start">
                    <div>
                      <p className="text-sm font-medium text-gray-900">{interview.candidateName}</p>
                      <p className="text-xs text-gray-500">{interview.jobTitle}</p>
                    </div>
                    <div className={`px-2 py-1 rounded-full text-xs font-medium ${interview.status === 'confirmed' ? 'bg-green-100 text-green-800' :
                      interview.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                        interview.status === 'rescheduled' ? 'bg-blue-100 text-blue-800' :
                          'bg-red-100 text-red-800'
                      }`}>
                      {interview.status === 'confirmed' ? 'Confirmada' :
                        interview.status === 'pending' ? 'Pendiente' :
                          interview.status === 'rescheduled' ? 'Reprogramada' :
                            'Cancelada'
                      }
                    </div>
                  </div>
                  <div className="mt-2 flex items-center">
                    <svg className="w-4 h-4 text-gray-400 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p className="text-xs text-gray-600">
                      {new Date(interview.dateTime).toLocaleString()}
                    </p>
                  </div>
                </div>
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
                <div key={application.id} className="p-3 border-l-2 border-[#F24495] bg-gray-50 rounded-r-md">
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
              <div key={item.stage} className="p-4 bg-gray-50 rounded-md text-center">
                <p className="text-xl font-semibold text-gray-900">{item.count}</p>
                <p className="text-sm text-gray-600 mt-1">{item.stage}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm">
          <h3 className="text-lg font-medium text-gray-900 mb-4">Actividad Reciente</h3>
          <div className="space-y-6 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-gray-300 before:to-transparent">
            {stats.activityTimeline.map((activity, index) => (
              <div key={activity.id} className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                <div className="flex items-center justify-center w-10 h-10 rounded-full border border-white bg-[#F24495] text-white shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
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
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default RecruiterDashboard;
