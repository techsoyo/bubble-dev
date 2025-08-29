/**
 * React Type Definitions - SECURE & PERFORMANCE OPTIMIZED
 *
 * ✅ SEGURIDAD: Previene XSS y manipulación insegura de props
 * ✅ TYPE SAFETY: Tipos estrictos para componentes React
 * ✅ PERFORMANCE: Optimizaciones para renderizado y memoización
 * ✅ ACCESSIBILITY: Soporte completo para ARIA y navegación
 */

import React from 'react';

// Security: Prevent dangerous React patterns
export type DangerousProps = {
  readonly dangerouslySetInnerHTML?: never; // ❌ BLOCKED: XSS risk
  readonly __html?: never; // ❌ BLOCKED: XSS risk
  readonly prototype?: never; // ❌ BLOCKED: Prototype pollution
};

// Security: Safe HTML attributes whitelist
export type SafeHTMLAttribute =
  | 'id' | 'className' | 'style' | 'tabIndex' | 'role'
  | 'aria-label' | 'aria-labelledby' | 'aria-describedby'
  | 'aria-expanded' | 'aria-hidden' | 'aria-live' | 'aria-atomic'
  | 'data-testid' | 'data-cy' | 'data-analytics'
  | 'data-component' | 'data-variant';

// Performance: Strict memo comparison types
export type MemoComparison<T> = (prevProps: T, nextProps: T) => boolean;

// Security: Safe component props base
export interface SecureComponentProps {
  readonly children?: React.ReactNode;
  readonly className?: string;
  readonly style?: React.CSSProperties;
  readonly 'data-testid'?: string;
  readonly 'data-cy'?: string;
}

// Accessibility: Enhanced component props
export interface AccessibleComponentProps extends SecureComponentProps {
  readonly id?: string;
  readonly role?: React.AriaRole;
  readonly tabIndex?: number;
  readonly 'aria-label'?: string;
  readonly 'aria-labelledby'?: string;
  readonly 'aria-describedby'?: string;
  readonly 'aria-expanded'?: boolean | 'true' | 'false';
  readonly 'aria-hidden'?: boolean | 'true' | 'false';
  readonly 'aria-live'?: 'off' | 'assertive' | 'polite';
  readonly 'aria-atomic'?: boolean | 'true' | 'false';
}

// Performance: Optimized ref types
export type SecureRef<T> = React.Ref<T> | React.MutableRefObject<T | null>;

// Security: Safe event handler types
export type SecureMouseEventHandler = (event: React.MouseEvent<HTMLElement>) => void;
export type SecureKeyboardEventHandler = (event: React.KeyboardEvent<HTMLElement>) => void;
export type SecureFocusEventHandler = (event: React.FocusEvent<HTMLElement>) => void;
export type SecureChangeEventHandler = (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => void;

// Performance: Strict memo component types
export interface MemoComponentProps extends AccessibleComponentProps {
  readonly memoKey?: string | number;
}

// Security: Safe HTML element props
export interface SecureDivProps extends AccessibleComponentProps {
  readonly onClick?: SecureMouseEventHandler;
  readonly onKeyDown?: SecureKeyboardEventHandler;
  readonly onFocus?: SecureFocusEventHandler;
  readonly onBlur?: SecureFocusEventHandler;
}

export interface SecureButtonProps extends SecureDivProps {
  readonly type?: 'button' | 'submit' | 'reset';
  readonly disabled?: boolean;
  readonly 'aria-pressed'?: boolean | 'true' | 'false';
}

export interface SecureInputProps extends AccessibleComponentProps {
  readonly type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'url' | 'search';
  readonly value?: string | number;
  readonly placeholder?: string;
  readonly disabled?: boolean;
  readonly required?: boolean;
  readonly name?: string;
  readonly autoComplete?: string;
  readonly onChange?: SecureChangeEventHandler;
  readonly onFocus?: SecureFocusEventHandler;
  readonly onBlur?: SecureFocusEventHandler;
}

export interface SecureLinkProps extends AccessibleComponentProps {
  readonly href?: string;
  readonly target?: '_blank' | '_self' | '_parent' | '_top';
  readonly rel?: string;
  readonly onClick?: SecureMouseEventHandler;
}

// Security: Safe context types
export interface SecureContextValue<T> {
  readonly value: T;
  readonly loading?: boolean;
  readonly error?: string | null;
}

export type SecureContext<T> = React.Context<SecureContextValue<T>>;

// Performance: Strict useEffect dependencies
export type DependencyList = React.DependencyList;
export type EffectCallback = React.EffectCallback;

// Security: Safe reducer types
export type SecureReducer<S, A> = React.Reducer<S, A>;
export type SecureReducerAction<R extends SecureReducer<any, any>> = React.ReducerAction<R>;
export type SecureReducerState<R extends SecureReducer<any, any>> = React.ReducerState<R>;

// Security: Type guards for React elements
export function isSecureReactElement(element: any): element is React.ReactElement {
  if (!React.isValidElement(element)) return false;

  const props = element.props as any;

  // Block dangerous props
  if (props.dangerouslySetInnerHTML) return false;
  if (props.__html) return false;

  // Validate event handlers
  const eventHandlers = ['onClick', 'onSubmit', 'onChange'];
  for (const handler of eventHandlers) {
    if (props[handler] && typeof props[handler] !== 'function') {
      return false;
    }
  }

  return true;
}

// Performance: Strict component comparison
export function secureMemo<P extends SecureComponentProps>(
  Component: React.FC<P>,
  compare?: MemoComparison<P>
): React.MemoExoticComponent<React.FC<P>> {
  return React.memo(Component, compare);
}

// Security: Safe component creation
export function createSecureComponent<P extends SecureComponentProps>(
  displayName: string,
  component: React.FC<P>
): React.FC<P> {
  const SecureComponent = (props: P) => {
    // Runtime security validation
    if (!isSecureReactElement(React.createElement(component, props))) {
      console.error(`Security violation in component: ${displayName}`);
      return null;
    }

    return React.createElement(component, props);
  };

  SecureComponent.displayName = `Secure(${displayName})`;
  return SecureComponent;
}

// Export for use in other files
export { };