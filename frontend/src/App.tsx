/**
 * Production-Ready Application Root
 * 
 * Enhanced with accessibility, performance monitoring, error boundaries,
 * and security features for production deployment.
 * 
 * @package App
 * @author Bubble Talents Development Team
 * @version 2.0.0
 * @since 2025-01-05
 */

import { lazy, Suspense, useEffect } from 'react';
import { TooltipProvider } from './components/ui/tooltip';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Layout } from './components/layout/Layout';
import { LanguageProvider } from './lib/i18n/LanguageContext';
import { AuthProvider } from './contexts/AuthContext';
import ProtectedRoute from './components/auth/ProtectedRoute';
import Login from './components/Login';
import TalentLogin from './pages/TalentLogin';
import StaffLogin from './pages/StaffLogin';


// Páginas críticas para la carga inicial - no usar lazy loading
import HomePage from './pages/HomePage';
import NotFound from './pages/NotFound';
import LoadingSpinner from './components/ui/LoadingSpinner';

// Importaciones diferidas para páginas no críticas
const JobListingsPage = lazy(() => import('./pages/jobs/Index'));
const JobDetailsPage = lazy(() => import('./pages/jobs/JobDetails'));
const ApplyPage = lazy(() => import('./pages/jobs/Apply'));
const ProfileSummary = lazy(() => import('./pages/profile/Summary'));
const CandidateAuthPage = lazy(() => import('./pages/auth/CandidateAuthPage'));
const RegisterPage = lazy(() => import('./pages/auth/Register'));
const RegisterCompletePage = lazy(() => import('./pages/auth/RegisterCompletePage'));
const DashboardPage = lazy(() => import('./pages/dashboard/CDDashboard'));
const ApplicationDetailsPage = lazy(() => import('./pages/dashboard/ApplicationDetails'));
const HRDashboardPage = lazy(() => import('./pages/dashboard/HRDashboard'));
const RecruiterDashboardPage = lazy(() => import('./pages/dashboard/RecruiterDashboard'));
const ApiTester = lazy(() => import('./pages/ApiTester'));
const ChatBotManage = lazy(() => import('./components/ChatBotManage'));

// Componentes optimizados con lazy loading
const EstadisticasRRHH = lazy(() => import('./pages/dashboard/EstadisticasOptimized'));
const ManagerDashboard = lazy(() => import('./pages/dashboard/ManagerDashboard'));
const AdminPanel = lazy(() => import('./components/admin/AdminPanel'));

// Componentes offline-aware
import OfflineAwareSuspense, { OfflineFallback } from './components/common/OfflineAwareSuspense';

// Production-ready features
import { ErrorBoundary } from './components/ErrorBoundary';
import { initializeAccessibility } from './utils/accessibility';
import { initializePerformanceMonitoring } from './utils/performance';
import { initializeBundleAnalysis } from './utils/bundleAnalysis';
import { initializeImageOptimization } from './components/OptimizedImage';
import { PerformanceProvider } from './contexts/PerformanceContext';
import { loadAnalytics } from './utils/analytics';

// 🔧 CORRECCIÓN: Importar configuración unificada
import { isDevelopment, isProduction, showApiTester } from './config/env';

// Componentes de optimización offline
import OfflineDetector from './components/ui/OfflineDetector';

// AOS - Import dinámico compatible con Vite
let AOS: any = null;
if (typeof window !== 'undefined') {
  import('aos').then(module => {
    AOS = module;
    // Inicializar AOS cuando se carga
    if (AOS && AOS.init) {
      AOS.init({
        duration: 800,
        easing: 'ease-out',
        once: false,
        disable: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
      });
    }
  }).catch(() => console.warn('AOS failed to load'));
}
import 'aos/dist/aos.css';


// Preload critical components
import './preload';

// Performance optimized query client
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      gcTime: 10 * 60 * 1000, // 10 minutes
      retry: (failureCount, error) => {
        // Don't retry on 4xx errors
        if (error instanceof Error && 'status' in error &&
          typeof error.status === 'number' && error.status >= 400 && error.status < 500) {
          return false;
        }
        return failureCount < 3;
      },
      retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 30000),
    },
    mutations: {
      retry: 1,
    },
  },
});

