/**
 * useJobDetails Hook
 * 
 * Custom hook for fetching and caching job details with optimized performance.
 * Implements caching, error handling, and loading states.
 * 
 * @package Hooks
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';

export interface JobDetails {
    id: string;
    title: string;
    company: string;
    location: string;
    type: 'full-time' | 'part-time' | 'contract' | 'internship';
    category: string;
    description: string;
    requirements: string[];
    responsibilities: string[];
    benefits: string[];
    salary: {
        min: number;
        max: number;
        currency: string;
        period: 'monthly' | 'yearly';
    };
    skills: string[];
    experience: {
        min: number;
        max: number;
        level: 'entry' | 'mid' | 'senior' | 'lead';
    };
    remote: boolean;
    datePosted: string;
    applicationDeadline?: string;
    isActive: boolean;
    applicationsCount: number;
    viewsCount: number;
    companyLogo?: string;
    companyDescription?: string;
    contactEmail?: string;
}

interface UseJobDetailsOptions {
    enabled?: boolean;
    staleTime?: number;
    cacheTime?: number;
    refetchOnWindowFocus?: boolean;
    retry?: number | boolean;
}

interface UseJobDetailsReturn {
    job: JobDetails | null;
    isLoading: boolean;
    isError: boolean;
    error: Error | null;
    isStale: boolean;
    refetch: () => Promise<any>;
    invalidateCache: () => void;
    prefetchRelatedJobs: () => void;
}

// Cache configuration
const DEFAULT_OPTIONS: UseJobDetailsOptions = {
    enabled: true,
    staleTime: 5 * 60 * 1000, // 5 minutes
    cacheTime: 10 * 60 * 1000, // 10 minutes
    refetchOnWindowFocus: false,
    retry: 3
};

// Mock API function - replace with actual API call
const fetchJobDetails = async (jobId: string): Promise<JobDetails> => {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, Math.random() * 1000 + 500));

    // Mock data - replace with actual API call
    const mockJobs: Record<string, JobDetails> = {
        '1': {
            id: '1',
            title: 'Senior Frontend Developer',
            company: 'TechCorp Inc.',
            location: 'San Francisco, CA',
            type: 'full-time',
            category: 'Engineering',
            description: 'We are looking for a passionate Senior Frontend Developer to join our dynamic team...',
            requirements: [
                '5+ years of experience with React and TypeScript',
                'Strong understanding of modern JavaScript (ES6+)',
                'Experience with state management (Redux, Zustand)',
                'Knowledge of testing frameworks (Jest, Cypress)',
                'Familiarity with CI/CD pipelines'
            ],
            responsibilities: [
                'Develop and maintain high-quality frontend applications',
                'Collaborate with designers and backend developers',
                'Write clean, maintainable, and testable code',
                'Participate in code reviews and technical discussions',
                'Mentor junior developers'
            ],
            benefits: [
                'Competitive salary and equity package',
                'Flexible working hours and remote work options',
                'Health, dental, and vision insurance',
                'Professional development budget',
                'Modern office space with free snacks'
            ],
            salary: {
                min: 120000,
                max: 160000,
                currency: 'USD',
                period: 'yearly'
            },
            skills: ['React', 'TypeScript', 'JavaScript', 'CSS', 'Node.js', 'Git'],
            experience: {
                min: 5,
                max: 8,
                level: 'senior'
            },
            remote: true,
            datePosted: '2025-01-01T00:00:00Z',
            applicationDeadline: '2025-02-01T23:59:59Z',
            isActive: true,
            applicationsCount: 45,
            viewsCount: 324,
            companyLogo: '/images/companies/techcorp.png',
            companyDescription: 'TechCorp is a leading technology company focused on building innovative solutions...',
            contactEmail: 'jobs@techcorp.com'
        },
        '2': {
            id: '2',
            title: 'Backend Engineer',
            company: 'DataFlow Systems',
            location: 'New York, NY',
            type: 'full-time',
            category: 'Engineering',
            description: 'Join our backend team to build scalable and robust API services...',
            requirements: [
                '3+ years of experience with Node.js or Python',
                'Experience with databases (PostgreSQL, MongoDB)',
                'Knowledge of microservices architecture',
                'Understanding of API design principles',
                'Experience with cloud platforms (AWS, GCP)'
            ],
            responsibilities: [
                'Design and implement RESTful APIs',
                'Optimize database performance',
                'Ensure system security and scalability',
                'Collaborate with frontend and DevOps teams',
                'Write comprehensive tests'
            ],
            benefits: [
                'Competitive compensation package',
                'Remote-first culture',
                'Learning and development opportunities',
                'Health and wellness benefits',
                'Stock options'
            ],
            salary: {
                min: 90000,
                max: 130000,
                currency: 'USD',
                period: 'yearly'
            },
            skills: ['Node.js', 'Python', 'PostgreSQL', 'Docker', 'AWS', 'REST APIs'],
            experience: {
                min: 3,
                max: 6,
                level: 'mid'
            },
            remote: true,
            datePosted: '2025-01-02T00:00:00Z',
            applicationDeadline: '2025-01-30T23:59:59Z',
            isActive: true,
            applicationsCount: 28,
            viewsCount: 198,
            companyLogo: '/images/companies/dataflow.png',
            companyDescription: 'DataFlow Systems specializes in data processing and analytics solutions...',
            contactEmail: 'careers@dataflow.com'
        }
    };

    const job = mockJobs[jobId];
    if (!job) {
        throw new Error(`Job with ID ${jobId} not found`);
    }

    return job;
};

// Query key factory
const createJobQueryKey = (jobId: string) => ['job', jobId];

export const useJobDetails = (
    jobId: string | undefined,
    options: UseJobDetailsOptions = {}
): UseJobDetailsReturn => {
    const queryClient = useQueryClient();
    const mergedOptions = { ...DEFAULT_OPTIONS, ...options };

    // React Query for job details
    const {
        data: job,
        isLoading,
        isError,
        error,
        isStale,
        refetch
    } = useQuery<JobDetails, Error>({
        queryKey: createJobQueryKey(jobId || ''),
        queryFn: () => fetchJobDetails(jobId!),
        enabled: Boolean(jobId) && Boolean(mergedOptions.enabled),
        staleTime: mergedOptions.staleTime ?? 5 * 60 * 1000,
        gcTime: mergedOptions.cacheTime ?? 10 * 60 * 1000,
        refetchOnWindowFocus: mergedOptions.refetchOnWindowFocus ?? false,
        retry: mergedOptions.retry ?? 3
    });

    // Invalidate cache function
    const invalidateCache = useCallback(() => {
        if (jobId) {
            queryClient.invalidateQueries({
                queryKey: createJobQueryKey(jobId)
            });
        }
    }, [jobId, queryClient]);

    // Prefetch related jobs for better UX
    const prefetchRelatedJobs = useCallback(async () => {
        if (!job) return;

        try {
            // Get related jobs from cache or fetch them
            const relatedJobIds = ['1', '2'].filter(id => id !== jobId); // Mock related jobs

            for (const relatedJobId of relatedJobIds) {
                queryClient.prefetchQuery({
                    queryKey: createJobQueryKey(relatedJobId),
                    queryFn: () => fetchJobDetails(relatedJobId),
                    staleTime: mergedOptions.staleTime ?? 5 * 60 * 1000
                });
            }
        } catch (error) {
            console.warn('Failed to prefetch related jobs:', error);
        }
    }, [job, jobId, queryClient, mergedOptions.staleTime]);

    // Auto-prefetch related jobs when job details are loaded
    useEffect(() => {
        if (job && !isLoading) {
            prefetchRelatedJobs();
        }
    }, [job, isLoading, prefetchRelatedJobs]);

    // Memoized return value to prevent unnecessary re-renders
    const returnValue = useMemo((): UseJobDetailsReturn => ({
        job: job || null,
        isLoading,
        isError,
        error: error || null,
        isStale,
        refetch,
        invalidateCache,
        prefetchRelatedJobs
    }), [job, isLoading, isError, error, isStale, refetch, invalidateCache, prefetchRelatedJobs]);

    return returnValue;
};

// Related hooks for job operations
export const useJobApplication = (jobId: string) => {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const queryClient = useQueryClient();

    const submitApplication = useCallback(async (applicationData: Record<string, any>) => {
        setIsSubmitting(true);
        try {
            // Mock API call - replace with actual implementation
            await new Promise(resolve => setTimeout(resolve, 2000));

            // Use the application data in the API call
            console.log('Submitting application:', applicationData);

            // Update job application count in cache
            queryClient.setQueryData(
                createJobQueryKey(jobId),
                (oldData: JobDetails | undefined) => {
                    if (oldData) {
                        return {
                            ...oldData,
                            applicationsCount: oldData.applicationsCount + 1
                        };
                    }
                    return oldData;
                }
            );

            return { success: true };
        } catch (error) {
            throw new Error('Failed to submit application');
        } finally {
            setIsSubmitting(false);
        }
    }, [jobId, queryClient]);

    return {
        submitApplication,
        isSubmitting
    };
};

export const useJobBookmark = (jobId: string) => {
    const [isBookmarked, setIsBookmarked] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    // Initialize bookmark status - in real app, fetch from API
    useEffect(() => {
        // Mock API call to check bookmark status
        const checkBookmarkStatus = async () => {
            try {
                // Simulate checking bookmark status based on jobId
                const bookmarked = localStorage.getItem(`bookmark_${jobId}`) === 'true';
                setIsBookmarked(bookmarked);
            } catch (error) {
                console.error('Failed to check bookmark status:', error);
            }
        };

        checkBookmarkStatus();
    }, [jobId]);

    const toggleBookmark = useCallback(async () => {
        setIsLoading(true);
        try {
            // Mock API call - replace with actual implementation
            await new Promise(resolve => setTimeout(resolve, 500));

            const newBookmarkState = !isBookmarked;
            setIsBookmarked(newBookmarkState);

            // Store in localStorage for demo purposes
            localStorage.setItem(`bookmark_${jobId}`, String(newBookmarkState));
        } catch (error) {
            console.error('Failed to toggle bookmark:', error);
        } finally {
            setIsLoading(false);
        }
    }, [isBookmarked, jobId]);

    return {
        isBookmarked,
        toggleBookmark,
        isLoading
    };
};

// Utility function to get cached job data without triggering a fetch
export const getCachedJobDetails = (jobId: string): JobDetails | undefined => {
    const queryClient = useQueryClient();
    return queryClient.getQueryData(createJobQueryKey(jobId));
};
