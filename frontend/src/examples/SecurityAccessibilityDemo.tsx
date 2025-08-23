/**
 * Security and Accessibility Implementation Example
 * 
 * Demonstrates the implementation of section 3 requirements:
 * - XSS Prevention and Injection Protection (3.1)
 * - CSP and HTTP Headers (3.2) 
 * - Accessibility Best Practices (3.3)
 * 
 * @package Examples
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React, { useState, useEffect } from 'react';
import { SafeExternalLink } from '../components/ui/SafeExternalLink';
import { AccessibleModal } from '../components/ui/AccessibleModal';
import { AccessibleInput } from '../components/ui/AccessibleInput';
import { SecurityValidator, CommonValidationRules } from '../utils/securityValidator';
import { initCSPReporting, generateCSPHeader, getCSPConfig } from '../security/csp';
import { useAnnouncer, initializeAccessibility } from '../utils/accessibility';

/**
 * Example form data interface
 */
interface ContactFormData {
    name: string;
    email: string;
    message: string;
    website?: string;
}

/**
 * Security and Accessibility Demo Component
 */
const SecurityAccessibilityDemo: React.FC = () => {
    const [formData, setFormData] = useState<ContactFormData>({
        name: '',
        email: '',
        message: '',
        website: ''
    });

    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const announce = useAnnouncer();

    // Initialize security and accessibility features
    useEffect(() => {
        // Initialize CSP violation reporting
        initCSPReporting();

        // Initialize accessibility features
        initializeAccessibility();

        // Log CSP configuration for demo
        const cspConfig = getCSPConfig();
        const cspHeader = generateCSPHeader(cspConfig);
        console.log('CSP Configuration:', cspHeader);

        announce('Security and accessibility demo loaded', 'polite');
    }, [announce]);

    /**
     * Handle form input changes with security validation
     */
    const handleInputChange = (field: keyof ContactFormData, value: string) => {
        // Update form data
        setFormData(prev => ({ ...prev, [field]: value }));

        // Clear previous errors for this field
        setErrors(prev => ({ ...prev, [field]: [] }));

        // Real-time validation for better UX
        const fieldRules = getValidationRules()[field];
        if (fieldRules) {
            const result = SecurityValidator.validateField(value, fieldRules, field);
            if (!result.isValid) {
                setErrors(prev => ({ ...prev, [field]: result.errors }));
            }
        }
    };

    /**
     * Get validation rules for the form
     */
    const getValidationRules = () => ({
        name: {
            ...CommonValidationRules.name,
            required: true,
        },
        email: {
            ...CommonValidationRules.email,
            required: true,
        },
        message: {
            ...CommonValidationRules.text,
            required: true,
            minLength: 10,
            maxLength: 500,
        },
        website: {
            ...CommonValidationRules.url,
            required: false,
        },
    });

    /**
     * Handle form submission with security validation
     */
    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        setIsSubmitting(true);

        try {
            // Validate all fields
            const formDataRecord: Record<string, string> = {
                name: formData.name,
                email: formData.email,
                message: formData.message,
                website: formData.website || ''
            };
            const validationResults = SecurityValidator.validateForm(formDataRecord, getValidationRules());

            if (!SecurityValidator.isFormValid(validationResults)) {
                const formErrors = SecurityValidator.getFormErrors(validationResults);
                setErrors(formErrors);
                announce('Please correct the errors in the form', 'assertive');
                return;
            }

            // Get sanitized data
            const sanitizedData = SecurityValidator.getSanitizedData(validationResults);

            // Check rate limiting (demo - in production this would be server-side)
            // In real app, use IP or user ID for rate limiting

            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1000));

            console.log('Secure form submission:', sanitizedData);
            announce('Form submitted successfully!', 'polite');

            // Reset form
            setFormData({ name: '', email: '', message: '', website: '' });
            setErrors({});

        } catch (error) {
            console.error('Submission error:', error);
            announce('An error occurred. Please try again.', 'assertive');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="max-w-4xl mx-auto p-6">
            {/* Skip Navigation Link */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 bg-blue-600 text-white p-2"
                style={{ zIndex: 'var(--z-tooltip)' }}
            >
                Skip to main content
            </a>

            {/* Header with semantic HTML */}
            <header role="banner">
                <h1 className="text-3xl font-bold text-gray-900 mb-2">
                    Security & Accessibility Demo
                </h1>
                <p className="text-gray-600 mb-8">
                    Demonstrates implementation of WCAG AA compliance and security best practices.
                </p>
            </header>

            {/* Main content */}
            <main id="main-content" role="main" aria-label="Main content">

                {/* Section 3.1: XSS Prevention Demo */}
                <section aria-labelledby="xss-prevention" className="mb-8">
                    <h2 id="xss-prevention" className="text-2xl font-semibold mb-4">
                        3.1 XSS Prevention & Input Sanitization
                    </h2>

                    <div className="bg-blue-50 p-4 rounded-lg mb-4">
                        <h3 className="font-medium mb-2">Security Features:</h3>
                        <ul className="list-disc pl-5 space-y-1 text-sm">
                            <li>Input sanitization using DOMPurify</li>
                            <li>SQL injection detection</li>
                            <li>XSS attack pattern recognition</li>
                            <li>Rate limiting for form submissions</li>
                            <li>Server-side validation confirmation</li>
                        </ul>
                    </div>

                    {/* Secure Contact Form */}
                    <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                        <AccessibleInput
                            label="Full Name"
                            type="text"
                            value={formData.name}
                            onChange={(e) => handleInputChange('name', e.target.value)}
                            error={errors.name?.[0]}
                            required
                            helpText="Enter your first and last name"
                            placeholder="John Smith"
                        />

                        <AccessibleInput
                            label="Email Address"
                            type="email"
                            value={formData.email}
                            onChange={(e) => handleInputChange('email', e.target.value)}
                            error={errors.email?.[0]}
                            required
                            helpText="We'll never share your email with anyone"
                            placeholder="john@example.com"
                        />

                        <AccessibleInput
                            label="Website (Optional)"
                            type="url"
                            value={formData.website}
                            onChange={(e) => handleInputChange('website', e.target.value)}
                            error={errors.website?.[0]}
                            helpText="Your personal or company website"
                            placeholder="https://example.com"
                        />

                        <div className="space-y-1">
                            <label
                                htmlFor="message"
                                className="block text-sm font-medium text-gray-700"
                            >
                                Message *
                            </label>
                            <textarea
                                id="message"
                                value={formData.message}
                                onChange={(e) => handleInputChange('message', e.target.value)}
                                rows={4}
                                required
                                aria-required="true"
                                aria-invalid={errors.message ? 'true' : 'false'}
                                aria-describedby={errors.message ? 'message-error' : 'message-help'}
                                className={`
                                    block w-full px-3 py-2 border rounded-md shadow-sm
                                    placeholder-gray-400 resize-vertical
                                    focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                    ${errors.message
                                        ? 'border-red-300 text-red-900'
                                        : 'border-gray-300 text-gray-900'
                                    }
                                `}
                                placeholder="Tell us about your security and accessibility needs..."
                            />
                            {!errors.message && (
                                <p id="message-help" className="text-sm text-gray-600">
                                    Minimum 10 characters, maximum 500 characters
                                </p>
                            )}
                            {errors.message && (
                                <p id="message-error" role="alert" className="text-sm text-red-600">
                                    {errors.message[0]}
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={isSubmitting}
                            aria-describedby="submit-help"
                            className={`
                                w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white
                                focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500
                                ${isSubmitting
                                    ? 'bg-gray-400 cursor-not-allowed'
                                    : 'bg-blue-600 hover:bg-blue-700'
                                }
                            `}
                        >
                            {isSubmitting ? 'Submitting...' : 'Submit Secure Form'}
                        </button>
                        <p id="submit-help" className="text-xs text-gray-500">
                            All data is validated and sanitized for security
                        </p>
                    </form>
                </section>

                {/* Section 3.2: Safe External Links Demo */}
                <section aria-labelledby="csp-headers" className="mb-8">
                    <h2 id="csp-headers" className="text-2xl font-semibold mb-4">
                        3.2 CSP & Safe External Links
                    </h2>

                    <div className="bg-green-50 p-4 rounded-lg mb-4">
                        <h3 className="font-medium mb-2">Security Headers Active:</h3>
                        <ul className="list-disc pl-5 space-y-1 text-sm">
                            <li>Content Security Policy (CSP)</li>
                            <li>X-Frame-Options: DENY</li>
                            <li>X-Content-Type-Options: nosniff</li>
                            <li>Strict-Transport-Security</li>
                            <li>Referrer-Policy: strict-origin-when-cross-origin</li>
                        </ul>
                    </div>

                    <div className="space-y-3">
                        <h3 className="font-medium">Safe External Links Examples:</h3>

                        <SafeExternalLink
                            href="https://www.w3.org/WAI/WCAG21/quickref/"
                            className="text-blue-600 underline hover:text-blue-800"
                        >
                            WCAG 2.1 Quick Reference (Safe)
                        </SafeExternalLink>

                        <SafeExternalLink
                            href="https://github.com/OWASP/CheatSheetSeries"
                            className="text-blue-600 underline hover:text-blue-800 block"
                        >
                            OWASP Security Cheat Sheets (Safe)
                        </SafeExternalLink>

                        {/* Demo of blocked unsafe link */}
                        <SafeExternalLink
                            href="javascript:alert('XSS')"
                            className="text-red-600 underline hover:text-red-800 block"
                        >
                            Unsafe JavaScript Link (Blocked)
                        </SafeExternalLink>
                    </div>
                </section>

                {/* Section 3.3: Accessibility Demo */}
                <section aria-labelledby="accessibility" className="mb-8">
                    <h2 id="accessibility" className="text-2xl font-semibold mb-4">
                        3.3 Accessibility Best Practices
                    </h2>

                    <div className="space-y-4">
                        <button
                            onClick={() => setIsModalOpen(true)}
                            className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                            aria-describedby="modal-description"
                        >
                            Open Accessible Modal
                        </button>
                        <p id="modal-description" className="text-sm text-gray-600">
                            Demonstrates focus trapping, keyboard navigation, and ARIA attributes
                        </p>

                        <div className="bg-yellow-50 p-4 rounded-lg">
                            <h3 className="font-medium mb-2">Accessibility Features Implemented:</h3>
                            <ul className="list-disc pl-5 space-y-1 text-sm">
                                <li>Semantic HTML5 elements (main, nav, header, footer)</li>
                                <li>ARIA labels and descriptions</li>
                                <li>Focus management and keyboard navigation</li>
                                <li>Screen reader announcements</li>
                                <li>Color contrast compliance (WCAG AA)</li>
                                <li>Skip navigation links</li>
                                <li>Form validation with error announcements</li>
                                <li>Focus trapping in modals</li>
                            </ul>
                        </div>
                    </div>
                </section>
            </main>

            {/* Accessible Modal Demo */}
            <AccessibleModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title="Accessibility Demo Modal"
                aria-describedby="modal-content"
            >
                <div id="modal-content">
                    <p className="mb-4">
                        This modal demonstrates several accessibility features:
                    </p>
                    <ul className="list-disc pl-5 space-y-2 mb-4">
                        <li>Focus is trapped within the modal</li>
                        <li>Escape key closes the modal</li>
                        <li>Backdrop click closes the modal</li>
                        <li>Screen readers announce modal opening</li>
                        <li>Focus returns to trigger button when closed</li>
                        <li>Proper ARIA roles and properties</li>
                    </ul>
                    <div className="flex justify-end space-x-3">
                        <button
                            onClick={() => setIsModalOpen(false)}
                            className="px-4 py-2 text-gray-600 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() => {
                                announce('Action confirmed!', 'polite');
                                setIsModalOpen(false);
                            }}
                            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            Confirm
                        </button>
                    </div>
                </div>
            </AccessibleModal>

            {/* Footer with semantic HTML */}
            <footer role="contentinfo" className="mt-12 pt-8 border-t border-gray-200">
                <p className="text-center text-gray-600 text-sm">
                    Security and Accessibility Implementation Demo -
                    <SafeExternalLink
                        href="https://github.com/bubble-talents"
                        className="text-blue-600 hover:text-blue-800 ml-1"
                    >
                        Bubble of Talents
                    </SafeExternalLink>
                </p>
            </footer>
        </div>
    );
};

export default SecurityAccessibilityDemo;
