/**
 * Lazy Loading Components for Performance Optimization
 * Implements code splitting and dynamic imports for better bundle sizes
 */

import React, { Suspense, ComponentType } from 'react';

// Loading fallback component
const LoadingFallback: React.FC = () => (
    <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
            <p className="mt-4 text-gray-600">Cargando...</p>
        </div>
    </div>
);

// Generic lazy loading wrapper
export function lazyLoad<T extends ComponentType<any>>(
    importFunc: () => Promise<{ default: T }>,
    fallback: React.ComponentType = LoadingFallback
): React.ComponentType<React.ComponentProps<T>> {
    const LazyComponent = React.lazy(importFunc);

    return (props: React.ComponentProps<T>) => (
        <Suspense fallback={React.createElement(fallback)}>
            <LazyComponent {...props} />
        </Suspense>
    );
}

// Pre-configured lazy loaded components
export const LazyDashboard = lazyLoad(() => import('../pages/dashboard/CDDashboard'));
export const LazyHRDashboard = lazyLoad(() => import('../pages/dashboard/HRDashboard'));
export const LazyJobsIndex = lazyLoad(() => import('../pages/jobs/Index'));

// Authentication pages (smaller bundles)
export const LazyLogin = lazyLoad(() => import('../pages/auth/Login'));
export const LazyRegister = lazyLoad(() => import('../pages/auth/Register'));
export const LazyRegisterComplete = lazyLoad(() => import('../pages/auth/RegisterCompletePage'));

// Utility functions for route-based code splitting
export const createLazyRoute = (importFunc: () => Promise<any>) => {
    return lazyLoad(importFunc);
};

// Performance monitoring for lazy loading
export const withPerformanceTracking = <P extends object>(
    Component: React.ComponentType<P>,
    componentName: string
) => {
    return (props: P) => {
        const startTime = performance.now();

        React.useEffect(() => {
            const loadTime = performance.now() - startTime;
            console.log(`🚀 ${componentName} loaded in ${loadTime.toFixed(2)}ms`);
        }, []);

        return <Component {...props} />;
    };
};

// Preload utilities for critical components
export const preloadComponent = (importFunc: () => Promise<any>) => {
    const schedulePreload = window.requestIdleCallback ||
        ((callback: () => void) => setTimeout(callback, 1));

    schedulePreload(() => {
        importFunc().catch(() => {
            // Silently fail preloading - not critical
        });
    });
};

// Critical component preloader
export const preloadCriticalComponents = () => {
    // Preload authentication components (always needed)
    preloadComponent(() => import('../pages/auth/Login'));
    preloadComponent(() => import('../pages/auth/Register'));

    // Preload dashboard components based on user role (would be dynamic)
    preloadComponent(() => import('../pages/dashboard/CDDashboard'));
};

// Initialize preloading on app start
if (typeof window !== 'undefined') {
    // Delay preloading to not block initial render
    setTimeout(preloadCriticalComponents, 100);
}

export { LoadingFallback };
