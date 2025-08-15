/**
 * Types for AI Intelligent Matching Service
 * 
 * @package Types
 * @author Bubble Talents Development Team
 * @version 1.0.0
 * @since 2025-01-07
 */

export interface Candidate {
  id: string;
  name: string;
  email: string;
  phone?: string;
  location?: string;
  expectedSalary?: number;
  summary?: string;
  skills: string[];
  experience: Experience[];
  education: Education[];
  profileImage?: string;
  status: 'active' | 'inactive' | 'hired';
  preferences?: CandidatePreferences;
  documents?: Document[];
  createdAt: string;
  updatedAt: string;
}

export interface Experience {
  id: string;
  company: string;
  position: string;
  duration: number; // in years
  startDate: string;
  endDate?: string;
  description?: string;
  industry?: string;
  skills?: string[];
  achievements?: string[];
}

export interface Education {
  id: string;
  institution: string;
  degree: string;
  field: string;
  startYear: number;
  endYear?: number;
  gpa?: number;
  achievements?: string[];
}

export interface CandidatePreferences {
  workType: 'remote' | 'onsite' | 'hybrid';
  salaryRange: {
    min: number;
    max: number;
  };
  locations: string[];
  industries: string[];
  companySize: 'startup' | 'small' | 'medium' | 'large' | 'enterprise';
}

export interface Document {
  id: string;
  type: 'cv' | 'cover_letter' | 'portfolio' | 'certificate';
  fileName: string;
  fileUrl: string;
  uploadedAt: string;
}

export interface Job {
  id: string;
  title: string;
  description: string;
  companyId: string;
  location?: string;
  remote: boolean;
  experienceLevel: 'entry' | 'mid' | 'senior' | 'lead';
  employmentType: 'full-time' | 'part-time' | 'contract' | 'internship';
  requiredSkills: string[];
  preferredSkills: string[];
  salaryRange: {
    min: number;
    max: number;
  };
  industry?: string;
  educationRequirement?: string;
  benefits?: string[];
  status: 'active' | 'paused' | 'closed';
  postedDate: string;
  closingDate?: string;
  requirements?: JobRequirement[];
}

export interface JobRequirement {
  type: 'required' | 'preferred';
  category: 'skill' | 'experience' | 'education' | 'certification';
  description: string;
  weight: number; // 0-1
}

export interface Company {
  id: string;
  name: string;
  description?: string;
  industry: string;
  size: 'startup' | 'small' | 'medium' | 'large' | 'enterprise';
  location: string;
  website?: string;
  logo?: string;
  culture?: CompanyCulture;
  benefits?: string[];
  workEnvironment?: WorkEnvironment;
  foundedYear?: number;
  employees?: number;
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

// Application and process types
export interface Application {
  id: string;
  candidateId: string;
  jobId: string;
  status: ApplicationStatus;
  appliedDate: string;
  lastUpdated: string;
  stage: ApplicationStage;
  score?: number;
  notes?: ApplicationNote[];
  documents?: string[]; // Document IDs
  feedback?: ApplicationFeedback[];
}

export type ApplicationStatus =
  | 'submitted'
  | 'screening'
  | 'interview'
  | 'technical'
  | 'final'
  | 'offered'
  | 'accepted'
  | 'rejected'
  | 'withdrawn';

export interface ApplicationStage {
  current: ApplicationStatus;
  completed: ApplicationStatus[];
  next?: ApplicationStatus;
  estimatedDuration?: number; // days
}

export interface ApplicationNote {
  id: string;
  authorId: string;
  authorName: string;
  content: string;
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

// User and role types
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
  language: string;
  theme: 'light' | 'dark' | 'auto';
}

export interface NotificationSettings {
  email: boolean;
  browser: boolean;
  mobile: boolean;
  frequency: 'immediate' | 'daily' | 'weekly';
  types: NotificationType[];
}

export type NotificationType =
  | 'application_update'
  | 'new_job_match'
  | 'interview_scheduled'
  | 'message_received'
  | 'system_update';

export interface PrivacySettings {
  profileVisibility: 'public' | 'recruiter_only' | 'private';
  showEmail: boolean;
  showPhone: boolean;
  searchable: boolean;
}

// Analytics and reporting types
export interface AnalyticsData {
  timeframe: {
    start: string;
    end: string;
  };
  metrics: Metric[];
  trends: Trend[];
  comparisons?: Comparison[];
}

export interface Metric {
  name: string;
  value: number;
  unit: string;
  change?: {
    value: number;
    period: string;
    direction: 'up' | 'down' | 'stable';
  };
}

export interface Trend {
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

// Search and filter types
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

// API Response types
export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: ApiError;
  meta?: ResponseMeta;
}

export interface ApiError {
  code: string;
  message: string;
  details?: Record<string, unknown>;
  timestamp: string;
}

export interface ResponseMeta {
  timestamp: string;
  requestId: string;
  version: string;
  pagination?: PaginationOptions;
}

// Event and notification types
export interface SystemEvent {
  id: string;
  type: string;
  entityType: string;
  entityId: string;
  userId?: string;
  data: Record<string, unknown>;
  timestamp: string;
  source: string;
}

export interface Notification {
  id: string;
  userId: string;
  type: NotificationType;
  title: string;
  message: string;
  data?: Record<string, unknown>;
  read: boolean;
  createdAt: string;
  expiresAt?: string;
}

// File and media types
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

// Configuration types
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

// This file exports all types for the AI service

// Tipos para recomendaciones
export interface RecommendationResult {
  id: string;
  title: string;
  type?: 'job' | 'candidate';
  category: string;
  confidence: number;
  score: number;
  lastUpdated: Date;
  matchScore: number;
  reasons: string[];
  data?: unknown;
}

export interface RecommendationFilters {
  categories?: string[];
  minConfidence?: number;
  dateRange?: {
    start: Date;
    end: Date;
  };
}
