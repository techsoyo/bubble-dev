/**
 * Error Boundary Component
 * 
 * Production-ready error boundary with accessibility features
 * and comprehensive error handling.
 * 
 * @package Components
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React, { Component, ErrorInfo } from 'react';

interface ErrorBoundaryState {
    hasError: boolean;
    error?: Error | undefined;
    errorInfo?: ErrorInfo;
}

interface ErrorBoundaryProps {
    children: React.ReactNode;
    fallback?: React.ComponentType<{ error?: Error; resetError: () => void }>;
}

export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
    constructor(props: ErrorBoundaryProps) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return { hasError: true, error };
    }

    override componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        this.setState({ errorInfo });
        // Log a Sentry o backend
        if ((window as any).Sentry) {
            (window as any).Sentry.captureException(error);
        } else {
            // En producción, podrías enviar el error a un endpoint de logging del backend
            // fetch('/api/log-error', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ error: error.message, errorInfo }) });
        }
        console.error('Error caught by boundary:', error, errorInfo);
    }

    resetError = () => {
        this.setState({ hasError: false });
    };

    override render() {
        if (this.state.hasError) {
            const FallbackComponent = this.props.fallback || DefaultErrorFallback;
            return <FallbackComponent {...(this.state.error ? { error: this.state.error } : {})} resetError={this.resetError} />;
        }

        return this.props.children;
    }
}

function DefaultErrorFallback({ error, resetError }: { error?: Error; resetError: () => void }) {
    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <div className="max-w-md w-full space-y-8 text-center">
                <div>
                    <h2 className="text-3xl font-extrabold text-gray-900">
                        Algo salió mal
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        Ha ocurrido un error inesperado. Por favor, intenta nuevamente.
                    </p>
                    {error && (
                        <details className="mt-4 text-left">
                            <summary className="cursor-pointer text-sm text-gray-500">
                                Detalles del error
                            </summary>
                            <pre className="mt-2 text-xs bg-gray-100 p-2 rounded overflow-auto">
                                {error.message}
                            </pre>
                        </details>
                    )}
                </div>
                <div>
                    <button
                        onClick={resetError}
                        className="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        Intentar nuevamente
                    </button>
                </div>
            </div>
        </div>
    );
}
