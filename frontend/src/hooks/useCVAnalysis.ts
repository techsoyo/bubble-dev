/*
Características principales:
Análisis individual y por lotes de CVs
Validación de archivos (formato, tamaño)
Comparación entre CVs
Ranking de candidatos
Búsqueda inteligente en CVs
Exportación en múltiples formatos
Estadísticas detalladas
Gestión de progreso en tiempo real
Configuración flexible del análisis
Funciones de utilidad avanzadas
El hook está completamente integrado con el servicio CVAnalysisService y proporciona una interfaz completa para el manejo de análisis de CVs con IA.
*/


import { useState, useCallback, useRef, useMemo } from 'react';
import { CVAnalysisService, CVAnalysisRequest } from '../services/ai/CVAnalysisService';
import { CVAnalysisResult } from '../services/types';

// Interfaces para el hook
interface UseCVAnalysisProps {
  autoAnalyze?: boolean;
  batchProcessing?: boolean;
  maxFileSize?: number; // en MB
  supportedFormats?: string[];
  enhancedAnalysis?: boolean;
  realTimeProcessing?: boolean;
}

interface CVFile {
  id: string;
  file: File;
  name: string;
  size: number;
  type: string;
  uploadedAt: Date;
  status: 'pending' | 'processing' | 'completed' | 'error';
  progress: number;
  result?: CVAnalysisResult;
  error?: string | undefined;
  preview?: string;
}

interface CVAnalysisState {
  files: CVFile[];
  processing: boolean;
  error: string | null;
  totalProcessed: number;
  successCount: number;
  errorCount: number;
  averageProcessingTime: number;
  batchProgress: number;
}

interface CVAnalysisFilters {
  minExperience?: number;
  maxExperience?: number;
  requiredSkills?: string[];
  preferredSkills?: string[];
  education?: string[];
  languages?: string[];
  location?: string[];
  availability?: ('immediate' | 'twoWeeks' | 'month' | 'negotiable')[];
  salaryRange?: {
    min: number;
    max: number;
    currency: string;
  };
  certifications?: string[];
  industries?: string[];
  positions?: string[];
}

interface CVComparisonResult {
  file1: CVFile;
  file2: CVFile;
  similarityScore: number;
  commonSkills: string[];
  uniqueSkills1: string[];
  uniqueSkills2: string[];
  experienceComparison: {
    file1Years: number;
    file2Years: number;
    difference: number;
  };
  strengthComparison: {
    file1Strengths: string[];
    file2Strengths: string[];
    commonStrengths: string[];
  };
  recommendation: string;
}

interface CVAnalysisStats {
  totalFiles: number;
  completedAnalysis: number;
  averageScore: number;
  topSkills: Array<{ skill: string; frequency: number }>;
  experienceDistribution: Array<{ range: string; count: number }>;
  educationLevels: Array<{ level: string; count: number }>;
  languageDistribution: Array<{ language: string; count: number }>;
  locationDistribution: Array<{ location: string; count: number }>;
}

interface UseCVAnalysisReturn {
  // Estado
  files: CVFile[];
  processing: boolean;
  error: string | null;
  stats: CVAnalysisStats;
  batchProgress: number;

  // Acciones principales
  uploadFiles: (files: FileList | File[]) => Promise<void>;
  analyzeCV: (fileId: string, options?: Partial<CVAnalysisRequest>) => Promise<void>;
  analyzeBatch: (fileIds?: string[], options?: Partial<CVAnalysisRequest>) => Promise<void>;
  removeFile: (fileId: string) => void;
  clearAll: () => void;
  retryAnalysis: (fileId: string) => Promise<void>;

  // Funcionalidades avanzadas
  compareCVs: (fileId1: string, fileId2: string) => Promise<CVComparisonResult>;
  rankCVs: (positionRequirements: CVAnalysisFilters) => CVFile[];
  searchCVs: (query: string, filters?: CVAnalysisFilters) => CVFile[];
  exportResults: (format: 'json' | 'csv' | 'pdf') => Promise<string | Blob>;

  // Utilidades
  getFilteredFiles: (filters: CVAnalysisFilters) => CVFile[];
  getTopCandidates: (count: number, criteria?: 'score' | 'experience' | 'skills') => CVFile[];
  generateReport: (fileIds?: string[]) => Promise<{
    summary: string;
    insights: string[];
    recommendations: string[];
    charts: any[];
  }>;

