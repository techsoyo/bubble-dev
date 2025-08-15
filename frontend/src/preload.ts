/**
 * Archivo de preload para la aplicación
 * 
 * Este archivo se encarga de precargar los componentes críticos y rutas principales
 * para mejorar el rendimiento inicial.
 */

// Importamos los componentes críticos para que se carguen de inmediato
import './pages/HomePage';
import './pages/NotFound';
import './components/layout/Layout';
import './components/Login';
import './components/auth/ProtectedRoute';

// Precargamos las rutas principales
const preloadRoutes = () => {
  // Precargar rutas principales después de que la página principal haya cargado
  setTimeout(() => {
    import('./pages/jobs/Index');
    import('./pages/auth/Register');
  }, 2000);

  // Precargar rutas secundarias con menor prioridad
  setTimeout(() => {
    import('./pages/jobs/JobDetails');
    import('./pages/dashboard/CDDashboard');
  }, 5000);
};

// Iniciar precarga después de que la página haya cargado
if (typeof window !== 'undefined') {
  if (document.readyState === 'complete') {
    preloadRoutes();
  } else {
    window.addEventListener('load', preloadRoutes);
  }
}

export { };
