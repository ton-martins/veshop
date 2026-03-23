import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

if (typeof document !== 'undefined') {
    document.addEventListener('inertia:invalid', (event) => {
        const response = event?.detail?.response ?? null;
        const payload = response?.data ?? null;

        const looksLikeInertiaPayload = isObject(payload)
            && typeof payload.component === 'string'
            && isObject(payload.props)
            && typeof payload.url === 'string'
            && Object.prototype.hasOwnProperty.call(payload, 'version');

        if (!looksLikeInertiaPayload) {
            return;
        }

        event.preventDefault();

        // Fail-safe for proxies/CDNs stripping X-Inertia response header.
        const targetUrl = String(payload.url || response?.request?.responseURL || window.location.href);
        if (targetUrl) {
            window.location.assign(targetUrl);
        }
    });
}

createInertiaApp({
    title: (title) => String(title ?? '').trim(),
    resolve: (name) => {
        const pages = {
            ...import.meta.glob('./Pages/**/*.vue'),
            ...import.meta.glob('./pages/**/*.vue'),
        };

        const candidatePaths = [`./pages/${name}.vue`, `./Pages/${name}.vue`];
        const resolvedPath = candidatePaths.find((path) => Object.prototype.hasOwnProperty.call(pages, path));

        if (!resolvedPath) {
            return resolvePageComponent(`./Pages/${name}.vue`, pages);
        }

        return resolvePageComponent(resolvedPath, pages);
    },
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);

        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('veshop:app-mounted'));
        }

        return vueApp;
    },
    progress: {
        color: '#4B5563',
    },
});
