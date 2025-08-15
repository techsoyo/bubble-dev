// 🚫 DEPRECATED: Este archivo ya no se usa - ahora se obtienen datos de la base de datos
// Los datos se obtienen directamente de las APIs de base de datos, no de localStorage

/*
import {
  departments,
  candidates,
  jobs,
  applications,
  recruiters,
  dashboardStats
// Eliminado: importación de mock-data-mvp-deprecated (carpeta eliminada)
*/

// Constante para almacenar los nombres de las claves en localStorage - YA NO SE USA
const STORAGE_KEYS = {
  DEPARTMENTS: 'bubble_talents_departments',
  CANDIDATES: 'bubble_talents_candidates',
  JOBS: 'bubble_talents_jobs',
  APPLICATIONS: 'bubble_talents_applications',
  RECRUITERS: 'bubble_talents_recruiters',
  DEPARTMENT_CATEGORIES: 'bubble_talents_department_categories',
  DASHBOARD_STATS: 'bubble_talents_dashboard_stats'
};

/**
 * 🚫 DEPRECATED: Inicializa los datos mock en localStorage si no existen
 * Ya no se usa - ahora se obtienen datos directamente de la base de datos
 */
export const initializeMockData = () => {
  // 🚫 DEPRECATED: Esta función ya no hace nada
  console.log('⚠️ DEPRECATED: initializeMockData() ya no se usa. Los datos ahora vienen de la base de datos.');

  /*
  // Código original comentado para evitar errores:
  const hasData = localStorage.getItem(STORAGE_KEYS.DEPARTMENTS) !== null;

  if (!hasData) {
    console.log('Inicializando datos mock en localStorage...');

    localStorage.setItem(STORAGE_KEYS.DEPARTMENTS, JSON.stringify(departments));
    localStorage.setItem(STORAGE_KEYS.CANDIDATES, JSON.stringify(candidates));
    localStorage.setItem(STORAGE_KEYS.JOBS, JSON.stringify(jobs));
    localStorage.setItem(STORAGE_KEYS.APPLICATIONS, JSON.stringify(applications));
    localStorage.setItem(STORAGE_KEYS.RECRUITERS, JSON.stringify(recruiters));
    localStorage.setItem(STORAGE_KEYS.DASHBOARD_STATS, JSON.stringify(dashboardStats));
    console.log('Datos mock inicializados correctamente');
  } else {
    console.log('Los datos mock ya existen en localStorage');
  }
  */
};

/**
 * 🚫 DEPRECATED: Obtiene datos mock del localStorage
 * Ya no se usa - ahora se obtienen datos directamente de la base de datos
 */
export const getMockData = <T>(_key: string): T[] => {
  console.log('⚠️ DEPRECATED: getMockData() ya no se usa. Los datos ahora vienen de la base de datos.');
  return [];
  /*
  // Código original comentado:
  const data = localStorage.getItem(key);
  return data ? JSON.parse(data) : [];
  */
};

/**
 * 🚫 DEPRECATED: Reinicia todos los datos mock (útil para testing o desarrollo)
 * Ya no se usa - ahora se obtienen datos directamente de la base de datos
 */
export const resetMockData = () => {
  console.log('⚠️ DEPRECATED: resetMockData() ya no se usa. Los datos ahora vienen de la base de datos.');

  /*
  // Código original comentado:
  Object.values(STORAGE_KEYS).forEach(key => {
    localStorage.removeItem(key);
  });

  initializeMockData();
  console.log('Datos mock reiniciados correctamente');
  */
};

/**
 * 🚫 DEPRECATED: Actualiza un conjunto específico de datos mock
 * Ya no se usa - ahora se obtienen datos directamente de la base de datos
 */
export const updateMockData = <T>(_key: string, _data: T[]) => {
  console.log('⚠️ DEPRECATED: updateMockData() ya no se usa. Los datos ahora vienen de la base de datos.');

  /*
  // Código original comentado:
  localStorage.setItem(key, JSON.stringify(data));
  */
};

// Exportamos las claves para que sean accesibles
export { STORAGE_KEYS };
