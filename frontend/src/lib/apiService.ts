// src/lib/apiService.ts

// 🔧 CORRECCIÓN: Importar configuración dinámica
import { getApiBaseUrl } from '../hooks/useApiConfig';

// Configuración dinámica de la URL base del API
const API_BASE_URL = getApiBaseUrl();

// Mapeo de iconos para la cultura corporativa.  La clave es el título devuelto por el backend
// y el valor es un nodo de React que representa el icono correspondiente.  Puedes añadir
// más entradas según los títulos que maneje tu base de datos.
const cultureIconMap: Record<string, string> = {
    // Mapeamos títulos a nombres de iconos
    'Trabajo en Equipo': 'FaUsers',
    'Innovación Continua': 'FaLightbulb',
    'Orientación al Cliente': 'FaHandsHelping',
    // Añade más valores según sea necesario
};

// (Eliminada función apiRequest porque no se utiliza)

// Función para obtener los headers de autenticación
function getAuthHeaders() {
    // Obtener el token almacenado en localStorage o sessionStorage
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');

    // Devolver los headers con el token si existe
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(token ? { 'Authorization': `Bearer ${token}` } : {})
    };
}

// Funciones existentes del MVP
export async function submitApplication(data: {
    nombre: string;
    email: string;
    job_id: string;
    cv_url?: string;
}) {
    // Nuevo endpoint producción
    const response = await fetch("/api/applications", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    });
    return response.json();
}

export async function changeCandidateStatus(data: {
    candidato_id: string;
    nuevo_estado: string;
    motivo?: string;
}) {
    // Nuevo endpoint producción
    // Se espera que data contenga candidato_id y nuevo_estado
    const { candidato_id, nuevo_estado, motivo } = data;
    const response = await fetch(`/api/applications/${candidato_id}/status`, {
        method: "PATCH",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ status: nuevo_estado, motivo }),
    });
    return response.json();
}

// --- NUEVAS FUNCIONES PARA LA VERSIÓN MEJORADA ---

// AUTENTICACIÓN DE CANDIDATOS CON SOCIAL LOGIN
export async function socialLogin(provider: 'google' | 'linkedin' | 'apple', token: string) {
    // Nuevo endpoint producción
    const response = await fetch("/api/auth/social-login", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ provider, token }),
    });
    return response.json();
}

export async function registerCandidate(data: {
    name: string;
    email: string;
    password?: string;
    social_provider?: string;
    social_id?: string;
    consentimiento_gdpr: boolean;
    // Campos adicionales que pueden venir del formulario completo
    phone?: string;
    country?: string;
    city?: string;
    gender?: string;
    jobPosition?: string;
    areaOfInterest?: string;
    jobSearchType?: string;
    educationLevel?: string;
    lastPosition?: string;
    lastCompany?: string;
    yearsOfExperience?: string;
    skills?: string[];
    softSkills?: string[];
    languages?: string[];
    motivation?: string;
    linkedin?: string;
    website?: string;
    github?: string;
    residencePermit?: string;
    workModality?: string;
    desiredSchedule?: string;
    desiredSalary?: {
        min: number;
        max: number;
        currency: string;
    };
}) {
    // Nuevo endpoint producción
    const response = await fetch("/api/candidates/register", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    });
    return response.json();
}

// Función para probar la conexión con el backend
export async function testBackendConnection() {
    // Eliminada lógica legacy de test_connection. Implementar health-check real si es necesario.
    return { success: true, message: "Función de test de backend eliminada. Usar health-check real." };
}

// Función para probar la conexión (versión alternativa)
export async function testBackendConnectionV2() {
    // Eliminada lógica legacy de test_connection. Implementar health-check real si es necesario.
    return { success: true, message: "Función de test de backend eliminada. Usar health-check real." };
}

export async function loginCandidate(email: string, password: string) {
    // Nuevo endpoint producción
    const response = await fetch("/api/auth/login", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password, tipo: "candidate" }),
    });
    return response.json();
}

export async function login(data: {
    email: string;
    password: string;
    rememberMe?: boolean;
}) {
    // Nuevo endpoint producción
    const response = await fetch("/api/auth/login", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    });
    return response.json();
}

