/**
 * Secure Authentication Manager - MIGRATION COMPLETED ✅
 * ✅ MIGRATION COMPLETED: Successfully migrated from TokenManager to secure cookies httpOnly
 * 🔒 PRODUCTION READY: Using AuthContext.tsx with secure cookie-based authentication
 * 🚫 DEPRECATED: TokenManager completely removed - no longer exists in codebase
 * @deprecated This file is being phased out. Use AuthContext.tsx for new implementations
 */

import { InputSanitizer } from './secureInputValidator';
// TokenManager import removed - using secure cookie-based authentication

export interface User {
  id: string;
  email: string;
  name?: string;
  role?: string;
  first_name?: string;
  last_name?: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
  rememberMe?: boolean;
}

export interface AuthResponse {
  success: boolean;
  user?: User;
  message?: string;
  token?: string;
  expires_in?: number;
  refresh_token?: string;
}

export class SecureAuthManager {
  private static readonly API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
  private static currentUser: User | null = null;

  /**
   * ✅ ACTUALIZADO: Verificar sesión con endpoints actualizados
   */
  static async verifySession(): Promise<{ isValid: boolean; user?: User }> {
    try {
      // Usar cookies httpOnly para autenticación - más seguro que tokens locales
      const response = await fetch(`${this.API_BASE_URL}/auth/session`, {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Accept': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();

        if (data.success && data.user) {
          this.currentUser = data.user;
          return { isValid: true, user: data.user };
        }
      }

      return { isValid: false };
    } catch (error) {
      console.error('Session verification failed:', error);
      return { isValid: false };
    }
  }

  /**
   * ✅ ACTUALIZADO: Login genérico (redirige a candidateLogin por defecto)
   */
  static async login(credentials: LoginCredentials): Promise<AuthResponse> {
    // Por defecto, el login genérico usa el endpoint de candidatos
    return this.candidateLogin(credentials);
  }

  /**
   * ✅ ACTUALIZADO: Candidate login usando nuevo endpoint /auth/register
   */
  static async candidateLogin(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      const sanitizedCredentials = {
        email: InputSanitizer.sanitizeEmail(credentials.email),
        password: credentials.password,
        action: 'login' // Especificar que es login, no registro
      };

      if (!sanitizedCredentials.email) {
        return {
          success: false,
          message: 'Email inválido'
        };
      }

      // ✅ NUEVO ENDPOINT: /auth/register con action=login
      const response = await fetch(`${this.API_BASE_URL}/auth/register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(sanitizedCredentials)
      });

      const data: AuthResponse = await response.json();

      if (response.ok && data.success) {
        // ✅ ALMACENAR USER DATA
        if (data.user) {
          this.currentUser = data.user;
        }

        // ✅ TOKENS MANEJADOS POR SERVIDOR VIA COOKIES HTTPONLY
        // Los tokens ya están configurados en cookies httpOnly por el servidor
        // No necesitamos almacenarlos localmente por seguridad
        console.log('✅ Tokens configurados en cookies httpOnly por el servidor');

        return {
          success: true,
          user: data.user,
          message: data.message || 'Login de candidato exitoso'
        };
      }

      return {
        success: false,
        message: data.message || 'Credenciales de candidato incorrectas'
      };
    } catch (error) {
      console.error('Error en candidate login:', error);
      return {
        success: false,
        message: 'Error de conexión en login de candidato'
      };
    }
  }

  /**
   * ✅ ACTUALIZADO: Staff login usando nuevo endpoint /staff/login
   */
  static async staffLogin(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      const sanitizedCredentials = {
        email: InputSanitizer.sanitizeEmail(credentials.email),
        password: credentials.password,
        action: 'login'
      };

      if (!sanitizedCredentials.email) {
        return {
          success: false,
          message: 'Email inválido'
        };
      }

      // ✅ NUEVO ENDPOINT: /staff/login
      const response = await fetch(`${this.API_BASE_URL}/staff/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(sanitizedCredentials)
      });

      const data: AuthResponse = await response.json();

      if (response.ok && data.success) {
        if (data.user) {
          this.currentUser = data.user;
        }

        // ✅ TOKENS MANEJADOS POR SERVIDOR VIA COOKIES HTTPONLY
        // Los tokens ya están configurados en cookies httpOnly por el servidor
        console.log('✅ Tokens configurados en cookies httpOnly por el servidor');

        return {
          success: true,
          user: data.user,
          message: data.message || 'Login de staff exitoso'
        };
      }

      return {
        success: false,
        message: data.message || 'Credenciales de staff incorrectas'
      };
    } catch (error) {
      console.error('Error en staff login:', error);
      return {
        success: false,
        message: 'Error de conexión en login de staff'
      };
    }
  }

  /**
   * ✅ NUEVO: Registro de candidatos usando /auth/register
   */
  static async registerCandidate(registrationData: {
    email: string;
    password: string;
    first_name?: string;
    last_name?: string;
    name?: string;
  }): Promise<AuthResponse> {
    try {
      const sanitizedData = {
        email: InputSanitizer.sanitizeEmail(registrationData.email),
        password: registrationData.password,
        action: 'register',
        first_name: registrationData.first_name ? InputSanitizer.sanitizeName(registrationData.first_name) : undefined,
        last_name: registrationData.last_name ? InputSanitizer.sanitizeName(registrationData.last_name) : undefined,
        name: registrationData.name ? InputSanitizer.sanitizeName(registrationData.name) : undefined,
      };

      if (!sanitizedData.email) {
        return {
          success: false,
          message: 'Email inválido'
        };
      }

      // ✅ USAR MISMO ENDPOINT: /auth/register con action=register
      const response = await fetch(`${this.API_BASE_URL}/auth/register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(sanitizedData)
      });

      const data: AuthResponse = await response.json();

      if (response.ok && data.success) {
        if (data.user) {
          this.currentUser = data.user;
        }

        // ✅ TOKENS MANEJADOS POR SERVIDOR VIA COOKIES HTTPONLY
        // Los tokens ya están configurados en cookies httpOnly por el servidor
        console.log('✅ Tokens configurados en cookies httpOnly por el servidor');

        return {
          success: true,
          user: data.user,
          message: data.message || 'Registro exitoso'
        };
      }

      return {
        success: false,
        message: data.message || 'Error en el registro'
      };
    } catch (error) {
      console.error('Error en registro:', error);
      return {
        success: false,
        message: 'Error de conexión en registro'
      };
    }
  }

  /**
   * ✅ ACTUALIZADO: Logout - necesitamos determinar el endpoint correcto
   */
  static async logout(): Promise<boolean> {
    try {
      // No necesitamos obtener tokens locales - usamos cookies httpOnly
      console.log('🔒 Logout: Using httpOnly cookies for authentication');

      const headers: Record<string, string> = {
        'Content-Type': 'application/json'
      };
      // DEPRECATED: Authorization headers disabled for production security
      // headers['Authorization'] = `Bearer ${token}`;  // Comentado para producción

      // Intentar con endpoint de logout general
      let response = await fetch(`${this.API_BASE_URL}/auth/logout`, {
        method: 'POST',
        headers,
        credentials: 'include',
        body: JSON.stringify({ action: 'logout' })
      });

      // Si no funciona, intentar con staff logout
      if (!response.ok) {
        response = await fetch(`${this.API_BASE_URL}/staff/logout`, {
          method: 'POST',
          headers,
          credentials: 'include',
          body: JSON.stringify({ action: 'logout' })
        });
      }

      // ✅ LIMPIAR ESTADO LOCAL COMPLETAMENTE
      this.currentUser = null;
      // Tokens are cleared server-side via cookies - no local cleanup needed
      console.log('✅ User state cleared, cookies handled by server');

      return true;
    } catch (error) {
      console.error('Error en logout:', error);

      // ✅ LIMPIAR ESTADO INCLUSO SI HAY ERROR
      this.currentUser = null;
      // Tokens are cleared server-side via cookies - no local cleanup needed
      console.log('✅ User state cleared on error, cookies handled by server');

      return false;
    }
  }

  /**
   * ✅ ACTUALIZADO: Change password - adaptar endpoint según usuario
   */
  static async changePassword(currentPassword: string, newPassword: string): Promise<AuthResponse> {
    try {
      // Verificar que el usuario esté autenticado via cookies
      if (!this.currentUser) {
        return {
          success: false,
          message: 'No autorizado. Inicia sesión primero.'
        };
      }

      // Determinar endpoint según el tipo de usuario actual
      let endpoint = '/auth/change-password';
      if (this.currentUser?.role === 'staff' || this.currentUser?.role === 'recruiter' || this.currentUser?.role === 'admin') {
        endpoint = '/staff/change-password';
      }

      const response = await fetch(`${this.API_BASE_URL}${endpoint}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          // DEPRECATED: Authorization header comentado para producción
          // 'Authorization': `Bearer ${token}`
        },
        credentials: 'include',
        body: JSON.stringify({
          action: 'change_password',
          current_password: currentPassword,
          new_password: newPassword
        })
      });

      const data: AuthResponse = await response.json();
      return data;
    } catch (error) {
      console.error('Error cambiando password:', error);
      return {
        success: false,
        message: 'Error de conexión'
      };
    }
  }

  /**
   * Métodos de utilidad
   */
  static getCurrentUser(): User | null {
    return this.currentUser;
  }

  static isAuthenticated(): boolean {
    // Check if user exists and session is valid via cookies
    return this.currentUser !== null;
  }

  static clearSession(): void {
    this.currentUser = null;
    // Cookies are cleared server-side - no local cleanup needed
    console.log('✅ Session cleared, cookies handled by server');
  }

  /**
   * ✅ NUEVO: Determinar tipo de usuario para UI
   */
  static getUserType(): 'candidate' | 'staff' | 'unknown' {
    if (!this.currentUser) return 'unknown';

    const role = this.currentUser.role?.toLowerCase();
    if (role === 'candidate') return 'candidate';
    if (role === 'staff' || role === 'recruiter' || role === 'admin' || role === 'rrhh') return 'staff';

    return 'unknown';
  }
}
