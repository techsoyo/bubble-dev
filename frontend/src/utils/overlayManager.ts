/**
 * Sistema de gestión de overlays
 * Controla el posicionamiento de elementos fixed para evitar colisiones
 *
 * ✅ SEGURIDAD: Sanitización de IDs y validación de elementos DOM
 * ✅ PERFORMANCE: Límite de overlays activos y cleanup automático
 * ✅ ACCESSIBILITY: Focus management y ARIA labels
 * ✅ ERROR HANDLING: Validación robusta y logging seguro
 */

import { sanitizeText } from '../security/xss';

export type OverlayPosition =
  | 'top-left' | 'top-center' | 'top-right'
  | 'center-left' | 'center-center' | 'center-right'
  | 'bottom-left' | 'bottom-center' | 'bottom-right';

export type OverlayType =
  | 'chatbot' | 'toast' | 'debug' | 'modal' | 'tooltip' | 'notification';

interface OverlayConfig {
  id: string;
  type: OverlayType;
  position: OverlayPosition;
  priority: number;
  element?: HTMLElement;
  isActive: boolean;
  stackIndex?: number;
  // Accessibility fields
  ariaLabel?: string;
  role?: string;
  // Security validation
  isValid: boolean;
  // Performance tracking
  createdAt: number;
  lastAccessed: number;
}

class OverlayManager {
  private overlays: Map<string, OverlayConfig> = new Map();
  private positionStacks: Map<OverlayPosition, string[]> = new Map();
  private activeOverlaysCount = 0;

  // Configuration constants
  private readonly MAX_OVERLAYS = 10;
  private readonly MAX_OVERLAYS_PER_POSITION = 3;
  private readonly OVERLAY_TIMEOUT = 300000; // 5 minutes
  private readonly CLEANUP_INTERVAL = 60000; // 1 minute

