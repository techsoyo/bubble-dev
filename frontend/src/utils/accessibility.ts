/**
 * Accessibility (A11y) Utilities - Versión Mejorada
 * 
 * Provides comprehensive accessibility features and utilities for production-ready
 * React applications that meet WCAG 2.1 AA standards.
 * 
 * Nuevas funciones de seguridad y UX mejoradas:
 * - Manejo seguro de focus
 * - Componentes de loading y error accesibles
 * - Respeto por preferencias de animación
 * - Validación de contraste de colores
 * 
 * @package Accessibility
 * @author Bubble Talents Development Team
 * @version 2.0.0
 * @since 2025-08-10
 */

import { useEffect, useRef, useState, useCallback } from 'react';

/**
 * ARIA live region types
 */
export type AriaLiveType = 'off' | 'polite' | 'assertive';

/**
 * ✅ NUEVA: Hook para announcements de screen reader seguros
 */
export const useScreenReaderAnnouncement = () => {
    const announce = useCallback((message: string, priority: AriaLiveType = 'polite') => {
        // Sanitizar mensaje para prevenir XSS
        const safeMessage = message.replace(/<[^>]*>/g, '');

        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', priority);
        announcement.setAttribute('aria-atomic', 'true');
        announcement.setAttribute('class', 'sr-only');
        announcement.textContent = safeMessage;

        document.body.appendChild(announcement);

        // Limpiar después del anuncio
        setTimeout(() => {
            if (announcement.parentNode) {
                document.body.removeChild(announcement);
            }
        }, 1000);
    }, []);

    return { announce };
};

/**
 * ✅ NUEVA: Hook para animaciones respetuosas
 */
export const useRespectfulAnimations = () => {
    const [prefersReducedMotion, setPrefersReducedMotion] = useState(false);

    useEffect(() => {
        const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        setPrefersReducedMotion(mediaQuery.matches);

        const handler = (e: MediaQueryListEvent) => {
            setPrefersReducedMotion(e.matches);
        };

        mediaQuery.addListener(handler);
        return () => mediaQuery.removeListener(handler);
    }, []);

    return {
        prefersReducedMotion,
        animationDuration: prefersReducedMotion ? 0 : 300,
        shouldAnimate: !prefersReducedMotion
    };
};

/**
 * Focus management utilities
 */
export class FocusManager {
    private static focusStack: HTMLElement[] = [];

    /**
     * Trap focus within a container element
     */
    static trapFocus(container: HTMLElement): () => void {
        const focusableElements = this.getFocusableElements(container);
        if (focusableElements.length === 0) return () => { };

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        // Store current focused element
        const previouslyFocused = document.activeElement as HTMLElement;
        this.focusStack.push(previouslyFocused);

        // Focus first element
        firstElement?.focus();

        const handleKeyDown = (event: KeyboardEvent): void => {
            if (event.key !== 'Tab') return;

            if (event.shiftKey) {
                // Shift + Tab
                if (document.activeElement === firstElement) {
                    event.preventDefault();
                    lastElement?.focus();
                }
            } else {
                // Tab
                if (document.activeElement === lastElement) {
                    event.preventDefault();
                    firstElement?.focus();
                }
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        // Return cleanup function
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            const elementToFocus = this.focusStack.pop();
            elementToFocus?.focus();
        };
    }

    /**
     * Get all focusable elements within a container
     */
    static getFocusableElements(container: HTMLElement): HTMLElement[] {
        const focusableSelectors = [
            'a[href]',
            'button:not([disabled])',
            'input:not([disabled])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            '[tabindex]:not([tabindex="-1"])',
            '[contenteditable="true"]',
        ].join(', ');

        return Array.from(container.querySelectorAll(focusableSelectors)).filter(
            (element): element is HTMLElement => {
                return (
                    element instanceof HTMLElement &&
                    !element.hasAttribute('disabled') &&
                    !element.getAttribute('aria-hidden') &&
                    element.offsetParent !== null // Element is visible
                );
            }
        );
    }

    /**
     * Move focus to next/previous focusable element
     */
    static moveFocus(direction: 'next' | 'previous', container?: HTMLElement): void {
        const root = container || document.body;
        const focusableElements = this.getFocusableElements(root);
        const currentIndex = focusableElements.indexOf(document.activeElement as HTMLElement);

        if (currentIndex === -1) return;

        let nextIndex: number;
        if (direction === 'next') {
            nextIndex = (currentIndex + 1) % focusableElements.length;
        } else {
            nextIndex = currentIndex === 0 ? focusableElements.length - 1 : currentIndex - 1;
        }

        focusableElements[nextIndex]?.focus();
    }
}

/**
 * Hook for managing focus trap
 */
export function useFocusTrap(enabled: boolean): React.RefObject<HTMLElement> {
    const containerRef = useRef<HTMLElement>(null);

    useEffect(() => {
        if (!enabled || !containerRef.current) return undefined;

        const cleanup = FocusManager.trapFocus(containerRef.current);
        return cleanup;
    }, [enabled]);

    return containerRef;
}

/**
 * Hook for managing focus restoration
 */
export function useFocusRestore(): {
    saveFocus: () => void;
    restoreFocus: () => void;
} {
    const savedFocus = useRef<HTMLElement | null>(null);

    const saveFocus = useCallback(() => {
        savedFocus.current = document.activeElement as HTMLElement;
    }, []);

    const restoreFocus = useCallback(() => {
        if (savedFocus.current && typeof savedFocus.current.focus === 'function') {
            savedFocus.current.focus();
            savedFocus.current = null;
        }
    }, []);

    return { saveFocus, restoreFocus };
}

/**
 * ARIA live region manager
 */
export class AriaLiveManager {
    private static regions = new Map<string, HTMLElement>();

