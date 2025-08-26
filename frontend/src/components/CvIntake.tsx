import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { safeGet, safeSet, safeRemove } from '../utils/safeStorage';
import { env } from '../config/env';
import { parseCv, confirmCv } from '../lib/cvApi';
import { useCvForm } from '../contexts/CvFormContext';
import { CvFormData, cvTemplate, validateMinimumData, ExperienciaLaboral, Educacion, Proyecto } from '../domain/cvSchema';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from './ui/dialog';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Textarea } from './ui/textarea';
import { Label } from './ui/label';
import { Separator } from './ui/separator';
import { Upload, Loader2, AlertTriangle, CheckCircle2, Plus, X, Info } from 'lucide-react';

// Tipo para la respuesta del backend
type IntakeResponse = {
  success: boolean;
  data: any;
  meta?: { mode?: string; reason?: string };
  error?: { code?: string; message?: string };
};

/**
 * CvIntake
 * Componente orquestador: subida PDF -> parse AI -> pre-relleno -> edición manual -> confirmación.
 * Requisitos cubiertos:
 * - Uploader (PDF) + botón "Procesar CV".
 * - Botón "Cargar manualmente" forza estado manual.
 * - Modal (CvReviewModal) reutiliza estado global (context) y muestra formulario único.
 * - Manejo de 200 success (prefill) / 422 PARSE_FAILED (cambio a manual con alerta) / otros errores.
 * - Validación básica cliente (email, fechas coherentes inicio <= fin, mínimos).
 * - Confirmación POST JSON a /api/cv/confirm (placeholder backend si no existe aún).
 * - Cancelar guarda borrador (localStorage) y cierra.
 */

interface CvIntakeProps {
  apiBase?: string;              // Base URL backend (por defecto /api)
  onConfirmed?: (data: CvFormData) => void; // Callback tras confirmación exitosa
  storageKey?: string;           // Clave localStorage para borrador
}

// Clave de autosave solicitada
const DRAFT_KEY_DEFAULT = 'cv-intake-draft-v1';

// --- Validación extendida (fechas coherentes) ---
function extraValidate(data: CvFormData): string[] {
  const errs: string[] = [];
  // Experiencias
  data.puestos_anteriores?.forEach((p, idx) => {
    if (p.fecha_inicio && p.fecha_fin && p.fecha_inicio > p.fecha_fin) {
      errs.push(`Experiencia #${idx + 1}: fecha_inicio posterior a fecha_fin`);
    }
  });
  // Educación
  data.educacion?.forEach((e, idx) => {
    if (e.fecha_inicio && e.fecha_fin && e.fecha_inicio > e.fecha_fin) {
      errs.push(`Educación #${idx + 1}: fecha_inicio posterior a fecha_fin`);
    }
  });
  return errs;
}

