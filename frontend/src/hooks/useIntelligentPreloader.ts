/**
 * Intelligent Preloader for Critical Routes
 * Precarga componentes en el momento óptimo para mejorar UX
 */

import { useEffect } from 'react';

export const useIntelligentPreloader = () => {
  useEffect(() => {
    // Preload después del initial render para no afectar FCP
    const timeoutId = setTimeout(() => {
      // Precargar componentes críticos después de 2 segundos
      preloadCriticalComponents();
    }, 2000);

    return () => clearTimeout(timeoutId);
  }, []);

  const preloadCriticalComponents = async () => {
    try {
      // Precargar en orden de prioridad
      const preloadPromises = [
        // Dashboard components (alta probabilidad de uso)
        import('../pages/dashboard/CDDashboard'),
        import('../pages/jobs/Index'),

        // 🚨 PRODUCCIÓN: Preload deshabilitado - sin localStorage
        // En producción, el preloading es automático por las cookies httpOnly
        Promise.resolve(),

        // 🚨 PRODUCCIÓN: Stats preload deshabilitado - sin localStorage
        // El servidor determina permisos automáticamente via cookies
        Promise.resolve(),
      ];

      await Promise.allSettled(preloadPromises);
      console.log('🚀 Critical components preloaded (production mode)');
    } catch (error) {
      console.warn('⚠️ Preload failed:', error);
    }
  };

  const shouldPreloadStats = (): boolean => {
    // 🚨 PRODUCCIÓN: Sin localStorage, el servidor maneja permisos automáticamente
    return false;
  };
};

// Hook para precargar cuando el usuario hace hover sobre enlaces
export const useHoverPreload = () => {
  const preloadOnHover = (importFn: () => Promise<any>) => {
    return {
      onMouseEnter: () => {
        // Precargar cuando el usuario hace hover
        importFn().catch(console.warn);
      }
    };
  };

  return { preloadOnHover };
};

export default useIntelligentPreloader;
