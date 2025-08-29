/**
 * Performance Optimization Utilities
 * 
 * Provides comprehensive performance optimization features for production-ready
 * React applications including bundle analysis, memory optimization, and 
 * service worker management.
 * 
 * @package Performance
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import { useMemo, useCallback, useEffect, useState } from 'react';

/**
 * Performance metrics interface
 */
interface PerformanceMetrics {
    fcp: number; // First Contentful Paint
    lcp: number; // Largest Contentful Paint
    fid: number; // First Input Delay
    cls: number; // Cumulative Layout Shift
    ttfb: number; // Time to First Byte
}

/**
 * Code splitting and lazy loading utilities
 */
export class CodeSplitting {
    /**
     * Preload a component for better user experience
     */
    static preloadComponent<T = unknown>(importFn: () => Promise<T>): void {
        // Start loading the component but don't do anything with it
        importFn().catch(error => {
            console.warn('Failed to preload component:', error);
        });
    }
}

/**
 * Image optimization utilities
 */
export class ImageOptimization {
    /**
     * Create responsive image with lazy loading
     */
    static createResponsiveImage(
        src: string,
        sizes: { width: number; height: number }[] = [
            { width: 320, height: 240 },
            { width: 640, height: 480 },
            { width: 1024, height: 768 },
            { width: 1920, height: 1080 }
        ]
    ): string {
        const srcSet = sizes
            .map(size => `${src}?w=${size.width}&h=${size.height} ${size.width}w`)
            .join(', ');

        return srcSet;
    }

    /**
     * Lazy load images with intersection observer
     */
    static lazyLoadImages(): void {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target as HTMLImageElement;
                        const src = img.dataset.src;
                        if (src) {
                            img.src = src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    /**
     * Optimize image loading with WebP support
     */
    static getOptimizedImageUrl(
        src: string,
        width?: number,
        height?: number,
        quality: number = 80
    ): string {
        const url = new URL(src, window.location.origin);
        const params = new URLSearchParams();

        if (width) params.set('w', width.toString());
        if (height) params.set('h', height.toString());
        params.set('q', quality.toString());

        // Check for WebP support
        if (ImageOptimization.supportsWebP()) {
            params.set('f', 'webp');
        }

        url.search = params.toString();
        return url.toString();
    }

    /**
     * Check if browser supports WebP
     */
    static supportsWebP(): boolean {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas.toDataURL('image/webp').indexOf('image/webp') === 5;
    }
}

/**
 * React performance optimization hooks
 */

/**
 * Hook for memoizing expensive calculations
 */
export function useExpensiveCalculation<T>(
    calculation: () => T,
    dependencies: React.DependencyList
): T {
    return useMemo(calculation, dependencies);
}

/**
 * Hook for debouncing values
 */
export function useDebounce<T>(value: T, delay: number): T {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const handler = setTimeout(() => {
            setDebouncedValue(value);
        }, delay);

        return () => {
            clearTimeout(handler);
        };
    }, [value, delay]);

    return debouncedValue;
}

/**
 * Hook for throttling callbacks
 */
export function useThrottle<T extends (...args: any[]) => any>(
    callback: T,
    delay: number
): T {
    const [isThrottled, setIsThrottled] = useState(false);

    const throttledCallback = useCallback((...args: Parameters<T>) => {
        if (!isThrottled) {
            callback(...args);
            setIsThrottled(true);
            setTimeout(() => setIsThrottled(false), delay);
        }
    }, [callback, delay, isThrottled]) as T;

    return throttledCallback;
}

/**
 * Hook for virtualization of large lists
 */
export function useVirtualization(
    itemCount: number,
    itemHeight: number,
    containerHeight: number,
    scrollTop: number
): { startIndex: number; endIndex: number; offsetY: number } {
    return useMemo(() => {
        const startIndex = Math.floor(scrollTop / itemHeight);
        const endIndex = Math.min(
            itemCount - 1,
            Math.floor((scrollTop + containerHeight) / itemHeight)
        );
        const offsetY = startIndex * itemHeight;

        return { startIndex, endIndex, offsetY };
    }, [itemCount, itemHeight, containerHeight, scrollTop]);
}

/**
 * Bundle analysis utilities
 */
export class BundleAnalysis {
    private static bundleAnalysisLogged = false;

