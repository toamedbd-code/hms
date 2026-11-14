import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
// Use import.meta.glob to resolve pages without laravel-vite-plugin
import { ZiggyVue } from 'ziggy-js';
import { Ziggy } from './ziggy.js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pages = import.meta.glob('./Pages/**/*.vue');

async function resolvePage(name) {
    const path = `./Pages/${name}.vue`;
    const importer = pages[path];
    if (!importer) throw new Error(`Page not found: ${path}`);
    const mod = await importer();
    return mod.default;
}

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) => resolvePage(name),
        setup({ App, props, plugin }) {
            const location = new URL(page.props.ziggy.location);

            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue, {
                    ...Ziggy,
                    url: location.origin,
                    location,
                });
        },
    })
);
