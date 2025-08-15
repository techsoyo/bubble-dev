import React, { useState } from 'react';
import { Label } from '../ui/label';
import { Input } from '../ui/input';
import { Textarea } from '../ui/textarea';
import { Button } from '../ui/button';
import { Badge } from '../ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Separator } from '../ui/separator';
import { Plus, X, FileText } from 'lucide-react';

interface CVData {
  nombre?: string;
  email?: string;
  telefono?: string;
  ubicacion_actual?: string;
  fecha_nacimiento?: string;
  portfolio?: string;
  linkedin?: string;
  otras_redes?: string[];
  resumen_profesional?: string;
  soft_skills?: string[];
  hard_skills?: string[];
  idiomas?: Array<{ idioma: string; nivel: string }>;
  intereses?: string[];
  referencias?: string;
  disponibilidad?: string;
  puestos_anteriores?: Array<{
    puesto: string;
    empresa: string;
    fecha_inicio: string;
    fecha_fin: string;
    descripcion: string;
    responsabilidades?: string[];
  }>;
  educacion?: Array<{
    titulo: string;
    institucion: string;
    fecha_inicio: string;
    fecha_fin: string;
    descripcion?: string;
  }>;
  certificaciones?: string[];
  proyectos?: Array<{
    nombre: string;
    descripcion: string;
    tecnologias?: string[];
  }>;
}

interface ManualCVFormProps {
  onSubmit: (data: CVData) => void;
  initialData?: Partial<CVData>;
  isLoading?: boolean;
}

