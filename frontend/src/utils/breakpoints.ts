/**
 * Breakpoints consistency system
 * Centralizes all responsive breakpoint values used across the application
 * Matches the CSS custom properties in design-system.css
 *
 * @example
 * ```typescript
 * // Check if current screen is tablet or larger
 * if (matchesBreakpoint('md')) {
 *   // Tablet and desktop logic
 * }
 *
 * // Get responsive values
 * const spacing = getResponsiveValue({
 *   default: 16,
 *   md: 24,
 *   lg: 32
 * });
 * ```
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

  // Accessibility queries
  'prefers-reduced-motion': '(prefers-reduced-motion: reduce)',
  'prefers-high-contrast': '(prefers-contrast: high)',
  'prefers-color-scheme-dark': '(prefers-color-scheme: dark)'
} as const;

/**
 * Cache for media query matches to improve performance
 */
const mediaQueryCache = new Map<string, MediaQueryList>();

/**
 * Helper function to check if current screen matches a breakpoint
 * Includes memoization for better performance
 */
export function matchesBreakpoint(breakpoint: keyof typeof MEDIA_QUERIES): boolean {
  // SSR safety check
  if (typeof window === 'undefined') {
    return false;
  }

  try {
    const query = MEDIA_QUERIES[breakpoint];

    // Use cached MediaQueryList or create new one
    let mediaQuery = mediaQueryCache.get(query);
    if (!mediaQuery) {
      mediaQuery = window.matchMedia(query);
      mediaQueryCache.set(query, mediaQuery);
    }

    return mediaQuery.matches;
  } catch (error) {
    console.warn(`Error checking breakpoint ${breakpoint}:`, error);
    return false;
  }
}

/**
 * Helper function to get responsive values based on screen size
 * Enhanced with input validation and better type safety
 */
export function getResponsiveValue<T>(values: {
  default: T;
  sm?: T;
  md?: T;
  lg?: T;
  xl?: T;
  '2xl'?: T;
  'max-sm'?: T;
  'max-md'?: T;
  'max-lg'?: T;
  'max-xl'?: T;
  'prefers-reduced-motion'?: T;
  'prefers-high-contrast'?: T;
  'prefers-color-scheme-dark'?: T;
}): T {
  // Input validation
  if (!values || typeof values !== 'object') {
    throw new Error('getResponsiveValue: values must be an object');
  }

  if (values.default === undefined) {
    throw new Error('getResponsiveValue: default value is required');
  }

  // SSR safety check
  if (typeof window === 'undefined') {
    return values.default;
  }

  try {
    // Check breakpoints in order of specificity (largest to smallest)
    const breakpointOrder: (keyof typeof MEDIA_QUERIES)[] = ['2xl', 'xl', 'lg', 'md', 'sm'];

    for (const breakpoint of breakpointOrder) {
      if (values[breakpoint] !== undefined && matchesBreakpoint(breakpoint)) {
        return values[breakpoint]!;
      }
    }

    return values.default;
  } catch (error) {
    console.warn('Error in getResponsiveValue:', error);
    return values.default;
  }
}

export type BreakpointKey = keyof typeof BREAKPOINTS;
export type MediaQueryKey = keyof typeof MEDIA_QUERIES;

/**
 * Accessibility and performance utility functions
 */

/**
 * Check if user prefers reduced motion
 */
export function prefersReducedMotion(): boolean {
  return matchesBreakpoint('prefers-reduced-motion');
}

/**
 * Check if user prefers high contrast
 */
export function prefersHighContrast(): boolean {
  return matchesBreakpoint('prefers-high-contrast');
}

/**
 * Check if user prefers dark color scheme
 */
export function prefersDarkMode(): boolean {
  return matchesBreakpoint('prefers-color-scheme-dark');
}

/**
 * Get current breakpoint name
 */
export function getCurrentBreakpoint(): BreakpointKey | 'xs' {
  if (typeof window === 'undefined') return 'xs';

  const breakpointOrder: BreakpointKey[] = ['2xl', 'xl', 'lg', 'md', 'sm'];

  for (const breakpoint of breakpointOrder) {
    if (matchesBreakpoint(breakpoint)) {
      return breakpoint;
    }
  }

  return 'xs'; // Smaller than smallest breakpoint
}

/**
 * Check if device is mobile (max-md)
 */
export function isMobile(): boolean {
  return matchesBreakpoint('max-md');
}

/**
 * Check if device is tablet (md to lg)
 */
export function isTablet(): boolean {
  return matchesBreakpoint('md') && !matchesBreakpoint('lg');
}

/**
 * Check if device is desktop (lg and above)
 */
export function isDesktop(): boolean {
  return matchesBreakpoint('lg');
}
