
import * as React from 'react';
import { useState, useEffect, useMemo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { Layout } from '../../components/layout/Layout';
import { Card, CardHeader, CardTitle, CardContent } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../../components/ui/tabs';
import { DashboardHeader } from '../../components/ui/dashboard-header';
import { StatusBadge } from '../../components/ui/status-badge';
import { Filters } from '../../components/ui/filters';
import {
    Users,
    Calendar,
    CheckCircle,
    Clock,
    TrendingUp,
    UserCheck,
    FileText,
    AlertCircle,
    Award,
    Target,
    BarChart3
} from 'lucide-react';
import { getCandidates, getJobs, getInterviews, getRecruiters } from '../../services/ApiService';
import StatusChangeForm from '../../components/StatusChangeForm';

interface ManagerDashboardProps { }

interface Candidate {
    id: number;
    name: string;
    email: string;
    phone: string;
    jobPosition: string;
    status: string;
    appliedDate: string;
    experience: string;
    recruiter?: string;
    department?: string;
    stage?: string;
    priority?: 'high' | 'medium' | 'low';
}

interface Interview {
    id: number;
    candidateId: number;
    candidateName: string;
    jobPosition: string;
    type: string;
    date: string;
    time: string;
    interviewer: string;
    status: string;
    department?: string;
}

interface TeamMember {
    id: string;
    name: string;
    role: string;
    activeCandidates: number;
    interviewsThisWeek: number;
    hiresThisMonth: number;
}

const ManagerDashboard: React.FC<ManagerDashboardProps> = React.memo(() => {
    const navigate = useNavigate();

    // Estados principales
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [interviews, setInterviews] = useState<Interview[]>([]);
    const [jobs, setJobs] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [activeTab, setActiveTab] = useState('overview');

    // Estados de filtros
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('');
    const [recruiterFilter, setRecruiterFilter] = useState('');
    const [priorityFilter, setPriorityFilter] = useState('');

    // Datos del equipo - Se cargarán desde la API
    const [teamMembers, setTeamMembers] = useState<TeamMember[]>([]);

    // Carga de datos
    useEffect(() => {
        const loadData = async () => {
            try {
                setLoading(true);
                const [candidatesRes, jobsRes, interviewsRes, recruitersRes] = await Promise.all([
                    getCandidates(),
                    getJobs(),
                    getInterviews().catch(() => ({ success: false, data: [] })),
                    getRecruiters().catch(() => ({ success: false, data: [] }))
                ]);

                if (candidatesRes.success && candidatesRes.data) {
                    setCandidates(candidatesRes.data);
                }
                if (jobsRes.success && jobsRes.data) {
                    setJobs(jobsRes.data);
                }
                if (interviewsRes.success && interviewsRes.data) {
                    setInterviews(interviewsRes.data);
                }
                if (recruitersRes.success && recruitersRes.data) {
                    // Mapear datos de recruiters a formato TeamMember
                    const formattedTeam = recruitersRes.data.map((recruiter: any) => ({
                        id: recruiter.id,
                        name: recruiter.name,
                        role: recruiter.role || 'Recruiter',
                        email: recruiter.email,
                        department: recruiter.department,
                        activeCandidates: recruiter.active_jobs || 0,
                        interviewsThisWeek: 0, // Se puede calcular más adelante
                        hiresThisMonth: 0 // Se puede calcular más adelante
                    }));
                    setTeamMembers(formattedTeam);
                }
            } catch (err) {
                setError('Error al cargar los datos del equipo');
                console.error('Error loading manager dashboard data:', err);
            } finally {
                setLoading(false);
            }
        };

        loadData();
    }, []);

    // Candidatos filtrados con memoización
    const filteredCandidates = useMemo(() => {
        return candidates.filter(candidate => {
            const matchesSearch = !searchTerm ||
                candidate.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                candidate.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
                candidate.jobPosition.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesStatus = !statusFilter || candidate.status === statusFilter;
            const matchesDepartment = !departmentFilter || candidate.department === departmentFilter;
            const matchesRecruiter = !recruiterFilter || candidate.recruiter === recruiterFilter;

            return matchesSearch && matchesStatus && matchesDepartment && matchesRecruiter;
        });
    }, [candidates, searchTerm, statusFilter, departmentFilter, recruiterFilter]);

    // Entrevistas filtradas
    const filteredInterviews = useMemo(() => {
        return interviews.filter(interview => {
            const matchesSearch = !searchTerm ||
                interview.candidateName.toLowerCase().includes(searchTerm.toLowerCase()) ||
                interview.jobPosition.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesDepartment = !departmentFilter || interview.department === departmentFilter;

            return matchesSearch && matchesDepartment;
        });
    }, [interviews, searchTerm, departmentFilter]);

    // Estadísticas del equipo
    const teamStats = useMemo(() => {
        const totalActiveCandidates = filteredCandidates.length;
        const totalHired = filteredCandidates.filter(c => c.status === 'Hired' || c.status === 'Offer Accepted').length;
        const totalInReview = filteredCandidates.filter(c => c.status === 'Under Review' || c.status === 'In Progress').length;
        const totalInterviews = filteredInterviews.length;
        const pendingApprovals = filteredCandidates.filter(c => c.status === 'Pending Approval').length;

        return {
            totalActiveCandidates,
            totalHired,
            totalInReview,
            totalInterviews,
            pendingApprovals,
            teamSize: teamMembers.length,
            avgHiresPerRecruiter: teamMembers.length > 0 ?
                (teamMembers.reduce((acc, member) => acc + member.hiresThisMonth, 0) / teamMembers.length).toFixed(1) : '0'
        };
    }, [filteredCandidates, filteredInterviews, teamMembers]);

    // Navegación a detalles
    const handleViewCandidate = useCallback((candidateId: number) => {
        navigate(`/dashboard/applications/${candidateId}`);
    }, [navigate]);

    // Limpiar filtros
    const clearFilters = useCallback(() => {
        setSearchTerm('');
        setStatusFilter('');
        setDepartmentFilter('');
        setRecruiterFilter('');
        setPriorityFilter('');
    }, []);

    // Breadcrumbs para el header
    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Manager Dashboard', href: '/dashboard/manager' }
    ];

    // Tabs para el header
    const tabs = [
        { key: 'overview', label: 'Resumen del Equipo', icon: '📊' },
        { key: 'candidates', label: 'Candidatos', icon: '👥' },
        { key: 'interviews', label: 'Entrevistas', icon: '📅' },
        { key: 'team', label: 'Mi Equipo', icon: '✅' },
        { key: 'approvals', label: 'Aprobaciones', icon: '✔️' }
    ];

    // Filtros personalizados para manager
    const customFilters = [
        {
            type: 'select' as const,
            placeholder: 'Reclutador',
            value: recruiterFilter,
            onChange: setRecruiterFilter,
            options: [
                { value: '', label: 'Todos los reclutadores' },
                ...teamMembers.map(member => ({ value: member.name, label: member.name }))
            ]
        },
        {
            type: 'select' as const,
            placeholder: 'Prioridad',
            value: priorityFilter,
            onChange: setPriorityFilter,
            options: [
                { value: '', label: 'Todas las prioridades' },
                { value: 'high', label: 'Alta' },
                { value: 'medium', label: 'Media' },
                { value: 'low', label: 'Baja' }
            ]
        }
    ];

    if (loading) {
        return (
            <Layout>
                <div className="flex items-center justify-center min-h-96">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#FF4785] mx-auto mb-4"></div>
                        <p className="text-gray-600">Cargando dashboard del manager...</p>
                    </div>
                </div>
            </Layout>
        );
    }

    if (error) {
        return (
            <Layout>
                <div className="text-center py-8">
                    <AlertCircle className="h-12 w-12 text-red-500 mx-auto mb-4" />
                    <h2 className="text-xl font-semibold text-gray-900 mb-2">Error al cargar datos</h2>
                    <p className="text-gray-600 mb-4">{error}</p>
                    <Button onClick={() => window.location.reload()}>
                        Reintentar
                    </Button>
                </div>
            </Layout>
        );
    }

    return (
        <Layout>
            <div className="space-y-8">
                {/* Header con navegación */}
                <DashboardHeader
                    title="Manager Dashboard"
                    subtitle="Supervisión y gestión del equipo de reclutamiento"
                    breadcrumbs={breadcrumbs}
                    tabs={tabs}
                    activeTab={activeTab}
                    onTabChange={setActiveTab}
                    actions={
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => navigate('/dashboard/estadisticas')}
                                className="flex items-center gap-2"
                            >
                                <BarChart3 size={16} />
                                Ver Estadísticas
                            </Button>
                            <Button
                                onClick={() => navigate('/dashboard/hr')}
                                className="bg-[#FF4785] hover:bg-[#FF3575] flex items-center gap-2"
                            >
                                <Users size={16} />
                                HR Dashboard
                            </Button>
                        </div>
                    }
                />

                {/* Filtros */}
                <Filters
                    searchTerm={searchTerm}
                    onSearchChange={setSearchTerm}
                    statusFilter={statusFilter}
                    onStatusChange={setStatusFilter}
                    departmentFilter={departmentFilter}
                    onDepartmentChange={setDepartmentFilter}
                    onClearFilters={clearFilters}
                    customFilters={customFilters}
                    showClearButton={Boolean(searchTerm || statusFilter || departmentFilter || recruiterFilter || priorityFilter)}
                />

                {/* Contenido por tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    {/* Resumen del Equipo */}
                    <TabsContent value="overview" className="space-y-6">
                        {/* KPI Cards */}
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <Card className="border-l-4 border-l-blue-500">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Candidatos Activos</CardTitle>
                                    <Users className="h-4 w-4 text-blue-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-blue-600">{teamStats.totalActiveCandidates}</div>
                                    <p className="text-xs text-gray-500 mt-1">Gestión del equipo</p>
                                </CardContent>
                            </Card>

                            <Card className="border-l-4 border-l-green-500">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Contrataciones</CardTitle>
                                    <Award className="h-4 w-4 text-green-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-green-600">{teamStats.totalHired}</div>
                                    <p className="text-xs text-gray-500 mt-1">Promedio: {teamStats.avgHiresPerRecruiter} por reclutador</p>
                                </CardContent>
                            </Card>

                            <Card className="border-l-4 border-l-orange-500">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Pendiente Aprobación</CardTitle>
                                    <Clock className="h-4 w-4 text-orange-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-orange-600">{teamStats.pendingApprovals}</div>
                                    <p className="text-xs text-gray-500 mt-1">Requieren su aprobación</p>
                                </CardContent>
                            </Card>

                            <Card className="border-l-4 border-l-purple-500">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Tamaño del Equipo</CardTitle>
                                    <UserCheck className="h-4 w-4 text-purple-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-purple-600">{teamStats.teamSize}</div>
                                    <p className="text-xs text-gray-500 mt-1">Reclutadores activos</p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Resumen del Equipo */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <TrendingUp size={20} className="text-[#FF4785]" />
                                        Rendimiento del Equipo
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {teamMembers.map(member => (
                                            <div key={member.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                <div>
                                                    <p className="font-medium">{member.name}</p>
                                                    <p className="text-sm text-gray-500">{member.role}</p>
                                                </div>
                                                <div className="text-right text-sm">
                                                    <p><span className="font-medium">{member.activeCandidates}</span> candidatos</p>
                                                    <p><span className="font-medium text-green-600">{member.hiresThisMonth}</span> contrataciones</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Target size={20} className="text-[#FF4785]" />
                                        Objetivos del Mes
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm">Contrataciones del mes</span>
                                            <span className="text-sm font-medium">{teamStats.totalHired}/15</span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2">
                                            <div
                                                className="bg-[#FF4785] h-2 rounded-full"
                                                style={{ width: `${Math.min((teamStats.totalHired / 15) * 100, 100)}%` }}
                                            ></div>
                                        </div>

                                        <div className="flex justify-between items-center mt-4">
                                            <span className="text-sm">Entrevistas programadas</span>
                                            <span className="text-sm font-medium">{teamStats.totalInterviews}/50</span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2">
                                            <div
                                                className="bg-blue-500 h-2 rounded-full"
                                                style={{ width: `${Math.min((teamStats.totalInterviews / 50) * 100, 100)}%` }}
                                            ></div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Candidatos del Equipo */}
                    <TabsContent value="candidates" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Users size={20} className="text-[#FF4785]" />
                                    Candidatos del Equipo ({filteredCandidates.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="text-left py-3 px-2">Candidato</th>
                                                <th className="text-left py-3 px-2">Puesto</th>
                                                <th className="text-left py-3 px-2">Reclutador</th>
                                                <th className="text-center py-3 px-2">Estado</th>
                                                <th className="text-center py-3 px-2">Fecha</th>
                                                <th className="text-center py-3 px-2">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filteredCandidates.slice(0, 10).map((candidate) => (
                                                <tr key={candidate.id} className="border-b hover:bg-gray-50">
                                                    <td className="py-3 px-2">
                                                        <div>
                                                            <p className="font-medium">{candidate.name}</p>
                                                            <p className="text-xs text-gray-500">{candidate.email}</p>
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-2">
                                                        <p className="font-medium">{candidate.jobPosition}</p>
                                                        <p className="text-xs text-gray-500">{candidate.experience} años exp.</p>
                                                    </td>
                                                    <td className="py-3 px-2">
                                                        <Badge variant="outline">
                                                            {candidate.recruiter || 'No asignado'}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 px-2 text-center">
                                                        <StatusBadge status={candidate.status} size="sm" />
                                                    </td>
                                                    <td className="py-3 px-2 text-center text-xs text-gray-500">
                                                        {new Date(candidate.appliedDate).toLocaleDateString()}
                                                    </td>
                                                    <td className="py-3 px-2 text-center">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleViewCandidate(candidate.id)}
                                                        >
                                                            Ver Detalles
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    {filteredCandidates.length > 10 && (
                                        <div className="text-center mt-4">
                                            <p className="text-sm text-gray-500">
                                                Mostrando 10 de {filteredCandidates.length} candidatos
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Entrevistas del Equipo */}
                    <TabsContent value="interviews" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Calendar size={20} className="text-[#FF4785]" />
                                    Entrevistas del Equipo ({filteredInterviews.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="text-left py-3 px-2">Candidato</th>
                                                <th className="text-left py-3 px-2">Puesto</th>
                                                <th className="text-center py-3 px-2">Tipo</th>
                                                <th className="text-center py-3 px-2">Fecha</th>
                                                <th className="text-left py-3 px-2">Entrevistador</th>
                                                <th className="text-center py-3 px-2">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filteredInterviews.slice(0, 10).map((interview) => (
                                                <tr key={interview.id} className="border-b hover:bg-gray-50">
                                                    <td className="py-3 px-2">
                                                        <p className="font-medium">{interview.candidateName}</p>
                                                    </td>
                                                    <td className="py-3 px-2">
                                                        <p className="text-sm">{interview.jobPosition}</p>
                                                    </td>
                                                    <td className="py-3 px-2 text-center">
                                                        <Badge variant="outline">
                                                            {interview.type}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 px-2 text-center">
                                                        <div className="text-sm">
                                                            <p>{new Date(interview.date).toLocaleDateString()}</p>
                                                            <p className="text-xs text-gray-500">{interview.time}</p>
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-2">
                                                        <p className="text-sm">{interview.interviewer}</p>
                                                    </td>
                                                    <td className="py-3 px-2 text-center">
                                                        <StatusBadge status={interview.status} size="sm" />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Mi Equipo */}
                    <TabsContent value="team" className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {teamMembers.map(member => (
                                <Card key={member.id}>
                                    <CardHeader>
                                        <div className="flex items-center gap-3">
                                            <div className="w-10 h-10 bg-[#FF4785] rounded-full flex items-center justify-center">
                                                <span className="text-white font-medium">
                                                    {member.name.split(' ').map(n => n[0]).join('')}
                                                </span>
                                            </div>
                                            <div>
                                                <CardTitle className="text-lg">{member.name}</CardTitle>
                                                <p className="text-sm text-gray-500">{member.role}</p>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-3">
                                            <div className="flex justify-between">
                                                <span className="text-sm text-gray-600">Candidatos activos:</span>
                                                <Badge variant="outline">{member.activeCandidates}</Badge>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-sm text-gray-600">Entrevistas semana:</span>
                                                <Badge variant="outline">{member.interviewsThisWeek}</Badge>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-sm text-gray-600">Contrataciones mes:</span>
                                                <Badge className="bg-green-100 text-green-800">
                                                    {member.hiresThisMonth}
                                                </Badge>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </TabsContent>

                    {/* Aprobaciones Pendientes */}
                    <TabsContent value="approvals" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CheckCircle size={20} className="text-[#FF4785]" />
                                    Aprobaciones Pendientes ({teamStats.pendingApprovals})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {teamStats.pendingApprovals > 0 ? (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b">
                                                    <th className="text-left py-3 px-2">Candidato</th>
                                                    <th className="text-left py-3 px-2">Puesto</th>
                                                    <th className="text-left py-3 px-2">Reclutador</th>
                                                    <th className="text-center py-3 px-2">Estado</th>
                                                    <th className="text-center py-3 px-2">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {filteredCandidates
                                                    .filter(c => c.status === 'Pending Approval')
                                                    .map((candidate) => (
                                                        <tr key={candidate.id} className="border-b hover:bg-gray-50">
                                                            <td className="py-3 px-2">
                                                                <div>
                                                                    <p className="font-medium">{candidate.name}</p>
                                                                    <p className="text-xs text-gray-500">{candidate.email}</p>
                                                                </div>
                                                            </td>
                                                            <td className="py-3 px-2">
                                                                <p className="font-medium">{candidate.jobPosition}</p>
                                                            </td>
                                                            <td className="py-3 px-2">
                                                                <Badge variant="outline">
                                                                    {candidate.recruiter || 'No asignado'}
                                                                </Badge>
                                                            </td>
                                                            <td className="py-3 px-2 text-center">
                                                                <StatusBadge status={candidate.status} size="sm" />
                                                            </td>
                                                            <td className="py-3 px-2 text-center">
                                                                <div className="flex gap-2 justify-center">
                                                                    <Button size="sm" className="bg-green-600 hover:bg-green-700">
                                                                        Aprobar
                                                                    </Button>
                                                                    <Button size="sm" variant="outline">
                                                                        Revisar
                                                                    </Button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="text-center py-8">
                                        <CheckCircle className="h-12 w-12 text-green-500 mx-auto mb-4" />
                                        <h3 className="text-lg font-medium text-gray-900 mb-2">¡Todo al día!</h3>
                                        <p className="text-gray-600">No hay aprobaciones pendientes en este momento.</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                {/* Herramientas de prueba */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm text-gray-600">Herramientas de Prueba (Manager)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <StatusChangeForm />
                    </CardContent>
                </Card>
            </div>
        </Layout>
    );
});

ManagerDashboard.displayName = 'ManagerDashboard';

export default ManagerDashboard;
