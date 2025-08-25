/**
 * API Service - Servicio centralizado para peticiones HTTP con JWT
 * 
 * @package Services
 * @author Bubble Talents Development Team
 * @version 2.0.0
 */

import { env } from '@/config/env';
import { TokenManager } from '@/lib/auth/tokenManager';

export interface ApiRequestOptions extends RequestInit {
  useCredentials?: boolean;
  timeout?: number;
  requireAuth?: boolean; // NUEVO: Indica si requiere autenticación
}

export class ApiService {
  private static readonly baseUrl = env.API_BASE_URL;
  private static readonly defaultTimeout = 30000;

  /**
   * Construye URL completa para endpoint API
   */
  static buildUrl(endpoint: string): string {
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;

    if (this.baseUrl.endsWith('/api')) {
      return `${this.baseUrl}/${cleanEndpoint}`;
    }

    if (cleanEndpoint.startsWith('api/')) {
      return `${this.baseUrl}/${cleanEndpoint}`;
    }

    return `${this.baseUrl}/api/${cleanEndpoint}`;
  }

  /**
   * Realiza petición HTTP con JWT authentication automática
   */
  static async request<T = any>(
    endpoint: string,
    options: ApiRequestOptions = {}
  ): Promise<T> {
    const {
      useCredentials = true,
      timeout = this.defaultTimeout,
      requireAuth = true, // Por defecto requiere autenticación
      headers = {},
      ...fetchOptions
    } = options;

    const url = this.buildUrl(endpoint);

    // Preparar headers base
    const bodyIsFormData =
      (fetchOptions as any)?.body instanceof FormData;

    // Empezar desde los headers entrantes
    let requestHeaders: HeadersInit = { ...headers };

    // ¿Ya hay Content-Type?
    const hasContentType =
      (requestHeaders instanceof Headers && requestHeaders.has('Content-Type')) ||
      (!!(requestHeaders as any)['Content-Type']);

    // Solo imponemos JSON si NO es FormData y no hay Content-Type definido
    if (!bodyIsFormData && !hasContentType) {
      requestHeaders = {
        'Content-Type': 'application/json',
        ...requestHeaders,
      };
    }


    // AÑADIR JWT TOKEN SI ES REQUERIDO
    if (requireAuth) {
      // Verificar si necesitamos renovar el token
      if (TokenManager.isTokenExpired()) {
        const refreshed = await TokenManager.refreshAccessToken();
        if (!refreshed) {
          // Token expirado y no se pudo renovar - redirigir al login
          window.dispatchEvent(new CustomEvent('auth-expired'));
          throw new Error('Authentication expired');
        }
      }

      // En producción, la autenticación se maneja completamente via cookies httpOnly
      // No se envían Authorization headers para máxima seguridad
      if (requireAuth) {
        console.log('Using httpOnly cookies for authentication');
      }
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);

    try {
      const response = await fetch(url, {
        ...fetchOptions,
        headers: requestHeaders,
        credentials: useCredentials ? 'include' : 'omit',
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      // Manejar 401 con renovación automática de token
      if (response.status === 401 && requireAuth) {
        // Intentar renovar token y reintentar
        const refreshed = await TokenManager.refreshAccessToken();
        if (refreshed) {
          const newToken = TokenManager.getAccessToken();
          const retryHeaders = {
            ...requestHeaders,
            // Note: In production, rely on cookies instead of Authorization header
          };

          const retryResponse = await fetch(url, {
            ...fetchOptions,
            headers: retryHeaders,
            credentials: useCredentials ? 'include' : 'omit',
          });

          if (retryResponse.ok) {
            return await retryResponse.json();
          }
        }

        // Si llega aquí, autenticación definitivamente falló
        window.dispatchEvent(new CustomEvent('auth-expired'));
        throw new Error('Authentication failed');
      }

      if (!response.ok) {
        // Mejorar manejo de errores con más detalle
        const errorData = await response.text();
        let errorMessage = `HTTP ${response.status}: ${response.statusText}`;

        try {
          const errorJson = JSON.parse(errorData);
          errorMessage = errorJson.message || errorMessage;
        } catch (e) {
          // Si no es JSON, usar el texto como está
          errorMessage = errorData || errorMessage;
        }

        throw new Error(errorMessage);
      }

      return await response.json();
    } catch (error) {
      clearTimeout(timeoutId);

      if (error instanceof Error) {
        if (error.name === 'AbortError') {
          throw new Error(`Request timeout after ${timeout}ms`);
        }
        throw error;
      }

      throw new Error('Unknown error occurred');
    }
  }

  /**
   * GET request con autenticación automática
   */
  static get<T = any>(
    endpoint: string,
    options: Omit<ApiRequestOptions, 'method' | 'body'> = {}
  ): Promise<T> {
    return this.request<T>(endpoint, { ...options, method: 'GET' });
  }

  /**
   * POST request con autenticación automática
   */
  static post<T = any>(
    endpoint: string,
    data: any,
    options: Omit<ApiRequestOptions, 'method'> = {}
  ): Promise<T> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  /**
   * Métodos públicos sin autenticación
   */
  static publicGet<T = any>(endpoint: string, options: Omit<ApiRequestOptions, 'method' | 'body' | 'requireAuth'> = {}): Promise<T> {
    return this.request<T>(endpoint, { ...options, method: 'GET', requireAuth: false });
  }

  static publicPost<T = any>(endpoint: string, data: any, options: Omit<ApiRequestOptions, 'method' | 'requireAuth'> = {}): Promise<T> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'POST',
      body: JSON.stringify(data),
      requireAuth: false
    });
  }

  // ... resto de métodos PUT, DELETE, upload igual pero con requireAuth

  /**
 * PUT request con autenticación automática
 */
  static put<T = any>(
    endpoint: string,
    data: any,
    options: Omit<ApiRequestOptions, 'method'> = {}
  ): Promise<T> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  /**
   * DELETE request con autenticación automática
   * - Si tu backend acepta body en DELETE, envíalo; si no, quítalo.
   */
  static delete<T = any>(
    endpoint: string,
    options: Omit<ApiRequestOptions, 'method'> = {}
  ): Promise<T> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'DELETE',
    });
  }

  /**
   * UPLOAD (multipart/form-data) con autenticación automática
   * - No seteamos 'Content-Type' para que el navegador añada el boundary.
   */
  static upload<T = any>(
    endpoint: string,
    formData: FormData,
    options: Omit<ApiRequestOptions, 'method' | 'body'> = {}
  ): Promise<T> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'POST',
      body: formData as any, // request() ya detecta FormData y no fuerza JSON
    });
  }

  static publicUpload<T = any>(
    endpoint: string,
    formData: FormData,
    options: Omit<ApiRequestOptions, 'method' | 'body' | 'requireAuth'> = {}
  ): Promise<T> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'POST',
      body: formData as any,
      requireAuth: false,
    });
  }

}

