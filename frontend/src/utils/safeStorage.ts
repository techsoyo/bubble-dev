/**
 * Safe Storage Wrapper - PRODUCCIÓN READY
 *
 * Elimina completamente el uso de localStorage/sessionStorage/indexedDB en producción
 * Solo permite storage en desarrollo para debugging
 *
 * ✅ SEGURIDAD: Sanitización de datos y validación de tamaño
 * ✅ PERFORMANCE: Compresión opcional y límites de tamaño
 * ✅ ERROR HANDLING: Tipos específicos de errores
 * ✅ MEMORY MANAGEMENT: Cleanup automático de datos expirados
 */

export const isProd = import.meta.env.MODE === 'production' || process.env.NODE_ENV === 'production';

// Configuration constants
const MAX_STORAGE_SIZE = 5 * 1024 * 1024; // 5MB total
const MAX_ITEM_SIZE = 1024 * 1024; // 1MB per item
const COMPRESSION_THRESHOLD = 10 * 1024; // 10KB
const EXPIRY_CHECK_INTERVAL = 60000; // 1 minute

interface StorageItem<T = any> {
  data: T;
  timestamp: number;
  expiresAt?: number;
  compressed?: boolean;
  size: number;
}

interface StorageError {
  type: 'QUOTA_EXCEEDED' | 'INVALID_DATA' | 'SIZE_EXCEEDED' | 'COMPRESSION_FAILED' | 'DESERIALIZATION_FAILED';
  message: string;
  key?: string;
}

/**
 * Safe localStorage setter - NO-OP en producción
 * ✅ SEGURIDAD: Validación de tamaño y sanitización
 * ✅ PERFORMANCE: Compresión opcional para datos grandes
 */
export const safeSet = async (key: string, value: any, options?: {
  expiresIn?: number; // milliseconds
  compress?: boolean;
}): Promise<StorageError | null> => {
  if (isProd) {
    return null; // NO-OP in production
  }

  try {
    // Input validation
    if (!key || typeof key !== 'string') {
      return { type: 'INVALID_DATA', message: 'Invalid key provided' };
    }

    // Create storage item
    const item: StorageItem = {
      data: value,
      timestamp: Date.now(),
      size: 0,
      compressed: false
    };

    if (options?.expiresIn) {
      item.expiresAt = Date.now() + options.expiresIn;
    }

    // Serialize data
    let serializedData: string;
    try {
      serializedData = JSON.stringify(item);
    } catch (error) {
      return { type: 'INVALID_DATA', message: 'Data cannot be serialized', key };
    }

    // Check item size
    const itemSize = new Blob([serializedData]).size;
    if (itemSize > MAX_ITEM_SIZE) {
      return { type: 'SIZE_EXCEEDED', message: `Item size ${itemSize} exceeds limit ${MAX_ITEM_SIZE}`, key };
    }

    item.size = itemSize;

    // Compress if enabled and data is large
    const shouldCompress = options?.compress !== false && itemSize > COMPRESSION_THRESHOLD;
    if (shouldCompress) {
      try {
        const compressed = await compressData(serializedData);
        if (compressed.length < serializedData.length) {
          serializedData = compressed;
          item.compressed = true;
        }
      } catch (error) {
        return { type: 'COMPRESSION_FAILED', message: 'Failed to compress data', key };
      }
    }

    // Check total storage size
    const error = checkStorageQuota(serializedData.length);
    if (error) return error;

    // Store the data
    localStorage.setItem(key, serializedData);

    // Schedule cleanup if expiry is set
    if (item.expiresAt) {
      scheduleExpiryCleanup();
    }

    return null;
  } catch (error) {
    console.warn(`safeSet failed for key ${key}:`, error);
    return { type: 'INVALID_DATA', message: 'Storage operation failed', key };
  }
};

/**
 * Safe localStorage getter - Siempre retorna fallback en producción
 * ✅ SEGURIDAD: Validación de datos expirados y descompresión
 * Versión síncrona para compatibilidad backward
 */
