import { Candidate, Education, WorkExperience } from '../types';
import type { CVAnalysisResult } from '../types';

export interface CVAnalysisRequest {
  fileContent: string;
  fileName: string;
  fileType: 'pdf' | 'doc' | 'docx' | 'txt';
  options?: AnalysisOptions;
}

export interface AnalysisOptions {
  extractSkills?: boolean;
  extractExperience?: boolean;
  extractEducation?: boolean;
  extractContact?: boolean;
  analyzeQuality?: boolean;
  language?: 'es' | 'en';
}

export interface QualityAnalysis {
  overall: number;
  completeness: number;
  clarity: number;
  relevance: number;
  formatting: number;
  details: {
    hasContact: boolean;
    hasObjective: boolean;
    hasExperience: boolean;
    hasEducation: boolean;
    hasSkills: boolean;
    hasAchievements: boolean;
  };
}

export interface ExtractedSection {
  type: string;
  content: string;
  confidence: number;
  startIndex: number;
  endIndex: number;
}

export class CVAnalysisService {
  private skillsKeywords = {
    technical: [
      // Lenguajes de programación
      'javascript', 'typescript', 'python', 'java', 'c++', 'c#', 'php', 'ruby', 'go', 'rust',
      'swift', 'kotlin', 'dart', 'scala', 'r', 'matlab', 'perl', 'lua', 'haskell',

      // Frameworks y librerías
      'react', 'vue', 'angular', 'svelte', 'next.js', 'nuxt.js', 'express', 'django', 'flask',
      'spring', 'laravel', 'symfony', 'rails', 'asp.net', 'fastapi', 'graphql',

      // Frontend
      'html', 'css', 'sass', 'less', 'bootstrap', 'tailwind', 'material-ui', 'styled-components',
      'webpack', 'vite', 'babel', 'eslint', 'prettier',

      // Backend y Bases de datos
      'node.js', 'mysql', 'postgresql', 'mongodb', 'redis', 'elasticsearch', 'oracle',
      'sqlite', 'cassandra', 'dynamodb', 'firebase', 'supabase',

      // Cloud y DevOps
      'aws', 'azure', 'gcp', 'docker', 'kubernetes', 'terraform', 'ansible', 'jenkins',
      'gitlab', 'github', 'bitbucket', 'jira', 'confluence', 'slack', 'trello',

      // Herramientas y otros
      'git', 'npm', 'yarn', 'pnpm', 'webpack', 'babel', 'jest', 'cypress', 'selenium',
      'figma', 'sketch', 'photoshop', 'illustrator', 'adobe xd'
    ],
    soft: [
      'leadership', 'communication', 'teamwork', 'problem solving', 'critical thinking',
      'creativity', 'adaptability', 'time management', 'project management', 'analytical thinking',
      'decision making', 'conflict resolution', 'mentoring', 'coaching', 'negotiation',
      'presentation skills', 'public speaking', 'writing', 'research', 'data analysis'
    ],
    marketing: [
      'digital marketing', 'seo', 'sem', 'social media', 'content marketing', 'email marketing',
      'google analytics', 'facebook ads', 'google ads', 'instagram', 'linkedin', 'twitter',
      'tiktok', 'youtube', 'influencer marketing', 'brand management', 'market research',
      'customer segmentation', 'crm', 'salesforce', 'hubspot', 'mailchimp'
    ],
    design: [
      'ui/ux design', 'user experience', 'user interface', 'graphic design', 'web design',
      'mobile design', 'responsive design', 'prototyping', 'wireframing', 'user research',
      'usability testing', 'accessibility', 'design systems', 'branding', 'logo design'
    ]
  };

  /**
   * Actualiza dinámicamente la lista de skills
   */
  updateSkillsCategory(category: keyof typeof this.skillsKeywords, newSkills: string[]): void {
    if (this.skillsKeywords[category]) {
      // Agregar skills sin duplicados
      const existingSkills = new Set(this.skillsKeywords[category]);
      newSkills.forEach(skill => existingSkills.add(skill.toLowerCase()));
      this.skillsKeywords[category] = Array.from(existingSkills);
    }
  }

  /**
   * Obtiene todas las skills disponibles
   */
  getAllSkills(): string[] {
    const allSkills: string[] = [];
    Object.values(this.skillsKeywords).forEach(categorySkills => {
      allSkills.push(...categorySkills);
    });
    return Array.from(new Set(allSkills)); // Remover duplicados
  }

