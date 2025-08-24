// Export all lib files from a central location

// Authentication
export * from './auth/AuthContext';

// API and services
export * from './api';
//export * from './apiService';

// Data and constants
// 🚫 DEPRECATED: Los siguientes archivos ya no se usan - ahora datos de DB
// export * from './constants'; 
// export * from './constants-users'; 
// export * from './departments';
// export * from './department-recruiters';
// export * from './category-department-mapping';
// export * from './candidate-department-assignment';
// export * from './constants-departments';
// export * from './db-mapping';

// Internationalization
export * from './i18n/LanguageContext';
export * from './i18n/languageService';
export * from './i18n/translations';

// Utilities
export * from './utils';
