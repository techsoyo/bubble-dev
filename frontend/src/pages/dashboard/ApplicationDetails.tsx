import { useEffect, useState, useMemo } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { Button } from '../../components/ui/button';
import { Layout } from '../../components/layout/Layout';
import { getApplications, getJobs } from '../../services/ApiService';
import { ChevronLeft, Calendar, MapPin, Briefcase, Clock } from 'lucide-react';
import StatusChangeForm from '../../components/StatusChangeForm';
import { useAuth } from '../../contexts/AuthContextSimple';
import { StatusBadge } from '../../components/ui/status-badge';
import { DashboardHeader } from '../../components/ui/dashboard-header';
import { Card, CardHeader, CardTitle, CardContent } from '../../components/ui/card';

// Define types for our data structures
interface ApplicationHistory {
  status: string;
  date: string;
  notes: string;
}

interface Application {
  id: string;
  jobId: string;
  status: string;
  appliedDate: string;
  history: ApplicationHistory[];
}

interface Job {
  id: string;
  title: string;
  location: string;
  type: string;
  department?: string;
  salary?: string;
}

export default function ApplicationDetailsPage() {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const [application, setApplication] = useState<Application | null>(null);
  const [job, setJob] = useState<Job | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Funciones de utilidad memoizadas
  const formatDate = useMemo(() => (date: string) => {
    return new Date(date).toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }, []);

  const getProgressPercentage = useMemo(() => {
    if (!application?.history?.length) return 0;
    const totalSteps = 5; // Postulación + 4 pasos futuros
    return (application.history.length / totalSteps) * 100;
  }, [application?.history?.length]);

  // Check if user is logged in and find application
  useEffect(() => {

    // Producción: solo usar contexto de autenticación
    const authContext = useAuth();
    if (!authContext.isLoggedIn) {
      navigate('/auth/register'); // Candidatos van al registro/login de candidatos
      return;
    }
    loadApplicationData(authContext.user?.email);
  }, [id, navigate]);

  const loadApplicationData = async (userEmail?: string) => {
    try {
      setLoading(true);
      setError(null);
      if (!userEmail) {
        navigate('/auth/register'); // Candidatos van al registro/login de candidatos
        return;
      }
      // Get all applications and jobs
      const [applicationsResponse, jobsResponse] = await Promise.all([
        getApplications(),
        getJobs()
      ]);

      if (!applicationsResponse.success || !jobsResponse.success) {
        throw new Error('Error al cargar los datos');
      }

      const applications = applicationsResponse.data || [];
      const jobs = jobsResponse.data || [];

      // Find the specific application by ID and user email
      const foundApplication = applications.find((app: any) =>
        app.id === id && app.candidate_email === userEmail
      );

      if (!foundApplication) {
        navigate('/dashboard/cddashboard');
        return;
      }

      // Create application history from status changes
      const history: ApplicationHistory[] = [
        {
          status: 'Recibida',
          date: foundApplication.created_at || foundApplication.application_date,
          notes: 'Solicitud recibida correctamente.'
        }
      ];

      // Add current status if different from 'Recibida'
      if (foundApplication.status && foundApplication.status !== 'Recibida') {
        history.push({
          status: foundApplication.status,
          date: foundApplication.updated_at || foundApplication.created_at,
          notes: foundApplication.notes || 'Estado actualizado por el equipo de RRHH.'
        });
      }

      const applicationData: Application = {
        id: foundApplication.id,
        jobId: foundApplication.job_id,
        status: foundApplication.status || 'Recibida',
        appliedDate: foundApplication.created_at || foundApplication.application_date,
        history: history
      };

      setApplication(applicationData);

      // Find job details
      const jobDetails = jobs.find((job: any) => job.id === foundApplication.job_id);
      if (jobDetails) {
        const jobData: Job = {
          id: jobDetails.id,
          title: jobDetails.title,
          location: jobDetails.location || jobDetails.company_location || 'No especificado',
          type: jobDetails.employment_type || jobDetails.type || 'No especificado',
          department: jobDetails.department,
          salary: jobDetails.salary
        };
        setJob(jobData);
      }

    } catch (error) {
      console.error('Error cargando aplicación:', error);
      setError('Error al cargar los detalles de la aplicación');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <Layout>
        <div className="container mx-auto px-4 py-8">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#FF4785] mx-auto"></div>
            <p className="mt-4 text-gray-600">Cargando detalles de la aplicación...</p>
          </div>
        </div>
      </Layout>
    );
  }

  if (error) {
    return (
      <Layout>
        <div className="container mx-auto px-4 py-8">
          <div className="text-center">
            <p className="text-red-600">{error}</p>
            <Button
              onClick={() => navigate('/dashboard/cddashboard')}
              className="mt-4"
            >
              Volver al panel
            </Button>
          </div>
        </div>
      </Layout>
    );
  }

  if (!application || !job) {
    return (
      <Layout>
        <div className="container mx-auto px-4 py-8">
          <div className="text-center">
            <p className="text-gray-600">Aplicación no encontrada</p>
            <Button
              onClick={() => navigate('/dashboard/cddashboard')}
              className="mt-4"
            >
              Volver al panel
            </Button>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      {/* Dashboard Header */}
      <DashboardHeader
        title={`Aplicación: ${job?.title || 'Cargando...'}`}
        subtitle="Detalles y progreso de tu aplicación"
        breadcrumbs={[
          { label: 'Dashboard', href: '/dashboard/cddashboard' },
          { label: 'Mis Aplicaciones', href: '/dashboard/cddashboard' },
          { label: job?.title || 'Detalle' }
        ]}
        actions={
          <Button
            variant="outline"
            onClick={() => navigate('/dashboard/cddashboard')}
            className="flex items-center gap-2"
          >
            <ChevronLeft size={16} />
            Volver al panel
          </Button>
        }
      />

      <div className="container mx-auto px-4 py-8">
        {/* Application Header */}
        <Card className="mb-6">
          <CardHeader>
            <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
              <div className="flex-1">
                <CardTitle className="text-2xl font-bold text-[#2F2F2F] mb-2">
                  {job.title}
                </CardTitle>
                <div className="flex flex-wrap items-center gap-4 text-gray-600">
                  <div className="flex items-center gap-1">
                    <MapPin size={16} />
                    <span>{job.location}</span>
                  </div>
                  <div className="flex items-center gap-1">
                    <Briefcase size={16} />
                    <span>{job.type}</span>
                  </div>
                  {job.department && (
                    <div className="flex items-center gap-1">
                      <span>🏢</span>
                      <span>{job.department}</span>
                    </div>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-4">
                <StatusBadge status={application.status} size="lg" />
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 border-t pt-6">
              <div className="text-center md:text-left">
                <div className="flex items-center justify-center md:justify-start gap-2 mb-1">
                  <Calendar size={16} className="text-[#FF4785]" />
                  <h3 className="text-sm font-medium text-gray-500">Fecha de postulación</h3>
                </div>
                <p className="font-semibold">{formatDate(application.appliedDate)}</p>
              </div>
              <div className="text-center md:text-left">
                <div className="flex items-center justify-center md:justify-start gap-2 mb-1">
                  <Clock size={16} className="text-[#FF4785]" />
                  <h3 className="text-sm font-medium text-gray-500">Última actualización</h3>
                </div>
                {application?.history?.length ? (
                  <p className="font-semibold">{formatDate(application.history[application.history.length - 1]?.date ?? '')}</p>
                ) : (
                  <p className="font-semibold text-gray-400">Sin historial</p>
                )}
              </div>
              <div className="text-center md:text-left">
                <div className="flex items-center justify-center md:justify-start gap-2 mb-1">
                  <span className="text-[#FF4785]">#️⃣</span>
                  <h3 className="text-sm font-medium text-gray-500">Referencia</h3>
                </div>
                <p className="font-semibold">#{application.id}</p>
              </div>
            </div>

            {/* Progress Bar */}
            <div className="mt-6 pt-6 border-t border-gray-200">
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-gray-700">Progreso general</span>
                <span className="text-sm text-gray-500">{Math.round(getProgressPercentage)}%</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div
                  className="bg-gradient-to-r from-[#FF4785] to-[#FF3575] h-2 rounded-full transition-all duration-500 ease-out"
                  style={{ width: `${getProgressPercentage}%` }}
                ></div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Application Progress */}
        <Card className="mb-6">
          <CardHeader>
            <CardTitle className="text-xl font-semibold text-[#2F2F2F] flex items-center gap-2">
              <span className="text-[#FF4785]">📈</span>
              Progreso de tu postulación
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="relative">
              <div className="absolute left-3 top-0 bottom-0 w-0.5 bg-gray-200"></div>

              {application?.history?.map((item, index) => (
                <div key={index} className="relative flex items-start mb-6 last:mb-0">
                  <div className={`absolute left-3 -translate-x-1/2 w-6 h-6 rounded-full flex items-center justify-center ${index === application.history.length - 1
                    ? 'bg-[#FF4785] shadow-lg shadow-[#FF4785]/25'
                    : 'bg-green-500'
                    } text-white`}>
                    {index === application.history.length - 1 ? (
                      <span className="text-xs font-bold">{index + 1}</span>
                    ) : (
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                      </svg>
                    )}
                  </div>

                  <div className="ml-10">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-2">
                      <h3 className="font-semibold text-[#2F2F2F]">{item.status}</h3>
                      <span className="text-sm text-gray-500 flex items-center gap-1">
                        <Calendar size={14} />
                        {formatDate(item.date)}
                      </span>
                    </div>
                    <p className="text-gray-600 mt-1">{item.notes}</p>
                  </div>
                </div>
              ))}

              {/* Future steps (disabled) */}
              {['Entrevista', 'Prueba técnica', 'Entrevista final', 'Decisión'].map((step, index) => {
                const stepIndex = index + application.history.length;
                return (
                  <div key={stepIndex} className="relative flex items-start mb-6 last:mb-0 opacity-50">
                    <div className="absolute left-3 -translate-x-1/2 w-6 h-6 rounded-full flex items-center justify-center bg-gray-300 text-white">
                      <span className="text-xs">{stepIndex + 1}</span>
                    </div>

                    <div className="ml-10">
                      <h3 className="font-semibold text-gray-500">{step}</h3>
                      <p className="text-gray-400 mt-1">Pendiente</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </CardContent>
        </Card>

        {/* Application Actions */}
        <Card>
          <CardHeader>
            <CardTitle className="text-lg font-semibold text-[#2F2F2F] flex items-center gap-2">
              <span className="text-[#FF4785]">⚙️</span>
              ¿Quieres actualizar algo?
            </CardTitle>
            <p className="text-gray-600">Puedes modificar o retirar tu postulación en cualquier momento</p>
          </CardHeader>
          <CardContent>
            <div className="flex flex-col sm:flex-row gap-4">
              <Button
                variant="outline"
                className="border-[#FF4785] text-[#FF4785] hover:bg-[#FF4785] hover:text-white"
              >
                Actualizar CV
              </Button>
              <Button
                variant="outline"
                className="text-red-600 border-red-600 hover:bg-red-50"
              >
                Retirar postulación
              </Button>
            </div>

            {/* Status Change Form */}
            <div className="mt-6 pt-6 border-t border-gray-200">
              <h3 className="text-sm font-medium text-gray-700 mb-4">Formulario de cambio de estado (Solo para pruebas)</h3>
              <StatusChangeForm />
            </div>
          </CardContent>
        </Card>
      </div>
    </Layout>
  );
}