export const CvIntake: React.FC<CvIntakeProps> = ({ apiBase = env.API_BASE_URL, onConfirmed, storageKey = DRAFT_KEY_DEFAULT }) => {
  const { state, setFile, setFormState, prefillFromAI, switchToManual, setErrors, setFormData, updateField } = useCvForm();
  const { state: flowState, data: formData, errors, originalFile, isLoading } = state;

  const [localFile, setLocalFile] = useState<File | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [alertMsg, setAlertMsg] = useState<string | null>(null);
  const [globalError, setGlobalError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const manualOnly = (import.meta as any).env?.VITE_CV_ALLOW_MANUAL_ONLY === 'true';

  // Forzar modo manual si flag activo en montaje y no hay borrador cargado aún
  useEffect(() => {
    if (manualOnly && flowState === 'idle') {
      switchToManual();
      setModalOpen(true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [manualOnly]);

  // Cargar borrador si existe al montar (sin sobrescribir si ya hay datos AI)
  useEffect(() => {
    if (flowState === 'idle') {
      try {
        const draft = safeGet<CvFormData>(storageKey, cvTemplate);
        if (draft && Object.keys(draft).length > Object.keys(cvTemplate).length) {
          setFormData({ ...cvTemplate, ...draft });
          setFormState('manual');
          setModalOpen(true);
        }
      } catch { }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Selección archivo
  const handleFileChange = (file: File | undefined) => {
    if (!file) return;
    setLocalFile(file);
  };

  const processFile = async () => {
    if (!localFile) return;
    setErrors([]);
    setGlobalError(null);
    setAlertMsg(null);
    setFormState('uploading');
    // Guardamos en contexto también (opcional)
    setFile(localFile);

    // Validaciones rápidas cliente
    if (localFile.type !== 'application/pdf') {
      setFormState('idle');
      setGlobalError('Solo se aceptan archivos PDF (application/pdf)');
      return;
    }
    if (localFile.size > 5 * 1024 * 1024) {
      setFormState('idle');
      setGlobalError('El archivo excede 5MB');
      return;
    }

    try {
      console.log('[CvIntake] Iniciando parseo CV', { name: localFile.name, size: localFile.size, type: localFile.type });
      setFormState('parsing');
      const { status, json: rawPayload } = await parseCv(localFile);
      const payload = rawPayload as IntakeResponse;
      console.log('[CvIntake] Respuesta parse', { status, payload });

      if (status === 200 && payload?.success) {
        // Verificar si el backend indica modo manual
        if (payload?.meta?.mode === 'manual') {
          switchToManual();
          const reason = payload?.meta?.reason;
          let message = 'El PDF no fue legible. Completa la información manualmente.';

          if (reason === 'NO_TEXT_EXTRACTED') {
            message = 'No se pudo extraer texto del PDF. Completa la información manualmente.';
          } else if (reason === 'IA_UNAVAILABLE') {
            message = 'El servicio de IA no está disponible. Completa la información manualmente.';
          }

          setAlertMsg(message);
          setModalOpen(true);
          return;
        }

        // Modo IA exitoso
        prefillFromAI(payload.data || {});
        setModalOpen(true);
        return;
      }
      if (status === 422 && payload?.error?.code === 'PARSE_FAILED') {
        switchToManual();
        setAlertMsg('El PDF no fue legible. Completa la información manualmente.');
        setModalOpen(true);
        return;
      }
      // Otros errores
      setFormState('idle');
      setGlobalError(payload?.error?.message || 'Error inesperado procesando el CV');
    } catch (e) {
      console.error('[CvIntake] Error de red/fetch parse', e);
      setFormState('idle');
      setGlobalError('Fallo de red o servidor no disponible');
    }
  };

  const openManual = () => {
    switchToManual();
    setModalOpen(true);
  };

  const retryAnotherPDF = () => {
    // Reset a estado inicial para permitir nuevo parseo
    setErrors([]);
    setGlobalError(null);
    setAlertMsg(null);
    setFormData(cvTemplate);
    safeRemove(storageKey);
    setModalOpen(false);
    setLocalFile(null);
    setFormState('idle');
  };

  const closeModal = () => {
    // Guardar borrador automático al cerrar (solo en desarrollo)
    safeSet(storageKey, formData);
    setModalOpen(false);
  };

  const clearDraft = () => {
    safeRemove(storageKey);
  };

  const handleConfirm = async () => {
    setErrors([]);
    setGlobalError(null);

    const minErrors = validateMinimumData(formData);
    const extra = extraValidate(formData);
    const combined = [...minErrors, ...extra];
    if (combined.length) { setErrors(combined); return; }

    setSaving(true);
    setFormState('saving');
    try {
      const { status, json: rawPayload } = await confirmCv(formData);
      const payload = rawPayload as IntakeResponse;
      if (status >= 200 && status < 300 && payload?.success !== false) {
        clearDraft();
        setModalOpen(false);
        if (onConfirmed) onConfirmed(formData);
      } else {
        setGlobalError(payload?.error?.message || 'Error al confirmar el CV');
        setFormState('manual'); // vuelve para seguir editando
      }
    } catch (e) {
      setGlobalError('Error de red confirmando CV');
      setFormState('manual');
    } finally {
      setSaving(false);
    }
  };

  // Autosave continuo cuando se edita (manual o ready) y modal abierto
  useEffect(() => {
    if (modalOpen && (flowState === 'manual' || flowState === 'ready')) {
      safeSet(storageKey, formData);
    }
  }, [formData, modalOpen, flowState, storageKey]);

  // --- Formulario dinámico ---
  const updateSimpleField = useCallback((field: keyof CvFormData, value: string | number | boolean | string[] | undefined) => {
    updateField(field, value);
  }, [updateField]);

  const arrayHelpersString = useCallback((field: keyof CvFormData) => {
    const arr = (formData[field] as string[]) || [];
    return {
      items: arr,
      add: (item: string) => {
        updateField(field, [...arr, item]);
      },
      remove: (idx: number) => updateField(field, arr.filter((_, i) => i !== idx)),
      update: (idx: number, item: string) => updateField(field, arr.map((v, i) => i === idx ? item : v))
    };
  }, [formData, updateField]);

  const arrayHelpersExp = useCallback((field: keyof CvFormData) => {
    const arr = (formData[field] as ExperienciaLaboral[]) || [];
    return {
      items: arr,
      add: (item: ExperienciaLaboral) => {
        updateField(field, [...arr, item]);
      },
      remove: (idx: number) => updateField(field, arr.filter((_, i) => i !== idx)),
      update: (idx: number, item: ExperienciaLaboral) => updateField(field, arr.map((v, i) => i === idx ? item : v))
    };
  }, [formData, updateField]);

  const arrayHelpersEdu = useCallback((field: keyof CvFormData) => {
    const arr = (formData[field] as Educacion[]) || [];
    return {
      items: arr,
      add: (item: Educacion) => updateField(field, [...arr, item]),
      remove: (idx: number) => updateField(field, arr.filter((_, i) => i !== idx)),
      update: (idx: number, item: Educacion) => updateField(field, arr.map((v, i) => i === idx ? item : v))
    };
  }, [formData, updateField]);

  const arrayHelpersProj = useCallback((field: keyof CvFormData) => {
    const arr = (formData[field] as Proyecto[]) || [];
    return {
      items: arr,
      add: (item: Proyecto) => updateField(field, [...arr, item]),
      remove: (idx: number) => updateField(field, arr.filter((_, i) => i !== idx)),
      update: (idx: number, item: Proyecto) => updateField(field, arr.map((v, i) => i === idx ? item : v))
    };
  }, [formData, updateField]);

  const exp = useMemo(() => arrayHelpersExp('puestos_anteriores'), [arrayHelpersExp]);
  const edu = useMemo(() => arrayHelpersEdu('educacion'), [arrayHelpersEdu]);
  const cert = useMemo(() => arrayHelpersString('certificaciones'), [arrayHelpersString]);
  const proj = useMemo(() => arrayHelpersProj('proyectos'), [arrayHelpersProj]);

  // Render helpers
  const renderBasicInput = (label: string, field: keyof CvFormData, type: string = 'text') => (
    <div className="space-y-1">
      <Label className="text-xs font-medium text-gray-700">{label}</Label>
      <Input
        type={type}
        className="text-xs"
        value={(formData[field] as string) || ''}
        onChange={(e) => updateSimpleField(field, e.target.value)}
      />
    </div>
  );

  const renderExperiences = () => (
    <div className="space-y-3" role="group" aria-labelledby="exp-label">
      <div className="flex items-center justify-between">
        <h4 id="exp-label" className="text-xs font-semibold flex items-center gap-1">Experiencia Laboral
          <span className="inline-flex items-center" aria-hidden="true" title="Añade puestos anteriores: título, empresa, fechas y breve descripción." aria-label="Ayuda Experiencia Laboral">
            <Info className="h-3 w-3 text-gray-400" />
          </span>
        </h4>
        <Button size="sm" variant="ghost" onClick={(e) => {
          e.preventDefault();
          console.log('➕ Añadiendo experiencia');
          exp.add({ puesto: '', empresa: '', fecha_inicio: '', fecha_fin: '', descripcion: '' });
        }}><Plus className="h-3 w-3" /></Button>
      </div>
      <div role="list" aria-label="Lista de experiencias">
        {exp.items.map((p, i) => (
          <div key={i} role="listitem" className="p-2 border rounded space-y-2">
            <div className="flex justify-between items-center">
              <span className="text-xs font-medium">Puesto #{i + 1}</span>
              <Button size="sm" variant="ghost" onClick={() => exp.remove(i)}><X className="h-3 w-3" /></Button>
            </div>
            <div className="grid grid-cols-3 gap-2">
              <Input placeholder="Puesto" className="text-xs" value={p.puesto} onChange={e => exp.update(i, { ...p, puesto: e.target.value })} />
              <Input placeholder="Empresa" className="text-xs" value={p.empresa} onChange={e => exp.update(i, { ...p, empresa: e.target.value })} />
              <Input type="date" className="text-xs" value={p.fecha_inicio} onChange={e => exp.update(i, { ...p, fecha_inicio: e.target.value })} />
              <Input type="date" className="text-xs" value={p.fecha_fin} onChange={e => exp.update(i, { ...p, fecha_fin: e.target.value })} />
              <Textarea placeholder="Descripción" className="col-span-3 text-xs min-h-[60px]" value={p.descripcion} onChange={e => exp.update(i, { ...p, descripcion: e.target.value })} />
            </div>
          </div>
        ))}
        {exp.items.length === 0 && <div className="text-xs text-gray-400 italic" role="note">Sin experiencias. Usa + para añadir.</div>}
      </div>
    </div>
  );

  const renderEducation = () => (
    <div className="space-y-3" role="group" aria-labelledby="edu-label">
      <div className="flex items-center justify-between">
        <h4 id="edu-label" className="text-xs font-semibold flex items-center gap-1">Educación
          <span className="inline-flex" aria-hidden="true" title="Añade formaciones: título, institución, fechas y descripción." aria-label="Ayuda Educación">
            <Info className="h-3 w-3 text-gray-400" />
          </span>
        </h4>
        <Button size="sm" variant="ghost" onClick={(e) => {
          e.preventDefault();
          console.log('➕ Añadiendo educación');
          edu.add({ titulo: '', institucion: '', fecha_inicio: '', fecha_fin: '' });
        }}><Plus className="h-3 w-3" /></Button>
      </div>
      <div role="list" aria-label="Lista de educación">
        {edu.items.map((e, i) => (
          <div key={i} role="listitem" className="p-2 border rounded space-y-2">
            <div className="flex justify-between items-center">
              <span className="text-xs font-medium">Educación #{i + 1}</span>
              <Button size="sm" variant="ghost" onClick={() => edu.remove(i)}><X className="h-3 w-3" /></Button>
            </div>
            <div className="grid grid-cols-3 gap-2">
              <Input placeholder="Título" className="text-xs" value={e.titulo} onChange={ev => edu.update(i, { ...e, titulo: ev.target.value })} />
              <Input placeholder="Institución" className="text-xs" value={e.institucion} onChange={ev => edu.update(i, { ...e, institucion: ev.target.value })} />
              <Input type="date" className="text-xs" value={e.fecha_inicio} onChange={ev => edu.update(i, { ...e, fecha_inicio: ev.target.value })} />
              <Input type="date" className="text-xs" value={e.fecha_fin} onChange={ev => edu.update(i, { ...e, fecha_fin: ev.target.value })} />
              <Textarea placeholder="Descripción" className="col-span-3 text-xs min-h-[60px]" value={e.descripcion || ''} onChange={ev => edu.update(i, { ...e, descripcion: ev.target.value })} />
            </div>
          </div>
        ))}
        {edu.items.length === 0 && <div className="text-xs text-gray-400 italic" role="note">Sin educación. Usa + para añadir.</div>}
      </div>
    </div>
  );

  const renderCertifications = () => (
    <div className="space-y-3" role="group" aria-labelledby="cert-label">
      <div className="flex items-center justify-between">
        <h4 id="cert-label" className="text-xs font-semibold flex items-center gap-1">Certificaciones
          <span className="inline-flex" aria-hidden="true" title="Incluye certificaciones relevantes (ej: AWS, PMP)." aria-label="Ayuda Certificaciones">
            <Info className="h-3 w-3 text-gray-400" />
          </span>
        </h4>
        <Button size="sm" variant="ghost" onClick={(e) => {
          e.preventDefault();
          console.log('➕ Añadiendo certificación');
          cert.add('');
        }}><Plus className="h-3 w-3" /></Button>
      </div>
      <div role="list" aria-label="Lista de certificaciones">
        {cert.items.map((c, i) => (
          <div key={i} role="listitem" className="flex items-center space-x-2">
            <Input className="text-xs" value={c} onChange={e => cert.update(i, e.target.value)} placeholder={`Certificación #${i + 1}`} />
            <Button size="sm" variant="ghost" onClick={() => cert.remove(i)}><X className="h-3 w-3" /></Button>
          </div>
        ))}
        {cert.items.length === 0 && <div className="text-xs text-gray-400 italic" role="note">Sin certificaciones. Usa + para añadir.</div>}
      </div>
    </div>
  );

  // const renderProjects = () => (
  //   <div className="space-y-3" role="group" aria-labelledby="proj-label">
  //     <div className="flex items-center justify-between">
  //       <h4 id="proj-label" className="text-xs font-semibold flex items-center gap-1">Proyectos
  //         <span className="inline-flex" aria-hidden="true" title="Añade proyectos destacados (nombre y breve descripción)." aria-label="Ayuda Proyectos">
  //           <Info className="h-3 w-3 text-gray-400" />
  //         </span>
  //       </h4>
  //       <Button size="sm" variant="ghost" onClick={(e) => {
  //         e.preventDefault();
  //         console.log('➕ Añadiendo proyecto');
  //         proj.add({ nombre: '', descripcion: '' });
  //       }}><Plus className="h-3 w-3" /></Button>
  //     </div>
  //     <div role="list" aria-label="Lista de proyectos">
  //       {proj.items.map((p, i) => (
  //         <div key={i} role="listitem" className="p-2 border rounded space-y-2">
  //           <div className="flex justify-between items-center">
  //             <span className="text-xs font-medium">Proyecto #{i + 1}</span>
  //             <Button size="sm" variant="ghost" onClick={() => proj.remove(i)}><X className="h-3 w-3" /></Button>
  //           </div>
  //           <Input placeholder="Nombre" className="text-xs" value={p.nombre} onChange={e => proj.update(i, { ...p, nombre: e.target.value })} />
  //           <Textarea placeholder="Descripción" className="text-xs min-h-[60px]" value={p.descripcion} onChange={e => proj.update(i, { ...p, descripcion: e.target.value })} />
  //         </div>
  //       ))}
  //       {proj.items.length === 0 && <div className="text-xs text-gray-400 italic" role="note">Sin proyectos. Usa + para añadir.</div>}
  //     </div>
  //   </div>
  // );

  const showProcessing = flowState === 'uploading' || flowState === 'parsing';
  const progressValue = flowState === 'uploading' ? 30 : flowState === 'parsing' ? 70 : (flowState === 'ready' || flowState === 'manual') ? 100 : 0;

  const statusBadge = flowState === 'ready' ? (
    <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700 border border-green-300" aria-label="Asistido por IA">Asistido por IA</span>
  ) : flowState === 'manual' ? (
    <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700 border border-amber-300" aria-label="Entrada manual">Entrada manual</span>
  ) : null;

  // Auto-abrir modal si llegamos a ready o manual y aún no está abierto (seguridad UX)
  useEffect(() => {
    if ((flowState === 'ready' || flowState === 'manual') && !modalOpen) {
      setModalOpen(true);
    }
  }, [flowState, modalOpen]);

  return (
    <div className="space-y-6">
      {!manualOnly && (
        <div className="border border-dashed rounded-lg p-6 text-center bg-white" aria-describedby="uploader-help">
          <Upload className="h-8 w-8 text-gray-400 mx-auto" />
          <p className="mt-2 text-sm font-medium">Sube tu CV en PDF</p>
          <p className="text-xs text-gray-500">Máx 5MB · Solo formato PDF</p>

          {progressValue > 0 && progressValue < 100 && (
            <div className="w-full mt-4" aria-live="polite">
              <div className="h-2 w-full bg-gray-200 rounded overflow-hidden" role="progressbar" aria-valuenow={progressValue} aria-valuemin={0} aria-valuemax={100} aria-label="Progreso de procesamiento">
                <div className="h-full bg-gradient-to-r from-blue-500 to-blue-400 transition-all duration-500" style={{ width: `${progressValue}%` }} />
              </div>
              <p className="mt-1 text-[11px] text-gray-600">{flowState === 'uploading' ? 'Subiendo archivo...' : flowState === 'parsing' ? 'Analizando y extrayendo datos...' : ''}</p>
            </div>
          )}

          <div className="mt-4 flex flex-col items-center space-y-2">
            <input
              id="cv-intake-file"
              type="file"
              accept="application/pdf"
              className="hidden"
              onChange={(e) => handleFileChange(e.target.files?.[0])}
            />
            <div className="flex space-x-2">
              <Button variant="outline" onClick={() => document.getElementById('cv-intake-file')?.click()} disabled={showProcessing}>
                Seleccionar Archivo
              </Button>
              <Button onClick={processFile} disabled={!localFile || showProcessing}>
                {showProcessing ? (
                  <><Loader2 className="h-4 w-4 mr-2 animate-spin" />Procesando...</>
                ) : 'Procesar CV'}
              </Button>
              <Button variant="secondary" onClick={openManual} disabled={showProcessing}>
                Cargar manualmente
              </Button>
            </div>
            {localFile && <p className="text-xs text-gray-600">{localFile.name} ({(localFile.size / 1024).toFixed(1)} KB)</p>}
          </div>

          {globalError && (
            <div className="mt-4 p-3 text-sm bg-red-50 border border-red-200 text-red-700 rounded flex items-start space-x-2">
              <AlertTriangle className="h-4 w-4 mt-0.5" />
              <span>{globalError}</span>
            </div>
          )}
          {alertMsg && (
            <div className="mt-4 p-3 text-sm bg-amber-50 border border-amber-200 text-amber-700 rounded flex items-start space-x-2">
              <AlertTriangle className="h-4 w-4 mt-0.5" />
              <span>{alertMsg}</span>
            </div>
          )}
        </div>
      )}
      {manualOnly && (
        <div className="p-4 border rounded bg-amber-50 text-amber-800 text-sm" role="alert">
          El modo IA está deshabilitado por política. Complete el CV manualmente.
        </div>
      )}

      <Dialog open={modalOpen} onOpenChange={setModalOpen}>
        <DialogContent className="max-w-5xl max-h-[95vh] overflow-y-auto focus:outline-none" role="dialog" aria-modal="true" aria-labelledby="cv-modal-title">
          <DialogHeader>
            <DialogTitle id="cv-modal-title" className="flex items-center space-x-2">
              {flowState === 'ready' && <><CheckCircle2 className="h-5 w-5 text-green-500" /><span>Revisar Datos Extraídos</span></>}
              {flowState === 'manual' && <><AlertTriangle className="h-5 w-5 text-amber-500" /><span>Completar Manualmente</span></>}
              {flowState === 'saving' && <><Loader2 className="h-5 w-5 text-blue-500 animate-spin" /><span>Guardando...</span></>}
              {statusBadge}
            </DialogTitle>
            <DialogDescription>
              {flowState === 'ready' && 'Revisa y corrige la información antes de confirmar.'}
              {flowState === 'manual' && 'Introduce los datos básicos de tu CV.'}
              {flowState === 'saving' && 'Guardando tu información...'}
            </DialogDescription>
          </DialogHeader>

          {errors.length > 0 && (
            <div className="bg-red-50 border border-red-200 rounded p-3 mb-4">
              <ul className="list-disc list-inside text-xs text-red-700 space-y-1">
                {errors.map((e, i) => <li key={i}>{e}</li>)}
              </ul>
            </div>
          )}

          <div className="space-y-6">
            {/* Información Personal */}
            <section className="space-y-3" aria-labelledby="info-personal-label">
              <h3 id="info-personal-label" className="text-sm font-semibold border-b pb-2 flex items-center gap-2">Información Personal
                <span className="inline-flex" aria-hidden="true" title="Incluye datos de contacto y un resumen profesional claro." aria-label="Ayuda Información Personal">
                  <Info className="h-4 w-4 text-gray-400" />
                </span>
              </h3>
              <div className="grid grid-cols-3 gap-3">
                {renderBasicInput('Nombre', 'nombre')}
                {renderBasicInput('Email', 'email', 'email')}
                {renderBasicInput('Teléfono', 'telefono', 'tel')}
                {renderBasicInput('Ubicación', 'ubicacion_actual')}
                {renderBasicInput('Fecha Nacimiento', 'fecha_nacimiento', 'date')}
                {renderBasicInput('LinkedIn', 'linkedin', 'url')}
              </div>
              <div>
                <Label className="text-xs font-medium text-gray-700">Resumen Profesional</Label>
                <Textarea className="text-xs min-h-[80px] mt-1" value={formData.resumen_profesional || ''} onChange={e => updateSimpleField('resumen_profesional', e.target.value)} aria-describedby="resumen-help" />
                <p id="resumen-help" className="text-[10px] text-gray-500 mt-1">Breve perfil (máx ~4 líneas) destacando experiencia y fortalezas clave.</p>
              </div>
            </section>

            <Separator />
            {renderExperiences()}
            <Separator />
            {renderEducation()}
            <Separator />
            {renderCertifications()}
            </div>

          <DialogFooter className="mt-6 flex items-center justify-between">
            <div className="flex items-center gap-2">
              {flowState === 'manual' && (
                <Button type="button" variant="secondary" onClick={retryAnotherPDF} disabled={saving} aria-label="Reintentar con otro PDF">Reintentar con otro PDF</Button>
              )}
              <Button variant="outline" onClick={closeModal} disabled={saving}>Cancelar</Button>
            </div>
            <div>
              <Button onClick={handleConfirm} disabled={saving || flowState === 'saving'}>
                {saving ? <><Loader2 className="h-4 w-4 mr-2 animate-spin" />Confirmando...</> : 'Confirmar y Guardar'}
              </Button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default CvIntake;
