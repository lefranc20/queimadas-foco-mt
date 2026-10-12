import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [vue(), tailwindcss()],
        server: {
            port: 5173,
            // Em desenvolvimento, /api é repassado ao backend (php artisan serve)
            proxy: {
                '/api': env.VITE_PROXY_API || 'http://127.0.0.1:8000',
            },
        },
        build: {
            rollupOptions: {
                output: {
                    manualChunks: (id) => {
                        if (id.includes('node_modules/leaflet/')) return 'leaflet';
                        if (id.includes('node_modules/chart.js')) return 'chartjs';
                    },
                },
            },
        },
    };
});
