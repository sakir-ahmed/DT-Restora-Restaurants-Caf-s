import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, defineComponent, h } from 'vue';
import { Toaster } from 'vue-sonner';
import { ZiggyVue, route as routeFn } from 'ziggy-js';
import ConfirmHost from './components/ui-kit/ConfirmHost.vue';
import { Ziggy } from './ziggy';

createInertiaApp({
    title: (title) => (title ? `${title} - ${import.meta.env.VITE_APP_NAME ?? 'DT Restora'}` : import.meta.env.VITE_APP_NAME ?? 'DT Restora'),

    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),

    setup({ el, App, props, plugin }) {
        // Root component keeps persistent UI (confirm dialogs, toasts)
        // alive across page navigation.
        const Root = defineComponent({
            setup() {
                return () => [
                    h(App, props),
                    h(ConfirmHost),
                    h(Toaster, {
                        position: 'top-right',
                        duration: 3000,
                        closeButton: true,
                        expand: false,
                    }),
                ];
            },
        });

        const app = createApp(Root);

        window.Ziggy = { ...Ziggy, url: window.location.origin };
        window.route = (name, params, absolute, config = window.Ziggy) =>
            // eslint-disable-next-line no-undef
            routeFn(name, params, absolute, config);

        app.use(plugin);
        app.use(ZiggyVue);

        app.mount(el);
    },
});

// PWA service worker registration (no reload mid-order: prompt type).
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // offline mode unavailable; the app still works online
        });
    });
}
