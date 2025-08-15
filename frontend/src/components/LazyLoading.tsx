/**
 * Lazy Loading and Code Splitting Components
 * 
 * Provides React components and utilities for lazy loading and code splitting
 * with enhanced error handling and performance optimization.
 * 
 * @package LazyLoading
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React, { Suspense, lazy, ComponentType, LazyExoticComponent } from 'react';
import type { ReactNode } from 'react';

/**
 * Enhanced lazy loading with error boundary
 */
export function createLazyComponent<T extends ComponentType<any>>(
    importFn: () => Promise<{ default: T }>,
    fallback?: ReactNode
): LazyExoticComponent<T> {
    const LazyComponent = lazy(async () => {
        try {
            return await importFn();
        } catch (error) {
            console.error('Failed to load component:', error);
            // Return a fallback component in case of error
            return {
                default: (() => (
                    <div className="p-4 text-center text-red-600">
                        Failed to load component. Please refresh the page.
                    </div>
                )) as unknown as T
            };
        }
    });

    // Wrapper component with Suspense
    const WrappedComponent = (props: any) => (
        <Suspense fallback={fallback || <LoadingSpinner />}>
            <LazyComponent {...props} />
        </Suspense>
    );

    return WrappedComponent as LazyExoticComponent<T>;
}

/**
 * Create route-based lazy component
 */
export function createRouteComponent(
    importFn: () => Promise<{ default: ComponentType<any> }>,
    preload: boolean = false
): LazyExoticComponent<ComponentType<any>> {
    if (preload) {
        // Preload after a short delay
        setTimeout(() => {
            importFn().catch(error => {
                console.warn('Failed to preload component:', error);
            });
        }, 100);
    }

    return createLazyComponent(importFn, <RouteLoadingSpinner />);
}

/**
 * Loading spinner component
 */
export function LoadingSpinner(): JSX.Element {
    return (
        <div className="p-4 text-center">
            <div className="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
            <span className="ml-2 text-gray-600">Loading...</span>
        </div>
    );
}

/**
 * Route loading spinner with full page height
 */
export function RouteLoadingSpinner(): JSX.Element {
    return (
        <div className="flex justify-center items-center h-64">
            <div className="text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p className="mt-2 text-gray-600">Loading page...</p>
            </div>
        </div>
    );
}

/**
 * Lazy image component with intersection observer
 */
interface LazyImageProps {
    src: string;
    alt: string;
    className?: string;
    placeholder?: string;
    width?: number;
    height?: number;
}

export function LazyImage({
    src,
    alt,
    className = '',
    placeholder = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIwIiBoZWlnaHQ9IjI0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjY2NjIi8+PC9zdmc+',
    width,
    height
}: LazyImageProps): JSX.Element {
    const [imageSrc, setImageSrc] = React.useState(placeholder);
    const [isLoaded, setIsLoaded] = React.useState(false);
    const imgRef = React.useRef<HTMLImageElement>(null);

    React.useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        setImageSrc(src);
                        observer.disconnect();
                    }
                });
            },
            { threshold: 0.1 }
        );

        if (imgRef.current) {
            observer.observe(imgRef.current);
        }

        return () => observer.disconnect();
    }, [src]);

    const handleLoad = () => {
        setIsLoaded(true);
    };

    return (
        <img
            ref={imgRef}
            src={imageSrc}
            alt={alt}
            className={`${className} ${isLoaded ? 'opacity-100' : 'opacity-75'} transition-opacity duration-300`}
            onLoad={handleLoad}
            width={width}
            height={height}
            loading="lazy"
        />
    );
}

/**
 * Progressive image component with multiple sources
 */
interface ProgressiveImageProps {
    src: string;
    lowQualitySrc?: string;
    alt: string;
    className?: string;
    width?: number;
    height?: number;
}

export function ProgressiveImage({
    src,
    lowQualitySrc,
    alt,
    className = '',
    width,
    height
}: ProgressiveImageProps): JSX.Element {
    const [currentSrc, setCurrentSrc] = React.useState(lowQualitySrc || src);
    const [isHighQualityLoaded, setIsHighQualityLoaded] = React.useState(false);

    React.useEffect(() => {
        if (lowQualitySrc && src !== lowQualitySrc) {
            const img = new Image();
            img.onload = () => {
                setCurrentSrc(src);
                setIsHighQualityLoaded(true);
            };
            img.src = src;
        }
    }, [src, lowQualitySrc]);

    return (
        <img
            src={currentSrc}
            alt={alt}
            className={`${className} ${isHighQualityLoaded ? 'opacity-100' : 'opacity-90'} transition-opacity duration-500`}
            width={width}
            height={height}
            loading="lazy"
        />
    );
}

/**
 * Virtual list component for large datasets
 */
interface VirtualListProps<T> {
    items: T[];
    itemHeight: number;
    containerHeight: number;
    renderItem: (item: T, index: number) => ReactNode;
    className?: string;
}

export function VirtualList<T>({
    items,
    itemHeight,
    containerHeight,
    renderItem,
    className = ''
}: VirtualListProps<T>): JSX.Element {
    const [scrollTop, setScrollTop] = React.useState(0);

    const startIndex = Math.floor(scrollTop / itemHeight);
    const endIndex = Math.min(
        items.length - 1,
        Math.floor((scrollTop + containerHeight) / itemHeight)
    );

    const visibleItems = items.slice(startIndex, endIndex + 1);
    const offsetY = startIndex * itemHeight;

    const handleScroll = (event: React.UIEvent<HTMLDivElement>) => {
        setScrollTop(event.currentTarget.scrollTop);
    };

    return (
        <div
            className={`overflow-auto ${className}`}
            style={{ height: containerHeight }}
            onScroll={handleScroll}
        >
            <div style={{ height: items.length * itemHeight, position: 'relative' }}>
                <div style={{ transform: `translateY(${offsetY}px)` }}>
                    {visibleItems.map((item, index) => (
                        <div key={startIndex + index} style={{ height: itemHeight }}>
                            {renderItem(item, startIndex + index)}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/**
 * Preload utilities
 */
export const PreloadUtils = {
    /**
     * Preload a component
     */
    preloadComponent: (importFn: () => Promise<any>): void => {
        importFn().catch(error => {
            console.warn('Failed to preload component:', error);
        });
    },

    /**
     * Preload an image
     */
    preloadImage: (src: string): Promise<void> => {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve();
            img.onerror = () => reject(new Error(`Failed to preload image: ${src}`));
            img.src = src;
        });
    },

    /**
     * Preload multiple images
     */
    preloadImages: async (sources: string[]): Promise<void> => {
        await Promise.all(sources.map(src => PreloadUtils.preloadImage(src)));
    },
};

export default {
    createLazyComponent,
    createRouteComponent,
    LoadingSpinner,
    RouteLoadingSpinner,
    LazyImage,
    ProgressiveImage,
    VirtualList,
    PreloadUtils,
};
