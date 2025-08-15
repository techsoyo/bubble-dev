/**
 * Production-Ready Form Components
 * 
 * Provides accessible, validated form components with error handling,
 * keyboard navigation, and ARIA support for production use.
 * 
 * @package FormComponents
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React, { forwardRef, useId, useEffect } from 'react';
import { useAnnouncer } from '../utils/accessibility';
import { sanitizeText } from '../security/xss';

/**
 * Base form field props
 */
interface BaseFieldProps {
    label: string;
    error?: string | undefined;
    required?: boolean;
    disabled?: boolean;
    className?: string;
    helpText?: string;
    'data-testid'?: string;
}

/**
 * Form Input Component
 */
interface FormInputProps extends BaseFieldProps {
    type?: 'text' | 'email' | 'password' | 'tel' | 'url' | 'search';
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    placeholder?: string;
    maxLength?: number;
    minLength?: number;
    pattern?: string;
    autoComplete?: string;
    autoFocus?: boolean;
}

export const FormInput = forwardRef<HTMLInputElement, FormInputProps>(
    (
        {
            label,
            error,
            required = false,
            disabled = false,
            className = '',
            helpText,
            type = 'text',
            value,
            onChange,
            onBlur,
            placeholder,
            maxLength,
            minLength,
            pattern,
            autoComplete,
            autoFocus = false,
            'data-testid': testId,
        },
        ref
    ) => {
        const inputId = useId();
        const errorId = useId();
        const helpId = useId();
        const announce = useAnnouncer();

        // Announce errors to screen readers
        useEffect(() => {
            if (error) {
                announce(error, 'assertive');
            }
        }, [error, announce]);

        const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
            const sanitizedValue = sanitizeText(event.target.value);
            onChange(sanitizedValue);
        };

        const inputClassName = `
      w-full px-3 py-2 border rounded-md shadow-sm
      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
      ${error ? 'border-red-500' : 'border-gray-300'}
      ${disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'}
      ${className}
    `.trim();

        return (
            <div className="space-y-1">
                <label
                    htmlFor={inputId}
                    className={`block text-sm font-medium ${error ? 'text-red-700' : 'text-gray-700'
                        }`}
                >
                    {label}
                    {required && (
                        <span className="text-red-500 ml-1" aria-label="required">
                            *
                        </span>
                    )}
                </label>

                <input
                    ref={ref}
                    id={inputId}
                    type={type}
                    value={value}
                    onChange={handleChange}
                    onBlur={onBlur}
                    placeholder={placeholder}
                    maxLength={maxLength}
                    minLength={minLength}
                    pattern={pattern}
                    autoComplete={autoComplete}
                    autoFocus={autoFocus}
                    disabled={disabled}
                    required={required}
                    className={inputClassName}
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={`${helpText ? helpId : ''} ${error ? errorId : ''}`.trim()}
                    data-testid={testId}
                />

                {helpText && (
                    <p id={helpId} className="text-sm text-gray-600">
                        {helpText}
                    </p>
                )}

                {error && (
                    <p
                        id={errorId}
                        className="text-sm text-red-600"
                        role="alert"
                        aria-live="polite"
                    >
                        {error}
                    </p>
                )}
            </div>
        );
    }
);

FormInput.displayName = 'FormInput';

/**
 * Form Select Component
 */
interface FormSelectProps extends BaseFieldProps {
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    options: Array<{ value: string; label: string; disabled?: boolean }>;
    placeholder?: string;
}

