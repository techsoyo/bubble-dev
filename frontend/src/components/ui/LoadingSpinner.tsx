import React from 'react';

interface LoadingSpinnerProps {
  size?: 'small' | 'medium' | 'large';
}

const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({ size = 'medium' }) => {
  const getSize = () => {
    switch (size) {
      case 'small': return 'w-4 h-4';
      case 'large': return 'w-10 h-10';
      default: return 'w-6 h-6';
    }
  };

  return (
    <div className="flex items-center justify-center p-4">
      <div className={`${getSize()} animate-spin rounded-full border-2 border-gray-300 border-t-blue-600`} />
      <span className="sr-only">Cargando...</span>
    </div>
  );
};

export default LoadingSpinner;
export { LoadingSpinner };
