import React, { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '../ui/dialog';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Textarea } from '../ui/textarea';
import { Badge } from '../ui/badge';
import { Separator } from '../ui/separator';
import { CheckCircle, Edit, Save, X } from 'lucide-react';

interface CVData {
  // bt_candidates - Campos principales
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
  cv_original_file?: string;
  cv_text_file?: string;
  cv_json_file?: string;
  data_source?: 'ai_processing' | 'manual_entry' | 'hybrid';

  // bt_candidate_experiences
  puestos_anteriores?: Array<{
    puesto: string;
    empresa: string;
    fecha_inicio: string;
    fecha_fin: string;
    descripcion: string;
    responsabilidades?: string[];
    ubicacion?: string;
    actual?: boolean;
  }>;

  // bt_candidate_education
  educacion?: Array<{
    titulo: string;
    campo_estudio?: string;
    institucion: string;
    fecha_inicio: string;
    fecha_fin: string;
    nivel_educativo?: string;
    descripcion?: string;
  }>;

  // bt_candidate_certifications - Detalles expandidos
  certificaciones?: string[];
  certificaciones_detalle?: Array<{
    nombre_certificacion: string;
    emisor?: string;
    fecha_emision?: string;
    fecha_expiracion?: string;
  }>;

  // bt_candidate_languages - Detalles expandidos
  idiomas_detalle?: Array<{
    idioma: string;
    nivel_competencia?: string;
  }>;

  // bt_candidate_projects
  proyectos?: Array<{
    nombre: string;
    descripcion: string;
    tecnologias?: string[];
    fecha_inicio?: string;
    fecha_fin?: string;
    url?: string;
  }>;

  // bt_candidate_references - Detalles expandidos
  referencias_detalle?: Array<{
    nombre_referencia: string;
    empresa_referencia?: string;
    email_referencia?: string;
    telefono_referencia?: string;
    notas?: string;
  }>;

  // bt_candidate_skills - Habilidades adicionales
  habilidades_adicionales?: string[];

  // bt_candidate_routing - Información de enrutamiento
  routing?: {
    categoria_departamento_id?: number;
    departamento_id?: number;
    reclutador_id?: string;
    fuente?: 'ai' | 'manual';
    razon?: string;
    fecha_asignacion?: string;
  };
}

interface CVValidationModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (validatedData: CVData) => void;
  cvData: CVData;
  isLoading?: boolean;
}

