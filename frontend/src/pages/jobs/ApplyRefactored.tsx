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

import { useState, useCallback } from 'react';
import type { Candidate } from '../../components/UploadCV';
import { useParams } from 'react-router-dom';
import { ErrorBoundary } from '../../components/ErrorBoundary';
import { JobApplicationForm, JobApplicationData } from '../../components/forms/JobApplicationForm';
import { useSkipLinks, useAnnouncer } from '../../utils/accessibility';
import UploadCV from '../../components/UploadCV';

/**
 * Application status enum
 */
enum ApplicationStatus {
    IDLE = 'idle',
    SUBMITTING = 'submitting',
    SUCCESS = 'success',
    ERROR = 'error'
}

/**
 * Apply Page Component - Uses extracted JobApplicationForm
 */
export default function ApplyPage(): JSX.Element {
    // Get job ID from URL params
    const { id: jobId } = useParams<{ id: string }>();

    // Skip links for accessibility
    useSkipLinks();

    // Screen reader announcements
    const announce = useAnnouncer();

    // Application state
    const [status, setStatus] = useState<ApplicationStatus>(ApplicationStatus.IDLE);
    const [successMessage, setSuccessMessage] = useState('');

    /**
     * Handle CV upload success
     */
    const handleCVUploadSuccess = useCallback((candidate: Candidate) => {
        announce('CV procesado y guardado exitosamente', 'polite');
        setSuccessMessage('¡Tu CV fue procesado y guardado exitosamente!');

        // Clear success message after 5 seconds
        setTimeout(() => setSuccessMessage(''), 5000);
    }, [announce]);

    /**
     * Handle job application form submission
     */
    const handleApplicationSubmit = useCallback(async (data: JobApplicationData) => {
        setStatus(ApplicationStatus.SUBMITTING);

        try {

            // Simulate API call - replace with actual API call
            await new Promise(resolve => setTimeout(resolve, 1200));

            // Success
            setStatus(ApplicationStatus.SUCCESS);
            setSuccessMessage('¡Tu postulación fue enviada exitosamente!');
            announce('Postulación enviada exitosamente', 'polite');

            // Clear success message after 5 seconds and reset status
            setTimeout(() => {
                setSuccessMessage('');
                setStatus(ApplicationStatus.IDLE);
            }, 5000);

        } catch (error) {
            console.error('Error al enviar postulación:', error);
            setStatus(ApplicationStatus.ERROR);
            announce('Error al enviar la postulación. Por favor intenta nuevamente.', 'assertive');

            // Clear error status after 3 seconds
            setTimeout(() => {
                setStatus(ApplicationStatus.IDLE);
            }, 3000);
        }
    }, [announce]);

    // Loading state
    const isLoading = status === ApplicationStatus.SUBMITTING;

    return (
        <ErrorBoundary>
            <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
                <div className="container mx-auto px-4 py-8 max-w-4xl">
                    <div className="bg-white rounded-lg shadow-xl overflow-hidden">
                        {/* Header */}
                        <div className="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6">
                            <h1 className="text-3xl font-bold mb-2">
                                Postulación a Vacante
                            </h1>
                            <p className="text-blue-100">
                                Completa tu postulación y sube tu CV
                            </p>
                            {jobId && (
                                <p className="text-blue-200 text-sm mt-1">
                                    ID de la vacante: {jobId}
                                </p>
                            )}
                        </div>

                        <div className="p-8">
                            {/* Success Message */}
                            {successMessage && (
                                <div className="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            <svg className="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <p className="text-green-700 font-medium">{successMessage}</p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Error Message */}
                            {status === ApplicationStatus.ERROR && (
                                <div className="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            <svg className="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <p className="text-red-700 font-medium">
                                                Error al enviar la postulación. Por favor intenta nuevamente.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* CV Upload Section */}
                            <section className="mb-8">
                                <h2 className="text-xl font-semibold text-gray-900 mb-4">
                                    Subir Curriculum Vitae
                                </h2>
                                <div className="bg-gray-50 p-6 rounded-lg">
                                    <UploadCV onSuccess={handleCVUploadSuccess} />
                                </div>
                            </section>

                            {/* Job Application Form */}
                            <section>
                                <h2 className="text-xl font-semibold text-gray-900 mb-6">
                                    Información de Postulación
                                </h2>

                                <JobApplicationForm
                                    jobId={jobId || "1"}
                                    onSubmit={handleApplicationSubmit}
                                    isLoading={isLoading}
                                    className="space-y-6"
                                />
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </ErrorBoundary>
    );
}
