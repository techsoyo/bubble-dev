/**
 * Lazy-loaded components for JobDetails page
 * 
 * These components are loaded asynchronously to improve initial page load performance.
 * Used for secondary components that are not immediately visible to the user.
 * 
 * @package Components/Jobs/Lazy
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React, { lazy, Suspense } from 'react';

// Placeholder components that will be replaced with actual implementations
const JobReviewsPlaceholder: React.FC = () => (
    <div className="p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold mb-2">Job Reviews</h3>
        <p className="text-gray-600">Employee reviews and ratings will be displayed here.</p>
    </div>
);

const CompanyProfilePlaceholder: React.FC = () => (
    <div className="p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold mb-2">Company Profile</h3>
        <p className="text-gray-600">Detailed company information will be displayed here.</p>
    </div>
);

const SimilarJobsPlaceholder: React.FC = () => (
    <div className="p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold mb-2">Similar Jobs</h3>
        <p className="text-gray-600">Related job opportunities will be displayed here.</p>
    </div>
);

const JobShareModalPlaceholder: React.FC = () => (
    <div className="p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold mb-2">Share Job</h3>
        <p className="text-gray-600">Job sharing functionality will be available here.</p>
    </div>
);

const ApplicationHistoryModalPlaceholder: React.FC = () => (
    <div className="p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold mb-2">Application History</h3>
        <p className="text-gray-600">Your application history will be displayed here.</p>
    </div>
);

const JobSalaryInsightsPlaceholder: React.FC = () => (
    <div className="p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold mb-2">Salary Insights</h3>
        <p className="text-gray-600">Salary data and market insights will be displayed here.</p>
    </div>
);

const JobRequirementsAnalyzerPlaceholder: React.FC = () => (
    <div className="p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold mb-2">Requirements Analyzer</h3>
        <p className="text-gray-600">AI-powered requirements analysis will be displayed here.</p>
    </div>
);

// Lazy-loaded components - using Promise.resolve for now to avoid module loading errors
export const JobReviews = lazy(() =>
    Promise.resolve({ default: JobReviewsPlaceholder })
);

export const CompanyProfile = lazy(() =>
    Promise.resolve({ default: CompanyProfilePlaceholder })
);

export const SimilarJobs = lazy(() =>
    Promise.resolve({ default: SimilarJobsPlaceholder })
);

export const JobShareModal = lazy(() =>
    Promise.resolve({ default: JobShareModalPlaceholder })
);

export const ApplicationHistoryModal = lazy(() =>
    Promise.resolve({ default: ApplicationHistoryModalPlaceholder })
);

export const JobSalaryInsights = lazy(() =>
    Promise.resolve({ default: JobSalaryInsightsPlaceholder })
);

export const JobRequirementsAnalyzer = lazy(() =>
    Promise.resolve({ default: JobRequirementsAnalyzerPlaceholder })
);

// Component loading wrapper with error boundary
export const withLazyLoading = (Component: React.LazyExoticComponent<any>) => {
    return (props: any) => {
        return (
            <Suspense fallback={<LazyComponentSkeleton />}>
                <ErrorBoundary>
                    <Component {...props} />
                </ErrorBoundary>
            </Suspense>
        );
    };
};

// Skeleton loader for lazy components
const LazyComponentSkeleton: React.FC = () => (
    <div className="animate-pulse space-y-4">
        <div className="h-4 bg-gray-200 rounded w-3/4"></div>
        <div className="h-4 bg-gray-200 rounded w-1/2"></div>
        <div className="h-4 bg-gray-200 rounded w-2/3"></div>
        <div className="h-20 bg-gray-200 rounded"></div>
    </div>
);

// Error boundary for lazy components
class ErrorBoundary extends React.Component<
    { children: React.ReactNode },
    { hasError: boolean }
> {
    constructor(props: { children: React.ReactNode }) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(_error: Error) {
        return { hasError: true };
    }

    override componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
        console.error('Lazy component error:', error, errorInfo);
    }

    override render() {
        if (this.state.hasError) {
            return (
                <div className="p-4 text-center text-gray-500">
                    <p>Something went wrong loading this component.</p>
                    <button
                        onClick={() => this.setState({ hasError: false })}
                        className="mt-2 px-4 py-2 text-sm bg-blue-500 text-white rounded hover:bg-blue-600"
                    >
                        Try Again
                    </button>
                </div>
            );
        }

        return this.props.children;
    }
}
