import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, defineComponent, h } from 'vue';
import { Toaster } from 'vue-sonner';
import { ZiggyVue, route as routeFn } from 'ziggy-js';
import ConfirmHost from './components/ui-kit/ConfirmHost.vue';
import UpdatePrompt from './components/ui-kit/UpdatePrompt.vue';
import { registerSW } from 'virtual:pwa-register';
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
                    h(UpdatePrompt),
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

// Prompt before applying a new service worker. Nothing reloads until the cashier chooses.
if (import.meta.env.PROD) {
    const updateSW = registerSW({
        immediate: true,
        onNeedRefresh() {
            window.dispatchEvent(new CustomEvent('pwa-update', {
                detail: { apply: () => updateSW(true) },
            }));
        },
    });
}