export const api = ApiService;
export default ApiService;

/* =======================================================================================
 * COMPAT LAYER — funciones legacy exportadas desde el servicio nuevo (JWT/refresh/timeout)
 * ======================================================================================= */

// ---------- JOBS ----------
export async function getJobs(params?: Record<string, any>) {
  const qs = params ? `?${new URLSearchParams(params as any).toString()}` : '';
  return await ApiService.publicGet(`jobs.php${qs}`);
}
export async function getJob(id: string | number) {
  return await ApiService.publicGet(`jobs.php?id=${id}`);
}
export async function createJob(data: any) {
  return await ApiService.post('jobs.php', data);
}
export async function updateJob(id: string | number, data: any) {
  return await ApiService.put(`jobs/${id}`, data);
}
export async function deleteJob(id: string | number) {
  return await ApiService.delete(`jobs/${id}`);
}
export async function getJobRequirements(jobId: string | number) {
  return await ApiService.get(`job_requirements.php?jobId=${jobId}`);
}
export async function getJobBenefits(jobId: string | number) {
  return await ApiService.get(`job_benefits.php?jobId=${jobId}`);
}
export async function getJobSkills(jobId: string | number) {
  return await ApiService.get(`job_skills.php?jobId=${jobId}`);
}

// ---------- CANDIDATES ----------
export async function getCandidates(params?: Record<string, any>) {
  const qs = params ? `?${new URLSearchParams(params as any).toString()}` : '';
  return await ApiService.get(`candidates.php${qs}`);
}
export async function getCandidate(id: string | number) {
  return await ApiService.get(`candidates.php?id=${id}`);
}
export async function createCandidate(data: any) {
  return await ApiService.post('candidates.php', data);
}
export async function updateCandidate(id: string | number, data: any) {
  return await ApiService.put(`candidates/${id}`, data);
}
export async function deleteCandidate(id: string | number) {
  return await ApiService.delete(`candidates/${id}`);
}
export async function saveCandidateFromAI(data: any) {
  return await ApiService.post('candidates/save-from-ai.php', data);
}

