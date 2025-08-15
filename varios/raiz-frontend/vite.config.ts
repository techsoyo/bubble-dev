import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react-swc';
import path from 'path';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';
import { securityHeadersPlugin } from './config/security-headers-plugin';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    react(),
    securityHeadersPlugin()
  ],
  css: {
    postcss: {
      plugins: [
        tailwindcss(),
        autoprefixer(),
      ],
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    // Optimizaciones de seguridad para producción
    minify: 'terser',
    chunkSizeWarningLimit: 500, // Advertir sobre chunks > 500KB
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
        pure_funcs: ['console.log', 'console.info', 'console.debug'],
        reduce_vars: true,
        unused: true,
      },
      mangle: {
        safari10: true,
      },
    },
    // 🔧 CORRECCIÓN: Configuración del Service Worker
    target: 'es2020',
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, './index.html'),
        sw: path.resolve(__dirname, './src/serviceWorker.ts'),
      },
      output: {
        // Evitar nombres predecibles para archivos de salida (security through obscurity)
        entryFileNames: (chunkInfo) => {
          // Service Worker debe tener nombre específico
          if (chunkInfo.name === 'sw') {
            return 'serviceWorker.js';
          }
          return 'assets/[name]-[hash].js';
        },
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]',
        // Optimización avanzada de chunks
        manualChunks: {
          // Vendor chunks separados para mejor caching
          'vendor-react': ['react', 'react-dom'],
          'vendor-router': ['react-router-dom'],
          'vendor-ui': [
            '@radix-ui/react-dialog',
            '@radix-ui/react-dropdown-menu',
            '@radix-ui/react-select',
            '@radix-ui/react-tabs',
            '@radix-ui/react-toast'
          ],
          'vendor-charts': ['chart.js', 'react-chartjs-2', 'recharts'],
          'vendor-pdf': ['pdfjs-dist'],
          'vendor-form': ['react-hook-form', '@hookform/resolvers', 'zod'],
          'vendor-utils': ['axios', 'date-fns', 'clsx', 'class-variance-authority']
        }
      },
      // Optimizaciones adicionales
      external: (id) => {
        // Externalizar dependencias grandes que se pueden cargar por CDN
        return false; // Por ahora mantenemos todo bundleado
      }
    },
    // Source maps solo en desarrollo
    sourcemap: process.env.NODE_ENV === 'development'
  },
  server: {
    // Configuración inteligente de puertos para evitar conflictos
    host: 'localhost', // Restringir a localhost por seguridad
    port: process.env.VITE_DEV_PORT ? parseInt(process.env.VITE_DEV_PORT) : 3002,
    strictPort: false, // ✅ CRÍTICO: Permitir que Vite busque puertos disponibles automáticamente
    open: true, // Abrir automáticamente en el navegador
    headers: {
      // Headers adicionales que no maneja el plugin
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'Expires': '0'
    },
    // Proxy para evitar CORS en desarrollo si API_BASE_URL apunta a otro host
    proxy: {
      '/backend/api': {
        target: process.env.VITE_API_PROXY_TARGET || 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path.replace(/^\/backend\/api/, '/api')
      },
    },
  }
});