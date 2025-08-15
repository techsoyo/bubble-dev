import { useEffect, useState } from 'react';

interface ScriptLoaderProps {
  src: string;
  async?: boolean;
  defer?: boolean;
  id?: string;
  onLoad?: () => void;
  onError?: (error: Error) => void;
  strategy?: 'afterInteractive' | 'beforeInteractive' | 'lazyOnload';
  integrity?: string;
  crossOrigin?: 'anonymous' | 'use-credentials';
}

/**
 * Componente para cargar scripts externos de forma optimizada
 * 
 * Permite cargar scripts con diferentes estrategias:
 * - afterInteractive: Carga después de que la página sea interactiva (default)
 * - beforeInteractive: Carga de inmediato (crítico)
 * - lazyOnload: Carga con baja prioridad
 */
export const ScriptLoader: React.FC<ScriptLoaderProps> = ({
  src,
  async = true,
  defer = true,
  id,
  onLoad,
  onError,
  strategy = 'afterInteractive',
  integrity,
  crossOrigin = 'anonymous'
}) => {
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    // No cargar si ya está cargado
    if (loaded) return undefined;

    // Comprobar si el script ya existe
    const existingScript = document.getElementById(id || src);
    if (existingScript) {
      setLoaded(true);
      if (onLoad) onLoad();
      return undefined;
    }

    // Crear y configurar el elemento script
    const scriptElement = document.createElement('script');
    scriptElement.src = src;
    scriptElement.async = async;
    scriptElement.defer = defer;

    if (id) scriptElement.id = id;
    if (integrity) scriptElement.integrity = integrity;
    if (crossOrigin) scriptElement.crossOrigin = crossOrigin;

    // Manejar eventos de carga
    scriptElement.onload = () => {
      setLoaded(true);
      if (onLoad) onLoad();
    };

    scriptElement.onerror = (error) => {
      if (onError) onError(error as unknown as Error);
    };

    // Determinar cuándo cargar el script basado en la estrategia
    const handleLoad = () => {
      document.body.appendChild(scriptElement);
    };

    if (strategy === 'beforeInteractive') {
      // Cargar de inmediato
      handleLoad();
    } else if (strategy === 'afterInteractive') {
      // Cargar después de que el DOM esté listo
      if (document.readyState === 'complete') {
        handleLoad();
      } else {
        window.addEventListener('load', handleLoad);
      }
    } else if (strategy === 'lazyOnload') {
      // Cargar cuando el navegador esté inactivo
      if ('requestIdleCallback' in window) {
        window.requestIdleCallback(handleLoad);
      } else {
        // Fallback para navegadores que no soportan requestIdleCallback
        setTimeout(handleLoad, 2000);
      }
    }

    return () => {
      if (strategy === 'afterInteractive' && document.readyState !== 'complete') {
        window.removeEventListener('load', handleLoad);
      }
    };
  }, [src, async, defer, id, onLoad, onError, strategy, integrity, crossOrigin, loaded]);

  return null;
};

export default ScriptLoader;
