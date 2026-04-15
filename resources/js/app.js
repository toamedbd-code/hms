import './bootstrap';
import '../css/app.css';
import FeatherIcon from './Components/FeatherIcon.vue';


import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
// dynamic import resolver (avoid laravel-vite-plugin dependency)
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'base-laravel-inertiajs';
let runtimeAppName = appName;
const pages = import.meta.glob('./Pages/**/*.vue');

const resolveInitialInertiaPage = (appElement) => {
    const inlinePage = appElement?.dataset?.page;
    if (typeof inlinePage === 'string' && inlinePage.trim() !== '' && inlinePage.trim() !== 'undefined') {
        return JSON.parse(inlinePage);
    }

    if (typeof document !== 'undefined') {
        const pageScript = document.querySelector('script[type="application/json"][data-page="app"]')
            ?? document.querySelector('script[type="application/json"][data-page]');

        const scriptPayload = pageScript?.textContent;
        if (typeof scriptPayload === 'string' && scriptPayload.trim() !== '' && scriptPayload.trim() !== 'undefined') {
            return JSON.parse(scriptPayload);
        }
    }

    throw new Error('Unable to resolve Inertia initial page payload.');
};

const themeTokens = {
    default: { primary: '#64748b', soft: '#f1f5f9', contrast: '#334155', surface: '#ffffff' },
    red: { primary: '#dc2626', soft: '#fecaca', contrast: '#7f1d1d', surface: '#fff1f2' },
    blue: { primary: '#1d4ed8', soft: '#bfdbfe', contrast: '#1e3a8a', surface: '#eff6ff' },
    gray: { primary: '#475569', soft: '#cbd5e1', contrast: '#1e293b', surface: '#f8fafc' },
    emerald: { primary: '#059669', soft: '#a7f3d0', contrast: '#065f46', surface: '#ecfdf5' },
    amber: { primary: '#d97706', soft: '#fde68a', contrast: '#92400e', surface: '#fffbeb' },
    rose: { primary: '#e11d48', soft: '#fecdd3', contrast: '#9f1239', surface: '#fff1f2' },
    indigo: { primary: '#4f46e5', soft: '#c7d2fe', contrast: '#3730a3', surface: '#eef2ff' },
};

const hexToRgb = (hex) => {
    const value = String(hex || '').replace('#', '').trim();
    if (!/^[0-9a-fA-F]{6}$/.test(value)) {
        return '37 99 235';
    }

    const number = parseInt(value, 16);
    const r = (number >> 16) & 255;
    const g = (number >> 8) & 255;
    const b = number & 255;

    return `${r} ${g} ${b}`;
};

const normalizeTheme = (theme) => {
    if (typeof theme !== 'string' || theme.trim() === '') {
        return 'default';
    }

    const normalized = theme.trim().toLowerCase();
    return themeTokens[normalized] ? normalized : 'default';
};

const setTheme = (themeName) => {
    if (typeof document === 'undefined') {
        return;
    }

    const name = normalizeTheme(themeName);
    const tokens = themeTokens[name];
    const root = document.documentElement;

    root.style.setProperty('--app-theme-primary', tokens.primary);
    root.style.setProperty('--app-theme-soft', tokens.soft);
    root.style.setProperty('--app-theme-contrast', tokens.contrast);
    root.style.setProperty('--app-theme-surface', tokens.surface);
    root.style.setProperty('--app-theme-primary-rgb', hexToRgb(tokens.primary));

    Object.keys(themeTokens).forEach((key) => root.classList.remove(`theme-${key}`));
    root.classList.add(`theme-${name}`);
};

const resolveFavicon = (webSetting) => {
    try {
        const version = webSetting?.updated_at || Date.now();
        if (typeof route === 'function' && route().has('backend.favicon.dynamic')) {
            return route('backend.favicon.dynamic', { v: encodeURIComponent(version) });
        }
    } catch (_) {
        // fallback to image field resolution below
    }

    if (!webSetting || typeof webSetting !== 'object') {
        return null;
    }

    const logo = typeof webSetting.logo === 'string' ? webSetting.logo.trim() : '';
    const icon = typeof webSetting.icon === 'string' ? webSetting.icon.trim() : '';
    const source = logo || icon;

    if (!source) {
        return null;
    }

    let normalizedSource = source;
    if (typeof window !== 'undefined') {
        try {
            normalizedSource = new URL(source, window.location.href).toString();
        } catch (_) {
            normalizedSource = source;
        }
    }

    const version = webSetting.updated_at || Date.now();
    const separator = normalizedSource.includes('?') ? '&' : '?';

    return `${normalizedSource}${separator}v=${encodeURIComponent(version)}`;
};

