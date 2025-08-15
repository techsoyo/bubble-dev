/**
 * Componente AdaptiveImage
 * 
 * Este componente extiende las capacidades de LazyImage para trabajar
 * de forma óptima con conexiones lentas o sin conexión.
 * Características:
 * - Carga imágenes progresivamente con baja calidad primero
 * - Maneja casos sin conexión con placeholders o imágenes en caché
 * - Adapta la estrategia según la calidad de conexión
 * - Reintentos inteligentes para conexiones inestables
 */

import React, { useState, useEffect } from 'react';
import { useOnlineStatus } from '../../hooks/useOnlineStatus';

interface AdaptiveImageProps extends React.ImgHTMLAttributes<HTMLImageElement> {
  src: string;
  alt: string;
  fallbackSrc?: string;
  lowQualitySrc?: string;
  placeholderColor?: string;
  retryCount?: number;
  retryDelay?: number;
  threshold?: number;
  loadingIndicator?: React.ReactNode;
  offline?: boolean;
}

const AdaptiveImage: React.FC<AdaptiveImageProps> = ({
  src,
  alt,
  fallbackSrc,
  lowQualitySrc,
  placeholderColor = '#f1f5f9',
  retryCount = 3,
  retryDelay = 2000,
  threshold = 0.1,
  loadingIndicator,
  offline = false,
  ...props
}) => {
  const [loaded, setLoaded] = useState(false);
  const [error, setError] = useState(false);
  const [retry, setRetry] = useState(0);
  const [imageSrc, setImageSrc] = useState<string>(lowQualitySrc || src);
  const { isOnline, connectionQuality } = useOnlineStatus();

  // Estilos base para el contenedor y la imagen
  const containerStyle: React.CSSProperties = {
    position: 'relative',
    overflow: 'hidden',
    backgroundColor: placeholderColor,
    width: props.width ? `${props.width}px` : '100%',
    height: props.height ? `${props.height}px` : 'auto',
    ...(props.style as React.CSSProperties),
  };

  const imageStyle: React.CSSProperties = {
    display: 'block',
    width: '100%',
    height: '100%',
    objectFit: (props as any).objectFit || 'cover',
    transition: 'opacity 0.3s ease-in-out, filter 0.5s ease-in-out',
    opacity: loaded ? 1 : 0,
    filter: loaded ? 'none' : 'blur(10px)',
  };

  // Manejar carga de imagen con reintentos
  useEffect(() => {
    if (!isOnline && offline) {
      // Si estamos sin conexión y la imagen tiene modo offline, no intentamos cargar
      return undefined;
    }

    let isMounted = true;
    let retryTimeout: NodeJS.Timeout;

    const img = new Image();

    const handleLoad = () => {
      if (isMounted) {
        if (lowQualitySrc && img.src !== src) {
          // Si cargamos primero la versión de baja calidad, ahora cargar la versión completa
          img.src = src;
        } else {
          setLoaded(true);
          setImageSrc(src);
        }
      }
    };

    const handleError = () => {
      if (isMounted) {
        if (retry < retryCount && isOnline) {
          // Reintentar después de un retraso
          retryTimeout = setTimeout(() => {
            setRetry(prev => prev + 1);
          }, retryDelay);
        } else {
          setError(true);
          // Usar imagen de respaldo si está disponible
          if (fallbackSrc) {
            setImageSrc(fallbackSrc);
            setLoaded(true);
          }
        }
      }
    };

    // Determinar la estrategia de carga según la calidad de conexión
    const loadImage = () => {
      img.onload = handleLoad;
      img.onerror = handleError;

      // Si la conexión es lenta, priorizar la versión de baja calidad
      if (connectionQuality === 'poor' && lowQualitySrc) {
        img.src = lowQualitySrc;
      } else {
        img.src = src;
      }
    };

    loadImage();

    return () => {
      isMounted = false;
      clearTimeout(retryTimeout);
      img.onload = null;
      img.onerror = null;
    };
  }, [
    src,
    lowQualitySrc,
    fallbackSrc,
    retry,
    retryCount,
    retryDelay,
    isOnline,
    connectionQuality,
    offline
  ]);

  // Usar IntersectionObserver para carga perezosa si está disponible
  useEffect(() => {
    // Saltarse si la imagen ya está cargada o no hay soporte para IntersectionObserver
    if (loaded || !('IntersectionObserver' in window)) {
      return undefined;
    }

    const imgElement = document.getElementById(`adaptive-img-${src.replace(/\W/g, '')}`);
    if (!imgElement) return undefined;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            // Actualizar la estrategia de carga cuando la imagen entra en el viewport
            observer.unobserve(imgElement);
          }
        });
      },
      { threshold }
    );

    observer.observe(imgElement);

    return () => {
      observer.unobserve(imgElement);
    };
  }, [src, loaded, threshold]);

  // Mostrar placeholder en modo offline si no está marcada como disponible offline
  if (!isOnline && !offline) {
    return (
      <div style={containerStyle} className={props.className}>
        <div
          style={{
            width: '100%',
            height: '100%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: placeholderColor,
            color: '#64748b',
            fontSize: '0.875rem',
          }}
        >
          <span>Imagen no disponible sin conexión</span>
        </div>
      </div>
    );
  }

  return (
    <div style={containerStyle} className={props.className}>
      <img
        id={`adaptive-img-${src.replace(/\W/g, '')}`}
        src={imageSrc}
        alt={alt}
        style={imageStyle}
        loading="lazy"
        onLoad={() => setLoaded(true)}
        onError={() => setError(true)}
        {...props}
      />

      {!loaded && !error && (
        <div
          style={{
            position: 'absolute',
            top: 0,
            left: 0,
            width: '100%',
            height: '100%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: placeholderColor,
          }}
        >
          {loadingIndicator || (
            <div style={{ width: '24px', height: '24px', border: '2px solid #e2e8f0', borderTopColor: '#3b82f6', borderRadius: '50%', animation: 'spin 1s linear infinite' }} />
          )}
        </div>
      )}

      {error && !fallbackSrc && (
        <div
          style={{
            position: 'absolute',
            top: 0,
            left: 0,
            width: '100%',
            height: '100%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: placeholderColor,
            color: '#64748b',
            fontSize: '0.875rem',
          }}
        >
          <span>Error al cargar la imagen</span>
        </div>
      )}

      <style dangerouslySetInnerHTML={{
        __html: `
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
        `
      }} />
    </div>
  );
};

export default AdaptiveImage;
