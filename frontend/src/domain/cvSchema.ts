/**
 * Schema unificado para CV - espejo del contrato backend
 * 
 * Proporciona:
 * - CvFormData: tipado TypeScript completo
 * - cvTemplate: estructura base para el formulario
 * - Estados finitos del formulario
 */

// Estados finitos del formulario CV
export type CvFormState =
  | 'idle'      // aún no subió PDF
  | 'uploading' // subiendo archivo
  | 'parsing'   // esperando IA
  | 'ready'     // form con datos pre-rellenados
  | 'manual'    // falló IA o usuario elige carga manual
  | 'saving';   // enviando confirmación

// Tipo para idiomas simples
export interface IdiomaSimple {
  idioma: string;
  nivel: string;
}

// Tipo para experiencia laboral
export interface ExperienciaLaboral {
  puesto: string;
  empresa: string;
  fecha_inicio: string;
  fecha_fin: string;
  descripcion: string;
  responsabilidades?: string[];
  ubicacion?: string;
  actual?: boolean;
}

// Tipo para educación
export interface Educacion {
  titulo: string;
  campo_estudio?: string;
  institucion: string;
  fecha_inicio: string;
  fecha_fin: string;
  nivel_educativo?: string;
  descripcion?: string;
}

// Tipo para certificación detallada
export interface CertificacionDetalle {
  nombre_certificacion: string;
  emisor?: string;
  fecha_emision?: string;
  fecha_expiracion?: string;
}

// Tipo para idioma detallado
export interface IdiomaDetalle {
  idioma: string;
  nivel_competencia?: string;
}

// Tipo para proyecto
export interface Proyecto {
  nombre: string;
  descripcion: string;
  tecnologias?: string[];
  fecha_inicio?: string;
  fecha_fin?: string;
  url?: string;
}

// Tipo para referencia detallada
export interface ReferenciaDetalle {
  nombre_referencia: string;
  empresa_referencia?: string;
  email_referencia?: string;
  telefono_referencia?: string;
  notas?: string;
}

// Tipo para routing
export interface Routing {
  categoria_departamento_id?: number;
  departamento_id?: number;
  reclutador_id?: string;
  fuente?: 'ai' | 'manual';
  razon?: string;
  fecha_asignacion?: string;
}

// Interfaz principal del formulario CV - espejo exacto del backend
export interface CvFormData {
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
  idiomas?: IdiomaSimple[];
  intereses?: string[];
  referencias?: string;
  disponibilidad?: string;
  cv_original_file?: string;
  cv_text_file?: string;
  cv_json_file?: string;
  data_source?: 'ai_processing' | 'manual_entry' | 'hybrid';

  // bt_candidate_experiences
  puestos_anteriores?: ExperienciaLaboral[];

  // bt_candidate_education
  educacion?: Educacion[];

  // bt_candidate_certifications - Detalles expandidos
  certificaciones?: string[];
  certificaciones_detalle?: CertificacionDetalle[];

  // bt_candidate_languages - Detalles expandidos
  idiomas_detalle?: IdiomaDetalle[];

  // bt_candidate_projects
  proyectos?: Proyecto[];

  // bt_candidate_references - Detalles expandidos
  referencias_detalle?: ReferenciaDetalle[];

  // bt_candidate_skills - Habilidades adicionales
  habilidades_adicionales?: string[];

  // bt_candidate_routing - Información de enrutamiento
  routing?: Routing;
}

// Template base del CV - espejo exacto del backend
export const cvTemplate: CvFormData = {
  // bt_candidates - Campos principales
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
  cv_original_file: '',
  cv_text_file: '',
  cv_json_file: '',
  data_source: 'manual_entry',

  // bt_candidate_experiences
  puestos_anteriores: [],

  // bt_candidate_education
  educacion: [],

  // bt_candidate_certifications - Detalles expandidos
  certificaciones: [],
  certificaciones_detalle: [],

  // bt_candidate_languages - Detalles expandidos
  idiomas_detalle: [],

  // bt_candidate_projects
  proyectos: [],

  // bt_candidate_references - Detalles expandidos
  referencias_detalle: [],

  // bt_candidate_skills - Habilidades adicionales
  habilidades_adicionales: [],

  // bt_candidate_routing - Información de enrutamiento
  routing: {
    categoria_departamento_id: undefined,
    departamento_id: undefined,
    reclutador_id: '',
    fuente: 'manual',
    razon: '',
    fecha_asignacion: ''
  }
};

// Utilidades de validación
export const validateMinimumData = (data: CvFormData): string[] => {
  const errors: string[] = [];

  if (!data.nombre?.trim()) {
    errors.push('El nombre es obligatorio');
  }

  if (!data.email?.trim()) {
    errors.push('El email es obligatorio');
  } else {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email)) {
      errors.push('El email no tiene un formato válido');
    }
  }

  return errors;
};

