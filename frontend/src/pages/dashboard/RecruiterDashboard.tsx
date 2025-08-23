// src/pages/dashboard/RecruiterDashboard.tsx
import * as React from 'react';
import { useState, useEffect, useReducer, useMemo } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import { useLanguage } from '../../lib/i18n/LanguageContext';
import { Button } from '../../components/ui/button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '../../components/ui/tabs';
import { Card, CardHeader, CardTitle, CardContent } from '../../components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '../../components/ui/dropdown-menu';
import { StatusBadge } from '../../components/ui/status-badge';
import { Filters } from '../../components/ui/filters';
import { DashboardHeader } from '../../components/ui/dashboard-header';
import StatusChangeForm from '../../components/StatusChangeForm';
import { sendCandidateStatusUpdateNotification } from '../../lib/emailService';
import { getAssignedCandidates, getRecruiterDashboardStats } from '../../lib/apiService';
import { toast } from '../../components/ui/use-toast';

interface Candidate {
    id: string;
    name: string;
    email: string;
    phone?: string;
    location?: string;
    skills?: string[];
    estado?: string;
    jobPosition?: string;
    assignedCategory?: string;
    categoria?: string;
    reclutador?: string;
    emailReclutador?: string;
    department?: string;
}

interface Job {
    id: string;
    title: string;
    location?: string;
    skills?: string[];
}

interface Application {
    id: string;
    candidateId: string;
    jobId: string;
    candidate: Candidate;
    job: Job;
    appliedDate: string;
    status: string;
    score: number;
}

interface Interview {
    id: string;
    candidateId: string;
    recruiterId: string;
    candidate: Candidate;
    recruiterName: string;
    recruiterEmail: string;
    date: string;
    time: string;
    duration: number; // en minutos
    location: string;
    type: 'presencial' | 'virtual' | 'telefónica';
    status: 'scheduled' | 'completed' | 'cancelled' | 'rescheduled';
    notes?: string;
    meetingLink?: string;
}

interface RecruiterDashboardProps {
    recruiterId?: string;
}

// Reducer para manejo de entrevistas
interface InterviewState {
    interviews: Interview[];
    loading: boolean;
    error: string | null;
}

type InterviewAction =
    | { type: 'SET_LOADING'; payload: boolean }
    | { type: 'SET_ERROR'; payload: string | null }
    | { type: 'LOAD_INTERVIEWS'; payload: Interview[] }
    | { type: 'ADD_INTERVIEW'; payload: Interview }
    | { type: 'UPDATE_INTERVIEW'; payload: { id: string; updates: Partial<Interview> } }
    | { type: 'CANCEL_INTERVIEW'; payload: string }
    | { type: 'DELETE_INTERVIEW'; payload: string };

const interviewReducer = (state: InterviewState, action: InterviewAction): InterviewState => {
    switch (action.type) {
        case 'SET_LOADING':
            return { ...state, loading: action.payload };
        case 'SET_ERROR':
            return { ...state, error: action.payload };
        case 'LOAD_INTERVIEWS':
            return { ...state, interviews: action.payload, loading: false, error: null };
        case 'ADD_INTERVIEW':
            const newInterviews = [...state.interviews, action.payload];
            // Guardar en localStorage
            localStorage.setItem('scheduledInterviews', JSON.stringify(newInterviews));
            return { ...state, interviews: newInterviews };
        case 'UPDATE_INTERVIEW':
            const updatedInterviews = state.interviews.map(interview =>
                interview.id === action.payload.id
                    ? { ...interview, ...action.payload.updates }
                    : interview
            );
            localStorage.setItem('scheduledInterviews', JSON.stringify(updatedInterviews));
            return { ...state, interviews: updatedInterviews };
        case 'CANCEL_INTERVIEW':
            const cancelledInterviews = state.interviews.map(interview =>
                interview.id === action.payload
                    ? { ...interview, status: 'cancelled' as const }
                    : interview
            );
            localStorage.setItem('scheduledInterviews', JSON.stringify(cancelledInterviews));
            return { ...state, interviews: cancelledInterviews };
        case 'DELETE_INTERVIEW':
            const filteredInterviews = state.interviews.filter(interview => interview.id !== action.payload);
            localStorage.setItem('scheduledInterviews', JSON.stringify(filteredInterviews));
            return { ...state, interviews: filteredInterviews };
        default:
            return state;
    }
};

