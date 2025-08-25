// src/lib/i18n/LanguageContext.tsx
import React, { createContext, useState, useContext, useEffect, ReactNode } from 'react';
import { en, es } from './translations';
import { languageService } from './languageService';
import { safeGet, safeSet } from '../../utils/safeStorage';

type Language = 'en' | 'es';
type Translations = typeof en | typeof es;

interface LanguageContextType {
  language: Language;
  setLanguage: (lang: Language) => void;
  t: (key: string) => any;
}

const defaultLanguage: Language = 'es';

// Create the context
const LanguageContext = createContext<LanguageContextType | undefined>(undefined);

// Create a provider component
export const LanguageProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  // Try to get the language from safe storage, or use default
  const [language, setLanguageState] = useState<Language>(() => {
    const savedLanguage = safeGet<Language>('language', defaultLanguage);
    return savedLanguage;
  });

  // Efecto para cargar el idioma del backend al iniciar la aplicación
  useEffect(() => {
    const fetchLanguage = async () => {
      try {
        const backendLanguage = await languageService.getLanguage();
        if (backendLanguage !== language) {
          setLanguageState(backendLanguage);
          safeSet('language', backendLanguage);
        }
      } catch (error) {
        console.error('Error al obtener el idioma del backend:', error);
      }
    };

    fetchLanguage();
  }, []);

  // Save language to safe storage when it changes (no-op in production)
  useEffect(() => {
    safeSet('language', language);
  }, [language]);

  // Function to change the language
  const setLanguage = async (lang: Language) => {
    setLanguageState(lang);

    // Sincronizar con el backend
    try {
      await languageService.setLanguage(lang);
    } catch (error) {
      console.error('Error al sincronizar el idioma con el backend:', error);
    }
  };

  // Function to get translations
  const t = (key: string): any => {
    const translations: Translations = language === 'en' ? en : es;

    // Split the key by dots to access nested properties
    const keys = key.split('.');
    let value: any = translations;

    // Navigate through the object
    for (const k of keys) {
      if (value && typeof value === 'object' && k in value) {
        value = value[k];
      } else {
        console.warn(`Translation key not found: ${key}`);
        return key; // Return the key itself if translation not found
      }
    }

    return value;
  };

  return (
    <LanguageContext.Provider value={{ language, setLanguage, t }}>
      {children}
    </LanguageContext.Provider>
  );
};

// Hook to use the language context
export const useLanguage = (): LanguageContextType => {
  const context = useContext(LanguageContext);
  if (context === undefined) {
    throw new Error('useLanguage must be used within a LanguageProvider');
  }
  return context;
};
