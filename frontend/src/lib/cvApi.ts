import { env } from '../config/env';
import { CvFormData } from '../domain/cvSchema';

/**
 * Analiza un CV usando GroqApiService con IA
 * @param file Archivo PDF del CV
 * @returns Datos estructurados extraídos del CV
 */
export async function parseCv(file: File) {
  const fd = new FormData();
  fd.append('cv_file', file); // Cambiar 'file' por 'cv_file' para que coincida con el backend

  try {
    const apiUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost/bubble_of_talents_1.0/backend/public';
    const res = await fetch(new URL('/api/analyze_cv.php', apiUrl), {
      method: 'POST',
      body: fd
    });

    const json = await res.json().catch(() => ({}));

    // Si el análisis fue exitoso, devolver los datos estructurados
    if (res.status === 200 && json.ok && json.data?.structured_data) {
      return {
        status: res.status,
        json: {
          success: true,
          data: json.data.structured_data
        },
        processing_info: json.data.processing_info
      };
    } else {
      return {
        status: res.status,
        json: {},
        error: json.message || json.error || 'Error procesando CV'
      };
    }
  } catch (error) {
    console.error('[CV_PARSE_ERROR]', error);
    return {
      status: 500,
      json: {},
      error: 'Error de conexión con el servidor'
    };
  }
}

/**
 * Guarda/confirma los datos del CV validados por el usuario
 * @param payload Datos del CV editados por el usuario
 * @returns Resultado de la confirmación
 */
export async function confirmCv(payload: CvFormData) {
  try {
    const res = await fetch(new URL('/api/candidates/save_v2.php', env.API_BASE_URL), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...payload,
        data_source: 'ai_processing' // Marcar como procesado por IA
      })
    });

    const json = await res.json().catch(() => ({}));
    return { status: res.status, json };
  } catch (error) {
    console.error('[CV_CONFIRM_ERROR]', error);
    return {
      status: 500,
      json: {},
      error: 'Error guardando datos del CV'
    };
  }
}
