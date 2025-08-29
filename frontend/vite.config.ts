import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react-swc';
import path from 'path';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    react(),
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
  server: {
    port: 3002,
    host: true,
    proxy: {
      '/api': {
        target: process.env.VITE_API_PROXY_TARGET || 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path,
      },
    },
    // Security headers for development
    headers: {
      'X-Content-Type-Options': 'nosniff',
      'X-Frame-Options': 'DENY',
      'X-XSS-Protection': '1; mode=block',
      'Referrer-Policy': 'strict-origin-when-cross-origin',
      'Permissions-Policy': 'camera=(), microphone=(), geolocation=()',
    },
  },
  build: {
    outDir: 'dist',
    target: 'es2020',
    minify: 'terser',
    chunkSizeWarningLimit: 500,
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
        reduce_vars: true,
        unused: true,
      },
      mangle: {
        safari10: true,
      },
    },
    rollupOptions: {
      output: {
        // Enhanced chunking strategy for better caching and smaller chunks
        manualChunks: (id) => {
          if (id.includes('node_modules')) {
            return getVendorChunk(id);
          }
          return getAppChunk(id);
        },
        // Optimize chunk size with better naming
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]',
      },
    },
    // Additional optimizations
    reportCompressedSize: false, // Skip gzip size reporting for faster builds
    cssCodeSplit: true, // Split CSS into separate files
    sourcemap: process.env.NODE_ENV === 'development', // Only in development for security
  },
  define: {
    __APP_VERSION__: JSON.stringify(process.env.npm_package_version),
  },
});

// Helper functions to reduce complexity
function getVendorChunk(id: string): string {
  return getReactVendor(id) || getRouterVendor(id) || getUIVendor(id) || getChartsVendor(id) || getFormsVendor(id) || getStateVendor(id) || getUtilsVendor(id) || getIconsVendor(id) || getFileVendor(id) || 'vendor-other';
}

function getAppChunk(id: string): string | undefined {
  return getPagesChunk(id) || getComponentsChunk(id) || getUtilsChunk(id);
}

function getPagesChunk(id: string): string | undefined {
  if (id.includes('/src/pages/dashboard/HRDashboard')) return 'page-hr-dashboard';
  if (id.includes('/src/pages/dashboard/Estadisticas')) return 'page-estadisticas';
  if (id.includes('/src/pages/dashboard/')) return 'pages-dashboard';
  if (id.includes('/src/pages/jobs/')) return 'pages-jobs';
  if (id.includes('/src/pages/auth/')) return 'pages-auth';
  return undefined;
}

function getComponentsChunk(id: string): string | undefined {
  if (id.includes('/src/components/UploadCV')) return 'component-upload-cv';
  if (id.includes('/src/components/admin/AdminPanel')) return 'page-admin';
  if (id.includes('/src/components/ui/')) return 'components-ui';
  if (id.includes('/src/components/forms/')) return 'components-forms';
  if (id.includes('/src/components/dashboard/')) return 'components-dashboard';
  if (id.includes('/src/components/')) return 'components-other';
  return undefined;
}

function getUtilsChunk(id: string): string | undefined {
  if (id.includes('/src/lib/')) return 'lib';
  if (id.includes('/src/utils/')) return 'utils';
  if (id.includes('/src/contexts/')) return 'contexts';
  return undefined;
}

function getReactVendor(id: string): string | undefined {
  if (id.includes('react-dom')) return 'vendor-react-dom';
  if (id.includes('react') || id.includes('scheduler')) return 'vendor-react';
  if (id.includes('prop-types')) return 'vendor-react';
  return undefined;
}

function getRouterVendor(id: string): string | undefined {
  if (id.includes('react-router')) return 'vendor-router';
  return undefined;
}

function getUIVendor(id: string): string | undefined {
  if (id.includes('@radix-ui/react-dialog') || id.includes('@radix-ui/react-dropdown-menu')) return 'vendor-radix-core';
  if (id.includes('@radix-ui/react-select') || id.includes('@radix-ui/react-tabs')) return 'vendor-radix-forms';
  if (id.includes('@radix-ui')) return 'vendor-radix-other';
  return undefined;
}

function getChartsVendor(id: string): string | undefined {
  if (id.includes('recharts')) return 'vendor-charts-recharts';
  if (id.includes('chart.js') || id.includes('react-chartjs-2')) return 'vendor-charts-chartjs';
  return undefined;
}

function getFormsVendor(id: string): string | undefined {
  if (id.includes('react-hook-form') || id.includes('@hookform')) return 'vendor-forms';
  if (id.includes('zod')) return 'vendor-validation';
  return undefined;
}

function getStateVendor(id: string): string | undefined {
  if (id.includes('@tanstack/react-query')) return 'vendor-query';
  return undefined;
}

function getUtilsVendor(id: string): string | undefined {
  if (id.includes('date-fns')) return 'vendor-date';
  if (id.includes('clsx') || id.includes('tailwind-merge') || id.includes('class-variance-authority')) return 'vendor-utils';
  return undefined;
}

function getIconsVendor(id: string): string | undefined {
  if (id.includes('lucide-react')) return 'vendor-icons-lucide';
  if (id.includes('react-icons')) return 'vendor-icons-react';
  if (id.includes('framer-motion')) return 'vendor-animations';
  if (id.includes('aos')) return 'vendor-animations-aos';
  return undefined;
}

function getFileVendor(id: string): string | undefined {
  if (id.includes('pdfjs-dist')) return 'vendor-pdf';
  if (id.includes('dompurify')) return 'vendor-security';
  return undefined;
}
