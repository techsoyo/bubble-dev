/**
 * Lazy Loading Component for PDF Processing
 * Optimiza el bundle principal cargando PDF.js solo cuando sea necesario
 */

import React, { lazy, Suspense } from 'react';
import { LoadingSpinner } from './ui/LoadingSpinner';

// Lazy load del componente UploadCV pesado
const UploadCVLazy = lazy(() =>
  import('./UploadCV').then(module => ({ default: module.default }))
);

interface UploadCVOptimizedProps {
  onSuccess?: (candidate: any) => void;
  jobId?: string;
}

export const UploadCVOptimized: React.FC<UploadCVOptimizedProps> = (props) => {
  return (
    <Suspense
      fallback={
        <div className="flex items-center justify-center min-h-[400px]">
          <LoadingSpinner size="large" />
          <span className="ml-3 text-gray-600">Cargando procesador de CV...</span>
        </div>
      }
    >
      <UploadCVLazy {...props} />
    </Suspense>
  );
};

export default UploadCVOptimized;
