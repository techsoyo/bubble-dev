/**
 * Lazy Loading and Code Splitting Configuration
 * 
 * Implements React.lazy() for dynamic imports to reduce initial bundle size
 * and improve application load times through code splitting.
 * 
 * @package Performance
 * @author Bubble Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React, { Suspense } from 'react';
import { ErrorBoundary } from '../components/ErrorBoundary';

/**
 * Loading fallback component
 */
const LoadingFallback: React.FC<{ message?: string }> = ({ message = 'Loading...' }) => (
    <div className="flex items-center justify-center min-h-[200px]" role="status" aria-live="polite">
        <div className="flex flex-col items-center space-y-4">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p className="text-gray-600 text-sm">{message}</p>
        </div>
    </div>
);

/**
 * Enhanced Suspense wrapper with error boundary
 */
const LazyWrapper: React.FC<{
    children: React.ReactNode;
    fallback?: React.ReactNode;
}> = ({ children, fallback }) => (
    <ErrorBoundary>
        <Suspense fallback={fallback || <LoadingFallback />}>
            {children}
        </Suspense>
    </ErrorBoundary>
);

// ============================
// LAZY LOADED PAGES
// ============================

/**
 * Job-related pages
 */
export const JobDetails = React.lazy<React.ComponentType<any>>(() =>
    import('../pages/jobs/JobDetails').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading job details</div>
    }))
);

export const JobsIndex = React.lazy<React.ComponentType<any>>(() =>
    import('../pages/jobs/Index').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading jobs</div>
    }))
);

export const JobApply = React.lazy<React.ComponentType<any>>(() =>
    import('../pages/jobs/Apply').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading application form</div>
    }))
);

/**
 * Dashboard pages
 */
export const HRDashboard = React.lazy<React.ComponentType<any>>(() =>
    import('../pages/hr/HRDashboardPage').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading HR dashboard</div>
    }))
);

export const RecruiterDashboard = React.lazy(() =>
    import('../pages/recruiter/RecruiterDashboardPage').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading recruiter dashboard</div>
    }))
);

export const CDDashboard = React.lazy(() =>
    import('../pages/dashboard/CDDashboard').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading candidate dashboard</div>
    }))
);

/**
 * Authentication pages
 */
export const LoginPage = React.lazy(() =>
    import('../pages/auth/LoginPage').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading login page</div>
    }))
);

export const RegisterPage = React.lazy(() =>
    import('../pages/auth/RegisterPage').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading registration page</div>
    }))
);

/**
 * Profile pages
 */
export const ProfileSummary = React.lazy(() =>
    import('../pages/profile/Summary').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading profile</div>
    }))
);

// ============================
// LAZY LOADED COMPONENTS
// ============================

/**
 * Heavy components that can be lazy loaded
 */
export const UploadCV = React.lazy(() =>
    import('../components/UploadCV').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading CV upload</div>
    }))
);

export const CalendarIntegration = React.lazy(() =>
    import('../components/calendar/CalendarIntegration').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading calendar</div>
    }))
);

export const SecurityAccessibilityDemo = React.lazy(() =>
    import('../examples/SecurityAccessibilityDemo').then(module => ({
        default: module.default
    })).catch(() => ({
        default: () => <div>Error loading demo</div>
    }))
);

// ============================
// PRELOADING UTILITIES
// ============================

/**
 * Preload components for better UX
 */
export const preloadComponent = (componentImport: () => Promise<any>) => {
    const componentImportFunc = componentImport;
    componentImportFunc().catch(() => {
        // Silently handle preload errors
    });
};

/**
 * Preload critical routes
 */
const preloadCriticalRoutes = () => {
    // Preload most commonly accessed pages
    preloadComponent(() => import('../pages/jobs/Index'));
    preloadComponent(() => import('../pages/jobs/JobDetails'));
    preloadComponent(() => import('../pages/auth/LoginPage'));
};

/**
 * Hook for intersection-based preloading
 */
const useIntersectionPreload = (
    ref: React.RefObject<HTMLElement>,
    componentImport: () => Promise<any>
) => {
    React.useEffect(() => {
        const element = ref.current;
        if (!element) return undefined;

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0]?.isIntersecting) {
                    preloadComponent(componentImport);
                    observer.disconnect();
                }
            },
            { rootMargin: '100px' }
        );

        observer.observe(element);
        return () => observer.disconnect();
    }, [ref, componentImport]);
};

// ============================
// EXPORTS
// ============================

export {
    LazyWrapper,
    LoadingFallback,
    preloadCriticalRoutes,
    useIntersectionPreload
};

export default {
    // Pages
    JobDetails,
    JobsIndex,
    JobApply,
    HRDashboard,
    RecruiterDashboard,
    CDDashboard,
    LoginPage,
    RegisterPage,
    ProfileSummary,

    // Components
    UploadCV,
    CalendarIntegration,
    SecurityAccessibilityDemo,

    // Utilities
    LazyWrapper,
    LoadingFallback,
    preloadCriticalRoutes,
    useIntersectionPreload
};
