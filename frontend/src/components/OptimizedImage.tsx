/**
 * Advanced Image Optimization Component
 * 
 * Production-ready image optimization with WebP support, lazy loading,
 * responsive images, and performance monitoring.
 * 
 * @package ImageOptimization
 * @author Bubble Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';
import { ImageOptimization } from '../utils/performance';

/**
 * Image formats supported
 */
type ImageFormat = 'webp' | 'jpeg' | 'png' | 'avif';

/**
 * Image props interface
 */
interface OptimizedImageProps {
    src: string;
    alt: string;
    width?: number;
    height?: number;
    quality?: number;
    formats?: ImageFormat[];
    lazy?: boolean;
    placeholder?: string;
    className?: string;
    onLoad?: () => void;
    onError?: (error: Error) => void;
    priority?: boolean;
    sizes?: string;
    loading?: 'lazy' | 'eager';
}

/**
 * Image loading state
 */
type LoadingState = 'loading' | 'loaded' | 'error';

/**
 * Optimized Image Component with WebP support
 */
export const OptimizedImage: React.FC<OptimizedImageProps> = ({
    src,
    alt,
    width,
    height,
    quality = 80,
    formats = ['webp', 'jpeg'],
    lazy = true,
    placeholder,
    className = '',
    onLoad,
    onError,
    priority = false,
    sizes,
    loading = 'lazy'
}) => {
    const [loadingState, setLoadingState] = useState<LoadingState>('loading');
    const [currentSrc, setCurrentSrc] = useState(placeholder || '');
    const imgRef = useRef<HTMLImageElement>(null);
    const observerRef = useRef<IntersectionObserver | null>(null);

    /**
     * Get optimized image sources for different formats
     */
    const getImageSources = useCallback(() => {
        const sources: { srcSet: string; type: string }[] = [];

        formats.forEach(format => {
            const optimizedUrl = ImageOptimization.getOptimizedImageUrl(
                src,
                width,
                height,
                quality
            );

            // Add format parameter
            const url = new URL(optimizedUrl, window.location.origin);
            url.searchParams.set('f', format);

            sources.push({
                srcSet: url.toString(),
                type: `image/${format}`
            });
        });

        return sources;
    }, [src, width, height, quality, formats]);

    /**
     * Load image with format fallback
     */
    const loadImage = useCallback(async () => {
        try {
            setLoadingState('loading');

            // Get optimized sources
            const sources = getImageSources();
            let loadedSrc = src;

            // Try each format until one works
            for (const source of sources) {
                try {
                    await new Promise<void>((resolve, reject) => {
                        const testImg = new Image();
                        testImg.onload = () => resolve();
                        testImg.onerror = () => reject();
                        testImg.src = source.srcSet;
                    });
                    loadedSrc = source.srcSet;
                    break;
                } catch {
                    // Continue to next format
                }
            }

            setCurrentSrc(loadedSrc);
            setLoadingState('loaded');
            onLoad?.();
        } catch (error) {
            setLoadingState('error');
            onError?.(error as Error);
        }
    }, [src, getImageSources, onLoad, onError]);

    /**
     * Setup intersection observer for lazy loading
     */
    useEffect(() => {
        if (!lazy || priority || !imgRef.current) {
            loadImage();
            return undefined;
        }

        observerRef.current = new IntersectionObserver(
            (entries) => {
                const [entry] = entries;
                if (entry?.isIntersecting) {
                    loadImage();
                    observerRef.current?.unobserve(imgRef.current!);
                }
            },
            {
                rootMargin: '50px 0px',
                threshold: 0.1
            }
        );

        observerRef.current.observe(imgRef.current);

        return () => {
            observerRef.current?.disconnect();
        };
    }, [lazy, priority, loadImage]);

    /**
     * Preload critical images
     */
    useEffect(() => {
        if (priority) {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'image';
            link.href = currentSrc;
            document.head.appendChild(link);
        }
    }, [priority, currentSrc]);

    return (
        <picture className={`optimized-image ${className}`}>
            {/* Generate source elements for different formats */}
            {getImageSources().map((source, index) => (
                <source
                    key={index}
                    srcSet={source.srcSet}
                    type={source.type}
                    sizes={sizes}
                />
            ))}

            <img
                ref={imgRef}
                src={currentSrc}
                alt={alt}
                width={width}
                height={height}
                loading={loading}
                className={`
                    transition-opacity duration-300
                    ${loadingState === 'loading' ? 'opacity-50' : 'opacity-100'}
                    ${loadingState === 'error' ? 'bg-gray-200' : ''}
                `}
                onLoad={() => {
                    setLoadingState('loaded');
                    onLoad?.();
                }}
                onError={() => {
                    setLoadingState('error');
                    onError?.(new Error('Image failed to load'));
                }}
            />
        </picture>
    );
};