/**
 * Application error fallback component
 */
function AppErrorFallback({ error, resetError }: { error?: Error; resetError: () => void }): JSX.Element {
  useEffect(() => {
    // Log error to monitoring service
    console.error('Application error:', error);
  }, [error]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8 text-center">
        <div>
          <h1 className="text-4xl font-extrabold text-gray-900">
            ¡Oops! Algo salió mal
          </h1>
          <p className="mt-4 text-lg text-gray-600">
            Ha ocurrido un error inesperado. Nuestro equipo ha sido notificado.
          </p>
          {isDevelopment && error && (
            <details className="mt-4 text-left">
              <summary className="cursor-pointer text-sm text-gray-500 hover:text-gray-700">
                Detalles del error (solo en desarrollo)
              </summary>
              <pre className="mt-2 text-xs bg-gray-100 p-4 rounded overflow-auto text-red-600">
                {error.message}
                {error.stack && '\n\nStack trace:\n' + error.stack}
              </pre>
            </details>
          )}
        </div>
        <div className="space-y-4">
          <button
            onClick={resetError}
            className="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
          >
            Intentar nuevamente
          </button>
          <button
            onClick={() => window.location.href = '/'}
            className="w-full flex justify-center py-3 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
          >
            Ir al inicio
          </button>
        </div>
      </div>
    </div>
  );
}

