/**
 * Production-Ready Apply Page - Refactored to use JobApplicationForm
 * 
 * Enhanced job application page with extracted form component,
 * accessibility, validation, error handling, and security features.
 * 
 * @package Jobs
 * @author Bubble Talents Development Team
 * @version 3.0.0
 * @since 2025-01-05
 */

import React, { useState, useCallback, useEffect, lazy, Suspense } from 'react';
import { ErrorBoundary } from '../../components/ErrorBoundary';
import { JobApplicationData } from '../../components/forms/JobApplicationForm';
import { FormValidator, JobApplicationSchema } from '../../utils/validation';
import { Input } from '../../components/ui/input';
import { Textarea } from '../../components/ui/textarea';
import { Select } from '../../components/ui/select';
import { Button } from '../../components/ui/button';
import { useSkipLinks, useAnnouncer } from '../../utils/accessibility';
import { useDebounce } from '../../utils/performance';

// Lazy load UploadCV component for better performance
const UploadCV = lazy(() => import('../../components/UploadCV'));

/**
 * Application status enum
 */
enum ApplicationStatus {
    IDLE = 'idle',
    SUBMITTING = 'submitting',
    SUCCESS = 'success',
    ERROR = 'error'
}

export default function ApplyPage(): JSX.Element {
    // Skip links for accessibility
    useSkipLinks();

    // Screen reader announcements
    const announce = useAnnouncer();

    // Form state
    const [formData, setFormData] = useState<JobApplicationData>({
        firstName: '',
        lastName: '',
        email: '',
        phone: '',
        location: '',
        coverLetter: '',
        salaryExpectation: '',
        availabilityDate: '',
        workPreference: 'remote',
        experience: '',
        portfolio: '',
        linkedinProfile: ''
    });

    // Form validation and UI state
    type Errors = Partial<Record<keyof JobApplicationData | 'general', string>>;
    const [errors, setErrors] = useState<Errors>({});
    const [status, setStatus] = useState<ApplicationStatus>(ApplicationStatus.IDLE);
    const [submitAttempted, setSubmitAttempted] = useState(false);
    const [successMessage, setSuccessMessage] = useState('');

    // Debounced form data for validation
    const debouncedFormData = useDebounce(formData, 300);

    // Form validator instance
    // Para evitar error de restricción, extendemos JobApplicationData con un índice string temporalmente
    type JobApplicationDataWithIndex = JobApplicationData & { [key: string]: unknown };
    const validator = new FormValidator<JobApplicationDataWithIndex>(JobApplicationSchema as any);

    // Work preference options
    const workPreferenceOptions = [
        { value: 'remote', label: 'Trabajo remoto' },
        { value: 'onsite', label: 'Trabajo presencial' },
        { value: 'hybrid', label: 'Trabajo híbrido' },
    ];

    /**
     * Validate form data and update errors
     */
    const validateForm = useCallback(async (data: JobApplicationDataWithIndex) => {
        try {
            const result = await validator.validateAll(data);
            if (!result.success) {
                const formErrors: Errors = {};
                result.errors.forEach((error) => {
                    formErrors[error.field as keyof JobApplicationData] = error.message;
                });
                setErrors(formErrors);
                return false;
            }
            setErrors({});
            return true;
        } catch (error) {
            console.error('Validation error:', error);
            setErrors({ general: 'Error de validación. Intente nuevamente.' });
            return false;
        }
    }, [validator]);

    // Validar formulario cuando cambian los datos (debounced)
    useEffect(() => {
        if (submitAttempted) {
            validateForm(debouncedFormData as JobApplicationDataWithIndex);
        }
    }, [debouncedFormData, submitAttempted, validateForm]);

    /**
     * Update form field value
     */
    const updateField = useCallback((field: keyof JobApplicationData, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));

        // Clear field error when user starts typing
        if (errors[field]) {
            setErrors(prev => ({ ...prev, [field]: '' }));
        }
    }, [errors]);

    /**
     * Handle field blur events for immediate validation
     */
    const handleFieldBlur = useCallback(async (field: keyof JobApplicationData) => {
        if (formData[field]) {
            const error = await validator.validateField(String(field), formData[field]);

            if (error) {
                setErrors(prev => ({ ...prev, [field]: error }));
            } else {
                setErrors(prev => ({ ...prev, [field]: '' }));
            }
        }
    }, [formData, validator]);    /**
     * Handle CV upload success
     */
    const handleCVUploadSuccess = useCallback(() => {
        announce('CV procesado y guardado exitosamente', 'polite');
        setSuccessMessage('¡Tu CV fue procesado y guardado exitosamente!');
        setTimeout(() => setSuccessMessage(''), 5000);
    }, [announce]);

    /**
     * Submit form data
     */
    const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSubmitAttempted(true);
        setStatus(ApplicationStatus.SUBMITTING);

        try {
            // Validate form
            const isValid = await validateForm(formData as JobApplicationDataWithIndex);
            if (!isValid) {
                setStatus(ApplicationStatus.ERROR);
                announce('Por favor, corrige los errores en el formulario', 'assertive');
                return;
            }

            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1200));

            // Success
            setStatus(ApplicationStatus.SUCCESS);
            setSuccessMessage('¡Tu postulación fue enviada exitosamente!');
            announce('Postulación enviada exitosamente', 'polite');

            // Reset form
            setFormData({
                firstName: '',
                lastName: '',
                email: '',
                phone: '',
                location: '',
                coverLetter: '',
                salaryExpectation: '',
                availabilityDate: '',
                workPreference: 'remote',
                experience: '',
                portfolio: '',
                linkedinProfile: ''
            });
            setSubmitAttempted(false);

            // Clear success message after 5 seconds
            setTimeout(() => {
                setSuccessMessage('');
                setStatus(ApplicationStatus.IDLE);
            }, 5000);

        } catch (error) {
            console.error('Error submitting application:', error);
            setStatus(ApplicationStatus.ERROR);
            setErrors({ general: 'Error al enviar la postulación. Intente nuevamente.' });
            announce('Error al enviar la postulación', 'assertive');
        }
    };

    const isSubmitting = status === ApplicationStatus.SUBMITTING;


    return (
        <ErrorBoundary>
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                {/* Skip to main content target */}
                <div id="main-content" tabIndex={-1}>
                    <header className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900 mb-2">
                            Postulación a Vacante
                        </h1>
                        <p className="text-gray-600">
                            Completa tu información para postularte a esta oportunidad laboral.
                        </p>
                    </header>

                    {/* Success message */}
                    {successMessage && (
                        <div
                            className="mb-6 p-4 bg-green-50 border border-green-200 rounded-md"
                            role="alert"
                            aria-live="polite"
                        >
                            <div className="flex">
                                <div className="flex-shrink-0">
                                    <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                    </svg>
                                </div>
                                <div className="ml-3">
                                    <p className="text-green-700 font-medium">{successMessage}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* General error message */}
                    {errors.general && (
                        <div
                            className="mb-6 p-4 bg-red-50 border border-red-200 rounded-md"
                            role="alert"
                            aria-live="assertive"
                        >
                            <div className="flex">
                                <div className="flex-shrink-0">
                                    <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                    </svg>
                                </div>
                                <div className="ml-3">
                                    <p className="text-red-700 font-medium">{errors.general}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {/* CV Upload Section */}
                        <section className="lg:col-span-2">
                            <h2 className="text-xl font-semibold text-gray-900 mb-4">
                                Subir Curriculum Vitae
                            </h2>
                            <div className="bg-gray-50 p-6 rounded-lg">
                                <Suspense fallback={
                                    <div className="flex items-center justify-center h-40">
                                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                        <span className="ml-2">Cargando formulario de CV...</span>
                                    </div>
                                }>
                                    <UploadCV onSuccess={handleCVUploadSuccess} />
                                </Suspense>
                            </div>
                        </section>

                        {/* Application Form */}
                        <section className="lg:col-span-2">
                            <h2 className="text-xl font-semibold text-gray-900 mb-6">
                                Información de Postulación
                            </h2>

                            <form onSubmit={handleSubmit} noValidate>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="md:col-span-2">
                                        <Textarea
                                            placeholder="Carta de Presentación"
                                            value={formData.coverLetter}
                                            onChange={e => updateField('coverLetter', e.target.value)}
                                            onBlur={() => handleFieldBlur('coverLetter')}
                                            className="w-full"
                                            rows={4}
                                            maxLength={2000}
                                        />
                                        {errors.coverLetter && <span className="text-red-600 text-sm">{errors.coverLetter}</span>}
                                    </div>
                                    <div>
                                        <Input
                                            type="text"
                                            placeholder="Pretensión Salarial (USD)"
                                            value={formData.salaryExpectation}
                                            onChange={e => updateField('salaryExpectation', e.target.value)}
                                            onBlur={() => handleFieldBlur('salaryExpectation')}
                                            className="w-full"
                                        />
                                        {errors.salaryExpectation && <span className="text-red-600 text-sm">{errors.salaryExpectation}</span>}
                                    </div>
                                    <div>
                                        <Input
                                            type="text"
                                            placeholder="Fecha de Disponibilidad"
                                            value={formData.availabilityDate}
                                            onChange={e => updateField('availabilityDate', e.target.value)}
                                            onBlur={() => handleFieldBlur('availabilityDate')}
                                            className="w-full"
                                        />
                                        {errors.availabilityDate && <span className="text-red-600 text-sm">{errors.availabilityDate}</span>}
                                    </div>
                                    <div>
                                        <Select
                                            value={formData.workPreference}
                                            onValueChange={value => updateField('workPreference', value as 'remote' | 'hybrid' | 'onsite')}
                                        >
                                            {workPreferenceOptions.map(opt => (
                                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                                            ))}
                                        </Select>
                                        {errors.workPreference && <span className="text-red-600 text-sm">{errors.workPreference}</span>}
                                    </div>
                                    <div>
                                        <Input
                                            type="url"
                                            placeholder="https://linkedin.com/in/tu-perfil"
                                            value={formData.linkedinProfile}
                                            onChange={e => updateField('linkedinProfile', e.target.value)}
                                            onBlur={() => handleFieldBlur('linkedinProfile')}
                                            className="w-full"
                                        />
                                        {errors.linkedinProfile && <span className="text-red-600 text-sm">{errors.linkedinProfile}</span>}
                                    </div>
                                    <div>
                                        <Input
                                            type="url"
                                            placeholder="https://tu-portafolio.com"
                                            value={formData.portfolio}
                                            onChange={e => updateField('portfolio', e.target.value)}
                                            onBlur={() => handleFieldBlur('portfolio')}
                                            className="w-full"
                                        />
                                        {errors.portfolio && <span className="text-red-600 text-sm">{errors.portfolio}</span>}
                                    </div>
                                </div>

                                {/* Submit Button */}
                                <div className="mt-8 flex flex-col sm:flex-row gap-4">
                                    <Button
                                        type="submit"
                                        disabled={isSubmitting}
                                        className="flex-1 sm:flex-none sm:min-w-[200px]"
                                        data-testid="submit-button"
                                    >
                                        {isSubmitting ? 'Enviando...' : 'Enviar Postulación'}
                                    </Button>
                                    <Button
                                        type="button"
                                        disabled={isSubmitting}
                                        onClick={() => {
                                            setFormData({
                                                firstName: '',
                                                lastName: '',
                                                email: '',
                                                phone: '',
                                                location: '',
                                                coverLetter: '',
                                                salaryExpectation: '',
                                                availabilityDate: '',
                                                workPreference: 'remote',
                                                experience: '',
                                                portfolio: '',
                                                linkedinProfile: ''
                                            });
                                            setErrors({});
                                            setSubmitAttempted(false);
                                            announce('Formulario reiniciado', 'polite');
                                        }}
                                        data-testid="reset-button"
                                    >
                                        Limpiar Formulario
                                    </Button>
                                </div>
                            </form>
                        </section>
                    </div>
                </div>
            </div>
        </ErrorBoundary>
    );
}