  private readonly experiencePatterns = [
    /(\d{4})\s*[-–]\s*(\d{4}|present|current|actual)/gi,
    /(\d{1,2}\/\d{4})\s*[-–]\s*(\d{1,2}\/\d{4}|present|current|actual)/gi,
    /(\d{1,2})\s*(year|año|month|mes|week|semana)s?\s*(of\s*)?experience/gi
  ];

  async analyzeCV(request: CVAnalysisRequest): Promise<CVAnalysisResult> {
    const startTime = Date.now();

    try {
      // Extract text content based on file type
      const textContent = await this.extractTextContent(request);

      // Perform parallel analysis
      const [
        extractedData,
        qualityAnalysis,
        sections,
        metadata
      ] = await Promise.all([
        this.extractCandidateData(textContent, request.options),
        this.analyzeQuality(textContent),
        this.extractSections(textContent),
        this.extractMetadata(textContent, request.fileName)
      ]);

      const analysis = this.generateAnalysis(extractedData, qualityAnalysis);
      const confidence = this.calculateConfidence(extractedData, qualityAnalysis);

      return {
        extractedData: {
          personalInfo: {
            name: extractedData.name || '',
            email: extractedData.email || '',
            phone: extractedData.phone,
            location: extractedData.location,
            linkedin_url: extractedData.resume_url,
            website: extractedData.resume_url
          },
          skills: extractedData.skills || [],
          languages: extractedData.languages,
          summary: extractedData.summary,
          totalExperience: extractedData.experience_years || 0,
          education: extractedData.education || [],
          workExperience: extractedData.work_experience || []
        },
        candidate: extractedData,
        analysis,
        confidence,
        overallScore: analysis.overallScore,
        aiAnalysis: {
          strengths: analysis.strengths,
          weaknesses: analysis.weaknesses,
          recommendations: analysis.recommendations
        },
        processingTime: Date.now() - startTime,
        metadata: {
          ...metadata,
          sections: sections.map(s => s.type)
        }
      };
    } catch (error) {
      console.error('CV Analysis failed:', error);
      throw new Error('Failed to analyze CV');
    }
  }

  private async extractTextContent(request: CVAnalysisRequest): Promise<string> {
    switch (request.fileType) {
      case 'txt':
        return request.fileContent;
      case 'pdf':
        return this.extractFromPDF(request.fileContent);
      case 'doc':
      case 'docx':
        return this.extractFromWord(request.fileContent);
      default:
        throw new Error(`Unsupported file type: ${request.fileType}`);
    }
  }

  private async extractFromPDF(content: string): Promise<string> {
    try {
      // Para PDFs, asumimos que el contenido ya viene extraído del lado del cliente
      // En una implementación completa, usaríamos una librería como pdf-parse o PDF.js
      if (!content || content.trim().length === 0) {
        throw new Error('PDF content is empty or invalid');
      }

      // Limpiar contenido básico de PDF
      let cleanContent = content
        .replace(/\\n/g, '\n') // Unificar saltos de línea
        .replace(/\\t/g, ' ') // Reemplazar tabs con espacios
        .replace(/\s+/g, ' ') // Normalizar espacios múltiples
        .trim();

      // Remover caracteres de control comunes en PDFs
      cleanContent = cleanContent.replace(/[\x00-\x1F\x7F-\x9F]/g, '');

      return cleanContent;
    } catch (error) {
      console.error('Error extracting text from PDF:', error);
      throw new Error('Failed to extract text from PDF document');
    }
  }

