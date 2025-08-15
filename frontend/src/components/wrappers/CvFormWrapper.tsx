import React, { useEffect } from 'react';
import { useCvForm } from '../../contexts/CvFormContext';
import { UnifiedCVForm } from '../forms/UnifiedCVForm';
import { CvFormData, mergeWithTemplate } from '../../domain/cvSchema';

interface CvFormWrapperProps {
  mode: 'validation' | 'manual';
  initialData: Partial<CvFormData>;
  isOpen: boolean;
  onClose: () => void;
  onSave: (data: CvFormData) => void;
}

/**
 * Wrapper que inicializa el contexto del formulario CV según el modo
 */
export const CvFormWrapper: React.FC<CvFormWrapperProps> = ({
  mode,
  initialData,
  isOpen,
  onClose,
  onSave
}) => {
  const { setFormData, setFormState, resetForm } = useCvForm();

  useEffect(() => {
    if (isOpen) {
      if (mode === 'validation') {
        // Pre-rellenar con datos de IA
        const mergedData = mergeWithTemplate(initialData);
        setFormData(mergedData);
        setFormState('ready');
      } else {
        // Modo manual - empezar con template vacío pero con datos iniciales
        const mergedData = mergeWithTemplate(initialData);
        setFormData(mergedData);
        setFormState('manual');
      }
    } else {
      // Limpiar al cerrar
      resetForm();
    }
  }, [isOpen, mode, initialData, setFormData, setFormState, resetForm]);

  const handleClose = () => {
    resetForm();
    onClose();
  };

  return (
    <UnifiedCVForm
      isOpen={isOpen}
      onClose={handleClose}
      onSave={onSave}
    />
  );
};
