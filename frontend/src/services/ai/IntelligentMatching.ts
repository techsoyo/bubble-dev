import { Candidate, Job, Company } from './types';

export interface MatchingRequest {
  candidateId?: string;
  jobId?: string;
  companyId?: string;
  filters?: MatchingFilters;
  algorithm?: 'basic' | 'advanced' | 'ai_enhanced';
  includeAnalytics?: boolean;
}

export interface MatchingFilters {
  skillsWeight?: number;
  experienceWeight?: number;
  cultureWeight?: number;
  locationWeight?: number;
  salaryWeight?: number;
  minScore?: number;
  maxResults?: number;
}

export interface MatchingResult {
  candidateId: string;
  jobId: string;
  score: number;
  breakdown: MatchingBreakdown;
  recommendations: string[];
  riskFactors: RiskFactor[];
  culturalFit: CulturalFitAnalysis;
  successPrediction: SuccessPrediction;
  metadata: MatchingMetadata;
}

export interface MatchingBreakdown {
  skillsMatch: ScoreComponent;
  experienceMatch: ScoreComponent;
  cultureMatch: ScoreComponent;
  locationMatch: ScoreComponent;
  salaryMatch: ScoreComponent;
  additionalFactors: AdditionalFactor[];
}

export interface ScoreComponent {
  score: number;
  weight: number;
  details: string[];
  confidence: number;
}

export interface AdditionalFactor {
  name: string;
  impact: number;
  description: string;
}

export interface RiskFactor {
  type: 'low' | 'medium' | 'high';
  category: string;
  description: string;
  mitigation: string[];
}

export interface CulturalFitAnalysis {
  overallFit: number;
  dimensions: CultureDimension[];
  recommendations: string[];
  concerns: string[];
}

export interface CultureDimension {
  name: string;
  candidateValue: number;
  companyValue: number;
  alignment: number;
  importance: number;
}

export interface SuccessPrediction {
  probability: number;
  confidence: number;
  factors: PredictionFactor[];
  timeline: PredictionTimeline;
}

export interface PredictionFactor {
  name: string;
  contribution: number;
  rationale: string;
}

export interface PredictionTimeline {
  shortTerm: number; // 3 months
  mediumTerm: number; // 1 year
  longTerm: number; // 3+ years
}

export interface MatchingMetadata {
  algorithm: string;
  version: string;
  processingTime: number;
  dataQuality: number;
  lastUpdated: string;
}

export class IntelligentMatching {
  async performMatching(request: MatchingRequest): Promise<MatchingResult[]> {
    try {
      let results: MatchingResult[] = [];

      if (request.candidateId && request.jobId) {
        const result = await this.matchSingle(request.candidateId, request.jobId, request);
        results = [result];
      } else {
        throw new Error('Either candidateId or jobId must be provided');
      }

      results = this.applyFilters(results, request.filters);
      results.sort((a, b) => b.score - a.score);

      return results;
    } catch (error) {
      console.error('Intelligent matching failed:', error);
      throw new Error('Failed to perform intelligent matching');
    }
  }

  private async matchSingle(
    candidateId: string,
    jobId: string,
    request: MatchingRequest
  ): Promise<MatchingResult> {
    const [candidate, job, company] = await Promise.all([
      this.fetchCandidate(candidateId),
      this.fetchJob(jobId),
      this.fetchCompany(jobId)
    ]);

    const algorithm = request.algorithm || 'advanced';
    const weights = this.getWeights(request.filters);

    const breakdown = await this.calculateMatchingBreakdown(candidate, job, weights);
    const overallScore = this.calculateOverallScore(breakdown);

    const culturalFit = this.analyzeCulturalFit(candidate, company);
    const riskFactors = this.assessRisks(candidate, job, overallScore);
    const successPrediction = this.predictSuccess(candidate, job, culturalFit, overallScore);

    const recommendations = this.generateRecommendations(
      candidate, job, breakdown, culturalFit, riskFactors
    );

    return {
      candidateId,
      jobId,
      score: overallScore,
      breakdown,
      recommendations,
      riskFactors,
      culturalFit,
      successPrediction,
      metadata: {
        algorithm,
        version: '2.0',
        processingTime: 0,
        dataQuality: this.assessDataQuality(candidate, job),
        lastUpdated: new Date().toISOString()
      }
    };
  }

