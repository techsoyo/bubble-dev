/**
 * Lazy Loading Component for Statistics Dashboard
 * Optimiza la carga inicial cargando charts solo cuando sea necesario
 */

import React, { lazy, Suspense } from 'react';
import LoadingSpinner from '../../components/ui/LoadingSpinner';

// Lazy load del componente Estadisticas pesado
const EstadisticasLazy = lazy(() =>
  import('./Estadisticas').then(module => ({ default: module.default }))
);

interface EstadisticasOptimizedProps {
  // Props del componente original si las hay
}

export const EstadisticasOptimized: React.FC<EstadisticasOptimizedProps> = (props) => {
  return (
    <Suspense
      fallback={
        <div className="flex flex-col items-center justify-center min-h-[400px]">
          <LoadingSpinner size="large" />
          <span className="ml-3 text-gray-600">Cargando estadísticas...</span>
          <p className="text-sm text-gray-500 mt-2">Preparando gráficos y datos</p>
        </div>
      }
    >
      <EstadisticasLazy {...props} />
    </Suspense>
  );
};

export default EstadisticasOptimized;