  private async extractFromWord(content: string): Promise<string> {
    try {
      // Para documentos Word, asumimos que el contenido ya viene extraído
      // En una implementación completa, usaríamos mammoth.js o similar
      if (!content || content.trim().length === 0) {
        throw new Error('Word document content is empty or invalid');
      }

      // Limpiar contenido básico de Word
      let cleanContent = content
        .replace(/\\n/g, '\n')
        .replace(/\\t/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

      // Remover metadatos comunes de Word
      cleanContent = cleanContent
        .replace(/\[.*?\]/g, '') // Remover referencias
        .replace(/<[^>]*>/g, '') // Remover tags HTML si existen
        .trim();

      return cleanContent;
    } catch (error) {
      console.error('Error extracting text from Word document:', error);
      throw new Error('Failed to extract text from Word document');
    }
  }

  private async extractCandidateData(
    text: string,
    options: AnalysisOptions = {}
  ): Promise<Partial<Candidate>> {
    const candidate: Partial<Candidate> = {};

    if (options.extractContact !== false) {
      candidate.email = this.extractEmail(text);
      candidate.phone = this.extractPhone(text);
      candidate.location = this.extractLocation(text);
    }

    if (options.extractSkills !== false) {
      candidate.skills = this.extractSkills(text);
    }

    if (options.extractExperience !== false) {
      candidate.experience = this.extractExperience(text);
    }

    if (options.extractEducation !== false) {
      candidate.education = this.extractEducation(text);
    }

    // Extract name (usually at the beginning)
    candidate.firstName = this.extractFirstName(text);
    candidate.lastName = this.extractLastName(text);

    return candidate;
  }

  private extractEmail(text: string): string | undefined {
    const emailRegex = /\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/g;
    const matches = text.match(emailRegex);
    return matches?.[0];
  }

  private extractPhone(text: string): string | undefined {
    const phoneRegex = /(?:\+?1[-.\s]?)?\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4})/g;
    const matches = text.match(phoneRegex);
    return matches?.[0];
  }

  private extractLocation(text: string): string | undefined {
    // Simple location extraction - could be improved with NLP
    const locationPatterns = [
      /(?:live|located|based|residing)\s+(?:in\s+)?([A-Za-z\s,]+)/gi,
      /address[:\s]+([A-Za-z\s,]+)/gi,
      /([A-Za-z\s]+),\s*([A-Z]{2})\s*\d{5}/gi
    ];

    for (const pattern of locationPatterns) {
      const matches = text.match(pattern);
      if (matches) {
        return matches[0].replace(/(?:live|located|based|residing|address)[:\s]+/gi, '').trim();
      }
    }

    return undefined;
  }

  private extractSkills(text: string): string[] {
    const skills: string[] = [];
    const lowerText = text.toLowerCase();

    // Extract technical skills
    for (const skill of this.skillsKeywords.technical) {
      if (lowerText.includes(skill.toLowerCase())) {
        skills.push(skill);
      }
    }

    // Extract soft skills
    for (const skill of this.skillsKeywords.soft) {
      if (lowerText.includes(skill.toLowerCase())) {
        skills.push(skill);
      }
    }

    // Look for skills sections
    const skillsSectionRegex = /(?:skills|competencies|technologies|tools)[:\s]+([\s\S]*?)(?:\n\n|\n[A-Z]|$)/gi;
    const skillsMatches = text.match(skillsSectionRegex);

    if (skillsMatches) {
      skillsMatches.forEach(match => {
        const skillsText = match.replace(/(?:skills|competencies|technologies|tools)[:\s]+/gi, '');
        const extractedSkills = skillsText
          .split(/[,\n•\-\*]/)
          .map(s => s.trim())
          .filter(s => s.length > 1 && s.length < 30);

        skills.push(...extractedSkills);
      });
    }

    return Array.from(new Set(skills)); // Remove duplicates
  }

  private extractExperience(text: string): any[] {
    const experiences: any[] = [];
    const lines = text.split('\n');

    // Look for experience patterns
    lines.forEach((line, index) => {
      const firstPattern = this.experiencePatterns[0];
      if (!firstPattern) return;

      const timeMatch = line.match(firstPattern);
      if (timeMatch && timeMatch[1] && timeMatch[2]) {
        const startYear = parseInt(timeMatch[1]);
        const endYear = timeMatch[2].toLowerCase().includes('present') ?
          new Date().getFullYear() : parseInt(timeMatch[2]);

        // Look for job title and company in surrounding lines
        const context = lines.slice(Math.max(0, index - 2), index + 3).join(' ');

        experiences.push({
          title: this.extractJobTitle(context),
          company: this.extractCompany(context),
          startDate: `${startYear}`,
          endDate: timeMatch[2].toLowerCase().includes('present') ? 'Present' : `${endYear}`,
          duration: endYear - startYear,
          description: this.extractJobDescription(context)
        });
      }
    });

    return experiences;
  }

  private extractEducation(text: string): any[] {
    const education: any[] = [];
    const educationKeywords = ['university', 'college', 'degree', 'bachelor', 'master', 'phd', 'diploma'];

    const lines = text.split('\n');
    lines.forEach(line => {
      const lowerLine = line.toLowerCase();
      if (educationKeywords.some(keyword => lowerLine.includes(keyword))) {
        education.push({
          degree: this.extractDegree(line),
          institution: this.extractInstitution(line),
          year: this.extractYear(line)
        });
      }
    });

    return education;
  }

  private extractFirstName(text: string): string | undefined {
    const lines = text.split('\n').slice(0, 5); // Check first 5 lines
    for (const line of lines) {
      const words = line.trim().split(/\s+/);
      if (words.length >= 2 && words[0] && words[0].length > 1) {
        return words[0];
      }
    }
    return undefined;
  }

  private extractLastName(text: string): string | undefined {
    const lines = text.split('\n').slice(0, 5); // Check first 5 lines
    for (const line of lines) {
      const words = line.trim().split(/\s+/);
      if (words.length >= 2 && words[1] && words[1].length > 1) {
        return words[1];
      }
    }
    return undefined;
  }

  private analyzeQuality(text: string): QualityAnalysis {
    const sections = this.detectSections(text);

    const details = {
      hasContact: this.hasContactInfo(text),
      hasObjective: sections.includes('objective') || sections.includes('summary'),
      hasExperience: sections.includes('experience') || sections.includes('work'),
      hasEducation: sections.includes('education'),
      hasSkills: sections.includes('skills') || sections.includes('competencies'),
      hasAchievements: sections.includes('achievements') || sections.includes('accomplishments')
    };

    const completeness = Object.values(details).filter(Boolean).length / Object.keys(details).length;
    const clarity = this.analyzeClarity(text);
    const relevance = this.analyzeRelevance(text);
    const formatting = this.analyzeFormatting(text);

    const overall = (completeness + clarity + relevance + formatting) / 4;

    return {
      overall,
      completeness,
      clarity,
      relevance,
      formatting,
      details
    };
  }

  private extractSections(text: string): ExtractedSection[] {
    const sections: ExtractedSection[] = [];
    const sectionHeaders = [
      'experience', 'education', 'skills', 'objective', 'summary',
      'achievements', 'certifications', 'languages'
    ];

    sectionHeaders.forEach(header => {
      const regex = new RegExp(`^\\s*${header}\\s*$`, 'gmi');
      const matches = Array.from(text.matchAll(regex));

      matches.forEach(match => {
        sections.push({
          type: header,
          content: this.extractSectionContent(text, match.index || 0),
          confidence: 0.8,
          startIndex: match.index || 0,
          endIndex: (match.index || 0) + 100 // Simplified
        });
      });
    });

    return sections;
  }

  private extractMetadata(text: string, fileName: string): any {
    return {
      wordCount: text.split(/\s+/).length,
      language: this.detectLanguage(text),
      format: fileName.split('.').pop() || 'unknown'
    };
  }

  private generateAnalysis(
    candidate: Partial<Candidate>,
    quality: QualityAnalysis
  ): any {
    const strengths: string[] = [];
    const weaknesses: string[] = [];
    const recommendations: string[] = [];

    // Analyze strengths
    if (quality.details.hasExperience && candidate.experience?.length) {
      strengths.push('Has relevant work experience');
    }
    if (candidate.skills?.length && candidate.skills.length > 5) {
      strengths.push('Diverse skill set');
    }
    if (quality.details.hasEducation) {
      strengths.push('Educational background provided');
    }

    // Analyze weaknesses
    if (!quality.details.hasContact) {
      weaknesses.push('Missing contact information');
      recommendations.push('Add complete contact details including email and phone');
    }
    if (!quality.details.hasObjective) {
      weaknesses.push('No professional summary or objective');
      recommendations.push('Include a professional summary highlighting key strengths');
    }
    if (quality.formatting < 0.5) {
      weaknesses.push('Poor formatting and structure');
      recommendations.push('Improve document formatting and organization');
    }

    return {
      quality,
      strengths,
      weaknesses,
      recommendations
    };
  }

  private calculateConfidence(candidate: Partial<Candidate>, quality: QualityAnalysis): number {
    let confidence = 0;

    // Base confidence on data extraction success
    if (candidate.email) confidence += 0.2;
    if (candidate.skills?.length) confidence += 0.3;
    if (candidate.experience?.length) confidence += 0.3;
    if (candidate.firstName && candidate.lastName) confidence += 0.2;

    // Adjust based on quality
    confidence *= quality.overall;

    return Math.min(confidence, 1);
  }

  // Helper methods
  private detectSections(text: string): string[] {
    const sections: string[] = [];
    const sectionPatterns = [
      /^(experience|work history)/gmi,
      /^(education|academic)/gmi,
      /^(skills|competencies)/gmi,
      /^(objective|summary)/gmi,
      /^(achievements|accomplishments)/gmi
    ];

    sectionPatterns.forEach(pattern => {
      if (pattern.test(text)) {
        const match = pattern.exec(text);
        if (match && match[1]) sections.push(match[1].toLowerCase());
      }
    });

    return sections;
  }

  private hasContactInfo(text: string): boolean {
    return !!(this.extractEmail(text) || this.extractPhone(text));
  }

  private analyzeClarity(text: string): number {
    const sentences = text.split(/[.!?]+/).filter(s => s.trim().length > 0);
    const avgSentenceLength = sentences.reduce((sum, s) => sum + s.split(' ').length, 0) / sentences.length;

    // Ideal sentence length is 15-20 words
    if (avgSentenceLength >= 15 && avgSentenceLength <= 20) return 1;
    if (avgSentenceLength >= 10 && avgSentenceLength <= 25) return 0.8;
    return 0.5;
  }

  private analyzeRelevance(text: string): number {
    const professionalKeywords = [
      'experience', 'skills', 'project', 'manage', 'develop', 'design',
      'implement', 'lead', 'collaborate', 'achieve', 'deliver'
    ];

    const lowerText = text.toLowerCase();
    const relevantWords = professionalKeywords.filter(keyword =>
      lowerText.includes(keyword)
    ).length;

    return Math.min(relevantWords / professionalKeywords.length, 1);
  }

  private analyzeFormatting(text: string): number {
    let score = 0;

    // Check for consistent formatting
    const hasHeaders = /^[A-Z\s]+$/gm.test(text);
    if (hasHeaders) score += 0.3;

    // Check for bullet points or lists
    const hasBullets = /^[\s]*[•\-\*]/gm.test(text);
    if (hasBullets) score += 0.3;

    // Check for consistent spacing
    const lines = text.split('\n');
    const hasConsistentSpacing = lines.filter(line => line.trim()).length / lines.length > 0.3;
    if (hasConsistentSpacing) score += 0.4;

    return score;
  }

  private extractJobTitle(context: string): string {
    // Simplified job title extraction
    const words = context.split(/\s+/);
    return words.slice(0, 3).join(' ');
  }

  private extractCompany(context: string): string {
    // Simplified company extraction
    const lines = context.split('\n');
    return lines[1] || '';
  }

  private extractJobDescription(context: string): string {
    return context.substring(0, 200);
  }

  private extractDegree(line: string): string {
    const degreePatterns = [
      /bachelor[^\w]*([^,\n]+)/gi,
      /master[^\w]*([^,\n]+)/gi,
      /phd[^\w]*([^,\n]+)/gi,
      /degree[^\w]*([^,\n]+)/gi
    ];

    for (const pattern of degreePatterns) {
      const match = line.match(pattern);
      if (match) return match[0];
    }

    return line.substring(0, 50);
  }

  private extractInstitution(line: string): string {
    // Simplified institution extraction
    return line.split(',')[1]?.trim() || '';
  }

  private extractYear(line: string): string {
    const yearMatch = line.match(/\b(19|20)\d{2}\b/);
    return yearMatch?.[0] || '';
  }

  private extractSectionContent(text: string, startIndex: number): string {
    return text.substring(startIndex, startIndex + 500);
  }

  private detectLanguage(text: string): string {
    const spanishKeywords = ['experiencia', 'educación', 'habilidades', 'trabajo'];
    const englishKeywords = ['experience', 'education', 'skills', 'work'];

    const lowerText = text.toLowerCase();
    const spanishCount = spanishKeywords.filter(word => lowerText.includes(word)).length;
    const englishCount = englishKeywords.filter(word => lowerText.includes(word)).length;

    return spanishCount > englishCount ? 'es' : 'en';
  }
}

export default CVAnalysisService;
