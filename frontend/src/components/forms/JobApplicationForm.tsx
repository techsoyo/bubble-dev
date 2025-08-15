/**
 * Job Application Form Component
 * 
 * Extracted from Apply.tsx for better reusability and testing.
 * Implements centralized validation with useValidator hook.
 * 
 * @package Components
 * @author Bubble Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import React, { useState, useCallback, useMemo, useEffect } from 'react';
import { useValidator } from '../../utils/validator';
import { FormInput } from '../ui/FormInput';
import { FormSelect } from '../ui/FormSelect';
import { FormTextarea } from '../ui/FormTextarea';
import { Button } from '../ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { toast } from '../ui/use-toast';

export interface JobApplicationData {
    firstName: string;
    lastName: string;
    email: string;
    phone: string;
    location: string;
    coverLetter: string;
    salaryExpectation: string;
    availabilityDate: string;
    workPreference: 'remote' | 'hybrid' | 'onsite';
    experience: string;
    portfolio?: string;
    linkedinProfile?: string;
    resumeFile?: File;
    [key: string]: unknown; // Firma de índice para compatibilidad
}

interface JobApplicationFormProps {
    jobId?: string;
    onSubmit: (data: JobApplicationData) => Promise<void>;
    initialData?: Partial<JobApplicationData>;
    isLoading?: boolean;
    className?: string;
}

// Validation rules for the form
const validationRules = {
    firstName: {
        required: true,
        minLength: 2,
        pattern: /^[a-zA-ZÀ-ÿ\s]+$/,
        errorMessage: 'First name must be at least 2 characters and contain only letters'
    },
    lastName: {
        required: true,
        minLength: 2,
        pattern: /^[a-zA-ZÀ-ÿ\s]+$/,
        errorMessage: 'Last name must be at least 2 characters and contain only letters'
    },
    email: {
        required: true,
        email: true,
        errorMessage: 'Please enter a valid email address'
    },
    phone: {
        required: true,
        pattern: /^[\+]?[1-9][\d]{0,15}$/,
        errorMessage: 'Please enter a valid phone number'
    },
    location: {
        required: true,
        minLength: 3,
        errorMessage: 'Location must be at least 3 characters'
    },
    coverLetter: {
        required: true,
        minLength: 100,
        maxLength: 2000,
        errorMessage: 'Cover letter must be between 100 and 2000 characters'
    },
    salaryExpectation: {
        required: true,
        pattern: /^\d+$/,
        errorMessage: 'Please enter a valid salary amount (numbers only)'
    },
    availabilityDate: {
        required: true,
        minLength: 3,
        errorMessage: 'Please specify your availability'
    },
    workPreference: {
        required: true,
        errorMessage: 'Please select your work preference'
    },
    experience: {
        required: true,
        minLength: 50,
        maxLength: 1000,
        errorMessage: 'Experience description must be between 50 and 1000 characters'
    },
    portfolio: {
        required: false,
        pattern: /^https?:\/\/.+/,
        errorMessage: 'Please enter a valid URL'
    },
    linkedinProfile: {
        required: false,
        pattern: /^https:\/\/(www\.)?linkedin\.com\/in\/.+/,
        errorMessage: 'Please enter a valid LinkedIn profile URL'
    }
};

const workPreferenceOptions = [
    { value: 'remote', label: 'Remote Work' },
    { value: 'hybrid', label: 'Hybrid Work' },
    { value: 'onsite', label: 'On-site Work' }
];

export const JobApplicationForm: React.FC<JobApplicationFormProps> = ({
    jobId,
    onSubmit,
    initialData = {},
    isLoading = false,
    className = ''
}) => {
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
        linkedinProfile: '',
        ...initialData
    });

    const [resumeFile, setResumeFile] = useState<File | null>(null);

    // Validation hook
    const {
        errors,
        validate,
        validateAll,
        clearError
    } = useValidator();

    // Check if form is valid
    const isValid = useMemo(() => {
        return Object.keys(errors).length === 0 &&
            formData.firstName &&
            formData.lastName &&
            formData.email &&
            formData.phone &&
            formData.location &&
            formData.coverLetter &&
            formData.experience &&
            formData.salaryExpectation &&
            formData.availabilityDate;
    }, [errors, formData]);

    // Memoized form sections for performance
    const personalInfoSection = useMemo(() => (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <FormInput
                label="First Name"
                name="firstName"
                type="text"
                value={formData.firstName}
                onChange={(e) => updateField('firstName', e.target.value)}
                error={errors.firstName}
                required
                placeholder="Enter your first name"
                data-testid="firstName-input"
                aria-describedby="firstName-error"
            />

            <FormInput
                label="Last Name"
                name="lastName"
                type="text"
                value={formData.lastName}
                onChange={(e) => updateField('lastName', e.target.value)}
                error={errors.lastName}
                required
                placeholder="Enter your last name"
                data-testid="lastName-input"
                aria-describedby="lastName-error"
            />

            <FormInput
                label="Email Address"
                name="email"
                type="email"
                value={formData.email}
                onChange={(e) => updateField('email', e.target.value)}
                error={errors.email}
                required
                placeholder="your.email@example.com"
                data-testid="email-input"
                aria-describedby="email-error"
            />

            <FormInput
                label="Phone Number"
                name="phone"
                type="tel"
                value={formData.phone}
                onChange={(e) => updateField('phone', e.target.value)}
                error={errors.phone}
                required
                placeholder="+1 (555) 123-4567"
                data-testid="phone-input"
                aria-describedby="phone-error"
            />

            <div className="md:col-span-2">
                <FormInput
                    label="Location"
                    name="location"
                    type="text"
                    value={formData.location}
                    onChange={(e) => updateField('location', e.target.value)}
                    error={errors.location}
                    required
                    placeholder="City, Country"
                    data-testid="location-input"
                    aria-describedby="location-error"
                />
            </div>
        </div>
    ), [formData.firstName, formData.lastName, formData.email, formData.phone, formData.location, errors]);

    const professionalInfoSection = useMemo(() => (
        <div className="space-y-6">
            <FormTextarea
                label="Cover Letter"
                name="coverLetter"
                value={formData.coverLetter}
                onChange={(e) => updateField('coverLetter', e.target.value)}
                error={errors.coverLetter}
                required
                placeholder="Tell us why you're interested in this position and how your skills align with our needs..."
                rows={6}
                data-testid="coverLetter-input"
                aria-describedby="coverLetter-error"
            />

            <FormTextarea
                label="Professional Experience"
                name="experience"
                value={formData.experience}
                onChange={(e) => updateField('experience', e.target.value)}
                error={errors.experience}
                required
                placeholder="Describe your relevant work experience, key achievements, and technologies you've worked with..."
                rows={5}
                data-testid="experience-input"
                aria-describedby="experience-error"
            />

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <FormInput
                    label="Salary Expectation (USD/month)"
                    name="salaryExpectation"
                    type="number"
                    value={formData.salaryExpectation}
                    onChange={(e) => updateField('salaryExpectation', e.target.value)}
                    error={errors.salaryExpectation}
                    required
                    placeholder="3000"
                    data-testid="salaryExpectation-input"
                    aria-describedby="salaryExpectation-error"
                />

                <FormInput
                    label="Availability Date"
                    name="availabilityDate"
                    type="text"
                    value={formData.availabilityDate}
                    onChange={(e) => updateField('availabilityDate', e.target.value)}
                    error={errors.availabilityDate}
                    required
                    placeholder="Immediate, 2 weeks, 1 month"
                    data-testid="availabilityDate-input"
                    aria-describedby="availabilityDate-error"
                />
            </div>

            <FormSelect
                label="Work Preference"
                name="workPreference"
                value={formData.workPreference}
                onChange={(e) => updateField('workPreference', e.target.value as 'remote' | 'hybrid' | 'onsite')}
                options={workPreferenceOptions}
                error={errors.workPreference}
                required
                data-testid="workPreference-select"
                aria-describedby="workPreference-error"
            />
        </div>
    ), [formData.coverLetter, formData.experience, formData.salaryExpectation, formData.availabilityDate, formData.workPreference, errors]);

    const optionalInfoSection = useMemo(() => (
        <div className="space-y-6">
            <FormInput
                label="Portfolio URL (Optional)"
                name="portfolio"
                type="url"
                value={formData.portfolio || ''}
                onChange={(e) => updateField('portfolio', e.target.value)}
                error={errors.portfolio}
                placeholder="https://your-portfolio.com"
                data-testid="portfolio-input"
                aria-describedby="portfolio-error"
            />

            <FormInput
                label="LinkedIn Profile (Optional)"
                name="linkedinProfile"
                type="url"
                value={formData.linkedinProfile || ''}
                onChange={(e) => updateField('linkedinProfile', e.target.value)}
                error={errors.linkedinProfile}
                placeholder="https://linkedin.com/in/your-profile"
                data-testid="linkedinProfile-input"
                aria-describedby="linkedinProfile-error"
            />

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    Resume/CV (Optional)
                </label>
                <input
                    type="file"
                    accept=".pdf,.doc,.docx"
                    onChange={handleFileChange}
                    className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    data-testid="resume-input"
                    aria-describedby="resume-help"
                />
                <p id="resume-help" className="text-xs text-gray-500 mt-1">
                    Accepted formats: PDF, DOC, DOCX. Max size: 5MB
                </p>
            </div>
        </div>
    ), [formData.portfolio, formData.linkedinProfile, errors]);

    // Update field handler with validation
    const updateField = useCallback((field: keyof JobApplicationData, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        // Clear error when user starts typing
        if (errors[field]) {
            clearError(String(field));
        }
    }, [errors, clearError]);

    // File upload handler
    const handleFileChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            // Validate file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                toast({
                    title: "File too large",
                    description: "Please select a file smaller than 5MB",
                    variant: "destructive"
                });
                return;
            }

            // Validate file type
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!allowedTypes.includes(file.type)) {
                toast({
                    title: "Invalid file type",
                    description: "Please select a PDF, DOC, or DOCX file",
                    variant: "destructive"
                });
                return;
            }

            setResumeFile(file);
        }
    }, []);

    // Form submission handler
    const handleSubmit = useCallback(async (e: React.FormEvent) => {
        e.preventDefault();

        // Validate all fields
        const formIsValid = validateAll(formData, validationRules);

        if (!formIsValid) {
            toast({
                title: "Validation Error",
                description: "Please correct the errors in the form",
                variant: "destructive"
            });
            return;
        }

        try {
            const applicationData = {
                ...formData,
                ...(resumeFile && { resumeFile })
            };

            await onSubmit(applicationData);

            toast({
                title: "Application Submitted",
                description: "Your job application has been submitted successfully!",
                variant: "default"
            });

            // Reset form on successful submission
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
            setResumeFile(null);

        } catch (error) {
            console.error('Error submitting application:', error);
            toast({
                title: "Submission Error",
                description: "There was an error submitting your application. Please try again.",
                variant: "destructive"
            });
        }
    }, [formData, resumeFile, validateAll, onSubmit]);

    return (
        <form onSubmit={handleSubmit} className={`space-y-8 ${className}`} noValidate>
            {/* Personal Information */}
            <Card>
                <CardHeader>
                    <CardTitle>Personal Information</CardTitle>
                </CardHeader>
                <CardContent>
                    {personalInfoSection}
                </CardContent>
            </Card>

            {/* Professional Information */}
            <Card>
                <CardHeader>
                    <CardTitle>Professional Information</CardTitle>
                </CardHeader>
                <CardContent>
                    {professionalInfoSection}
                </CardContent>
            </Card>

            {/* Optional Information */}
            <Card>
                <CardHeader>
                    <CardTitle>Additional Information</CardTitle>
                </CardHeader>
                <CardContent>
                    {optionalInfoSection}
                </CardContent>
            </Card>

            {/* Submit Button */}
            <div className="flex justify-end pt-6">
                <Button
                    type="submit"
                    disabled={isLoading || !isValid}
                    className="bg-[#FF4785] hover:bg-[#FF3575] text-white px-8 py-3 text-lg font-medium"
                    data-testid="submit-button"
                >
                    {isLoading ? 'Submitting...' : 'Submit Application'}
                </Button>
            </div>
        </form>
    );
};
