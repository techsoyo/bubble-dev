import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { IntelligentMatching } from '../services/ai/IntelligentMatching';
import ApiService from '@/services/ApiService';
import { isProduction } from '@/config/env';

// Interfaces para el hook
interface UseIntelligentSearchProps {
  searchType: 'candidates' | 'positions' | 'companies';
  autoSearch?: boolean;
  debounceDelay?: number;
  maxResults?: number;
  enableSemanticSearch?: boolean;
  enableFacetedSearch?: boolean;
  cacheResults?: boolean;
  /**
   * Contexto opcional para matching IA: si se provee, se usará como jobId
   * para enriquecer resultados de candidatos con IntelligentMatching
   */
  contextJobId?: string;
}

interface SearchFilters {
  // Filtros generales
  query?: string;
  location?: string;
  remote?: boolean;
  dateRange?: {
    start: Date;
    end: Date;
  };

  // Contexto opcional para IA
  jobId?: string;

  // Filtros para candidatos
  skills?: string[];
  experience?: {
    min: number;
    max: number;
  };
  education?: string[];
  certifications?: string[];
  languages?: string[];
  availability?: 'immediate' | 'twoWeeks' | 'month' | 'negotiable';
  salaryExpectation?: {
    min: number;
    max: number;
    currency: string;
  };

  // Filtros para posiciones
  department?: string[];
  level?: ('entry' | 'mid' | 'senior' | 'lead' | 'executive')[];
  employmentType?: ('fullTime' | 'partTime' | 'contract' | 'internship')[];
  industry?: string[];
  companySize?: ('startup' | 'small' | 'medium' | 'large' | 'enterprise')[];
  benefits?: string[];

  // Filtros avanzados de IA
  culturalFit?: number;
  riskTolerance?: 'low' | 'medium' | 'high';
  innovationLevel?: number;
  teamworkScore?: number;
  leadershipPotential?: number;
}

interface SearchResult {
  id: string;
  type: 'candidate' | 'position' | 'company';
  title: string;
  subtitle?: string;
  description: string;
  score: number;
  relevanceScore: number;
  culturalFitScore?: number;
  metadata: Record<string, any>;
  highlights: string[];
  avatar?: string;
  tags: string[];
  lastUpdated: Date;
  aiInsights?: {
    matchReason: string;
    strengthAreas: string[];
    concerns: string[];
    recommendations: string[];
  };
}

interface SearchFacet {
  field: string;
  label: string;
  values: Array<{
    value: string;
    label: string;
    count: number;
    selected: boolean;
  }>;
}

interface SearchSuggestion {
  text: string;
  type: 'query' | 'skill' | 'location' | 'company';
  count?: number;
}

interface SearchState {
  results: SearchResult[];
  facets: SearchFacet[];
  suggestions: SearchSuggestion[];
  loading: boolean;
  error: string | null;
  totalCount: number;
  hasMore: boolean;
  currentPage: number;
  searchTime: number;
  lastQuery: string;
}

interface UseIntelligentSearchReturn {
  // Estado
  results: SearchResult[];
  facets: SearchFacet[];
  suggestions: SearchSuggestion[];
  loading: boolean;
  error: string | null;
  totalCount: number;
  hasMore: boolean;
  currentPage: number;
  searchTime: number;
  lastQuery: string;

  // Acciones
  search: (query: string, filters?: SearchFilters) => Promise<void>;
  loadMore: () => Promise<void>;
  applyFilters: (filters: SearchFilters) => Promise<void>;
  clearFilters: () => void;
  clearSearch: () => void;
  retrySearch: () => Promise<void>;

  // Utilidades
  exportResults: (format: 'json' | 'csv') => string;
  saveSearch: (name: string) => void;
  getSavedSearches: () => Array<{ name: string; query: string; filters: SearchFilters; date: Date }>;
  deleteSavedSearch: (name: string) => void;

  // Estado de filtros
  activeFilters: SearchFilters;
  hasActiveFilters: boolean;

  // Funcionalidades avanzadas
  semanticSearchEnabled: boolean;
  toggleSemanticSearch: () => void;
  getSearchInsights: () => {
    topSkills: string[];
    commonLocations: string[];
    averageScore: number;
    searchTrends: Array<{ term: string; frequency: number }>;
  };
}