  private async calculateMatchingBreakdown(
    candidate: Candidate,
    job: Job,
    weights: MatchingFilters
  ): Promise<MatchingBreakdown> {
    const skillsMatch = this.calculateSkillsMatch(candidate, job);
    const experienceMatch = this.calculateExperienceMatch(candidate, job);
    const cultureMatch = this.calculateCultureMatch();
    const locationMatch = this.calculateLocationMatch(candidate, job);
    const salaryMatch = this.calculateSalaryMatch(candidate, job);

    return {
      skillsMatch: {
        score: skillsMatch.score,
        weight: weights.skillsWeight || 0.4,
        details: skillsMatch.details,
        confidence: skillsMatch.confidence
      },
      experienceMatch: {
        score: experienceMatch.score,
        weight: weights.experienceWeight || 0.3,
        details: experienceMatch.details,
        confidence: experienceMatch.confidence
      },
      cultureMatch: {
        score: cultureMatch.score,
        weight: weights.cultureWeight || 0.15,
        details: cultureMatch.details,
        confidence: cultureMatch.confidence
      },
      locationMatch: {
        score: locationMatch.score,
        weight: weights.locationWeight || 0.1,
        details: locationMatch.details,
        confidence: locationMatch.confidence
      },
      salaryMatch: {
        score: salaryMatch.score,
        weight: weights.salaryWeight || 0.05,
        details: salaryMatch.details,
        confidence: salaryMatch.confidence
      },
      additionalFactors: []
    };
  }


  private calculateSkillsMatch(candidate: Candidate, job: Job): { score: number; details: string[]; confidence: number } {
    const candidateSkills = (candidate.skills || []).map((s: string) => s.toLowerCase());
    const requiredSkills = (job.requiredSkills || []).map((s: string) => s.toLowerCase());

    if (requiredSkills.length === 0) {
      return { score: 0.5, details: ['No required skills specified'], confidence: 0.3 };
    }

    const matchedRequired = requiredSkills.filter((skill: string) =>
      candidateSkills.includes(skill)
    );

    const requiredScore = matchedRequired.length / requiredSkills.length;
    const details = [`${matchedRequired.length}/${requiredSkills.length} required skills matched`];

    return {
      score: requiredScore,
      details,
      confidence: 0.9
    };
  }


  private calculateExperienceMatch(candidate: Candidate, job: Job): { score: number; details: string[]; confidence: number } {
    const candidateExperience = (candidate.experience || []);
    const totalYears = candidateExperience.reduce((sum: number, exp: { duration?: number }) => sum + (exp.duration || 0), 0);
    const jobLevel = job.experienceLevel || 'entry';

    const levelRequirements: Record<string, { min: number; max: number }> = {
      'entry': { min: 0, max: 2 },
      'mid': { min: 2, max: 5 },
      'senior': { min: 5, max: 10 },
      'lead': { min: 8, max: 15 }
    };

    const requirement = levelRequirements[jobLevel];
    if (!requirement) {
      return { score: 0.5, details: ['Unknown experience level'], confidence: 0.3 };
    }

    let score = 0;
    if (totalYears >= requirement.min && totalYears <= requirement.max) {
      score = 1;
    } else if (totalYears < requirement.min) {
      score = requirement.min > 0 ? (totalYears / requirement.min) * 0.7 : 0;
    } else {
      score = 0.3;
    }

    const details = [`${totalYears} years total experience`];
    return { score, details, confidence: 0.9 };
  }


  private calculateCultureMatch(): { score: number; details: string[]; confidence: number } {
    return {
      score: 0.7,
      details: ['Culture match assessment pending'],
      confidence: 0.5
    };
  }


  private calculateLocationMatch(_candidate: Candidate, job: Job): { score: number; details: string[]; confidence: number } {
    if (job.remote) {
      return {
        score: 1,
        details: ['Remote position'],
        confidence: 1
      };
    }

    return {
      score: 0.8,
      details: ['Location assessment'],
      confidence: 0.8
    };
  }


  private calculateSalaryMatch(candidate: Candidate, job: Job): { score: number; details: string[]; confidence: number } {
    if (!candidate.expectedSalary || !job.salaryRange) {
      return {
        score: 0.5,
        details: ['Salary information incomplete'],
        confidence: 0.3
      };
    }

    const expected = candidate.expectedSalary;
    const { min, max } = job.salaryRange;

    let score = 0;
    if (expected >= min && expected <= max) {
      score = 1;
    } else {
      score = 0.5;
    }

    const details = [`Expected: $${expected.toLocaleString()}`];
    return { score, details, confidence: 0.8 };
  }

  private calculateOverallScore(breakdown: MatchingBreakdown): number {
    const components = [
      breakdown.skillsMatch,
      breakdown.experienceMatch,
      breakdown.cultureMatch,
      breakdown.locationMatch,
      breakdown.salaryMatch
    ];

    const weightedScore = components.reduce((sum, component) =>
      sum + (component.score * component.weight), 0);

    return Math.min(1, weightedScore);
  }