export async function updateApplication(id: string | number, data: any) {
  return await ApiService.put(`applications/${id}`, data);
}
export async function deleteApplication(id: string | number) {
  return await ApiService.delete(`applications/${id}`);
}

export async function changeCandidateStatus(data: { candidato_id: string | number; nuevo_estado: string; motivo?: string }) {
  return await ApiService.put(`applications/${data.candidato_id}/status`, {
    status: data.nuevo_estado,
    reason: data.motivo
  });
}

// ---------- AUTH ----------
export async function registerCandidate(data: any) {
  return await ApiService.publicPost('auth/register.php', data);
}


// ---------- NOTIFICATIONS ----------
export async function getNotificationTemplates() {
  return await ApiService.get('notifications/templates.php');
}
export async function sendNotification(data: {
  recipient_id: string;
  recipient_type: 'candidate' | 'recruiter' | 'admin';
  template_id: string;
  variables?: Record<string, string>;
  channel?: 'email' | 'sms' | 'push' | 'in-app';
  scheduled_for?: string;
}) {
  return await ApiService.post('notifications/send.php', data);
}

// ---------- CALENDAR ----------
export async function getCalendarEvents(params: { userId: string; userType: 'candidate' | 'recruiter' | 'admin'; startDate: string; endDate: string }) {
  const qs = `?${new URLSearchParams(params as any).toString()}`;
  return await ApiService.get(`calendar/events${qs}`);
}
export async function scheduleInterview(data: {
  application_id: string;
  title: string;
  description?: string;
  start_time: string;
  end_time: string;
  attendees: Array<{ id: string; email: string; role: 'candidate' | 'recruiter' | 'hr' }>;
  location?: string;
}) {
  return await ApiService.post('calendar/schedule-interview', data);
}
export async function getAvailableSlots(params: { recruiter_id: string; start_date: string; end_date: string }) {
  const qs = `?${new URLSearchParams(params as any).toString()}`;
  return await ApiService.get(`calendar/available-slots${qs}`);
}

// ---------- RECRUITER / ASSIGNMENTS ----------
export async function getRecruiterDashboardStats(recruiter_id: string) {
  return await ApiService.get(`recruiter/${recruiter_id}/dashboard-stats`);
}
export async function getAssignedCandidates(recruiter_id?: string) {
  const endpoint = recruiter_id ? `recruiters/${recruiter_id}/assigned-candidates` : 'recruiters/assigned-candidates';
  return await ApiService.get(endpoint);
}



// ---------- ALIAS ÚTIL ----------
export async function getJobsForTable(params?: Record<string, any>) {
  return await getJobs(params);
}

// ========================= PARCHE COMPAT AMPLIADO =========================

// ---- CALENDAR ----
export async function getCalendarIntegrations(userId: string, userType: 'candidate' | 'recruiter' | 'admin') {
  const qs = `?${new URLSearchParams({ userId, userType }).toString()}`;
  return await ApiService.get(`calendar/integrations${qs}`);
}

// Firmas antiguas (3 args) y nueva (objeto)
export async function connectCalendar(
  userId: string, userType: 'candidate' | 'recruiter' | 'admin', provider: 'google' | 'microsoft' | 'microsoft365'
): Promise<any>;
export async function connectCalendar(
  payload: { userId: string; userType: 'candidate' | 'recruiter' | 'admin'; provider: 'google' | 'microsoft' | 'microsoft365'; auth_code?: string }
): Promise<any>;
export async function connectCalendar(a: any, b?: any, c?: any): Promise<any> {
  const body = typeof a === 'object' ? a : { userId: a as string, userType: b, provider: c };
  return await ApiService.post('calendar/connect', body);
}

export async function disconnectCalendar(
  userId: string, userType: 'candidate' | 'recruiter' | 'admin', provider: 'google' | 'microsoft'
): Promise<any>;
export async function disconnectCalendar(
  payload: { userId: string; userType: 'candidate' | 'recruiter' | 'admin'; provider: 'google' | 'microsoft' }
): Promise<any>;
export async function disconnectCalendar(a: any, b?: any, c?: any): Promise<any> {
  const body = typeof a === 'object' ? a : { userId: a as string, userType: b, provider: c };
  return await ApiService.post('calendar/disconnect', body);
}

// ---- APPLICATIONS ----
export async function getApplications(params?: Record<string, any>) {
  const qs = params ? `?${new URLSearchParams(params as any).toString()}` : '';
  return await ApiService.get(`applications.php${qs}`);
}

