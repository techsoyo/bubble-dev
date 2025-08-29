/**
 * Módulo para cargar scripts de análisis de manera optimizada
 * 
 * Carga scripts de análisis de rendimiento como Google Analytics,
 * Hotjar, etc. de forma diferida para no impactar el rendimiento inicial
 */

import { isProduction } from '../config/env';

// IDs de análisis
const ANALYTICS_IDS = {
  googleAnalytics: 'G-EXAMPLE123456',
  hotjar: '1234567',
  clarity: 'abcdef123456'
};

/**
 * Carga los scripts de análisis de manera diferida
 */
export const loadAnalytics = () => {
  if (!isProduction) {
    return;
  }

  // Esperar a que la página esté completamente cargada
  window.addEventListener('load', () => {
    // Usar setTimeout para priorizar la interactividad del usuario
    setTimeout(() => {
      loadGoogleAnalytics();
      loadHotjar();
      loadClarity();
    }, 3500); // Esperar 3.5 segundos después de la carga
  });
};

/**
 * Carga Google Analytics de manera diferida
 */
/**
 * Carga Google Analytics de manera segura y diferida
 */
const loadGoogleAnalytics = () => {
  const id = ANALYTICS_IDS.googleAnalytics;

  // Crear y configurar el elemento script externo
  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${id}`;

  // Crear configuración de Google Analytics de forma segura
  const configScript = document.createElement('script');
  configScript.type = 'text/javascript';

  // ✅ SEGURIDAD: Usar textContent en lugar de innerHTML
  configScript.textContent = `
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '${id}', { 'anonymize_ip': true, 'send_page_view': false });
    
    // Enviar vista de página después de carga completa
    if (document.readyState === 'complete') {
      gtag('event', 'page_view');
    } else {
      window.addEventListener('load', () => {
        setTimeout(() => {
          gtag('event', 'page_view');
        }, 100);
      });
    }
  `;

  // Añadir scripts al documento de forma segura
  document.head.appendChild(script);
  document.head.appendChild(configScript);
};

/**
 * Carga Hotjar de manera diferida y segura
 */
const loadHotjar = () => {
  const id = ANALYTICS_IDS.hotjar;

  const script = document.createElement('script');
  script.type = 'text/javascript';

  // ✅ SEGURIDAD: Usar textContent en lugar de innerHTML
  script.textContent = `
    (function(h,o,t,j,a,r){
      h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
      h._hjSettings={hjid:${id},hjsv:6};
      a=o.getElementsByTagName('head')[0];
      r=o.createElement('script');r.async=1;
      r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
      a.appendChild(r);
    })(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
  `;

  document.head.appendChild(script);
};

/**
 * Carga Microsoft Clarity de manera diferida y segura
 */
const loadClarity = () => {
  const id = ANALYTICS_IDS.clarity;

  const script = document.createElement('script');
  script.type = 'text/javascript';

  // ✅ SEGURIDAD: Usar textContent en lugar de innerHTML
  script.textContent = `
    (function(c,l,a,r,i,t,y){
      c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
      t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
      y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "${id}");
  `;

  document.head.appendChild(script);
};

export default loadAnalytics;