export const FormSelect = forwardRef<HTMLSelectElement, FormSelectProps>(
    (
        {
            label,
            error,
            required = false,
            disabled = false,
            className = '',
            helpText,
            value,
            onChange,
            onBlur,
            options,
            placeholder,
            'data-testid': testId,
        },
        ref
    ) => {
        const selectId = useId();
        const errorId = useId();
        const helpId = useId();
        const announce = useAnnouncer();

        useEffect(() => {
            if (error) {
                announce(error, 'assertive');
            }
        }, [error, announce]);

        const handleChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
            onChange(event.target.value);
        };

        const selectClassName = `
      w-full px-3 py-2 border rounded-md shadow-sm
      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
      ${error ? 'border-red-500' : 'border-gray-300'}
      ${disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'}
      ${className}
    `.trim();

        return (
            <div className="space-y-1">
                <label
                    htmlFor={selectId}
                    className={`block text-sm font-medium ${error ? 'text-red-700' : 'text-gray-700'
                        }`}
                >
                    {label}
                    {required && (
                        <span className="text-red-500 ml-1" aria-label="required">
                            *
                        </span>
                    )}
                </label>

                <select
                    ref={ref}
                    id={selectId}
                    value={value}
                    onChange={handleChange}
                    onBlur={onBlur}
                    disabled={disabled}
                    required={required}
                    className={selectClassName}
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={`${helpText ? helpId : ''} ${error ? errorId : ''}`.trim()}
                    data-testid={testId}
                >
                    {placeholder && (
                        <option value="" disabled>
                            {placeholder}
                        </option>
                    )}
                    {options.map((option) => (
                        <option
                            key={option.value}
                            value={option.value}
                            disabled={option.disabled}
                        >
                            {option.label}
                        </option>
                    ))}
                </select>

                {helpText && (
                    <p id={helpId} className="text-sm text-gray-600">
                        {helpText}
                    </p>
                )}

                {error && (
                    <p
                        id={errorId}
                        className="text-sm text-red-600"
                        role="alert"
                        aria-live="polite"
                    >
                        {error}
                    </p>
                )}
            </div>
        );
    }
);

FormSelect.displayName = 'FormSelect';

/**
 * Form Textarea Component
 */
interface FormTextareaProps extends BaseFieldProps {
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    placeholder?: string;
    rows?: number;
    maxLength?: number;
    minLength?: number;
    resize?: 'none' | 'vertical' | 'horizontal' | 'both';
}

export const FormTextarea = forwardRef<HTMLTextAreaElement, FormTextareaProps>(
    (
        {
            label,
            error,
            required = false,
            disabled = false,
            className = '',
            helpText,
            value,
            onChange,
            onBlur,
            placeholder,
            rows = 4,
            maxLength,
            minLength,
            resize = 'vertical',
            'data-testid': testId,
        },
        ref
    ) => {
        const textareaId = useId();
        const errorId = useId();
        const helpId = useId();
        const announce = useAnnouncer();

        useEffect(() => {
            if (error) {
                announce(error, 'assertive');
            }
        }, [error, announce]);

        const handleChange = (event: React.ChangeEvent<HTMLTextAreaElement>) => {
            const sanitizedValue = sanitizeText(event.target.value);
            onChange(sanitizedValue);
        };

        const textareaClassName = `
      w-full px-3 py-2 border rounded-md shadow-sm
      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
      ${error ? 'border-red-500' : 'border-gray-300'}
      ${disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'}
      resize-${resize}
      ${className}
    `.trim();

        return (
            <div className="space-y-1">
                <label
                    htmlFor={textareaId}
                    className={`block text-sm font-medium ${error ? 'text-red-700' : 'text-gray-700'
                        }`}
                >
                    {label}
                    {required && (
                        <span className="text-red-500 ml-1" aria-label="required">
                            *
                        </span>
                    )}
                </label>

                <textarea
                    ref={ref}
                    id={textareaId}
                    value={value}
                    onChange={handleChange}
                    onBlur={onBlur}
                    placeholder={placeholder}
                    rows={rows}
                    maxLength={maxLength}
                    minLength={minLength}
                    disabled={disabled}
                    required={required}
                    className={textareaClassName}
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={`${helpText ? helpId : ''} ${error ? errorId : ''}`.trim()}
                    data-testid={testId}
                />

                {helpText && (
                    <p id={helpId} className="text-sm text-gray-600">
                        {helpText}
                    </p>
                )}

                {error && (
                    <p
                        id={errorId}
                        className="text-sm text-red-600"
                        role="alert"
                        aria-live="polite"
                    >
                        {error}
                    </p>
                )}
            </div>
        );
    }
);