/**
 * Background Image Component with optimization
 */
interface OptimizedBackgroundImageProps {
    src: string;
    children: React.ReactNode;
    className?: string;
    quality?: number;
    lazy?: boolean;
    priority?: boolean;
}

export const OptimizedBackgroundImage: React.FC<OptimizedBackgroundImageProps> = ({
    src,
    children,
    className = '',
    quality = 80,
    lazy = true,
    priority = false
}) => {
    const [backgroundImage, setBackgroundImage] = useState<string>('');
    const [isLoaded, setIsLoaded] = useState(false);
    const elementRef = useRef<HTMLDivElement>(null);

    const loadBackgroundImage = useCallback(async () => {
        try {
            const optimizedUrl = ImageOptimization.getOptimizedImageUrl(src, undefined, undefined, quality);

            // Preload the image
            await new Promise<void>((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve();
                img.onerror = () => reject();
                img.src = optimizedUrl;
            });

            setBackgroundImage(`url("${optimizedUrl}")`);
            setIsLoaded(true);
        } catch (error) {
            console.warn('Failed to load background image:', error);
        }
    }, [src, quality]);

    useEffect(() => {
        if (!lazy || priority) {
            loadBackgroundImage();
            return undefined;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const [entry] = entries;
                if (entry?.isIntersecting) {
                    loadBackgroundImage();
                    observer.unobserve(elementRef.current!);
                }
            },
            { rootMargin: '50px 0px' }
        );

        if (elementRef.current) {
            observer.observe(elementRef.current);
        }

        return () => observer.disconnect();
    }, [lazy, priority, loadBackgroundImage]);

    return (
        <div
            ref={elementRef}
            className={`
                ${className}
                transition-all duration-500
                ${isLoaded ? 'opacity-100' : 'opacity-70'}
            `}
            style={{
                backgroundImage,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
                backgroundRepeat: 'no-repeat'
            }}
        >
            {children}
        </div>
    );
};

/**
 * Avatar Component with optimization
 */
interface OptimizedAvatarProps {
    src?: string;
    alt: string;
    size?: number;
    fallback?: React.ReactNode;
    className?: string;
}

export const OptimizedAvatar: React.FC<OptimizedAvatarProps> = ({
    src,
    alt,
    size = 40,
    fallback,
    className = ''
}) => {
    const [imageSrc, setImageSrc] = useState<string | null>(null);
    const [hasError, setHasError] = useState(false);

    useEffect(() => {
        if (src) {
            const optimizedUrl = ImageOptimization.getOptimizedImageUrl(
                src,
                size * 2, // 2x for retina displays
                size * 2,
                90
            );
            setImageSrc(optimizedUrl);
            setHasError(false);
        }
    }, [src, size]);

    if (!src || hasError) {
        return (
            <div
                className={`
                    flex items-center justify-center
                    rounded-full bg-gray-300 text-gray-600
                    ${className}
                `}
                style={{ width: size, height: size }}
            >
                {fallback || alt.charAt(0).toUpperCase()}
            </div>
        );
    }

    return (
        <OptimizedImage
            src={imageSrc!}
            alt={alt}
            width={size}
            height={size}
            quality={90}
            formats={['webp', 'jpeg']}
            lazy={false}
            className={`rounded-full ${className}`}
            onError={() => setHasError(true)}
        />
    );
};

/**
 * Image Gallery Component with lazy loading
 */
interface ImageGalleryProps {
    images: Array<{
        src: string;
        alt: string;
        width?: number;
        height?: number;
    }>;
    className?: string;
    imageClassName?: string;
}

export const OptimizedImageGallery: React.FC<ImageGalleryProps> = ({
    images,
    className = '',
    imageClassName = ''
}) => {
    return (
        <div className={`grid gap-4 ${className}`}>
            {images.map((image, index) => (
                <OptimizedImage
                    key={index}
                    src={image.src}
                    alt={image.alt}
                    width={image.width || undefined}
                    height={image.height || undefined}
                    lazy={index > 2} // First 3 images load immediately
                    priority={index < 2} // First 2 images are priority
                    className={imageClassName}
                    quality={85}
                    formats={['webp', 'jpeg']}
                />
            ))}
        </div>
    );
};

