/**
 * Sistema de gestión de overlays
 * Controla el posicionamiento de elementos fixed para evitar colisiones
 */

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
}

class OverlayManager {
  private overlays: Map<string, OverlayConfig> = new Map();
  private positionStacks: Map<OverlayPosition, string[]> = new Map();

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

  /**
   * Registra un overlay en el sistema
   */
  registerOverlay(config: Omit<OverlayConfig, 'isActive' | 'stackIndex'>): void {
    const fullConfig: OverlayConfig = {
      ...config,
      isActive: false,
      stackIndex: 0
    };

    this.overlays.set(config.id, fullConfig);
    this.updatePositionStack(config.position, config.id);
  }

  /**
   * Activa un overlay y ajusta posiciones
   */
  activateOverlay(id: string, element?: HTMLElement): void {
    const overlay = this.overlays.get(id);
    if (!overlay) return;

    overlay.isActive = true;
    overlay.element = element;

    this.recalculatePositions(overlay.position);
    this.applyPositioning(id);
  }

  /**
   * Desactiva un overlay
   */
  deactivateOverlay(id: string): void {
    const overlay = this.overlays.get(id);
    if (!overlay) return;

    overlay.isActive = false;
    overlay.element = undefined;

    this.recalculatePositions(overlay.position);
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
