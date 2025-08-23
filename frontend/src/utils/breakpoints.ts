/**
 * Breakpoints consistency system
 * Centralizes all responsive breakpoint values used across the application
 * Matches the CSS custom properties in design-system.css
 */

export const BREAKPOINTS = {
  sm: 640,   // Small devices (mobile landscape)
  md: 768,   // Medium devices (tablet)
  lg: 1024,  // Large devices (desktop)
  xl: 1280,  // Extra large devices (large desktop)
  '2xl': 1536 // 2X large devices (larger desktop)
} as const;

/**
 * Mobile breakpoint for compatibility with existing useIsMobile hook
 */
export const MOBILE_BREAKPOINT = BREAKPOINTS.md;

/**
 * Media query strings for programmatic use
 */
export const MEDIA_QUERIES = {
  sm: `(min-width: ${BREAKPOINTS.sm}px)`,
  md: `(min-width: ${BREAKPOINTS.md}px)`,
  lg: `(min-width: ${BREAKPOINTS.lg}px)`,
  xl: `(min-width: ${BREAKPOINTS.xl}px)`,
  '2xl': `(min-width: ${BREAKPOINTS['2xl']}px)`,

  // Max-width queries
  'max-sm': `(max-width: ${BREAKPOINTS.sm - 1}px)`,
  'max-md': `(max-width: ${BREAKPOINTS.md - 1}px)`,
  'max-lg': `(max-width: ${BREAKPOINTS.lg - 1}px)`,
  'max-xl': `(max-width: ${BREAKPOINTS.xl - 1}px)`,
} as const;

/**
 * Helper function to check if current screen matches a breakpoint
 */
export function matchesBreakpoint(breakpoint: keyof typeof MEDIA_QUERIES): boolean {
  if (typeof window === 'undefined') return false;
  return window.matchMedia(MEDIA_QUERIES[breakpoint]).matches;
}

/**
 * Helper function to get responsive values based on screen size
 */
export function getResponsiveValue<T>(values: {
  default: T;
  sm?: T;
  md?: T;
  lg?: T;
  xl?: T;
  '2xl'?: T;
}): T {
  if (typeof window === 'undefined') return values.default;

  if (values['2xl'] && matchesBreakpoint('2xl')) return values['2xl'];
  if (values.xl && matchesBreakpoint('xl')) return values.xl;
  if (values.lg && matchesBreakpoint('lg')) return values.lg;
  if (values.md && matchesBreakpoint('md')) return values.md;
  if (values.sm && matchesBreakpoint('sm')) return values.sm;

  return values.default;
}

export type BreakpointKey = keyof typeof BREAKPOINTS;
export type MediaQueryKey = keyof typeof MEDIA_QUERIES;