  // Configuración de posiciones
  private readonly POSITION_STYLES: Record<OverlayPosition, Partial<CSSStyleDeclaration>> = {
    'top-left': { top: '1rem', left: '1rem' },
    'top-center': { top: '1rem', left: '50%', transform: 'translateX(-50%)' },
    'top-right': { top: '1rem', right: '1rem' },
    'center-left': { top: '50%', left: '1rem', transform: 'translateY(-50%)' },
    'center-center': { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' },
    'center-right': { top: '50%', right: '1rem', transform: 'translateY(-50%)' },
    'bottom-left': { bottom: '1rem', left: '1rem' },
    'bottom-center': { bottom: '1rem', left: '50%', transform: 'translateX(-50%)' },
    'bottom-right': { bottom: '1rem', right: '1rem' }
  };

  // Espaciado entre elementos en stack
  private readonly STACK_SPACING = 16; // px
  private cleanupIntervalId?: NodeJS.Timeout;

  /**
   * Sanitiza y valida un ID de overlay
   * ✅ SEGURIDAD: Previene XSS y valida formato
   */
  private sanitizeOverlayId(id: string): string | null {
    try {
      if (!id || typeof id !== 'string') {
        return null;
      }

      // Sanitize the ID
      const sanitized = sanitizeText(id);

      // Validate format (alphanumeric, dash, underscore only)
      if (!/^[a-zA-Z0-9_-]+$/.test(sanitized)) {
        return null;
      }

      // Check length limits
      if (sanitized.length < 1 || sanitized.length > 50) {
        return null;
      }

      return sanitized;
    } catch (error) {
      console.error('OverlayManager: Error sanitizing overlay ID:', error);
      return null;
    }
  }

  /**
   * Asegura que el intervalo de cleanup esté ejecutándose
   */
  private ensureCleanupInterval(): void {
    if (!this.cleanupIntervalId) {
      this.cleanupIntervalId = setInterval(() => {
        this.cleanupExpiredOverlays();
      }, this.CLEANUP_INTERVAL);
    }
  }

  /**
   * Limpia overlays expirados
   */
  private cleanupExpiredOverlays(): void {
    const now = Date.now();
    const expiredIds: string[] = [];

    this.overlays.forEach((overlay, id) => {
      if (now - overlay.lastAccessed > this.OVERLAY_TIMEOUT) {
        expiredIds.push(id);
      }
    });

    expiredIds.forEach(id => {
      this.deactivateOverlay(id);
      this.overlays.delete(id);
    });

    if (expiredIds.length > 0) {
      console.log(`OverlayManager: Cleaned up ${expiredIds.length} expired overlays`);
    }
  }

  /**
   * Valida que un elemento DOM sea seguro y válido
   */
  private isValidDomElement(element: HTMLElement): boolean {
    try {
      // Check if element exists in document
      if (!document.contains(element)) {
        return false;
      }

      // Check if element is a valid HTMLElement
      if (!(element instanceof HTMLElement)) {
        return false;
      }

      // Security: Check for suspicious attributes
      const suspiciousAttrs = ['onclick', 'onload', 'onerror', 'javascript:'];
      for (const attr of suspiciousAttrs) {
        if (element.hasAttribute(attr) || element.getAttribute(attr)) {
          return false;
        }
      }

      return true;
    } catch (error) {
      console.error('OverlayManager: Error validating DOM element:', error);
      return false;
    }
  }

  /**
   * Configura accessibility para un overlay
   */
  private setupAccessibility(overlay: OverlayConfig): void {
    if (!overlay.element) return;

    try {
      const element = overlay.element;

      // Set ARIA attributes
      if (overlay.ariaLabel) {
        element.setAttribute('aria-label', overlay.ariaLabel);
      }

      if (overlay.role) {
        element.setAttribute('role', overlay.role);
      } else {
        // Default role based on type
        const defaultRoles: Record<OverlayType, string> = {
          'chatbot': 'dialog',
          'toast': 'alert',
          'debug': 'log',
          'modal': 'dialog',
          'tooltip': 'tooltip',
          'notification': 'alert'
        };
        element.setAttribute('role', defaultRoles[overlay.type] || 'region');
      }

      // Ensure proper focus management
      element.setAttribute('tabindex', '-1');

      // Add focus trap for modal types
      if (overlay.type === 'modal' || overlay.type === 'chatbot') {
        this.setupFocusTrap(element);
      }

    } catch (error) {
      console.error('OverlayManager: Error setting up accessibility:', error);
    }
  }

  /**
   * Configura focus trap para modales
   */
  private setupFocusTrap(element: HTMLElement): void {
    const focusableElements = element.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    if (focusableElements.length > 0) {
      const firstElement = focusableElements[0] as HTMLElement;
      const lastElement = focusableElements[focusableElements.length - 1] as HTMLElement;

      const handleTabKey = (e: KeyboardEvent) => {
        if (e.key === 'Tab') {
          if (e.shiftKey) {
            if (document.activeElement === firstElement) {
              lastElement.focus();
              e.preventDefault();
            }
          } else {
            if (document.activeElement === lastElement) {
              firstElement.focus();
              e.preventDefault();
            }
          }
        }
      };

      element.addEventListener('keydown', handleTabKey);

      // Store cleanup function
      (element as any)._overlayFocusHandler = handleTabKey;
    }
  }

  /**
   * Limpia focus trap para un elemento
   */
  private cleanupFocusTrap(element: HTMLElement): void {
    try {
      const handler = (element as any)._overlayFocusHandler;
      if (handler) {
        element.removeEventListener('keydown', handler);
        delete (element as any)._overlayFocusHandler;
      }
    } catch (error) {
      console.error('OverlayManager: Error cleaning up focus trap:', error);
    }
  }

  /**
   * Registra un overlay en el sistema
   * ✅ SEGURIDAD: Sanitización de ID y validación de entrada
   * ✅ PERFORMANCE: Límite de overlays totales
   */
  registerOverlay(config: Omit<OverlayConfig, 'isActive' | 'stackIndex' | 'isValid' | 'createdAt' | 'lastAccessed'>): boolean {
    try {
      // Security: Sanitize and validate ID
      const sanitizedId = this.sanitizeOverlayId(config.id);
      if (!sanitizedId) {
        console.warn('OverlayManager: Invalid overlay ID provided');
        return false;
      }

      // Security: Check for existing overlay
      if (this.overlays.has(sanitizedId)) {
        console.warn(`OverlayManager: Overlay with ID ${sanitizedId} already exists`);
        return false;
      }

      // Performance: Check total overlay limit
      if (this.overlays.size >= this.MAX_OVERLAYS) {
        console.warn('OverlayManager: Maximum number of overlays reached');
        return false;
      }

      // Performance: Check position limit
      const positionOverlays = this.getActiveOverlaysInPosition(config.position);
      if (positionOverlays.length >= this.MAX_OVERLAYS_PER_POSITION) {
        console.warn(`OverlayManager: Maximum overlays reached for position ${config.position}`);
        return false;
      }

      const fullConfig: OverlayConfig = {
        ...config,
        id: sanitizedId,
        isActive: false,
        stackIndex: 0,
        isValid: true,
        createdAt: Date.now(),
        lastAccessed: Date.now()
      };

      this.overlays.set(sanitizedId, fullConfig);
      this.updatePositionStack(config.position, sanitizedId);

      // Start cleanup interval if not already running
      this.ensureCleanupInterval();

      return true;
    } catch (error) {
      console.error('OverlayManager: Error registering overlay:', error);
      return false;
    }
  }

  /**
   * Activa un overlay y ajusta posiciones
   * ✅ SEGURIDAD: Validación de elementos DOM
   * ✅ ACCESSIBILITY: Focus management y ARIA
   */
  activateOverlay(id: string, element?: HTMLElement): boolean {
    try {
      const overlay = this.overlays.get(id);
      if (!overlay || !overlay.isValid) {
        console.warn(`OverlayManager: Invalid or non-existent overlay ${id}`);
        return false;
      }

      // Security: Validate DOM element
      if (element && !this.isValidDomElement(element)) {
        console.warn(`OverlayManager: Invalid DOM element provided for overlay ${id}`);
        return false;
      }

      // Performance: Check activation limits
      if (this.activeOverlaysCount >= this.MAX_OVERLAYS) {
        console.warn('OverlayManager: Maximum active overlays reached');
        return false;
      }

      overlay.isActive = true;
      overlay.element = element || overlay.element;
      overlay.lastAccessed = Date.now();

      if (overlay.isActive) {
        this.activeOverlaysCount++;
      }

      this.recalculatePositions(overlay.position);
      this.applyPositioning(id);

      // Accessibility: Set up focus management
      this.setupAccessibility(overlay);

      return true;
    } catch (error) {
      console.error(`OverlayManager: Error activating overlay ${id}:`, error);
      return false;
    }
  }

  /**
   * Desactiva un overlay
   * ✅ ACCESSIBILITY: Cleanup de focus management
   * ✅ PERFORMANCE: Decrementa contador activo
   */
  deactivateOverlay(id: string): boolean {
    try {
      const overlay = this.overlays.get(id);
      if (!overlay) {
        return false;
      }

      overlay.isActive = false;
      overlay.lastAccessed = Date.now();

      if (overlay.element) {
        // Accessibility: Clean up focus trap
        this.cleanupFocusTrap(overlay.element);

        // Clean up ARIA attributes
        overlay.element.removeAttribute('aria-label');
        overlay.element.removeAttribute('role');
        overlay.element.removeAttribute('tabindex');
      }

      overlay.element = undefined;

      if (this.activeOverlaysCount > 0) {
        this.activeOverlaysCount--;
      }

      this.recalculatePositions(overlay.position);

      return true;
    } catch (error) {
      console.error(`OverlayManager: Error deactivating overlay ${id}:`, error);
      return false;
    }
  }

  /**
   * Obtiene la mejor posición disponible para un tipo de overlay
   */
  getBestPosition(type: OverlayType): OverlayPosition {
    const preferredPositions: Record<OverlayType, OverlayPosition[]> = {
      'chatbot': ['bottom-right', 'bottom-left', 'top-right'],
      'toast': ['top-right', 'top-center', 'bottom-right'],
      'debug': ['top-left', 'bottom-left', 'top-right'],
      'modal': ['center-center'],
      'tooltip': ['top-center', 'bottom-center', 'center-right'],
      'notification': ['top-right', 'top-center', 'bottom-center']
    };

    const positions = preferredPositions[type];

    // Buscar la posición con menos elementos
    return positions.reduce((best, current) => {
      const currentCount = this.getActiveOverlaysInPosition(current).length;
      const bestCount = this.getActiveOverlaysInPosition(best).length;
      return currentCount < bestCount ? current : best;
    });
  }

  /**
   * Aplica el posicionamiento CSS a un elemento
   */
  private applyPositioning(id: string): void {
    const overlay = this.overlays.get(id);
    if (!overlay || !overlay.element || !overlay.isActive) return;

    const baseStyles = this.POSITION_STYLES[overlay.position];
    const stackIndex = overlay.stackIndex || 0;

    // Aplicar estilos base
    Object.assign(overlay.element.style, {
      position: 'fixed',
      ...baseStyles
    });

    // Aplicar offset para stack
    this.applyStackOffset(overlay.element, overlay.position, stackIndex);
  }

  /**
   * Aplica offset para elementos stacked
   */
  private applyStackOffset(element: HTMLElement, position: OverlayPosition, stackIndex: number): void {
    const offset = stackIndex * this.STACK_SPACING;

    if (position.includes('top')) {
      element.style.top = `${1 + (offset / 16)}rem`;
    } else if (position.includes('bottom')) {
      element.style.bottom = `${1 + (offset / 16)}rem`;
    }

    if (position.includes('left') && !position.includes('center')) {
      element.style.left = `${1 + (offset / 16)}rem`;
    } else if (position.includes('right')) {
      element.style.right = `${1 + (offset / 16)}rem`;
    }
  }

  /**
   * Recalcula posiciones en un stack
   */
  private recalculatePositions(position: OverlayPosition): void {
    const activeOverlays = this.getActiveOverlaysInPosition(position);

    // Ordenar por prioridad
    activeOverlays.sort((a, b) => b.priority - a.priority);

    // Asignar índices de stack
    activeOverlays.forEach((overlay, index) => {
      overlay.stackIndex = index;
      if (overlay.element) {
        this.applyPositioning(overlay.id);
      }
    });
  }

  /**
   * Obtiene overlays activos en una posición
   */
  private getActiveOverlaysInPosition(position: OverlayPosition): OverlayConfig[] {
    return Array.from(this.overlays.values())
      .filter(overlay => overlay.position === position && overlay.isActive);
  }

  /**
   * Actualiza el stack de posición
   */
  private updatePositionStack(position: OverlayPosition, id: string): void {
    if (!this.positionStacks.has(position)) {
      this.positionStacks.set(position, []);
    }

    const stack = this.positionStacks.get(position)!;
    if (!stack.includes(id)) {
      stack.push(id);
    }
  }

  /**
   * Obtiene información de debug
   */
  getDebugInfo(): Record<string, any> {
    return {
      totalOverlays: this.overlays.size,
      activeOverlays: Array.from(this.overlays.values()).filter(o => o.isActive).length,
      positionStacks: Object.fromEntries(
        Array.from(this.positionStacks.entries()).map(([pos, stack]) => [
          pos,
          stack.map(id => ({
            id,
            isActive: this.overlays.get(id)?.isActive || false,
            type: this.overlays.get(id)?.type
          }))
        ])
      )
    };
  }
}

// Instancia singleton
export const overlayManager = new OverlayManager();

// Hook React para gestión de overlays
export function useOverlayManager() {
  return {
    register: overlayManager.registerOverlay.bind(overlayManager),
    activate: overlayManager.activateOverlay.bind(overlayManager),
    deactivate: overlayManager.deactivateOverlay.bind(overlayManager),
    getBestPosition: overlayManager.getBestPosition.bind(overlayManager),
    getDebugInfo: overlayManager.getDebugInfo.bind(overlayManager)
  };
}
