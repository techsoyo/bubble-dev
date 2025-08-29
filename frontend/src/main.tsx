// src/main.tsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './index.css';

// Service Worker Registration
import { registerServiceWorker, setupConnectivityDetection } from './lib/serviceWorkerRegistration';

// Security: Initialize SRI Manager for external resources
import { SRIManager } from './security/sri';
// Security: Initialize XSS Protection System
import { initXSSProtection } from './security/xss';

// Initialize security for external resources
const initializeSecurity = async () => {
  // Load Google Fonts with SRI verification
  const googleFontsLoaded = await SRIManager.loadResource('googleFonts');

  // Load FontAwesome with SRI verification
  const fontAwesomeLoaded = await SRIManager.loadResource('fontawesome');

  if (googleFontsLoaded && fontAwesomeLoaded) {
  } else {
    console.warn('⚠️ Security: Some resources failed, fallbacks active');
  }
};

// Solo usar StrictMode en desarrollo para evitar doble renderizado en producción
const StrictModeWrapper = ({ children }: { children: React.ReactNode }) => {
  return import.meta.env.DEV ? (
    <React.StrictMode>{children}</React.StrictMode>
  ) : (
    <>{children}</>
  );
};

// Render first, then initialize security in parallel
createRoot(document.getElementById('root')!).render(
  <StrictModeWrapper>
    <App />
  </StrictModeWrapper>
);

// Registrar el Service Worker y configurar detección de conectividad
registerServiceWorker();
setupConnectivityDetection();

// Activar protecciones XSS y rate limiting
initXSSProtection();

// Initialize security in the background without blocking rendering
initializeSecurity().then(() => {
}).catch((error) => {
  console.warn('⚠️ Security: SRI initialization failed, continuing with fallbacks', error);
});