// AUTENTICACIÓN DE RRHH Y RECRUITERS
export async function loginStaff(email: string, password: string) {
    // Nuevo endpoint producción
    const response = await fetch("/api/auth/login", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password, tipo: "staff" }),
    });
    return response.json();
}

// CAMBIO DE CONTRASEÑA PARA CANDIDATOS
export async function changeCandidatePassword(data: {
    candidate_id: string;
    current_password: string;
    new_password: string;
    new_password_confirmation: string;
}) {
    try {
        const response = await fetch('http://localhost:8000/api/change-password.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            body: JSON.stringify(data),
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Error al cambiar contraseña');
        }

        return result;
    } catch (error) {
        console.error('Error en changeCandidatePassword:', error);
        throw error;
    }
}

// GESTIÓN DE NOTIFICACIONES
export async function sendNotification(data: {
    recipient_id: string;
    recipient_type: 'candidate' | 'recruiter' | 'admin';
    template_id: string;
    variables?: Record<string, string>;
    channel?: 'email' | 'sms' | 'push' | 'in-app';
    scheduled_for?: string;
}) {
    // Nuevo endpoint producción
    const response = await fetch("/api/notifications", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    });
    return response.json();
}

// INTEGRACIÓN DE CALENDARIOS
export async function getCalendarIntegrations(userId: string, userType: 'candidate' | 'recruiter' | 'admin') {
    // Nuevo endpoint producción
    const response = await fetch(`/api/calendar/integrations?userId=${userId}&userType=${userType}`, {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
        },
    });
    return response.json();
}

export async function connectCalendar(
    userId: string,
    userType: 'candidate' | 'recruiter' | 'admin',
    provider: 'google' | 'microsoft' | 'microsoft365',
    authCode?: string
) {
    // Nuevo endpoint producción
    const response = await fetch("/api/calendar/connect", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            userId,
            userType,
            provider,
            auth_code: authCode
        }),
    });
    return response.json();
}

export async function disconnectCalendar(userId: string, userType: 'candidate' | 'recruiter' | 'admin', provider: 'google' | 'microsoft') {
    // Nuevo endpoint producción
    const response = await fetch("/api/calendar/disconnect", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ userId, userType, provider }),
    });
    return response.json();
}

export async function getCalendarEvents(userId: string, userType: 'candidate' | 'recruiter' | 'admin', startDate: string, endDate: string) {
    // Nuevo endpoint producción
    const response = await fetch(`/api/calendar/events?userId=${userId}&userType=${userType}&startDate=${startDate}&endDate=${endDate}`, {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
        },
    });
    return response.json();
}

export async function getNotificationTemplates() {
    // Nuevo endpoint producción
    const response = await fetch("/api/notifications/templates");
    return response.json();
}

// INTEGRACIÓN CON CALENDARIOS - Ya está definida arriba
// Usando la versión unificada de connectCalendar

export async function scheduleInterview(data: {
    application_id: string;
    title: string;
    description?: string;
    start_time: string;
    end_time: string;
    attendees: Array<{
        id: string;
        email: string;
        role: 'candidate' | 'recruiter' | 'hr';
    }>;
    location?: string;
}) {
    // Nuevo endpoint producción
    const response = await fetch("/api/calendar/schedule-interview", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    });
    return response.json();
}

export async function getAvailableSlots(recruiter_id: string, start_date: string, end_date: string) {
    // Nuevo endpoint producción
    const response = await fetch(`/api/calendar/available-slots?recruiter_id=${recruiter_id}&start_date=${start_date}&end_date=${end_date}`);
    return response.json();
}

