// src/lib/googleTranslation.ts

import * as React from 'react';
import { getApiBaseUrl } from '../hooks/useApiConfig';

const API_BASE_URL = getApiBaseUrl();

// Cache para traducciones para evitar llamadas repetidas
const translationCache = new Map<string, string>();

// Función para crear clave de cache
function getCacheKey(text: string, targetLanguage: string, sourceLanguage: string = 'es'): string {
  return `${sourceLanguage}-${targetLanguage}-${text}`;
}

// Función para traducir texto usando Google Translate
export async function translateWithGoogle(
  text: string,
  targetLanguage: 'en' | 'es' = 'en',
  sourceLanguage: 'en' | 'es' = 'es'
): Promise<string> {
  if (!text || text.trim() === '') return text;

  // Si el idioma objetivo es el mismo que el origen, devolver texto original
  if (targetLanguage === sourceLanguage) {
    return text;
  }

  // Clave de cache
  const cacheKey = getCacheKey(text, targetLanguage, sourceLanguage);

  // Verificar cache primero
  if (translationCache.has(cacheKey)) {
    return translationCache.get(cacheKey)!;
  }

  try {
    // API_BASE_URL puede incluir "/api", evitar duplicarlo
    const response = await fetch(`${API_BASE_URL}/jobs?action=translate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        text: text,
        targetLanguage: targetLanguage,
        sourceLanguage: sourceLanguage
      }),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    if (result.success && result.translation) {
      // Guardar en cache
      translationCache.set(cacheKey, result.translation);
      return result.translation;
    } else {
      console.warn('Google translation failed, returning original text:', result.error);
      return text;
    }
  } catch (error) {
    console.error('Error en traducción Google:', error);
    // En caso de error, devolver el texto original
    return text;
  }
}

// Función específica para traducir múltiples textos de un trabajo
export async function translateJobData(
  job: { description: string; category: string; type: string; title?: string },
  targetLanguage: 'en' | 'es' = 'en'
): Promise<{ description: string; category: string; type: string; title?: string }> {

  if (targetLanguage === 'es') {
    return job; // Devolver original si es español
  }

  try {
    // Traducir múltiples campos en paralelo
    const translations = await Promise.all([
      translateWithGoogle(job.description, targetLanguage),
      translateWithGoogle(job.category, targetLanguage),
      translateWithGoogle(job.type, targetLanguage),
      job.title ? translateWithGoogle(job.title, targetLanguage) : Promise.resolve(job.title)
    ]);

    return {
      description: translations[0],
      category: translations[1],
      type: translations[2],
      ...(job.title && { title: translations[3] })
    };
  } catch (error) {
    console.error('Error traduciendo datos del trabajo:', error);
    return job; // Devolver original en caso de error
  }
}

// Hook personalizado para usar traducciones Google en componentes React
export function useGoogleTranslation() {
  const [isTranslating, setIsTranslating] = React.useState(false);

  const translateText = async (
    text: string,
    targetLanguage: 'en' | 'es' = 'en',
    sourceLanguage: 'en' | 'es' = 'es'
  ) => {
    setIsTranslating(true);
    try {
      const translated = await translateWithGoogle(text, targetLanguage, sourceLanguage);
      return translated;
    } finally {
      setIsTranslating(false);
    }
  };

  return { translateText, isTranslating };
}

// Limpiar cache (útil para desarrollo)
export function clearTranslationCache() {
  translationCache.clear();
}

// Obtener estadísticas del cache
export function getCacheStats() {
  return {
    size: translationCache.size,
    entries: Array.from(translationCache.entries())
  };
}