const RecruiterDashboard: React.FC<RecruiterDashboardProps> = ({ recruiterId: propRecruiterId }) => {
    const { user } = useAuth();
    const { t } = useLanguage();
    const recruiterId = propRecruiterId || user?.id || 'default-recruiter';

    // Componente de Filtros - usando componente reutilizable
    const CandidateFilters = React.memo(() => (
        <Filters
            searchTerm={searchTerm}
            onSearchChange={setSearchTerm}
            searchPlaceholder={t('recruiterDashboard.searchCandidates')}
            statusFilter={statusFilter}
            onStatusChange={setStatusFilter}
            departmentFilter={departmentFilter}
            onDepartmentChange={setDepartmentFilter}
            onClearFilters={clearFiltersCallback}
        />
    ));

    // Componente Quick Actions Menu - memoizado
    const QuickActionsMenu = React.memo(({ candidate }: { candidate: any }) => (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                    <span className="text-lg">⋮</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem
                    onClick={() => {
                        setInterviewForm(prev => ({ ...prev, candidateId: candidate.candidateId }));
                        setShowScheduleForm(true);
                        setActiveTab('interviews');
                    }}
                    className="cursor-pointer"
                >
                    📅 Programar Entrevista
                </DropdownMenuItem>
                <DropdownMenuItem
                    onClick={() => {
                        window.open(`mailto:${candidate.email || `candidate${candidate.candidateId}@example.com`}`, '_blank');
                    }}
                    className="cursor-pointer"
                >
                    ✉️ Enviar Email
                </DropdownMenuItem>
                <DropdownMenuItem
                    onClick={() => {
                        window.open(`tel:${candidate.phone || ''}`, '_blank');
                    }}
                    className="cursor-pointer"
                >
                    📞 Llamar
                </DropdownMenuItem>
                <DropdownMenuItem
                    onClick={() => {
                        // Aquí podrías abrir un modal con el CV o descargar el archivo
                        toast({
                            title: "CV del candidato",
                            description: `Abriendo CV de ${candidate.candidateName}`,
                            duration: 3000
                        });
                    }}
                    className="cursor-pointer"
                >
                    📄 Ver CV
                </DropdownMenuItem>
                <DropdownMenuItem
                    onClick={() => {
                        setSelectedCandidate({
                            id: candidate.candidateId,
                            name: candidate.candidateName,
                            email: candidate.email || `candidate${candidate.candidateId}@example.com`
                        });
                        // Scroll to status change form
                        document.getElementById('status-change-section')?.scrollIntoView({ behavior: 'smooth' });
                    }}
                    className="cursor-pointer"
                >
                    🔄 Cambiar Estado
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    ));

    // Componente CandidateCard memoizado
    const CandidateCard = React.memo(({ candidate }: { candidate: any }) => (
        <Card className="border hover:shadow-md transition-shadow">
            <CardContent className="p-4">
                <div className="flex justify-between items-start">
                    <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                            <h3 className="font-semibold text-lg text-[#2F2F2F]">{candidate.candidateName}</h3>
                            <StatusBadge status={candidate.status || 'Under Review'} />
                        </div>
                        <div className="space-y-1 text-sm">
                            <p className="text-gray-600 flex items-center gap-2">
                                <span className="font-medium">📍 Departamento:</span>
                                {candidate.candidateDepartment}
                            </p>
                            <p className="text-gray-500 flex items-center gap-2">
                                <span className="font-medium">📅 Asignado:</span>
                                {candidate.assignedDate}
                            </p>
                            {candidate.email && (
                                <p className="text-gray-500 flex items-center gap-2">
                                    <span className="font-medium">✉️ Email:</span>
                                    {candidate.email}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="flex gap-2 ml-4">
                        <Button
                            size="sm"
                            className="bg-[#FF4785] hover:bg-[#FF3575] text-white shadow-sm"
                            onClick={() => handleScheduleInterviewCallback(candidate.candidateId)}
                        >
                            📅 Programar Entrevista
                        </Button>
                        <QuickActionsMenu candidate={candidate} />
                    </div>
                </div>
            </CardContent>
        </Card>
    ));

    const [isLoading, setIsLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('candidates'); // Empezar en candidatos, no overview

    // Estados para filtros y búsqueda
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('');

    // Estados para gestión de candidatos y entrevistas
    const [assignedCandidates, setAssignedCandidates] = useState<any[]>([]);
    const [applications, setApplications] = useState<Application[]>([]);

    // Manejo de entrevistas con useReducer
    const [interviewState, dispatchInterview] = useReducer(interviewReducer, {
        interviews: [],
        loading: false,
        error: null
    });

    const [selectedCandidate, setSelectedCandidate] = useState<Candidate | null>(null);
    const [selectedApplication, setSelectedApplication] = useState<Application | null>(null);

    // Estados para formularios
    const [statusForm, setStatusForm] = useState({ estado: '', notas: '' });
    const [interviewForm, setInterviewForm] = useState({
        candidateId: '',
        date: '',
        time: '',
        duration: 60,
        location: '',
        type: 'virtual' as 'presencial' | 'virtual' | 'telefónica',
        notes: '',
        meetingLink: ''
    });
    const [showScheduleForm, setShowScheduleForm] = useState(false);
    const [statusMsg, setStatusMsg] = useState('');

    useEffect(() => {
        const loadDashboardData = async () => {
            try {
                setIsLoading(true);

                // Cargar candidatos asignados usando apiService
                const recruiterEmail = user?.email || localStorage.getItem('userEmail');
                if (recruiterEmail) {
                    try {
                        const result = await getAssignedCandidates(recruiterId);

                        if (result.success && result.data) {
                            setAssignedCandidates(result.data);
                        } else {
                            console.warn('No se encontraron candidatos asignados');
                            setAssignedCandidates([]);
                        }
                    } catch (error) {
                        console.error('Error cargando candidatos asignados:', error);
                        setAssignedCandidates([]);
                    }
                }

                // Cargar entrevistas programadas desde localStorage
                const savedInterviews = localStorage.getItem('scheduledInterviews');
                if (savedInterviews) {
                    dispatchInterview({ type: 'LOAD_INTERVIEWS', payload: JSON.parse(savedInterviews) });
                }

            } catch (error) {
                console.error('Error al cargar el dashboard de reclutador:', error);
            } finally {
                setIsLoading(false);
            }
        };

        if (recruiterId) {
            loadDashboardData();
        }
    }, [recruiterId, user]);

    // Funciones para gestión de candidatos
    const handleStatusChange = async (candidateId: string, newStatus: string, notes: string) => {
        try {
            setStatusMsg('Actualizando estado del candidato...');

            // Buscar el candidato
            const candidate = assignedCandidates.find(c => c.candidateId === candidateId);
            if (!candidate) {
                setStatusMsg('Candidato no encontrado');
                return;
            }

            // Obtener datos del reclutador actual
            const recruiterName = user?.name || 'Reclutador';
            const recruiterEmail = user?.email || 'recruiter@bubblegum-agency.com';

            // Enviar notificación por email al candidato
            const emailResult = await sendCandidateStatusUpdateNotification(
                candidate.email || `candidate${candidateId}@example.com`, // Email del candidato
                candidate.candidateName,
                'Under Review', // Estado anterior
                newStatus,
                candidate.candidateDepartment || 'Posición no especificada',
                recruiterName,
                recruiterEmail,
                notes
            );

            if (emailResult.success) {
                toast({
                    title: "Estado actualizado",
                    description: `Se ha notificado a ${candidate.candidateName} sobre el cambio de estado.`,
                    duration: 5000
                });
                setStatusMsg('Estado actualizado y candidato notificado');
            } else {
                toast({
                    title: "Estado actualizado",
                    description: `Estado cambiado, pero hubo un problema con la notificación.`,
                    variant: "destructive",
                    duration: 5000
                });
                setStatusMsg('Estado actualizado, problema con notificación');
            }

            // Limpiar formulario
            setStatusForm({ estado: '', notas: '' });

        } catch (error) {
            console.error('Error al actualizar estado:', error);
            setStatusMsg('Error al actualizar estado del candidato');
        }

        setTimeout(() => setStatusMsg(''), 5000);
    };

    // Funciones para gestión de entrevistas
    const handleScheduleInterview = async (e: React.FormEvent) => {
        e.preventDefault();

        try {
            // Buscar datos del candidato
            const candidate = assignedCandidates.find(c => c.candidateId === interviewForm.candidateId);
            if (!candidate) return;

            // Obtener datos del reclutador actual
            const recruiterName = user?.name || 'Reclutador';
            const recruiterEmail = user?.email || 'recruiter@bubblegum-agency.com';

            // Crear nueva entrevista
            const newInterview: Interview = {
                id: `interview-${Date.now()}`,
                candidateId: interviewForm.candidateId,
                recruiterId: recruiterId,
                candidate: {
                    id: candidate.candidateId,
                    name: candidate.candidateName,
                    email: candidate.email || `candidate${candidate.candidateId}@example.com`,
                    department: candidate.candidateDepartment
                } as Candidate,
                recruiterName,
                recruiterEmail,
                date: interviewForm.date,
                time: interviewForm.time,
                duration: Number(interviewForm.duration),
                location: interviewForm.location,
                type: interviewForm.type,
                status: 'scheduled',
                notes: interviewForm.notes,
                meetingLink: interviewForm.meetingLink
            };

            // Actualizar estado local usando reducer
            dispatchInterview({ type: 'ADD_INTERVIEW', payload: newInterview });

            // Enviar notificación al candidato
            const emailResult = await sendCandidateStatusUpdateNotification(
                newInterview.candidate.email,
                newInterview.candidate.name,
                'Under Review',
                'Interview Scheduled',
                candidate.candidateDepartment || 'Posición no especificada',
                recruiterName,
                recruiterEmail,
                `Se ha programado una entrevista para el día ${interviewForm.date} a las ${interviewForm.time}. ${interviewForm.notes}`
            );

            if (emailResult.success) {
                toast({
                    title: "Entrevista programada",
                    description: `Se ha notificado a ${candidate.candidateName} sobre la entrevista.`,
                    duration: 5000
                });
            }

            // Reiniciar formulario
            setInterviewForm({
                candidateId: '',
                date: '',
                time: '',
                duration: 60,
                location: '',
                type: 'virtual',
                notes: '',
                meetingLink: ''
            });
            setShowScheduleForm(false);

        } catch (error) {
            console.error('Error al programar entrevista:', error);
            toast({
                title: "Error",
                description: "Hubo un problema al programar la entrevista.",
                variant: "destructive",
                duration: 5000
            });
        }
    };

    const handleCancelInterview = (interviewId: string) => {
        dispatchInterview({ type: 'CANCEL_INTERVIEW', payload: interviewId });

        toast({
            title: "Entrevista cancelada",
            description: "La entrevista ha sido cancelada.",
            duration: 3000
        });
    };

    // Función para filtrar candidatos con useMemo para optimización
    const filteredCandidates = useMemo(() => {
        return assignedCandidates.filter(candidate => {
            const matchesSearch = !searchTerm ||
                candidate.candidateName.toLowerCase().includes(searchTerm.toLowerCase());
            const matchesStatus = !statusFilter ||
                (candidate.status || 'Under Review') === statusFilter;
            const matchesDepartment = !departmentFilter ||
                candidate.candidateDepartment === departmentFilter;

            return matchesSearch && matchesStatus && matchesDepartment;
        });
    }, [assignedCandidates, searchTerm, statusFilter, departmentFilter]);    // Callbacks memoizados para optimización
    const handleScheduleInterviewCallback = React.useCallback((candidateId: string) => {
        setInterviewForm(prev => ({ ...prev, candidateId }));
        setShowScheduleForm(true);
        setActiveTab('interviews');
    }, []);

    const clearFiltersCallback = React.useCallback(() => {
        setSearchTerm('');
        setStatusFilter('');
        setDepartmentFilter('');
    }, []);

    if (isLoading) {
        return (
            <div className="min-h-screen bg-gray-50 p-6">
                <div className="container mx-auto">
                    <div className="text-center space-y-6 mt-20">
                        {/* Logo/Icon animado */}
                        <div className="flex justify-center">
                            <div className="relative">
                                <div className="animate-spin rounded-full h-16 w-16 border-4 border-[#FF4785] border-t-transparent"></div>
                                <div className="absolute inset-0 flex items-center justify-center">
                                    <span className="text-[#FF4785] font-bold text-sm">BT</span>
                                </div>
                            </div>
                        </div>

                        {/* Mensaje de carga */}
                        <div className="space-y-3">
                            <h2 className="text-xl font-semibold text-[#2F2F2F]">Preparando tu Dashboard</h2>
                            <p className="text-gray-600">{t('recruiterDashboard.loadingCandidates')}</p>
                        </div>

                        {/* Barra de progreso */}
                        <div className="max-w-md mx-auto">
                            <div className="w-full bg-gray-200 rounded-full h-2">
                                <div className="bg-gradient-to-r from-[#FF4785] to-[#FF3575] h-2 rounded-full animate-pulse" style={{ width: '70%' }}></div>
                            </div>
                            <p className="text-xs text-gray-500 mt-2">Esto puede tomar unos segundos...</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <section className="py-8">
            <div className="container mx-auto px-4">
                {/* Breadcrumbs Navigation */}
                <nav className="flex items-center space-x-2 text-sm text-gray-600 mb-4" aria-label="Breadcrumb">
                    <span className="text-[#FF4785] font-medium">Dashboard</span>
                    <span>/</span>
                    <span className="capitalize font-medium">
                        {activeTab === 'candidates' ? t('recruiterDashboard.assignedCandidates') : t('recruiterDashboard.interviewManagement')}
                    </span>
                </nav>

                {/* Header principal - estilo MVP con mejoras responsive */}
                <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center bg-[#FF4785] rounded-md p-4 lg:p-5 font-poppins mb-8">
                    <div className="mb-4 lg:mb-0">
                        <h1 className="text-xl md:text-2xl font-bold text-[#2F2F2F]">Recruiter Dashboard</h1>
                        <p className="text-white text-sm md:text-base">{t('recruiterDashboard.manageYourCandidates')}</p>
                    </div>

                    {/* Navegación responsive */}
                    <div className="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                        <Button
                            className={`flex-1 sm:flex-initial ${activeTab === 'candidates' ? 'bg-[#2F2F2F] text-white' : 'bg-white text-[#FF4785]'} transition-colors`}
                            onClick={() => setActiveTab('candidates')}
                        >
                            <span className="mr-2">👥</span>
                            <span className="hidden sm:inline">{t('recruiterDashboard.assignedCandidates')}</span>
                            <span className="sm:hidden">{t('recruiterDashboard.candidatesShort')}</span>
                        </Button>
                        <Button
                            className={`flex-1 sm:flex-initial ${activeTab === 'interviews' ? 'bg-[#2F2F2F] text-white' : 'bg-white text-[#FF4785]'} transition-colors`}
                            onClick={() => setActiveTab('interviews')}
                        >
                            <span className="mr-2">📅</span>
                            <span className="hidden sm:inline">{t('recruiterDashboard.interviews')}</span>
                            <span className="sm:hidden">{t('recruiterDashboard.interviews')}</span>
                        </Button>
                    </div>
                </div>

                {/* Contenido principal */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                    <TabsList className="hidden" />

                    {/* Tab: Candidatos Asignados */}
                    <TabsContent value="candidates" className="space-y-6">
                        {/* Filtros y Búsqueda */}
                        <CandidateFilters />

                        <Card>
                            <CardHeader>
                                <div className="flex justify-between items-center">
                                    <CardTitle className="text-[#FF4785]">
                                        {t('recruiterDashboard.assignedCandidates')}
                                        {filteredCandidates.length !== assignedCandidates.length && (
                                            <span className="text-sm text-gray-500 font-normal ml-2">
                                                ({filteredCandidates.length} de {assignedCandidates.length})
                                            </span>
                                        )}
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {assignedCandidates.length === 0 ? (
                                    <div className="text-center py-16">
                                        <div className="max-w-sm mx-auto">
                                            <div className="text-6xl mb-4">👥</div>
                                            <h3 className="text-xl font-semibold text-gray-700 mb-2">{t('recruiterDashboard.noCandidatesAssigned')}</h3>
                                            <p className="text-gray-500 mb-4">{t('recruiterDashboard.noCandidatesAssignedDesc')}</p>
                                            <div className="text-sm text-gray-400">
                                                💡 Tip: Puedes contactar a HR para recibir nuevas asignaciones
                                            </div>
                                        </div>
                                    </div>
                                ) : filteredCandidates.length === 0 ? (
                                    <div className="text-center py-16">
                                        <div className="max-w-sm mx-auto">
                                            <div className="text-6xl mb-4">🔍</div>
                                            <h3 className="text-xl font-semibold text-gray-700 mb-2">{t('recruiterDashboard.noCandidatesFound')}</h3>
                                            <p className="text-gray-500 mb-4">Intenta ajustar los filtros para encontrar lo que buscas.</p>
                                            <Button
                                                variant="outline"
                                                onClick={clearFiltersCallback}
                                            >
                                                ✕ Limpiar filtros
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {filteredCandidates.map((candidate) => (
                                            <CandidateCard key={candidate.candidateId} candidate={candidate} />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Formulario de cambio de estado */}
                        <Card id="status-change-section">
                            <CardHeader>
                                <CardTitle className="text-[#FF4785]">Cambiar Estado del Candidato</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={(e) => {
                                    e.preventDefault();
                                    if (selectedCandidate && statusForm.estado) {
                                        handleStatusChange(selectedCandidate.id, statusForm.estado, statusForm.notas);
                                    }
                                }} className="space-y-4">
                                    <div>
                                        <label className="block mb-2 font-semibold text-[#FF4785]">Candidato:</label>
                                        <select
                                            value={selectedCandidate?.id || ''}
                                            onChange={(e) => {
                                                const candidate = assignedCandidates.find(c => c.candidateId === e.target.value);
                                                setSelectedCandidate(candidate ? {
                                                    id: candidate.candidateId,
                                                    name: candidate.candidateName,
                                                    email: candidate.email || `candidate${candidate.candidateId}@example.com`
                                                } as Candidate : null);
                                            }}
                                            className="border rounded px-3 py-2 w-full"
                                            required
                                        >
                                            <option value="">Seleccionar candidato</option>
                                            {assignedCandidates.map(candidate => (
                                                <option key={candidate.candidateId} value={candidate.candidateId}>
                                                    {candidate.candidateName}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block mb-2 font-semibold text-[#FF4785]">Nuevo Estado:</label>
                                        <select
                                            value={statusForm.estado}
                                            onChange={(e) => setStatusForm(prev => ({ ...prev, estado: e.target.value }))}
                                            className="border rounded px-3 py-2 w-full"
                                            required
                                        >
                                            <option value="">Seleccionar estado</option>
                                            <option value="Under Review">En revisión</option>
                                            <option value="Interview">Entrevista</option>
                                            <option value="Technical Test">Prueba técnica</option>
                                            <option value="Second Interview">Segunda entrevista</option>
                                            <option value="Final Interview">Entrevista final</option>
                                            <option value="Reference Check">Verificación de referencias</option>
                                            <option value="Offer Extended">Oferta extendida</option>
                                            <option value="Offer Accepted">Oferta aceptada</option>
                                            <option value="Offer Declined">Oferta rechazada</option>
                                            <option value="Rejected">No seleccionado</option>
                                            <option value="On Hold">En espera</option>
                                            <option value="Withdrawn">Candidato retirado</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block mb-2 font-semibold text-[#FF4785]">Notas del reclutador:</label>
                                        <textarea
                                            value={statusForm.notas}
                                            onChange={(e) => setStatusForm(prev => ({ ...prev, notas: e.target.value }))}
                                            className="border rounded px-3 py-2 w-full h-32"
                                            placeholder="Añade aquí tus observaciones sobre el candidato..."
                                        />
                                    </div>

                                    <div className="flex gap-3">
                                        <Button
                                            type="submit"
                                            className="bg-[#FF4785] hover:bg-[#FF3575]"
                                        >
                                            Actualizar Estado
                                        </Button>
                                    </div>

                                    {statusMsg && (
                                        <div className="mt-4 p-2 bg-green-100 text-green-800 rounded-md text-center">
                                            {statusMsg}
                                        </div>
                                    )}
                                </form>

                                {/* StatusChangeForm del MVP */}
                                <div className="mt-8">
                                    <StatusChangeForm />
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab: Entrevistas */}
                    <TabsContent value="interviews" className="space-y-6">
                        <Card>
                            <CardHeader className="flex flex-row justify-between items-center">
                                <CardTitle className="text-[#FF4785]">Entrevistas Programadas</CardTitle>
                                <Button
                                    className="bg-[#FF4785] hover:bg-[#FF3575]"
                                    onClick={() => setShowScheduleForm(!showScheduleForm)}
                                >
                                    {showScheduleForm ? 'Cancelar' : 'Programar Nueva Entrevista'}
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {/* Formulario de programación de entrevistas */}
                                {showScheduleForm && (
                                    <div className="mb-8 p-4 border rounded-lg">
                                        <h3 className="text-lg font-semibold mb-4 text-[#FF4785]">Programar Nueva Entrevista</h3>
                                        <form onSubmit={handleScheduleInterview} className="space-y-4">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label className="block mb-1 text-sm font-medium text-[#FF4785]">Candidato:</label>
                                                    <select
                                                        value={interviewForm.candidateId}
                                                        onChange={(e) => setInterviewForm(prev => ({ ...prev, candidateId: e.target.value }))}
                                                        className="w-full border rounded p-2"
                                                        required
                                                    >
                                                        <option value="">Seleccionar candidato</option>
                                                        {assignedCandidates.map(candidate => (
                                                            <option key={candidate.candidateId} value={candidate.candidateId}>
                                                                {candidate.candidateName}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>

                                                <div>
                                                    <label className="block mb-1 text-sm font-medium text-[#FF4785]">Tipo de entrevista:</label>
                                                    <select
                                                        value={interviewForm.type}
                                                        onChange={(e) => setInterviewForm(prev => ({ ...prev, type: e.target.value as any }))}
                                                        className="w-full border rounded p-2"
                                                        required
                                                    >
                                                        <option value="virtual">Virtual</option>
                                                        <option value="presencial">Presencial</option>
                                                        <option value="telefónica">Telefónica</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label className="block mb-1 text-sm font-medium text-[#FF4785]">Fecha:</label>
                                                    <input
                                                        type="date"
                                                        value={interviewForm.date}
                                                        onChange={(e) => setInterviewForm(prev => ({ ...prev, date: e.target.value }))}
                                                        className="w-full border rounded p-2"
                                                        required
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block mb-1 text-sm font-medium text-[#FF4785]">Hora:</label>
                                                    <input
                                                        type="time"
                                                        value={interviewForm.time}
                                                        onChange={(e) => setInterviewForm(prev => ({ ...prev, time: e.target.value }))}
                                                        className="w-full border rounded p-2"
                                                        required
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block mb-1 text-sm font-medium text-[#FF4785]">Duración (minutos):</label>
                                                    <input
                                                        type="number"
                                                        value={interviewForm.duration}
                                                        onChange={(e) => setInterviewForm(prev => ({ ...prev, duration: parseInt(e.target.value) }))}
                                                        className="w-full border rounded p-2"
                                                        min="15"
                                                        max="240"
                                                        required
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block mb-1 text-sm font-medium text-[#FF4785]">Ubicación/Enlace:</label>
                                                    <input
                                                        type="text"
                                                        value={interviewForm.location}
                                                        onChange={(e) => setInterviewForm(prev => ({ ...prev, location: e.target.value }))}
                                                        className="w-full border rounded p-2"
                                                        placeholder="Sala de reuniones o enlace de videollamada"
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div>
                                                <label className="block mb-1 text-sm font-medium text-[#FF4785]">Notas adicionales:</label>
                                                <textarea
                                                    value={interviewForm.notes}
                                                    onChange={(e) => setInterviewForm(prev => ({ ...prev, notes: e.target.value }))}
                                                    className="w-full border rounded p-2 h-20"
                                                    placeholder="Instrucciones o información adicional para el candidato..."
                                                />
                                            </div>

                                            <div className="flex gap-3">
                                                <Button
                                                    type="submit"
                                                    className="bg-[#FF4785] hover:bg-[#FF3575]"
                                                >
                                                    Programar Entrevista
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() => setShowScheduleForm(false)}
                                                >
                                                    Cancelar
                                                </Button>
                                            </div>
                                        </form>
                                    </div>
                                )}

                                {/* Lista de entrevistas programadas */}
                                {interviewState.interviews.length === 0 && !showScheduleForm ? (
                                    <div className="text-center py-16">
                                        <div className="max-w-sm mx-auto">
                                            <div className="text-6xl mb-4">📅</div>
                                            <h3 className="text-xl font-semibold text-gray-700 mb-2">No hay entrevistas programadas</h3>
                                            <p className="text-gray-500 mb-4">Programa tu primera entrevista usando el botón de arriba.</p>
                                            <Button
                                                className="bg-[#FF4785] hover:bg-[#FF3575] text-white"
                                                onClick={() => setShowScheduleForm(true)}
                                            >
                                                📅 Programar Primera Entrevista
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {interviewState.interviews.map((interview) => (
                                            <Card key={interview.id} className="border hover:shadow-md transition-shadow">
                                                <CardContent className="p-4">
                                                    <div className="flex justify-between items-start">
                                                        <div className="flex-1">
                                                            <div className="flex items-center gap-3 mb-3">
                                                                <h3 className="font-semibold text-lg text-[#2F2F2F]">{interview.candidate.name}</h3>
                                                                <StatusBadge status={interview.status === 'scheduled' ? 'Interview Scheduled' : interview.status} />
                                                            </div>

                                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                                                <p className="text-gray-600 flex items-center gap-2">
                                                                    <span className="text-[#FF4785]">📅</span>
                                                                    <span className="font-medium">{interview.date}</span> a las <span className="font-medium">{interview.time}</span>
                                                                </p>
                                                                <p className="text-gray-600 flex items-center gap-2">
                                                                    <span className="text-[#FF4785]">📍</span>
                                                                    <span className="capitalize">{interview.type}</span> - {interview.location}
                                                                </p>
                                                                <p className="text-gray-600 flex items-center gap-2">
                                                                    <span className="text-[#FF4785]">⏱️</span>
                                                                    Duración: <span className="font-medium">{interview.duration} minutos</span>
                                                                </p>
                                                                {interview.meetingLink && (
                                                                    <p className="text-gray-600 flex items-center gap-2">
                                                                        <span className="text-[#FF4785]">�</span>
                                                                        <a href={interview.meetingLink} target="_blank" rel="noopener noreferrer"
                                                                            className="text-blue-600 hover:text-blue-800 underline">
                                                                            Enlace de reunión
                                                                        </a>
                                                                    </p>
                                                                )}
                                                            </div>

                                                            {interview.notes && (
                                                                <div className="mt-3 p-2 bg-gray-50 rounded text-sm">
                                                                    <span className="text-[#FF4785] font-medium">📝 Notas:</span>
                                                                    <p className="text-gray-700 mt-1">{interview.notes}</p>
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="flex gap-2 ml-4">
                                                            {interview.status === 'scheduled' && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="text-red-600 border-red-200 hover:bg-red-50"
                                                                    onClick={() => handleCancelInterview(interview.id)}
                                                                >
                                                                    ❌ Cancelar
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </section>
    );
};

export default RecruiterDashboard;