/**
 * Image optimization utilities
 */
export class AdvancedImageOptimization extends ImageOptimization {
    /**
     * Create responsive image with multiple sizes
     */
    static createResponsiveImageSet(
        src: string,
        breakpoints: { [key: string]: { width: number; height?: number } } = {
            mobile: { width: 320 },
            tablet: { width: 768 },
            desktop: { width: 1024 },
            wide: { width: 1920 }
        }
    ): { srcSet: string; sizes: string } {
        const srcSet = Object.entries(breakpoints)
            .map(([_breakpoint, dimensions]) => {
                const url = ImageOptimization.getOptimizedImageUrl(
                    src,
                    dimensions.width,
                    dimensions.height
                );
                return `${url} ${dimensions.width}w`;
            })
            .join(', ');

        const sizes = Object.entries(breakpoints)
            .map(([_breakpoint, dimensions], index, array) => {
                if (index === array.length - 1) {
                    return `${dimensions.width}px`;
                }
                const nextBreakpoint = array[index + 1]?.[1]?.width;
                if (!nextBreakpoint) return `${dimensions.width}px`;
                return `(max-width: ${nextBreakpoint}px) ${dimensions.width}px`;
            })
            .join(', ');

        return { srcSet, sizes };
    }

    /**
     * Optimize image for specific use case
     */
    static optimizeForUseCase(
        src: string,
        useCase: 'avatar' | 'thumbnail' | 'hero' | 'gallery' | 'background'
    ): string {
        const optimizations = {
            avatar: { width: 80, height: 80, quality: 90 },
            thumbnail: { width: 300, height: 200, quality: 80 },
            hero: { width: 1920, height: 1080, quality: 85 },
            gallery: { width: 800, height: 600, quality: 80 },
            background: { width: 1920, quality: 75 }
        };

        const config = optimizations[useCase];
        return ImageOptimization.getOptimizedImageUrl(
            src,
            config.width,
            'height' in config ? config.height : undefined,
            config.quality
        );
    }

    /**
     * Batch optimize images
     */
    static async batchOptimize(
        images: Array<{ src: string; useCase: string }>,
        onProgress?: (completed: number, total: number) => void
    ): Promise<Array<{ original: string; optimized: string }>> {
        const results: Array<{ original: string; optimized: string }> = [];

        for (let i = 0; i < images.length; i++) {
            const image = images[i];
            if (!image) continue;

            const optimized = this.optimizeForUseCase(
                image.src,
                image.useCase as any
            );

            results.push({
                original: image.src,
                optimized
            });

            onProgress?.(i + 1, images.length);
        }

        return results;
    }

    /**
     * Monitor image loading performance
     */
    static monitorImagePerformance(): void {
        if ('PerformanceObserver' in window) {
            const observer = new PerformanceObserver((list) => {
                list.getEntries().forEach((entry) => {
                    if (entry.entryType === 'resource' &&
                        (entry.name.includes('image') ||
                            entry.name.match(/\.(jpg|jpeg|png|webp|avif)(\?|$)/))) {

                        const resourceEntry = entry as PerformanceResourceTiming;
                        const loadTime = resourceEntry.duration;
                        const size = resourceEntry.transferSize || 0;

                        if (loadTime > 1000) { // Images taking longer than 1 second
                            console.warn(`Slow image detected: ${entry.name}`, {
                                loadTime: `${loadTime.toFixed(2)}ms`,
                                size: `${(size / 1024).toFixed(2)}KB`
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
 * Initialize image optimization
 */
export function initializeImageOptimization(): void {
    // Set up lazy loading for existing images
    ImageOptimization.lazyLoadImages();

    // Monitor image performance
    AdvancedImageOptimization.monitorImagePerformance();

    // Add CSS for optimized images
    const style = document.createElement('style');
    style.textContent = `
        .optimized-image {
            display: block;
            max-width: 100%;
            height: auto;
        }
        
        .optimized-image img {
            width: 100%;
            height: auto;
        }
        
        .optimized-image.loading::before {
            content: '';
            display: block;
            width: 100%;
            height: 200px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    `;
    document.head.appendChild(style);
}



export default {
    OptimizedImage,
    OptimizedBackgroundImage,
    OptimizedAvatar,
    OptimizedImageGallery,
    AdvancedImageOptimization,
    initializeImageOptimization
};
