import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Veshop';
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
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
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
