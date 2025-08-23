/*
AIInsightsPanel - Refactorizado sin MUI para optimización de rendimiento
Migrado de Material UI a componentes Radix UI + Tailwind CSS
*/

import * as React from 'react';
import { useState, useEffect, useCallback, useMemo } from 'react';
import ApiService from '../../services/ApiService';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '../ui/dialog';
import { Input } from '../ui/input';
import { Switch } from '../ui/switch';
import { Badge } from '../ui/badge';
import { Separator } from '../ui/separator';
import { Alert, AlertDescription } from '../ui/alert';
import { Skeleton } from '../ui/skeleton';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '../ui/tooltip';
import {
  TrendingUp,
  TrendingDown,
  Brain,
  Zap,
  BarChart3,
  Star,
  AlertTriangle,
  CheckCircle,
  Info,
  Lightbulb,
  Activity,
  Users,
  BookOpen,
  Briefcase,
  MapPin,
  DollarSign,
  ChevronDown,
  RotateCcw,
  Settings,
  Bookmark,
  Share,
  Download,
  Bell,
  Filter,
  Eye,
  EyeOff,
  ThumbsUp,
  ThumbsDown,
  MessageCircle,
  Clock,
  AlertCircle,
  Database,
  Cpu,
  Bot
} from 'lucide-react';

// Interfaces para tipos de datos
interface AIInsight {
  id: string;
  type: 'recommendation' | 'prediction' | 'alert' | 'opportunity' | 'risk' | 'trend';
  category: 'recruitment' | 'candidate' | 'performance' | 'market' | 'process' | 'quality';
  title: string;
  description: string;
  confidence: number;
  impact: 'low' | 'medium' | 'high' | 'critical';
  priority: number;
  timestamp: Date;
  source: string;
  actionable: boolean;
  actions?: Array<{
    id: string;
    label: string;
    type: 'primary' | 'secondary' | 'warning' | 'error';
    action: 'navigate' | 'dialog' | 'external';
  }>;
  metadata?: { data: Record<string, any> };
  status?: 'new' | 'viewed' | 'acted' | 'dismissed';
  tags?: string[];
  relatedInsights?: string[];
}

interface PredictiveInsight {
  id: string;
  title: string;
  description: string;
  probability: number;
  category: string;
  timeFrame: string;
  impact: 'low' | 'medium' | 'high' | 'critical';
  confidence: number;
  data: Record<string, any>;
  recommendations: string[];
}

interface TrendAnalysis {
  id: string;
  metric: string;
  trend: 'up' | 'down' | 'stable';
  percentage: number;
  period: string;
  significance: 'low' | 'medium' | 'high';
  context: string;
}

interface AIInsightsPanelProps {
  candidateId?: string;
  positionId?: string;
  recruiterId?: string;
  enablePredictions?: boolean;
  enableTrends?: boolean;
  compactMode?: boolean;
  maxInsights?: number;
  refreshInterval?: number;
  onInsightAction?: (insightId: string, actionId: string) => void;
}