    /**
     * Create or get an ARIA live region
     */
    static getRegion(id: string, type: AriaLiveType = 'polite'): HTMLElement {
        if (this.regions.has(id)) {
            return this.regions.get(id)!;
        }

        const region = document.createElement('div');
        region.id = id;
        region.setAttribute('aria-live', type);
        region.setAttribute('aria-atomic', 'true');
        region.className = 'sr-only'; // Screen reader only
        document.body.appendChild(region);

        this.regions.set(id, region);
        return region;
    }

    /**
     * Announce a message to screen readers
     */
    static announce(message: string, type: AriaLiveType = 'polite'): void {
        const region = this.getRegion('announcements', type);
        region.textContent = message;

        // Clear after a short delay to allow for re-announcements
        setTimeout(() => {
            region.textContent = '';
        }, 1000);
    }

    /**
     * Remove all live regions
     */
    static cleanup(): void {
        this.regions.forEach((region) => {
            region.remove();
        });
        this.regions.clear();
    }
}

/**
 * Hook for ARIA live announcements
 */
export function useAnnouncer(): (message: string, type?: AriaLiveType) => void {
    return useCallback((message: string, type: AriaLiveType = 'polite') => {
        AriaLiveManager.announce(message, type);
    }, []);
}

/**
 * Keyboard navigation utilities
 */
export const KeyboardNavigation = {
    /**
     * Handle arrow key navigation for lists/grids
     */
    handleArrowKeys: (
        event: KeyboardEvent,
        orientation: 'horizontal' | 'vertical' | 'both' = 'vertical'
    ): boolean => {
        const { key } = event;
        const isHorizontal = orientation === 'horizontal' || orientation === 'both';
        const isVertical = orientation === 'vertical' || orientation === 'both';

        if ((isVertical && (key === 'ArrowUp' || key === 'ArrowDown')) ||
            (isHorizontal && (key === 'ArrowLeft' || key === 'ArrowRight'))) {

            event.preventDefault();

            const direction = (key === 'ArrowUp' || key === 'ArrowLeft') ? 'previous' : 'next';
            FocusManager.moveFocus(direction);

            return true;
        }

        return false;
    },

    /**
     * Handle Home/End keys for navigation
     */
    handleHomeEnd: (event: KeyboardEvent, container?: HTMLElement): boolean => {
        const { key } = event;

        if (key === 'Home' || key === 'End') {
            event.preventDefault();

            const root = container || document.body;
            const focusableElements = FocusManager.getFocusableElements(root);

            if (focusableElements.length > 0) {
                const target = key === 'Home' ? focusableElements[0] : focusableElements[focusableElements.length - 1];
                target?.focus();
            }

            return true;
        }

        return false;
    },

    /**
     * Handle Escape key for closing modals/dropdowns
     */
    handleEscape: (event: KeyboardEvent, onEscape: () => void): boolean => {
        if (event.key === 'Escape') {
            event.preventDefault();
            onEscape();
            return true;
        }
        return false;
    },
};

/**
 * Hook for keyboard navigation
 */
export function useKeyboardNavigation(
    options: {
        onEscape?: () => void;
        orientation?: 'horizontal' | 'vertical' | 'both';
        container?: React.RefObject<HTMLElement>;
    } = {}
): void {
    const { onEscape, orientation = 'vertical', container } = options;

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent): void => {
            // Handle Escape
            if (onEscape && KeyboardNavigation.handleEscape(event, onEscape)) {
                return;
            }

            // Handle Arrow keys
            if (KeyboardNavigation.handleArrowKeys(event, orientation)) {
                return;
            }

            // Handle Home/End
            KeyboardNavigation.handleHomeEnd(event, container?.current || undefined);
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [onEscape, orientation, container]);
}

