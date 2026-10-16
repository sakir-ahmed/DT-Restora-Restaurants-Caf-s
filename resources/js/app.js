import '../css/app.css';
import 'vue-sonner/style.css';

import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, createSSRApp, defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Toaster } from 'vue-sonner';
import { ZiggyVue, route as routeFn } from 'ziggy-js';
import DemoBanner from './components/DemoBanner.vue';
import ConfirmHost from './components/ui-kit/ConfirmHost.vue';
import UpdatePrompt from './components/ui-kit/UpdatePrompt.vue';
import { registerSW } from 'virtual:pwa-register';
import { t } from './lib/i18n';
import { notify } from './lib/notify';
import { Ziggy } from './ziggy';

createInertiaApp({
    title: (title) => (title ? `${title} - ${import.meta.env.VITE_APP_NAME ?? 'DT Restora'}` : import.meta.env.VITE_APP_NAME ?? 'DT Restora'),

    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),

    setup({ el, App, props, plugin }) {
        // Root component keeps persistent UI (confirm dialogs, toasts)
        // alive across page navigation.
        // Client-only widgets mount after hydration so the server markup matches.
        const Root = defineComponent({
            setup() {
                const mounted = ref(false);
                const page = usePage();

                // Toasts follow the site's own light/dark switch, not only the OS setting.
                const dark = ref(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));
                let themeObserver = null;

                // SMS_DEMO: show every captured SMS as a toast with its full text.
                const showDemoSms = (items) => (items ?? []).forEach((entry) => notify.sms(entry));

                onMounted(() => {
                    mounted.value = true;
                    dark.value = document.documentElement.classList.contains('dark');
                    themeObserver = new MutationObserver(() => {
                        dark.value = document.documentElement.classList.contains('dark');
                    });
                    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                    showDemoSms(page.props?.demoSms);
                });

                watch(() => page.props?.demoSms, showDemoSms);

                onBeforeUnmount(() => themeObserver?.disconnect());

                return () => [
                    page.props?.isDemo ? h(DemoBanner) : null,
                    h(App, props),
                    mounted.value && page.props?.smsDemo
                        ? h('div', {
                            role: 'status',
                            class: 'pointer-events-none fixed bottom-3 left-3 z-[60] max-w-[calc(100vw-1.5rem)] rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-medium text-muted shadow-sm',
                        }, t('common.demo_sms_badge'))
                        : null,
                    mounted.value ? h(ConfirmHost) : null,
                    mounted.value ? h(UpdatePrompt) : null,
                    mounted.value
                        ? h(Toaster, {
                            theme: dark.value ? 'dark' : 'light',
                            offset: page.props?.isDemo ? '32px' : undefined,
                            position: 'top-right',
                            duration: 3000,
                            closeButton: true,
                            expand: true,
                            visibleToasts: 4,
                        })
                        : null,
                ];
            },
        });

        const app = el.hasAttribute('data-server-rendered') ? createSSRApp(Root) : createApp(Root);

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
