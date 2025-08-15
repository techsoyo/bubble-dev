import React, { useEffect, useState, useMemo, useReducer } from 'react';
import { Button } from '../../components/ui/button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '../../components/ui/tabs';
import { Card, CardHeader, CardTitle, CardContent } from '../../components/ui/card';
import { ApplicationsTable, ApplicationDetailsDialog } from '../../components/dashboard/ApplicationsTable';
import { Plus, Menu, Bot } from 'lucide-react';
import ChatbotAdmin from '../../components/ChatBotManage';
import StatusChangeForm from '../../components/StatusChangeForm';
import { StatusBadge } from '../../components/ui/status-badge';
import { Filters } from '../../components/ui/filters';
import { DashboardHeader } from '../../components/ui/dashboard-header';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  PieChart,
  Pie,
  Cell,
  ResponsiveContainer,
  LineChart,
  Line
} from 'recharts';
import {
  getHRDashboardStats,
  getApplications,
  getCandidates,
  getJobs,
  getRecruiters,
  getCandidateSkills,
  getCandidateExperiences,
} from '../../lib/apiService';
import { getCandidateDepartmentAssignment } from '../../lib/candidate-department-assignment';

import { useNavigate } from 'react-router-dom';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '../../components/ui/dialog';
import { Label } from '../../components/ui/label';
import { useLanguage } from '../../lib/i18n/LanguageContext';
import { sendRecruiterAssignmentNotification, sendCandidateStatusUpdateNotification } from '../../lib/emailService';
import { toast } from '../../components/ui/use-toast';
import { EmailHistoryViewer } from '../../components/dashboard/EmailHistoryViewer';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '../../components/ui/dropdown-menu';

// Definición de tipos
interface Candidate {
  id: string;
  name: string;
  email: string;
  location?: string;
  skills?: string[];
  department?: string;
  // Otros campos...
}

interface Job {
  id: string;
  title: string;
  location?: string;
  skills?: string[];
  department?: string;
  // Otros campos...
}

interface Application {
  id: string;
  candidate: Candidate;
  job: Job;
  appliedDate: string;
  status: string;
  score: number;
  insights?: any;
}

