import { Candidate, Job, Company } from './types';
import type { RecommendationResult as BaseRecommendationResult, RecommendationFilters as BaseRecommendationFilters } from './types';

export interface RecommendationRequest {
  candidateId?: string;
  jobId?: string;
  companyId?: string;
  filters?: RecommendationFilters;
  limit?: number;
}

export interface RecommendationFilters {
  experience?: string[];
  skills?: string[];
  location?: string[];
  salaryRange?: { min: number; max: number };
  remote?: boolean;
  industry?: string[];
}

export interface RecommendationResult {
  id: string;
  type: 'candidate' | 'job' | 'company';
  score: number;
  reasons: string[];
  data: Candidate | Job | Company;
  matchScore: number;
  confidence: number;
  algorithm: string;
  factors: RecommendationFactor[];
}

export interface RecommendationFactor {
  name: string;
  weight: number;
  value: number;
  description: string;
}

export class RecommendationEngine {
  private readonly weights = {
    skills: 0.4,
    experience: 0.25,
    culture: 0.15,
    location: 0.1,
    salary: 0.1
  };

  async getJobRecommendations(
    candidateId: string,
    options: RecommendationRequest = {}
  ): Promise<RecommendationResult[]> {
    try {
      const candidate = await this.fetchCandidate(candidateId);
      const jobs = await this.fetchJobs(options.filters);

      const recommendations = jobs.map(job =>
        this.calculateJobMatch(candidate, job)
      );

      return recommendations
        .sort((a, b) => b.score - a.score)
        .slice(0, options.limit || 20);
    } catch (error) {
      console.error('Error getting job recommendations:', error);
      throw new Error('Failed to generate job recommendations');
    }
  }

  async getCandidateRecommendations(
    jobId: string,
    options: RecommendationRequest = {}
  ): Promise<RecommendationResult[]> {
    try {
      const job = await this.fetchJob(jobId);
      const candidates = await this.fetchCandidates(options.filters);

      const recommendations = candidates.map(candidate =>
        this.calculateCandidateMatch(candidate, job)
      );

      return recommendations
        .sort((a, b) => b.score - a.score)
        .slice(0, options.limit || 20);
    } catch (error) {
      console.error('Error getting candidate recommendations:', error);
      throw new Error('Failed to generate candidate recommendations');
    }
  }

  private calculateJobMatch(candidate: Candidate, job: Job): RecommendationResult {
    const factors: RecommendationFactor[] = [];
    let totalScore = 0;

    // Skills matching
    const skillsScore = this.calculateSkillsMatch(
      candidate.skills || [],
      job.requiredSkills || []
    );
    factors.push({
      name: 'Skills Match',
      weight: this.weights.skills,
      value: skillsScore,
      description: `${Math.round(skillsScore * 100)}% skills alignment`
    });
    totalScore += skillsScore * this.weights.skills;

    // Experience matching
    const experienceScore = this.calculateExperienceMatch(
      candidate.experience || [],
      job.experienceLevel || 'entry'
    );
    factors.push({
      name: 'Experience Level',
      weight: this.weights.experience,
      value: experienceScore,
      description: `Experience level compatibility: ${Math.round(experienceScore * 100)}%`
    });
    totalScore += experienceScore * this.weights.experience;

    // Location matching
    const locationScore = this.calculateLocationMatch(
      candidate.location,
      job.location,
      job.remote
    );
    factors.push({
      name: 'Location',
      weight: this.weights.location,
      value: locationScore,
      description: `Location compatibility: ${Math.round(locationScore * 100)}%`
    });
    totalScore += locationScore * this.weights.location;

    // Salary matching
    const salaryScore = this.calculateSalaryMatch(
      candidate.expectedSalary,
      job.salaryRange
    );
    factors.push({
      name: 'Salary Expectations',
      weight: this.weights.salary,
      value: salaryScore,
      description: `Salary alignment: ${Math.round(salaryScore * 100)}%`
    });
    totalScore += salaryScore * this.weights.salary;

    const reasons = this.generateJobReasons(factors, candidate, job);

    return {
      id: job.id,
      type: 'job',
      score: Math.round(totalScore * 100) / 100,
      reasons,
      data: job,
      matchScore: Math.round(totalScore * 100) / 100,
      confidence: this.calculateConfidence(factors),
      algorithm: 'weighted_scoring_v1',
      factors
    };
  }

  private calculateCandidateMatch(candidate: Candidate, job: Job): RecommendationResult {
    const factors: RecommendationFactor[] = [];
    let totalScore = 0;

    // Skills matching
    const skillsScore = this.calculateSkillsMatch(
      candidate.skills || [],
      job.requiredSkills || []
    );
    factors.push({
      name: 'Skills Match',
      weight: this.weights.skills,
      value: skillsScore,
      description: `${Math.round(skillsScore * 100)}% skills alignment`
    });
    totalScore += skillsScore * this.weights.skills;

    // Experience matching
    const experienceScore = this.calculateExperienceMatch(
      candidate.experience || [],
      job.experienceLevel || 'entry'
    );
    factors.push({
      name: 'Experience Level',
      weight: this.weights.experience,
      value: experienceScore,
      description: `Experience level compatibility: ${Math.round(experienceScore * 100)}%`
    });
    totalScore += experienceScore * this.weights.experience;

    const reasons = this.generateCandidateReasons(factors, candidate, job);

    return {
      id: candidate.id,
      type: 'candidate',
      score: Math.round(totalScore * 100) / 100,
      reasons,
      data: candidate,
      matchScore: Math.round(totalScore * 100) / 100,
      confidence: this.calculateConfidence(factors),
      algorithm: 'weighted_scoring_v1',
      factors
    };
  }

