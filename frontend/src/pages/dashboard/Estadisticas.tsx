import { Layout } from '../../components/layout/Layout';
import { Card, CardHeader, CardTitle, CardContent } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { getCandidates, getJobs, getHRDashboardStats } from '../../services/ApiService';
import { Bar, Pie } from 'react-chartjs-2';
import { Chart, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement } from 'chart.js';
import { useMemo, useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { TrendingUp, Users, FileText, Clock, BarChart3, PieChart } from 'lucide-react';
import StatusChangeForm from '../../components/StatusChangeForm';
import { DashboardHeader } from '../../components/ui/dashboard-header';
import { StatusBadge } from '../../components/ui/status-badge';
import { Filters } from '../../components/ui/filters';

Chart.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement);

export default function EstadisticasRRHH() {
    const navigate = useNavigate();
    const [candidates, setCandidates] = useState<any[]>([]);
    const [jobs, setJobs] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // Estados para filtros
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('');
    const [dateRange, setDateRange] = useState('all'); // all, month, quarter, year

    // Estados calculados
    const [stats, setStats] = useState({
        totalApplications: 0,
        totalHired: 0,
        totalRejected: 0,
        totalInterview: 0,
        avgTimeToHire: '12 días'
    });

    useEffect(() => {
        const loadData = async () => {
            try {
                setLoading(true);
                const [candidatesResponse, jobsResponse] = await Promise.all([
                    getCandidates(),
                    getJobs()
                ]);

                if (candidatesResponse.success && candidatesResponse.data) {
                    setCandidates(candidatesResponse.data);
                }

                if (jobsResponse && Array.isArray(jobsResponse)) {
                    setJobs(jobsResponse);
                }

                // Calcular estadísticas mejoradas
                const candidatesData = candidatesResponse.data || [];
                const totalCandidates = candidatesData.length;
                const hired = candidatesData.filter((c: any) => c.status === 'Hired' || c.status === 'Offer Accepted').length;
                const rejected = candidatesData.filter((c: any) => c.status === 'Rejected').length;
                const interviews = candidatesData.filter((c: any) =>
                    c.status === 'Interview' ||
                    c.status === 'Second Interview' ||
                    c.status === 'Final Interview'
                ).length;

                setStats({
                    totalApplications: totalCandidates,
                    totalHired: hired,
                    totalRejected: rejected,
                    totalInterview: interviews,
                    avgTimeToHire: '12 días'
                });

            } catch (err) {
                console.error('Error loading data:', err);
                setError('Error al cargar los datos');
            } finally {
                setLoading(false);
            }
        };

        loadData();
    }, []);

    const APPLICATION_STATUS = ['Under Review', 'Interview', 'Technical Test', 'Offer Extended', 'Offer Accepted', 'Rejected'];

    // Datos filtrados basados en filtros
    const filteredCandidates = useMemo(() => {
        let filtered = candidates;

        if (searchTerm) {
            filtered = filtered.filter(c =>
                c.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                c.email?.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }

        if (statusFilter) {
            filtered = filtered.filter(c => c.status === statusFilter);
        }

        if (departmentFilter) {
            filtered = filtered.filter(c => c.department === departmentFilter);
        }

        return filtered;
    }, [candidates, searchTerm, statusFilter, departmentFilter]);

    // Datos para gráficos actualizados con filtros
    const jobTitles = jobs.map(job => job.title);
    const applicationsByJob = jobTitles.map(title =>
        filteredCandidates.filter(c => c.jobPosition === title).length
    );
    const statusCounts = APPLICATION_STATUS.map(status =>
        filteredCandidates.filter(c => c.status === status).length
    );

    // Pie chart: distribución por nivel educativo
    const educationLevels = ["Primary", "Secondary", "Undergraduate", "Master's", "Doctorate", "Other"];
    const educationCounts = educationLevels.map(level =>
        filteredCandidates.filter(c => c.educationLevel === level).length
    );
    const barData = useMemo(() => ({
        labels: jobTitles,
        datasets: [
            {
                label: 'Aplicaciones por Puesto',
                data: applicationsByJob,
                backgroundColor: '#FF4785',
                borderColor: '#FF3575',
                borderWidth: 1,
                borderRadius: 4,
            },
        ],
    }), [jobTitles, applicationsByJob]);

    const statusData = useMemo(() => ({
        labels: APPLICATION_STATUS,
        datasets: [
            {
                label: 'Estado de Aplicaciones',
                data: statusCounts,
                backgroundColor: [
                    '#FCD34D', // Under Review - amarillo
                    '#60A5FA', // Interview - azul
                    '#A78BFA', // Technical Test - morado
                    '#34D399', // Offer Extended - verde
                    '#10B981', // Offer Accepted - verde oscuro
                    '#F87171', // Rejected - rojo
                ],
                borderWidth: 2,
                borderColor: '#FFFFFF',
            },
        ],
    }), [statusCounts]);

    const educationData = useMemo(() => ({
        labels: educationLevels,
        datasets: [
            {
                label: 'Distribución por Educación',
                data: educationCounts,
                backgroundColor: [
                    '#FF4785',
                    '#FF3575',
                    '#2F2F2F',
                    '#60A5FA',
                    '#A78BFA',
                    '#34D399',
                ],
                borderWidth: 2,
                borderColor: '#FFFFFF',
            },
        ],
    }), [educationCounts]);

    if (loading) {
        return (
            <Layout>
                <div className="flex justify-center items-center min-h-[400px]">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                </div>
            </Layout>
        );
    }

    if (error) {
        return (
            <Layout>
                <div className="text-center text-red-600 min-h-[400px] flex items-center justify-center">
                    <p>{error}</p>
                </div>
            </Layout>
        );
    }

    return (
        <Layout>
            {/* Dashboard Header */}
            <DashboardHeader
                title="Estadísticas RRHH"
                subtitle="Análisis completo de procesos de selección y métricas de rendimiento"
                breadcrumbs={[
                    { label: 'Dashboard', href: '/dashboard/hrdashboard' },
                    { label: 'Estadísticas' }
                ]}
                actions={
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => navigate('/dashboard/hrdashboard')}
                            className="border-[#FF4785] text-[#FF4785] hover:bg-[#FF4785] hover:text-white"
                        >
                            🏠 HR Dashboard
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => navigate('/dashboard/recruiter')}
                            className="border-[#FF4785] text-[#FF4785] hover:bg-[#FF4785] hover:text-white"
                        >
                            👨‍💼 Recruiter
                        </Button>
                    </div>
                }
            />

            <div className="container mx-auto px-4 py-8">
                {/* Filtros */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <BarChart3 size={20} className="text-[#FF4785]" />
                            Filtros de Análisis
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Filters
                            searchTerm={searchTerm}
                            onSearchChange={setSearchTerm}
                            searchPlaceholder="🔍 Buscar candidatos..."
                            statusFilter={statusFilter}
                            onStatusChange={setStatusFilter}
                            departmentFilter={departmentFilter}
                            onDepartmentChange={setDepartmentFilter}
                            customFilters={[
                                {
                                    value: dateRange,
                                    onChange: setDateRange,
                                    options: [
                                        { value: 'all', label: 'Todo el tiempo', icon: '📅' },
                                        { value: 'month', label: 'Último mes', icon: '📅' },
                                        { value: 'quarter', label: 'Último trimestre', icon: '📅' },
                                        { value: 'year', label: 'Último año', icon: '📅' }
                                    ],
                                    placeholder: '📅 Periodo'
                                }
                            ]}
                            onClearFilters={() => {
                                setSearchTerm('');
                                setStatusFilter('');
                                setDepartmentFilter('');
                                setDateRange('all');
                            }}
                        />
                    </CardContent>
                </Card>

                {/* KPI Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <Card className="border-l-4 border-l-[#FF4785]">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Aplicaciones</CardTitle>
                            <FileText className="h-4 w-4 text-[#FF4785]" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-[#FF4785]">{stats.totalApplications}</div>
                            <p className="text-xs text-gray-500 mt-1">
                                {filteredCandidates.length !== candidates.length &&
                                    `(${filteredCandidates.length} filtrados)`
                                }
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-l-4 border-l-green-500">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Contratados</CardTitle>
                            <Users className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{stats.totalHired}</div>
                            <p className="text-xs text-gray-500 mt-1">
                                {stats.totalApplications > 0 ?
                                    `${((stats.totalHired / stats.totalApplications) * 100).toFixed(1)}% éxito`
                                    : '0% éxito'
                                }
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-l-4 border-l-blue-500">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">En Entrevista</CardTitle>
                            <Clock className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">{stats.totalInterview}</div>
                            <p className="text-xs text-gray-500 mt-1">
                                {stats.totalApplications > 0 ?
                                    `${((stats.totalInterview / stats.totalApplications) * 100).toFixed(1)}% en proceso`
                                    : '0% en proceso'
                                }
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-l-4 border-l-red-500">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Rechazados</CardTitle>
                            <TrendingUp className="h-4 w-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{stats.totalRejected}</div>
                            <p className="text-xs text-gray-500 mt-1">
                                {stats.totalApplications > 0 ?
                                    `${((stats.totalRejected / stats.totalApplications) * 100).toFixed(1)}% rechazados`
                                    : '0% rechazados'
                                }
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BarChart3 size={20} className="text-[#FF4785]" />
                                Aplicaciones por Puesto
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Bar
                                data={barData}
                                options={{
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            labels: { color: '#4B5563' }
                                        }
                                    },
                                    scales: {
                                        x: { ticks: { color: '#6B7280' } },
                                        y: { ticks: { color: '#6B7280' } }
                                    },
                                }}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <PieChart size={20} className="text-[#FF4785]" />
                                Estado de Aplicaciones
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Pie
                                data={statusData}
                                options={{
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                            labels: { color: '#4B5563', padding: 20 }
                                        }
                                    }
                                }}
                            />
                        </CardContent>
                    </Card>
                </div>

                {/* Education Distribution Chart */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <PieChart size={20} className="text-[#FF4785]" />
                                Distribución por Educación
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Pie
                                data={educationData}
                                options={{
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                            labels: { color: '#4B5563', padding: 20 }
                                        }
                                    }
                                }}
                            />
                        </CardContent>
                    </Card>

                    {/* Summary Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText size={20} className="text-[#FF4785]" />
                                Resumen por Puesto
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left py-2">Puesto</th>
                                            <th className="text-center py-2">Aplicaciones</th>
                                            <th className="text-center py-2">Contratados</th>
                                            <th className="text-center py-2">Tasa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {jobs.map(job => {
                                            const total = filteredCandidates.filter((c: any) => c.jobPosition === job.title).length;
                                            const hired = filteredCandidates.filter((c: any) =>
                                                c.jobPosition === job.title &&
                                                (c.status === 'Hired' || c.status === 'Offer Accepted')
                                            ).length;
                                            const rate = total > 0 ? ((hired / total) * 100).toFixed(1) : '0';
                                            return (
                                                <tr key={job.id} className="border-b">
                                                    <td className="py-2 font-medium">{job.title}</td>
                                                    <td className="text-center py-2">{total}</td>
                                                    <td className="text-center py-2">{hired}</td>
                                                    <td className="text-center py-2">
                                                        <span className={`px-2 py-1 rounded text-xs ${parseFloat(rate) > 50 ? 'bg-green-100 text-green-800' :
                                                                parseFloat(rate) > 25 ? 'bg-yellow-100 text-yellow-800' :
                                                                    'bg-red-100 text-red-800'
                                                            }`}>
                                                            {rate}%
                                                        </span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Status Change Form (for testing) */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm text-gray-600">Herramientas de Prueba</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <StatusChangeForm />
                    </CardContent>
                </Card>
            </div>
        </Layout>
    );
}
