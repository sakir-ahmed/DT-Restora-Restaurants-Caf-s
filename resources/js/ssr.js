import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { renderToString } from 'vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { pageTitle } from './lib/title';

/**
 * Server-side rendering for the public website pages. Only pages the
 * server decides to render (see Inertia::withoutSsr in AppServiceProvider)
 * ever reach this entry point.
 */
createServer((page) => createInertiaApp({
    page,
    render: renderToString,
    title: pageTitle,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
    setup: ({ App, props, plugin }) => createSSRApp({ render: () => h(App, props) }).use(plugin),
}));