// Candidate Profile Modal Component
function CandidateProfileModal({
  candidate,
  open,
  onClose
}: {
  candidate: Candidate | null,
  open: boolean,
  onClose: () => void
}) {
  const { t } = useLanguage();
  const [skills, setSkills] = useState<string[]>([]);
  const [experiences, setExperiences] = useState<any[]>([]);

  useEffect(() => {
    // Cargar skills y experiencias cuando el candidato cambia
    const loadDetails = async () => {
      if (!candidate) return;
      try {
        const skillsResp = await getCandidateSkills(candidate.id);
        const mappedSkills = (skillsResp || []).map((s: any) => s.skill || s.skills || s.name || s);
        setSkills(mappedSkills);
      } catch (err) {
        console.error('Error loading candidate skills for modal:', err);
        setSkills(candidate?.skills || []);
      }
      try {
        const exps = await getCandidateExperiences(candidate.id);
        setExperiences(exps || []);
      } catch (err) {
        console.error('Error loading candidate experiences for modal:', err);
        setExperiences([]);
      }
    };
    loadDetails();
  }, [candidate]);

  if (!candidate) return null;

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>{t('hrDashboard.candidateDetails') || 'Candidate Details'}</DialogTitle>
          <DialogDescription>
            {t('hrDashboard.viewCandidateProfile') || 'View full candidate profile information'}
          </DialogDescription>
        </DialogHeader>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 py-4">
          <div>
            <h3 className="text-lg font-semibold mb-4">{t('profile.personalInfo') || 'Personal Information'}</h3>
            <div className="space-y-4">
              <div>
                <Label>{t('register.name') || 'Name'}</Label>
                <p className="font-medium">{candidate.name}</p>
              </div>
              <div>
                <Label>{t('register.email') || 'Email'}</Label>
                <p>{candidate.email}</p>
              </div>
              <div>
                <Label>{t('profile.location') || 'Location'}</Label>
                <p>{candidate.location || 'Not specified'}</p>
              </div>
            </div>
          </div>
          <div>
            <h3 className="text-lg font-semibold mb-4">{t('profile.skills') || 'Skills'}</h3>
            <div className="flex flex-wrap gap-2">
              {skills && skills.length > 0 ? (
                skills.map((skill: string, index: number) => (
                  <span
                    key={index}
                    className="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded"
                  >
                    {skill}
                  </span>
                ))
              ) : (
                <p className="text-gray-500">{t('profile.noSkills') || 'No skills listed'}</p>
              )}
            </div>
          </div>
        </div>
        {/* Experiencia profesional */}
        <div className="py-4">
          <h3 className="text-lg font-semibold mb-4">{t('profile.experience') || 'Experience'}</h3>
          {experiences && experiences.length > 0 ? (
            <div className="space-y-3">
              {experiences.map((exp: any, idx: number) => (
                <div key={idx} className="border-b pb-2 last:border-b-0 last:pb-0">
                  <div className="font-medium text-[#2F2F2F]">
                    {exp.position} @ {exp.company}
                  </div>
                  <div className="text-xs text-gray-500">
                    {exp.start_date} – {exp.end_date || (exp.current ? 'Present' : '')}
                    {exp.location ? ` • ${exp.location}` : ''}
                  </div>
                  {exp.description && (
                    <p className="text-sm text-gray-700 mt-1">{exp.description}</p>
                  )}
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-600">{t('profile.noExperience') || 'No experience listed'}</p>
          )}
        </div>
        <div className="py-4">
          <h3 className="text-lg font-semibold mb-4">
            {t('jobs.resume') || 'Resume'}
          </h3>
          <Button variant="outline" size="sm" className="w-full md:w-auto justify-start">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              className="h-4 w-4 mr-2"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
              />
            </svg>
            {t('profile.downloadResume') || 'Download Resume'}
          </Button>
        </div>
        <DialogFooter>
          <div className="flex gap-2 justify-end">
            <Button variant="outline" onClick={onClose}>
              {t('common.close') || 'Close'}
            </Button>
            <Button className="bg-[#FF4785] hover:bg-[#FF3575]">
              {t('hrDashboard.contactCandidate') || 'Contact Candidate'}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default function HRDashboardPage() {
  // Navigation
  const navigate = useNavigate();
  // const { t } = useLanguage(); // Solo usar t si se usa en el render

  // State for current tab (for mobile navigation)
  const [currentTab, setCurrentTab] = useState<string>("applications");

  // State for candidate/recruiter selection
  const [selectedCandidateId, setSelectedCandidateId] = useState('');
  const [selectedRecruiterId, setSelectedRecruiterId] = useState('');
  const [assignMsg, setAssignMsg] = useState('');

  // Sorting state
  const [sortByScore, setSortByScore] = useState(false);
  const [statusMsg, setStatusMsg] = useState('');
  const [selectedCandidate, setSelectedCandidate] = useState<Application | null>(null);
  const [applications, setApplications] = useState<Application[]>([]);

  // State for candidate profile modal
  const [selectedProfileCandidate, setSelectedProfileCandidate] = useState<Candidate | null>(null);

  // State for real data
  const [candidates, setCandidates] = useState<any[]>([]);
  const [jobs, setJobs] = useState<any[]>([]);
  const [recruiters, setRecruiters] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Estado para mostrar/ocultar el panel de administración del chatbot (EXCLUSIVO DE HR)
  const [showChatbotAdmin, setShowChatbotAdmin] = useState(false);

  // Estados de filtros
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [departmentFilter, setDepartmentFilter] = useState('');
  // Estado para la asignación de departamento del candidato seleccionado
  const [departmentAssignment, setDepartmentAssignment] = useState<{ departmentId: string; departmentName: string } | null>(null);
  // Cargar asignación de departamento cuando cambia el candidato seleccionado
  useEffect(() => {
    if (selectedCandidateId && candidates.length > 0) {
      // Buscar directamente en los candidatos cargados
      const selectedCandidate = candidates.find(c => c.id === selectedCandidateId);

      if (selectedCandidate && selectedCandidate.department_id && selectedCandidate.department_name) {
        setDepartmentAssignment({
          departmentId: selectedCandidate.department_id.toString(),
          departmentName: selectedCandidate.department_name
        });
      } else {
        setDepartmentAssignment(null);
      }
    } else {
      setDepartmentAssignment(null);
    }
  }, [selectedCandidateId, candidates]);
  const [dashboardStats, setDashboardStats] = useState<any>(null);

  // Cargar datos reales de las APIs
  useEffect(() => {
    const loadData = async () => {
      try {
        setLoading(true);

        // Cargar datos en paralelo
        const [candidatesResponse, jobsResponse, applicationsResponse, statsResponse, recruitersResponse] = await Promise.all([
          getCandidates(),
          getJobs(),
          getApplications(),
          getHRDashboardStats(),
          getRecruiters()
        ]);

        if (candidatesResponse.success && Array.isArray(candidatesResponse.data)) {
          setCandidates(candidatesResponse.data);
        } else {
          console.error('Error: candidatesResponse.data no es un array:', candidatesResponse);
          setCandidates([]);
        }

        if (jobsResponse.success) {
          setJobs(jobsResponse.data || []);
        }

        if (applicationsResponse.success) {
          // Las aplicaciones ya vienen con la estructura correcta desde la API
          const apps: Application[] = (applicationsResponse.data || []).map((app: any) => ({
            id: app.id,
            candidate: {
              id: app.candidate?.id || app.candidate_id,
              name: app.candidate?.name || `Candidato ${app.candidate_id}`,
              email: app.candidate?.email || app.candidate_email || '',
              location: app.candidate?.location || '',
              skills: [],
              department: app.candidate?.department_name || ''
            },
            job: {
              id: app.job?.id || app.job_id,
              title: app.job?.title || 'Posición no especificada',
              location: app.job?.location || '',
              skills: [],
              department: app.job?.department_name || ''
            },
            appliedDate: app.created_at || app.application_date || new Date().toISOString(),
            status: app.status || 'applied',
            score: app.match_score || app.score || 0,
            insights: app.insights
          }));
          setApplications(apps);
        }

        if (statsResponse.success) {
          setDashboardStats(statsResponse.data);
        }

        if (recruitersResponse && recruitersResponse.success && Array.isArray(recruitersResponse.data)) {
          setRecruiters(recruitersResponse.data);
        }

      } catch (error) {
        console.error('Error al cargar datos del dashboard:', error);
        toast({
          title: "Error",
          description: "No se pudieron cargar los datos del dashboard",
          variant: "destructive"
        });
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  // Filtros y ordenamiento de aplicaciones
  const sortedApplications = useMemo(() => {
    let filtered = applications;

    // Filtrar por búsqueda
    if (searchTerm) {
      filtered = filtered.filter(app =>
        app.candidate.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        app.candidate.email.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }

    // Filtrar por estado
    if (statusFilter) {
      filtered = filtered.filter(app => app.status === statusFilter);
    }

    // Filtrar por departamento (si está disponible)
    if (departmentFilter) {
      filtered = filtered.filter(app =>
        app.job.department === departmentFilter ||
        app.candidate.department === departmentFilter
      );
    }

    // Ordenar por score si está activado
    if (sortByScore) {
      return [...filtered].sort((a, b) => b.score - a.score);
    }

    return filtered;
  }, [applications, sortByScore, searchTerm, statusFilter, departmentFilter]);

  // Status change handler
  const handleStatusChange = async (id: string, status: string) => {
    // Find the application that's being updated
    const app = applications.find(app => app.id === id);
    if (!app) return;

    // Get the previous status before updating
    const previousStatus = app.status;

    // Update the application status in state
    setApplications(prevApps =>
      prevApps.map(app =>
        app.id === id ? { ...app, status } : app
      )
    );

    // Notify the candidate about status change via email
    try {
      setStatusMsg('Actualizando estado y enviando notificación...');

      // Get the recruiter details (using the HR admin as sender)
      const hrAdmin = {
        name: 'Admin HR',
        email: 'hr@bubblegum-agency.com'
      };

      // Send email notification to the candidate
      const emailResult = await sendCandidateStatusUpdateNotification(
        app.candidate.email || 'email@ejemplo.com',
        app.candidate.name || 'Candidato',
        previousStatus,
        status,
        app.job.title || 'Posición',
        hrAdmin.name,
        hrAdmin.email,
        `Su proceso de reclutamiento ha sido actualizado por nuestro equipo de Recursos Humanos.`
      );

      if (emailResult.success) {
        toast({
          title: "Estado actualizado",
          description: `Se ha notificado a ${app.candidate.name} sobre el cambio de estado a: ${status}`,
          duration: 5000
        });
        setStatusMsg(`Estado actualizado a: ${status}. Notificación enviada.`);
      } else {
        toast({
          title: "Estado actualizado",
          description: `Estado actualizado, pero hubo un problema al enviar la notificación: ${emailResult.message}`,
          variant: "destructive",
          duration: 5000
        });
        setStatusMsg(`Estado actualizado a: ${status}. Error al enviar notificación.`);
      }
    } catch (error) {
      console.error('Error al enviar la notificación:', error);
      toast({
        title: "Error al enviar notificación",
        description: "Ocurrió un error al intentar notificar al candidato.",
        variant: "destructive",
        duration: 5000
      });
      setStatusMsg(`Estado actualizado a: ${status}. Error al enviar notificación.`);
    }

    // Limpiar mensaje después de 5 segundos
    setTimeout(() => setStatusMsg(''), 5000);
  };

  // Assign handler
  const handleAssign = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!selectedCandidateId || !selectedRecruiterId) {
      setAssignMsg('Por favor selecciona un candidato y un reclutador');
      setTimeout(() => setAssignMsg(''), 3000);
      return;
    }

    // Encontrar el candidato y reclutador seleccionados
    const candidate = candidates.find(c => c.id === selectedCandidateId);
    const recruiter = recruiters.find((r: any) => r.id === selectedRecruiterId);

    if (candidate && recruiter) {
      try {
        setAssignMsg('Procesando asignación...');

        // Obtener información del departamento
        const departmentAssignmentResult = await getCandidateDepartmentAssignment(selectedCandidateId, candidates);

        // Crear un objeto con la información completa para el RecruiterDashboard
        const assignedCandidate = {
          ...candidate,
          reclutador: recruiter.name,
          emailReclutador: recruiter.email,
          departmentId: departmentAssignmentResult?.departmentId || '',
          departmentName: departmentAssignmentResult?.departmentName || ''
        };

        // Guardar asignación en backend vía API (producción)
        try {
          await fetch('/api/assign-candidate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(assignedCandidate)
          });
        } catch (err) {
          console.error('Error al guardar la asignación en backend:', err);
        }

        setAssignMsg('Enviando notificaciones...');

        // 1. Enviar notificación por email al reclutador
        const recruiterEmailResult = await sendRecruiterAssignmentNotification(
          recruiter.email,
          recruiter.name,
          candidate.name || 'Candidato',
          candidate.email || 'email@ejemplo.com',
          departmentAssignment?.departmentName || 'General'
        );

        // 2. Enviar notificación al candidato informando que ha pasado a la primera fase
        const candidateEmailResult = await sendCandidateStatusUpdateNotification(
          candidate.email || 'email@ejemplo.com',
          candidate.name || 'Candidato',
          'applied', // estado anterior
          'interview', // nuevo estado - primera fase
          'Proceso de Selección', // título del trabajo
          recruiter.name,
          recruiter.email,
          'Tu candidatura ha sido asignada a un reclutador especializado y has pasado a la primera fase del proceso de selección.'
        );

        // Mostrar resultados de las notificaciones
        if (recruiterEmailResult.success && candidateEmailResult.success) {
          toast({
            title: "¡Asignación completada con éxito!",
            description: `Candidato ${candidate.name} asignado a ${recruiter.name}. Notificaciones enviadas a ambas partes.`,
            duration: 5000
          });
          setAssignMsg(`✅ Asignación exitosa. Notificaciones enviadas al reclutador y candidato.`);

          // Cerrar el modal de asignación después de 3 segundos para que el admin permanezca en HR Dashboard
          setTimeout(() => {
            setSelectedCandidate(null);
            setSelectedRecruiterId('');
            setAssignMsg('');
          }, 3000);

        } else {
          // Manejar errores parciales
          const errors = [];
          if (!recruiterEmailResult.success) errors.push(`Error al notificar al reclutador: ${recruiterEmailResult.message}`);
          if (!candidateEmailResult.success) errors.push(`Error al notificar al candidato: ${candidateEmailResult.message}`);

          toast({
            title: "Asignación completada con errores",
            description: errors.join(' | '),
            variant: "destructive",
            duration: 7000
          });
          setAssignMsg(`⚠️ Candidato asignado, pero hubo errores en las notificaciones.`);

          // Cerrar el modal después de 4 segundos incluso con errores parciales
          setTimeout(() => {
            setSelectedCandidate(null);
            setSelectedRecruiterId('');
            setAssignMsg('');
          }, 4000);
        }

      } catch (error) {
        console.error('Error durante la asignación:', error);
        toast({
          title: "Error en la asignación",
          description: "Ocurrió un error durante el proceso de asignación.",
          variant: "destructive",
          duration: 5000
        });
        setAssignMsg('❌ Error durante la asignación. Por favor intenta de nuevo.');
      }

      // Limpiar formulario y mensaje después de 5 segundos
      setTimeout(() => {
        setAssignMsg('');
        setSelectedCandidateId('');
        setSelectedRecruiterId('');
      }, 5000);

    } else {
      setAssignMsg('Error al encontrar candidato o reclutador. Por favor intenta de nuevo.');
      setTimeout(() => setAssignMsg(''), 3000);
    }
  };
  // ...declare all needed hooks and state here...
  // For now, just wrap the existing return
  // Handle view details
  const handleViewDetails = (application: Application) => {
    setSelectedCandidate(application);
  };

  return (
    <>
      {loading ? (
        <div className="container mx-auto px-4 py-12 text-center">
          <p>Cargando datos del dashboard...</p>
        </div>
      ) : (
        <>
          {/* Candidate Profile Modal */}
          <CandidateProfileModal
            candidate={selectedProfileCandidate}
            open={!!selectedProfileCandidate}
            onClose={() => setSelectedProfileCandidate(null)}
          />

          {/* Dashboard Header */}
          <DashboardHeader
            title="HR Dashboard"
            subtitle="Gestión completa de candidatos y trabajos"
            activeTab={currentTab}
            tabs={[
              { key: 'applications', label: 'Aplicaciones', icon: '📋' },
              { key: 'candidates', label: 'Candidate Database', shortLabel: 'Database', icon: '👥' },
              { key: 'assignments', label: 'Asignaciones', icon: '⚡' }
            ]}
            onTabChange={setCurrentTab}
            breadcrumbs={[
              { label: 'Dashboard', href: '/dashboard' },
              { label: 'HR Dashboard' }
            ]}
            actions={
              <div className="flex gap-2">
                <Button
                  className="bg-[#FF4785] hover:bg-[#FF3575]"
                  onClick={() => navigate('/dashboard/estadisticas')}
                >
                  📊 Estadísticas
                </Button>
                <Button
                  className="bg-gradient-to-r from-[#FF4785] to-[#FF3575] hover:from-[#FF3575] hover:to-[#FF4785] text-white flex items-center gap-2 shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-white/20 hover:border-white/40 font-semibold"
                  onClick={() => setShowChatbotAdmin(v => !v)}
                  title="Panel exclusivo para administrar el chatbot - Solo disponible para HR"
                >
                  <Bot size={20} className="animate-pulse" />
                  {showChatbotAdmin ? '🔧 Cerrar Admin Chatbot' : '🤖 Administrar Chatbot'}
                </Button>
              </div>
            }
          />

          {/* Panel de administración del chatbot (modal flotante mejorado) */}
          {showChatbotAdmin && (
            <div style={{
              position: 'fixed',
              top: 0,
              left: 0,
              width: '100vw',
              height: '100vh',
              background: 'rgba(0,0,0,0.4)',
              zIndex: 1000,
              overflow: 'auto',
              backdropFilter: 'blur(4px)'
            }}>
              <div style={{
                maxWidth: 1200,
                margin: '20px auto',
                background: 'linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%)',
                borderRadius: 16,
                boxShadow: '0 25px 50px rgba(0,0,0,0.25)',
                padding: 24,
                border: '2px solid #FF4785'
              }}>
                <div className="flex justify-between items-center mb-4">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-gradient-to-r from-[#FF4785] to-[#FF3575] rounded-full flex items-center justify-center">
                      <Bot size={24} className="text-white" />
                    </div>
                    <div>
                      <h2 className="text-2xl font-bold text-gray-900">🤖 Panel de Administración del Chatbot</h2>
                      <p className="text-gray-600">Gestiona el flujo conversacional y la experiencia del usuario</p>
                    </div>
                  </div>
                  <Button
                    onClick={() => setShowChatbotAdmin(false)}
                    className="bg-gradient-to-r from-[#FF4785] to-[#FF3575] hover:from-[#FF3575] hover:to-[#FF4785] text-white shadow-lg hover:shadow-xl transition-all"
                  >
                    ✕ Cerrar
                  </Button>
                </div>
                <ChatbotAdmin />
              </div>
            </div>
          )}

          {/* Dashboard Content */}
          <section className="py-8">
            <div className="container mx-auto px-4">{/* MVP: Asignar candidato a reclutador */}
              <div className="mb-8">
                <div className="bg-white rounded-lg shadow p-6 mb-2">
                  <h2 className="text-lg font-bold mb-4 text-[#2F2F2F]">Asignar candidato a reclutador</h2>
                  <form className="flex flex-col gap-4" onSubmit={handleAssign}>
                    <div className="flex flex-wrap gap-4 items-center">
                      <label className="font-semibold">Candidato:
                        <select
                          className="ml-2 p-2 border rounded"
                          value={selectedCandidateId}
                          onChange={e => setSelectedCandidateId(e.target.value)}
                          required
                        >
                          <option value="">Selecciona</option>
                          {Array.isArray(candidates) ? candidates.map(c => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                          )) : null}
                        </select>
                      </label>
                      <label className="font-semibold">Reclutador:
                        <select
                          className="ml-2 p-2 border rounded"
                          value={selectedRecruiterId}
                          onChange={e => setSelectedRecruiterId(e.target.value)}
                          required
                        >
                          <option value="">Selecciona</option>
                          {recruiters.map((r: any) => (
                            <option key={r.id} value={r.id}>{r.name}</option>
                          ))}
                        </select>
                      </label>
                      <Button
                        type="submit"
                        className="bg-gradient-to-r from-[#FF4785] to-[#FF3575] hover:from-[#FF3575] hover:to-[#FF4785] text-white font-bold px-6 py-2 rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 flex items-center gap-2"
                        disabled={!selectedCandidateId || !selectedRecruiterId}
                      >
                        {assignMsg.includes('Procesando') || assignMsg.includes('Enviando') ? (
                          <>
                            <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                            Procesando...
                          </>
                        ) : (
                          <>
                            🚀 Asignar Candidato
                          </>
                        )}
                      </Button>
                    </div>

                    {/* Información de departamento y reclutador */}
                    {selectedCandidateId && (
                      <div className="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border-2 border-blue-200">
                        {!departmentAssignment ? (
                          <div className="text-center py-4">
                            <p className="text-gray-600">⚠️ No hay información de departamento para este candidato.</p>
                          </div>
                        ) : (
                          (() => {
                            // Buscar reclutadores para este departamento usando comparación de números
                            const departmentRecruiters = recruiters.filter((r: any) =>
                              Number(r.departmentId) === Number(departmentAssignment.departmentId)
                            );
                            const primaryRecruiter = departmentRecruiters.length > 0 ? departmentRecruiters[0] : null;
                            const selectedRecruiterInfo = selectedRecruiterId ? recruiters.find((r: any) => r.id === selectedRecruiterId) : null;

                            return (
                              <div className="space-y-4">
                                <h3 className="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                  📋 Información de Asignación
                                </h3>

                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                  {/* Departamento */}
                                  <div className="bg-white p-3 rounded-lg shadow-sm border">
                                    <p className="font-medium text-gray-700 flex items-center gap-2">
                                      🏢 Departamento:
                                    </p>
                                    <p className="text-lg font-semibold text-blue-900 mt-1">
                                      {departmentAssignment.departmentName}
                                    </p>
                                  </div>

                                  {/* Reclutador recomendado */}
                                  <div className="bg-white p-3 rounded-lg shadow-sm border">
                                    <p className="font-medium text-gray-700 flex items-center gap-2">
                                      👤 Reclutador recomendado:
                                    </p>
                                    <p className="text-lg font-semibold text-green-900 mt-1">
                                      {primaryRecruiter ? primaryRecruiter.name : 'No asignado'}
                                    </p>
                                  </div>

                                  {/* Email del reclutador recomendado */}
                                  <div className="bg-white p-3 rounded-lg shadow-sm border">
                                    <p className="font-medium text-gray-700 flex items-center gap-2">
                                      📧 Email recomendado:
                                    </p>
                                    <p className="text-sm text-gray-800 mt-1 break-all">
                                      {primaryRecruiter ? primaryRecruiter.email : 'N/A'}
                                    </p>
                                  </div>
                                </div>

                                {/* Información del reclutador seleccionado (si es diferente al recomendado) */}
                                {selectedRecruiterInfo && selectedRecruiterInfo.id !== primaryRecruiter?.id && (
                                  <div className="mt-4 p-3 bg-yellow-50 border-2 border-yellow-200 rounded-lg">
                                    <h4 className="font-semibold text-yellow-800 mb-2 flex items-center gap-2">
                                      ⚡ Reclutador Seleccionado:
                                    </h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                      <div>
                                        <p className="font-medium text-yellow-700">Nombre:</p>
                                        <p className="text-yellow-900 font-semibold">{selectedRecruiterInfo.name}</p>
                                      </div>
                                      <div>
                                        <p className="font-medium text-yellow-700">Email:</p>
                                        <p className="text-yellow-900 text-sm break-all">{selectedRecruiterInfo.email}</p>
                                      </div>
                                    </div>
                                  </div>
                                )}

                                {/* Mensaje informativo */}
                                <div className="mt-4 p-3 bg-indigo-50 border-l-4 border-indigo-400 rounded">
                                  <p className="text-indigo-800 text-sm">
                                    💡 <strong>Al asignar:</strong> Se enviará una notificación por email al reclutador y al candidato.
                                    El candidato será informado de que ha pasado a la primera fase del proceso.
                                  </p>
                                </div>
                              </div>
                            );
                          })()
                        )}
                      </div>
                    )}
                  </form>
                  {assignMsg && (
                    <div className={`mt-4 p-3 rounded-lg border ${assignMsg.includes('✅') ? 'bg-green-50 border-green-200 text-green-800' :
                      assignMsg.includes('❌') ? 'bg-red-50 border-red-200 text-red-800' :
                        assignMsg.includes('⚠️') ? 'bg-yellow-50 border-yellow-200 text-yellow-800' :
                          'bg-blue-50 border-blue-200 text-blue-800'
                      }`}>
                      <div className="flex items-center gap-2">
                        {(assignMsg.includes('Procesando') || assignMsg.includes('Enviando')) && (
                          <div className="animate-spin rounded-full h-4 w-4 border-2 border-current border-t-transparent"></div>
                        )}
                        <span className="font-medium">{assignMsg}</span>
                      </div>
                    </div>
                  )}
                </div>
              </div>
              <Tabs defaultValue="applications" value={currentTab} onValueChange={setCurrentTab} className="w-full">
                {/* Menú Hamburguesa para pantallas pequeñas */}
                <div className="block sm:hidden mb-6">
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" className="w-full flex items-center justify-between">
                        <span>
                          {currentTab === "applications" && "Candidates by Job"}
                          {currentTab === "jobs" && "Job Management"}
                          {currentTab === "candidates" && "Candidate Database"}
                          {currentTab === "statistics" && "Estadísticas"}
                        </span>
                        <Menu className="h-4 w-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-full">
                      <DropdownMenuItem
                        className={`cursor-pointer ${currentTab === "applications" ? "bg-[#FF4785] text-white" : ""}`}
                        onClick={() => setCurrentTab("applications")}
                      >
                        Candidates by Job
                      </DropdownMenuItem>
                      <DropdownMenuItem
                        className={`cursor-pointer ${currentTab === "jobs" ? "bg-[#FF4785] text-white" : ""}`}
                        onClick={() => setCurrentTab("jobs")}
                      >
                        Job Management
                      </DropdownMenuItem>
                      <DropdownMenuItem
                        className={`cursor-pointer ${currentTab === "candidates" ? "bg-[#FF4785] text-white" : ""}`}
                        onClick={() => setCurrentTab("candidates")}
                      >
                        Candidate Database
                      </DropdownMenuItem>
                      <DropdownMenuItem
                        className={`cursor-pointer ${currentTab === "statistics" ? "bg-[#FF4785] text-white" : ""}`}
                        onClick={() => setCurrentTab("statistics")}
                      >
                        Estadísticas
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>

                {/* Botones tradicionales para pantallas medianas y grandes */}
                <TabsList className="mb-6 hidden sm:flex">
                  <TabsTrigger
                    value="applications"
                    onClick={() => setCurrentTab("applications")}
                    className="bg-[#FF4785] text-white font-semibold rounded-md px-4 py-2 mr-2"
                  >
                    Candidates by Job
                  </TabsTrigger>
                  <TabsTrigger
                    value="jobs"
                    onClick={() => setCurrentTab("jobs")}
                    className="bg-[#FF4785] text-white font-semibold rounded-md px-4 py-2 mr-2"
                  >
                    Job Management
                  </TabsTrigger>
                  <TabsTrigger
                    value="candidates"
                    onClick={() => setCurrentTab("candidates")}
                    className="bg-[#FF4785] text-white font-semibold rounded-md px-4 py-2 mr-2"
                  >
                    Candidate Database
                  </TabsTrigger>
                  <TabsTrigger
                    value="statistics"
                    onClick={() => setCurrentTab("statistics")}
                    className="bg-[#FF4785] text-white font-semibold rounded-md px-4 py-2"
                  >
                    Estadísticas
                  </TabsTrigger>
                </TabsList>

                {/* Applications Tab */}
                <TabsContent value="applications" className="space-y-6">
                  <Card className="bg-[#ffffff]">
                    <CardHeader className="pb-3 flex flex-col md:flex-row md:items-center md:justify-between">
                      <CardTitle>Candidate List ({sortedApplications.length})</CardTitle>
                      <Button
                        className="bg-[#FF4785] hover:bg-[#FF3575] mt-2 md:mt-0"
                        onClick={() => setSortByScore(s => !s)}
                      >
                        {sortByScore ? 'Unsort by Score' : 'Sort by Score'}
                      </Button>
                      {statusMsg && (
                        <div className="mt-2 text-green-600 font-semibold text-sm">{statusMsg}</div>
                      )}
                    </CardHeader>
                    <CardContent>
                      {/* Filtros */}
                      <Filters
                        searchTerm={searchTerm}
                        onSearchChange={setSearchTerm}
                        searchPlaceholder="🔍 Buscar candidatos por nombre o email..."
                        statusFilter={statusFilter}
                        onStatusChange={setStatusFilter}
                        departmentFilter={departmentFilter}
                        onDepartmentChange={setDepartmentFilter}
                        onClearFilters={() => {
                          setSearchTerm('');
                          setStatusFilter('');
                          setDepartmentFilter('');
                        }}
                      />

                      {/* Lista de aplicaciones */}
                      <ApplicationsTable
                        applications={sortedApplications}
                        onStatusChange={handleStatusChange}
                        onViewDetails={handleViewDetails}
                      />
                      <ApplicationDetailsDialog
                        app={selectedCandidate}
                        open={!!selectedCandidate}
                        onClose={() => setSelectedCandidate(null)}
                        onStatusChange={handleStatusChange}
                      />
                      <div className="mt-8">
                        <StatusChangeForm />
                      </div>
                    </CardContent>
                  </Card>
                </TabsContent>

                {/* Jobs Tab */}
                <TabsContent value="jobs" className="space-y-6">
                  <div className="flex justify-between">
                    <h2 className="text-2xl font-semibold">Active Jobs</h2>
                    <Button className="bg-[#FF4785] hover:bg-[#FF3575]">
                      <Plus size={16} className="mr-2" />
                      New Job
                    </Button>
                  </div>

                  <Card className="bg-[#ffffff]">
                    <CardHeader className="pb-3">
                      <CardTitle>Job Listings ({jobs.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {jobs.map((job: any) => (
                          <div key={job.id} className="border rounded-lg p-4 shadow-sm">
                            <h3 className="font-semibold text-lg">{job.title}</h3>
                            <p className="text-gray-600 text-sm mb-2">{job.location}</p>
                            <div className="flex flex-wrap gap-1 mb-3">
                              {job.skills && job.skills.map((skill: string) => (
                                <span key={skill} className="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">
                                  {skill}
                                </span>
                              ))}
                            </div>
                            <Button variant="outline" size="sm" className="w-full mt-2">
                              View Details
                            </Button>
                          </div>
                        ))}
                      </div>
                    </CardContent>
                  </Card>
                </TabsContent>

                {/* Candidate Database Tab */}
                <TabsContent value="candidates" className="space-y-6">
                  <Card className="bg-[#ffffff]">
                    <CardHeader className="pb-3">
                      <CardTitle>Candidate Database ({Array.isArray(candidates) ? candidates.length : 0})</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {Array.isArray(candidates) ? candidates.map((candidate: any) => (
                          <div key={candidate.id} className="border rounded-lg p-4 shadow-sm">
                            <h3 className="font-semibold text-lg">{candidate.name}</h3>
                            <p className="text-gray-600 text-sm mb-2">{candidate.email}</p>
                            <div className="flex flex-wrap gap-1 mb-3">
                              {candidate.skills && candidate.skills.length > 0 ? (
                                candidate.skills.map((skill: string) => (
                                  <span key={skill} className="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">
                                    {skill}
                                  </span>
                                ))
                              ) : (
                                <span className="text-gray-500 text-xs">No skills listed</span>
                              )}
                            </div>
                            <Button
                              variant="outline"
                              size="sm"
                              className="w-full mt-2"
                              onClick={() => setSelectedProfileCandidate(candidate)}
                            >
                              View Profile
                            </Button>
                          </div>
                        )) : (
                          <div className="col-span-full text-center py-8 text-gray-500">
                            No hay candidatos disponibles
                          </div>
                        )}
                      </div>
                    </CardContent>
                  </Card>
                </TabsContent>

                {/* Statistics Tab */}
                <TabsContent value="statistics" className="space-y-6">
                  <Card className="bg-[#ffffff]">
                    <CardHeader>
                      <CardTitle>Estadísticas del Dashboard</CardTitle>
                    </CardHeader>
                    <CardContent>
                      {dashboardStats || candidates.length > 0 ? (
                        <div className="space-y-8">
                          {/* Resumen de métricas principales */}
                          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            {/* Total Candidates */}
                            <div className="bg-blue-50 p-6 rounded-lg border">
                              <h3 className="text-lg font-semibold text-blue-800 mb-2">Total Candidatos</h3>
                              <p className="text-3xl font-bold text-blue-600">
                                {dashboardStats?.total_candidates || candidates.length}
                              </p>
                            </div>

                            {/* Total Applications */}
                            <div className="bg-green-50 p-6 rounded-lg border">
                              <h3 className="text-lg font-semibold text-green-800 mb-2">Total Aplicaciones</h3>
                              <p className="text-3xl font-bold text-green-600">
                                {dashboardStats?.total_applications || applications.length}
                              </p>
                            </div>

                            {/* Total Jobs */}
                            <div className="bg-purple-50 p-6 rounded-lg border">
                              <h3 className="text-lg font-semibold text-purple-800 mb-2">Puestos Activos</h3>
                              <p className="text-3xl font-bold text-purple-600">
                                {dashboardStats?.total_jobs || jobs.length}
                              </p>
                            </div>

                            {/* Total Recruiters */}
                            <div className="bg-orange-50 p-6 rounded-lg border">
                              <h3 className="text-lg font-semibold text-orange-800 mb-2">Reclutadores</h3>
                              <p className="text-3xl font-bold text-orange-600">
                                {dashboardStats?.total_recruiters || recruiters.length}
                              </p>
                            </div>
                          </div>

                          {/* Gráficos */}
                          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            {/* Gráfico de Estado de Aplicaciones */}
                            <Card>
                              <CardHeader>
                                <CardTitle>Estado de Aplicaciones</CardTitle>
                              </CardHeader>
                              <CardContent>
                                <ResponsiveContainer width="100%" height={300}>
                                  <PieChart>
                                    <Pie
                                      data={[
                                        {
                                          name: 'Aplicadas',
                                          value: applications.filter(app => app.status === 'applied').length,
                                          color: '#3B82F6'
                                        },
                                        {
                                          name: 'En Entrevista',
                                          value: applications.filter(app => app.status === 'interview').length,
                                          color: '#F59E0B'
                                        },
                                        {
                                          name: 'Contratados',
                                          value: applications.filter(app => app.status === 'hired').length,
                                          color: '#10B981'
                                        },
                                        {
                                          name: 'Rechazados',
                                          value: applications.filter(app => app.status === 'rejected').length,
                                          color: '#EF4444'
                                        }
                                      ]}
                                      cx="50%"
                                      cy="50%"
                                      labelLine={false}
                                      label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                                      outerRadius={80}
                                      fill="#8884d8"
                                      dataKey="value"
                                    >
                                      {[
                                        { name: 'Aplicadas', value: applications.filter(app => app.status === 'applied').length, color: '#3B82F6' },
                                        { name: 'En Entrevista', value: applications.filter(app => app.status === 'interview').length, color: '#F59E0B' },
                                        { name: 'Contratados', value: applications.filter(app => app.status === 'hired').length, color: '#10B981' },
                                        { name: 'Rechazados', value: applications.filter(app => app.status === 'rejected').length, color: '#EF4444' }
                                      ].map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={entry.color} />
                                      ))}
                                    </Pie>
                                    <Tooltip />
                                    <Legend />
                                  </PieChart>
                                </ResponsiveContainer>
                              </CardContent>
                            </Card>

                            {/* Gráfico de Candidatos por Departamento */}
                            <Card>
                              <CardHeader>
                                <CardTitle>Candidatos por Departamento</CardTitle>
                              </CardHeader>
                              <CardContent>
                                <ResponsiveContainer width="100%" height={300}>
                                  <BarChart
                                    data={candidates.reduce((acc: any[], candidate: any) => {
                                      const dept = candidate.department_name || 'Sin departamento';
                                      const existing = acc.find(item => item.department === dept);
                                      if (existing) {
                                        existing.count++;
                                      } else {
                                        acc.push({ department: dept, count: 1 });
                                      }
                                      return acc;
                                    }, [])}
                                  >
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                      dataKey="department"
                                      angle={-45}
                                      textAnchor="end"
                                      height={80}
                                    />
                                    <YAxis />
                                    <Tooltip />
                                    <Legend />
                                    <Bar dataKey="count" fill="#8B5CF6" />
                                  </BarChart>
                                </ResponsiveContainer>
                              </CardContent>
                            </Card>

                            {/* Gráfico de Distribución de Skills */}
                            <Card>
                              <CardHeader>
                                <CardTitle>Top Skills de Candidatos</CardTitle>
                              </CardHeader>
                              <CardContent>
                                <ResponsiveContainer width="100%" height={300}>
                                  <BarChart
                                    data={(() => {
                                      const skillsCount: Record<string, number> = {};
                                      candidates.forEach((candidate: any) => {
                                        if (candidate.skills && Array.isArray(candidate.skills)) {
                                          candidate.skills.forEach((skill: string) => {
                                            skillsCount[skill] = (skillsCount[skill] || 0) + 1;
                                          });
                                        }
                                      });
                                      return Object.entries(skillsCount)
                                        .sort(([, a], [, b]) => b - a)
                                        .slice(0, 10)
                                        .map(([skill, count]) => ({ skill, count }));
                                    })()}
                                  >
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                      dataKey="skill"
                                      angle={-45}
                                      textAnchor="end"
                                      height={80}
                                    />
                                    <YAxis />
                                    <Tooltip />
                                    <Legend />
                                    <Bar dataKey="count" fill="#06B6D4" />
                                  </BarChart>
                                </ResponsiveContainer>
                              </CardContent>
                            </Card>

                            {/* Gráfico de Puntuaciones de Match */}
                            <Card>
                              <CardHeader>
                                <CardTitle>Distribución de Puntuaciones de Match</CardTitle>
                              </CardHeader>
                              <CardContent>
                                <ResponsiveContainer width="100%" height={300}>
                                  <BarChart
                                    data={(() => {
                                      const scoreBuckets = [
                                        { range: '0-20', min: 0, max: 20, count: 0 },
                                        { range: '21-40', min: 21, max: 40, count: 0 },
                                        { range: '41-60', min: 41, max: 60, count: 0 },
                                        { range: '61-80', min: 61, max: 80, count: 0 },
                                        { range: '81-100', min: 81, max: 100, count: 0 }
                                      ];

                                      applications.forEach(app => {
                                        const score = app.score || 0;
                                        scoreBuckets.forEach(bucket => {
                                          if (score >= bucket.min && score <= bucket.max) {
                                            bucket.count++;
                                          }
                                        });
                                      });

                                      return scoreBuckets;
                                    })()}
                                  >
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="range" />
                                    <YAxis />
                                    <Tooltip />
                                    <Legend />
                                    <Bar dataKey="count" fill="#F59E0B" />
                                  </BarChart>
                                </ResponsiveContainer>
                              </CardContent>
                            </Card>
                          </div>
                        </div>
                      ) : (
                        <div className="text-center py-8 text-gray-500">
                          <p>Cargando estadísticas...</p>
                        </div>
                      )}
                    </CardContent>
                  </Card>
                </TabsContent>
              </Tabs>
            </div>
          </section>

          {/* Email History Section */}
          <section className="py-8">
            <div className="container mx-auto px-4">
              <EmailHistoryViewer />
            </div>
          </section>
        </>
      )}
    </>
  );
}