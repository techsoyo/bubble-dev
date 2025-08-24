/**
 * LEGACY API SERVICE - MIGRATED TO NEW SECURE VERSION
 * 
 * Este archivo mantiene la compatibilidad pero redirige al nuevo ApiService
 * con JWT authentication automática.
 */

// ✅ Importar el nuevo ApiService seguro
import ApiService from '../services/ApiService';

// ✅ Re-exportar métodos principales con compatibilidad
export const api = ApiService;

// ===== FUNCIONES DE COMPATIBILIDAD CON JWT =====

export async function getJobs(params?: any) {
    try {
        const queryString = params ? new URLSearchParams(params).toString() : '';
        const endpoint = queryString ? `jobs.php?${queryString}` : 'jobs.php';

        // ✅ Usar ApiService con JWT automático
        const response = await ApiService.publicGet(endpoint); // Jobs listing es público

        return {
            success: true,
            data: response.data || response,
            message: response.message || 'Jobs obtenidos exitosamente'
        };
    } catch (error: any) {
        console.error('Error getting jobs:', error);
        return {
            success: false,
            data: [],
            message: error.message || 'Error al obtener trabajos'
        };
    }
}

export async function getCandidateApplications(candidateId: string) {
    try {
        // ✅ Usar endpoint corregido con JWT
        const response = await ApiService.get(`candidate-applications.php?candidate_id=${candidateId}`);

        return {
            success: true,
            data: response.data?.applications || response.data || [],
            message: response.message || 'Aplicaciones obtenidas exitosamente'
        };
    } catch (error: any) {
        console.error('Error getting candidate applications:', error);
        return {
            success: false,
            data: [],
            message: error.message || 'Error al obtener aplicaciones'
        };
    }
}

export async function submitApplication(data: {
    nombre: string;
    email: string;
    job_id: string;
    cv_url?: string;
    cover_letter?: string;
}) {
    try {
        // ✅ Usar endpoint corregido con JWT
        const response = await ApiService.post('applications.php', {
            job_id: data.job_id,
            cover_letter: data.cover_letter || ''
        });

        return {
            success: true,
            data: response.data,
            message: response.message || 'Aplicación enviada exitosamente'
        };
    } catch (error: any) {
        console.error('Error submitting application:', error);
        return {
            success: false,
            data: null,
            message: error.message || 'Error al enviar aplicación'
        };
    }
}

export async function changeCandidateStatus(data: {
    candidato_id: string;
    nuevo_estado: string;
    motivo?: string;
}) {
    try {
        // ✅ Usar nuevo endpoint con JWT - solo admin/hr
        const response = await ApiService.put(`applications/${data.candidato_id}/status`, {
            status: data.nuevo_estado,
            reason: data.motivo
        });

        return {
            success: true,
            data: response.data,
            message: response.message || 'Estado actualizado exitosamente'
        };
    } catch (error: any) {
        console.error('Error changing candidate status:', error);
        return {
            success: false,
            data: null,
            message: error.message || 'Error al cambiar estado'
        };
    }
}

export async function getCandidates(params?: any) {
    try {
        const queryString = params ? new URLSearchParams(params).toString() : '';
        const endpoint = queryString ? `candidates.php?${queryString}` : 'candidates.php';

        // ✅ Solo admin/hr pueden ver candidatos
        const response = await ApiService.get(endpoint);

        return {
            success: true,
            data: response.data || response,
            message: response.message || 'Candidatos obtenidos exitosamente'
        };
    } catch (error: any) {
        console.error('Error getting candidates:', error);
        return {
            success: false,
            data: [],
            message: error.message || 'Error al obtener candidatos'
        };
    }
}

export async function uploadCV(formData: FormData) {
    try {
        // ✅ Usar método upload con JWT
        const response = await ApiService.upload('analyze_cv.php', formData);

        return {
            success: true,
            data: response.data,
            message: response.message || 'CV subido exitosamente'
        };
    } catch (error: any) {
        console.error('Error uploading CV:', error);
        return {
            success: false,
            data: null,
            message: error.message || 'Error al subir CV'
        };
    }
}

export async function updateCandidateProfile(candidateId: string, profileData: any) {
    try {
        const response = await ApiService.put(`candidates/${candidateId}`, profileData);

        return {
            success: true,
            data: response.data,
            message: response.message || 'Perfil actualizado exitosamente'
        };
    } catch (error: any) {
        console.error('Error updating candidate profile:', error);
        return {
            success: false,
            data: null,
            message: error.message || 'Error al actualizar perfil'
        };
    }
}

export async function changeCandidatePassword(data: {
    current_password: string;
    new_password: string;
}) {
    try {
        const response = await ApiService.post('change-password.php', data);

        return {
            success: true,
            data: response.data,
            message: response.message || 'Contraseña cambiada exitosamente'
        };
    } catch (error: any) {
        console.error('Error changing password:', error);
        return {
            success: false,
            data: null,
            message: error.message || 'Error al cambiar contraseña'
        };
    }
}

// ===== FUNCIONES ADMIN (Solo admin/hr) =====

export async function createJob(jobData: any) {
    try {
        const response = await ApiService.post('jobs.php', jobData);
        return { success: true, data: response.data, message: response.message };
    } catch (error: any) {
        return { success: false, data: null, message: error.message };
    }
}

export async function updateJob(jobId: string, jobData: any) {
    try {
        const response = await ApiService.put(`jobs/${jobId}`, jobData);
        return { success: true, data: response.data, message: response.message };
    } catch (error: any) {
        return { success: false, data: null, message: error.message };
    }
}

export async function deleteJob(jobId: string) {
    try {
        const response = await ApiService.delete(`jobs/${jobId}`);
        return { success: true, data: response.data, message: response.message };
    } catch (error: any) {
        return { success: false, data: null, message: error.message };
    }
}

export async function getApplications(params?: any) {
    try {
        const queryString = params ? new URLSearchParams(params).toString() : '';
        const endpoint = queryString ? `applications.php?${queryString}` : 'applications.php';

        const response = await ApiService.get(endpoint);
        return { success: true, data: response.data, message: response.message };
    } catch (error: any) {
        return { success: false, data: [], message: error.message };
    }
}

// ===== FUNCIONES AUXILIARES =====
export async function registerCandidate(data: any) {
    return await ApiService.publicPost('auth/register.php', data);
}

export async function saveCandidateFromAI(data: any) {
    return await ApiService.post('candidates/save-from-ai.php', data);
}

export async function getHRDashboardStats() {
    return await ApiService.get('dashboard/hr-stats.php');
}

export async function getNotificationTemplates() {
    return await ApiService.get('notifications/templates.php');
}

export async function sendNotification(data: any) {
    return await ApiService.post('notifications/send.php', data);
}

// Mantener compatibilidad con API_BASE_URL (aunque ya no se use directamente)
import { env } from '../config/env';
const API_BASE_URL = env.API_BASE_URL;
export { API_BASE_URL };

// ✅ Función para obtener headers (DEPRECATED - usar ApiService)
export function getAuthHeaders() {
    console.warn('⚠️ getAuthHeaders() is deprecated. Use ApiService directly.');
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    };
}

// ===== EXPORTS DE COMPATIBILIDAD =====
export default {
    get: ApiService.get,
    post: ApiService.post,
    put: ApiService.put,
    delete: ApiService.delete,
    upload: ApiService.upload
};