// DASHBOARDS ESPECÍFICOS
export async function getHRDashboardStats() {
    // Nuevo endpoint producción
    try {
        const response = await fetch("/api/hr/dashboard-stats", {
            headers: getAuthHeaders()
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('Error fetching HR dashboard stats:', error);
        // Fallback a datos por defecto en caso de error
        return {
            totalCandidates: 0,
            activeJobs: 0,
            pendingApplications: 0,
            interviewsScheduled: 0,
            hiringRate: 0,
            timeToHire: 0,
            topRecruiters: [],
            applicationTrends: [],
            departmentStats: []
        };
    }
}

// Función para obtener estadísticas del dashboard del reclutador
export async function getRecruiterDashboardStats(recruiter_id: string) {
    // Nuevo endpoint producción
    try {
        const response = await fetch(`/api/recruiter/${recruiter_id}/dashboard-stats`, {
            headers: getAuthHeaders()
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('Error fetching recruiter dashboard stats:', error);
        // Fallback a datos por defecto en caso de error
        return {
            assignedJobs: 0,
            activeApplications: 0,
            interviewsScheduled: 0,
            placementsMade: 0,
            notifications: [],
            interviews: [],
            metrics: {
                averageTimeToHire: 0,
                placementRate: 0,
                candidateSatisfaction: 0
            }
        };
    }
}

// Función para obtener los datos para la tabla de aplicaciones
export async function getApplicationsTableData() {
    // Nuevo endpoint producción
    const response = await fetch("/api/applications/table-data", {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
        },
    });
    return response.json();
}

// FUNCIONES PARA GESTIÓN DE FORMULARIOS MOBILE-FIRST
export async function savePartialApplication(data: Record<string, unknown>) {
    // Nuevo endpoint producción
    // Nos aseguramos de que candidate_id esté presente si existe
    const bodyData = { ...data };
    if (!('candidate_id' in bodyData) && 'id' in bodyData) {
        // Si el id está presente pero no candidate_id, lo agregamos
        bodyData['candidate_id'] = bodyData['id'];
    }
    const response = await fetch("/api/applications/save-partial", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(bodyData),
    });
    return response.json();
}

export async function uploadCV(file: File, candidate_id: string) {
    // Nuevo endpoint producción
    const formData = new FormData();
    formData.append('cv', file);
    formData.append('candidate_id', candidate_id);

    const response = await fetch("/api/files/upload-cv", {
        method: "POST",
        body: formData,
    });
    return response.json();
}

// -----------------------------------------------------------------------------
// NUEVOS ENDPOINTS PARA TABLAS ADICIONALES
// Estas funciones consumen los nuevos endpoints del backend (ubicados en
// `api/*.php`) para obtener datos de cultura, noticias, departamentos,
// experiencias, habilidades, notas, notificaciones, social logins, entrevistas
// y otros recursos asociados.

export async function getDepartments() {
    try {
        const url = `${API_BASE_URL}/departments.php`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Error al obtener departamentos');
        }
        return result.data || [];
    } catch (error) {
        console.error('Error en getDepartments:', error);
        return [];
    }
}

export async function getCandidateExperiences(candidateId: string) {
    try {
        const url = `${API_BASE_URL}/candidate_experiences.php?candidateId=${candidateId}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const result = await response.json();
        return result.data || [];
    } catch (error) {
        console.error('Error en getCandidateExperiences:', error);
        return [];
    }
}

export async function getCandidateSkills(candidateId: string) {
    try {
        const url = `${API_BASE_URL}/candidate_skills.php?candidateId=${candidateId}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const result = await response.json();
        return result.data || [];
    } catch (error) {
        console.error('Error en getCandidateSkills:', error);
        return [];
    }
}

export async function getCandidateApplications(candidateId: string) {
    try {
        const url = `${API_BASE_URL}/candidate-applications.php?candidate_id=${candidateId}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const result = await response.json();
        return result.success ? result.data : [];
    } catch (error) {
        console.error('Error en getCandidateApplications:', error);
        return [];
    }
}

export async function getApplicationNotes(applicationId: string) {
    try {
        const url = `${API_BASE_URL}/application_notes.php?applicationId=${applicationId}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const result = await response.json();
        return result.data || [];
    } catch (error) {
        console.error('Error en getApplicationNotes:', error);
        return [];
    }
}

export async function getNotifications(candidateId: string) {
    try {
        const url = `${API_BASE_URL}/notifications.php?candidateId=${candidateId}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const result = await response.json();
        return result.data || [];
    } catch (error) {
        console.error('Error en getNotifications:', error);
        return [];
    }
}

export async function getSocialLogins(candidateId: string) {
    try {
        const url = `${API_BASE_URL}/social_logins.php?candidateId=${candidateId}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const result = await response.json();
        return result.data || [];
    } catch (error) {
        console.error('Error en getSocialLogins:', error);
        return [];
    }
}

export async function getInterviews(params?: { applicationId?: string; page?: number; limit?: number; }) {
    try {
        const queryParams = new URLSearchParams();
        if (params?.applicationId) queryParams.append('applicationId', params.applicationId);
        if (params?.page) queryParams.append('page', params.page.toString());
        if (params?.limit) queryParams.append('limit', params.limit.toString());
        const url = `${API_BASE_URL}/interviews.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const result = await response.json();
        return result.data || [];
    } catch (error) {
        console.error('Error en getInterviews:', error);
        return [];
    }
}

export async function getJobBenefits(jobId: string) {
    try {
        const url = `${API_BASE_URL}/job_benefits.php?jobId=${jobId}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const result = await response.json();
        return result.data || [];
    } catch (error) {
        console.error('Error en getJobBenefits:', error);
        return [];
    }
}

export async function getJobRequirements(jobId: string) {
    try {
        const url = `${API_BASE_URL}/job_requirements.php?jobId=${jobId}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const result = await response.json();
        return result.data || [];
    } catch (error) {
        console.error('Error en getJobRequirements:', error);
        return [];
    }
}

export async function getJobSkills(jobId: string) {
    try {
        const url = `${API_BASE_URL}/job_skills.php?jobId=${jobId}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const result = await response.json();
        return result.data || [];
    } catch (error) {
        console.error('Error en getJobSkills:', error);
        return [];
    }
}

// =============================================================================
// 🔥 NUEVAS APIS REALES - CONEXIÓN DIRECTA CON BASE DE DATOS
// =============================================================================

// Nota: Configuración ya importada al inicio del archivo

// CANDIDATOS - API Real
export async function getCandidates(params?: {
    page?: number;
    limit?: number;
    status?: string;
    skills?: string[];
}) {
    try {
        const url = `${API_BASE_URL}/candidates`;

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        // El endpoint ahora devuelve {success: true, data: [...]}
        if (result.success && Array.isArray(result.data)) {
            return {
                success: true,
                data: result.data
            };
        } else {
            // Fallback para compatibilidad si devuelve directamente el array
            return {
                success: true,
                data: Array.isArray(result) ? result : []
            };
        }
    } catch (error) {
        console.error('Error en getCandidates:', error);
        return {
            success: false,
            error: error instanceof Error ? error.message : 'Error desconocido'
        };
    }
}

export async function getCandidate(id: string) {
    try {
        const response = await fetch(`${API_BASE_URL}/candidates.php?id=${id}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al obtener candidato');
        }

        return result;
    } catch (error) {
        console.error('Error en getCandidate:', error);
        throw error;
    }
}

// TRABAJOS - API Real
export async function getJobs(params?: {
    page?: number;
    limit?: number;
    status?: string;
}) {
    try {
        // Nuevo endpoint producción
        const queryParams = new URLSearchParams();
        if (params?.page) queryParams.append('page', params.page.toString());
        if (params?.limit) queryParams.append('limit', params.limit.toString());
        if (params?.status) queryParams.append('status', params.status);
        // Construir la URL usando API_BASE_URL para permitir entornos de desarrollo y producción
        const url = `${API_BASE_URL}/jobs.php${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Error al obtener trabajos');
        }
        return result;
    } catch (error) {
        console.error('Error en getJobs:', error);
        throw error;
    }
}

export async function getJob(id: string) {
    try {
        // Corregido: usar endpoint que funciona con CORS
        const response = await fetch(`${API_BASE_URL}/jobs.php?id=${id}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Error al obtener trabajo');
        }
        return result;
    } catch (error) {
        console.error('Error en getJob:', error);
        throw error;
    }
}

// APLICACIONES - API Real  
export async function getApplications(params?: {
    jobId?: string;
    candidateId?: string;
    status?: string;
}) {
    try {
        // Nuevo endpoint producción
        const queryParams = new URLSearchParams();
        if (params?.jobId) queryParams.append('jobId', params.jobId);
        if (params?.candidateId) queryParams.append('candidateId', params.candidateId);
        if (params?.status) queryParams.append('status', params.status);
        const url = `/api/applications${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Error al obtener aplicaciones');
        }
        return result;
    } catch (error) {
        console.error('Error en getApplications:', error);
        throw error;
    }
}

export async function getRecruiters(params?: {
    status?: string;
    company?: string;
}) {
    try {
        // Nuevo endpoint producción
        const queryParams = new URLSearchParams();
        if (params?.status) queryParams.append('status', params.status);
        if (params?.company) queryParams.append('company', params.company);
        const url = `/api/recruiters${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Error al obtener reclutadores');
        }
        return result;
    } catch (error) {
        console.error('Error en getRecruiters:', error);
        throw error;
    }
}

export async function getSkills(params?: { category?: string }) {
    try {
        // Nuevo endpoint producción
        const queryParams = new URLSearchParams();
        if (params?.category) queryParams.append('category', params.category);
        const url = `/api/skills${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Error al obtener habilidades');
        }
        return result;
    } catch (error) {
        console.error('Error en getSkills:', error);
        throw error;
    }
}

// =====================================
// FUNCIONES CRUD COMPLETAS
// =====================================

// CANDIDATOS CRUD
export async function createCandidate(data: {
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
}) {
    try {
        // Nuevo endpoint producción
        const response = await fetch("/api/candidates", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al crear candidato');
        }

        return result;
    } catch (error) {
        console.error('Error en createCandidate:', error);
        throw error;
    }
}

export async function updateCandidate(id: string, data: {
    name?: string;
    email?: string;
    phone?: string;
    location?: string;
    skills?: string[];
    experience_years?: number;
    education_level?: string;
    linkedin?: string;
    github?: string;
    portfolio?: string;
}) {
    try {
        // Nuevo endpoint producción
        const response = await fetch(`/api/candidates/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al actualizar candidato');
        }

        return result;
    } catch (error) {
        console.error('Error en updateCandidate:', error);
        throw error;
    }
}

export async function deleteCandidate(id: string) {
    try {
        // Nuevo endpoint producción
        const response = await fetch(`/api/candidates/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al eliminar candidato');
        }

        return result;
    } catch (error) {
        console.error('Error en deleteCandidate:', error);
        throw error;
    }
}

// TRABAJOS CRUD
export async function createJob(data: {
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
}) {
    try {
        const response = await fetch(`${API_BASE_URL}/jobs.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al crear trabajo');
        }

        return result;
    } catch (error) {
        console.error('Error en createJob:', error);
        throw error;
    }
}

export async function updateJob(id: string, data: {
    title?: string;
    description?: string;
    company_name?: string;
    location?: string;
    employment_type?: string;
    department?: string;
    salary_min?: number;
    salary_max?: number;
    required_skills?: string[];
    experience_level?: string;
    education_level?: string;
    status?: string;
}) {
    try {
        const response = await fetch(`${API_BASE_URL}/jobs.php?id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al actualizar trabajo');
        }

        return result;
    } catch (error) {
        console.error('Error en updateJob:', error);
        throw error;
    }
}

export async function deleteJob(id: string) {
    try {
        const response = await fetch(`${API_BASE_URL}/jobs.php?id=${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al eliminar trabajo');
        }

        return result;
    } catch (error) {
        console.error('Error en deleteJob:', error);
        throw error;
    }
}

// APLICACIONES CRUD
export async function createApplication(data: {
    candidate_id: string;
    job_id: string;
    cover_letter?: string;
    cv_file?: File;
}) {
    try {
        const formData = new FormData();
        formData.append('candidate_id', data.candidate_id);
        formData.append('job_id', data.job_id);
        if (data.cover_letter) formData.append('cover_letter', data.cover_letter);
        if (data.cv_file) formData.append('cv_file', data.cv_file);

        const response = await fetch(`${API_BASE_URL}/applications.php`, {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al crear aplicación');
        }

        return result;
    } catch (error) {
        console.error('Error en createApplication:', error);
        throw error;
    }
}

export async function updateApplication(id: string, data: {
    status?: string;
    notes?: string;
    score?: number;
}) {
    try {
        const response = await fetch(`${API_BASE_URL}/applications.php?id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al actualizar aplicación');
        }

        return result;
    } catch (error) {
        console.error('Error en updateApplication:', error);
        throw error;
    }
}

export async function deleteApplication(id: string) {
    try {
        const response = await fetch(`${API_BASE_URL}/applications.php?id=${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al eliminar aplicación');
        }

        return result;
    } catch (error) {
        console.error('Error en deleteApplication:', error);
        throw error;
    }
}

// HABILIDADES CRUD
export async function createSkill(data: {
    name: string;
    category: string;
}) {
    try {
        const response = await fetch(`${API_BASE_URL}/skills.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al crear habilidad');
        }

        return result;
    } catch (error) {
        console.error('Error en createSkill:', error);
        throw error;
    }
}

export async function updateSkill(id: string, data: {
    name?: string;
    category?: string;
}) {
    try {
        const response = await fetch(`${API_BASE_URL}/skills.php?id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al actualizar habilidad');
        }

        return result;
    } catch (error) {
        console.error('Error en updateSkill:', error);
        throw error;
    }
}

export async function deleteSkill(id: string) {
    try {
        const response = await fetch(`${API_BASE_URL}/skills.php?id=${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al eliminar habilidad');
        }

        return result;
    } catch (error) {
        console.error('Error en deleteSkill:', error);
        throw error;
    }
}

// FUNCIONES UTILITARIAS PARA OPERACIONES EN LOTE
export async function bulkUpdateApplications(applications: Array<{
    id: string;
    status?: string;
    notes?: string;
    score?: number;
}>) {
    try {
        const response = await fetch(`${API_BASE_URL}/applications.php`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ bulk_update: applications }),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error en actualización masiva');
        }

        return result;
    } catch (error) {
        console.error('Error en bulkUpdateApplications:', error);
        throw error;
    }
}

// CONTENIDO DE CULTURA EMPRESARIAL
// Obtiene los valores de cultura corporativa desde el endpoint real.  El
// backend devuelve objetos con id, title, description, image y sort_order.
// Esta función los transforma a la estructura usada por la interfaz de
// CultureCard (title, desc y un icono opcional).
export async function getCultureContent() {
    try {
        const url = `${API_BASE_URL}/culture.php`;
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Error al obtener contenido de cultura');
        }
        const items = result.data?.items || [];
        // Transformar cada elemento en la estructura esperada y asignar iconos
        return items.map((item: any) => ({
            id: item.id,
            title: item.title,
            desc: item.description,
            // Elegir el icono según el título. Si no existe, se deja como null
            iconName: cultureIconMap[item.title] ?? null,
        }));
    } catch (error) {
        console.error('Error en getCultureContent:', error);
        return [];
    }
}

// NOTICIAS/BLOG
// Obtiene las noticias desde el nuevo endpoint real y las adapta a la
// estructura utilizada por BlogCard (id, title, date, excerpt y to).
export async function getBlogPosts() {
    try {
        const url = `${API_BASE_URL}/news.php`;
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Error al obtener noticias');
        }
        const posts = result.data?.items || [];
        return posts.map((post: any) => ({
            id: post.id,
            title: post.title,
            date: new Date(post.date_published).toLocaleDateString(),
            excerpt: post.summary,
            to: `/blog/${post.slug}`
        }));
    } catch (error) {
        console.error('Error en getBlogPosts:', error);
        return [];
    }
}