FormTextarea.displayName = 'FormTextarea';

/**
 * Form Checkbox Component
 */
interface FormCheckboxProps extends Omit<BaseFieldProps, 'label'> {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: React.ReactNode;
    value?: string;
}

export const FormCheckbox = forwardRef<HTMLInputElement, FormCheckboxProps>(
    (
        {
            error,
            required = false,
            disabled = false,
            className = '',
            helpText,
            checked,
            onChange,
            label,
            value,
            'data-testid': testId,
        },
        ref
    ) => {
        const checkboxId = useId();
        const errorId = useId();
        const helpId = useId();
        const announce = useAnnouncer();

        useEffect(() => {
            if (error) {
                announce(error, 'assertive');
            }
        }, [error, announce]);

        const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
            onChange(event.target.checked);
        };

        return (
            <div className="space-y-1">
                <div className="flex items-start">
                    <input
                        ref={ref}
                        id={checkboxId}
                        type="checkbox"
                        checked={checked}
                        onChange={handleChange}
                        value={value}
                        disabled={disabled}
                        required={required}
                        className={`
              mt-0.5 h-4 w-4 text-blue-600 border-gray-300 rounded
              focus:ring-2 focus:ring-blue-500
              ${disabled ? 'cursor-not-allowed' : 'cursor-pointer'}
              ${className}
            `.trim()}
                        aria-invalid={error ? 'true' : 'false'}
                        aria-describedby={`${helpText ? helpId : ''} ${error ? errorId : ''}`.trim()}
                        data-testid={testId}
                    />
                    <label
                        htmlFor={checkboxId}
                        className={`ml-2 text-sm ${error ? 'text-red-700' : 'text-gray-700'
                            } ${disabled ? 'cursor-not-allowed' : 'cursor-pointer'}`}
                    >
                        {label}
                        {required && (
                            <span className="text-red-500 ml-1" aria-label="required">
                                *
                            </span>
                        )}
                    </label>
                </div>

                {helpText && (
                    <p id={helpId} className="text-sm text-gray-600 ml-6">
                        {helpText}
                    </p>
                )}

                {error && (
                    <p
                        id={errorId}
                        className="text-sm text-red-600 ml-6"
                        role="alert"
                        aria-live="polite"
                    >
                        {error}
                    </p>
                )}
            </div>
        );
    }
);

FormCheckbox.displayName = 'FormCheckbox';

/**
 * Form Radio Group Component
 */
interface RadioOption {
    value: string;
    label: string;
    disabled?: boolean;
}

interface FormRadioGroupProps extends BaseFieldProps {
    value: string;
    onChange: (value: string) => void;
    options: RadioOption[];
    name: string;
}

