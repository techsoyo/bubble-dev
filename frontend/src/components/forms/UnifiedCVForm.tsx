import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '../ui/dialog';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Textarea } from '../ui/textarea';
import { Separator } from '../ui/separator';
import { CheckCircle, Edit, Save, X, Plus, Upload, FileText, Loader2 } from 'lucide-react';
import { useCvForm } from '../../contexts/CvFormContext';
import { CvFormData, validateMinimumData } from '../../domain/cvSchema';

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

interface UnifiedCVFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (validatedData: CvFormData) => void;
}

export function UnifiedCVForm({
  isOpen,
  onClose,
  onSave
}: UnifiedCVFormProps) {
  // Usar el contexto global del formulario CV
  const {
    state: cvFormState,
    updateField,
    setErrors,
    resetForm,
    switchToManual,
    setFile,
    setLoading
  } = useCvForm();

  const { state: formState, data: formData, errors, isLoading } = cvFormState;

  const [editingField, setEditingField] = useState<string | null>(null);

  const handleFieldEdit = (field: string, value: any) => {
    updateField(field as keyof CvFormData, value);
  };

  const handleArrayFieldEdit = (field: string, index: number, newValue: any) => {
    const currentArray = (formData[field as keyof CvFormData] as any[]) || [];
    const updatedArray = currentArray.map((item, i) => i === index ? newValue : item);
    updateField(field as keyof CvFormData, updatedArray);
  };

  const addArrayItem = (field: string, newItem: any) => {
    const currentArray = (formData[field as keyof CvFormData] as any[]) || [];
    updateField(field as keyof CvFormData, [...currentArray, newItem]);
  };

  const removeArrayItem = (field: string, index: number) => {
    const currentArray = (formData[field as keyof CvFormData] as any[]) || [];
    const updatedArray = currentArray.filter((_, i) => i !== index);
    updateField(field as keyof CvFormData, updatedArray);
  };

  const handleSave = () => {
    const validationErrors = validateMinimumData(formData);
    if (validationErrors.length > 0) {
      setErrors(validationErrors);
      return;
    }
    onSave(formData);
  };

  const renderEditableField = (label: string, field: string, value: any, type: 'text' | 'email' | 'tel' | 'date' | 'textarea' | 'url' = 'text') => {
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
          <div className="p-2 bg-gray-50 rounded text-xs min-h-[36px] flex items-center">
            {value || <span className="text-gray-400 italic">No especificado - Click editar</span>}
          </div>
        )}
      </div>
    );
  };

  const renderSkillsSection = (label: string, field: string, skills: string[] = []) => {
    return (
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <Label className="text-xs font-medium text-gray-700">{label}</Label>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => addArrayItem(field, '')}
          >
            <Plus className="h-3 w-3" />
          </Button>
        </div>

        <div className="space-y-1">
          {skills.map((skill, index) => (
            <div key={index} className="flex items-center space-x-2">
              <Input
                className="text-xs"
                value={skill}
                onChange={(e) => handleArrayFieldEdit(field, index, e.target.value)}
                placeholder={`${label} ${index + 1}`}
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
            Click + para agregar {label.toLowerCase()}
          </div>
        )}
      </div>
    );
  };

  const renderExperienceSection = () => {
    const puestos = formData.puestos_anteriores || [];

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
            <Plus className="h-3 w-3 mr-1" />
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
                  placeholder="Ej: Developer"
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
                  placeholder="Ej: TechCorp"
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
                  placeholder="Ej: Madrid"
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
                  disabled={puesto.actual}
                />
              </div>
              <div className="flex items-center space-x-2">
                <input
                  type="checkbox"
                  checked={puesto.actual || false}
                  onChange={(e) => handleArrayFieldEdit('puestos_anteriores', index, {
                    ...puesto,
                    actual: e.target.checked,
                    fecha_fin: e.target.checked ? '' : puesto.fecha_fin
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
                placeholder="Describe las responsabilidades y logros..."
              />
            </div>
          </div>
        ))}

        {puestos.length === 0 && (
          <div className="p-3 bg-gray-50 rounded text-xs text-gray-400 italic">
            Click + para agregar experiencia laboral
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
            <Plus className="h-3 w-3 mr-1" />
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
                  placeholder="Ej: Grado en Informática"
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
                  placeholder="Ej: Informática"
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
                  placeholder="Ej: Universidad Complutense"
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
                <Label className="text-xs">Nivel</Label>
                <Input
                  className="text-xs"
                  value={edu.nivel_educativo || ''}
                  onChange={(e) => handleArrayFieldEdit('educacion', index, {
                    ...edu,
                    nivel_educativo: e.target.value
                  })}
                  placeholder="Ej: Grado, Máster"
                />
              </div>
            </div>
          </div>
        ))}

        {educacion.length === 0 && (
          <div className="p-3 bg-gray-50 rounded text-xs text-gray-400 italic">
            Click + para agregar educación
          </div>
        )}
      </div>
    );
  };

  const getModalTitle = () => {
    switch (formState) {
      case 'ready':
        return (
          <>
            <CheckCircle className="h-5 w-5 text-green-500" />
            <span>Validar Datos Extraídos del CV</span>
          </>
        );
      case 'manual':
        return (
          <>
            <Edit className="h-5 w-5 text-blue-500" />
            <span>Completar Información Manualmente</span>
          </>
        );
      case 'uploading':
        return (
          <>
            <Upload className="h-5 w-5 text-blue-500" />
            <span>Subiendo Archivo</span>
          </>
        );
      case 'parsing':
        return (
          <>
            <Loader2 className="h-5 w-5 text-blue-500 animate-spin" />
            <span>Procesando con IA</span>
          </>
        );
      case 'saving':
        return (
          <>
            <Loader2 className="h-5 w-5 text-green-500 animate-spin" />
            <span>Guardando Información</span>
          </>
        );
      default:
        return (
          <>
            <FileText className="h-5 w-5 text-gray-500" />
            <span>Registro de CV</span>
          </>
        );
    }
  };

  const getModalDescription = () => {
    switch (formState) {
      case 'ready':
        return "La IA ha extraído la siguiente información de tu CV. Por favor, revisa y edita cualquier dato que necesite corrección antes de guardar.";
      case 'manual':
        return "No se pudo procesar automáticamente tu CV. Por favor, completa manualmente la información de tu perfil profesional.";
      case 'uploading':
        return "Tu archivo se está subiendo al servidor...";
      case 'parsing':
        return "Estamos procesando tu CV con inteligencia artificial para extraer la información...";
      case 'saving':
        return "Guardando tu información en el sistema...";
      case 'idle':
        return "Sube tu CV en PDF para procesarlo automáticamente o completa manualmente tu información.";
      default:
        return "Completa tu información profesional.";
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-5xl max-h-[95vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center space-x-2">
            {getModalTitle()}
          </DialogTitle>
          <DialogDescription>
            {getModalDescription()}
          </DialogDescription>
        </DialogHeader>

        {/* Mostrar errores si los hay */}
        {errors.length > 0 && (
          <div className="bg-red-50 border border-red-200 rounded-md p-3">
            <div className="flex">
              <X className="h-4 w-4 text-red-400 mt-0.5" />
              <div className="ml-2">
                <h4 className="text-sm font-medium text-red-800">
                  Se encontraron los siguientes errores:
                </h4>
                <ul className="mt-1 text-sm text-red-700 list-disc list-inside">
                  {errors.map((error, index) => (
                    <li key={index}>{error}</li>
                  ))}
                </ul>
              </div>
            </div>
          </div>
        )}

        {/* Componente de subida de archivo (solo si está en idle) */}
        {formState === 'idle' && (
          <div className="space-y-4">
            <h3 className="text-sm font-semibold border-b pb-2">Subir CV</h3>
            <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
              <Upload className="mx-auto h-8 w-8 text-gray-400" />
              <div className="mt-2">
                <label htmlFor="cv-upload" className="cursor-pointer">
                  <span className="text-sm text-blue-600 hover:text-blue-500">
                    Sube tu CV en PDF
                  </span>
                  <input
                    id="cv-upload"
                    type="file"
                    accept=".pdf"
                    className="hidden"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) {
                        setFile(file);
                      }
                    }}
                  />
                </label>
                <span className="text-sm text-gray-500"> o </span>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={switchToManual}
                >
                  Completar manualmente
                </Button>
              </div>
              <p className="text-xs text-gray-500 mt-2">
                Acepta archivos PDF hasta 10MB
              </p>
            </div>
          </div>
        )}

        {/* Estados de carga */}
        {(formState === 'uploading' || formState === 'parsing' || formState === 'saving') && (
          <div className="flex items-center justify-center p-8">
            <div className="text-center">
              <Loader2 className="mx-auto h-8 w-8 animate-spin text-blue-500" />
              <p className="mt-2 text-sm text-gray-600">
                {formState === 'uploading' && 'Subiendo archivo...'}
                {formState === 'parsing' && 'Procesando con IA...'}
                {formState === 'saving' && 'Guardando información...'}
              </p>
            </div>
          </div>
        )}

        {/* Formulario (solo si está en ready o manual) */}
        {(formState === 'ready' || formState === 'manual') && (
          <div className="space-y-6">
            {/* Información Personal */}
            <div className="space-y-4">
              <h3 className="text-sm font-semibold border-b pb-2">Información Personal</h3>

              <div className="grid grid-cols-3 gap-3">
                {renderEditableField('Nombre', 'nombre', formData.nombre)}
                {renderEditableField('Email', 'email', formData.email, 'email')}
                {renderEditableField('Teléfono', 'telefono', formData.telefono, 'tel')}
                {renderEditableField('Ubicación', 'ubicacion_actual', formData.ubicacion_actual)}
                {renderEditableField('Fecha Nacimiento', 'fecha_nacimiento', formData.fecha_nacimiento, 'date')}
                {renderEditableField('LinkedIn', 'linkedin', formData.linkedin, 'url')}
                {renderEditableField('Portfolio', 'portfolio', formData.portfolio, 'url')}
                {renderEditableField('Disponibilidad', 'disponibilidad', formData.disponibilidad)}
              </div>

              <div>
                {renderEditableField('Resumen Profesional', 'resumen_profesional', formData.resumen_profesional, 'textarea')}
              </div>
            </div>

            <Separator />

            {/* Habilidades */}
            <div className="space-y-4">
              <h3 className="text-sm font-semibold border-b pb-2">Habilidades</h3>

              <div className="grid grid-cols-3 gap-3">
                {renderSkillsSection('Hard Skills', 'hard_skills', formData.hard_skills)}
                {renderSkillsSection('Soft Skills', 'soft_skills', formData.soft_skills)}
                {renderSkillsSection('Habilidades Adicionales', 'habilidades_adicionales', formData.habilidades_adicionales)}
              </div>
            </div>

            <Separator />

            {/* Experiencia Laboral */}
            {renderExperienceSection()}

            <Separator />

            {/* Educación */}
            {renderEducationSection()}

            <Separator />

            {/* Información Adicional */}
            <div className="space-y-4">
              <h3 className="text-sm font-semibold border-b pb-2">Información Adicional</h3>

              <div className="grid grid-cols-3 gap-3">
                {renderSkillsSection('Certificaciones', 'certificaciones', formData.certificaciones)}
                {renderSkillsSection('Intereses', 'intereses', formData.intereses)}
                {renderSkillsSection('Otras Redes', 'otras_redes', formData.otras_redes)}
              </div>

              <div>
                {renderEditableField('Referencias', 'referencias', formData.referencias, 'textarea')}
              </div>
            </div>
          </div>
        )}

        <DialogFooter className="mt-6">
          <Button variant="outline" onClick={onClose} disabled={isLoading}>
            Cancelar
          </Button>
          {(formState === 'ready' || formState === 'manual') && (
            <Button onClick={handleSave} disabled={isLoading}>
              {isLoading ? 'Guardando...' : 'Guardar y Continuar'}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