export function CVValidationModal({
  isOpen,
  onClose,
  onSave,
  cvData,
  isLoading = false
}: CVValidationModalProps) {
  const [editedData, setEditedData] = useState<CVData>(cvData);
  const [editingField, setEditingField] = useState<string | null>(null);

  const handleFieldEdit = (field: string, value: any) => {
    setEditedData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleArrayFieldEdit = (field: string, index: number, newValue: any) => {
    setEditedData(prev => ({
      ...prev,
      [field]: (prev[field as keyof CVData] as any[])?.map((item, i) =>
        i === index ? newValue : item
      ) || []
    }));
  };

  const addArrayItem = (field: string, emptyItem: any) => {
    setEditedData(prev => ({
      ...prev,
      [field]: [...((prev[field as keyof CVData] as any[]) || []), emptyItem]
    }));
  };

  const removeArrayItem = (field: string, index: number) => {
    setEditedData(prev => ({
      ...prev,
      [field]: (prev[field as keyof CVData] as any[])?.filter((_, i) => i !== index) || []
    }));
  };

  const handleSave = () => {
    onSave(editedData);
  };

  const renderEditableField = (label: string, field: string, value: any, type: 'text' | 'email' | 'tel' | 'date' | 'textarea' = 'text') => {
    const isEditing = editingField === field;

    return (
      <div className="space-y-1">
        <div className="flex items-center justify-between">
          <Label className="text-xs font-medium text-gray-700">{label}</Label>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setEditingField(isEditing ? null : field)}
          >
            {isEditing ? <Save className="h-3 w-3" /> : <Edit className="h-3 w-3" />}
          </Button>
        </div>

        {isEditing ? (
          type === 'textarea' ? (
            <Textarea
              value={value || ''}
              onChange={(e) => handleFieldEdit(field, e.target.value)}
              className="min-h-[50px] text-xs"
            />
          ) : (
            <Input
              type={type}
              value={value || ''}
              onChange={(e) => handleFieldEdit(field, e.target.value)}
              className="text-xs"
            />
          )
        ) : (
          <div className="p-2 bg-gray-50 rounded text-xs">
            {value || <span className="text-gray-400 italic">No especificado</span>}
          </div>
        )}
      </div>
    );
  };

  const renderSkillsSection = (label: string, field: string, skills: string[] = []) => {
    return (
      <div className="space-y-1">
        <div className="flex items-center justify-between">
          <Label className="text-xs font-medium text-gray-700">{label}</Label>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => addArrayItem(field, '')}
          >
            <span className="text-xs">+</span>
          </Button>
        </div>

        <div className="space-y-1">
          {skills.map((skill, index) => (
            <div key={index} className="flex items-center space-x-1">
              <Input
                value={skill}
                onChange={(e) => handleArrayFieldEdit(field, index, e.target.value)}
                className="flex-1 text-xs"
              />
              <Button
                variant="ghost"
                size="sm"
                onClick={() => removeArrayItem(field, index)}
              >
                <X className="h-3 w-3" />
              </Button>
            </div>
          ))}
        </div>

        {skills.length === 0 && (
          <div className="p-2 bg-gray-50 rounded text-xs text-gray-400 italic">
            No hay {label.toLowerCase()}
          </div>
        )}
      </div>
    );
  };

  const renderExperienceSection = () => {
    const puestos = editedData.puestos_anteriores || [];

    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <Label className="text-sm font-medium text-gray-700">Experiencia Laboral</Label>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => addArrayItem('puestos_anteriores', {
              puesto: '',
              empresa: '',
              fecha_inicio: '',
              fecha_fin: '',
              descripcion: '',
              responsabilidades: [],
              ubicacion: '',
              actual: false
            })}
          >
            <span className="text-xs">Agregar</span>
          </Button>
        </div>

        {puestos.map((puesto, index) => (
          <div key={index} className="p-3 border rounded-lg space-y-2">
            <div className="flex justify-between items-start">
              <h4 className="text-xs font-medium">Puesto {index + 1}</h4>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => removeArrayItem('puestos_anteriores', index)}
              >
                <X className="h-3 w-3" />
              </Button>
            </div>

            <div className="grid grid-cols-3 gap-2">
              <div>
                <Label className="text-xs">Puesto</Label>
                <Input
                  className="text-xs"
                  value={puesto.puesto}
                  onChange={(e) => handleArrayFieldEdit('puestos_anteriores', index, {
                    ...puesto,
                    puesto: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Empresa</Label>
                <Input
                  className="text-xs"
                  value={puesto.empresa}
                  onChange={(e) => handleArrayFieldEdit('puestos_anteriores', index, {
                    ...puesto,
                    empresa: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Ubicación</Label>
                <Input
                  className="text-xs"
                  value={puesto.ubicacion || ''}
                  onChange={(e) => handleArrayFieldEdit('puestos_anteriores', index, {
                    ...puesto,
                    ubicacion: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Fecha Inicio</Label>
                <Input
                  className="text-xs"
                  type="date"
                  value={puesto.fecha_inicio}
                  onChange={(e) => handleArrayFieldEdit('puestos_anteriores', index, {
                    ...puesto,
                    fecha_inicio: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Fecha Fin</Label>
                <Input
                  className="text-xs"
                  type="date"
                  value={puesto.fecha_fin}
                  onChange={(e) => handleArrayFieldEdit('puestos_anteriores', index, {
                    ...puesto,
                    fecha_fin: e.target.value
                  })}
                />
              </div>
              <div className="flex items-center space-x-2">
                <input
                  type="checkbox"
                  checked={puesto.actual || false}
                  onChange={(e) => handleArrayFieldEdit('puestos_anteriores', index, {
                    ...puesto,
                    actual: e.target.checked
                  })}
                />
                <Label className="text-xs">Actual</Label>
              </div>
            </div>

            <div>
              <Label className="text-xs">Descripción</Label>
              <Textarea
                className="text-xs min-h-[50px]"
                value={puesto.descripcion}
                onChange={(e) => handleArrayFieldEdit('puestos_anteriores', index, {
                  ...puesto,
                  descripcion: e.target.value
                })}
              />
            </div>
          </div>
        ))}

        {puestos.length === 0 && (
          <div className="p-3 bg-gray-50 rounded text-xs text-gray-400 italic">
            No se encontró experiencia laboral
          </div>
        )}
      </div>
    );
  };

  const renderEducationSection = () => {
    const educacion = editedData.educacion || [];

    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <Label className="text-sm font-medium text-gray-700">Educación</Label>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => addArrayItem('educacion', {
              titulo: '',
              campo_estudio: '',
              institucion: '',
              fecha_inicio: '',
              fecha_fin: '',
              nivel_educativo: '',
              descripcion: ''
            })}
          >
            <span className="text-xs">Agregar</span>
          </Button>
        </div>

        {educacion.map((edu, index) => (
          <div key={index} className="p-3 border rounded-lg space-y-2">
            <div className="flex justify-between items-start">
              <h4 className="text-xs font-medium">Educación {index + 1}</h4>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => removeArrayItem('educacion', index)}
              >
                <X className="h-3 w-3" />
              </Button>
            </div>

            <div className="grid grid-cols-3 gap-2">
              <div>
                <Label className="text-xs">Título</Label>
                <Input
                  className="text-xs"
                  value={edu.titulo}
                  onChange={(e) => handleArrayFieldEdit('educacion', index, {
                    ...edu,
                    titulo: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Campo de Estudio</Label>
                <Input
                  className="text-xs"
                  value={edu.campo_estudio || ''}
                  onChange={(e) => handleArrayFieldEdit('educacion', index, {
                    ...edu,
                    campo_estudio: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Institución</Label>
                <Input
                  className="text-xs"
                  value={edu.institucion}
                  onChange={(e) => handleArrayFieldEdit('educacion', index, {
                    ...edu,
                    institucion: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Fecha Inicio</Label>
                <Input
                  className="text-xs"
                  type="date"
                  value={edu.fecha_inicio}
                  onChange={(e) => handleArrayFieldEdit('educacion', index, {
                    ...edu,
                    fecha_inicio: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Fecha Fin</Label>
                <Input
                  className="text-xs"
                  type="date"
                  value={edu.fecha_fin}
                  onChange={(e) => handleArrayFieldEdit('educacion', index, {
                    ...edu,
                    fecha_fin: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Nivel Educativo</Label>
                <Input
                  className="text-xs"
                  value={edu.nivel_educativo || ''}
                  onChange={(e) => handleArrayFieldEdit('educacion', index, {
                    ...edu,
                    nivel_educativo: e.target.value
                  })}
                />
              </div>
            </div>

            {edu.descripcion !== undefined && (
              <div>
                <Label className="text-xs">Descripción</Label>
                <Textarea
                  className="text-xs min-h-[40px]"
                  value={edu.descripcion || ''}
                  onChange={(e) => handleArrayFieldEdit('educacion', index, {
                    ...edu,
                    descripcion: e.target.value
                  })}
                />
              </div>
            )}
          </div>
        ))}

        {educacion.length === 0 && (
          <div className="p-3 bg-gray-50 rounded text-xs text-gray-400 italic">
            No se encontró información educativa
          </div>
        )}
      </div>
    );
  };

  const renderCertificationsDetailSection = () => {
    const certificaciones = editedData.certificaciones_detalle || [];

    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <Label className="text-xs font-medium text-gray-700">Certificaciones Detalladas</Label>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => addArrayItem('certificaciones_detalle', {
              nombre_certificacion: '',
              emisor: '',
              fecha_emision: '',
              fecha_expiracion: ''
            })}
          >
            Agregar
          </Button>
        </div>

        {certificaciones.map((cert, index) => (
          <div key={index} className="p-3 border rounded-lg space-y-2">
            <div className="flex justify-between items-start">
              <h5 className="text-xs font-medium">Certificación {index + 1}</h5>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => removeArrayItem('certificaciones_detalle', index)}
              >
                <X className="h-3 w-3" />
              </Button>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div>
                <Label className="text-xs">Nombre</Label>
                <Input
                  className="text-xs"
                  value={cert.nombre_certificacion}
                  onChange={(e) => handleArrayFieldEdit('certificaciones_detalle', index, {
                    ...cert,
                    nombre_certificacion: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Emisor</Label>
                <Input
                  className="text-xs"
                  value={cert.emisor}
                  onChange={(e) => handleArrayFieldEdit('certificaciones_detalle', index, {
                    ...cert,
                    emisor: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Fecha Emisión</Label>
                <Input
                  className="text-xs"
                  type="date"
                  value={cert.fecha_emision}
                  onChange={(e) => handleArrayFieldEdit('certificaciones_detalle', index, {
                    ...cert,
                    fecha_emision: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Fecha Expiración</Label>
                <Input
                  className="text-xs"
                  type="date"
                  value={cert.fecha_expiracion}
                  onChange={(e) => handleArrayFieldEdit('certificaciones_detalle', index, {
                    ...cert,
                    fecha_expiracion: e.target.value
                  })}
                />
              </div>
            </div>
          </div>
        ))}

        {certificaciones.length === 0 && (
          <div className="p-3 bg-gray-50 rounded text-xs text-gray-400 italic">
            No hay certificaciones detalladas
          </div>
        )}
      </div>
    );
  };

  const renderLanguagesDetailSection = () => {
    const idiomas = editedData.idiomas_detalle || [];

    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <Label className="text-xs font-medium text-gray-700">Idiomas</Label>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => addArrayItem('idiomas_detalle', {
              idioma: '',
              nivel_competencia: ''
            })}
          >
            Agregar
          </Button>
        </div>

        {idiomas.map((idioma, index) => (
          <div key={index} className="p-3 border rounded-lg space-y-2">
            <div className="flex justify-between items-start">
              <h5 className="text-xs font-medium">Idioma {index + 1}</h5>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => removeArrayItem('idiomas_detalle', index)}
              >
                <X className="h-3 w-3" />
              </Button>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div>
                <Label className="text-xs">Idioma</Label>
                <Input
                  className="text-xs"
                  value={idioma.idioma}
                  onChange={(e) => handleArrayFieldEdit('idiomas_detalle', index, {
                    ...idioma,
                    idioma: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Nivel</Label>
                <Input
                  className="text-xs"
                  value={idioma.nivel_competencia}
                  onChange={(e) => handleArrayFieldEdit('idiomas_detalle', index, {
                    ...idioma,
                    nivel_competencia: e.target.value
                  })}
                />
              </div>
            </div>
          </div>
        ))}

        {idiomas.length === 0 && (
          <div className="p-3 bg-gray-50 rounded text-xs text-gray-400 italic">
            No hay idiomas especificados
          </div>
        )}
      </div>
    );
  };

  const renderProjectsSection = () => {
    const proyectos = editedData.proyectos || [];

    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <Label className="text-xs font-medium text-gray-700">Proyectos</Label>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => addArrayItem('proyectos', {
              nombre: '',
              descripcion: '',
              tecnologias: [],
              fecha_inicio: '',
              fecha_fin: '',
              url: ''
            })}
          >
            Agregar
          </Button>
        </div>

        {proyectos.map((proyecto, index) => (
          <div key={index} className="p-3 border rounded-lg space-y-2">
            <div className="flex justify-between items-start">
              <h5 className="text-xs font-medium">Proyecto {index + 1}</h5>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => removeArrayItem('proyectos', index)}
              >
                <X className="h-3 w-3" />
              </Button>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div>
                <Label className="text-xs">Nombre</Label>
                <Input
                  className="text-xs"
                  value={proyecto.nombre}
                  onChange={(e) => handleArrayFieldEdit('proyectos', index, {
                    ...proyecto,
                    nombre: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">URL</Label>
                <Input
                  className="text-xs"
                  value={proyecto.url}
                  onChange={(e) => handleArrayFieldEdit('proyectos', index, {
                    ...proyecto,
                    url: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Fecha Inicio</Label>
                <Input
                  className="text-xs"
                  type="date"
                  value={proyecto.fecha_inicio}
                  onChange={(e) => handleArrayFieldEdit('proyectos', index, {
                    ...proyecto,
                    fecha_inicio: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Fecha Fin</Label>
                <Input
                  className="text-xs"
                  type="date"
                  value={proyecto.fecha_fin}
                  onChange={(e) => handleArrayFieldEdit('proyectos', index, {
                    ...proyecto,
                    fecha_fin: e.target.value
                  })}
                />
              </div>
            </div>

            <div>
              <Label className="text-xs">Descripción</Label>
              <Textarea
                className="text-xs min-h-[50px]"
                value={proyecto.descripcion}
                onChange={(e) => handleArrayFieldEdit('proyectos', index, {
                  ...proyecto,
                  descripcion: e.target.value
                })}
              />
            </div>
          </div>
        ))}

        {proyectos.length === 0 && (
          <div className="p-3 bg-gray-50 rounded text-xs text-gray-400 italic">
            No hay proyectos registrados
          </div>
        )}
      </div>
    );
  };

  const renderReferencesSection = () => {
    const referencias = editedData.referencias_detalle || [];

    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <Label className="text-xs font-medium text-gray-700">Referencias</Label>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => addArrayItem('referencias_detalle', {
              nombre_referencia: '',
              empresa_referencia: '',
              email_referencia: '',
              telefono_referencia: '',
              notas: ''
            })}
          >
            Agregar
          </Button>
        </div>

        {referencias.map((ref, index) => (
          <div key={index} className="p-3 border rounded-lg space-y-2">
            <div className="flex justify-between items-start">
              <h5 className="text-xs font-medium">Referencia {index + 1}</h5>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => removeArrayItem('referencias_detalle', index)}
              >
                <X className="h-3 w-3" />
              </Button>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div>
                <Label className="text-xs">Nombre</Label>
                <Input
                  className="text-xs"
                  value={ref.nombre_referencia}
                  onChange={(e) => handleArrayFieldEdit('referencias_detalle', index, {
                    ...ref,
                    nombre_referencia: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Empresa</Label>
                <Input
                  className="text-xs"
                  value={ref.empresa_referencia}
                  onChange={(e) => handleArrayFieldEdit('referencias_detalle', index, {
                    ...ref,
                    empresa_referencia: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Email</Label>
                <Input
                  className="text-xs"
                  type="email"
                  value={ref.email_referencia}
                  onChange={(e) => handleArrayFieldEdit('referencias_detalle', index, {
                    ...ref,
                    email_referencia: e.target.value
                  })}
                />
              </div>
              <div>
                <Label className="text-xs">Teléfono</Label>
                <Input
                  className="text-xs"
                  value={ref.telefono_referencia}
                  onChange={(e) => handleArrayFieldEdit('referencias_detalle', index, {
                    ...ref,
                    telefono_referencia: e.target.value
                  })}
                />
              </div>
            </div>

            <div>
              <Label className="text-xs">Notas</Label>
              <Textarea
                className="text-xs min-h-[50px]"
                value={ref.notas}
                onChange={(e) => handleArrayFieldEdit('referencias_detalle', index, {
                  ...ref,
                  notas: e.target.value
                })}
              />
            </div>
          </div>
        ))}

        {referencias.length === 0 && (
          <div className="p-3 bg-gray-50 rounded text-xs text-gray-400 italic">
            No hay referencias registradas
          </div>
        )}
      </div>
    );
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center space-x-2">
            <CheckCircle className="h-5 w-5 text-green-500" />
            <span>Validar Datos Extraídos del CV</span>
          </DialogTitle>
          <DialogDescription>
            La IA ha extraído la siguiente información de tu CV. Por favor, revisa y edita cualquier dato que necesite corrección antes de guardar.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-6">
          {/* Información Personal */}
          <div className="space-y-4">
            <h3 className="text-sm font-semibold border-b pb-2">Información Personal</h3>

            <div className="grid grid-cols-3 gap-3">
              {renderEditableField('Nombre', 'nombre', editedData.nombre)}
              {renderEditableField('Email', 'email', editedData.email, 'email')}
              {renderEditableField('Teléfono', 'telefono', editedData.telefono, 'tel')}
              {renderEditableField('Ubicación', 'ubicacion_actual', editedData.ubicacion_actual)}
              {renderEditableField('Fecha Nacimiento', 'fecha_nacimiento', editedData.fecha_nacimiento, 'date')}
              {renderEditableField('LinkedIn', 'linkedin', editedData.linkedin)}
              {renderEditableField('Portfolio', 'portfolio', editedData.portfolio)}
              {renderEditableField('Disponibilidad', 'disponibilidad', editedData.disponibilidad)}
            </div>

            <div>
              {renderEditableField('Resumen Profesional', 'resumen_profesional', editedData.resumen_profesional, 'textarea')}
            </div>
          </div>

          <Separator />

          {/* Habilidades */}
          <div className="space-y-4">
            <h3 className="text-sm font-semibold border-b pb-2">Habilidades</h3>

            <div className="grid grid-cols-3 gap-3">
              {renderSkillsSection('Hard Skills', 'hard_skills', editedData.hard_skills)}
              {renderSkillsSection('Soft Skills', 'soft_skills', editedData.soft_skills)}
              {renderSkillsSection('Habilidades Adicionales', 'habilidades_adicionales', editedData.habilidades_adicionales)}
            </div>
          </div>

          <Separator />

          {/* Experiencia Laboral */}
          {renderExperienceSection()}

          <Separator />

          {/* Educación */}
          {renderEducationSection()}

          <Separator />

          {/* Certificaciones */}
          <div className="space-y-4">
            <h3 className="text-sm font-semibold border-b pb-2">Certificaciones</h3>
            <div className="grid grid-cols-2 gap-4">
              <div>
                {renderSkillsSection('Certificaciones (Lista)', 'certificaciones', editedData.certificaciones)}
              </div>
              <div>
                {renderCertificationsDetailSection()}
              </div>
            </div>
          </div>

          <Separator />

          {/* Idiomas */}
          <div className="space-y-4">
            <h3 className="text-sm font-semibold border-b pb-2">Idiomas</h3>
            {renderLanguagesDetailSection()}
          </div>

          <Separator />

          {/* Proyectos */}
          <div className="space-y-4">
            <h3 className="text-sm font-semibold border-b pb-2">Proyectos</h3>
            {renderProjectsSection()}
          </div>

          <Separator />

          {/* Referencias */}
          <div className="space-y-4">
            <h3 className="text-sm font-semibold border-b pb-2">Referencias</h3>
            <div className="grid grid-cols-2 gap-4">
              <div>
                {renderEditableField('Referencias Generales', 'referencias', editedData.referencias, 'textarea')}
              </div>
              <div>
                {renderReferencesSection()}
              </div>
            </div>
          </div>

          <Separator />

          {/* Información Adicional */}
          <div className="space-y-4">
            <h3 className="text-sm font-semibold border-b pb-2">Información Adicional</h3>

            <div className="grid grid-cols-2 gap-4">
              {renderSkillsSection('Intereses', 'intereses', editedData.intereses)}
              {renderSkillsSection('Otras Redes', 'otras_redes', editedData.otras_redes)}
            </div>

            <div className="grid grid-cols-3 gap-3">
              {renderEditableField('Archivo CV Original', 'cv_original_file', editedData.cv_original_file)}
              {renderEditableField('Archivo Texto', 'cv_text_file', editedData.cv_text_file)}
              {renderEditableField('Archivo JSON', 'cv_json_file', editedData.cv_json_file)}
            </div>

            {/* Routing Information */}
            {editedData.routing && (
              <div className="p-3 bg-blue-50 rounded-lg">
                <h4 className="text-xs font-medium mb-2">Información de Enrutamiento</h4>
                <div className="grid grid-cols-2 gap-2 text-xs">
                  <div>Departamento ID: {editedData.routing.departamento_id || 'N/A'}</div>
                  <div>Categoría ID: {editedData.routing.categoria_departamento_id || 'N/A'}</div>
                  <div>Reclutador ID: {editedData.routing.reclutador_id || 'N/A'}</div>
                  <div>Fuente: {editedData.routing.fuente || 'N/A'}</div>
                  <div className="col-span-2">Razón: {editedData.routing.razon || 'N/A'}</div>
                </div>
              </div>
            )}
          </div>
        </div>

        <DialogFooter className="mt-6">
          <Button variant="outline" onClick={onClose} disabled={isLoading}>
            Cancelar
          </Button>
          <Button onClick={handleSave} disabled={isLoading}>
            {isLoading ? 'Guardando...' : 'Guardar y Continuar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
