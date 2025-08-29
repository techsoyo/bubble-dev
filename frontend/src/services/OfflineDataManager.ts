/**
 * OfflineDataManager
 * 
 * Gestiona el almacenamiento y recuperación de datos críticos para uso offline.
 * Utiliza IndexedDB nativo para almacenar datos de manera eficiente y persistente.
 */

// Interfaces para los datos que almacenaremos
interface JobData {
  id: number;
  title: string;
  description: string;
  company: string;
  location: string;
  salary: string;
  createdAt: string;
  updatedAt: string;
  expiresAt: string;
  requirements: string[];
  responsibilities: string[];
  skills: string[];
  benefits: string[];
  applicationCount: number;
  isActive: boolean;
  category: string;
  type: string;
  level: string;
  lastSyncedAt: string;
}

interface FormDraft<T = unknown> {
  formId: string;
  formData: T;
  lastModified: string;
}

interface ApiCacheItem<T = unknown> {
  url: string;
  data: T;
  expiresAt: number;
}

interface PendingRequest<T = unknown> {
  id?: string;
  url: string;
  method: string;
  body: T;
  headers: Record<string, string>;
  createdAt: number;
  retryCount: number;
  lastRetry: number | null;
}

class OfflineDataManager {
  private db: IDBDatabase | null = null;
  private readonly DB_NAME = 'bubble-talents-offline';
  private readonly DB_VERSION = 1;

  constructor() {
    this.initDB();
  }

  // Inicializar la base de datos
  private initDB(): Promise<IDBDatabase> {
    if (this.db) {
      return Promise.resolve(this.db);
    }
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.DB_NAME, this.DB_VERSION);

      request.onerror = () => {
        reject(new Error('Error al abrir la base de datos'));
      };

      request.onsuccess = () => {
        this.db = request.result;
        resolve(this.db);
      };

