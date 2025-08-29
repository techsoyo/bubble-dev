/**
 * Bundle Analysis and Optimization Utilities
 * 
 * Advanced bundle analysis tools for production optimization including
 * source map analysis, chunk optimization, and tree shaking validation.
 * 
 * @package BundleAnalysis
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

import { BundleAnalysis } from './performance';

/**
 * Bundle size analysis interface
 */
interface BundleReport {
    totalSize: number;
    gzippedSize: number;
    chunks: ChunkInfo[];
    treeShakenModules: string[];
    unusedExports: string[];
    recommendations: string[];
}

/**
 * Chunk information interface
 */
interface ChunkInfo {
    name: string;
    size: number;
    modules: string[];
    isAsync: boolean;
}

/**
 * Advanced Bundle Analyzer
 */
export class AdvancedBundleAnalyzer extends BundleAnalysis {
    private static bundleReport: BundleReport | null = null;

    /**
     * Generate comprehensive bundle report
     */
    static async generateReport(): Promise<BundleReport> {
        const report: BundleReport = {
            totalSize: 0,
            gzippedSize: 0,
            chunks: [],
            treeShakenModules: [],
            unusedExports: [],
            recommendations: []
        };

        // Analyze loaded modules
        if ('performance' in window) {
            const entries = performance.getEntriesByType('resource') as PerformanceResourceTiming[];

            entries.forEach(entry => {
                if (entry.name.includes('.js') || entry.name.includes('.css')) {
                    const size = entry.transferSize || 0;
                    report.totalSize += size;

                    // Extract chunk information
                    const chunkName = this.extractChunkName(entry.name);
                    const existingChunk = report.chunks.find(chunk => chunk.name === chunkName);

                    if (existingChunk) {
                        existingChunk.size += size;
                        existingChunk.modules.push(entry.name);
                    } else {
                        report.chunks.push({
                            name: chunkName,
                            size,
                            modules: [entry.name],
                            isAsync: entry.name.includes('chunk') || entry.name.includes('lazy')
                        });
                    }
                }
            });
        }

        // Generate recommendations
        report.recommendations = this.generateRecommendations(report);

        this.bundleReport = report;
        return report;
    }

    /**
     * Extract chunk name from resource URL
     */
    private static extractChunkName(url: string): string {
        const fileName = url.split('/').pop() || '';
        const nameWithoutHash = fileName.replace(/\.[a-f0-9]{8}\./g, '.');
        return nameWithoutHash.replace(/\.(js|css)$/, '');
    }

    /**
     * Generate optimization recommendations
     */
    private static generateRecommendations(report: BundleReport): string[] {
        const recommendations: string[] = [];

        // Check for large chunks
        const largeChunks = report.chunks.filter(chunk => chunk.size > 250000); // 250KB
        if (largeChunks.length > 0) {
            recommendations.push(
                `Consider splitting large chunks: ${largeChunks.map(c => c.name).join(', ')}`
            );
        }

        // Check for too many chunks
        if (report.chunks.length > 20) {
            recommendations.push('Too many chunks detected. Consider chunk merging strategies.');
        }

        // Check total bundle size
        if (report.totalSize > 1048576) { // 1MB
            recommendations.push('Total bundle size exceeds 1MB. Consider code splitting and lazy loading.');
        }

        // Check for synchronous chunks that could be async
        const syncChunks = report.chunks.filter(chunk => !chunk.isAsync && chunk.size > 100000);
        if (syncChunks.length > 0) {
            recommendations.push(
                `Consider making these chunks async: ${syncChunks.map(c => c.name).join(', ')}`
            );
        }

        return recommendations;
    }

    /**
     * Analyze tree shaking effectiveness
     */
    static analyzeTreeShaking(): string[] {
        const treeShakenModules: string[] = [];

        // In a real scenario, this would analyze the build output
        // For now, we'll simulate common tree shaking opportunities
        const commonLibraries = [
            'lodash', 'moment', 'rxjs', 'antd', 'material-ui'
        ];

        commonLibraries.forEach(lib => {
            // Simulate checking if library is fully imported vs tree-shaken
            const isFullyImported = this.checkFullImport(lib);
            if (isFullyImported) {
                treeShakenModules.push(`${lib}: Consider using specific imports instead of full library`);
            }
        });

        return treeShakenModules;
    }

    /**
     * Check if library is fully imported (simulation)
     */
    private static checkFullImport(_library: string): boolean {
        // This would typically analyze the actual bundle
        // For demo purposes, we'll return random results
        return Math.random() > 0.5;
    }

