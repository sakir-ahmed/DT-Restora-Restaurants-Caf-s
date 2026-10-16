import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),
        vue(),
        tailwindcss(),
        VitePWA({
            registerType: 'prompt',
            scope: '/',
            includeAssets: ['favicon.ico', 'favicon.svg'],
            manifest: {
                name: 'DT Restora POS',
                short_name: 'DT Restora',
                description: 'Offline-first restaurant POS',
                theme_color: '#0f172a',
                background_color: '#f8fafc',
                display: 'standalone',
                icons: [
                    { src: '/favicon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,svg,png,woff2,ico}'],
                navigateFallback: null,
                runtimeCaching: [
                    {
                        urlPattern: ({ url, request }) => request.method === 'GET' && (url.pathname.startsWith('/admin') || url.pathname.startsWith('/rider') || url.pathname === '/login'),
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'app-shell',
                            networkTimeoutSeconds: 3,
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                ],
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
