import React, { createContext, useCallback, useContext, useReducer, ReactNode } from 'react';
import {
  CvFormContextState,
  CvFormAction,
  cvFormReducer,
  initialCvFormState,
  CvFormData,
  CvFormState
} from '../domain/cvSchema';

/**
 * Contexto React para el estado global del formulario CV
 * 
 * Maneja los estados finitos:
 * - idle: aún no subió PDF
 * - uploading: subiendo archivo
 * - parsing: esperando IA
 * - ready: form con datos pre-rellenados
 * - manual: falló IA o usuario elige carga manual
 * - saving: enviando confirmación
 */

interface CvFormContextType {
  state: CvFormContextState;
  dispatch: React.Dispatch<CvFormAction>;

  // Actions helpers
  setFormState: (newState: CvFormState) => void;
  setFormData: (data: CvFormData) => void;
  updateField: (field: keyof CvFormData, value: any) => void;
  setErrors: (errors: string[]) => void;
  setLoading: (loading: boolean) => void;
  setFile: (file: File) => void;
  resetForm: () => void;
  prefillFromAI: (data: CvFormData) => void;
  switchToManual: () => void;
}

const CvFormContext = createContext<CvFormContextType | undefined>(undefined);

interface CvFormProviderProps {
  children: ReactNode;
}

export const CvFormProvider: React.FC<CvFormProviderProps> = ({ children }) => {
  const [state, dispatch] = useReducer(cvFormReducer, initialCvFormState);

  // Helper functions para simplificar el uso
  const setFormState = (newState: CvFormState) => {
    dispatch({ type: 'SET_STATE', payload: newState });
  };

  const setFormData = (data: CvFormData) => {
    dispatch({ type: 'SET_DATA', payload: data });
  };

  const updateField = useCallback((field: keyof CvFormData, value: any) => {
    dispatch({ type: 'UPDATE_FIELD', payload: { field, value } });
  }, []);

  const setErrors = (errors: string[]) => {
    dispatch({ type: 'SET_ERRORS', payload: errors });
  };

  const setLoading = (loading: boolean) => {
    dispatch({ type: 'SET_LOADING', payload: loading });
  };

  const setFile = (file: File) => {
    dispatch({ type: 'SET_FILE', payload: file });
  };

  const resetForm = () => {
    dispatch({ type: 'RESET_FORM' });
  };

  const prefillFromAI = (data: CvFormData) => {
    dispatch({ type: 'PREFILL_FROM_AI', payload: data });
  };

  const switchToManual = () => {
    dispatch({ type: 'SWITCH_TO_MANUAL' });
  };

  const contextValue: CvFormContextType = {
    state,
    dispatch,
    setFormState,
    setFormData,
    updateField,
    setErrors,
    setLoading,
    setFile,
    resetForm,
    prefillFromAI,
    switchToManual
  };

  return (
    <CvFormContext.Provider value={contextValue}>
      {children}
    </CvFormContext.Provider>
  );
};

// Hook personalizado para usar el contexto
export const useCvForm = (): CvFormContextType => {
  const context = useContext(CvFormContext);
  if (!context) {
    throw new Error('useCvForm debe ser usado dentro de un CvFormProvider');
  }
  return context;
};

// Hook para obtener solo el estado actual
export const useCvFormState = () => {
  const { state } = useCvForm();
  return state;
};

// Hook para obtener solo los datos del formulario
export const useCvFormData = () => {
  const { state } = useCvForm();
  return state.data;
};

// Hook para obtener solo el estado finito actual
export const useCvFormCurrentState = () => {
  const { state } = useCvForm();
  return state.state;
};
