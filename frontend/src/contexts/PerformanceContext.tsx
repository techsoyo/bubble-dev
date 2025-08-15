import React, { createContext, useContext, useEffect, useState } from 'react';

interface PerformanceContextType {
  metrics: {
    fcp: number | null; // First Contentful Paint
    lcp: number | null; // Largest Contentful Paint
    fid: number | null; // First Input Delay
    cls: number | null; // Cumulative Layout Shift
    ttfb: number | null; // Time to First Byte
    networkInfo: {
      effectiveType: string | null;
      downlink: number | null;
      rtt: number | null;
    };
  };
  isLowEndDevice: boolean;
  isLowEndNetwork: boolean;
  prefetch: (url: string, as?: string) => void;
  preconnect: (url: string, crossorigin?: boolean) => void;
}

const defaultContext: PerformanceContextType = {
  metrics: {
    fcp: null,
    lcp: null,
    fid: null,
    cls: null,
    ttfb: null,
    networkInfo: {
      effectiveType: null,
      downlink: null,
      rtt: null
    }
  },
  isLowEndDevice: false,
  isLowEndNetwork: false,
  prefetch: () => { },
  preconnect: () => { }
};

const PerformanceContext = createContext<PerformanceContextType>(defaultContext);

export const usePerformance = () => useContext(PerformanceContext);

interface PerformanceProviderProps {
  children: React.ReactNode;
}

export const PerformanceProvider: React.FC<PerformanceProviderProps> = ({ children }) => {
  const [metrics, setMetrics] = useState(defaultContext.metrics);
  const [isLowEndDevice, setIsLowEndDevice] = useState(false);
  const [isLowEndNetwork, setIsLowEndNetwork] = useState(false);

  // Detectar dispositivos y redes de bajo rendimiento
  useEffect(() => {
    // Detectar dispositivos de bajo rendimiento
    const deviceMemory = (navigator as any).deviceMemory;
    const hardwareConcurrency = navigator.hardwareConcurrency || 0;

    if (deviceMemory && deviceMemory < 4 || hardwareConcurrency < 4) {
      setIsLowEndDevice(true);
    }

    // Detectar conexiones de red lentas
    if ('connection' in navigator) {
      const connection = (navigator as any).connection;

      if (connection) {
        const effectiveType = connection.effectiveType;
        const downlink = connection.downlink;

        setMetrics(prev => ({
          ...prev,
          networkInfo: {
            effectiveType: effectiveType || null,
            downlink: downlink || null,
            rtt: connection.rtt || null
          }
        }));

        if (effectiveType === 'slow-2g' || effectiveType === '2g' || effectiveType === '3g' || downlink < 1) {
          setIsLowEndNetwork(true);
        }

        // Escuchar cambios en la conexión
        connection.addEventListener('change', () => {
          setMetrics(prev => ({
            ...prev,
            networkInfo: {
              effectiveType: connection.effectiveType || null,
              downlink: connection.downlink || null,
              rtt: connection.rtt || null
            }
          }));

          setIsLowEndNetwork(
            connection.effectiveType === 'slow-2g' ||
            connection.effectiveType === '2g' ||
            connection.effectiveType === '3g' ||
            connection.downlink < 1
          );
        });
      }
    }
  }, []);

  // Medir Web Vitals
  useEffect(() => {
    if (typeof window !== 'undefined' && 'PerformanceObserver' in window) {
      // First Contentful Paint (FCP)
      const fcpObserver = new PerformanceObserver((entryList) => {
        const entries = entryList.getEntries();
        if (entries.length > 0) {
          const fcp = entries[0].startTime;
          setMetrics(prev => ({ ...prev, fcp }));
        }
      });

      try {
        fcpObserver.observe({ type: 'paint', buffered: true });
      } catch (e) {
        console.warn('FCP measurement not supported', e);
      }

      // Largest Contentful Paint (LCP)
      const lcpObserver = new PerformanceObserver((entryList) => {
        const entries = entryList.getEntries();
        const lastEntry = entries[entries.length - 1];
        if (lastEntry) {
          const lcp = lastEntry.startTime;
          setMetrics(prev => ({ ...prev, lcp }));
        }
      });

      try {
        lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
      } catch (e) {
        console.warn('LCP measurement not supported', e);
      }

      // First Input Delay (FID)
      const fidObserver = new PerformanceObserver((entryList) => {
        const entries = entryList.getEntries();
        if (entries.length > 0) {
          const firstInput = entries[0];
          const fid = firstInput.processingStart - firstInput.startTime;
          setMetrics(prev => ({ ...prev, fid }));
        }
      });

      try {
        fidObserver.observe({ type: 'first-input', buffered: true });
      } catch (e) {
        console.warn('FID measurement not supported', e);
      }

      // Layout Shifts (CLS)
      let clsValue = 0;
      const clsEntries: any[] = [];

      const clsObserver = new PerformanceObserver((entryList) => {
        const entries = entryList.getEntries() as any[];

        entries.forEach(entry => {
          // Solo contar si no fue causado por interacción del usuario
          if (!entry.hadRecentInput) {
            clsValue += entry.value;
            clsEntries.push(entry);
            setMetrics(prev => ({ ...prev, cls: clsValue }));
          }
        });
      });

      try {
        clsObserver.observe({ type: 'layout-shift', buffered: true });
      } catch (e) {
        console.warn('CLS measurement not supported', e);
      }

      // Time to First Byte (TTFB)
      const navigationEntries = performance.getEntriesByType('navigation');
      if (navigationEntries.length > 0) {
        const navigationEntry = navigationEntries[0] as PerformanceNavigationTiming;
        const ttfb = navigationEntry.responseStart;
        setMetrics(prev => ({ ...prev, ttfb }));
      }

      return () => {
        fcpObserver.disconnect();
        lcpObserver.disconnect();
        fidObserver.disconnect();
        clsObserver.disconnect();
      };
    }

    return undefined;
  }, []);

  // Función para prefetch de recursos
  const prefetch = (url: string, as: string = 'fetch') => {
    if (isLowEndNetwork) return; // No hacer prefetch en redes lentas

    const linkElement = document.createElement('link');
    linkElement.rel = 'prefetch';
    linkElement.href = url;
    linkElement.as = as;
    document.head.appendChild(linkElement);
  };

  // Función para preconnect a orígenes
  const preconnect = (url: string, crossorigin: boolean = true) => {
    const linkElement = document.createElement('link');
    linkElement.rel = 'preconnect';
    linkElement.href = url;
    if (crossorigin) {
      linkElement.crossOrigin = 'anonymous';
    }
    document.head.appendChild(linkElement);
  };

  return (
    <PerformanceContext.Provider
      value={{
        metrics,
        isLowEndDevice,
        isLowEndNetwork,
        prefetch,
        preconnect
      }}
    >
      {children}
    </PerformanceContext.Provider>
  );
};

export default PerformanceProvider;