const resolveRuntimeAppName = (webSetting) => {
    if (!webSetting || typeof webSetting !== 'object') {
        return appName;
    }

    const shortName = typeof webSetting.company_short_name === 'string'
        ? webSetting.company_short_name.trim()
        : '';
    if (shortName) {
        return shortName;
    }

    const companyName = typeof webSetting.company_name === 'string'
        ? webSetting.company_name.trim()
        : '';
    if (companyName) {
        return companyName;
    }

    return appName;
};

const updateDocumentTitle = (runtimeName) => {
    if (typeof document === 'undefined') {
        return;
    }

    const currentTitle = document.title || '';
    const titleParts = currentTitle.split(' - ');
    const pageTitle = titleParts[0] || runtimeName;
    document.title = `${pageTitle} - ${runtimeName}`;
};

const detectFaviconType = (url) => {
    const cleaned = String(url || '').toLowerCase();
    if (cleaned.includes('.ico')) return 'image/x-icon';
    if (cleaned.includes('.svg')) return 'image/svg+xml';
    if (cleaned.includes('.jpg') || cleaned.includes('.jpeg')) return 'image/jpeg';
    if (cleaned.includes('.webp')) return 'image/webp';
    if (cleaned.includes('.gif')) return 'image/gif';
    return 'image/png';
};

const setFavicon = (webSetting) => {
    if (typeof document === 'undefined') {
        return;
    }

    const faviconUrl = resolveFavicon(webSetting);
    if (!faviconUrl) {
        return;
    }

    const iconType = detectFaviconType(faviconUrl);
    const rels = ['icon', 'shortcut icon'];

    rels.forEach((relValue, index) => {
        let favicon = document.querySelector(`link[data-app-favicon="true"][data-favicon-rel="${relValue}"]`);
        if (!favicon) {
            favicon = document.createElement('link');
            favicon.setAttribute('data-app-favicon', 'true');
            favicon.setAttribute('data-favicon-rel', relValue);
            document.head.appendChild(favicon);
        }

        favicon.setAttribute('rel', relValue);
        favicon.setAttribute('type', iconType);
        favicon.setAttribute('href', faviconUrl);
    });

    document
        .querySelectorAll('link[rel="icon"], link[rel="shortcut icon"]')
        .forEach((node) => {
            node.setAttribute('href', faviconUrl);
            node.setAttribute('type', iconType);
        });
};

const applyRuntimeBranding = (pageProps) => {
    const webSetting = pageProps?.webSetting ?? null;
    const runtimeName = resolveRuntimeAppName(webSetting);
    runtimeAppName = runtimeName;

    updateDocumentTitle(runtimeName);
    setTheme(webSetting?.current_theme ?? 'default');
    setFavicon(webSetting);
};

document.addEventListener('inertia:success', (event) => {
    const pageProps = event?.detail?.page?.props;
    applyRuntimeBranding(pageProps);
});

createInertiaApp({
    page: resolveInitialInertiaPage(typeof document === 'undefined' ? null : document.getElementById('app')),
    title: (title) => `${title} - ${runtimeAppName}`,
    resolve: async (name) => {
        const path = `./Pages/${name}.vue`;
        const importer = pages[path];

        if (!importer) {
            throw new Error(`Unknown Inertia page: ${path}`);
        }

        const module = await importer();
        return module.default;
    },
    compilerOptions: {
        isCustomElement: (tag) => tag === 'Link',
        // isCustomElement: (tag) => tag === 'Pagination',
    },
    setup({ el, App, props, plugin }) {
        applyRuntimeBranding(props?.initialPage?.props);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('FeatherIcon', FeatherIcon)
            // .mixin({ methods: { handleFormSubmission, defaultSuccessHandler, defaultErrorHandler,showToast } })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