/**
 * Color contrast utilities
 */
export const ColorContrast = {
    /**
     * Calculate relative luminance of a color
     */
    getLuminance: (hex: string): number => {
        const rgb = ColorContrast.hexToRgb(hex);
        if (!rgb) return 0;

        const { r, g, b } = rgb;
        const rgbValues = [r, g, b].map(c => {
            c = c / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        });

        const [rs = 0, gs = 0, bs = 0] = rgbValues;
        return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
    },

    /**
     * Convert hex color to RGB
     */
    hexToRgb: (hex: string): { r: number; g: number; b: number } | null => {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result && result[1] && result[2] && result[3] ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    },

    /**
     * Calculate contrast ratio between two colors
     */
    getContrastRatio: (color1: string, color2: string): number => {
        const lum1 = ColorContrast.getLuminance(color1);
        const lum2 = ColorContrast.getLuminance(color2);
        const brightest = Math.max(lum1, lum2);
        const darkest = Math.min(lum1, lum2);
        return (brightest + 0.05) / (darkest + 0.05);
    },

    /**
     * Check if color combination meets WCAG standards
     */
    meetsWCAG: (
        foreground: string,
        background: string,
        level: 'AA' | 'AAA' = 'AA',
        size: 'normal' | 'large' = 'normal'
    ): boolean => {
        const ratio = ColorContrast.getContrastRatio(foreground, background);

        if (level === 'AAA') {
            return size === 'large' ? ratio >= 4.5 : ratio >= 7;
        } else {
            return size === 'large' ? ratio >= 3 : ratio >= 4.5;
        }
    },
};

/**
 * Screen reader utilities
 */
export const ScreenReader = {
    /**
     * Hide element from screen readers but keep it visible
     */
    hideFromScreenReader: (element: HTMLElement): void => {
        element.setAttribute('aria-hidden', 'true');
    },

    /**
     * Make element visible only to screen readers
     */
    makeScreenReaderOnly: (element: HTMLElement): void => {
        element.className += ' sr-only';
    },

    /**
     * Set accessible name for an element
     */
    setAccessibleName: (element: HTMLElement, name: string): void => {
        element.setAttribute('aria-label', name);
    },

    /**
     * Set accessible description for an element
     */
    setAccessibleDescription: (element: HTMLElement, description: string): void => {
        const descId = `desc-${Math.random().toString(36).substr(2, 9)}`;
        const descElement = document.createElement('div');
        descElement.id = descId;
        descElement.textContent = description;
        descElement.className = 'sr-only';

        element.parentNode?.insertBefore(descElement, element.nextSibling);
        element.setAttribute('aria-describedby', descId);
    },
};

/**
 * Hook for reduced motion preference
 */
export function useReducedMotion(): boolean {
    const [prefersReducedMotion, setPrefersReducedMotion] = useState<boolean>(false);

    useEffect(() => {
        const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        setPrefersReducedMotion(mediaQuery.matches);

        const handleChange = (event: MediaQueryListEvent): void => {
            setPrefersReducedMotion(event.matches);
        };

        mediaQuery.addEventListener('change', handleChange);
        return () => mediaQuery.removeEventListener('change', handleChange);
    }, []);

    return prefersReducedMotion;
}

/**
 * Hook for high contrast preference
 */
export function useHighContrast(): boolean {
    const [prefersHighContrast, setPrefersHighContrast] = useState<boolean>(false);

    useEffect(() => {
        const mediaQuery = window.matchMedia('(prefers-contrast: high)');
        setPrefersHighContrast(mediaQuery.matches);

        const handleChange = (event: MediaQueryListEvent): void => {
            setPrefersHighContrast(event.matches);
        };

        mediaQuery.addEventListener('change', handleChange);
        return () => mediaQuery.removeEventListener('change', handleChange);
    }, []);

    return prefersHighContrast;
}

/**
 * Hook for accessible skip links
 */
export function useSkipLinks(): void {
    useEffect(() => {
        const skipLink = document.createElement('a');
        skipLink.href = '#main-content';
        skipLink.textContent = 'Skip to main content';
        skipLink.className = 'sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 bg-blue-600 text-white p-2';
        skipLink.style.zIndex = 'var(--z-tooltip)';
        skipLink.style.position = 'absolute';
        skipLink.style.top = '-40px';
        skipLink.style.left = '6px';
        skipLink.style.transition = 'top 0.3s';

        skipLink.addEventListener('focus', () => {
            skipLink.style.top = '6px';
        });

        skipLink.addEventListener('blur', () => {
            skipLink.style.top = '-40px';
        });

        document.body.insertBefore(skipLink, document.body.firstChild);

        return () => {
            skipLink.remove();
        };
    }, []);
}

/**
 * Initialize accessibility features
 */
