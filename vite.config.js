import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

// Workbox copies each function below into the worker as text, so none of them may use
// a variable from this file. Screens of the installed app (kept for offline use) are
// /admin, /rider and /login; /offline is stored at install time as the page shown for a
// screen that was never opened on this device.

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
            // The cashier decides when a new version applies; see lib/pwa.js.
            registerType: 'prompt',
            // lib/pwa.js registers the worker itself, and the manifest comes from Brand::manifest().
            injectRegister: false,
            manifest: false,
            // Served from /sw.js (see PwaController), so it must not import other files:
            // a separately cached import would hide new releases.
            workbox: {
                inlineWorkboxRuntime: true,
                cleanupOutdatedCaches: true,
                clientsClaim: true,
                // Built files are listed relative to /build, but the worker is served from the root.
                modifyURLPrefix: { '': '/build/' },
                globPatterns: ['**/*.{js,css,svg,png,woff2,ico}'],
                additionalManifestEntries: [{ url: '/offline', revision: String(Date.now()) }],
                navigateFallback: null,
                runtimeCaching: [
                    {
                        // Opening a screen (reload, installed app launch, typed address).
                        urlPattern: ({ request, url }) => request.mode === 'navigate' && (url.pathname.startsWith('/admin') || url.pathname.startsWith('/rider') || url.pathname === '/login'),
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'pages',
                            networkTimeoutSeconds: 3,
                            cacheableResponse: { statuses: [200] },
                            expiration: { maxEntries: 60 },
                            plugins: [{
                                // Nothing on the network and nothing saved: show the offline page.
                                handlerDidError: async () => (await caches.match('/offline', { ignoreSearch: true })) ?? Response.error(),
                            }],
                        },
                    },
                    {
                        // Any other address, such as the public website: needs the network.
                        urlPattern: ({ request, url }) => request.mode === 'navigate' && !(url.pathname.startsWith('/admin') || url.pathname.startsWith('/rider') || url.pathname === '/login') && url.pathname !== '/offline',
                        handler: 'NetworkOnly',
                        options: {
                            plugins: [{
                                handlerDidError: async () => (await caches.match('/offline', { ignoreSearch: true })) ?? Response.error(),
                            }],
                        },
                    },
                    {
                        // Moving between screens inside the app is a data request, not a page load.
                        urlPattern: ({ request, url }) => request.method === 'GET' && request.headers.has('X-Inertia') && (url.pathname.startsWith('/admin') || url.pathname.startsWith('/rider') || url.pathname === '/login'),
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'screens',
                            networkTimeoutSeconds: 3,
                            cacheableResponse: { statuses: [200] },
                            expiration: { maxEntries: 100 },
                        },
                    },
                    {
                        // Logos, menu photos and uploads.
                        urlPattern: ({ request, url }) => request.method === 'GET' && (url.pathname.startsWith('/images/') || url.pathname.startsWith('/storage/')),
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'media',
                            cacheableResponse: { statuses: [200] },
                            expiration: { maxEntries: 150, maxAgeSeconds: 60 * 60 * 24 * 30 },
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