export async function submitApplication(data: any) {
  // Acepta payloads antiguos (nombre, email, cv_url...) pero envía lo que sí usa el backend
  const safe = {
    job_id: data?.job_id,
    cover_letter: data?.cover_letter || '',
    ...(data?.cv_url ? { cv_url: data.cv_url } : {}),
    ...(data?.email ? { email: data.email } : {}),
    ...(data?.nombre ? { nombre: data.nombre } : {}),
  };
  return await ApiService.post('applications.php', safe);

}
// Usar POST para actualizaciones en lote
export async function bulkUpdateApplications(applications: Array<any>) {
  // Usar POST para operaciones en lote
  return await ApiService.post('applications.php', { bulk_update: applications });
}

// ---- AUTH ----
// ---- SOCIAL LOGIN (2 args o objeto) ----
export async function socialLogin(
  provider: 'google' | 'linkedin' | 'apple', code: string
): Promise<any>;
export async function socialLogin(
  payload: { provider: 'google' | 'linkedin' | 'apple'; code: string; redirect_uri?: string }
): Promise<any>;
export async function socialLogin(a: any, b?: any): Promise<any> {
  const body = typeof a === 'object'
    ? a
    : {
      provider: a as 'google' | 'linkedin' | 'apple',
      code: b as string,
      redirect_uri: `${window.location.origin}/auth/callback/${a}`
    };
  return await ApiService.publicPost('auth/social-login.php', body);
}

export async function changeCandidatePassword(data: {
  current_password: string;
  new_password: string;
  new_password_confirmation?: string;
  candidate_id?: string;
}) {
  // candidate_id permitido pero ignorado por backend típico de cambio de password
  const { current_password, new_password, new_password_confirmation } = data;
  return await ApiService.post('change-password.php', {
    current_password,
    new_password,
    ...(new_password_confirmation ? { new_password_confirmation } : {}),
  });
}


// ---- HR / DASHBOARDS ----
export async function getHRDashboardStats() {
  return await ApiService.get('dashboard/hr-stats.php');
}

// ---- CANDIDATE EXTRAS ----
export async function getCandidateSkills(candidateId: string | number) {
  return await ApiService.get(`candidate_skills.php?candidateId=${candidateId}`);
}
export async function getCandidateExperiences(candidateId: string | number) {
  return await ApiService.get(`candidate-experiences.php?candidate_id=${candidateId}`);
}
export async function getCandidateApplications(candidateId: string | number) {
  return await ApiService.get(`candidate-applications.php?candidate_id=${candidateId}`);
}


// ---- PROFILE UPDATE (dos firmas: con id y sin id) ----
export async function updateCandidateProfile(
  candidateId: string | number, profileData: any
): Promise<any>;
export async function updateCandidateProfile(
  profileData: any
): Promise<any>;
export async function updateCandidateProfile(
  a: any, b?: any
): Promise<any> {
  if (b !== undefined) {
    return await ApiService.put(`candidates/${a}`, b);
  }
  // Ruta alternativa legacy si no pasas id
  return await ApiService.post('update-candidate-profile.php', a);
}

// ---- RECRUITERS LIST ----
export async function getRecruiters(params?: Record<string, any>) {
  const qs = params ? `?${new URLSearchParams(params as any).toString()}` : '';
  return await ApiService.get(`recruiters${qs}`);
}

// ---- INTERVIEWS ----
export async function getInterviews(params?: { applicationId?: string; page?: number; limit?: number }) {
  const q = new URLSearchParams();
  if (params?.applicationId) q.append('applicationId', String(params.applicationId));
  if (params?.page != null) q.append('page', String(params.page));
  if (params?.limit != null) q.append('limit', String(params.limit));
  const qs = q.toString() ? `?${q.toString()}` : '';
  return await ApiService.get(`interviews.php${qs}`);
}

// ---- UPLOAD CV (acepta FormData o (file, candidate_id)) ----
export async function uploadCV(formData: FormData): Promise<any>;
export async function uploadCV(file: File, candidate_id: string | number): Promise<any>;
export async function uploadCV(a: any, b?: any): Promise<any> {
  if (a instanceof FormData) {
    return await ApiService.upload('analyze_cv.php', a);
  }
  const fd = new FormData();
  fd.append('cv', a as File);
  if (b !== undefined) fd.append('candidate_id', String(b));
  return await ApiService.upload('analyze_cv.php', fd);
}