export function initializeAccessibility(): void {
    console.log('🎯 Inicializando características de accesibilidad...');

    // Add screen reader only class to CSS
    const style = document.createElement('style');
    style.textContent = `
    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    .focus\\:not-sr-only:focus {
      position: static;
      width: auto;
      height: auto;
      padding: inherit;
      margin: inherit;
      overflow: visible;
      clip: auto;
      white-space: normal;
    }

    .skip-link {
      position: absolute;
      top: -40px;
      left: 6px;
      background: #000;
      color: #fff;
      padding: 8px;
      text-decoration: none;
      z-index: 100;
    }
    .skip-link:focus {
      top: 0;
    }
  `;
    document.head.appendChild(style);

    // Set up global ARIA live regions
    AriaLiveManager.getRegion('announcements', 'polite');
    AriaLiveManager.getRegion('alerts', 'assertive');

    // Add focus indicators for better keyboard navigation
    const focusStyle = document.createElement('style');
    focusStyle.textContent = `
    *:focus {
      outline: 2px solid #3b82f6;
      outline-offset: 2px;
    }

    *:focus:not(:focus-visible) {
      outline: none;
    }
  `;
    document.head.appendChild(focusStyle);

    // Configurar skip links
    const skipLinks = [
        { href: '#main-content', text: 'Ir al contenido principal' },
        { href: '#navigation', text: 'Ir a la navegación' },
        { href: '#footer', text: 'Ir al pie de página' }
    ];

    skipLinks.forEach(({ href, text }) => {
        const existingLink = document.querySelector(`a[href="${href}"]`);
        if (!existingLink) {
            const skipLink = document.createElement('a');
            skipLink.href = href;
            skipLink.textContent = text;
            skipLink.className = 'skip-link sr-only focus:not-sr-only';
            document.body.insertBefore(skipLink, document.body.firstChild);
        }
    });

    // Configurar navegación por teclado global
    document.addEventListener('keydown', (event) => {
        // Alt + H: Ir al inicio
        if (event.altKey && event.key === 'h') {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Alt + M: Ir al contenido principal
        if (event.altKey && event.key === 'm') {
            event.preventDefault();
            const mainContent = document.getElementById('main-content');
            if (mainContent) {
                mainContent.focus();
                mainContent.scrollIntoView({ behavior: 'smooth' });
            }
        }
    });

    // Configurar manejo de foco para elementos dinámicos
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    const element = node as Element;
                    // Asegurar que elementos interactivos sean accesibles por teclado
                    if (['BUTTON', 'A', 'INPUT', 'SELECT', 'TEXTAREA'].includes(element.tagName)) {
                        if (!element.hasAttribute('tabindex') && !element.hasAttribute('disabled')) {
                            element.setAttribute('tabindex', '0');
                        }
                    }
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    console.log('✅ Accesibilidad inicializada correctamente');
}

/**
 * Accessibility validation utilities
 */
export const A11yValidator = {
    /**
     * Check if element has accessible name
     */
    hasAccessibleName: (element: HTMLElement): boolean => {
        return !!(
            element.getAttribute('aria-label') ||
            element.getAttribute('aria-labelledby') ||
            (element.tagName === 'INPUT' && element.getAttribute('placeholder')) ||
            element.textContent?.trim()
        );
    },

    /**
     * Check if interactive element is keyboard accessible
     */
    isKeyboardAccessible: (element: HTMLElement): boolean => {
        const tabIndex = element.getAttribute('tabindex');
        return !!(
            element.tagName === 'A' ||
            element.tagName === 'BUTTON' ||
            element.tagName === 'INPUT' ||
            element.tagName === 'SELECT' ||
            element.tagName === 'TEXTAREA' ||
            (tabIndex !== null && tabIndex !== '-1')
        );
    },

    /**
     * Validate form accessibility
     */
    validateForm: (form: HTMLFormElement): string[] => {
        const issues: string[] = [];
        const inputs = form.querySelectorAll('input, select, textarea');

        inputs.forEach((input) => {
            const inputElement = input as HTMLElement;

            if (!A11yValidator.hasAccessibleName(inputElement)) {
                issues.push(`Input ${input.tagName.toLowerCase()} lacks accessible name`);
            }

            if (input.hasAttribute('required') && !input.getAttribute('aria-required')) {
                issues.push(`Required input lacks aria-required attribute`);
            }
        });

        return issues;
    },
};

export default {
    FocusManager,
    AriaLiveManager,
    KeyboardNavigation,
    ColorContrast,
    ScreenReader,
    A11yValidator,
    useFocusTrap,
    useFocusRestore,
    useAnnouncer,
    useKeyboardNavigation,
    useReducedMotion,
    useHighContrast,
    useSkipLinks,
};