// Función para mergear datos pre-rellenados con el template
export const mergeWithTemplate = (data: Partial<CvFormData>): CvFormData => {
  const merged = { ...cvTemplate };

  // Mergear campos de forma type-safe
  if (data.nombre !== undefined) merged.nombre = data.nombre;
  if (data.email !== undefined) merged.email = data.email;
  if (data.telefono !== undefined) merged.telefono = data.telefono;
  if (data.ubicacion_actual !== undefined) merged.ubicacion_actual = data.ubicacion_actual;
  if (data.fecha_nacimiento !== undefined) merged.fecha_nacimiento = data.fecha_nacimiento;
  if (data.portfolio !== undefined) merged.portfolio = data.portfolio;
  if (data.linkedin !== undefined) merged.linkedin = data.linkedin;
  if (data.resumen_profesional !== undefined) merged.resumen_profesional = data.resumen_profesional;
  if (data.referencias !== undefined) merged.referencias = data.referencias;
  if (data.disponibilidad !== undefined) merged.disponibilidad = data.disponibilidad;
  if (data.cv_original_file !== undefined) merged.cv_original_file = data.cv_original_file;
  if (data.cv_text_file !== undefined) merged.cv_text_file = data.cv_text_file;
  if (data.cv_json_file !== undefined) merged.cv_json_file = data.cv_json_file;
  if (data.data_source !== undefined) merged.data_source = data.data_source;

  // Arrays
  if (Array.isArray(data.otras_redes)) merged.otras_redes = data.otras_redes;
  if (Array.isArray(data.soft_skills)) merged.soft_skills = data.soft_skills;
  if (Array.isArray(data.hard_skills)) merged.hard_skills = data.hard_skills;
  if (Array.isArray(data.idiomas)) merged.idiomas = data.idiomas;
  if (Array.isArray(data.intereses)) merged.intereses = data.intereses;
  if (Array.isArray(data.puestos_anteriores)) merged.puestos_anteriores = data.puestos_anteriores;
  if (Array.isArray(data.educacion)) merged.educacion = data.educacion;
  if (Array.isArray(data.certificaciones)) merged.certificaciones = data.certificaciones;
  if (Array.isArray(data.certificaciones_detalle)) merged.certificaciones_detalle = data.certificaciones_detalle;
  if (Array.isArray(data.idiomas_detalle)) merged.idiomas_detalle = data.idiomas_detalle;
  if (Array.isArray(data.proyectos)) merged.proyectos = data.proyectos;
  if (Array.isArray(data.referencias_detalle)) merged.referencias_detalle = data.referencias_detalle;
  if (Array.isArray(data.habilidades_adicionales)) merged.habilidades_adicionales = data.habilidades_adicionales;

  // Objeto routing
  if (data.routing && typeof data.routing === 'object') {
    merged.routing = { ...merged.routing, ...data.routing };
  }

  return merged;
};

// Estado del formulario CV
export interface CvFormContextState {
  state: CvFormState;
  data: CvFormData;
  errors: string[];
  isLoading: boolean;
  originalFile?: File;
}

// Acciones del estado del formulario
export type CvFormAction =
  | { type: 'SET_STATE'; payload: CvFormState }
  | { type: 'SET_DATA'; payload: CvFormData }
  | { type: 'UPDATE_FIELD'; payload: { field: keyof CvFormData; value: any } }
  | { type: 'SET_ERRORS'; payload: string[] }
  | { type: 'SET_LOADING'; payload: boolean }
  | { type: 'SET_FILE'; payload: File }
  | { type: 'RESET_FORM' }
  | { type: 'PREFILL_FROM_AI'; payload: CvFormData }
  | { type: 'SWITCH_TO_MANUAL' };

// Reducer para el estado del formulario
export const cvFormReducer = (state: CvFormContextState, action: CvFormAction): CvFormContextState => {
  switch (action.type) {
    case 'SET_STATE':
      return { ...state, state: action.payload };

    case 'SET_DATA':
      return { ...state, data: action.payload };

    case 'UPDATE_FIELD':
      return {
        ...state,
        data: {
          ...state.data,
          [action.payload.field]: action.payload.value
        }
      };

    case 'SET_ERRORS':
      return { ...state, errors: action.payload };

    case 'SET_LOADING':
      return { ...state, isLoading: action.payload };

    case 'SET_FILE':
      return {
        ...state,
        originalFile: action.payload,
        state: 'uploading'
      };

    case 'RESET_FORM':
      return {
        state: 'idle',
        data: { ...cvTemplate },
        errors: [],
        isLoading: false,
        originalFile: undefined
      };

    case 'PREFILL_FROM_AI':
      return {
        ...state,
        data: mergeWithTemplate(action.payload),
        state: 'ready',
        isLoading: false,
        errors: []
      };

    case 'SWITCH_TO_MANUAL':
      return {
        ...state,
        data: { ...cvTemplate },
        state: 'manual',
        isLoading: false,
        errors: []
      };

    default:
      return state;
  }
};

// Estado inicial del formulario
export const initialCvFormState: CvFormContextState = {
  state: 'idle',
  data: { ...cvTemplate },
  errors: [],
  isLoading: false,
  originalFile: undefined
};