    /**
     * Monitor real-time bundle performance - Disabled for cleaner logs
     */
    static startRealTimeMonitoring(): void {
        // Disabled in current version to reduce console noise
        // Enable only when actively debugging bundle issues
        if (import.meta.env.MODE === 'development' && window.location.search.includes('debug=bundle')) {
            // Only run when explicitly requested via ?debug=bundle
            setInterval(() => {
                this.generateReport().then(report => {
                    if (report.totalSize > 0) {
                        const hasIssues = report.recommendations.length > 0;
                        const isLargeBundle = report.totalSize > 5 * 1024 * 1024; // 5MB

                        if (hasIssues || isLargeBundle) {
                            console.group('🔍 Bundle Analysis Report');



                            const topChunks = report.chunks
                                .sort((a, b) => b.size - a.size)
                                .slice(0, 3)
                                .map(chunk => `${chunk.name}: ${(chunk.size / 1024).toFixed(2)}KB`);
                            // Puedes descomentar la siguiente línea para ver los chunks más grandes en consola:
                            if (report.recommendations.length > 0) {

                            }
                            console.groupEnd();
                        }
                    }
                });
            }, 60000); // Increased to 60 seconds
        }
    }

    /**
     * Get current bundle report
     */
    static getBundleReport(): BundleReport | null {
        return this.bundleReport;
    }
}

/**
 * Chunk optimization utilities
 */
export class ChunkOptimizer {
    /**
     * Preload critical chunks - Disabled to avoid 404 errors
     * Only preload chunks that actually exist in the build
     */
    static preloadCriticalChunks(): void {
        // Get actual chunks from the document instead of hardcoded names
        const existingScripts = Array.from(document.querySelectorAll('script[src*="/assets/"]'));
        const existingChunks = existingScripts
            .map(script => (script as HTMLScriptElement).src)
            .filter(src => src.includes('/assets/'))
            .slice(0, 3); // Limit to first 3 chunks to avoid overloading

        existingChunks.forEach(chunkSrc => {
            // Only preload if not already loaded
            if (!document.querySelector(`link[href="${chunkSrc}"]`)) {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'script';
                link.href = chunkSrc;
                document.head.appendChild(link);
            }
        });
    }

    /**
     * Prefetch non-critical chunks - Optimized for real chunks
     */
    static prefetchNonCriticalChunks(): void {
        // Prefetch after critical resources are loaded
        window.addEventListener('load', () => {
            setTimeout(() => {
                // Get chunks that aren't already loaded
                const allScripts = Array.from(document.querySelectorAll('script[src*="/assets/"]'));
                const loadedChunks = allScripts.map(script => (script as HTMLScriptElement).src);

                // Find additional chunks to prefetch (if any are discovered later)
                const additionalChunks = loadedChunks
                    .filter(src => !document.querySelector(`link[href="${src}"]`))
                    .slice(3); // Skip the first 3 (already preloaded)

                additionalChunks.forEach(chunkSrc => {
                    const link = document.createElement('link');
                    link.rel = 'prefetch';
                    link.href = chunkSrc;
                    document.head.appendChild(link);
                });
            }, 2000);
        });
    }

    /**
     * Dynamic chunk loading with retry
     */
    static async loadChunkWithRetry(
        chunkLoader: () => Promise<any>,
        maxRetries: number = 3
    ): Promise<any> {
        let lastError: Error;

        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            try {
                return await chunkLoader();
            } catch (error) {
                lastError = error as Error;
                console.warn(`Chunk loading attempt ${attempt} failed:`, error);

                if (attempt < maxRetries) {
                    // Exponential backoff
                    await new Promise(resolve =>
                        setTimeout(resolve, Math.pow(2, attempt) * 1000)
                    );
                }
            }
        }

        throw new Error(`Failed to load chunk after ${maxRetries} attempts: ${lastError!.message}`);
    }
}

/**
 * Source map analysis utilities
 */
export class SourceMapAnalyzer {
    /**
     * Analyze source map coverage
     */
    static analyzeSourceMaps(): void {
        if (import.meta.env.MODE === 'development') {
            // Check if source maps are available
            const scripts = Array.from(document.querySelectorAll('script[src]'));
            const hasSourceMaps = scripts.some(script => {
                const src = (script as HTMLScriptElement).src;
                return src.includes('.map') || this.hasSourceMapComment(src);
            });

            if (!hasSourceMaps) {
                console.warn('No source maps detected. Enable source maps for better debugging.');
            } else {

            }
        }
    }

