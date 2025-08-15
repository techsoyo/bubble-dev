/**
 * Accessible Form Input Component
 * 
 * Implements WCAG AA compliant form inputs with proper labeling,
 * validation, and error handling.
 * 
 * @package Components/UI
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React, { forwardRef, useId } from 'react';
import { sanitizeText } from '../../security/xss';

interface AccessibleInputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'aria-describedby' | 'aria-invalid'> {
    label: string;
    error?: string | undefined;
    helpText?: string;
    required?: boolean;
    hideLabel?: boolean;
    sanitize?: boolean;
    containerClassName?: string;
    labelClassName?: string;
    inputClassName?: string;
    errorClassName?: string;
    helpClassName?: string;
}

/**
 * AccessibleInput Component
 * 
 * Features:
 * - Proper label association
 * - Error state management
 * - Help text support
 * - Screen reader optimized
 * - XSS protection via sanitization
 * - ARIA attributes for accessibility
 * - Focus management
 */
export const AccessibleInput = forwardRef<HTMLInputElement, AccessibleInputProps>(({
    label,
    error,
    helpText,
    required = false,
    hideLabel = false,
    sanitize = true,
    containerClassName = '',
    labelClassName = '',
    inputClassName = '',
    errorClassName = '',
    helpClassName = '',
    id,
    value,
    onChange,
    onBlur,
    ...props
}, ref) => {
    const inputId = useId();
    const finalId = id || inputId;
    const errorId = `${finalId}-error`;
    const helpId = `${finalId}-help`;

    // Sanitize input value if enabled
    const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        if (sanitize && event.target.value) {
            const sanitizedValue = sanitizeText(event.target.value);
            event.target.value = sanitizedValue;
        }
        if (onChange) {
            onChange(event);
        }
    };

    // Build aria-describedby attribute
    const ariaDescribedBy = [
        error ? errorId : null,
        helpText ? helpId : null,
    ].filter(Boolean).join(' ') || undefined;

    return (
        <div className={`space-y-1 ${containerClassName}`}>
            {/* Label */}
            <label
                htmlFor={finalId}
                className={`
                    block text-sm font-medium text-gray-700
                    ${hideLabel ? 'sr-only' : ''}
                    ${labelClassName}
                `}
            >
                {label}
                {required && (
                    <span
                        className="text-red-500 ml-1"
                        aria-label="required"
                    >
                        *
                    </span>
                )}
            </label>

            {/* Input */}
            <input
                ref={ref}
                id={finalId}
                value={value}
                onChange={handleChange}
                onBlur={onBlur}
                required={required}
                aria-required={required}
                aria-invalid={error ? 'true' : 'false'}
                aria-describedby={ariaDescribedBy}
                className={`
                    block w-full px-3 py-2 border rounded-md shadow-sm
                    placeholder-gray-400 
                    focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                    disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed
                    ${error
                        ? 'border-red-300 text-red-900 focus:ring-red-500 focus:border-red-500'
                        : 'border-gray-300 text-gray-900'
                    }
                    ${inputClassName}
                `}
                {...props}
            />

            {/* Help text */}
            {helpText && !error && (
                <p
                    id={helpId}
                    className={`text-sm text-gray-600 ${helpClassName}`}
                >
                    {helpText}
                </p>
            )}

            {/* Error message */}
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className={`text-sm text-red-600 ${errorClassName}`}
                >
                    {error}
                </p>
            )}
        </div>
    );
});

AccessibleInput.displayName = 'AccessibleInput';

export default AccessibleInput;