  private calculateSkillsMatch(candidateSkills: string[], requiredSkills: string[]): number {
    if (requiredSkills.length === 0) return 1;

    const matchedSkills = candidateSkills.filter(skill =>
      requiredSkills.some(required =>
        skill.toLowerCase().includes(required.toLowerCase()) ||
        required.toLowerCase().includes(skill.toLowerCase())
      )
    );

    return matchedSkills.length / requiredSkills.length;
  }

  private calculateExperienceMatch(
    candidateExperience: any[],
    requiredLevel: string
  ): number {
    const totalYears = candidateExperience.reduce((sum, exp) => {
      const years = exp.duration || 0;
      return sum + years;
    }, 0);

    const levelRequirements: Record<string, { min: number; max: number }> = {
      'entry': { min: 0, max: 2 },
      'mid': { min: 2, max: 5 },
      'senior': { min: 5, max: 10 },
      'lead': { min: 8, max: 15 }
    };

    const requirement = levelRequirements[requiredLevel] || levelRequirements['entry'];

    if (!requirement) return 0;

    if (totalYears >= requirement.min && totalYears <= requirement.max) {
      return 1;
    } else if (totalYears < requirement.min) {
      return totalYears / requirement.min;
    } else {
      return Math.max(0.7, 1 - (totalYears - requirement.max) / 10);
    }
  }

  private calculateLocationMatch(
    candidateLocation: string,
    jobLocation: string,
    isRemote: boolean
  ): number {
    if (isRemote) return 1;
    if (!candidateLocation || !jobLocation) return 0.5;

    const candidate = candidateLocation.toLowerCase();
    const job = jobLocation.toLowerCase();

    if (candidate === job) return 1;
    if (candidate.includes(job) || job.includes(candidate)) return 0.8;

    return 0.3;
  }

  private calculateSalaryMatch(
    expectedSalary: number,
    salaryRange: { min: number; max: number }
  ): number {
    if (!expectedSalary || !salaryRange) return 0.5;

    if (expectedSalary >= salaryRange.min && expectedSalary <= salaryRange.max) {
      return 1;
    }

    if (expectedSalary < salaryRange.min) {
      const diff = (salaryRange.min - expectedSalary) / salaryRange.min;
      return Math.max(0, 1 - diff);
    } else {
      const diff = (expectedSalary - salaryRange.max) / salaryRange.max;
      return Math.max(0, 1 - diff);
    }
  }

  private calculateConfidence(factors: RecommendationFactor[]): number {
    const avgScore = factors.reduce((sum, factor) => sum + factor.value, 0) / factors.length;
    const variance = factors.reduce((sum, factor) =>
      sum + Math.pow(factor.value - avgScore, 2), 0
    ) / factors.length;

    return Math.max(0.1, 1 - variance);
  }

  private generateJobReasons(
    factors: RecommendationFactor[],
    _candidate: Candidate,
    job: Job
  ): string[] {
    const reasons: string[] = [];

    factors.forEach(factor => {
      if (factor.value > 0.7) {
        switch (factor.name) {
          case 'Skills Match':
            reasons.push(`Strong skills alignment with ${job.title}`);
            break;
          case 'Experience Level':
            reasons.push(`Experience level matches job requirements`);
            break;
          case 'Location':
            reasons.push(`Location compatibility with job`);
            break;
          case 'Salary Expectations':
            reasons.push(`Salary expectations align with offered range`);
            break;
        }
      }
    });

    if (reasons.length === 0) {
      reasons.push('Potential growth opportunity');
    }

    return reasons;
  }

  private generateCandidateReasons(
    factors: RecommendationFactor[],
    _candidate: Candidate,
    job: Job
  ): string[] {
    const reasons: string[] = [];

    factors.forEach(factor => {
      if (factor.value > 0.7) {
        switch (factor.name) {
          case 'Skills Match':
            reasons.push(`Has required skills for ${job.title}`);
            break;
          case 'Experience Level':
            reasons.push(`Experience level fits job requirements`);
            break;
        }
      }
    });

    if (reasons.length === 0) {
      reasons.push('Potential candidate worth considering');
    }

    return reasons;
  }

  private async fetchCandidate(_candidateId: string): Promise<Candidate> {
    // Implementation would fetch from API/database
    throw new Error('Method not implemented');
  }

  private async fetchJob(_jobId: string): Promise<Job> {
    // Implementation would fetch from API/database
    throw new Error('Method not implemented');
  }

  private async fetchJobs(_filters?: RecommendationFilters): Promise<Job[]> {
    // Implementation would fetch from API/database
    throw new Error('Method not implemented');
  }

  private async fetchCandidates(_filters?: RecommendationFilters): Promise<Candidate[]> {
    // Implementation would fetch from API/database
    throw new Error('Method not implemented');
  }
}

export default RecommendationEngine;