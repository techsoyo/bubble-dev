import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { IntelligentMatching } from '../services/ai/IntelligentMatching';

// Interfaces para el hook
interface UseIntelligentSearchProps {
  searchType: 'candidates' | 'positions' | 'companies';
  autoSearch?: boolean;
  debounceDelay?: number;
  maxResults?: number;
  enableSemanticSearch?: boolean;
  enableFacetedSearch?: boolean;
  cacheResults?: boolean;
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

const useIntelligentSearch = ({
  searchType,
  autoSearch = false,
  debounceDelay = 300,
  maxResults = 50,
  enableSemanticSearch = true,
  enableFacetedSearch = true,
  cacheResults = true
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

      // Simular búsqueda inteligente (aquí iría la integración real con la API)
      const mockResults = await simulateIntelligentSearch(searchParams);

      // Procesar resultados con IA
      const processedResults = await Promise.all(
        mockResults.results.map(async (result) => {
          if (searchType === 'candidates' && result.type === 'candidate') {
            // Análisis de matching inteligente para candidatos
            const matchingResults = await intelligentMatching.performMatching({
              candidateId: result.id,
              jobId: 'mock-job-id', // En producción vendría de los parámetros de búsqueda
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
        facets: mockResults.facets,
        suggestions: mockResults.suggestions,
        loading: false,
        totalCount: mockResults.totalCount,
        hasMore: mockResults.hasMore,
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

// Función de simulación de búsqueda (reemplazar con API real)
const simulateIntelligentSearch = async (params: any) => {
  // Simular delay de API
  await new Promise(resolve => setTimeout(resolve, 800));

  // Generar resultados mock basados en el tipo de búsqueda
  const mockResults: SearchResult[] = [];
  const count = Math.min(params.limit, 25);

  for (let i = 0; i < count; i++) {
    if (params.type === 'candidates') {
      mockResults.push({
        id: `candidate-${i}`,
        type: 'candidate',
        title: `Desarrollador ${['Frontend', 'Backend', 'Full Stack'][i % 3]} ${i + 1}`,
        subtitle: `${2 + i} años de experiencia`,
        description: `Desarrollador con experiencia en React, Node.js y bases de datos...`,
        score: 85 + Math.random() * 15,
        relevanceScore: 80 + Math.random() * 20,
        metadata: {
          location: ['Madrid', 'Barcelona', 'Valencia'][i % 3],
          experience: 2 + i,
          skills: ['React', 'Node.js', 'TypeScript'],
          availability: 'immediate'
        },
        highlights: [`Experiencia en ${['React', 'Vue', 'Angular'][i % 3]}`, 'Disponible inmediatamente'],
        tags: ['JavaScript', 'React', 'Node.js'],
        lastUpdated: new Date()
      });
    }
  }

  return {
    results: mockResults,
    facets: [
      {
        field: 'location',
        label: 'Ubicación',
        values: [
          { value: 'madrid', label: 'Madrid', count: 15, selected: false },
          { value: 'barcelona', label: 'Barcelona', count: 12, selected: false },
          { value: 'valencia', label: 'Valencia', count: 8, selected: false }
        ]
      }
    ],
    suggestions: [
      { text: 'React developer', type: 'query' as const, count: 45 },
      { text: 'JavaScript', type: 'skill' as const, count: 120 },
      { text: 'Madrid', type: 'location' as const, count: 89 }
    ],
    totalCount: 150,
    hasMore: true
  };
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