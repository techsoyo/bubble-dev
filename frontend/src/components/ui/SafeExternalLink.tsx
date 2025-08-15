/**
 * Safe External Link Component
 * 
 * Implements secure external linking with proper ARIA attributes
 * and security headers as specified in section 3.2 of the frontend guide.
 * 
 * @package Components/Security
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React from 'react';
import { getSafeExternalLinkProps } from '../../security/csp';

interface SafeExternalLinkProps {
    href: string;
    children: React.ReactNode;
    className?: string;
    'aria-label'?: string;
    onClick?: (event: React.MouseEvent<HTMLAnchorElement>) => void;
}

/**
 * SafeExternalLink Component
 * 
 * Automatically adds security attributes for external links:
 * - rel="noopener noreferrer" 
 * - target="_blank"
 * - Validates URLs for security
 * - Provides accessible labels
 */
export const SafeExternalLink: React.FC<SafeExternalLinkProps> = ({
    href,
    children,
    className = '',
    'aria-label': ariaLabel,
    onClick,
    ...props
}) => {
    const safeProps = getSafeExternalLinkProps(href);

    // Combine provided aria-label with safe props
    const finalAriaLabel = ariaLabel || safeProps['aria-label'];

    return (
        <a
            {...safeProps}
            className={`${className} focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2`}
            aria-label={finalAriaLabel}
            onClick={onClick}
            {...props}
        >
            {children}
            {/* Screen reader indicator for external links */}
            <span className="sr-only">
                (opens in new tab)
            </span>
        </a>
    );
};

export default SafeExternalLink;