export function ManualCVForm({ onSubmit, initialData = {}, isLoading = false }: ManualCVFormProps) {
  const [formData, setFormData] = useState<CVData>({
    nombre: '',
    email: '',
    telefono: '',
    ubicacion_actual: '',
    fecha_nacimiento: '',
    portfolio: '',
    linkedin: '',
    otras_redes: [],
    resumen_profesional: '',
    soft_skills: [],
    hard_skills: [],
    idiomas: [],
    intereses: [],
    referencias: '',
    disponibilidad: '',
    puestos_anteriores: [],
    educacion: [],
    certificaciones: [],
    proyectos: [],
    ...initialData
  });

  const [errors, setErrors] = useState<{ [key: string]: string }>({});

  const handleFieldChange = (field: string, value: any) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
    // Limpiar error cuando el usuario empiece a escribir
    if (errors[field]) {
      setErrors(prev => ({
        ...prev,
        [field]: ''
      }));
    }
  };

  const handleArrayFieldChange = (field: string, index: number, value: any) => {
    setFormData(prev => ({
      ...prev,
      [field]: (prev[field as keyof CVData] as any[])?.map((item, i) =>
        i === index ? value : item
      ) || []
    }));
  };

  const addArrayItem = (field: string, emptyItem: any) => {
    setFormData(prev => ({
      ...prev,
      [field]: [...((prev[field as keyof CVData] as any[]) || []), emptyItem]
    }));
  };

  const removeArrayItem = (field: string, index: number) => {
    setFormData(prev => ({
      ...prev,
      [field]: (prev[field as keyof CVData] as any[])?.filter((_, i) => i !== index) || []
    }));
  };

  const addSkill = (skillType: 'hard_skills' | 'soft_skills', skill: string) => {
    if (skill.trim()) {
      addArrayItem(skillType, skill.trim());
    }
  };

  const validateForm = (): boolean => {
    const newErrors: { [key: string]: string } = {};

    if (!formData.nombre?.trim()) {
      newErrors.nombre = 'El nombre es requerido';
    }

    if (!formData.email?.trim()) {
      newErrors.email = 'El email es requerido';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      newErrors.email = 'El email no es válido';
    }

    if (!formData.telefono?.trim()) {
      newErrors.telefono = 'El teléfono es requerido';
    }

    if (!formData.ubicacion_actual?.trim()) {
      newErrors.ubicacion_actual = 'La ubicación es requerida';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (validateForm()) {
      onSubmit(formData);
    }
  };

  const renderSkillInput = (skillType: 'hard_skills' | 'soft_skills', label: string) => {
    const skills = formData[skillType] || [];
    const [newSkill, setNewSkill] = useState('');

    return (
      <div className="space-y-3">
        <Label className="text-sm font-medium">{label}</Label>

        {/* Skills existentes */}
        <div className="flex flex-wrap gap-2">
          {skills.map((skill, index) => (
            <Badge key={index} variant="secondary" className="px-2 py-1">
              {skill}
              <Button
                variant="ghost"
                size="sm"
                className="ml-1 h-4 w-4 p-0"
                onClick={() => removeArrayItem(skillType, index)}
              >
                <X className="h-3 w-3" />
              </Button>
            </Badge>
          ))}
        </div>

        {/* Input para nueva skill */}
        <div className="flex space-x-2">
          <Input
            placeholder={`Agregar ${label.toLowerCase()}`}
            value={newSkill}
            onChange={(e) => setNewSkill(e.target.value)}
            onKeyPress={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                addSkill(skillType, newSkill);
                setNewSkill('');
              }
            }}
          />
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => {
              addSkill(skillType, newSkill);
              setNewSkill('');
            }}
          >
            <Plus className="h-4 w-4" />
          </Button>
        </div>
      </div>
    );
  };

  const renderExperienceSection = () => {
    const puestos = formData.puestos_anteriores || [];

    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <Label className="text-sm font-medium">Experiencia Laboral</Label>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => addArrayItem('puestos_anteriores', {
              puesto: '',
              empresa: '',
              fecha_inicio: '',
              fecha_fin: '',
              descripcion: ''
            })}
          >
            <Plus className="h-4 w-4 mr-1" />
            Agregar Experiencia
          </Button>
        </div>

        {puestos.map((puesto, index) => (
          <Card key={index}>
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm">Experiencia {index + 1}</CardTitle>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => removeArrayItem('puestos_anteriores', index)}
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <Label className="text-xs">Puesto *</Label>
                  <Input
                    value={puesto.puesto}
                    onChange={(e) => handleArrayFieldChange('puestos_anteriores', index, {
                      ...puesto,
                      puesto: e.target.value
                    })}
                    placeholder="Ej: Desarrollador Frontend"
                  />
                </div>
                <div>
                  <Label className="text-xs">Empresa *</Label>
                  <Input
                    value={puesto.empresa}
                    onChange={(e) => handleArrayFieldChange('puestos_anteriores', index, {
                      ...puesto,
                      empresa: e.target.value
                    })}
                    placeholder="Ej: Tech Company S.A."
                  />
                </div>
                <div>
                  <Label className="text-xs">Fecha Inicio</Label>
                  <Input
                    type="date"
                    value={puesto.fecha_inicio}
                    onChange={(e) => handleArrayFieldChange('puestos_anteriores', index, {
                      ...puesto,
                      fecha_inicio: e.target.value
                    })}
                  />
                </div>
                <div>
                  <Label className="text-xs">Fecha Fin</Label>
                  <Input
                    type="date"
                    value={puesto.fecha_fin}
                    onChange={(e) => handleArrayFieldChange('puestos_anteriores', index, {
                      ...puesto,
                      fecha_fin: e.target.value
                    })}
                    placeholder="Dejar vacío si es actual"
                  />
                </div>
              </div>
              <div>
                <Label className="text-xs">Descripción del Puesto</Label>
                <Textarea
                  value={puesto.descripcion}
                  onChange={(e) => handleArrayFieldChange('puestos_anteriores', index, {
                    ...puesto,
                    descripcion: e.target.value
                  })}
                  placeholder="Describe tus responsabilidades y logros..."
                  rows={3}
                />
              </div>
            </CardContent>
          </Card>
        ))}

        {puestos.length === 0 && (
          <div className="text-center py-8 text-gray-500">
            <FileText className="h-8 w-8 mx-auto mb-2" />
            <p>No has agregado experiencia laboral aún</p>
            <p className="text-sm">Haz clic en "Agregar Experiencia" para comenzar</p>
          </div>
        )}
      </div>
    );
  };

  const renderEducationSection = () => {
    const educacion = formData.educacion || [];

    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <Label className="text-sm font-medium">Educación</Label>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => addArrayItem('educacion', {
              titulo: '',
              institucion: '',
              fecha_inicio: '',
              fecha_fin: '',
              descripcion: ''
            })}
          >
            <Plus className="h-4 w-4 mr-1" />
            Agregar Educación
          </Button>
        </div>

        {educacion.map((edu, index) => (
          <Card key={index}>
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm">Educación {index + 1}</CardTitle>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => removeArrayItem('educacion', index)}
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <Label className="text-xs">Título/Grado *</Label>
                  <Input
                    value={edu.titulo}
                    onChange={(e) => handleArrayFieldChange('educacion', index, {
                      ...edu,
                      titulo: e.target.value
                    })}
                    placeholder="Ej: Ingeniería en Sistemas"
                  />
                </div>
                <div>
                  <Label className="text-xs">Institución *</Label>
                  <Input
                    value={edu.institucion}
                    onChange={(e) => handleArrayFieldChange('educacion', index, {
                      ...edu,
                      institucion: e.target.value
                    })}
                    placeholder="Ej: Universidad Nacional"
                  />
                </div>
                <div>
                  <Label className="text-xs">Fecha Inicio</Label>
                  <Input
                    type="date"
                    value={edu.fecha_inicio}
                    onChange={(e) => handleArrayFieldChange('educacion', index, {
                      ...edu,
                      fecha_inicio: e.target.value
                    })}
                  />
                </div>
                <div>
                  <Label className="text-xs">Fecha Fin</Label>
                  <Input
                    type="date"
                    value={edu.fecha_fin}
                    onChange={(e) => handleArrayFieldChange('educacion', index, {
                      ...edu,
                      fecha_fin: e.target.value
                    })}
                  />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}

        {educacion.length === 0 && (
          <div className="text-center py-8 text-gray-500">
            <FileText className="h-8 w-8 mx-auto mb-2" />
            <p>No has agregado educación aún</p>
            <p className="text-sm">Haz clic en "Agregar Educación" para comenzar</p>
          </div>
        )}
      </div>
    );
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-8">
      {/* Información Personal */}
      <Card>
        <CardHeader>
          <CardTitle>Información Personal</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <Label htmlFor="nombre">Nombre Completo *</Label>
              <Input
                id="nombre"
                value={formData.nombre}
                onChange={(e) => handleFieldChange('nombre', e.target.value)}
                className={errors.nombre ? 'border-red-500' : ''}
              />
              {errors.nombre && <p className="text-sm text-red-500 mt-1">{errors.nombre}</p>}
            </div>

            <div>
              <Label htmlFor="email">Email *</Label>
              <Input
                id="email"
                type="email"
                value={formData.email}
                onChange={(e) => handleFieldChange('email', e.target.value)}
                className={errors.email ? 'border-red-500' : ''}
              />
              {errors.email && <p className="text-sm text-red-500 mt-1">{errors.email}</p>}
            </div>

            <div>
              <Label htmlFor="telefono">Teléfono *</Label>
              <Input
                id="telefono"
                type="tel"
                value={formData.telefono}
                onChange={(e) => handleFieldChange('telefono', e.target.value)}
                className={errors.telefono ? 'border-red-500' : ''}
              />
              {errors.telefono && <p className="text-sm text-red-500 mt-1">{errors.telefono}</p>}
            </div>

            <div>
              <Label htmlFor="ubicacion">Ubicación *</Label>
              <Input
                id="ubicacion"
                value={formData.ubicacion_actual}
                onChange={(e) => handleFieldChange('ubicacion_actual', e.target.value)}
                className={errors.ubicacion_actual ? 'border-red-500' : ''}
              />
              {errors.ubicacion_actual && <p className="text-sm text-red-500 mt-1">{errors.ubicacion_actual}</p>}
            </div>

            <div>
              <Label htmlFor="linkedin">LinkedIn</Label>
              <Input
                id="linkedin"
                value={formData.linkedin}
                onChange={(e) => handleFieldChange('linkedin', e.target.value)}
                placeholder="https://linkedin.com/in/tu-perfil"
              />
            </div>

            <div>
              <Label htmlFor="portfolio">Portfolio/Sitio Web</Label>
              <Input
                id="portfolio"
                value={formData.portfolio}
                onChange={(e) => handleFieldChange('portfolio', e.target.value)}
                placeholder="https://tu-portfolio.com"
              />
            </div>
          </div>

          <div>
            <Label htmlFor="resumen">Resumen Profesional</Label>
            <Textarea
              id="resumen"
              value={formData.resumen_profesional}
              onChange={(e) => handleFieldChange('resumen_profesional', e.target.value)}
              placeholder="Describe brevemente tu perfil profesional..."
              rows={4}
            />
          </div>
        </CardContent>
      </Card>

      {/* Habilidades */}
      <Card>
        <CardHeader>
          <CardTitle>Habilidades</CardTitle>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {renderSkillInput('hard_skills', 'Habilidades Técnicas')}
            {renderSkillInput('soft_skills', 'Habilidades Blandas')}
          </div>
        </CardContent>
      </Card>

      <Separator />

      {/* Experiencia Laboral */}
      <Card>
        <CardHeader>
          <CardTitle>Experiencia Laboral</CardTitle>
        </CardHeader>
        <CardContent>
          {renderExperienceSection()}
        </CardContent>
      </Card>

      <Separator />

      {/* Educación */}
      <Card>
        <CardHeader>
          <CardTitle>Educación</CardTitle>
        </CardHeader>
        <CardContent>
          {renderEducationSection()}
        </CardContent>
      </Card>

      {/* Botón Submit */}
      <div className="flex justify-end space-x-3 pt-6">
        <Button type="submit" disabled={isLoading} className="min-w-[120px]">
          {isLoading ? 'Guardando...' : 'Guardar Información'}
        </Button>
      </div>
    </form>
  );
}