  // Validación y helpers
  validateFile: (file: File) => { valid: boolean; error?: string };
  getFilePreview: (fileId: string) => Promise<string>;
  downloadOriginal: (fileId: string) => void;

  // Estado de configuración
  config: UseCVAnalysisProps;
  updateConfig: (newConfig: Partial<UseCVAnalysisProps>) => void;
}

const useCVAnalysis = ({
  autoAnalyze = true,
  batchProcessing = false,
  maxFileSize = 10, // 10MB por defecto
  supportedFormats = ['pdf', 'doc', 'docx', 'txt'],
  enhancedAnalysis = true,
  realTimeProcessing = false
}: UseCVAnalysisProps = {}): UseCVAnalysisReturn => {

  // Estado principal
  const [state, setState] = useState<CVAnalysisState>({
    files: [],
    processing: false,
    error: null,
    totalProcessed: 0,
    successCount: 0,
    errorCount: 0,
    averageProcessingTime: 0,
    batchProgress: 0
  });

  // Configuración del hook
  const [config, setConfig] = useState({
    autoAnalyze,
    batchProcessing,
    maxFileSize,
    supportedFormats,
    enhancedAnalysis,
    realTimeProcessing
  });

  // Servicio de análisis de CV
  const cvAnalysisService = useMemo(() => new CVAnalysisService(), []);

  // Referencias para tracking de progreso
  const progressRef = useRef<{ [fileId: string]: number }>({});
  const processingTimesRef = useRef<number[]>([]);

  // Función para validar archivo
  const validateFile = useCallback((file: File): { valid: boolean; error?: string } => {
    // Verificar tamaño
    if (file.size > config.maxFileSize * 1024 * 1024) {
      return {
        valid: false,
        error: `El archivo excede el tamaño máximo de ${config.maxFileSize}MB`
      };
    }

    // Verificar formato
    const fileExtension = file.name.split('.').pop()?.toLowerCase();
    if (!fileExtension || !config.supportedFormats.includes(fileExtension)) {
      return {
        valid: false,
        error: `Formato no soportado. Formatos permitidos: ${config.supportedFormats.join(', ')}`
      };
    }

    return { valid: true };
  }, [config.maxFileSize, config.supportedFormats]);

  // Función para subir archivos
  const uploadFiles = useCallback(async (files: FileList | File[]) => {
    const fileArray = Array.from(files);
    const newFiles: CVFile[] = [];

    // Validar y procesar cada archivo
    for (const file of fileArray) {
      const validation = validateFile(file);

      if (!validation.valid) {
        setState(prev => ({ ...prev, error: validation.error! }));
        continue;
      }

      const cvFile: CVFile = {
        id: `cv-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
        file,
        name: file.name,
        size: file.size,
        type: file.type,
        uploadedAt: new Date(),
        status: 'pending',
        progress: 0
      };

      newFiles.push(cvFile);
    }

    // Actualizar estado con nuevos archivos
    setState(prev => ({
      ...prev,
      files: [...prev.files, ...newFiles],
      error: null
    }));

    // Auto-analizar si está habilitado
    if (config.autoAnalyze) {
      for (const cvFile of newFiles) {
        await analyzeCV(cvFile.id);
      }
    }
  }, [config.autoAnalyze, validateFile]);

  // Función para analizar un CV individual
  const analyzeCV = useCallback(async (
    fileId: string,
    options: Partial<CVAnalysisRequest> = {}
  ) => {
    const file = state.files.find(f => f.id === fileId);
    if (!file) return;

    const startTime = Date.now();

    // Actualizar estado del archivo
    setState(prev => ({
      ...prev,
      files: prev.files.map(f =>
        f.id === fileId
          ? { ...f, status: 'processing', progress: 0, error: undefined }
          : f
      ),
      processing: true,
      error: null
    }));

    try {
      // Preparar request para el análisis
      const analysisRequest: CVAnalysisRequest = {
        fileContent: await file.file.text(),
        fileName: file.file.name,
        fileType: file.file.type.includes('pdf') ? 'pdf' : 'txt',
        options: {
          extractSkills: true,
          extractExperience: true,
          extractEducation: true,
          extractContact: true,
          analyzeQuality: config.enhancedAnalysis,
          ...options
        }
      };

      // Simular progreso durante el análisis
      const progressInterval = setInterval(() => {
        setState(prev => ({
          ...prev,
          files: prev.files.map(f =>
            f.id === fileId && f.status === 'processing'
              ? { ...f, progress: Math.min(f.progress + Math.random() * 20, 90) }
              : f
          )
        }));
      }, 500);

      // Realizar análisis
      const result = await cvAnalysisService.analyzeCV(analysisRequest);

      clearInterval(progressInterval);

      // Calcular tiempo de procesamiento
      const processingTime = Date.now() - startTime;
      processingTimesRef.current.push(processingTime);

      // Actualizar estado con resultado
      setState(prev => {
        const newSuccessCount = prev.successCount + 1;
        const newTotalProcessed = prev.totalProcessed + 1;
        const newAverageTime = processingTimesRef.current.reduce((a, b) => a + b, 0) / processingTimesRef.current.length;

        return {
          ...prev,
          files: prev.files.map(f =>
            f.id === fileId
              ? {
                ...f,
                status: 'completed',
                progress: 100,
                result,
                error: undefined
              }
              : f
          ),
          processing: prev.files.filter(f => f.status === 'processing').length <= 1,
          successCount: newSuccessCount,
          totalProcessed: newTotalProcessed,
          averageProcessingTime: newAverageTime
        };
      });

    } catch (error) {
      setState(prev => ({
        ...prev,
        files: prev.files.map(f =>
          f.id === fileId
            ? {
              ...f,
              status: 'error',
              progress: 0,
              error: error instanceof Error ? error.message : 'Error de análisis'
            }
            : f
        ),
        processing: prev.files.filter(f => f.status === 'processing').length <= 1,
        errorCount: prev.errorCount + 1,
        totalProcessed: prev.totalProcessed + 1
      }));
    }
  }, [state.files, config.enhancedAnalysis, cvAnalysisService]);

  // Función para análisis en lote
  const analyzeBatch = useCallback(async (
    fileIds?: string[],
    options: Partial<CVAnalysisRequest> = {}
  ) => {
    const filesToAnalyze = fileIds
      ? state.files.filter(f => fileIds.includes(f.id) && f.status === 'pending')
      : state.files.filter(f => f.status === 'pending');

    if (filesToAnalyze.length === 0) return;

    setState(prev => ({ ...prev, processing: true, batchProgress: 0 }));

    // Procesar archivos secuencialmente o en paralelo según configuración
    if (config.batchProcessing) {
      // Procesamiento en lotes pequeños para evitar sobrecarga
      const batchSize = 3;
      const batches = [];

      for (let i = 0; i < filesToAnalyze.length; i += batchSize) {
        batches.push(filesToAnalyze.slice(i, i + batchSize));
      }

      for (let i = 0; i < batches.length; i++) {
        const batch = batches[i];

        // Procesar lote en paralelo
        await Promise.all(
          batch.map(file => analyzeCV(file.id, options))
        );

        // Actualizar progreso del lote
        const progress = ((i + 1) / batches.length) * 100;
        setState(prev => ({ ...prev, batchProgress: progress }));
      }
    } else {
      // Procesamiento secuencial
      for (let i = 0; i < filesToAnalyze.length; i++) {
        await analyzeCV(filesToAnalyze[i].id, options);

        const progress = ((i + 1) / filesToAnalyze.length) * 100;
        setState(prev => ({ ...prev, batchProgress: progress }));
      }
    }

    setState(prev => ({ ...prev, processing: false, batchProgress: 100 }));
  }, [state.files, config.batchProcessing, analyzeCV]);

  // Función para comparar CVs
  const compareCVs = useCallback(async (
    fileId1: string,
    fileId2: string
  ): Promise<CVComparisonResult> => {
    const file1 = state.files.find(f => f.id === fileId1);
    const file2 = state.files.find(f => f.id === fileId2);

    if (!file1?.result || !file2?.result) {
      throw new Error('Ambos CVs deben estar analizados para poder compararlos');
    }

    // Análisis de similitud
    const skills1 = file1.result.extractedData.skills;
    const skills2 = file2.result.extractedData.skills;
    const commonSkills = skills1.filter(skill => skills2.includes(skill));
    const uniqueSkills1 = skills1.filter(skill => !skills2.includes(skill));
    const uniqueSkills2 = skills2.filter(skill => !skills1.includes(skill));

    const similarityScore = (commonSkills.length * 2) / (skills1.length + skills2.length) * 100;

    // Comparación de experiencia
    const exp1 = file1.result.extractedData.totalExperience;
    const exp2 = file2.result.extractedData.totalExperience;

    return {
      file1,
      file2,
      similarityScore: Math.round(similarityScore),
      commonSkills,
      uniqueSkills1,
      uniqueSkills2,
      experienceComparison: {
        file1Years: exp1,
        file2Years: exp2,
        difference: Math.abs(exp1 - exp2)
      },
      strengthComparison: {
        file1Strengths: file1.result.aiAnalysis.strengths,
        file2Strengths: file2.result.aiAnalysis.strengths,
        commonStrengths: file1.result.aiAnalysis.strengths.filter(s =>
          file2.result!.aiAnalysis.strengths.includes(s)
        )
      },
      recommendation: similarityScore > 70
        ? 'Candidatos muy similares, considerar diferenciadores únicos'
        : 'Candidatos complementarios, evaluar según requisitos específicos'
    };
  }, [state.files]);

  // Función para ranking de CVs
  const rankCVs = useCallback((positionRequirements: CVAnalysisFilters): CVFile[] => {
    return state.files
      .filter(f => f.status === 'completed' && f.result)
      .map(file => {
        let score = 0;
        const data = file.result!.extractedData;

        // Puntuación por habilidades requeridas
        if (positionRequirements.requiredSkills) {
          const matchedRequired = data.skills.filter(skill =>
            positionRequirements.requiredSkills!.includes(skill)
          ).length;
          score += (matchedRequired / positionRequirements.requiredSkills.length) * 40;
        }

        // Puntuación por habilidades preferidas
        if (positionRequirements.preferredSkills) {
          const matchedPreferred = data.skills.filter(skill =>
            positionRequirements.preferredSkills!.includes(skill)
          ).length;
          score += (matchedPreferred / positionRequirements.preferredSkills.length) * 20;
        }

        // Puntuación por experiencia
        if (positionRequirements.minExperience) {
          if (data.totalExperience >= positionRequirements.minExperience) {
            score += 20;
          }
        }

        // Puntuación por educación
        if (positionRequirements.education) {
          const hasRequiredEducation = data.education.some(edu =>
            positionRequirements.education!.includes(edu.degree)
          );
          if (hasRequiredEducation) score += 20;
        }

        return { ...file, calculatedScore: Math.round(score) };
      })
      .sort((a, b) => (b as any).calculatedScore - (a as any).calculatedScore);
  }, [state.files]);

  // Función para búsqueda de CVs
  const searchCVs = useCallback((query: string, filters?: CVAnalysisFilters): CVFile[] => {
    const searchTerms = query.toLowerCase().split(' ');

    return state.files
      .filter(f => f.status === 'completed' && f.result)
      .filter(file => {
        const data = file.result!.extractedData;
        const searchableContent = [
          data.personalInfo.name,
          data.personalInfo.email,
          ...data.skills,
          ...data.education.map(e => e.institution + ' ' + e.degree),
          ...data.workExperience.map(w => w.company + ' ' + w.position),
          data.summary
        ].join(' ').toLowerCase();

        // Verificar si todos los términos de búsqueda están presentes
        const matchesQuery = searchTerms.every(term =>
          searchableContent.includes(term)
        );

        if (!matchesQuery) return false;

        // Aplicar filtros adicionales
        if (filters) {
          if (filters.minExperience && data.totalExperience < filters.minExperience) return false;
          if (filters.maxExperience && data.totalExperience > filters.maxExperience) return false;
          if (filters.requiredSkills && !filters.requiredSkills.every(skill => data.skills.includes(skill))) return false;
          if (filters.education && !data.education.some(edu => filters.education!.includes(edu.degree))) return false;
          if (filters.languages && !filters.languages.every(lang => data.languages.includes(lang))) return false;
        }

        return true;
      });
  }, [state.files]);

  // Función para exportar resultados
  const exportResults = useCallback(async (format: 'json' | 'csv' | 'pdf'): Promise<string | Blob> => {
    const completedFiles = state.files.filter(f => f.status === 'completed' && f.result);

    if (format === 'json') {
      const data = completedFiles.map(file => ({
        fileName: file.name,
        analysisDate: file.result!.metadata.analysisDate,
        score: file.result!.overallScore,
        extractedData: file.result!.extractedData,
        aiAnalysis: file.result!.aiAnalysis
      }));
      return JSON.stringify(data, null, 2);
    }

    if (format === 'csv') {
      const headers = [
        'Archivo', 'Nombre', 'Email', 'Teléfono', 'Experiencia Total',
        'Habilidades', 'Educación', 'Score Global', 'Fecha Análisis'
      ];

      const rows = completedFiles.map(file => {
        const data = file.result!.extractedData;
        return [
          file.name,
          data.personalInfo.name,
          data.personalInfo.email,
          data.personalInfo.phone,
          data.totalExperience,
          data.skills.join('; '),
          data.education.map(e => `${e.degree} - ${e.institution}`).join('; '),
          file.result!.overallScore,
          file.result!.metadata.analysisDate.toLocaleDateString()
        ].map(field => `"${String(field).replace(/"/g, '""')}"`);
      });

      return [headers.join(','), ...rows.map(row => row.join(','))].join('\n');
    }

    // Para PDF, retornar un Blob (implementación simplificada)
    const pdfContent = completedFiles.map(file =>
      `CV: ${file.name}\nScore: ${file.result!.overallScore}\nHabilidades: ${file.result!.extractedData.skills.join(', ')}\n\n`
    ).join('');

    return new Blob([pdfContent], { type: 'application/pdf' });
  }, [state.files]);

  // Funciones de utilidad adicionales
  const removeFile = useCallback((fileId: string) => {
    setState(prev => ({
      ...prev,
      files: prev.files.filter(f => f.id !== fileId)
    }));
  }, []);

  const clearAll = useCallback(() => {
    setState({
      files: [],
      processing: false,
      error: null,
      totalProcessed: 0,
      successCount: 0,
      errorCount: 0,
      averageProcessingTime: 0,
      batchProgress: 0
    });
    progressRef.current = {};
    processingTimesRef.current = [];
  }, []);

  const retryAnalysis = useCallback(async (fileId: string) => {
    setState(prev => ({
      ...prev,
      files: prev.files.map(f =>
        f.id === fileId
          ? { ...f, status: 'pending', progress: 0, error: undefined, result: undefined }
          : f
      )
    }));

    await analyzeCV(fileId);
  }, [analyzeCV]);

  // Estadísticas calculadas
  const stats = useMemo((): CVAnalysisStats => {
    const completedFiles = state.files.filter(f => f.status === 'completed' && f.result);

    if (completedFiles.length === 0) {
      return {
        totalFiles: state.files.length,
        completedAnalysis: 0,
        averageScore: 0,
        topSkills: [],
        experienceDistribution: [],
        educationLevels: [],
        languageDistribution: [],
        locationDistribution: []
      };
    }

    // Recopilar todas las habilidades
    const allSkills: string[] = [];
    const experienceYears: number[] = [];
    const educationLevels: string[] = [];
    const languages: string[] = [];
    const locations: string[] = [];
    let totalScore = 0;

    completedFiles.forEach(file => {
      const data = file.result!.extractedData;
      allSkills.push(...data.skills);
      experienceYears.push(data.totalExperience);
      educationLevels.push(...data.education.map(e => e.degree));
      languages.push(...data.languages);
      if (data.personalInfo.location) {
        locations.push(data.personalInfo.location);
      }
      totalScore += file.result!.overallScore;
    });

    // Calcular frecuencias
    const skillFreq = allSkills.reduce((acc, skill) => {
      acc[skill] = (acc[skill] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    const eduFreq = educationLevels.reduce((acc, edu) => {
      acc[edu] = (acc[edu] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    const langFreq = languages.reduce((acc, lang) => {
      acc[lang] = (acc[lang] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    const locFreq = locations.reduce((acc, loc) => {
      acc[loc] = (acc[loc] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    // Distribución de experiencia
    const expDistribution = [
      { range: '0-2 años', count: experienceYears.filter(exp => exp <= 2).length },
      { range: '3-5 años', count: experienceYears.filter(exp => exp >= 3 && exp <= 5).length },
      { range: '6-10 años', count: experienceYears.filter(exp => exp >= 6 && exp <= 10).length },
      { range: '10+ años', count: experienceYears.filter(exp => exp > 10).length }
    ];

    return {
      totalFiles: state.files.length,
      completedAnalysis: completedFiles.length,
      averageScore: Math.round(totalScore / completedFiles.length),
      topSkills: Object.entries(skillFreq)
        .sort(([, a], [, b]) => b - a)
        .slice(0, 10)
        .map(([skill, frequency]) => ({ skill, frequency })),
      experienceDistribution: expDistribution,
      educationLevels: Object.entries(eduFreq)
        .sort(([, a], [, b]) => b - a)
        .map(([level, count]) => ({ level, count })),
      languageDistribution: Object.entries(langFreq)
        .sort(([, a], [, b]) => b - a)
        .slice(0, 5)
        .map(([language, count]) => ({ language, count })),
      locationDistribution: Object.entries(locFreq)
        .sort(([, a], [, b]) => b - a)
        .slice(0, 5)
        .map(([location, count]) => ({ location, count }))
    };
  }, [state.files]);

  // Funciones auxiliares adicionales
  const getFilteredFiles = useCallback((filters: CVAnalysisFilters): CVFile[] => {
    return searchCVs('', filters);
  }, [searchCVs]);

  const getTopCandidates = useCallback((
    count: number,
    criteria: 'score' | 'experience' | 'skills' = 'score'
  ): CVFile[] => {
    const completedFiles = state.files.filter(f => f.status === 'completed' && f.result);

    return completedFiles
      .sort((a, b) => {
        switch (criteria) {
          case 'score':
            return b.result!.overallScore - a.result!.overallScore;
          case 'experience':
            return b.result!.extractedData.totalExperience - a.result!.extractedData.totalExperience;
          case 'skills':
            return b.result!.extractedData.skills.length - a.result!.extractedData.skills.length;
          default:
            return 0;
        }
      })
      .slice(0, count);
  }, [state.files]);

  const generateReport = useCallback(async (fileIds?: string[]) => {
    const filesToReport = fileIds
      ? state.files.filter(f => fileIds.includes(f.id) && f.status === 'completed')
      : state.files.filter(f => f.status === 'completed');

    const summary = `Análisis completado de ${filesToReport.length} CVs con un score promedio de ${stats.averageScore}`;

    const insights = [
      `Habilidad más demandada: ${stats.topSkills[0]?.skill || 'N/A'}`,
      `Experiencia promedio: ${stats.experienceDistribution.find(d => d.count > 0)?.range || 'N/A'}`,
      `Nivel educativo más común: ${stats.educationLevels[0]?.level || 'N/A'}`
    ];

    const recommendations = [
      'Considerar diversificar fuentes de candidatos para mayor variedad de perfiles',
      'Enfocar búsquedas en las habilidades más demandadas identificadas',
      'Implementar procesos de screening basados en los patrones encontrados'
    ];

    return {
      summary,
      insights,
      recommendations,
      charts: [
        { type: 'skills', data: stats.topSkills },
        { type: 'experience', data: stats.experienceDistribution },
        { type: 'education', data: stats.educationLevels }
      ]
    };
  }, [state.files, stats]);

  const getFilePreview = useCallback(async (fileId: string): Promise<string> => {
    const file = state.files.find(f => f.id === fileId);
    if (!file) throw new Error('Archivo no encontrado');

    // Simular generación de preview
    return `Preview del archivo: ${file.name}`;
  }, [state.files]);

  const downloadOriginal = useCallback((fileId: string) => {
    const file = state.files.find(f => f.id === fileId);
    if (!file) return;

    const url = URL.createObjectURL(file.file);
    const a = document.createElement('a');
    a.href = url;
    a.download = file.name;
    a.click();
    URL.revokeObjectURL(url);
  }, [state.files]);

  const updateConfig = useCallback((newConfig: Partial<UseCVAnalysisProps>) => {
    setConfig(prev => ({ ...prev, ...newConfig }));
  }, []);

  return {
    // Estado
    files: state.files,
    processing: state.processing,
    error: state.error,
    stats,
    batchProgress: state.batchProgress,

    // Acciones principales
    uploadFiles,
    analyzeCV,
    analyzeBatch,
    removeFile,
    clearAll,
    retryAnalysis,

    // Funcionalidades avanzadas
    compareCVs,
    rankCVs,
    searchCVs,
    exportResults,

    // Utilidades
    getFilteredFiles,
    getTopCandidates,
    generateReport,

    // Validación y helpers
    validateFile,
    getFilePreview,
    downloadOriginal,

    // Configuración
    config,
    updateConfig
  };
};

export default useCVAnalysis;

// Tipos exportados
export type {
  UseCVAnalysisProps,
  CVFile,
  CVAnalysisFilters,
  CVComparisonResult,
  CVAnalysisStats,
  UseCVAnalysisReturn
};