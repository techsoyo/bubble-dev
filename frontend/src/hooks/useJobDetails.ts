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
import ApiService from '@/services/ApiService';

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

// API function for fetching job details
const fetchJobDetails = async (jobId: string): Promise<JobDetails> => {
    try {
        // Real API call
        const response = await ApiService.get<JobDetails>(`jobs/${jobId}`);

        // Normalize dates to ensure consistency
        return {
            ...response,
            datePosted: response.datePosted || new Date().toISOString(),
            applicationDeadline: response.applicationDeadline || undefined
        };
    } catch (error) {
        console.error('Error fetching job details:', error);
        throw error;
    }
};


// Query key factory
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
            // Fetch related jobs from API based on job category, skills, or company
            const relatedJobsResponse = await ApiService.get<{ jobIds: string[] }>(`jobs/${jobId}/related`, {
                timeout: 5000 // shorter timeout for prefetch
            });

            const relatedJobIds = relatedJobsResponse.jobIds || [];

            for (const relatedJobId of relatedJobIds.slice(0, 3)) { // Limit to 3 related jobs
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
            // Real API call to submit application
            const response = await ApiService.post(`jobs/${jobId}/apply`, {
                ...applicationData,
                jobId,
                appliedAt: new Date().toISOString()
            });

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

            return response;
        } catch (error) {
            console.error('Failed to submit application:', error);
            throw error;
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

    // Initialize bookmark status from API
    useEffect(() => {
        const checkBookmarkStatus = async () => {
            try {
                // Real API call to get bookmark status
                const response = await ApiService.get<{ isBookmarked: boolean }>(`jobs/${jobId}/bookmark`);
                setIsBookmarked(response.isBookmarked);
            } catch (error) {
                console.error('Failed to check bookmark status:', error);
                setIsBookmarked(false);
            }
        };

        checkBookmarkStatus();
    }, [jobId]);

    const toggleBookmark = useCallback(async () => {
        setIsLoading(true);
        try {
            const newBookmarkState = !isBookmarked;

            // Real API call to toggle bookmark
            await ApiService.post(`jobs/${jobId}/bookmark`, {
                isBookmarked: newBookmarkState
            });

            setIsBookmarked(newBookmarkState);
        } catch (error) {
            console.error('Failed to toggle bookmark:', error);
            throw error;
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