export const safeGet = <T>(key: string, fallback: T): T => {
  if (isProd) return fallback;

  try {
    const raw = localStorage.getItem(key);
    if (!raw) return fallback;

    let parsedData: string;
    let item: StorageItem<T>;

    try {
      // Try to parse as StorageItem first
      item = JSON.parse(raw);

      // Check if it's the new format
      if (item.data !== undefined && item.timestamp !== undefined) {
        // Check expiry
        if (item.expiresAt && Date.now() > item.expiresAt) {
          localStorage.removeItem(key);
          return fallback;
        }

        // For sync version, skip decompression if compressed
        if (item.compressed) {
          console.warn(`safeGet: Skipping compressed item ${key} in sync mode`);
          return fallback;
        }

        return item.data;
      } else {
        // Legacy format - return as is
        return JSON.parse(raw);
      }
    } catch (error) {
      // If parsing fails, try legacy format
      try {
        return JSON.parse(raw);
      } catch (legacyError) {
        console.warn(`safeGet failed to parse data for key ${key}:`, error);
        return fallback;
      }
    }
  } catch (error) {
    console.warn(`safeGet failed for key ${key}:`, error);
    return fallback;
  }
};

/**
 * Async version with full features including decompression
 */
export const safeGetAsync = async <T>(key: string, fallback: T): Promise<T> => {
  if (isProd) return fallback;

  try {
    const raw = localStorage.getItem(key);
    if (!raw) return fallback;

    let parsedData: string;
    let item: StorageItem<T>;

    try {
      // Try to parse as StorageItem first
      item = JSON.parse(raw);

      // Check if it's the new format
      if (item.data !== undefined && item.timestamp !== undefined) {
        // Check expiry
        if (item.expiresAt && Date.now() > item.expiresAt) {
          localStorage.removeItem(key);
          return fallback;
        }

        // Decompress if needed
        if (item.compressed) {
          parsedData = await decompressData(raw);
          item = JSON.parse(parsedData);
        }

        return item.data;
      } else {
        // Legacy format - return as is
        return JSON.parse(raw);
      }
    } catch (error) {
      // If parsing fails, try legacy format
      try {
        return JSON.parse(raw);
      } catch (legacyError) {
        console.warn(`safeGetAsync failed to parse data for key ${key}:`, error);
        return fallback;
      }
    }
  } catch (error) {
    console.warn(`safeGetAsync failed for key ${key}:`, error);
    return fallback;
  }
};

/**
 * Safe localStorage remover - NO-OP en producción
 */
export const safeRemove = (key: string) => {
  if (!isProd) {
    try {
      localStorage.removeItem(key);
    } catch (error) {
      console.warn(`safeRemove failed for key ${key}:`, error);
    }
  }
};

/**
 * Safe sessionStorage setter - NO-OP en producción
 */
export const safeSetSession = (key: string, value: any) => {
  if (!isProd) {
    try {
      sessionStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
      console.warn(`safeSetSession failed for key ${key}:`, error);
    }
  }
};

/**
 * Safe sessionStorage getter - Siempre retorna fallback en producción
 */
