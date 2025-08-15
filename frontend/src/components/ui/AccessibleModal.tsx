/**
 * Accessible Modal Component
 * 
 * Implements WCAG AA compliant modal with focus management,
 * keyboard navigation, and proper ARIA attributes.
 * 
 * @package Components/UI
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React, { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useFocusTrap, useAnnouncer, useKeyboardNavigation } from '../../utils/accessibility';

interface AccessibleModalProps {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    children: React.ReactNode;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    closeOnOverlayClick?: boolean;
    closeOnEscape?: boolean;
    className?: string;
    role?: 'dialog' | 'alertdialog';
    'aria-describedby'?: string;
}

/**
 * AccessibleModal Component
 * 
 * Features:
 * - Focus trapping
 * - Keyboard navigation (Escape to close)
 * - Proper ARIA attributes
 * - Screen reader announcements
 * - Backdrop click handling
 * - Semantic HTML structure
 */
export const AccessibleModal: React.FC<AccessibleModalProps> = ({
    isOpen,
    onClose,
    title,
    children,
    size = 'md',
    closeOnOverlayClick = true,
    closeOnEscape = true,
    className = '',
    role = 'dialog',
    'aria-describedby': ariaDescribedBy,
}) => {
    const modalRef = useFocusTrap(isOpen) as React.RefObject<HTMLDivElement>;
    const announce = useAnnouncer();
    const previousActiveElement = useRef<HTMLElement | null>(null);

    // Store focus before opening modal
    useEffect(() => {
        if (isOpen) {
            previousActiveElement.current = document.activeElement as HTMLElement;
            announce(`${role === 'alertdialog' ? 'Alert dialog' : 'Dialog'} opened: ${title}`, 'polite');
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        } else {
            // Restore body scroll
            document.body.style.overflow = '';
            // Restore focus
            if (previousActiveElement.current) {
                previousActiveElement.current.focus();
            }
        }

        return () => {
            document.body.style.overflow = '';
        };
    }, [isOpen, announce, title, role]);

    // Keyboard navigation
    if (closeOnEscape) {
        useKeyboardNavigation({
            onEscape: onClose,
        });
    } else {
        useKeyboardNavigation({});
    }

    // Handle overlay click
    const handleOverlayClick = (event: React.MouseEvent) => {
        if (closeOnOverlayClick && event.target === event.currentTarget) {
            onClose();
        }
    };

    // Size classes
    const sizeClasses = {
        sm: 'max-w-sm',
        md: 'max-w-md',
        lg: 'max-w-lg',
        xl: 'max-w-xl',
    };

    if (!isOpen) return null;

    const modalContent = (
        <div
            className="fixed inset-0 z-50 overflow-y-auto"
            role="presentation"
        >
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
                onClick={handleOverlayClick}
                aria-hidden="true"
            />

            {/* Modal container */}
            <div className="flex min-h-full items-center justify-center p-4">
                <div
                    ref={modalRef}
                    role={role}
                    aria-modal="true"
                    aria-labelledby="modal-title"
                    aria-describedby={ariaDescribedBy}
                    className={`
                        relative w-full ${sizeClasses[size]} 
                        bg-white rounded-lg shadow-xl 
                        transform transition-all
                        ${className}
                    `}
                >
                    {/* Header */}
                    <div className="flex items-center justify-between p-6 border-b border-gray-200">
                        <h2
                            id="modal-title"
                            className="text-xl font-semibold text-gray-900"
                        >
                            {title}
                        </h2>
                        <button
                            type="button"
                            onClick={onClose}
                            className="
                                rounded-md text-gray-400 hover:text-gray-600 
                                focus:outline-none focus:ring-2 focus:ring-blue-500
                                p-2 -m-2
                            "
                            aria-label="Close dialog"
                        >
                            <svg
                                className="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    {/* Content */}
                    <div className="p-6">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );

    // Render in portal to avoid z-index issues
    return createPortal(modalContent, document.body);
};

export default AccessibleModal;
