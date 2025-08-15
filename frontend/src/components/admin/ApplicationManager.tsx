import React, { useState, useEffect } from 'react';
import { Button } from '../ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '../ui/dialog';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Textarea } from '../ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../ui/table';
import { Badge } from '../ui/badge';
import { getApplications, updateApplication, deleteApplication, bulkUpdateApplications, getCandidates, getJobs } from '../../lib/apiService';
import { APPLICATION_STATUS } from '../../lib/constants';
import { Eye, Trash2, Search, CheckSquare, Square, Filter } from 'lucide-react';

interface Application {
    id: string;
    candidate_id: string;
    job_id: string;
    status: string;
    score?: number;
    notes?: string;
    created_at: string;
    candidate?: {
        name: string;
        email: string;
        phone?: string;
    };
    job?: {
        title: string;
        company_name: string;
        location: string;
    };
}

export default function ApplicationManager() {
    const [applications, setApplications] = useState<Application[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [selectedApplications, setSelectedApplications] = useState<Set<string>>(new Set());
    const [selectedApplication, setSelectedApplication] = useState<Application | null>(null);
    const [isViewDialogOpen, setIsViewDialogOpen] = useState(false);
    const [isStatusDialogOpen, setIsStatusDialogOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [isBulkStatusDialogOpen, setIsBulkStatusDialogOpen] = useState(false);
    const [newStatus, setNewStatus] = useState('');
    const [statusNotes, setStatusNotes] = useState('');

    useEffect(() => {
        loadApplications();
    }, []);

    const loadApplications = async () => {
        try {
            setLoading(true);
            setError(null);

            // Load applications, candidates, and jobs in parallel
            const [applicationsResponse, candidatesResponse, jobsResponse] = await Promise.all([
                getApplications(),
                getCandidates(),
                getJobs()
            ]);

            if (applicationsResponse.success) {
                const applications = applicationsResponse.data || [];
                const candidates = candidatesResponse.data || [];
                const jobs = jobsResponse.data || [];

                // Enrich applications with candidate and job data
                const enrichedApplications = applications.map((app: any) => {
                    const candidate = candidates.find((c: any) => c.id === app.candidate_id);
                    const job = jobs.find((j: any) => j.id === app.job_id);

                    return {
                        ...app,
                        candidate: candidate ? {
                            name: candidate.name || candidate.nombre,
                            email: candidate.email,
                            phone: candidate.phone || candidate.telefono
                        } : undefined,
                        job: job ? {
                            title: job.title || job.titulo,
                            company_name: job.company_name || job.empresa,
                            location: job.location || job.ubicacion
                        } : undefined
                    };
                });

                setApplications(enrichedApplications);
            }
        } catch (error) {
            console.error('Error loading applications:', error);
            setError('Error al cargar aplicaciones');
        } finally {
            setLoading(false);
        }
    };

    const handleStatusChange = async (applicationId: string, status: string, notes?: string) => {
        try {
            setLoading(true);
            const response = await updateApplication(applicationId, { status, notes });
            if (response.success) {
                await loadApplications();
                setIsStatusDialogOpen(false);
                setSelectedApplication(null);
                setNewStatus('');
                setStatusNotes('');
            }
        } catch (error) {
            console.error('Error updating application status:', error);
            setError('Error al actualizar estado de aplicación');
        } finally {
            setLoading(false);
        }
    };

    const handleBulkStatusChange = async () => {
        try {
            setLoading(true);
            const updates = Array.from(selectedApplications).map(id => ({
                id,
                status: newStatus,
                notes: statusNotes
            }));

            const response = await bulkUpdateApplications(updates);
            if (response.success) {
                await loadApplications();
                setIsBulkStatusDialogOpen(false);
                setSelectedApplications(new Set());
                setNewStatus('');
                setStatusNotes('');
            }
        } catch (error) {
            console.error('Error updating applications:', error);
            setError('Error al actualizar aplicaciones');
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async () => {
        if (!selectedApplication) return;

        try {
            setLoading(true);
            const response = await deleteApplication(selectedApplication.id);
            if (response.success) {
                await loadApplications();
                setIsDeleteDialogOpen(false);
                setSelectedApplication(null);
            }
        } catch (error) {
            console.error('Error deleting application:', error);
            setError('Error al eliminar aplicación');
        } finally {
            setLoading(false);
        }
    };

    const toggleApplicationSelection = (applicationId: string) => {
        const newSelection = new Set(selectedApplications);
        if (newSelection.has(applicationId)) {
            newSelection.delete(applicationId);
        } else {
            newSelection.add(applicationId);
        }
        setSelectedApplications(newSelection);
    };

    const toggleSelectAll = () => {
        if (selectedApplications.size === filteredApplications.length) {
            setSelectedApplications(new Set());
        } else {
            setSelectedApplications(new Set(filteredApplications.map(app => app.id)));
        }
    };

    const openViewDialog = (application: Application) => {
        setSelectedApplication(application);
        setIsViewDialogOpen(true);
    };

    const openStatusDialog = (application: Application) => {
        setSelectedApplication(application);
        setNewStatus(application.status);
        setStatusNotes('');
        setIsStatusDialogOpen(true);
    };

    const openDeleteDialog = (application: Application) => {
        setSelectedApplication(application);
        setIsDeleteDialogOpen(true);
    };

    const filteredApplications = applications.filter(app => {
        const matchesSearch =
            (app.candidate?.name || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
            (app.candidate?.email || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
            (app.job?.title || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
            (app.job?.company_name || '').toLowerCase().includes(searchTerm.toLowerCase());

        const matchesStatus = statusFilter === 'all' || app.status === statusFilter;

        return matchesSearch && matchesStatus;
    });

    const getStatusColor = (status: string) => {
        switch (status.toLowerCase()) {
            case 'received': case 'recibida': return 'bg-gray-100 text-gray-800';
            case 'under review': case 'en revisión': return 'bg-blue-100 text-blue-800';
            case 'interview': case 'entrevista': return 'bg-yellow-100 text-yellow-800';
            case 'technical test': case 'prueba técnica': return 'bg-purple-100 text-purple-800';
            case 'offer': case 'oferta': return 'bg-green-100 text-green-800';
            case 'hired': case 'contratado': return 'bg-emerald-100 text-emerald-800';
            case 'rejected': case 'rechazado': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    return (
        <div className="p-6">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">Gestión de Aplicaciones</h1>
                {selectedApplications.size > 0 && (
                    <Button
                        onClick={() => setIsBulkStatusDialogOpen(true)}
                        className="bg-[#FF4785] hover:bg-[#FF3575]"
                    >
                        Cambiar Estado ({selectedApplications.size})
                    </Button>
                )}
            </div>

            {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    {error}
                </div>
            )}

            <div className="flex gap-4 mb-4">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <Input
                        placeholder="Buscar por candidato, trabajo o empresa..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="pl-10"
                    />
                </div>
                <div className="relative">
                    <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <Select value={statusFilter} onValueChange={setStatusFilter}>
                        <SelectTrigger className="w-48 pl-10">
                            <SelectValue placeholder="Filtrar por estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los estados</SelectItem>
                            {APPLICATION_STATUS.map(status => (
                                <SelectItem key={status} value={status}>{status}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {loading ? (
                <div className="text-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#FF4785] mx-auto"></div>
                    <p className="mt-2 text-gray-600">Cargando aplicaciones...</p>
                </div>
            ) : (
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={toggleSelectAll}
                                        className="p-0"
                                    >
                                        {selectedApplications.size === filteredApplications.length && filteredApplications.length > 0 ?
                                            <CheckSquare className="w-4 h-4" /> :
                                            <Square className="w-4 h-4" />
                                        }
                                    </Button>
                                </TableHead>
                                <TableHead>Candidato</TableHead>
                                <TableHead>Trabajo</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Puntuación</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filteredApplications.map((app) => (
                                <TableRow key={app.id}>
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => toggleApplicationSelection(app.id)}
                                            className="p-0"
                                        >
                                            {selectedApplications.has(app.id) ?
                                                <CheckSquare className="w-4 h-4" /> :
                                                <Square className="w-4 h-4" />
                                            }
                                        </Button>
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <p className="font-medium">{app.candidate?.name || 'Candidato desconocido'}</p>
                                            <p className="text-sm text-gray-500">{app.candidate?.email}</p>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <p className="font-medium">{app.job?.title || 'Trabajo desconocido'}</p>
                                            <p className="text-sm text-gray-500">{app.job?.company_name}</p>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            className={`${getStatusColor(app.status)} cursor-pointer`}
                                            onClick={() => openStatusDialog(app)}
                                        >
                                            {app.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {app.score ? `${app.score}%` : '-'}
                                    </TableCell>
                                    <TableCell>
                                        {new Date(app.created_at).toLocaleDateString()}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex space-x-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openViewDialog(app)}
                                            >
                                                <Eye className="w-4 h-4" />
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openDeleteDialog(app)}
                                                className="text-red-600 hover:text-red-700"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    {filteredApplications.length === 0 && (
                        <div className="text-center py-8 text-gray-500">
                            No se encontraron aplicaciones
                        </div>
                    )}
                </div>
            )}

            {/* View Dialog */}
            <Dialog open={isViewDialogOpen} onOpenChange={setIsViewDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Detalles de la Aplicación</DialogTitle>
                    </DialogHeader>
                    {selectedApplication && (
                        <div className="py-4 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label className="font-semibold">Candidato</Label>
                                    <p>{selectedApplication.candidate?.name}</p>
                                    <p className="text-sm text-gray-500">{selectedApplication.candidate?.email}</p>
                                </div>
                                <div>
                                    <Label className="font-semibold">Trabajo</Label>
                                    <p>{selectedApplication.job?.title}</p>
                                    <p className="text-sm text-gray-500">{selectedApplication.job?.company_name}</p>
                                </div>
                                <div>
                                    <Label className="font-semibold">Estado</Label>
                                    <Badge className={getStatusColor(selectedApplication.status)}>
                                        {selectedApplication.status}
                                    </Badge>
                                </div>
                                <div>
                                    <Label className="font-semibold">Puntuación</Label>
                                    <p>{selectedApplication.score ? `${selectedApplication.score}%` : 'No disponible'}</p>
                                </div>
                                <div>
                                    <Label className="font-semibold">Fecha de Aplicación</Label>
                                    <p>{new Date(selectedApplication.created_at).toLocaleDateString()}</p>
                                </div>
                                <div>
                                    <Label className="font-semibold">ID</Label>
                                    <p className="text-sm text-gray-500">{selectedApplication.id}</p>
                                </div>
                            </div>
                            {selectedApplication.notes && (
                                <div>
                                    <Label className="font-semibold">Notas</Label>
                                    <p className="mt-2 text-gray-700 bg-gray-50 p-3 rounded">{selectedApplication.notes}</p>
                                </div>
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsViewDialogOpen(false)}>
                            Cerrar
                        </Button>
                        <Button onClick={() => {
                            setIsViewDialogOpen(false);
                            if (selectedApplication) openStatusDialog(selectedApplication);
                        }}>
                            Cambiar Estado
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Status Change Dialog */}
            <Dialog open={isStatusDialogOpen} onOpenChange={setIsStatusDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cambiar Estado de Aplicación</DialogTitle>
                    </DialogHeader>
                    <div className="py-4 space-y-4">
                        <div>
                            <Label htmlFor="status">Nuevo Estado</Label>
                            <Select value={newStatus} onValueChange={setNewStatus}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    {APPLICATION_STATUS.map(status => (
                                        <SelectItem key={status} value={status}>{status}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="notes">Notas (opcional)</Label>
                            <Textarea
                                id="notes"
                                value={statusNotes}
                                onChange={(e) => setStatusNotes(e.target.value)}
                                placeholder="Agregar comentarios sobre el cambio de estado..."
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsStatusDialogOpen(false)}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={() => selectedApplication && handleStatusChange(selectedApplication.id, newStatus, statusNotes)}
                            disabled={!newStatus || loading}
                        >
                            {loading ? 'Actualizando...' : 'Actualizar Estado'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Bulk Status Change Dialog */}
            <Dialog open={isBulkStatusDialogOpen} onOpenChange={setIsBulkStatusDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cambiar Estado de {selectedApplications.size} Aplicaciones</DialogTitle>
                    </DialogHeader>
                    <div className="py-4 space-y-4">
                        <div>
                            <Label htmlFor="bulk-status">Nuevo Estado</Label>
                            <Select value={newStatus} onValueChange={setNewStatus}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    {APPLICATION_STATUS.map(status => (
                                        <SelectItem key={status} value={status}>{status}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="bulk-notes">Notas (opcional)</Label>
                            <Textarea
                                id="bulk-notes"
                                value={statusNotes}
                                onChange={(e) => setStatusNotes(e.target.value)}
                                placeholder="Agregar comentarios para todas las aplicaciones..."
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsBulkStatusDialogOpen(false)}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={handleBulkStatusChange}
                            disabled={!newStatus || loading}
                        >
                            {loading ? 'Actualizando...' : `Actualizar ${selectedApplications.size} Aplicaciones`}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar Eliminación</DialogTitle>
                    </DialogHeader>
                    <p>¿Estás seguro de que quieres eliminar esta aplicación?</p>
                    <div className="bg-gray-50 p-3 rounded mt-2">
                        <p><strong>Candidato:</strong> {selectedApplication?.candidate?.name}</p>
                        <p><strong>Trabajo:</strong> {selectedApplication?.job?.title}</p>
                        <p><strong>Estado:</strong> {selectedApplication?.status}</p>
                    </div>
                    <p className="text-sm text-red-600 mt-2">Esta acción no se puede deshacer.</p>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsDeleteDialogOpen(false)}>
                            Cancelar
                        </Button>
                        <Button onClick={handleDelete} disabled={loading} className="bg-red-600 hover:bg-red-700">
                            {loading ? 'Eliminando...' : 'Eliminar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
