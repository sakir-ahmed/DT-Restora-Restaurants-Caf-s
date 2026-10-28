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
            includeAssets: ['favicon.ico', 'favicon.png'],
            manifest: {
                name: "Arman's Kitchen POS",
                short_name: "Arman's Kitchen",
                description: "Arman's Kitchen restaurant POS, works offline",
                start_url: '/admin/pos',
                theme_color: '#17120b',
                background_color: '#0e0b07',
                display: 'standalone',
                icons: [
                    { src: '/images/brand/emblem-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
                    { src: '/images/brand/emblem-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
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