    /**
     * Analyze bundle size and performance
     */
    static analyzeBundleSize(): void {
        if (import.meta.env.MODE === 'development' && !this.bundleAnalysisLogged) {
            // Only run once in development - silenced for clean console
            const observer = new PerformanceObserver((list) => {
                list.getEntries().forEach((entry) => {
                    if (entry.entryType === 'navigation' && !this.bundleAnalysisLogged) {
                        const navEntry = entry as PerformanceNavigationTiming;
                        // Bundle analysis silenced - available via isDevelopment flag if needed
                        this.bundleAnalysisLogged = true;
                        observer.disconnect(); // Stop observing after first log
                    }
                });
            });

            observer.observe({ entryTypes: ['navigation'] });
        }
    }

    /**
     * Monitor resource loading performance
     */
    static monitorResourceLoading(): void {
        if (import.meta.env.MODE === 'development') {
            const observer = new PerformanceObserver((list) => {
                list.getEntries().forEach((entry) => {
                    if (entry.entryType === 'resource') {
                        const resourceEntry = entry as PerformanceResourceTiming;
                        const size = resourceEntry.transferSize || 0;
                        const duration = resourceEntry.duration;

                        if (size > 500000) { // Resources larger than 500KB (more reasonable for images)
                            console.warn(`Large resource detected: ${entry.name}`, {
                                size: `${(size / 1024).toFixed(2)}KB`,
                                duration: `${duration.toFixed(2)}ms`
                            });
                        }
                    }
                });
            });

            observer.observe({ entryTypes: ['resource'] });
        }
    }
}

/**
 * Performance monitoring utilities
 */
export class PerformanceMonitor {
    private static metrics: Partial<PerformanceMetrics> = {};

    /**
     * Collect Core Web Vitals
     */
    static collectCoreWebVitals(): void {
        // First Contentful Paint
        this.observeEntry('paint', (entry) => {
            if (entry.name === 'first-contentful-paint') {
                this.metrics.fcp = entry.startTime;
            }
        });

        // Largest Contentful Paint
        this.observeEntry('largest-contentful-paint', (entry) => {
            this.metrics.lcp = entry.startTime;
        });

        // First Input Delay
        this.observeEntry('first-input', (entry) => {
            this.metrics.fid = (entry as any).processingStart - entry.startTime;
        });

        // Cumulative Layout Shift
        this.observeEntry('layout-shift', (entry) => {
            if (!(entry as any).hadRecentInput) {
                this.metrics.cls = (this.metrics.cls || 0) + (entry as any).value;
            }
        });

        // Time to First Byte
        this.observeEntry('navigation', (entry) => {
            const navEntry = entry as PerformanceNavigationTiming;
            this.metrics.ttfb = navEntry.responseStart - navEntry.requestStart;
        });
    }

    /**
     * Observe performance entries
     */
    private static observeEntry(
        entryType: string,
        callback: (entry: PerformanceEntry) => void
    ): void {
        try {
            const observer = new PerformanceObserver((list) => {
                list.getEntries().forEach(callback);
            });
            observer.observe({ entryTypes: [entryType] });
        } catch (error) {
            console.warn(`Performance observer for ${entryType} not supported:`, error);
        }
    }

    /**
     * Get current performance metrics
     */
    static getMetrics(): Partial<PerformanceMetrics> {
        return { ...this.metrics };
    }

    /**
     * Send metrics to analytics
     */
    static sendMetrics(endpoint?: string): void {
        const metrics = this.getMetrics();

        if (endpoint) {
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(metrics),
            }).catch(error => {
                console.warn('Failed to send performance metrics:', error);
            });
        } else {

        }
    }
}

/**
 * Memory optimization utilities
 */
export class MemoryOptimization {
    /**
     * Clean up event listeners and timers
     */
    static cleanup(): void {
        // Clear any lingering timers - Note: This is a simplified cleanup

    }

    /**
     * Monitor memory usage - Conditional logging
     */
    static monitorMemory(): void {
        // Only log if explicitly enabled
        if ('memory' in performance && !window.location.search.includes('silent')) {
            const memoryInfo = (performance as any).memory;
            // Aquí podrías agregar lógica de monitoreo si es necesario
        }
    }

    /**
     * Force garbage collection (development only)
     */
    static forceGC(): void {
        if (import.meta.env.MODE === 'development' && 'gc' in window) {
            (window as any).gc();
        }
    }
}

/**
 * Service Worker utilities for caching
 */