export async function getLanguage(params?: { id?: string }) {
    // Nuevo endpoint producción
    const queryParams = new URLSearchParams();
    if (params?.id) queryParams.append('id', params.id);
    const url = `/api/language${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
    const response = await fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
    });
    return response.json();
}

export async function getInfo() {
    // Nuevo endpoint producción
    const response = await fetch("/api/info", {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
    });
    return response.json();
}

export async function bulkDeleteApplications(ids: string[]) {
    try {
        const response = await fetch(`${API_BASE_URL}/applications.php`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ bulk_delete: ids }),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error en eliminación masiva');
        }

        return result;
    } catch (error) {
        console.error('Error en bulkDeleteApplications:', error);
        throw error;
    }
}

// ==========================================
// FUNCIONES DEL CHATBOT CON IA
// ==========================================

export interface ChatMessage {
    role: 'user' | 'assistant' | 'system';
    content: string;
}

export interface ChatOptions {
    model?: string;
    temperature?: number;
}

export async function sendChatMessage(messages: ChatMessage[], options: ChatOptions = {}) {
    try {
        const response = await fetch(`${API_BASE_URL}/chatbot.php`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify({
                messages: messages,
                options: options
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Error al comunicarse con el chatbot');
        }

        return result.data.response;
    } catch (error) {
        console.error('Error en sendChatMessage:', error);
        throw error;
    }
}

