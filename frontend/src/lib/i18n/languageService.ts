// src/lib/i18n/languageService.ts
import { api } from '../api';

/**
 * Servicio para sincronizar el idioma con el backend
 */
export const languageService = {
  /**
   * Establece el idioma en el backend
   * @param language Código de idioma (es, en)
   */
  async setLanguage(language: 'es' | 'en'): Promise<void> {
    try {
      await api.post('/language.php', { language });
    } catch (error) {
      console.error('Error al establecer el idioma en el backend:', error);
    }
  },

  /**
   * Obtiene el idioma actual del backend
   */
  async getLanguage(): Promise<'es' | 'en'> {
    try {
      const response = await api.get('/language.php');
      return response.data.data.language;
    } catch (error) {
      console.error('Error al obtener el idioma del backend:', error);
      return 'es'; // Valor por defecto
    }
  }
};
