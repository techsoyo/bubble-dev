/**
 * Subresource Integrity (SRI) Management
 * 
 * Provides utilities for managing SRI hashes for external resources
 * with fallback strategies for dynamic content.
 * 
 * @package SRI
 * @author Bubble of Talents Security Team
 * @version 1.0.0
 */

interface SRIResource {
  url: string;
  integrity: string;
  fallback?: string;
  crossorigin?: 'anonymous' | 'use-credentials';
}

/**
 * SRI resource registry for external dependencies
 */
const SRI_REGISTRY: Record<string, SRIResource> = {
  // FontAwesome CDN
  fontawesome: {
    url: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    integrity: 'sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==',
    crossorigin: 'anonymous',
    fallback: '/assets/fonts/fontawesome-fallback.css'
  },

  // Google Fonts - Using stable endpoint
  googleFonts: {
    url: 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
    integrity: '', // Will be generated dynamically
    crossorigin: 'anonymous',
    fallback: '/assets/fonts/inter-fallback.css'
  }
};

/**
 * SRI Manager class for handling external resources securely
 */
export class SRIManager {
  private static loadedResources: Set<string> = new Set();
  private static failedResources: Set<string> = new Set();

  /**
   * Load external resource with SRI verification
   */
  static async loadResource(resourceKey: string): Promise<boolean> {
    const resource = SRI_REGISTRY[resourceKey];

    if (!resource) {
      console.warn(`SRI: Unknown resource key: ${resourceKey}`);
      return false;
    }

    if (this.loadedResources.has(resourceKey)) {
      return true;
    }

    if (this.failedResources.has(resourceKey)) {
      return this.loadFallback(resource);
    }

    try {
      return await this.loadWithSRI(resource, resourceKey);
    } catch (error) {
      console.warn(`SRI: Failed to load ${resourceKey}, using fallback:`, error);
      this.failedResources.add(resourceKey);
      return this.loadFallback(resource);
    }
  }

  /**
   * Load resource with SRI verification
   */
  private static async loadWithSRI(resource: SRIResource, resourceKey: string): Promise<boolean> {
    return new Promise((resolve, reject) => {
      // Comprobar si el recurso ya está cargado en la página
      const existingLinks = document.querySelectorAll('link');
      for (let i = 0; i < existingLinks.length; i++) {
        const link = existingLinks[i];
        if (link && link.getAttribute && link.getAttribute('href') === resource.url) {
          this.loadedResources.add(resourceKey);
          resolve(true);
          return;
        }
      }

      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = resource.url;
      link.crossOrigin = resource.crossorigin || 'anonymous';

      // Set integrity if available
      if (resource.integrity) {
        link.integrity = resource.integrity;
      }

      link.onload = () => {
        this.loadedResources.add(resourceKey);
        resolve(true);
      };

      link.onerror = () => {
        document.head.removeChild(link);
        reject(new Error(`Failed to load ${resource.url}`));
      };

      document.head.appendChild(link);

      // Reducir el timeout a 5 segundos para mejorar el rendimiento
      setTimeout(() => {
        if (!this.loadedResources.has(resourceKey)) {
          document.head.removeChild(link);
          reject(new Error(`Timeout loading ${resource.url}`));
        }
      }, 5000);
    });
  }

  /**
   * Load fallback resource
   */
  private static loadFallback(resource: SRIResource): Promise<boolean> {
    if (!resource.fallback) {
      console.warn('SRI: No fallback available for resource');
      return Promise.resolve(false);
    }

    return new Promise((resolve) => {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = resource.fallback!;

      link.onload = () => {
        // ...eliminado console.log para producción...
        resolve(true);
      };

      link.onerror = () => {
        console.error('SRI: Fallback also failed to load');
        resolve(false);
      };

      document.head.appendChild(link);
    });
  }

  /**
   * Generate SRI hash for a given URL (for development)
   */
  static async generateSRIHash(url: string): Promise<string> {
    try {
      const response = await fetch(url);
      const content = await response.text();

      // Use Web Crypto API to generate SHA-384 hash
      const encoder = new TextEncoder();
      const data = encoder.encode(content);
      const hashBuffer = await crypto.subtle.digest('SHA-384', data);

      // Convert to base64
      const hashArray = new Uint8Array(hashBuffer);
      const hashBase64 = btoa(String.fromCharCode(...hashArray));

      return `sha384-${hashBase64}`;
    } catch (error) {
      console.error('Failed to generate SRI hash:', error);
      return '';
    }
  }

  /**
   * Verify if a resource passes SRI check
   */
  static async verifySRI(url: string, expectedHash: string): Promise<boolean> {
    try {
      const actualHash = await this.generateSRIHash(url);
      return actualHash === expectedHash;
    } catch (error) {
      console.error('SRI verification failed:', error);
      return false;
    }
  }

  /**
   * Initialize SRI for all registered resources
   */
  static async initializeAll(): Promise<void> {
    // ...eliminado console.log para producción...

    const loadPromises = Object.keys(SRI_REGISTRY).map(key =>
      this.loadResource(key).catch(error => {
        console.warn(`Failed to load resource ${key}:`, error);
        return false;
      })
    );

    await Promise.all(loadPromises);
    // Eliminada la variable successCount ya que no se utilizaba

    // ...eliminado console.log para producción...
  }

  /**
   * Get resource registry for debugging
   */
  static getRegistry(): Record<string, SRIResource> {
    return { ...SRI_REGISTRY };
  }

  /**
   * Add new resource to registry
   */
  static addResource(key: string, resource: SRIResource): void {
    SRI_REGISTRY[key] = resource;
  }
}

/**
 * Initialize SRI when DOM is ready
 */
export function initializeSRI(): void {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      SRIManager.initializeAll();
    });
  } else {
    SRIManager.initializeAll();
  }
}

export default SRIManager;
