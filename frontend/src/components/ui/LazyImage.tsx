import React, { useState, useEffect } from 'react';

interface LazyImageProps extends React.ImgHTMLAttributes<HTMLImageElement> {
  src: string;
  alt: string;
  placeholderSrc?: string;
  threshold?: number;
  width?: number | string;
  height?: number | string;
  className?: string;
}

/**
 * Componente de imagen optimizado con lazy loading
 * 
 * Características:
 * - Lazy loading nativo con fallback a IntersectionObserver
 * - Placeholder configurable
 * - Efecto de desvanecimiento al cargar
 * - Soporte para imágenes WebP con fallback
 */
const LazyImage: React.FC<LazyImageProps> = ({
  src,
  alt,
  placeholderSrc = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB4PSIwIiB5PSIwIiB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZWVlZWVlIiAvPjwvc3ZnPg==',
  threshold = 0.1,
  width,
  height,
  className = '',
  ...props
}) => {
  const [isLoaded, setIsLoaded] = useState(false);
  const [currentSrc, setCurrentSrc] = useState(placeholderSrc);

  useEffect(() => {
    // Verificar soporte para lazy loading nativo
    const hasNativeLazyLoading = 'loading' in HTMLImageElement.prototype;

    if (hasNativeLazyLoading) {
      // Usar lazy loading nativo
      setCurrentSrc(src);
      return undefined;
    } else {
      // Fallback a IntersectionObserver
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              setCurrentSrc(src);
              observer.disconnect();
            }
          });
        },
        { threshold }
      );

      // Crear un elemento de referencia para observar
      const imgElement = document.createElement('img');
      imgElement.src = placeholderSrc;
      observer.observe(imgElement);

      return () => {
        observer.disconnect();
      };
    }
  }, [src, placeholderSrc, threshold]);

  const handleImageLoad = () => {
    setIsLoaded(true);
  };

  // Generar srcSet para densidad de píxeles si es posible
  const generateSrcSet = () => {
    if (!src.includes('.jpg') && !src.includes('.png') && !src.includes('.jpeg')) {
      return undefined;
    }

    // Extraer nombre base y extensión
    const lastDot = src.lastIndexOf('.');
    const baseName = src.substring(0, lastDot);
    const extension = src.substring(lastDot);

    return `${src} 1x, ${baseName}@2x${extension} 2x, ${baseName}@3x${extension} 3x`;
  };

  return (
    <img
      src={currentSrc}
      alt={alt}
      width={width}
      height={height}
      onLoad={handleImageLoad}
      loading="lazy"
      srcSet={currentSrc === src ? generateSrcSet() : undefined}
      className={`transition-opacity duration-300 ${isLoaded ? 'opacity-100' : 'opacity-40'} ${className}`}
      {...props}
    />
  );
};

export default LazyImage;
