import React, { useState } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../ui/tabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../ui/card';
import CandidateManager from './CandidateManager';
import JobManager from './JobManager';
import ApplicationManager from './ApplicationManager';
import { Users, Briefcase, FileText, BarChart3 } from 'lucide-react';

export default function AdminPanel() {
    const [activeTab, setActiveTab] = useState('overview');

    return (
        <div className="min-h-screen bg-gray-50">
            <div className="border-b bg-white">
                <div className="container mx-auto px-4 py-4">
                    <h1 className="text-3xl font-bold text-gray-900">Panel de Administración</h1>
                    <p className="text-gray-600 mt-1">Gestiona candidatos, trabajos y aplicaciones</p>
                </div>
            </div>

            <div className="container mx-auto px-4 py-6">
                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList className="grid w-full grid-cols-4 lg:w-auto lg:grid-cols-4">
                        <TabsTrigger value="overview" className="flex items-center gap-2">
                            <BarChart3 className="w-4 h-4" />
                            <span className="hidden sm:inline">Resumen</span>
                        </TabsTrigger>
                        <TabsTrigger value="candidates" className="flex items-center gap-2">
                            <Users className="w-4 h-4" />
                            <span className="hidden sm:inline">Candidatos</span>
                        </TabsTrigger>
                        <TabsTrigger value="jobs" className="flex items-center gap-2">
                            <Briefcase className="w-4 h-4" />
                            <span className="hidden sm:inline">Trabajos</span>
                        </TabsTrigger>
                        <TabsTrigger value="applications" className="flex items-center gap-2">
                            <FileText className="w-4 h-4" />
                            <span className="hidden sm:inline">Aplicaciones</span>
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="mt-6">
                        <AdminOverview onNavigate={setActiveTab} />
                    </TabsContent>

                    <TabsContent value="candidates" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Gestión de Candidatos</CardTitle>
                                <CardDescription>
                                    Administra los candidatos registrados en la plataforma
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <CandidateManager />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="jobs" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Gestión de Trabajos</CardTitle>
                                <CardDescription>
                                    Administra las ofertas de trabajo publicadas
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <JobManager />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="applications" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Gestión de Aplicaciones</CardTitle>
                                <CardDescription>
                                    Administra las aplicaciones y cambia estados de forma individual o masiva
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <ApplicationManager />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    );
}

function AdminOverview({ onNavigate }: { onNavigate: (tab: string) => void }) {
    const [stats, setStats] = useState({
        totalCandidates: 145,
        totalJobs: 28,
        totalApplications: 892,
        activeJobs: 15,
        pendingApplications: 134,
        interviewsScheduled: 23
    });

    const quickStats = [
        {
            title: 'Total Candidatos',
            value: stats.totalCandidates,
            icon: Users,
            color: 'bg-blue-500',
            description: 'Candidatos registrados'
        },
        {
            title: 'Trabajos Activos',
            value: stats.activeJobs,
            icon: Briefcase,
            color: 'bg-green-500',
            description: `de ${stats.totalJobs} trabajos totales`
        },
        {
            title: 'Aplicaciones Pendientes',
            value: stats.pendingApplications,
            icon: FileText,
            color: 'bg-yellow-500',
            description: `de ${stats.totalApplications} aplicaciones totales`
        },
        {
            title: 'Entrevistas Programadas',
            value: stats.interviewsScheduled,
            icon: BarChart3,
            color: 'bg-purple-500',
            description: 'Para esta semana'
        }
    ];

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {quickStats.map((stat, index) => (
                    <Card key={index}>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">
                                {stat.title}
                            </CardTitle>
                            <div className={`${stat.color} p-2 rounded-lg`}>
                                <stat.icon className="w-4 h-4 text-white" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stat.value}</div>
                            <p className="text-xs text-gray-500 mt-1">{stat.description}</p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Acciones Rápidas</CardTitle>
                        <CardDescription>Operaciones frecuentes del sistema</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 gap-3">
                            <button
                                onClick={() => onNavigate('candidates')}
                                className="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <Users className="w-5 h-5 text-blue-500" />
                                    <div className="text-left">
                                        <p className="font-medium">Gestionar Candidatos</p>
                                        <p className="text-sm text-gray-500">Crear, editar y eliminar candidatos</p>
                                    </div>
                                </div>
                                <span className="text-sm text-gray-400">→</span>
                            </button>

                            <button
                                onClick={() => onNavigate('jobs')}
                                className="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <Briefcase className="w-5 h-5 text-green-500" />
                                    <div className="text-left">
                                        <p className="font-medium">Gestionar Trabajos</p>
                                        <p className="text-sm text-gray-500">Crear, editar y eliminar ofertas de trabajo</p>
                                    </div>
                                </div>
                                <span className="text-sm text-gray-400">→</span>
                            </button>

                            <button
                                onClick={() => onNavigate('applications')}
                                className="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <FileText className="w-5 h-5 text-yellow-500" />
                                    <div className="text-left">
                                        <p className="font-medium">Gestionar Aplicaciones</p>
                                        <p className="text-sm text-gray-500">Cambiar estados y gestionar aplicaciones</p>
                                    </div>
                                </div>
                                <span className="text-sm text-gray-400">→</span>
                            </button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Actividad Reciente</CardTitle>
                        <CardDescription>Últimas acciones en el sistema</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="flex items-center gap-3">
                                <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium">Nueva aplicación recibida</p>
                                    <p className="text-xs text-gray-500">María García aplicó para Desarrollador Frontend</p>
                                </div>
                                <span className="text-xs text-gray-400">Hace 5 min</span>
                            </div>

                            <div className="flex items-center gap-3">
                                <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium">Candidato actualizado</p>
                                    <p className="text-xs text-gray-500">Juan Pérez actualizó su perfil</p>
                                </div>
                                <span className="text-xs text-gray-400">Hace 12 min</span>
                            </div>

                            <div className="flex items-center gap-3">
                                <div className="w-2 h-2 bg-yellow-500 rounded-full"></div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium">Trabajo publicado</p>
                                    <p className="text-xs text-gray-500">Nueva oferta: Diseñador UX/UI</p>
                                </div>
                                <span className="text-xs text-gray-400">Hace 1 hora</span>
                            </div>

                            <div className="flex items-center gap-3">
                                <div className="w-2 h-2 bg-purple-500 rounded-full"></div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium">Estado cambiado</p>
                                    <p className="text-xs text-gray-500">Ana López pasó a entrevista</p>
                                </div>
                                <span className="text-xs text-gray-400">Hace 2 horas</span>
                            </div>

                            <div className="flex items-center gap-3">
                                <div className="w-2 h-2 bg-red-500 rounded-full"></div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium">Aplicación rechazada</p>
                                    <p className="text-xs text-gray-500">Carlos Martín para Desarrollador Backend</p>
                                </div>
                                <span className="text-xs text-gray-400">Hace 3 horas</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
