import { useState, useEffect, useCallback, useMemo } from 'react';
import { RecommendationEngine, RecommendationRequest, RecommendationResult, RecommendationFilters } from '../services/ai/RecommendationEngine';

// Interfaces para el hook
interface UseAIRecommendationsProps {
  candidateId?: string;
  positionId?: string;
  autoRefresh?: boolean;
  refreshInterval?: number;
  maxRecommendations?: number;
  filters?: RecommendationFilters;
}

interface RecommendationState {
  recommendations: RecommendationResult[];
  loading: boolean;
  error: string | null;
  lastUpdated: Date | null;
  hasMore: boolean;
  totalCount: number;
  currentPage: number;
}

interface RecommendationStats {
  total: number;
  avgScore: number;
  categories: string[];
  highScore: number;
  mediumScore: number;
  lowScore: number;
  bookmarked: number;
  dismissed: number;
  applied: number;
}

interface UseAIRecommendationsReturn {
  // Estado
  recommendations: RecommendationResult[];
  loading: boolean;
  error: string | null;
  lastUpdated: Date | null;
  hasMore: boolean;
  totalCount: number;
  currentPage: number;

  // Acciones
  refreshRecommendations: () => Promise<void>;
  loadMoreRecommendations: () => Promise<void>;
  applyFilters: (filters: RecommendationFilters) => void;
  clearFilters: () => void;
  bookmarkRecommendation: (recommendationId: string) => void;
  dismissRecommendation: (recommendationId: string) => void;
  applyToRecommendation: (recommendationId: string) => void;

  // Utilidades
  getRecommendationsByCategory: (category: string) => RecommendationResult[];
  getTopRecommendations: (count: number) => RecommendationResult[];
  exportRecommendations: (format: 'json' | 'csv') => string;
  resetRecommendations: () => void;

  // Estadísticas
  stats: RecommendationStats;
  bookmarkedIds: string[];
  dismissedIds: string[];
  appliedIds: string[];

  // Filtros activos
  activeFilters: RecommendationFilters;
  hasActiveFilters: boolean;
}