type NormalizedSearchResponse = {
  results: SearchResult[];
  facets: SearchFacet[];
  suggestions: SearchSuggestion[];
  totalCount: number;
  hasMore: boolean;
};

const useIntelligentSearch = ({
  searchType,
  autoSearch = false,
  debounceDelay = 300,
  maxResults = 50,
  enableSemanticSearch = true,
  enableFacetedSearch = true,
  cacheResults = true,
  contextJobId
}: UseIntelligentSearchProps): UseIntelligentSearchReturn => {

  // Estado principal
  const [state, setState] = useState<SearchState>({
    results: [],
    facets: [],
    suggestions: [],
    loading: false,
    error: null,
    totalCount: 0,
    hasMore: true,
    currentPage: 1,
    searchTime: 0,
    lastQuery: ''
  });

  // Filtros activos
  const [filters, setFilters] = useState<SearchFilters>({});

  // Configuración de búsqueda semántica
  const [semanticSearchEnabled, setSemanticSearchEnabled] = useState(enableSemanticSearch);

  // Cache de resultados
  const cacheRef = useRef<Map<string, SearchResult[]>>(new Map());

  // Referencia para debounce
  const debounceRef = useRef<NodeJS.Timeout>();

  // Instancia del servicio de matching inteligente
  const intelligentMatching = useMemo(() => new IntelligentMatching(), []);
  // Guardar contextJobId en ref para acceso dentro de callbacks
  const contextJobIdRef = useRef<string | undefined>(contextJobId);
  useEffect(() => {
    contextJobIdRef.current = contextJobId;
  }, [contextJobId]);

  // Helper: llamada real a API de búsqueda inteligente
  const fetchIntelligentSearch = useCallback(async (params: any): Promise<NormalizedSearchResponse> => {
    type ApiSearchResponse = {
      results: Array<{
        id: string;
        type: 'candidate' | 'position' | 'company';
        title: string;
        subtitle?: string;
        description?: string;
        score: number;
        relevanceScore?: number;
        metadata?: Record<string, any>;
        highlights?: string[];
        tags?: string[];
        lastUpdated?: string;
        avatar?: string;
      }>;
      facets: SearchFacet[];
      suggestions: SearchSuggestion[];
      totalCount: number;
      hasMore: boolean;
    };

    // Opción A (unificado): /api/search
    const endpoint = 'search';

    const payload = {
      query: params.query,
      type: params.type,
      filters: params.filters || {},
      semanticSearch: !!params.semanticSearch,
      facetedSearch: !!params.facetedSearch,
      page: params.page || 1,
      limit: params.limit || 50
    };

    const apiData = await ApiService.post<ApiSearchResponse>(endpoint, payload);

    // Mapear lastUpdated a Date y normalizar arrays
    const normalized = {
      results: (apiData.results || []).map((r) => ({
        id: r.id,
        type: r.type,
        title: r.title,
        subtitle: r.subtitle || '',
        description: r.description || '',
        score: r.score,
        relevanceScore: r.relevanceScore ?? r.score,
        metadata: r.metadata || {},
        highlights: r.highlights || [],
        tags: r.tags || [],
        lastUpdated: r.lastUpdated ? new Date(r.lastUpdated) : new Date(),
        avatar: r.avatar
      })),
      facets: apiData.facets || [],
      suggestions: apiData.suggestions || [],
      totalCount: apiData.totalCount || 0,
      hasMore: !!apiData.hasMore
    } as const;

    return normalized as NormalizedSearchResponse;
  }, []);

  // Función principal de búsqueda
  const performSearch = useCallback(async (
    query: string,
    searchFilters: SearchFilters = {},
    append: boolean = false
  ) => {
    const startTime = Date.now();

    setState(prev => ({
      ...prev,
      loading: true,
      error: null,
      lastQuery: query
    }));

    try {
      // Verificar cache si está habilitado
      const cacheKey = `${query}-${JSON.stringify(searchFilters)}-${state.currentPage}`;
      if (cacheResults && cacheRef.current.has(cacheKey) && !append) {
        const cachedResults = cacheRef.current.get(cacheKey)!;
        setState(prev => ({
          ...prev,
          results: cachedResults,
          loading: false,
          searchTime: Date.now() - startTime
        }));
        return;
      }

      // Preparar parámetros de búsqueda
      const searchParams = {
        query,
        type: searchType,
        filters: { ...filters, ...searchFilters },
        semanticSearch: semanticSearchEnabled,
        facetedSearch: enableFacetedSearch,
        page: append ? state.currentPage + 1 : 1,
        limit: maxResults
      };

      // Llamada real a API de búsqueda inteligente
      const apiResults: NormalizedSearchResponse = await fetchIntelligentSearch(searchParams);

      // Procesar resultados con IA
      const processedResults = await Promise.all(
        apiResults.results.map(async (result) => {
          if (searchType === 'candidates' && result.type === 'candidate') {
            // Análisis de matching inteligente para candidatos
            const jobIdContext: string | undefined = (searchFilters as SearchFilters)?.jobId
              || (filters as SearchFilters)?.jobId
              || undefined;

            // Usar contextJobId del hook si no viene en filtros
            const jobIdFinal = jobIdContext || contextJobIdRef.current || undefined;

            // Si no hay jobId real, omitir matching IA
            if (!jobIdFinal) {
              return result;
            }

            const matchingResults = await intelligentMatching.performMatching({
              candidateId: result.id,
              jobId: String(jobIdFinal),
              filters: {
                skillsWeight: 0.3,
                experienceWeight: 0.3,
                cultureWeight: 0.2,
                locationWeight: 0.1,
                salaryWeight: 0.1
              }
            });

            const matchingResult = matchingResults[0];

            return {
              ...result,
              culturalFitScore: matchingResult?.culturalFit?.overallFit || 0,
              aiInsights: {
                matchReason: matchingResult?.recommendations?.join(', ') || '',
                strengthAreas: matchingResult?.breakdown?.skillsMatch?.details || [],
                concerns: matchingResult?.riskFactors?.map(rf => rf.description) || [],
                recommendations: matchingResult?.recommendations || []
              }
            };
          }
          return result;
        })
      );

      // Actualizar estado
      setState(prev => ({
        ...prev,
        results: append ? [...prev.results, ...processedResults] : processedResults,
        facets: apiResults.facets,
        suggestions: apiResults.suggestions,
        loading: false,
        totalCount: apiResults.totalCount,
        hasMore: apiResults.hasMore,
        currentPage: append ? prev.currentPage + 1 : 1,
        searchTime: Date.now() - startTime
      }));

      // Guardar en cache
      if (cacheResults) {
        cacheRef.current.set(cacheKey, processedResults);
      }

    } catch (error) {
      setState(prev => ({
        ...prev,
        loading: false,
        error: error instanceof Error ? error.message : 'Error en la búsqueda',
        searchTime: Date.now() - startTime
      }));
    }
  }, [searchType, filters, semanticSearchEnabled, enableFacetedSearch, maxResults, cacheResults, state.currentPage, intelligentMatching]);

  // Función de búsqueda con debounce
  const search = useCallback((query: string, searchFilters?: SearchFilters) => {
    // Limpiar debounce anterior
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    return new Promise<void>((resolve) => {
      debounceRef.current = setTimeout(async () => {
        await performSearch(query, searchFilters);
        resolve();
      }, debounceDelay);
    });
  }, [performSearch, debounceDelay]);

  // Cargar más resultados
  const loadMore = useCallback(async () => {
    if (state.hasMore && !state.loading && state.lastQuery) {
      await performSearch(state.lastQuery, filters, true);
    }
  }, [state.hasMore, state.loading, state.lastQuery, filters, performSearch]);

  // Aplicar filtros
  const applyFilters = useCallback(async (newFilters: SearchFilters) => {
    setFilters(prev => ({ ...prev, ...newFilters }));
    if (state.lastQuery) {
      setState(prev => ({ ...prev, currentPage: 1 }));
      await performSearch(state.lastQuery, newFilters);
    }
  }, [state.lastQuery, performSearch]);

  // Limpiar filtros
  const clearFilters = useCallback(() => {
    setFilters({});
    if (state.lastQuery) {
      setState(prev => ({ ...prev, currentPage: 1 }));
      performSearch(state.lastQuery, {});
    }
  }, [state.lastQuery, performSearch]);

  // Limpiar búsqueda
  const clearSearch = useCallback(() => {
    setState({
      results: [],
      facets: [],
      suggestions: [],
      loading: false,
      error: null,
      totalCount: 0,
      hasMore: true,
      currentPage: 1,
      searchTime: 0,
      lastQuery: ''
    });
    setFilters({});
    cacheRef.current.clear();
  }, []);

  // Reintentar búsqueda
  const retrySearch = useCallback(async () => {
    if (state.lastQuery) {
      await performSearch(state.lastQuery, filters);
    }
  }, [state.lastQuery, filters, performSearch]);

  // Alternar búsqueda semántica
  const toggleSemanticSearch = useCallback(() => {
    setSemanticSearchEnabled(prev => !prev);
  }, []);

  // Exportar resultados
  const exportResults = useCallback((format: 'json' | 'csv') => {
    const data = state.results.map(result => ({
      id: result.id,
      type: result.type,
      title: result.title,
      subtitle: result.subtitle || '',
      score: result.score,
      relevanceScore: result.relevanceScore,
      culturalFitScore: result.culturalFitScore || 0,
      tags: result.tags.join('; '),
      lastUpdated: result.lastUpdated.toISOString(),
      highlights: result.highlights.join('; ')
    }));

    if (format === 'json') {
      return JSON.stringify(data, null, 2);
    } else {
      const headers = Object.keys(data[0] || {});
      const csvContent = [
        headers.join(','),
        ...data.map(row => headers.map(header =>
          `"${String(row[header as keyof typeof row]).replace(/"/g, '""')}"`
        ).join(','))
      ].join('\n');
      return csvContent;
    }
  }, [state.results]);

  // Guardar búsqueda
  const saveSearch = useCallback((name: string) => {
    const savedSearches = JSON.parse(localStorage.getItem('intelligent-searches') || '[]');
    const newSearch = {
      name,
      query: state.lastQuery,
      filters,
      date: new Date()
    };

    const updatedSearches = [...savedSearches.filter((s: any) => s.name !== name), newSearch];
    localStorage.setItem('intelligent-searches', JSON.stringify(updatedSearches));
  }, [state.lastQuery, filters]);

  // Obtener búsquedas guardadas
  const getSavedSearches = useCallback(() => {
    return JSON.parse(localStorage.getItem('intelligent-searches') || '[]');
  }, []);

  // Eliminar búsqueda guardada
  const deleteSavedSearch = useCallback((name: string) => {
    const savedSearches = JSON.parse(localStorage.getItem('intelligent-searches') || '[]');
    const updatedSearches = savedSearches.filter((s: any) => s.name !== name);
    localStorage.setItem('intelligent-searches', JSON.stringify(updatedSearches));
  }, []);

  // Obtener insights de búsqueda
  const getSearchInsights = useCallback(() => {
    const allTags = state.results.flatMap(r => r.tags);
    const skillCounts = allTags.reduce((acc, tag) => {
      acc[tag] = (acc[tag] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    const locations = state.results
      .map(r => r.metadata.location)
      .filter(Boolean)
      .reduce((acc, loc) => {
        acc[loc] = (acc[loc] || 0) + 1;
        return acc;
      }, {} as Record<string, number>);

    return {
      topSkills: Object.entries(skillCounts)
        .sort(([, a], [, b]) => (b as number) - (a as number))
        .slice(0, 10)
        .map(([skill]) => skill),
      commonLocations: Object.entries(locations)
        .sort(([, a], [, b]) => (b as number) - (a as number))
        .slice(0, 5)
        .map(([location]) => location),
      averageScore: state.results.length > 0
        ? state.results.reduce((sum, r) => sum + r.score, 0) / state.results.length
        : 0,
      searchTrends: Object.entries(skillCounts)
        .sort(([, a], [, b]) => (b as number) - (a as number))
        .slice(0, 5)
        .map(([term, frequency]) => ({ term, frequency }))
    };
  }, [state.results]);

  // Verificar si hay filtros activos
  const hasActiveFilters = useMemo(() => {
    return Object.keys(filters).length > 0;
  }, [filters]);

  // Limpiar debounce al desmontar
  useEffect(() => {
    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, []);

  return {
    // Estado
    results: state.results,
    facets: state.facets,
    suggestions: state.suggestions,
    loading: state.loading,
    error: state.error,
    totalCount: state.totalCount,
    hasMore: state.hasMore,
    currentPage: state.currentPage,
    searchTime: state.searchTime,
    lastQuery: state.lastQuery,

    // Acciones
    search,
    loadMore,
    applyFilters,
    clearFilters,
    clearSearch,
    retrySearch,

    // Utilidades
    exportResults,
    saveSearch,
    getSavedSearches,
    deleteSavedSearch,

    // Estado de filtros
    activeFilters: filters,
    hasActiveFilters,

    // Funcionalidades avanzadas
    semanticSearchEnabled,
    toggleSemanticSearch,
    getSearchInsights
  };
};

// Función para obtener resultados reales desde la API
const fetchIntelligentSearch = async (params: any): Promise<NormalizedSearchResponse> => {
  try {
    // Determinar endpoint según el tipo de búsqueda
    let endpoint = '';
    let apiParams: any = {};

    switch (params.type) {
      case 'candidates':
        endpoint = 'candidates';
        apiParams = {
          search: params.filters.query,
          location: params.filters.location,
          skills: params.filters.skills?.join(','),
          experience_min: params.filters.experience?.min,
          experience_max: params.filters.experience?.max,
          page: params.page,
          limit: params.limit,
          job_id: params.filters.jobId // Para matching inteligente
        };
        break;

      case 'positions':
        endpoint = 'jobs';
        apiParams = {
          search: params.filters.query,
          location: params.filters.location,
          department: params.filters.department,
          type: params.filters.jobType,
          page: params.page,
          limit: params.limit
        };
        break;

      case 'companies':
        endpoint = 'companies';
        apiParams = {
          search: params.filters.query,
          location: params.filters.location,
          industry: params.filters.industry,
          page: params.page,
          limit: params.limit
        };
        break;
    }

    // Remover parámetros undefined/null
    Object.keys(apiParams).forEach(key => {
      if (apiParams[key] === undefined || apiParams[key] === null || apiParams[key] === '') {
        delete apiParams[key];
      }
    });

    // Construir endpoint con parámetros de query
    const queryParams = new URLSearchParams();
    Object.entries(apiParams).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, value.toString());
      }
    });

    const endpointWithParams = queryParams.toString()
      ? `${endpoint}?${queryParams.toString()}`
      : endpoint;

    const response = await ApiService.get(endpointWithParams);

    if (!response.success) {
      throw new Error(response.message || 'Error en la búsqueda');
    }

    // Normalizar respuesta de la API al formato esperado
    const normalizedResults: SearchResult[] = response.data?.map((item: any) => ({
      id: item.id.toString(),
      type: params.type.slice(0, -1), // 'candidates' -> 'candidate'
      title: item.title || item.name || `${item.first_name} ${item.last_name}` || 'Sin título',
      subtitle: item.subtitle || item.location || item.department || '',
      description: item.description || item.bio || item.summary || '',
      score: item.score || item.matching_score || 0,
      relevanceScore: item.relevance_score || item.score || 0,
      metadata: {
        location: item.location || '',
        experience: item.experience_years || item.years_experience || 0,
        skills: item.skills ? (Array.isArray(item.skills) ? item.skills : item.skills.split(',')) : [],
        availability: item.availability || 'negotiable',
        department: item.department || '',
        jobType: item.type || item.job_type || '',
        company: item.company || item.company_name || ''
      },
      highlights: item.highlights || [],
      tags: item.tags || item.skills || [],
      lastUpdated: new Date(item.updated_at || item.created_at || Date.now())
    })) || [];

    return {
      results: normalizedResults,
      facets: response.facets || [],
      suggestions: response.suggestions || [],
      totalCount: response.total || normalizedResults.length,
      hasMore: response.has_more || false
    };

  } catch (error) {
    console.error('Error en fetchIntelligentSearch:', error);
    throw error;
  }
};

export default useIntelligentSearch;

// Hooks especializados
export const useIntelligentCandidateSearch = (options?: Omit<UseIntelligentSearchProps, 'searchType'>) => {
  return useIntelligentSearch({ ...options, searchType: 'candidates' });
};

export const useIntelligentPositionSearch = (options?: Omit<UseIntelligentSearchProps, 'searchType'>) => {
  return useIntelligentSearch({ ...options, searchType: 'positions' });
};

export const useIntelligentCompanySearch = (options?: Omit<UseIntelligentSearchProps, 'searchType'>) => {
  return useIntelligentSearch({ ...options, searchType: 'companies' });
};

// Tipos exportados
export type {
  UseIntelligentSearchProps,
  SearchFilters,
  SearchResult,
  SearchFacet,
  SearchSuggestion,
  UseIntelligentSearchReturn
};