export const AIInsightsPanel: React.FC<AIInsightsPanelProps> = ({
  candidateId,
  positionId,
  recruiterId,
  enablePredictions = true,
  enableTrends = true,
  compactMode = false,
  maxInsights = 10,
  refreshInterval = 0,
  onInsightAction
}) => {
  // Estado principal
  const [insights, setInsights] = useState<AIInsight[]>([]);
  const [predictions, setPredictions] = useState<PredictiveInsight[]>([]);
  const [trends, setTrends] = useState<TrendAnalysis[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

  // Estado de configuración
  const [selectedTypes, setSelectedTypes] = useState<string[]>([]);
  const [minConfidence, setMinConfidence] = useState(0);
  const [showDismissed, setShowDismissed] = useState(false);
  const [settingsOpen, setSettingsOpen] = useState(false);

  // Estado UI
  const [expandedInsights, setExpandedInsights] = useState<Set<string>>(new Set());
  const [bookmarkedInsights, setBookmarkedInsights] = useState<Set<string>>(new Set());

  // Cargar insights desde API
  const loadInsights = useCallback(async () => {
    setLoading(true);
    try {
      // Construir parámetros de query
      const queryParams = new URLSearchParams();
      if (candidateId) queryParams.append('candidate_id', candidateId);
      if (positionId) queryParams.append('position_id', positionId);
      if (recruiterId) queryParams.append('recruiter_id', recruiterId);
      queryParams.append('limit', maxInsights.toString());

      // Cargar insights reales desde API
      const [insightsResponse, predictionsResponse, trendsResponse] = await Promise.all([
        ApiService.get(`ai/insights?${queryParams.toString()}`),
        enablePredictions ? ApiService.get(`ai/predictions?${queryParams.toString()}`) : Promise.resolve({ data: [] }),
        enableTrends ? ApiService.get(`ai/trends?${queryParams.toString()}`) : Promise.resolve({ data: [] })
      ]);

      // Normalizar las fechas en los insights
      const normalizedInsights = (insightsResponse.data || []).map((insight: any) => ({
        ...insight,
        timestamp: new Date(insight.timestamp)
      }));

      setInsights(normalizedInsights);
      setPredictions(predictionsResponse.data || []);
      setTrends(trendsResponse.data || []);

      setLastUpdated(new Date());
      setError(null);
    } catch (err) {
      console.error('Error loading AI insights:', err);
      setError('Error al cargar insights de IA. Intente de nuevo más tarde.');
    } finally {
      setLoading(false);
    }
  }, [candidateId, positionId, recruiterId, enablePredictions, enableTrends, maxInsights]);

  // Efectos
  useEffect(() => {
    loadInsights();

    if (refreshInterval > 0) {
      const interval = setInterval(loadInsights, refreshInterval);
      return () => clearInterval(interval);
    }
    return undefined;
  }, [loadInsights, refreshInterval]);

  // Filtrar insights
  const filteredInsights = useMemo(() => {
    return insights
      .filter(insight => {
        if (!showDismissed && insight.status === 'dismissed') return false;
        if (selectedTypes.length > 0 && !selectedTypes.includes(insight.type)) return false;
        if (insight.confidence < minConfidence) return false;
        return true;
      })
      .sort((a, b) => a.priority - b.priority);
  }, [insights, selectedTypes, minConfidence, showDismissed]);

  // Handlers
  const handleInsightAction = useCallback(async (insight: AIInsight, actionId: string) => {
    if (onInsightAction) {
      onInsightAction(insight.id, actionId);
    }

    try {
      await ApiService.post(`ai/insights/${insight.id}/action`, { actionId });
      setInsights(prev => prev.map(i =>
        i.id === insight.id ? { ...i, status: 'acted' } : i
      ));
    } catch (error) {
      console.error('Error marking insight action:', error);
    }
  }, [onInsightAction]);

  const handleBookmarkInsight = useCallback((insightId: string) => {
    setBookmarkedInsights(prev => {
      const newSet = new Set(prev);
      if (newSet.has(insightId)) {
        newSet.delete(insightId);
      } else {
        newSet.add(insightId);
      }
      return newSet;
    });
  }, []);

  const getInsightIcon = (type: AIInsight['type']) => {
    switch (type) {
      case 'recommendation': return <Lightbulb className="h-4 w-4" />;
      case 'prediction': return <Brain className="h-4 w-4" />;
      case 'alert': return <AlertTriangle className="h-4 w-4" />;
      case 'opportunity': return <TrendingUp className="h-4 w-4" />;
      case 'risk': return <AlertCircle className="h-4 w-4" />;
      case 'trend': return <BarChart3 className="h-4 w-4" />;
      default: return <Info className="h-4 w-4" />;
    }
  };

  const getImpactColor = (impact: AIInsight['impact']) => {
    switch (impact) {
      case 'critical': return 'bg-red-100 text-red-800';
      case 'high': return 'bg-orange-100 text-orange-800';
      case 'medium': return 'bg-yellow-100 text-yellow-800';
      case 'low': return 'bg-gray-100 text-gray-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const formatTimestamp = (date: Date) => {
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const hours = Math.floor(diff / (1000 * 60 * 60));

    if (hours < 1) return 'Hace menos de 1 hora';
    if (hours < 24) return `Hace ${hours} hora${hours > 1 ? 's' : ''}`;

    const days = Math.floor(hours / 24);
    return `Hace ${days} día${days > 1 ? 's' : ''}`;
  };

  if (loading && insights.length === 0) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center justify-center py-8">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
          <p className="mt-2 text-sm text-muted-foreground">Cargando insights de IA...</p>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card>
        <CardContent className="p-4">
          <Alert variant="destructive">
            <AlertTriangle className="h-4 w-4" />
            <AlertDescription className="ml-2">
              {error}
              <Button
                variant="outline"
                size="sm"
                onClick={loadInsights}
                className="ml-2"
              >
                Reintentar
              </Button>
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>
    );
  }

  return (
    <TooltipProvider>
      <Card className="w-full">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-lg font-semibold">
            AI Insights {filteredInsights.length > 0 && `(${filteredInsights.length})`}
          </CardTitle>
          <div className="flex items-center space-x-2">
            {lastUpdated && (
              <span className="text-xs text-muted-foreground">
                {formatTimestamp(lastUpdated)}
              </span>
            )}
            <Tooltip>
              <TooltipTrigger asChild>
                <Button variant="outline" size="sm" onClick={loadInsights} disabled={loading}>
                  <RotateCcw className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>Actualizar insights</TooltipContent>
            </Tooltip>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button variant="outline" size="sm" onClick={() => setSettingsOpen(true)}>
                  <Settings className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>Configuración</TooltipContent>
            </Tooltip>
          </div>
        </CardHeader>

        <CardContent className="p-4">
          {filteredInsights.length === 0 ? (
            <Alert>
              <Info className="h-4 w-4" />
              <AlertDescription>
                No hay insights disponibles para mostrar.
              </AlertDescription>
            </Alert>
          ) : (
            <div className="space-y-4">
              {filteredInsights.map((insight) => (
                <Card key={insight.id} className={`transition-all ${insight.status === 'new' ? 'border-primary shadow-md' : ''}`}>
                  <CardContent className="p-4">
                    <div className="flex items-start justify-between">
                      <div className="flex items-start space-x-3 flex-1">
                        <div className={`p-2 rounded-full ${getImpactColor(insight.impact)}`}>
                          {getInsightIcon(insight.type)}
                        </div>

                        <div className="flex-1 min-w-0">
                          <div className="flex items-center space-x-2 mb-1">
                            <h4 className="text-sm font-medium truncate">{insight.title}</h4>
                            <Badge variant="outline" className="text-xs">
                              {insight.confidence}%
                            </Badge>
                            {insight.status === 'new' && (
                              <Badge className="text-xs">Nuevo</Badge>
                            )}
                          </div>

                          <p className="text-xs text-muted-foreground mb-2 line-clamp-2">
                            {insight.description}
                          </p>

                          <div className="flex items-center space-x-2 text-xs text-muted-foreground">
                            <span>{insight.source}</span>
                            <span>•</span>
                            <span>{formatTimestamp(insight.timestamp)}</span>
                          </div>

                          {insight.tags && insight.tags.length > 0 && (
                            <div className="flex flex-wrap gap-1 mt-2">
                              {insight.tags.map(tag => (
                                <Badge key={tag} variant="secondary" className="text-xs">
                                  {tag}
                                </Badge>
                              ))}
                            </div>
                          )}

                          {insight.actions && insight.actions.length > 0 && (
                            <div className="flex space-x-2 mt-3">
                              {insight.actions.map(action => (
                                <Button
                                  key={action.id}
                                  size="sm"
                                  variant={action.type === 'primary' ? 'default' : 'outline'}
                                  onClick={() => handleInsightAction(insight, action.id)}
                                >
                                  {action.label}
                                </Button>
                              ))}
                            </div>
                          )}
                        </div>
                      </div>

                      <div className="flex flex-col space-y-1">
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button variant="ghost" size="sm" onClick={() => handleBookmarkInsight(insight.id)}>
                              {bookmarkedInsights.has(insight.id) ? (
                                <Bookmark className="h-4 w-4 fill-current" />
                              ) : (
                                <Bookmark className="h-4 w-4" />
                              )}
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent>
                            {bookmarkedInsights.has(insight.id) ? 'Quitar marcador' : 'Marcar'}
                          </TooltipContent>
                        </Tooltip>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}

          {/* Predictions Section */}
          {enablePredictions && predictions.length > 0 && (
            <div className="mt-6">
              <h3 className="text-base font-semibold mb-3">Predicciones de IA ({predictions.length})</h3>
              <div className="space-y-2">
                {predictions.map(prediction => (
                  <Card key={prediction.id}>
                    <CardContent className="p-3">
                      <div className="flex items-center justify-between mb-2">
                        <h4 className="text-sm font-medium">{prediction.title}</h4>
                        <Badge variant="outline">{prediction.probability}%</Badge>
                      </div>
                      <p className="text-xs text-muted-foreground mb-2">{prediction.description}</p>
                      <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Plazo: {prediction.timeFrame}</span>
                        <div className="w-20 bg-secondary rounded-full h-1">
                          <div
                            className="bg-primary h-1 rounded-full transition-all"
                            style={{ width: `${prediction.probability}%` }}
                          />
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </div>
          )}

          {/* Trends Section */}
          {enableTrends && trends.length > 0 && (
            <div className="mt-6">
              <h3 className="text-base font-semibold mb-3">Análisis de Tendencias ({trends.length})</h3>
              <div className="space-y-2">
                {trends.map(trend => (
                  <Card key={trend.id}>
                    <CardContent className="p-3">
                      <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center space-x-2">
                          {trend.trend === 'up' ? (
                            <TrendingUp className="h-4 w-4 text-green-600" />
                          ) : (
                            <TrendingDown className="h-4 w-4 text-red-600" />
                          )}
                          <span className="text-sm font-medium">{trend.metric}</span>
                        </div>
                        <Badge
                          variant={trend.trend === 'up' ? 'default' : 'destructive'}
                          className="text-xs"
                        >
                          {trend.percentage > 0 ? '+' : ''}{trend.percentage}%
                        </Badge>
                      </div>
                      <p className="text-xs text-muted-foreground mb-1">{trend.context}</p>
                      <span className="text-xs text-muted-foreground">
                        {trend.period} • Significancia: {trend.significance}
                      </span>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </div>
          )}
        </CardContent>

        {/* Settings Dialog */}
        <Dialog open={settingsOpen} onOpenChange={setSettingsOpen}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Configuración de Insights</DialogTitle>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="flex items-center justify-between">
                <label htmlFor="showDismissed" className="text-sm font-medium">
                  Mostrar insights descartados
                </label>
                <Switch
                  checked={showDismissed}
                  onChange={setShowDismissed}
                />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-medium">Confianza mínima (%)</label>
                <Input
                  type="number"
                  value={minConfidence}
                  onChange={(e) => setMinConfidence(Number(e.target.value))}
                  min={0}
                  max={100}
                />
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </Card>
    </TooltipProvider>
  );
};

export default AIInsightsPanel;
