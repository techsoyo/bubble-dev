/**
 * Tipos principales para el sistema de reclutamiento
 */

export interface Candidate {
  id: string;
  name: string;
  firstName?: string;
  lastName?: string;
  email: string;
  phone?: string;
  location?: string;
  resume_url?: string;
  skills: string[];
  experience?: WorkExperience[];
  languages?: string[];
  summary?: string;
  expectedSalary?: number;
  experience_years: number;
  education: Education[];
  work_experience: WorkExperience[];
  created_at: string;
  updated_at: string;
  status: 'active' | 'inactive' | 'pending';
}

export interface Job {
  id: string;
  title: string;
  description: string;
  company_id: string;
  requirements: string[];
  requiredSkills?: string[];
  preferred_skills: string[];
  experience_level: 'entry' | 'mid' | 'senior' | 'lead';
  experienceLevel?: 'entry' | 'mid' | 'senior' | 'lead'; // Alias for compatibility
  salary_min?: number;
  salary_max?: number;
  salaryRange?: { min: number; max: number };
  location: string;
  remote?: boolean;
  remote_allowed: boolean;
  status: 'open' | 'closed' | 'draft';
  created_at: string;
  updated_at: string;
}

export interface Company {
  id: string;
  name: string;
  description?: string;
  website?: string;
  industry?: string;
  size?: string;
  location?: string;
  logo_url?: string;
  created_at: string;
  updated_at: string;
}

export interface Education {
  institution: string;
  degree: string;
  field_of_study?: string;
  start_date?: string;
  end_date?: string;
  gpa?: number;
}

export interface WorkExperience {
  company: string;
  position: string;
  description?: string;
  start_date: string;
  end_date?: string;
  current: boolean;
}

// Tipos para análisis de CV
export interface CVAnalysisResult {
  extractedData: {
    personalInfo: {
      name: string;
      email: string;
      phone?: string;
      location?: string;
      linkedin_url?: string;
      website?: string;
    };
    skills: string[];
    languages?: string[];
    summary?: string;
    totalExperience: number;
    education: Education[];
    workExperience: WorkExperience[];
  };
  candidate?: Partial<Candidate>;
  confidence?: number;
  analysis: {
    strengths: string[];
    weaknesses: string[];
    recommendations: string[];
    overallScore: number;
  };
  metadata: {
    wordCount: number;
    sections: string[];
    language: string;
    format: string;
    analysisDate: Date;
  };
  overallScore: number;
  aiAnalysis: {
    strengths: string[];
    weaknesses: string[];
    recommendations: string[];
  };
  processingTime?: number;
}

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
}

export interface RecommendationFilters {
  categories?: string[];
  minConfidence?: number;
  dateRange?: {
    start: Date;
    end: Date;
  };
}