export class ServiceWorkerManager {
    /**
     * Register service worker
     */
    static async register(swPath: string = '/sw.js'): Promise<void> {
        if ('serviceWorker' in navigator && import.meta.env.MODE === 'production') {
            try {
                const registration = await navigator.serviceWorker.register(swPath);


                // Listen for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    if (newWorker) {
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // New content is available, notify user
                                this.notifyUpdate();
                            }
                        });
                    }
                });
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }
    }

    /**
     * Notify user about updates
     */
    private static notifyUpdate(): void {
        if (confirm('New version available! Reload to update?')) {
            window.location.reload();
        }
    }

    /**
     * Unregister service worker
     */
    static async unregister(): Promise<void> {
        if ('serviceWorker' in navigator) {
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (const registration of registrations) {
                await registration.unregister();
            }
        }
    }
}

/**
 * Component optimization utilities
 */
export const ComponentOptimization = {
    /**
     * Check if component should update based on props
     */
    shouldComponentUpdate: <T extends Record<string, any>>(
        prevProps: T,
        nextProps: T,
        shallowCompare: boolean = true
    ): boolean => {
        if (shallowCompare) {
            const prevKeys = Object.keys(prevProps);
            const nextKeys = Object.keys(nextProps);

            if (prevKeys.length !== nextKeys.length) {
                return true;
            }

            for (const key of prevKeys) {
                if (prevProps[key] !== nextProps[key]) {
                    return true;
                }
            }

            return false;
        } else {
            return JSON.stringify(prevProps) !== JSON.stringify(nextProps);
        }
    },
};

/**
 * Initialize performance monitoring
 */
export function initializePerformanceMonitoring(): void {
    console.log('🎯 Inicializando monitoreo de rendimiento...');

    // Collect Core Web Vitals
    PerformanceMonitor.collectCoreWebVitals();

    // Analyze bundle size in development
    BundleAnalysis.analyzeBundleSize();
    BundleAnalysis.monitorResourceLoading();

    // Set up lazy loading for images
    ImageOptimization.lazyLoadImages();

    // Register service worker in production
    ServiceWorkerManager.register();

    // Monitor memory usage in development - Reduced frequency
    if (import.meta.env.MODE === 'development') {
        setInterval(() => {
            MemoryOptimization.monitorMemory();
        }, 120000); // Every 2 minutes instead of 30 seconds
    }

    // Configurar monitoreo de memoria inicial
    if ('memory' in performance) {
        const memoryInfo = (performance as any).memory;
        console.log('📊 Información de memoria inicial:', {
            used: `${(memoryInfo.usedJSHeapSize / 1024 / 1024).toFixed(2)}MB`,
            total: `${(memoryInfo.totalJSHeapSize / 1024 / 1024).toFixed(2)}MB`,
            limit: `${(memoryInfo.jsHeapSizeLimit / 1024 / 1024).toFixed(2)}MB`
        });
    }

    // Monitoreo de navegación
    window.addEventListener('load', () => {
        setTimeout(() => {
            const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
            if (navigation) {
                console.log('📈 Métricas de navegación:', {
                    domContentLoaded: `${navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart}ms`,
                    loadComplete: `${navigation.loadEventEnd - navigation.loadEventStart}ms`,
                    totalTime: `${navigation.loadEventEnd - navigation.fetchStart}ms`
                });
            }
        }, 1000);
    });

    // Monitoreo de recursos críticos
    const criticalResources = [
        '/assets/css/critical.css',
        '/assets/images/logo.svg',
        '/favicon.ico'
    ];

    criticalResources.forEach(resource => {
        const observer = new PerformanceObserver((list) => {
            list.getEntries().forEach((entry) => {
                if (entry.name.includes(resource)) {
                    const resourceEntry = entry as PerformanceResourceTiming;
                    console.log(`⚡ Recurso crítico cargado: ${resource}`, {
                        duration: `${entry.duration.toFixed(2)}ms`,
                        size: resourceEntry.transferSize ? `${(resourceEntry.transferSize / 1024).toFixed(2)}KB` : 'N/A'
                    });
                }
            });
        });

        observer.observe({ entryTypes: ['resource'] });
    });

    // Send metrics after page load
    window.addEventListener('load', () => {
        setTimeout(() => {
            PerformanceMonitor.sendMetrics();
        }, 5000); // Send metrics 5 seconds after load
    });

    console.log('✅ Monitoreo de rendimiento inicializado correctamente');
}



export default {
    CodeSplitting,
    ImageOptimization,
    BundleAnalysis,
    PerformanceMonitor,
    MemoryOptimization,
    ServiceWorkerManager,
    ComponentOptimization,
    useExpensiveCalculation,
    useDebounce,
    useThrottle,
    useVirtualization,
    initializePerformanceMonitoring,
};
