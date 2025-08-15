/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_API_URL: string;
    readonly VITE_APP_TITLE: string;
    readonly VITE_ENVIRONMENT: string;
    readonly VITE_CV_ALLOW_MANUAL_ONLY?: string; // 'true' | 'false'
    readonly MODE: string;
    readonly BASE_URL: string;
    readonly PROD: boolean;
    readonly DEV: boolean;
    readonly SSR: boolean;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}

declare global {
    interface Window {
        ENV: {
            VITE_API_URL: string;
            VITE_ENVIRONMENT: string;
        };
    }
}
