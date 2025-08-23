import { useEffect, useRef } from 'react';
import { overlayManager, OverlayType, OverlayPosition } from '../utils/overlayManager';

interface UseOverlayOptions {
  id: string;
  type: OverlayType;
  position?: OverlayPosition;
  priority?: number;
  autoPosition?: boolean;
}

/**
 * Hook para gestionar overlays con posicionamiento inteligente
 */
export function useOverlay({
  id,
  type,
  position,
  priority = 1000,
  autoPosition = true
}: UseOverlayOptions) {
  const elementRef = useRef<HTMLDivElement>(null);
  const isRegisteredRef = useRef(false);

  // Auto-determinar la mejor posición
  const finalPosition = position || (autoPosition ? overlayManager.getBestPosition(type) : 'bottom-right');

  useEffect(() => {
    // Registrar overlay en el sistema
    if (!isRegisteredRef.current) {
      overlayManager.registerOverlay({
        id,
        type,
        position: finalPosition,
        priority
      });
      isRegisteredRef.current = true;
    }

    return () => {
      // Cleanup al desmontar
      overlayManager.deactivateOverlay(id);
    };
  }, [id, type, finalPosition, priority]);

  const activate = () => {
    if (elementRef.current) {
      overlayManager.activateOverlay(id, elementRef.current);
    }
  };

  const deactivate = () => {
    overlayManager.deactivateOverlay(id);
  };

  return {
    elementRef,
    activate,
    deactivate,
    position: finalPosition
  };
}

/**
 * Hook para debug de overlays
 */
export function useOverlayDebug() {
  const getDebugInfo = () => overlayManager.getDebugInfo();

  return { getDebugInfo };
}