const App = () => {
  useEffect(() => {
    // Initialize production-ready features
    initializeAccessibility();
    initializePerformanceMonitoring();
    initializeBundleAnalysis();
    initializeImageOptimization();

    // Cargar scripts de análisis sólo en producción
    if (isProduction) {
      loadAnalytics();
    }

    // Initialize animations only if AOS is available
    if (AOS && AOS.init) {
      AOS.init({
        duration: 800,
        easing: 'ease-out',
        once: false,
        // Respect user's motion preferences
        disable: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
      });
    }

    // Add global error handler for unhandled promise rejections
    const handleUnhandledRejection = (event: PromiseRejectionEvent) => {
      console.error('Unhandled promise rejection:', event.reason);

      // Prevent default browser behavior (logging to console)
      event.preventDefault();

      // In production, you might want to report this to an error monitoring service
      if (isProduction) {
        // reportError('unhandled_promise_rejection', event.reason);
      }
    };

    // Add global error handler
    const handleError = (event: ErrorEvent) => {
      console.error('Global error:', event.error);

      if (isProduction) {
        // reportError('global_error', event.error);
      }
    };

    window.addEventListener('unhandledrejection', handleUnhandledRejection);
    window.addEventListener('error', handleError);

    // Cleanup
    return () => {
      window.removeEventListener('unhandledrejection', handleUnhandledRejection);
      window.removeEventListener('error', handleError);
    };
  }, []);

  return (
    <ErrorBoundary fallback={AppErrorFallback}>
      <PerformanceProvider>
        <QueryClientProvider client={queryClient}>
          <TooltipProvider>
            <AuthProvider>
              <LanguageProvider>
                <BrowserRouter>
                  <Routes>
                    <Route path="/" element={<Layout />}>
                      <Route index element={<HomePage />} />
                      <Route path="jobs" element={
                        <OfflineAwareSuspense
                          fallback={<LoadingSpinner size="large" />}
                          offlineFallback={<OfflineFallback message="La lista de trabajos no está disponible sin conexión." />}
                          resourceUrl="/api/jobs"
                        >
                          <JobListingsPage />
                        </OfflineAwareSuspense>
                      } />
                      <Route path="/talent/login" element={<TalentLogin />} />
                      <Route path="/candidates/login" element={
                        <Suspense fallback={<LoadingSpinner size="large" />}>
                          <CandidateAuthPage />
                        </Suspense>
                      } />
                      <Route path="/staff/login" element={<StaffLogin />} />
                      <Route path="jobs/:id" element={
                        <OfflineAwareSuspense
                          fallback={<LoadingSpinner size="large" />}
                          offlineFallback={<OfflineFallback message="Los detalles del trabajo no están disponibles sin conexión." />}
                        >
                          <JobDetailsPage />
                        </OfflineAwareSuspense>
                      } />
                      <Route path="jobs/apply" element={
                        <Suspense fallback={<LoadingSpinner size="large" />}>
                          <ApplyPage />
                        </Suspense>
                      } />
                      <Route path="profile/summary" element={
                        <Suspense fallback={<LoadingSpinner size="large" />}>
                          <ProfileSummary />
                        </Suspense>
                      } />
                      <Route path="auth/login" element={<Login />} />
                      {/* Redirección de /login a /auth/login para mayor comodidad */}
                      <Route path="login" element={<Navigate to="/auth/login" replace />} />
                      <Route path="test-chatbot" element={
                        <Suspense fallback={<LoadingSpinner size="large" />}>
                          <ChatBotManage />
                        </Suspense>
                      } />
                      <Route path="candidates/login" element={
                        <Suspense fallback={<LoadingSpinner size="large" />}>
                          <CandidateAuthPage />
                        </Suspense>
                      } />
                      <Route path="candidates/login-old" element={
                        <Suspense fallback={<LoadingSpinner size="large" />}>
                          <RegisterPage />
                        </Suspense>
                      } />
                      <Route path="candidates/login-complete" element={
                        <Suspense fallback={<LoadingSpinner size="large" />}>
                          <RegisterCompletePage />
                        </Suspense>
                      } />
                      <Route path="dashboard/cddashboard" element={
                        <Suspense fallback={<LoadingSpinner size="large" />}>
                          <DashboardPage />
                        </Suspense>
                      } />
                      <Route path="dashboard/applications/:id" element={
                        <Suspense fallback={<LoadingSpinner size="large" />}>
                          <ApplicationDetailsPage />
                        </Suspense>
                      } />
                      <Route
                        path="dashboard/hrdashboard"
                        element={
                          <ProtectedRoute requiredRole="admin">
                            <Suspense fallback={<LoadingSpinner size="large" />}>
                              <HRDashboardPage />
                            </Suspense>
                          </ProtectedRoute>
                        }
                      />
                      <Route
                        path="dashboard/recruiterdashboard"
                        element={
                          <ProtectedRoute requiredRole="recruiter">
                            <Suspense fallback={<LoadingSpinner size="large" />}>
                              <RecruiterDashboardPage />
                            </Suspense>
                          </ProtectedRoute>
                        }
                      />
                      <Route
                        path="dashboard/estadisticas"
                        element={
                          <ProtectedRoute requiredRole="admin">
                            <Suspense fallback={<LoadingSpinner size="large" />}>
                              <EstadisticasRRHH />
                            </Suspense>
                          </ProtectedRoute>
                        }
                      />
                      <Route
                        path="dashboard/manager"
                        element={
                          <ProtectedRoute requiredRole="admin">
                            <Suspense fallback={<LoadingSpinner size="large" />}>
                              <ManagerDashboard />
                            </Suspense>
                          </ProtectedRoute>
                        }
                      />
                      <Route
                        path="admin"
                        element={
                          <ProtectedRoute requiredRole="admin">
                            <Suspense fallback={<LoadingSpinner size="large" />}>
                              <AdminPanel />
                            </Suspense>
                          </ProtectedRoute>
                        }
                      />
                      {showApiTester && (
                        <Route path="apitester" element={
                          <Suspense fallback={<LoadingSpinner size="large" />}>
                            <ApiTester />
                          </Suspense>
                        } />
                      )}
                      <Route path="*" element={<NotFound />} />
                    </Route>
                  </Routes>
                </BrowserRouter>
                <OfflineDetector />
              </LanguageProvider>
            </AuthProvider>
          </TooltipProvider>
        </QueryClientProvider>
      </PerformanceProvider>
    </ErrorBoundary>
  );
};

export default App;