export const FormRadioGroup = forwardRef<HTMLDivElement, FormRadioGroupProps>(
    (
        {
            label,
            error,
            required = false,
            disabled = false,
            className = '',
            helpText,
            value,
            onChange,
            options,
            name,
            'data-testid': testId,
        },
        ref
    ) => {
        const groupId = useId();
        const errorId = useId();
        const helpId = useId();
        const announce = useAnnouncer();

        useEffect(() => {
            if (error) {
                announce(error, 'assertive');
            }
        }, [error, announce]);

        const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
            onChange(event.target.value);
        };

        return (
            <div ref={ref} className={`space-y-2 ${className}`} data-testid={testId}>
                <fieldset>
                    <legend
                        className={`text-sm font-medium ${error ? 'text-red-700' : 'text-gray-700'
                            }`}
                    >
                        {label}
                        {required && (
                            <span className="text-red-500 ml-1" aria-label="required">
                                *
                            </span>
                        )}
                    </legend>

                    <div
                        className="mt-2 space-y-2"
                        role="radiogroup"
                        aria-labelledby={groupId}
                        aria-invalid={error ? 'true' : 'false'}
                        aria-describedby={`${helpText ? helpId : ''} ${error ? errorId : ''}`.trim()}
                    >
                        {options.map((option) => {
                            const radioId = `${groupId}-${option.value}`;
                            return (
                                <div key={option.value} className="flex items-center">
                                    <input
                                        id={radioId}
                                        type="radio"
                                        name={name}
                                        value={option.value}
                                        checked={value === option.value}
                                        onChange={handleChange}
                                        disabled={disabled || option.disabled}
                                        className={`
                      h-4 w-4 text-blue-600 border-gray-300
                      focus:ring-2 focus:ring-blue-500
                      ${disabled || option.disabled ? 'cursor-not-allowed' : 'cursor-pointer'}
                    `.trim()}
                                    />
                                    <label
                                        htmlFor={radioId}
                                        className={`ml-2 text-sm ${error ? 'text-red-700' : 'text-gray-700'
                                            } ${disabled || option.disabled ? 'cursor-not-allowed' : 'cursor-pointer'
                                            }`}
                                    >
                                        {option.label}
                                    </label>
                                </div>
                            );
                        })}
                    </div>
                </fieldset>

                {helpText && (
                    <p id={helpId} className="text-sm text-gray-600">
                        {helpText}
                    </p>
                )}

                {error && (
                    <p
                        id={errorId}
                        className="text-sm text-red-600"
                        role="alert"
                        aria-live="polite"
                    >
                        {error}
                    </p>
                )}
            </div>
        );
    }
);

FormRadioGroup.displayName = 'FormRadioGroup';

/**
 * Form Button Component
 */
interface FormButtonProps {
    children: React.ReactNode;
    type?: 'button' | 'submit' | 'reset';
    variant?: 'primary' | 'secondary' | 'danger';
    size?: 'sm' | 'md' | 'lg';
    disabled?: boolean;
    loading?: boolean;
    onClick?: () => void;
    className?: string;
    'data-testid'?: string;
}

export const FormButton = forwardRef<HTMLButtonElement, FormButtonProps>(
    (
        {
            children,
            type = 'button',
            variant = 'primary',
            size = 'md',
            disabled = false,
            loading = false,
            onClick,
            className = '',
            'data-testid': testId,
        },
        ref
    ) => {
        const baseClasses = `
      inline-flex items-center justify-center font-medium rounded-md
      focus:outline-none focus:ring-2 focus:ring-offset-2
      transition-colors duration-200
      disabled:opacity-50 disabled:cursor-not-allowed
    `;

        const variantClasses = {
            primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
            secondary: 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500',
            danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        };

        const sizeClasses = {
            sm: 'px-3 py-1.5 text-sm',
            md: 'px-4 py-2 text-sm',
            lg: 'px-6 py-3 text-base',
        };

        const buttonClassName = `
      ${baseClasses}
      ${variantClasses[variant]}
      ${sizeClasses[size]}
      ${className}
    `.trim();

        return (
            <button
                ref={ref}
                type={type}
                disabled={disabled || loading}
                onClick={onClick}
                className={buttonClassName}
                data-testid={testId}
                aria-label={loading ? 'Loading...' : undefined}
            >
                {loading && (
                    <svg
                        className="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle
                            className="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            strokeWidth="4"
                        ></circle>
                        <path
                            className="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                )}
                {children}
            </button>
        );
    }
);

FormButton.displayName = 'FormButton';

export default {
    FormInput,
    FormSelect,
    FormTextarea,
    FormCheckbox,
    FormRadioGroup,
    FormButton,
};
