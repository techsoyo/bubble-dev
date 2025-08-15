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
import { getCandidates, createCandidate, updateCandidate, deleteCandidate } from '../../lib/apiService';
import { Pencil, Trash2, Plus, Search } from 'lucide-react';

interface Candidate {
    id: string;
    name: string;
    email: string;
    phone?: string;
    location?: string;
    skills?: string[];
    experience_years?: number;
    education_level?: string;
    linkedin?: string;
    github?: string;
    portfolio?: string;
    created_at?: string;
}

interface CandidateFormData {
    name: string;
    email: string;
    phone: string;
    location: string;
    skills: string;
    experience_years: string;
    education_level: string;
    linkedin: string;
    github: string;
    portfolio: string;
}

const initialFormData: CandidateFormData = {
    name: '',
    email: '',
    phone: '',
    location: '',
    skills: '',
    experience_years: '',
    education_level: '',
    linkedin: '',
    github: '',
    portfolio: ''
};

export default function CandidateManager() {
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCandidate, setSelectedCandidate] = useState<Candidate | null>(null);
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [formData, setFormData] = useState<CandidateFormData>(initialFormData);

    useEffect(() => {
        loadCandidates();
    }, []);

    const loadCandidates = async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await getCandidates();
            if (response.success) {
                setCandidates(response.data || []);
            }
        } catch (error) {
            console.error('Error loading candidates:', error);
            setError('Error al cargar candidatos');
        } finally {
            setLoading(false);
        }
    };

    const handleCreate = async () => {
        try {
            setLoading(true);
            const candidateData = {
                ...formData,
                skills: formData.skills ? formData.skills.split(',').map(s => s.trim()) : [],
                experience_years: formData.experience_years ? parseInt(formData.experience_years) : undefined
            };

            const response = await createCandidate(candidateData);
            if (response.success) {
                await loadCandidates();
                setIsCreateDialogOpen(false);
                setFormData(initialFormData);
            }
        } catch (error) {
            console.error('Error creating candidate:', error);
            setError('Error al crear candidato');
        } finally {
            setLoading(false);
        }
    };

    const handleUpdate = async () => {
        if (!selectedCandidate) return;

        try {
            setLoading(true);
            const candidateData = {
                ...formData,
                skills: formData.skills ? formData.skills.split(',').map(s => s.trim()) : [],
                experience_years: formData.experience_years ? parseInt(formData.experience_years) : undefined
            };

            const response = await updateCandidate(selectedCandidate.id, candidateData);
            if (response.success) {
                await loadCandidates();
                setIsEditDialogOpen(false);
                setSelectedCandidate(null);
                setFormData(initialFormData);
            }
        } catch (error) {
            console.error('Error updating candidate:', error);
            setError('Error al actualizar candidato');
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async () => {
        if (!selectedCandidate) return;

        try {
            setLoading(true);
            const response = await deleteCandidate(selectedCandidate.id);
            if (response.success) {
                await loadCandidates();
                setIsDeleteDialogOpen(false);
                setSelectedCandidate(null);
            }
        } catch (error) {
            console.error('Error deleting candidate:', error);
            setError('Error al eliminar candidato');
        } finally {
            setLoading(false);
        }
    };

    const openEditDialog = (candidate: Candidate) => {
        setSelectedCandidate(candidate);
        setFormData({
            name: candidate.name || '',
            email: candidate.email || '',
            phone: candidate.phone || '',
            location: candidate.location || '',
            skills: candidate.skills ? candidate.skills.join(', ') : '',
            experience_years: candidate.experience_years?.toString() || '',
            education_level: candidate.education_level || '',
            linkedin: candidate.linkedin || '',
            github: candidate.github || '',
            portfolio: candidate.portfolio || ''
        });
        setIsEditDialogOpen(true);
    };

    const openDeleteDialog = (candidate: Candidate) => {
        setSelectedCandidate(candidate);
        setIsDeleteDialogOpen(true);
    };

    const filteredCandidates = candidates.filter(candidate =>
        candidate.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        candidate.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (candidate.location && candidate.location.toLowerCase().includes(searchTerm.toLowerCase()))
    );

    return (
        <div className="p-6">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">Gestión de Candidatos</h1>
                <Button onClick={() => setIsCreateDialogOpen(true)} className="bg-[#FF4785] hover:bg-[#FF3575]">
                    <Plus className="w-4 h-4 mr-2" />
                    Nuevo Candidato
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
                        placeholder="Buscar candidatos..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="pl-10"
                    />
                </div>
            </div>

            {loading ? (
                <div className="text-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#FF4785] mx-auto"></div>
                    <p className="mt-2 text-gray-600">Cargando candidatos...</p>
                </div>
            ) : (
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Ubicación</TableHead>
                                <TableHead>Experiencia</TableHead>
                                <TableHead>Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filteredCandidates.map((candidate) => (
                                <TableRow key={candidate.id}>
                                    <TableCell className="font-medium">{candidate.name}</TableCell>
                                    <TableCell>{candidate.email}</TableCell>
                                    <TableCell>{candidate.location || '-'}</TableCell>
                                    <TableCell>{candidate.experience_years ? `${candidate.experience_years} años` : '-'}</TableCell>
                                    <TableCell>
                                        <div className="flex space-x-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openEditDialog(candidate)}
                                            >
                                                <Pencil className="w-4 h-4" />
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openDeleteDialog(candidate)}
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

                    {filteredCandidates.length === 0 && (
                        <div className="text-center py-8 text-gray-500">
                            No se encontraron candidatos
                        </div>
                    )}
                </div>
            )}

            {/* Create Dialog */}
            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Crear Nuevo Candidato</DialogTitle>
                    </DialogHeader>
                    <div className="grid grid-cols-2 gap-4 py-4">
                        <div>
                            <Label htmlFor="name">Nombre *</Label>
                            <Input
                                id="name"
                                value={formData.name}
                                onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                                placeholder="Nombre completo"
                            />
                        </div>
                        <div>
                            <Label htmlFor="email">Email *</Label>
                            <Input
                                id="email"
                                type="email"
                                value={formData.email}
                                onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
                                placeholder="email@ejemplo.com"
                            />
                        </div>
                        <div>
                            <Label htmlFor="phone">Teléfono</Label>
                            <Input
                                id="phone"
                                value={formData.phone}
                                onChange={(e) => setFormData(prev => ({ ...prev, phone: e.target.value }))}
                                placeholder="+1 234 567 8900"
                            />
                        </div>
                        <div>
                            <Label htmlFor="location">Ubicación</Label>
                            <Input
                                id="location"
                                value={formData.location}
                                onChange={(e) => setFormData(prev => ({ ...prev, location: e.target.value }))}
                                placeholder="Ciudad, País"
                            />
                        </div>
                        <div>
                            <Label htmlFor="experience_years">Años de Experiencia</Label>
                            <Input
                                id="experience_years"
                                type="number"
                                value={formData.experience_years}
                                onChange={(e) => setFormData(prev => ({ ...prev, experience_years: e.target.value }))}
                                placeholder="5"
                            />
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
                            <Label htmlFor="skills">Habilidades (separadas por comas)</Label>
                            <Textarea
                                id="skills"
                                value={formData.skills}
                                onChange={(e) => setFormData(prev => ({ ...prev, skills: e.target.value }))}
                                placeholder="JavaScript, React, Node.js, Python"
                            />
                        </div>
                        <div>
                            <Label htmlFor="linkedin">LinkedIn</Label>
                            <Input
                                id="linkedin"
                                value={formData.linkedin}
                                onChange={(e) => setFormData(prev => ({ ...prev, linkedin: e.target.value }))}
                                placeholder="https://linkedin.com/in/usuario"
                            />
                        </div>
                        <div>
                            <Label htmlFor="github">GitHub</Label>
                            <Input
                                id="github"
                                value={formData.github}
                                onChange={(e) => setFormData(prev => ({ ...prev, github: e.target.value }))}
                                placeholder="https://github.com/usuario"
                            />
                        </div>
                        <div className="col-span-2">
                            <Label htmlFor="portfolio">Portfolio</Label>
                            <Input
                                id="portfolio"
                                value={formData.portfolio}
                                onChange={(e) => setFormData(prev => ({ ...prev, portfolio: e.target.value }))}
                                placeholder="https://miportfolio.com"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                            Cancelar
                        </Button>
                        <Button onClick={handleCreate} disabled={!formData.name || !formData.email || loading}>
                            {loading ? 'Creando...' : 'Crear Candidato'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Editar Candidato</DialogTitle>
                    </DialogHeader>
                    <div className="grid grid-cols-2 gap-4 py-4">
                        {/* Same form fields as create dialog */}
                        <div>
                            <Label htmlFor="edit-name">Nombre *</Label>
                            <Input
                                id="edit-name"
                                value={formData.name}
                                onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                                placeholder="Nombre completo"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-email">Email *</Label>
                            <Input
                                id="edit-email"
                                type="email"
                                value={formData.email}
                                onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
                                placeholder="email@ejemplo.com"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-phone">Teléfono</Label>
                            <Input
                                id="edit-phone"
                                value={formData.phone}
                                onChange={(e) => setFormData(prev => ({ ...prev, phone: e.target.value }))}
                                placeholder="+1 234 567 8900"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-location">Ubicación</Label>
                            <Input
                                id="edit-location"
                                value={formData.location}
                                onChange={(e) => setFormData(prev => ({ ...prev, location: e.target.value }))}
                                placeholder="Ciudad, País"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-experience_years">Años de Experiencia</Label>
                            <Input
                                id="edit-experience_years"
                                type="number"
                                value={formData.experience_years}
                                onChange={(e) => setFormData(prev => ({ ...prev, experience_years: e.target.value }))}
                                placeholder="5"
                            />
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
                            <Label htmlFor="edit-skills">Habilidades (separadas por comas)</Label>
                            <Textarea
                                id="edit-skills"
                                value={formData.skills}
                                onChange={(e) => setFormData(prev => ({ ...prev, skills: e.target.value }))}
                                placeholder="JavaScript, React, Node.js, Python"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-linkedin">LinkedIn</Label>
                            <Input
                                id="edit-linkedin"
                                value={formData.linkedin}
                                onChange={(e) => setFormData(prev => ({ ...prev, linkedin: e.target.value }))}
                                placeholder="https://linkedin.com/in/usuario"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-github">GitHub</Label>
                            <Input
                                id="edit-github"
                                value={formData.github}
                                onChange={(e) => setFormData(prev => ({ ...prev, github: e.target.value }))}
                                placeholder="https://github.com/usuario"
                            />
                        </div>
                        <div className="col-span-2">
                            <Label htmlFor="edit-portfolio">Portfolio</Label>
                            <Input
                                id="edit-portfolio"
                                value={formData.portfolio}
                                onChange={(e) => setFormData(prev => ({ ...prev, portfolio: e.target.value }))}
                                placeholder="https://miportfolio.com"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                            Cancelar
                        </Button>
                        <Button onClick={handleUpdate} disabled={!formData.name || !formData.email || loading}>
                            {loading ? 'Actualizando...' : 'Actualizar Candidato'}
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
                    <p>¿Estás seguro de que quieres eliminar al candidato <strong>{selectedCandidate?.name}</strong>?</p>
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