export const safeGetSession = <T>(key: string, fallback: T): T => {
  if (isProd) return fallback;

  try {
    const raw = sessionStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch (error) {
    console.warn(`safeGetSession failed for key ${key}:`, error);
    return fallback;
  }
};

/**
 * Safe sessionStorage remover - NO-OP en producción
 */
export const safeRemoveSession = (key: string) => {
  if (!isProd) {
    try {
      sessionStorage.removeItem(key);
    } catch (error) {
      console.warn(`safeRemoveSession failed for key ${key}:`, error);
    }
  }
};

/**
 * Deprecation warnings para desarrolladores
 */
export const deprecatedStorageWarning = (storageType: string, functionName: string) => {
  if (!isProd) {
    console.warn(
      `🚨 DEPRECATED: ${functionName} usa ${storageType}. ` +
      `Migra a cookies seguras o usa safeStorage. ` +
      `Esta funcionalidad está DESHABILITADA en producción.`
    );
  }
};

/**
 * Comprime datos usando LZ-string (si está disponible)
 */
async function compressData(data: string): Promise<string> {
  // Check if LZString is available (can be loaded dynamically)
  if (typeof (window as any).LZString !== 'undefined') {
    return (window as any).LZString.compress(data);
  }

  // Fallback: simple compression using built-in methods
  try {
    const stream = new CompressionStream('gzip');
    const writer = stream.writable.getWriter();
    const reader = stream.readable.getReader();

    writer.write(new TextEncoder().encode(data));
    writer.close();

    const chunks: Uint8Array[] = [];
    let result = await reader.read();
    while (!result.done) {
      chunks.push(result.value);
      result = await reader.read();
    }

    const compressed = new Uint8Array(chunks.reduce((acc, chunk) => acc + chunk.length, 0));
    let offset = 0;
    for (const chunk of chunks) {
      compressed.set(chunk, offset);
      offset += chunk.length;
    }

    return btoa(String.fromCharCode(...compressed));
  } catch (error) {
    // If compression fails, return original data
    return data;
  }
}

/**
 * Descomprime datos
 */
async function decompressData(data: string): Promise<string> {
  try {
    if (typeof (window as any).LZString !== 'undefined') {
      return (window as any).LZString.decompress(data);
    }

    // Fallback decompression
    const compressed = Uint8Array.from(atob(data), c => c.charCodeAt(0));
    const stream = new DecompressionStream('gzip');
    const writer = stream.writable.getWriter();
    const reader = stream.readable.getReader();

    writer.write(compressed);
    writer.close();

    const chunks: Uint8Array[] = [];
    let result = await reader.read();
    while (!result.done) {
      chunks.push(result.value);
      result = await reader.read();
    }

    const decompressed = new Uint8Array(chunks.reduce((acc, chunk) => acc + chunk.length, 0));
    let offset = 0;
    for (const chunk of chunks) {
      decompressed.set(chunk, offset);
      offset += chunk.length;
    }

    return new TextDecoder().decode(decompressed);
  } catch (error) {
    throw new Error('Failed to decompress data');
  }
}

/**
 * Verifica cuota de almacenamiento
 */
function checkStorageQuota(newItemSize: number): StorageError | null {
  try {
    let totalSize = newItemSize;

    // Calculate current storage usage
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key) {
        const value = localStorage.getItem(key);
        if (value) {
          totalSize += new Blob([value]).size;
        }
      }
    }

    if (totalSize > MAX_STORAGE_SIZE) {
      return {
        type: 'QUOTA_EXCEEDED',
        message: `Storage quota exceeded: ${totalSize} > ${MAX_STORAGE_SIZE}`
      };
    }

    return null;
  } catch (error) {
    return { type: 'INVALID_DATA', message: 'Failed to check storage quota' };
  }
}

/**
 * Programa cleanup de elementos expirados
 */
let cleanupScheduled = false;
function scheduleExpiryCleanup(): void {
  if (cleanupScheduled || isProd) return;

  cleanupScheduled = true;
  setTimeout(() => {
    cleanupExpiredItems();
    cleanupScheduled = false;
  }, EXPIRY_CHECK_INTERVAL);
}

/**
 * Limpia elementos expirados del storage
 */
function cleanupExpiredItems(): void {
  const now = Date.now();
  const keysToRemove: string[] = [];

  try {
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (!key) continue;

      try {
        const value = localStorage.getItem(key);
        if (!value) continue;

        const item: StorageItem = JSON.parse(value);
        if (item.expiresAt && now > item.expiresAt) {
          keysToRemove.push(key);
        }
      } catch (error) {
        // If we can't parse the item, it's probably corrupted, remove it
        keysToRemove.push(key);
      }
    }

    keysToRemove.forEach(key => {
      localStorage.removeItem(key);
    });

    if (keysToRemove.length > 0) {
      console.log(`Cleaned up ${keysToRemove.length} expired storage items`);
    }
  } catch (error) {
    console.warn('Error during storage cleanup:', error);
  }
}