const useAIRecommendations = ({
  candidateId,
  positionId,
  autoRefresh = false,
  refreshInterval = 300000, // 5 minutos por defecto
  maxRecommendations = 50,
  filters: initialFilters = {}
}: UseAIRecommendationsProps = {}): UseAIRecommendationsReturn => {

  // Estado principal
  const [state, setState] = useState<RecommendationState>({
    recommendations: [],
    loading: false,
    error: null,
    lastUpdated: null,
    hasMore: true,
    totalCount: 0,
    currentPage: 1
  });

  // Filtros activos
  const [filters, setFilters] = useState<RecommendationFilters>(initialFilters);

  // Recomendaciones marcadas, descartadas y aplicadas
  const [bookmarkedIds, setBookmarkedIds] = useState<Set<string>>(new Set());
  const [dismissedIds, setDismissedIds] = useState<Set<string>>(new Set());
  const [appliedIds, setAppliedIds] = useState<Set<string>>(new Set());

  // Instancia del motor de recomendaciones
  const recommendationEngine = useMemo(() => new RecommendationEngine(), []);

  // Función para cargar recomendaciones
  const loadRecommendations = useCallback(async (
    append: boolean = false,
    customFilters?: RecommendationFilters
  ) => {
    if (!candidateId && !positionId) {
      setState(prev => ({ ...prev, error: 'Se requiere candidateId o positionId' }));
      return;
    }

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const request: RecommendationRequest = {
        candidateId,
        filters: customFilters || filters,
        limit: maxRecommendations
      };

      const result = await recommendationEngine.getJobRecommendations(candidateId, request);

      setState(prev => ({
        ...prev,
        recommendations: append ? [...prev.recommendations, ...result] : result,
        loading: false,
        lastUpdated: new Date(),
        hasMore: result.length >= maxRecommendations,
        totalCount: result.length,
        currentPage: append ? prev.currentPage + 1 : 1
      }));

    } catch (error) {
      setState(prev => ({
        ...prev,
        loading: false,
        error: error instanceof Error ? error.message : 'Error al cargar recomendaciones'
      }));
    }
  }, [candidateId, positionId, maxRecommendations, filters, dismissedIds, recommendationEngine, state.currentPage]);

  // Función para refrescar recomendaciones
  const refreshRecommendations = useCallback(async () => {
    setState(prev => ({ ...prev, currentPage: 1 }));
    await loadRecommendations(false);
  }, [loadRecommendations]);

  // Función para cargar más recomendaciones
  const loadMoreRecommendations = useCallback(async () => {
    if (state.hasMore && !state.loading) {
      await loadRecommendations(true);
    }
  }, [loadRecommendations, state.hasMore, state.loading]);

  // Función para aplicar filtros
  const applyFilters = useCallback((newFilters: RecommendationFilters) => {
    setFilters(prev => ({ ...prev, ...newFilters }));
    setState(prev => ({ ...prev, currentPage: 1 }));
  }, []);

  // Función para limpiar filtros
  const clearFilters = useCallback(() => {
    setFilters({});
    setState(prev => ({ ...prev, currentPage: 1 }));
  }, []);

  // Función para resetear recomendaciones
  const resetRecommendations = useCallback(() => {
    setState({
      recommendations: [],
      loading: false,
      error: null,
      lastUpdated: null,
      hasMore: true,
      totalCount: 0,
      currentPage: 1
    });
    setBookmarkedIds(new Set());
    setDismissedIds(new Set());
    setAppliedIds(new Set());
  }, []);

  // Función para marcar recomendación
  const bookmarkRecommendation = useCallback((recommendationId: string) => {
    setBookmarkedIds(prev => {
      const newSet = new Set(prev);
      if (newSet.has(recommendationId)) {
        newSet.delete(recommendationId);
      } else {
        newSet.add(recommendationId);
      }
      return newSet;
    });

    // Persistir en localStorage
    const bookmarks = JSON.parse(localStorage.getItem('ai-bookmarks') || '[]');
    const isBookmarked = bookmarks.includes(recommendationId);

    if (isBookmarked) {
      localStorage.setItem('ai-bookmarks', JSON.stringify(
        bookmarks.filter((id: string) => id !== recommendationId)
      ));
    } else {
      localStorage.setItem('ai-bookmarks', JSON.stringify([...bookmarks, recommendationId]));
    }
  }, []);

  // Función para descartar recomendación
  const dismissRecommendation = useCallback((recommendationId: string) => {
    setDismissedIds(prev => new Set([...prev, recommendationId]));
    setState(prev => ({
      ...prev,
      recommendations: prev.recommendations.filter(r => r.id !== recommendationId)
    }));

    // Persistir en localStorage
    const dismissed = JSON.parse(localStorage.getItem('ai-dismissed') || '[]');
    localStorage.setItem('ai-dismissed', JSON.stringify([...dismissed, recommendationId]));
  }, []);

  // Función para aplicar a recomendación
  const applyToRecommendation = useCallback((recommendationId: string) => {
    setAppliedIds(prev => new Set([...prev, recommendationId]));

    // Persistir en localStorage
    const applied = JSON.parse(localStorage.getItem('ai-applied') || '[]');
    localStorage.setItem('ai-applied', JSON.stringify([...applied, recommendationId]));
  }, []);

  // Función para obtener recomendaciones por categoría
  const getRecommendationsByCategory = useCallback((category: string) => {
    return state.recommendations.filter(rec => rec.type === category);
  }, [state.recommendations]);

  // Función para obtener top recomendaciones
  const getTopRecommendations = useCallback((count: number) => {
    return state.recommendations
      .sort((a, b) => b.score - a.score)
      .slice(0, count);
  }, [state.recommendations]);

  // Función para obtener el título de una recomendación
  const getRecommendationTitle = useCallback((rec: RecommendationResult): string => {
    if (rec.type === 'job' && 'title' in rec.data) {
      return rec.data.title;
    } else if ((rec.type === 'candidate' || rec.type === 'company') && 'name' in rec.data) {
      return rec.data.name;
    }
    return 'Sin título';
  }, []);

  // Función para exportar recomendaciones
  const exportRecommendations = useCallback((format: 'json' | 'csv') => {
    const data = state.recommendations.map(rec => ({
      id: rec.id,
      title: getRecommendationTitle(rec),
      score: rec.score,
      category: rec.type,
      reasons: rec.reasons.join('; '),
      confidence: rec.confidence,
      lastUpdated: new Date(),
      bookmarked: bookmarkedIds.has(rec.id),
      dismissed: dismissedIds.has(rec.id),
      applied: appliedIds.has(rec.id)
    }));

    if (format === 'json') {
      return JSON.stringify(data, null, 2);
    } else {
      // Formato CSV
      const headers = Object.keys(data[0] || {});
      const csvContent = [
        headers.join(','),
        ...data.map(row => headers.map(header =>
          `"${String(row[header as keyof typeof row]).replace(/"/g, '""')}"`
        ).join(','))
      ].join('\n');
      return csvContent;
    }
  }, [state.recommendations, bookmarkedIds, dismissedIds, appliedIds]);

  // Recomendaciones filtradas
  const filteredRecommendations = useMemo(() => {
    let filtered = state.recommendations.filter(rec => !dismissedIds.has(rec.id));

    // Aplicar filtros adicionales del lado cliente usando las propiedades existentes
    // Filtrar por score (como proxy para minScore/maxScore)
    filtered = filtered.filter(rec => rec.score >= 0.1); // Score mínimo básico

    // Filtrar por tipo si es necesario
    filtered = filtered.filter(rec => rec.type === 'job' || rec.type === 'candidate' || rec.type === 'company');

    return filtered.sort((a, b) => {
      // Priorizar bookmarked
      if (bookmarkedIds.has(a.id) && !bookmarkedIds.has(b.id)) return -1;
      if (!bookmarkedIds.has(a.id) && bookmarkedIds.has(b.id)) return 1;
      // Luego por score
      return b.score - a.score;
    });
  }, [state.recommendations, dismissedIds, filters, appliedIds, bookmarkedIds]);

  // Estadísticas calculadas
  const stats = useMemo((): RecommendationStats => {
    const total = filteredRecommendations.length;
    const avgScore = total > 0
      ? filteredRecommendations.reduce((sum, rec) => sum + rec.score, 0) / total
      : 0;

    return {
      total,
      avgScore: Math.round(avgScore * 100) / 100,
      categories: [...new Set(filteredRecommendations.map(rec => rec.type))],
      highScore: filteredRecommendations.filter(rec => rec.score >= 80).length,
      mediumScore: filteredRecommendations.filter(rec => rec.score >= 60 && rec.score < 80).length,
      lowScore: filteredRecommendations.filter(rec => rec.score < 60).length,
      bookmarked: filteredRecommendations.filter(rec => bookmarkedIds.has(rec.id)).length,
      dismissed: dismissedIds.size,
      applied: appliedIds.size
    };
  }, [filteredRecommendations, bookmarkedIds, dismissedIds, appliedIds]);

  // Verificar si hay filtros activos
  const hasActiveFilters = useMemo(() => {
    return Object.keys(filters).length > 0;
  }, [filters]);

  // Cargar datos persistidos al inicializar
  useEffect(() => {
    const bookmarks = JSON.parse(localStorage.getItem('ai-bookmarks') || '[]');
    const dismissed = JSON.parse(localStorage.getItem('ai-dismissed') || '[]');
    const applied = JSON.parse(localStorage.getItem('ai-applied') || '[]');

    setBookmarkedIds(new Set(bookmarks));
    setDismissedIds(new Set(dismissed));
    setAppliedIds(new Set(applied));
  }, []);

  // Efecto para carga inicial
  useEffect(() => {
    if (candidateId || positionId) {
      loadRecommendations();
    }
  }, [candidateId, positionId]);

  // Efecto para auto-refresh
  useEffect(() => {
    if (autoRefresh && refreshInterval && refreshInterval > 0) {
      const interval = setInterval(() => {
        refreshRecommendations();
      }, refreshInterval);

      return () => clearInterval(interval);
    }
    return undefined;
  }, [autoRefresh, refreshInterval, refreshRecommendations]);

  // Efecto para recargar cuando cambian los filtros
  useEffect(() => {
    if (filters && Object.keys(filters).length > 0) {
      loadRecommendations(false, filters);
    }
  }, [filters, loadRecommendations]);

  return {
    // Estado
    recommendations: filteredRecommendations,
    loading: state.loading,
    error: state.error,
    lastUpdated: state.lastUpdated,
    hasMore: state.hasMore,
    totalCount: state.totalCount,
    currentPage: state.currentPage,

    // Acciones
    refreshRecommendations,
    loadMoreRecommendations,
    applyFilters,
    clearFilters,
    bookmarkRecommendation,
    dismissRecommendation,
    applyToRecommendation,
    resetRecommendations,

    // Utilidades
    getRecommendationsByCategory,
    getTopRecommendations,
    exportRecommendations,

    // Estadísticas
    stats,
    bookmarkedIds: Array.from(bookmarkedIds),
    dismissedIds: Array.from(dismissedIds),
    appliedIds: Array.from(appliedIds),

    // Filtros
    activeFilters: filters,
    hasActiveFilters
  };
};

export default useAIRecommendations;

// Hook especializado para candidatos
export const useAICandidateRecommendations = (
  candidateId: string,
  options?: Omit<UseAIRecommendationsProps, 'candidateId'>
) => {
  return useAIRecommendations({ ...options, candidateId });
};

// Hook especializado para posiciones
export const useAIPositionRecommendations = (
  positionId: string,
  options?: Omit<UseAIRecommendationsProps, 'positionId'>
) => {
  return useAIRecommendations({ ...options, positionId });
};

// Hook para recomendaciones generales
export const useAIGeneralRecommendations = (
  options?: Omit<UseAIRecommendationsProps, 'candidateId' | 'positionId'>
) => {
  return useAIRecommendations(options);
};

// Tipos exportados
export type {
  UseAIRecommendationsProps,
  RecommendationFilters,
  UseAIRecommendationsReturn,
  RecommendationStats
};