      request.onupgradeneeded = (event) => {
        const db = (event.target as IDBOpenDBRequest).result;

        // Crear almacén para trabajos
        if (!db.objectStoreNames.contains('jobs')) {
          const jobsStore = db.createObjectStore('jobs', { keyPath: 'id' });
          jobsStore.createIndex('by-category', 'category', { unique: false });
          jobsStore.createIndex('by-created', 'createdAt', { unique: false });
        }

        // Crear almacén para datos del usuario
        if (!db.objectStoreNames.contains('user-data')) {
          db.createObjectStore('user-data');
        }

        // Crear almacén para borradores de formularios
        if (!db.objectStoreNames.contains('form-drafts')) {
          db.createObjectStore('form-drafts', { keyPath: 'formId' });
        }

        // Crear almacén para caché de API
        if (!db.objectStoreNames.contains('api-cache')) {
          db.createObjectStore('api-cache', { keyPath: 'url' });
        }

        // Crear almacén para solicitudes pendientes
        if (!db.objectStoreNames.contains('pending-requests')) {
          db.createObjectStore('pending-requests', { keyPath: 'id', autoIncrement: true });
        }
      };
    });
  }

  // Guardar trabajos para uso offline
  async saveJobs(jobs: JobData[]): Promise<void> {
    // Validar entrada
    if (!Array.isArray(jobs)) {
      throw new Error('Jobs must be an array');
    }

    if (jobs.length === 0) {
      console.warn('No jobs to save');
      return;
    }

    // Validar estructura de cada job
    const validatedJobs = jobs.map(job => this.validateJobData(job));

    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('jobs', 'readwrite');
      const store = tx.objectStore('jobs');
      let completed = 0;
      let hasErrors = false;

      const jobsWithTimestamp = validatedJobs.map(job => ({
        ...job,
        lastSyncedAt: new Date().toISOString()
      }));

      jobsWithTimestamp.forEach(job => {
        const req = store.put(job);
        req.onerror = () => {
          console.error('Error saving job:', job.id, req.error);
          hasErrors = true;
          if (!hasErrors) reject(req.error);
        };
        req.onsuccess = () => {
          completed++;
          if (completed === jobsWithTimestamp.length && !hasErrors) {
            resolve();
          }
        };
      });

      tx.onerror = () => {
        console.error('Transaction error:', tx.error);
        reject(tx.error);
      };
    });
  }

  // Obtener todos los trabajos almacenados
  async getJobs(): Promise<JobData[]> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('jobs', 'readonly');
      const store = tx.objectStore('jobs');
      const req = store.getAll();
      req.onsuccess = () => {
        resolve(req.result);
      };
      req.onerror = () => {
        reject(req.error);
      };
    });
  }

  // Obtener trabajos por categoría
  async getJobsByCategory(category: string): Promise<JobData[]> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('jobs', 'readonly');
      const store = tx.objectStore('jobs');
      const index = store.index('by-category');
      const req = index.getAll(category);
      req.onsuccess = () => {
        resolve(req.result);
      };
      req.onerror = () => {
        reject(req.error);
      };
    });
  }

  // Guardar datos del usuario
  async saveUserData<T = unknown>(key: string, data: T): Promise<void> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('user-data', 'readwrite');
      const store = tx.objectStore('user-data');
      const req = store.put(data, key);
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  // Obtener datos del usuario
  async getUserData<T = unknown>(key: string): Promise<T | undefined> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('user-data', 'readonly');
      const store = tx.objectStore('user-data');
      const req = store.get(key);
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  // Guardar borrador de formulario
  async saveFormDraft<T = unknown>(formId: string, formData: T): Promise<void> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('form-drafts', 'readwrite');
      const store = tx.objectStore('form-drafts');
      const req = store.put({
        formId,
        formData,
        lastModified: new Date().toISOString()
      });
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  // Obtener borrador de formulario
  async getFormDraft<T = unknown>(formId: string): Promise<FormDraft<T> | undefined> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('form-drafts', 'readonly');
      const store = tx.objectStore('form-drafts');
      const req = store.get(formId);
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  // Eliminar borrador de formulario
  async deleteFormDraft(formId: string): Promise<void> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('form-drafts', 'readwrite');
      const store = tx.objectStore('form-drafts');
      const req = store.delete(formId);
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  // Guardar datos en caché para uso offline (con expiración)
  async cacheApiResponse<T = unknown>(url: string, data: T, ttlMinutes: number = 60): Promise<void> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('api-cache', 'readwrite');
      const store = tx.objectStore('api-cache');
      const expiresAt = Date.now() + (ttlMinutes * 60 * 1000);
      const req = store.put({ url, data, expiresAt });
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  // Obtener datos de caché
  async getCachedApiResponse<T = unknown>(url: string): Promise<T | null> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('api-cache', 'readwrite');
      const store = tx.objectStore('api-cache');
      const req = store.get(url);
      req.onsuccess = () => {
        const cachedData = req.result;
        if (!cachedData) {
          resolve(null);
          return;
        }
        if (cachedData.expiresAt < Date.now()) {
          store.delete(url);
          resolve(null);
        } else {
          resolve(cachedData.data);
        }
      };
      req.onerror = () => reject(req.error);
    });
  }

  // Registrar una solicitud pendiente para ejecutar cuando haya conexión
  async addPendingRequest<T = unknown>(request: Omit<PendingRequest<T>, 'id' | 'createdAt' | 'retryCount' | 'lastRetry'>): Promise<string> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('pending-requests', 'readwrite');
      const store = tx.objectStore('pending-requests');
      const req = store.add({
        ...request,
        createdAt: Date.now(),
        retryCount: 0,
        lastRetry: null
      });
      req.onsuccess = () => resolve(req.result.toString());
      req.onerror = () => reject(req.error);
    });
  }

  // Obtener solicitudes pendientes
  async getPendingRequests<T = unknown>(): Promise<PendingRequest<T>[]> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('pending-requests', 'readonly');
      const store = tx.objectStore('pending-requests');
      const req = store.getAll();
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  // Marcar una solicitud pendiente como completada
  async removePendingRequest(id: string): Promise<void> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('pending-requests', 'readwrite');
      const store = tx.objectStore('pending-requests');
      const req = store.delete(Number(id));
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  // Limpiar caché expirada
  async cleanExpiredCache(): Promise<void> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('api-cache', 'readwrite');
      const store = tx.objectStore('api-cache');
      const req = store.getAll();
      req.onsuccess = () => {
        const now = Date.now();
        const expiredKeys = req.result.filter((item: { expiresAt: number }) => item.expiresAt < now).map((item: { url: string }) => item.url);
        let deleted = 0;
        if (expiredKeys.length === 0) {
          resolve();
          return;
        }
        expiredKeys.forEach((key: string) => {
          const delReq = store.delete(key);
          delReq.onsuccess = () => {
            deleted++;
            if (deleted === expiredKeys.length) {
              resolve();
            }
          };
          delReq.onerror = () => {
            reject(delReq.error);
          };
        });
      };
      req.onerror = () => reject(req.error);
    });
  }

  /**
   * Verifica si hay conectividad a internet
   */
  async isOnline(): Promise<boolean> {
    try {
      // Intentar hacer una petición HEAD a un endpoint ligero
      const response = await fetch('/api/health', {
        method: 'HEAD',
        cache: 'no-cache',
        signal: AbortSignal.timeout(5000) // Timeout de 5 segundos
      });
      return response.ok;
    } catch (error) {
      console.warn('Connectivity check failed:', error);
      return false;
    }
  }

  /**
   * Obtiene estadísticas de uso del almacenamiento offline
   */
  async getStorageStats(): Promise<{
    jobsCount: number;
    draftsCount: number;
    cacheItemsCount: number;
    pendingRequestsCount: number;
    totalSize: number;
  }> {
    try {
      const db = await this.initDB();

      const [jobs, drafts, cache, pending] = await Promise.all([
        this.getJobs(),
        this.getFormDrafts(),
        this.getCacheItems(),
        this.getPendingRequests()
      ]);

      // Estimación aproximada del tamaño (muy básica)
      const totalSize = JSON.stringify({ jobs, drafts, cache, pending }).length;

      return {
        jobsCount: jobs.length,
        draftsCount: drafts.length,
        cacheItemsCount: cache.length,
        pendingRequestsCount: pending.length,
        totalSize
      };
    } catch (error) {
      console.error('Error getting storage stats:', error);
      throw new Error('Failed to get storage statistics');
    }
  }

  /**
   * Limpia todos los datos expirados
   */
  async cleanup(): Promise<void> {
    try {
      await Promise.all([
        this.cleanExpiredCache(),
        this.clearExpiredJobs()
      ]);
      console.log('Offline storage cleanup completed');
    } catch (error) {
      console.error('Error during cleanup:', error);
      throw new Error('Failed to cleanup offline storage');
    }
  }

  /**
   * Método auxiliar para obtener borradores de formulario
   */
  private async getFormDrafts(): Promise<FormDraft[]> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('form-drafts', 'readonly');
      const store = tx.objectStore('form-drafts');
      const req = store.getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => reject(req.error);
    });
  }

  /**
   * Método auxiliar para obtener elementos de caché
   */
  private async getCacheItems(): Promise<ApiCacheItem[]> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('api-cache', 'readonly');
      const store = tx.objectStore('api-cache');
      const req = store.getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => reject(req.error);
    });
  }

  /**
   * Limpia trabajos expirados
   */
  private async clearExpiredJobs(): Promise<void> {
    const db = await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('jobs', 'readwrite');
      const store = tx.objectStore('jobs');
      const req = store.getAll();

      req.onsuccess = () => {
        const now = new Date().toISOString();
        const expiredJobs = req.result.filter((job: JobData) => job.expiresAt < now);

        if (expiredJobs.length === 0) {
          resolve();
          return;
        }

        let deleted = 0;
        expiredJobs.forEach((job: JobData) => {
          const delReq = store.delete(job.id);
          delReq.onsuccess = () => {
            deleted++;
            if (deleted === expiredJobs.length) {
              resolve();
            }
          };
          delReq.onerror = () => reject(delReq.error);
        });
      };

      req.onerror = () => reject(req.error);
    });
  }
  private validateJobData(job: any): JobData {
    if (!job || typeof job !== 'object') {
      throw new Error('Job data must be a valid object');
    }

    // Validar campos requeridos
    if (!job.id || typeof job.id !== 'number') {
      throw new Error('Job must have a valid numeric id');
    }

    if (!job.title || typeof job.title !== 'string' || job.title.trim().length === 0) {
      throw new Error('Job must have a valid title');
    }

    if (!job.description || typeof job.description !== 'string') {
      throw new Error('Job must have a valid description');
    }

    // Validar y sanitizar arrays
    const validatedJob: JobData = {
      id: job.id,
      title: job.title.trim(),
      description: job.description.trim(),
      company: job.company || 'Unknown Company',
      location: job.location || 'Remote',
      salary: job.salary || 'Not specified',
      createdAt: job.createdAt || new Date().toISOString(),
      updatedAt: job.updatedAt || new Date().toISOString(),
      expiresAt: job.expiresAt || new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(), // 30 días por defecto
      requirements: Array.isArray(job.requirements) ? job.requirements : [],
      responsibilities: Array.isArray(job.responsibilities) ? job.responsibilities : [],
      skills: Array.isArray(job.skills) ? job.skills : [],
      benefits: Array.isArray(job.benefits) ? job.benefits : [],
      applicationCount: typeof job.applicationCount === 'number' ? job.applicationCount : 0,
      isActive: typeof job.isActive === 'boolean' ? job.isActive : true,
      category: job.category || 'General',
      type: job.type || 'Full-time',
      level: job.level || 'Mid-level',
      lastSyncedAt: job.lastSyncedAt || new Date().toISOString()
    };

    return validatedJob;
  }

  /**
   * Valida datos de formulario
   */
  private validateFormData<T>(formData: any): T {
    if (!formData || typeof formData !== 'object') {
      throw new Error('Form data must be a valid object');
    }

    // Validación básica de estructura
    if (Object.keys(formData).length === 0) {
      throw new Error('Form data cannot be empty');
    }

    return formData as T;
  }

  /**
   * Valida elementos de caché de API
   */
  private validateCacheItem<T>(item: any): ApiCacheItem<T> {
    if (!item || typeof item !== 'object') {
      throw new Error('Cache item must be a valid object');
    }

    if (!item.url || typeof item.url !== 'string') {
      throw new Error('Cache item must have a valid URL');
    }

    if (!item.data) {
      throw new Error('Cache item must have data');
    }

    return {
      url: item.url,
      data: item.data,
      expiresAt: item.expiresAt || Date.now() + (24 * 60 * 60 * 1000) // 24 horas por defecto
    };
  }
}

// Exportar una instancia única para toda la aplicación
export const offlineDataManager = new OfflineDataManager();
