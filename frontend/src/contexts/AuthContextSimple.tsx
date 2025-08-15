import { createContext, useContext, ReactNode } from 'react';

// Definir tipos simples para evitar problemas
interface User {
  id: string;
  email: string;
  role: string;
  name?: string;
}

interface AuthContextType {
  isLoggedIn: boolean;
  user: User | null;
  isLoading: boolean;
  login: (email: string, password: string, rememberMe?: boolean) => Promise<boolean>;
  logout: () => Promise<void>;
  refreshSession: () => Promise<void>;
  isInitialized: boolean;
}

// Crear el contexto
const AuthContext = createContext<AuthContextType | undefined>(undefined);

// Provider simple para evitar problemas de compilación
export function AuthProvider({ children }: { children: ReactNode }) {
  // Valores por defecto para que compile en producción
  const value: AuthContextType = {
    isLoggedIn: false,
    user: null,
    isLoading: false,
    login: async () => false,
    logout: async () => { },
    refreshSession: async () => { },
    isInitialized: true
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

// Hook para usar el contexto
export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}

export default AuthContext;
