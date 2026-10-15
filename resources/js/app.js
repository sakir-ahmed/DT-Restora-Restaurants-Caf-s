import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, defineComponent, h } from 'vue';
import { Toaster } from 'vue-sonner';
import ConfirmHost from './components/ui-kit/ConfirmHost.vue';

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

        app.use(plugin);

        app.mount(el);
    },
});
