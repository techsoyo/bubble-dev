/**
 * AI-Specific Types for Intelligent Matching Service
 *
 * This file contains only AI-specific types and interfaces.
 * Base types (Candidate, Job, Company, Education, etc.) are imported from ../types.ts
 *
 * @package Types
 * @author Bubble Talents Development Team
 * @version 1.0.0
 * @since 2025-01-07
 */

// Import base types from main types file to avoid duplication
export type {
  Candidate,
  Job,
  Company,
  Education,
  WorkExperience
} from '../types';

// AI-specific types and extensions
export interface CandidatePreferences {
  preferredIndustries: string[];
  preferredLocations: string[];
  preferredSalaryRange: {
    min: number;
    max: number;
  };
  preferredEmploymentTypes: string[];
  preferredCompanySizes: string[];
  workLifeBalance: number; // 0-1
  careerGrowth: number; // 0-1
  remoteWork: boolean;
  relocationWilling: boolean;
}

export interface Document {
  id: string;
  type: 'resume' | 'cover_letter' | 'portfolio' | 'certificate' | 'reference';
  fileName: string;
  fileSize: number;
  mimeType: string;
  url: string;
  uploadedAt: string;
  status: 'processing' | 'completed' | 'failed';
}

export interface JobRequirement {
  type: 'required' | 'preferred';
  category: 'skill' | 'experience' | 'education' | 'certification';
  description: string;
  weight: number; // 0-1
}

export interface CompanyCulture {
  innovation: number; // 0-1
  collaboration: number; // 0-1
  workLifeBalance: number; // 0-1
  growth: number; // 0-1
  diversity: number; // 0-1
  flexibility: number; // 0-1
}

export interface WorkEnvironment {
  remoteWork: boolean;
  flexibleHours: boolean;
  openOffice: boolean;
  meetingCulture: 'minimal' | 'moderate' | 'frequent';
  decisionMaking: 'hierarchical' | 'collaborative' | 'autonomous';
}

export type ApplicationStatus =
  | 'draft'
  | 'submitted'
  | 'under_review'
  | 'shortlisted'
  | 'interview_scheduled'
  | 'interviewed'
  | 'assessment'
  | 'offer_made'
  | 'offer_accepted'
  | 'offer_declined'
  | 'rejected'
  | 'withdrawn';

export type ApplicationStage =
  | 'application'
  | 'screening'
  | 'assessment'
  | 'interview'
  | 'final_interview'
  | 'offer'
  | 'hired'
  | 'rejected';

export interface ApplicationNote {
  id: string;
  content: string;
  authorId: string;
  authorName: string;
  type: 'general' | 'interview' | 'technical' | 'feedback';
  createdAt: string;
  private: boolean;
}

export interface ApplicationFeedback {
  id: string;
  stage: ApplicationStatus;
  rating: number; // 1-5
  comments: string;
  criteria: FeedbackCriteria[];
  reviewerId: string;
  reviewerName: string;
  createdAt: string;
}

export interface FeedbackCriteria {
  name: string;
  score: number; // 1-5
  weight: number; // 0-1
  comments?: string;
}

export interface User {
  id: string;
  email: string;
  name: string;
  role: UserRole;
  profile: UserProfile;
  settings: UserSettings;
  createdAt: string;
  lastLogin?: string;
  active: boolean;
}

export type UserRole = 'candidate' | 'recruiter' | 'hr_manager' | 'admin';

export interface UserProfile {
  firstName: string;
  lastName: string;
  phone?: string;
  location?: string;
  timezone?: string;
  profileImage?: string;
  bio?: string;
}

export interface UserSettings {
  notifications: NotificationSettings;
  privacy: PrivacySettings;
  preferences: UserPreferences;
}

export interface NotificationSettings {
  email: boolean;
  push: boolean;
  sms: boolean;
  applicationUpdates: boolean;
  interviewReminders: boolean;
  offerUpdates: boolean;
  marketingEmails: boolean;
}

export interface PrivacySettings {
  profileVisibility: 'public' | 'recruiters_only' | 'private';
  showSalary: boolean;
  showExperience: boolean;
  allowDataSharing: boolean;
}

export interface UserPreferences {
  theme: 'light' | 'dark' | 'auto';
  language: string;
  timezone: string;
  dateFormat: string;
  currency: string;
}

export interface AnalyticsData {
  name: string;
  data: DataPoint[];
  type: 'line' | 'bar' | 'area';
}

export interface DataPoint {
  x: string | number;
  y: number;
  label?: string;
}

export interface Comparison {
  name: string;
  current: number;
  previous: number;
  change: number;
  changePercent: number;
}

export interface SearchFilters {
  keywords?: string;
  location?: string;
  remote?: boolean;
  experienceLevel?: string[];
  employmentType?: string[];
  salaryRange?: {
    min?: number;
    max?: number;
  };
  skills?: string[];
  industry?: string[];
  companySize?: string[];
  postedWithin?: number; // days
}

export interface SearchResult<T> {
  items: T[];
  total: number;
  page: number;
  pageSize: number;
  totalPages: number;
  hasNext: boolean;
  hasPrev: boolean;
}

export interface PaginationOptions {
  page: number;
  pageSize: number;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}

export interface FileUpload {
  file: File;
  progress: number;
  status: 'pending' | 'uploading' | 'completed' | 'failed';
  error?: string;
  result?: UploadResult;
}

export interface UploadResult {
  id: string;
  fileName: string;
  fileSize: number;
  mimeType: string;
  url: string;
  thumbnailUrl?: string;
  uploadedAt: string;
}

export interface AppConfig {
  api: {
    baseUrl: string;
    timeout: number;
    retries: number;
  };
  features: {
    aiMatching: boolean;
    videoInterviews: boolean;
    realTimeChat: boolean;
    analytics: boolean;
  };
  limits: {
    maxFileSize: number;
    maxApplications: number;
    searchResultsPerPage: number;
  };
  ui: {
    theme: string;
    language: string;
    dateFormat: string;
    timezone: string;
  };
}

// Re-export RecommendationResult from main types to avoid duplication
export type { RecommendationResult, RecommendationFilters } from '../types';