  private analyzeCulturalFit(_candidate: Candidate, company: Company): CulturalFitAnalysis {
    return {
      overallFit: 0.7,
      dimensions: [
        {
          name: 'Innovation',
          candidateValue: 0.8,
          companyValue: company.culture?.innovation || 0.5,
          alignment: 0.7,
          importance: 0.8
        }
      ],
      recommendations: ['Conduct cultural fit interview'],
      concerns: []
    };
  }

  private assessRisks(_candidate: Candidate, _job: Job, matchingScore: number): RiskFactor[] {
    const risks: RiskFactor[] = [];

    if (matchingScore < 0.7) {
      risks.push({
        type: 'medium',
        category: 'Skills Gap',
        description: 'Some skills gap identified',
        mitigation: ['Provide training']
      });
    }

    return risks;
  }

  private predictSuccess(
    _candidate: Candidate,
    _job: Job,
    _culturalFit: CulturalFitAnalysis,
    matchingScore: number
  ): SuccessPrediction {
    return {
      probability: matchingScore * 0.8,
      confidence: 0.7,
      factors: [
        {
          name: 'Skills Alignment',
          contribution: matchingScore * 0.5,
          rationale: 'Skills match analysis'
        }
      ],
      timeline: {
        shortTerm: matchingScore * 0.9,
        mediumTerm: matchingScore,
        longTerm: matchingScore * 1.1
      }
    };
  }

  private generateRecommendations(
    _candidate: Candidate,
    _job: Job,
    breakdown: MatchingBreakdown,
    _culturalFit: CulturalFitAnalysis,
    _riskFactors: RiskFactor[]
  ): string[] {
    const recommendations: string[] = [];

    if (breakdown.skillsMatch.score > 0.8) {
      recommendations.push('Strong candidate - proceed with interview');
    } else {
      recommendations.push('Consider skills assessment');
    }

    return recommendations;
  }

  private getWeights(filters?: MatchingFilters): MatchingFilters {
    return {
      skillsWeight: filters?.skillsWeight || 0.4,
      experienceWeight: filters?.experienceWeight || 0.3,
      cultureWeight: filters?.cultureWeight || 0.15,
      locationWeight: filters?.locationWeight || 0.1,
      salaryWeight: filters?.salaryWeight || 0.05,
      minScore: filters?.minScore || 0,
      maxResults: filters?.maxResults || 100
    };
  }

  private applyFilters(results: MatchingResult[], filters?: MatchingFilters): MatchingResult[] {
    if (!filters) return results;

    let filtered = results;

    if (filters.minScore !== undefined) {
      filtered = filtered.filter(result => result.score >= filters.minScore!);
    }

    if (filters.maxResults !== undefined) {
      filtered = filtered.slice(0, filters.maxResults);
    }

    return filtered;
  }

  private async fetchCandidate(candidateId: string): Promise<Candidate> {
    // Mock implementation
    return {
      id: candidateId,
      name: 'John Doe',
      email: 'john@example.com',
      skills: ['JavaScript', 'React', 'Node.js'],
      experience: [
        {
          id: '1',
          company: 'Tech Corp',
          position: 'Frontend Developer',
          duration: 2,
          startDate: '2022-01-01',
          industry: 'technology'
        }
      ],
      education: [
        {
          id: '1',
          institution: 'University',
          degree: 'Computer Science',
          field: 'Software Engineering',
          startYear: 2018,
          endYear: 2022
        }
      ],
      status: 'active',
      createdAt: '2022-01-01',
      updatedAt: '2024-01-01'
    };
  }

  private async fetchJob(jobId: string): Promise<Job> {
    // Mock implementation
    return {
      id: jobId,
      title: 'Senior Frontend Developer',
      description: 'Looking for an experienced frontend developer',
      companyId: '1',
      remote: true,
      experienceLevel: 'senior',
      employmentType: 'full-time',
      requiredSkills: ['JavaScript', 'React', 'TypeScript'],
      preferredSkills: ['Node.js', 'GraphQL'],
      salaryRange: { min: 80000, max: 120000 },
      industry: 'technology',
      status: 'active',
      postedDate: '2024-01-01'
    };
  }

  private async fetchCompany(_jobId: string): Promise<Company> {
    // Mock implementation
    return {
      id: '1',
      name: 'Innovation Labs',
      industry: 'technology',
      size: 'medium',
      location: 'San Francisco',
      culture: {
        innovation: 0.9,
        collaboration: 0.8,
        workLifeBalance: 0.7,
        growth: 0.8,
        diversity: 0.7,
        flexibility: 0.9
      }
    };
  }

  private assessDataQuality(candidate: Candidate, job: Job): number {
    let score = 0;

    if (candidate.skills && candidate.skills.length > 0) score += 0.3;
    if (candidate.experience && candidate.experience.length > 0) score += 0.3;
    if (job.requiredSkills && job.requiredSkills.length > 0) score += 0.4;

    return score;
  }
}

export default IntelligentMatching;
