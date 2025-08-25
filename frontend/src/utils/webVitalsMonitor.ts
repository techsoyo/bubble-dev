/**
 * Web Vitals Monitoring
 * Monitorea Core Web Vitals en producción
 */

import { onCLS, onINP, onFCP, onLCP, onTTFB } from 'web-vitals';
import { safeGet, safeSet } from './safeStorage';

interface WebVitalMetric {
  name: string;
  value: number;
  delta: number;
  id: string;
  rating: 'good' | 'needs-improvement' | 'poor';
}

class WebVitalsMonitor {
  private metrics: WebVitalMetric[] = [];
  private readonly thresholds = {
    LCP: { good: 2500, poor: 4000 },
    INP: { good: 200, poor: 500 },
    CLS: { good: 0.1, poor: 0.25 },
    FCP: { good: 1800, poor: 3000 },
    TTFB: { good: 800, poor: 1800 },
  };

  constructor() {
    this.initializeMetrics();
  }

  private initializeMetrics(): void {
    // Core Web Vitals
    onCLS(this.handleMetric.bind(this));
    onINP(this.handleMetric.bind(this));
    onLCP(this.handleMetric.bind(this));

    // Additional metrics
    onFCP(this.handleMetric.bind(this));
    onTTFB(this.handleMetric.bind(this));
  }

  private handleMetric(metric: WebVitalMetric): void {
    const webVitalMetric: WebVitalMetric = {
      name: metric.name,
      value: metric.value,
      delta: metric.delta,
      id: metric.id,
      rating: this.getRating(metric.name, metric.value),
    };

    this.metrics.push(webVitalMetric);
    this.reportMetric(webVitalMetric);

    // Log en desarrollo
    if (import.meta.env.MODE === 'development') {
      // ...eliminado console.log para producción...
    }
  }

  private getRating(name: string, value: number): 'good' | 'needs-improvement' | 'poor' {
    const threshold = this.thresholds[name as keyof typeof this.thresholds];
    if (!threshold) return 'good';

    if (value <= threshold.good) return 'good';
    if (value <= threshold.poor) return 'needs-improvement';
    return 'poor';
  }

  private reportMetric(metric: WebVitalMetric): void {
    // Enviar a analytics en producción
    if (import.meta.env.MODE === 'production') {
      this.sendToAnalytics(metric);
    }

    // Almacenar localmente para debug
    this.storeLocally(metric);
  }

  private sendToAnalytics(metric: WebVitalMetric): void {
    // Implementar envío a Google Analytics, DataDog, etc.
    try {
      // Ejemplo: Google Analytics 4
      const gtag = (globalThis as Record<string, unknown>).gtag;
      if (typeof gtag === 'function') {
        gtag('event', metric.name, {
          event_category: 'Web Vitals',
          value: Math.round(metric.value),
          custom_parameter_1: metric.rating,
        });
      }

      // API endpoint personalizado
      fetch('/api/web-vitals', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          metric,
          userAgent: navigator.userAgent,
          url: location.href,
          timestamp: Date.now(),
        }),
      }).catch(console.warn);
    } catch (error) {
      console.warn('Error reporting web vital:', error);
    }
  }

  private storeLocally(metric: WebVitalMetric): void {
    try {
      const existingMetrics = safeGet<any[]>('webVitals', []);

      existingMetrics.push({
        ...metric,
        timestamp: Date.now(),
        url: location.href,
      });

      // Mantener solo las últimas 50 métricas (no-op en producción)
      const recentMetrics = existingMetrics.slice(-50);
      safeSet('webVitals', recentMetrics);
    } catch (error) {
      console.warn('Error storing web vital locally:', error);
    }
  }

  public getMetrics(): WebVitalMetric[] {
    return [...this.metrics];
  }

  public getAverages(): Record<string, number> {
    const groupedMetrics = this.metrics.reduce((acc, metric) => {
      if (!acc[metric.name]) acc[metric.name] = [];
      acc[metric.name]!.push(metric.value);
      return acc;
    }, {} as Record<string, number[]>);

    return Object.entries(groupedMetrics).reduce((acc, [name, values]) => {
      acc[name] = values.reduce((sum, val) => sum + val, 0) / values.length;
      return acc;
    }, {} as Record<string, number>);
  }

  public generateReport(): string {
    const averages = this.getAverages();
    let report = '📊 Web Vitals Report\\n';
    report += '=====================\\n';

    Object.entries(averages).forEach(([name, avg]) => {
      const rating = this.getRating(name, avg);
      const emoji = rating === 'good' ? '✅' : rating === 'needs-improvement' ? '⚠️' : '🚨';
      report += `${emoji} ${name}: ${avg.toFixed(2)}ms (${rating})\\n`;
    });

    return report;
  }
}

// Inicializar monitor
const webVitalsMonitor = new WebVitalsMonitor();

// Exportar para uso en componentes
export { webVitalsMonitor, WebVitalsMonitor };

// Debug helper
export const logWebVitals = () => {
  // ...eliminado console.log para producción...
  return webVitalsMonitor.getMetrics();
};
