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
import { getJobs, createJob, updateJob, deleteJob } from '../../services/ApiService';
import { Pencil, Trash2, Plus, Search, Eye } from 'lucide-react';

interface Job {
    id: string;
    title: string;
    description: string;
    company_name: string;
    location: string;
    employment_type?: string;
    department?: string;
    salary_min?: number;
    salary_max?: number;
    required_skills?: string[];
    experience_level?: string;
    education_level?: string;
    status?: string;
    created_at?: string;
}

interface JobFormData {
    title: string;
    description: string;
    company_name: string;
    location: string;
    employment_type: string;
    department: string;
    salary_min: string;
    salary_max: string;
    required_skills: string;
    experience_level: string;
    education_level: string;
    status: string;
}

const initialFormData: JobFormData = {
    title: '',
    description: '',
    company_name: '',
    location: '',
    employment_type: '',
    department: '',
    salary_min: '',
    salary_max: '',
    required_skills: '',
    experience_level: '',
    education_level: '',
    status: 'active'
};

export default function JobManager() {
    const [jobs, setJobs] = useState<Job[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedJob, setSelectedJob] = useState<Job | null>(null);
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [isViewDialogOpen, setIsViewDialogOpen] = useState(false);
    const [formData, setFormData] = useState<JobFormData>(initialFormData);

    useEffect(() => {
        loadJobs();
    }, []);

    const loadJobs = async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await getJobs();
            if (response.success) {
                setJobs(response.data || []);
            }
        } catch (error) {
            console.error('Error loading jobs:', error);
            setError('Error al cargar trabajos');
        } finally {
            setLoading(false);
        }
    };

    const handleCreate = async () => {
        try {
            setLoading(true);
            const jobData = {
                ...formData,
                required_skills: formData.required_skills ? formData.required_skills.split(',').map(s => s.trim()) : [],
                salary_min: formData.salary_min ? parseInt(formData.salary_min) : undefined,
                salary_max: formData.salary_max ? parseInt(formData.salary_max) : undefined
            };

            const response = await createJob(jobData);
            if (response.success) {
                await loadJobs();
                setIsCreateDialogOpen(false);
                setFormData(initialFormData);
            }
        } catch (error) {
            console.error('Error creating job:', error);
            setError('Error al crear trabajo');
        } finally {
            setLoading(false);
        }
    };

    const handleUpdate = async () => {
        if (!selectedJob) return;

        try {
            setLoading(true);
            const jobData = {
                ...formData,
                required_skills: formData.required_skills ? formData.required_skills.split(',').map(s => s.trim()) : [],
                salary_min: formData.salary_min ? parseInt(formData.salary_min) : undefined,
                salary_max: formData.salary_max ? parseInt(formData.salary_max) : undefined
            };

            const response = await updateJob(selectedJob.id, jobData);
            if (response.success) {
                await loadJobs();
                setIsEditDialogOpen(false);
                setSelectedJob(null);
                setFormData(initialFormData);
            }
        } catch (error) {
            console.error('Error updating job:', error);
            setError('Error al actualizar trabajo');
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async () => {
        if (!selectedJob) return;

        try {
            setLoading(true);
            const response = await deleteJob(selectedJob.id);
            if (response.success) {
                await loadJobs();
                setIsDeleteDialogOpen(false);
                setSelectedJob(null);
            }
        } catch (error) {
            console.error('Error deleting job:', error);
            setError('Error al eliminar trabajo');
        } finally {
            setLoading(false);
        }
    };

    const openEditDialog = (job: Job) => {
        setSelectedJob(job);
        setFormData({
            title: job.title || '',
            description: job.description || '',
            company_name: job.company_name || '',
            location: job.location || '',
            employment_type: job.employment_type || '',
            department: job.department || '',
            salary_min: job.salary_min?.toString() || '',
            salary_max: job.salary_max?.toString() || '',
            required_skills: job.required_skills ? job.required_skills.join(', ') : '',
            experience_level: job.experience_level || '',
            education_level: job.education_level || '',
            status: job.status || 'active'
        });
        setIsEditDialogOpen(true);
    };

    const openViewDialog = (job: Job) => {
        setSelectedJob(job);
        setIsViewDialogOpen(true);
    };

    const openDeleteDialog = (job: Job) => {
        setSelectedJob(job);
        setIsDeleteDialogOpen(true);
    };

    const filteredJobs = jobs.filter(job =>
        job.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
        job.company_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (job.location && job.location.toLowerCase().includes(searchTerm.toLowerCase())) ||
        (job.department && job.department.toLowerCase().includes(searchTerm.toLowerCase()))
    );

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active': return 'bg-green-100 text-green-800';
            case 'paused': return 'bg-yellow-100 text-yellow-800';
            case 'closed': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    return (
        <div className="p-6">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">Gestión de Trabajos</h1>
                <Button onClick={() => setIsCreateDialogOpen(true)} className="bg-[#FF4785] hover:bg-[#FF3575]">
                    <Plus className="w-4 h-4 mr-2" />
                    Nuevo Trabajo
                </Button>
            </div>

            {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    {error}
                </div>
            )}

            <div className="mb-4">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <Input
                        placeholder="Buscar trabajos..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="pl-10"
                    />
                </div>
            </div>

            {loading ? (
                <div className="text-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#FF4785] mx-auto"></div>
                    <p className="mt-2 text-gray-600">Cargando trabajos...</p>
                </div>
            ) : (
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Título</TableHead>
                                <TableHead>Empresa</TableHead>
                                <TableHead>Ubicación</TableHead>
                                <TableHead>Departamento</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filteredJobs.map((job) => (
                                <TableRow key={job.id}>
                                    <TableCell className="font-medium">{job.title}</TableCell>
                                    <TableCell>{job.company_name}</TableCell>
                                    <TableCell>{job.location}</TableCell>
                                    <TableCell>{job.department || '-'}</TableCell>
                                    <TableCell>
                                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(job.status || 'active')}`}>
                                            {job.status || 'active'}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex space-x-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openViewDialog(job)}
                                            >
                                                <Eye className="w-4 h-4" />
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openEditDialog(job)}
                                            >
                                                <Pencil className="w-4 h-4" />
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openDeleteDialog(job)}
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

                    {filteredJobs.length === 0 && (
                        <div className="text-center py-8 text-gray-500">
                            No se encontraron trabajos
                        </div>
                    )}
                </div>
            )}

            {/* Create Dialog */}
            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Crear Nuevo Trabajo</DialogTitle>
                    </DialogHeader>
                    <div className="grid grid-cols-2 gap-4 py-4">
                        <div>
                            <Label htmlFor="title">Título *</Label>
                            <Input
                                id="title"
                                value={formData.title}
                                onChange={(e) => setFormData(prev => ({ ...prev, title: e.target.value }))}
                                placeholder="Desarrollador Full Stack"
                            />
                        </div>
                        <div>
                            <Label htmlFor="company_name">Empresa *</Label>
                            <Input
                                id="company_name"
                                value={formData.company_name}
                                onChange={(e) => setFormData(prev => ({ ...prev, company_name: e.target.value }))}
                                placeholder="Nombre de la empresa"
                            />
                        </div>
                        <div>
                            <Label htmlFor="location">Ubicación *</Label>
                            <Input
                                id="location"
                                value={formData.location}
                                onChange={(e) => setFormData(prev => ({ ...prev, location: e.target.value }))}
                                placeholder="Ciudad, País"
                            />
                        </div>
                        <div>
                            <Label htmlFor="department">Departamento</Label>
                            <Input
                                id="department"
                                value={formData.department}
                                onChange={(e) => setFormData(prev => ({ ...prev, department: e.target.value }))}
                                placeholder="Tecnología"
                            />
                        </div>
                        <div>
                            <Label htmlFor="employment_type">Tipo de Empleo</Label>
                            <Select value={formData.employment_type} onValueChange={(value) => setFormData(prev => ({ ...prev, employment_type: value }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="full-time">Tiempo Completo</SelectItem>
                                    <SelectItem value="part-time">Medio Tiempo</SelectItem>
                                    <SelectItem value="contract">Contrato</SelectItem>
                                    <SelectItem value="freelance">Freelance</SelectItem>
                                    <SelectItem value="internship">Prácticas</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="status">Estado</Label>
                            <Select value={formData.status} onValueChange={(value) => setFormData(prev => ({ ...prev, status: value }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Activo</SelectItem>
                                    <SelectItem value="paused">Pausado</SelectItem>
                                    <SelectItem value="closed">Cerrado</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="salary_min">Salario Mínimo</Label>
                            <Input
                                id="salary_min"
                                type="number"
                                value={formData.salary_min}
                                onChange={(e) => setFormData(prev => ({ ...prev, salary_min: e.target.value }))}
                                placeholder="50000"
                            />
                        </div>
                        <div>
                            <Label htmlFor="salary_max">Salario Máximo</Label>
                            <Input
                                id="salary_max"
                                type="number"
                                value={formData.salary_max}
                                onChange={(e) => setFormData(prev => ({ ...prev, salary_max: e.target.value }))}
                                placeholder="80000"
                            />
                        </div>
                        <div>
                            <Label htmlFor="experience_level">Nivel de Experiencia</Label>
                            <Select value={formData.experience_level} onValueChange={(value) => setFormData(prev => ({ ...prev, experience_level: value }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar nivel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="entry">Junior</SelectItem>
                                    <SelectItem value="mid">Semi-Senior</SelectItem>
                                    <SelectItem value="senior">Senior</SelectItem>
                                    <SelectItem value="lead">Lead</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="education_level">Nivel de Educación</Label>
                            <Select value={formData.education_level} onValueChange={(value) => setFormData(prev => ({ ...prev, education_level: value }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar nivel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="secundaria">Secundaria</SelectItem>
                                    <SelectItem value="tecnico">Técnico</SelectItem>
                                    <SelectItem value="universitario">Universitario</SelectItem>
                                    <SelectItem value="posgrado">Posgrado</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="col-span-2">
                            <Label htmlFor="description">Descripción *</Label>
                            <Textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                                placeholder="Descripción detallada del trabajo..."
                                className="min-h-[100px]"
                            />
                        </div>
                        <div className="col-span-2">
                            <Label htmlFor="required_skills">Habilidades Requeridas (separadas por comas)</Label>
                            <Textarea
                                id="required_skills"
                                value={formData.required_skills}
                                onChange={(e) => setFormData(prev => ({ ...prev, required_skills: e.target.value }))}
                                placeholder="JavaScript, React, Node.js, SQL, Git"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                            Cancelar
                        </Button>
                        <Button onClick={handleCreate} disabled={!formData.title || !formData.company_name || !formData.location || !formData.description || loading}>
                            {loading ? 'Creando...' : 'Crear Trabajo'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog (similar to create) */}
            <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
                <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Editar Trabajo</DialogTitle>
                    </DialogHeader>
                    {/* Same form fields as create dialog */}
                    <div className="grid grid-cols-2 gap-4 py-4">
                        <div>
                            <Label htmlFor="edit-title">Título *</Label>
                            <Input
                                id="edit-title"
                                value={formData.title}
                                onChange={(e) => setFormData(prev => ({ ...prev, title: e.target.value }))}
                                placeholder="Desarrollador Full Stack"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-company_name">Empresa *</Label>
                            <Input
                                id="edit-company_name"
                                value={formData.company_name}
                                onChange={(e) => setFormData(prev => ({ ...prev, company_name: e.target.value }))}
                                placeholder="Nombre de la empresa"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-location">Ubicación *</Label>
                            <Input
                                id="edit-location"
                                value={formData.location}
                                onChange={(e) => setFormData(prev => ({ ...prev, location: e.target.value }))}
                                placeholder="Ciudad, País"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-department">Departamento</Label>
                            <Input
                                id="edit-department"
                                value={formData.department}
                                onChange={(e) => setFormData(prev => ({ ...prev, department: e.target.value }))}
                                placeholder="Tecnología"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-employment_type">Tipo de Empleo</Label>
                            <Select value={formData.employment_type} onValueChange={(value) => setFormData(prev => ({ ...prev, employment_type: value }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="full-time">Tiempo Completo</SelectItem>
                                    <SelectItem value="part-time">Medio Tiempo</SelectItem>
                                    <SelectItem value="contract">Contrato</SelectItem>
                                    <SelectItem value="freelance">Freelance</SelectItem>
                                    <SelectItem value="internship">Prácticas</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="edit-status">Estado</Label>
                            <Select value={formData.status} onValueChange={(value) => setFormData(prev => ({ ...prev, status: value }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Activo</SelectItem>
                                    <SelectItem value="paused">Pausado</SelectItem>
                                    <SelectItem value="closed">Cerrado</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="edit-salary_min">Salario Mínimo</Label>
                            <Input
                                id="edit-salary_min"
                                type="number"
                                value={formData.salary_min}
                                onChange={(e) => setFormData(prev => ({ ...prev, salary_min: e.target.value }))}
                                placeholder="50000"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-salary_max">Salario Máximo</Label>
                            <Input
                                id="edit-salary_max"
                                type="number"
                                value={formData.salary_max}
                                onChange={(e) => setFormData(prev => ({ ...prev, salary_max: e.target.value }))}
                                placeholder="80000"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-experience_level">Nivel de Experiencia</Label>
                            <Select value={formData.experience_level} onValueChange={(value) => setFormData(prev => ({ ...prev, experience_level: value }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar nivel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="entry">Junior</SelectItem>
                                    <SelectItem value="mid">Semi-Senior</SelectItem>
                                    <SelectItem value="senior">Senior</SelectItem>
                                    <SelectItem value="lead">Lead</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="edit-education_level">Nivel de Educación</Label>
                            <Select value={formData.education_level} onValueChange={(value) => setFormData(prev => ({ ...prev, education_level: value }))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar nivel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="secundaria">Secundaria</SelectItem>
                                    <SelectItem value="tecnico">Técnico</SelectItem>
                                    <SelectItem value="universitario">Universitario</SelectItem>
                                    <SelectItem value="posgrado">Posgrado</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="col-span-2">
                            <Label htmlFor="edit-description">Descripción *</Label>
                            <Textarea
                                id="edit-description"
                                value={formData.description}
                                onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                                placeholder="Descripción detallada del trabajo..."
                                className="min-h-[100px]"
                            />
                        </div>
                        <div className="col-span-2">
                            <Label htmlFor="edit-required_skills">Habilidades Requeridas (separadas por comas)</Label>
                            <Textarea
                                id="edit-required_skills"
                                value={formData.required_skills}
                                onChange={(e) => setFormData(prev => ({ ...prev, required_skills: e.target.value }))}
                                placeholder="JavaScript, React, Node.js, SQL, Git"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                            Cancelar
                        </Button>
                        <Button onClick={handleUpdate} disabled={!formData.title || !formData.company_name || !formData.location || !formData.description || loading}>
                            {loading ? 'Actualizando...' : 'Actualizar Trabajo'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* View Dialog */}
            <Dialog open={isViewDialogOpen} onOpenChange={setIsViewDialogOpen}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Detalles del Trabajo</DialogTitle>
                    </DialogHeader>
                    {selectedJob && (
                        <div className="py-4 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label className="font-semibold">Título</Label>
                                    <p>{selectedJob.title}</p>
                                </div>
                                <div>
                                    <Label className="font-semibold">Empresa</Label>
                                    <p>{selectedJob.company_name}</p>
                                </div>
                                <div>
                                    <Label className="font-semibold">Ubicación</Label>
                                    <p>{selectedJob.location}</p>
                                </div>
                                <div>
                                    <Label className="font-semibold">Departamento</Label>
                                    <p>{selectedJob.department || '-'}</p>
                                </div>
                                <div>
                                    <Label className="font-semibold">Tipo de Empleo</Label>
                                    <p>{selectedJob.employment_type || '-'}</p>
                                </div>
                                <div>
                                    <Label className="font-semibold">Estado</Label>
                                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(selectedJob.status || 'active')}`}>
                                        {selectedJob.status || 'active'}
                                    </span>
                                </div>
                                {(selectedJob.salary_min || selectedJob.salary_max) && (
                                    <div className="col-span-2">
                                        <Label className="font-semibold">Salario</Label>
                                        <p>
                                            {selectedJob.salary_min && selectedJob.salary_max
                                                ? `$${selectedJob.salary_min.toLocaleString()} - $${selectedJob.salary_max.toLocaleString()}`
                                                : selectedJob.salary_min
                                                    ? `Desde $${selectedJob.salary_min.toLocaleString()}`
                                                    : selectedJob.salary_max
                                                        ? `Hasta $${selectedJob.salary_max.toLocaleString()}`
                                                        : '-'
                                            }
                                        </p>
                                    </div>
                                )}
                            </div>
                            <div>
                                <Label className="font-semibold">Descripción</Label>
                                <p className="mt-2 text-gray-700 whitespace-pre-wrap">{selectedJob.description}</p>
                            </div>
                            {selectedJob.required_skills && selectedJob.required_skills.length > 0 && (
                                <div>
                                    <Label className="font-semibold">Habilidades Requeridas</Label>
                                    <div className="flex flex-wrap gap-2 mt-2">
                                        {selectedJob.required_skills.map((skill, index) => (
                                            <span key={index} className="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-sm">
                                                {skill}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button onClick={() => setIsViewDialogOpen(false)}>
                            Cerrar
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
                    <p>¿Estás seguro de que quieres eliminar el trabajo <strong>{selectedJob?.title}</strong>?</p>
                    <p className="text-sm text-red-600 mt-2">Esta acción no se puede deshacer y eliminará todas las aplicaciones asociadas.</p>
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