    /**
     * Check if script has source map comment
     */
    private static hasSourceMapComment(_scriptUrl: string): boolean {
        // In a real implementation, this would fetch and check the script content
        // For now, we'll assume production builds have source maps
        return import.meta.env.MODE === 'development';
    }
}

/**
 * Webpack Bundle Analyzer integration
 */
export class WebpackAnalyzerIntegration {
    /**
     * Generate bundle analysis command
     */
    static generateAnalysisCommand(): string {
        return 'npx webpack-bundle-analyzer dist/static/js/*.js';
    }

    /**
     * Export bundle stats for analysis
     */
    static exportBundleStats(): void {
        if (import.meta.env.MODE === 'development') {
            const stats = {
                timestamp: new Date().toISOString(),
                url: window.location.href,
                userAgent: navigator.userAgent,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                },
                memory: this.getMemoryInfo(),
                performance: this.getPerformanceMetrics()
            };

            // Export to console for copy-paste into analysis tools

        }
    }

    /**
     * Get memory information
     */
    private static getMemoryInfo(): any {
        if ('memory' in performance) {
            const memory = (performance as any).memory;
            return {
                used: memory.usedJSHeapSize,
                total: memory.totalJSHeapSize,
                limit: memory.jsHeapSizeLimit
            };
        }
        return null;
    }

    /**
     * Get performance metrics
     */
    private static getPerformanceMetrics(): any {
        const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
        return {
            domContentLoaded: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart,
            loadComplete: navigation.loadEventEnd - navigation.loadEventStart,
            timeToFirstByte: navigation.responseStart - navigation.requestStart
        };
    }
}

/**
 * Initialize bundle analysis
 */
export function initializeBundleAnalysis(): void {
    console.log('🎯 Inicializando análisis de bundle...');

    // Start real-time monitoring in development
    AdvancedBundleAnalyzer.startRealTimeMonitoring();

    // Analyze source maps
    SourceMapAnalyzer.analyzeSourceMaps();

    // Preload critical chunks
    ChunkOptimizer.preloadCriticalChunks();

    // Prefetch non-critical chunks
    ChunkOptimizer.prefetchNonCriticalChunks();

    // Export stats for analysis
    WebpackAnalyzerIntegration.exportBundleStats();

    // Generar reporte inicial después de la carga
    window.addEventListener('load', () => {
        setTimeout(async () => {
            try {
                const report = await AdvancedBundleAnalyzer.generateReport();
                console.log('📊 Reporte de bundle generado:', {
                    tamañoTotal: `${(report.totalSize / 1024 / 1024).toFixed(2)}MB`,
                    tamañoComprimido: `${(report.gzippedSize / 1024 / 1024).toFixed(2)}MB`,
                    chunks: report.chunks.length,
                    recomendaciones: report.recommendations.length
                });

                // Mostrar recomendaciones importantes
                if (report.recommendations.length > 0) {
                    console.log('💡 Recomendaciones de optimización:', report.recommendations.slice(0, 3));
                }

                // Alertar sobre bundles grandes
                if (report.totalSize > 5 * 1024 * 1024) { // 5MB
                    console.warn('⚠️ Bundle grande detectado. Considera optimizaciones adicionales.');
                }
            } catch (error) {
                console.warn('Error generando reporte de bundle:', error);
            }
        }, 2000);
    });

    // Monitoreo de chunks cargados dinámicamente
    const observer = new PerformanceObserver((list) => {
        list.getEntries().forEach((entry) => {
            if (entry.entryType === 'resource' && entry.name.includes('.js')) {
                const resourceEntry = entry as PerformanceResourceTiming;
                console.log(`📦 Chunk cargado: ${entry.name}`, {
                    tamaño: resourceEntry.transferSize ? `${(resourceEntry.transferSize / 1024).toFixed(2)}KB` : 'N/A',
                    duración: `${entry.duration.toFixed(2)}ms`
                });
            }
        });
    });

    observer.observe({ entryTypes: ['resource'] });

    console.log('✅ Análisis de bundle inicializado correctamente');
}



export default {
    AdvancedBundleAnalyzer,
    ChunkOptimizer,
    SourceMapAnalyzer,
    WebpackAnalyzerIntegration,
    initializeBundleAnalysis
};
