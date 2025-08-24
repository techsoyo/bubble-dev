// src/components/dashboard/HRDashboard.tsx
import React, { useState, useEffect } from 'react';
import { getHRDashboardStats } from '../../services/ApiService';
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

const HRDashboard: React.FC = () => {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadDashboardStats = async () => {
      try {
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
    };

    loadDashboardStats();
  }, []);

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
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Dashboard de Recursos Humanos</h1>

      {/* Tarjetas de resumen */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div className="bg-white p-6 rounded-lg shadow-sm">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500">Total Candidatos</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.totalCandidates}</p>
            </div>
            <div className="p-3 bg-blue-50 rounded-full">
              <svg className="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
          </div>
          <div className="mt-4">
            <Link to="/hr/candidates" className="text-sm text-[#F24495] hover:underline">
              Ver todos los candidatos →
            </Link>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-sm">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-500">Vacantes Activas</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.activeJobs}</p>
            </div>
            <div className="p-3 bg-green-50 rounded-full">
              <svg className="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
          <div className="mt-4">
            <Link to="/hr/jobs" className="text-sm text-[#F24495] hover:underline">
              Gestionar vacantes →
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
            <Link to="/hr/applications" className="text-sm text-[#F24495] hover:underline">
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
            <Link to="/hr/calendar" className="text-sm text-[#F24495] hover:underline">
              Ver calendario →
            </Link>
          </div>
        </div>
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
              className="bg-[#F24495] h-2.5 rounded-full"
              style={{ width: `${stats.hiringRate}%` }}
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
            <svg className="w-4 h-4 text-gray-400 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
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
              <div key={recruiter.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-md">
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
              <div key={hire.id} className="p-3 border-l-2 border-[#F24495] bg-gray-50 rounded-r-md">
                <p className="text-sm font-medium text-gray-900">{hire.name}</p>
                <p className="text-xs text-gray-500">{hire.position}</p>
                <p className="text-xs text-gray-400 mt-1">Contratado el {new Date(hire.hireDate).toLocaleDateString()}</p>
              </div>
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
            <div key={item.status} className="p-4 bg-gray-50 rounded-md text-center">
              <p className="text-xl font-semibold text-gray-900">{item.count}</p>
              <p className="text-sm text-gray-600 mt-1">{item.status}</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default HRDashboard;
