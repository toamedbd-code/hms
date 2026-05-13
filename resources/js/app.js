import './bootstrap';
import '../css/app.css';
import FeatherIcon from './Components/FeatherIcon.vue';
import eventBus from './eventBus.js';


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
    const webSetting = pageProps?.websetting ?? pageProps?.webSetting ?? null;
    let brandingPayload = null;

    // Maintain a last-seen branding payload so pages that don't include
    // branding props (partial Inertia responses) can still receive the
    // most recent runtime branding update emitted earlier.
    try {
        if (typeof window !== 'undefined') {
            window.__last_branding_payload = window.__last_branding_payload ?? null;

            // Hydrate last branding payload from localStorage so full page
            // reloads keep the most recent branding visible to the user.
            try {
                if (!window.__last_branding_payload && typeof localStorage !== 'undefined') {
                    const stored = localStorage.getItem('__last_branding_payload');
                    if (stored) {
                        window.__last_branding_payload = JSON.parse(stored);
                    }
                }
            } catch (e) {
                // ignore localStorage parse errors
            }
        }
    } catch (e) {
        // ignore
    }

    // Prepare a candidate branding payload based on server props but do
    // not yet apply it to `pageProps`/`window.__inertia` — we'll decide
    // the final effective branding below after comparing timestamps with
    // any in-memory / persisted payload.
    let serverCandidate = null;
    try {
        if (webSetting) {
            serverCandidate = {
                id: webSetting.id ?? null,
                name: webSetting.company_name ?? webSetting.companyName ?? (pageProps?.companyInfo?.name ?? ''),
                short_name: webSetting.company_short_name ?? (pageProps?.companyInfo?.short_name ?? ''),
                phone: webSetting.phone ?? (pageProps?.companyInfo?.phone ?? ''),
                email: webSetting.email ?? (pageProps?.companyInfo?.email ?? ''),
                logo: webSetting.logo ?? (pageProps?.companyInfo?.logo ?? ''),
                favicon: webSetting.icon ?? (pageProps?.companyInfo?.favicon ?? ''),
                address: webSetting.address ?? (pageProps?.companyInfo?.address ?? ''),
                sorting: pageProps?.companyInfo?.sorting ?? 0,
                status: webSetting.status ?? (pageProps?.companyInfo?.status ?? 'Active'),
                created_at: webSetting.created_at ?? (pageProps?.companyInfo?.created_at ?? null),
                updated_at: webSetting.updated_at ?? (pageProps?.companyInfo?.updated_at ?? null),
                current_theme: webSetting?.current_theme ?? (pageProps?.companyInfo?.current_theme ?? undefined),
            };

            brandingPayload = serverCandidate;
        } else if (pageProps?.companyInfo) {
            brandingPayload = pageProps.companyInfo;
            serverCandidate = brandingPayload;
        }
    } catch (e) {
        // non-fatal: continue with branding even if mapping fails
    }

    // After comparing and selecting the effective branding payload below,
    // we'll persist it to localStorage so full reloads can hydrate it.

    // If the server did not provide branding for this Inertia visit but we
    // have a last-known branding payload, reuse it so client UI remains
    // consistent across navigation that omits branding props.
    try {
        if (!brandingPayload && typeof window !== 'undefined' && window.__last_branding_payload) {
            brandingPayload = window.__last_branding_payload;
            if (pageProps) {
                pageProps.companyInfo = brandingPayload;
                pageProps.webSetting = brandingPayload;
                pageProps.websetting = brandingPayload;
            }
            if (typeof window !== 'undefined' && window.__inertia && window.__inertia.page && window.__inertia.page.props) {
                window.__inertia.page.props.companyInfo = brandingPayload;
                window.__inertia.page.props.webSetting = brandingPayload;
                window.__inertia.page.props.websetting = brandingPayload;
            }
        }
    } catch (e) {
        // ignore
    }

    try {
        // Safe update: avoid overwriting a newer in-memory branding with an
        // older server response. Compare timestamps between any previously
        // persisted payload and the server candidate and choose the newer
        // as the `window.__last_branding_payload`.
        const prev = (typeof window !== 'undefined' && window.__last_branding_payload) ? window.__last_branding_payload : null;

        const parseTs = (v) => {
            if (!v) return NaN;
            const s = String(v);
            const t = Date.parse(s);
            return Number.isFinite(t) ? t : NaN;
        };

        if (!prev && brandingPayload) {
            window.__last_branding_payload = brandingPayload;
        } else if (prev && brandingPayload) {
            const prevTs = parseTs(prev.updated_at ?? prev.updatedAt ?? null);
            const newTs = parseTs(brandingPayload.updated_at ?? brandingPayload.updatedAt ?? null);

            if (!Number.isFinite(prevTs) && !Number.isFinite(newTs)) {
                // No reliable timestamps: keep existing persisted payload
                window.__last_branding_payload = prev;
                brandingPayload = prev;
            } else if (!Number.isFinite(prevTs) && Number.isFinite(newTs)) {
                window.__last_branding_payload = brandingPayload;
            } else if (Number.isFinite(prevTs) && !Number.isFinite(newTs)) {
                window.__last_branding_payload = prev;
                brandingPayload = prev;
            } else {
                if (newTs >= prevTs) {
                    window.__last_branding_payload = brandingPayload;
                } else {
                    window.__last_branding_payload = prev;
                    brandingPayload = prev;
                }
            }
        }
    } catch (e) {
        // non-fatal: continue with branding even if sync fails
    }

    // Determine the final effective branding payload we'll apply to the
    // page props and to persisted storage. If the server provided a
    // candidate (`serverCandidate`) prefer that as the authoritative
    // source so freshly-saved WebSetting values show immediately in
    // layout components (Sidebar, Navbar). Otherwise fall back to the
    // last-known persisted payload when available.
    let effectiveBranding = null;
    if (serverCandidate) {
        effectiveBranding = serverCandidate;
    } else if (typeof window !== 'undefined' && window.__last_branding_payload) {
        effectiveBranding = window.__last_branding_payload;
    } else {
        effectiveBranding = (brandingPayload ?? null);
    }

    // Normalize branding for components that expect server-style keys
    // (`company_name`, `company_short_name`) as well as client-style
    // keys (`name`, `short_name`). This prevents UI fallbacks like
    // "Company Name" when the page props contain `name` instead of
    // `company_name`.
    const normalizeBranding = (b) => {
        if (!b || typeof b !== 'object') return b;
        return {
            id: b.id ?? null,
            name: b.name ?? b.company_name ?? '',
            short_name: b.short_name ?? b.company_short_name ?? '',
            company_name: b.name ?? b.company_name ?? '',
            company_short_name: b.short_name ?? b.company_short_name ?? '',
            phone: b.phone ?? '',
            email: b.email ?? '',
            logo: b.logo ?? '',
            favicon: b.favicon ?? b.icon ?? '',
            address: b.address ?? '',
            sorting: b.sorting ?? 0,
            status: b.status ?? 'Active',
            created_at: b.created_at ?? b.createdAt ?? null,
            updated_at: b.updated_at ?? b.updatedAt ?? null,
            current_theme: b.current_theme ?? b.currentTheme ?? undefined,
        };
    };

    // Allow pages that are clearly the public website (they include
    // `initialSection` in their props) to prefer the server-provided
    // branding. This prevents stale persisted branding in localStorage
    // from overriding freshly-saved CMS settings on the public site.
    let effectiveBrandingNormalized = normalizeBranding(effectiveBranding);
    try {
        const isPublicWebsite = pageProps && typeof pageProps.initialSection !== 'undefined';
        if (isPublicWebsite && serverCandidate) {
            const serverNormalized = normalizeBranding(serverCandidate);
            effectiveBrandingNormalized = serverNormalized;
            if (typeof window !== 'undefined') {
                window.__last_branding_payload = serverNormalized;
                try {
                    if (typeof localStorage !== 'undefined') {
                        localStorage.setItem('__last_branding_payload', JSON.stringify(serverNormalized));
                    }
                } catch (e) {
                    // ignore storage write errors
                }
                try {
                    if (typeof eventBus !== 'undefined' && eventBus && typeof eventBus.emit === 'function') {
                        eventBus.emit('branding.updated', serverNormalized);
                    }
                } catch (e) {
                    // ignore emit errors
                }
            }
        }
    } catch (e) {
        // ignore
    }

    // If this Inertia visit appears to be the public website (frontend pages
    // expose `initialSection`), prefer the server-provided branding payload
    // to avoid a stale client-persisted branding (localStorage) overriding
    // the authoritative server value on the public site.
    try {
        const isPublicWebsite = pageProps && typeof pageProps.initialSection !== 'undefined';
        if (isPublicWebsite && serverCandidate) {
            const serverNormalized = normalizeBranding(serverCandidate);
            effectiveBrandingNormalized = serverNormalized;

            if (typeof window !== 'undefined') {
                window.__last_branding_payload = serverNormalized;
                try {
                    if (typeof localStorage !== 'undefined') {
                        localStorage.setItem('__last_branding_payload', JSON.stringify(serverNormalized));
                    }
                } catch (e) {
                    // ignore storage write errors
                }

                try {
                    if (typeof eventBus !== 'undefined' && eventBus && typeof eventBus.emit === 'function') {
                        eventBus.emit('branding.updated', serverNormalized);
                    }
                } catch (e) {
                    // ignore
                }
            }
        }
    } catch (e) {
        // ignore
    }

    // Apply the effective branding into the Inertia page props so that a
    // full reload or initial render shows the chosen payload.
    try {
        if (effectiveBrandingNormalized && pageProps) {
            pageProps.companyInfo = effectiveBrandingNormalized;
            pageProps.webSetting = effectiveBrandingNormalized;
            pageProps.websetting = effectiveBrandingNormalized;
        }

        if (typeof window !== 'undefined' && window.__inertia && window.__inertia.page && window.__inertia.page.props) {
            if (effectiveBrandingNormalized) {
                window.__inertia.page.props.companyInfo = effectiveBrandingNormalized;
                window.__inertia.page.props.webSetting = effectiveBrandingNormalized;
                window.__inertia.page.props.websetting = effectiveBrandingNormalized;
            }
        }

        // Also ensure the global last-branding payload is normalized so
        // subsequent comparisons and persistence use consistent keys.
        if (typeof window !== 'undefined') {
            window.__last_branding_payload = effectiveBrandingNormalized ?? window.__last_branding_payload;
        }
    } catch (e) {
        // ignore
    }

    // Persist the chosen branding so full page reloads can hydrate it.
    try {
        if (typeof window !== 'undefined' && window.__last_branding_payload && typeof localStorage !== 'undefined') {
            try {
                localStorage.setItem('__last_branding_payload', JSON.stringify(window.__last_branding_payload));
            } catch (e) {
                // ignore storage write errors
            }
        }
    } catch (e) {
        // ignore
    }

    // Notify other components (Sidebar, Navbar) that branding changed so
    // they can update themselves even if they were rendered with stale props.
    try {
        if (effectiveBranding && typeof eventBus !== 'undefined' && eventBus && typeof eventBus.emit === 'function') {
            eventBus.emit('branding.updated', effectiveBranding);
        }
    } catch (e) {
        // ignore
    }

    const runtimeName = resolveRuntimeAppName(effectiveBranding ?? null);
    runtimeAppName = runtimeName;

    updateDocumentTitle(runtimeName);
    setTheme(effectiveBranding?.current_theme ?? 'default');
    setFavicon(effectiveBranding ?? { icon: effectiveBranding?.favicon, logo: effectiveBranding?.logo, updated_at: effectiveBranding?.updated_at });
};

document.addEventListener('inertia:success', (event) => {
    const page = event?.detail?.page;
    try {
        if (typeof window !== 'undefined') {
            window.__inertia = window.__inertia || {};
            window.__inertia.page = page ?? window.__inertia.page ?? null;
        }
    } catch (e) {
        // ignore
    }

    const pageProps = page?.props;
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
        // Seed global `window.__inertia.page` for legacy consumers that
        // inspect `window.__inertia.page.props` directly from console or
        // non-Inertia code paths.
        try {
            if (typeof window !== 'undefined') {
                window.__inertia = window.__inertia || {};
                window.__inertia.page = props?.initialPage ?? window.__inertia.page ?? null;
            }
        } catch (e) {
            // ignore
        }

        applyRuntimeBranding(props?.initialPage?.props);

        const instance = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('FeatherIcon', FeatherIcon);

        const mounted = instance.mount(el);

        // Monkey-patch global `route()` to try sensible fallbacks when Ziggy
        // throws "route ... is not in the route list" due to naming prefixes
        // differences (for example 'backend.accounts.list' vs 'backend.backend.accounts.list').
        try {
            if (typeof window !== 'undefined' && typeof window.route === 'function') {
                const _origRoute = window.route;
                window._origRoute = _origRoute;

                window.route = function (name, params = {}, absolute = undefined) {
                    try {
                        return _origRoute(name, params, absolute);
                    } catch (err) {
                        // Only attempt fallbacks for string route names
                        if (typeof name !== 'string') throw err;

                        const tried = new Set();
                        const alts = [];

                        // If route begins with 'backend.' but not 'backend.backend.', try inserting extra 'backend.'
                        if (name.startsWith('backend.') && !name.startsWith('backend.backend.')) {
                            alts.push(name.replace(/^backend\./, 'backend.backend.'));
                        }

                        // If route does not start with 'backend.', try adding common prefixes
                        if (!name.startsWith('backend.')) {
                            alts.push(`backend.${name}`);
                            alts.push(`backend.backend.${name}`);
                        }

                        // Try each alternative until one succeeds
                        for (const alt of alts) {
                            if (!alt || tried.has(alt)) continue;
                            tried.add(alt);
                            try {
                                const result = _origRoute(alt, params, absolute);
                                console.warn(`[route] fallback used: ${name} -> ${alt}`);
                                return result;
                            } catch (_) {
                                // continue
                            }
                        }

                        // No fallback worked — rethrow original error
                        throw err;
                    }
                };
            }
        } catch (e) {
            // Non-fatal: keep original route behavior if patching fails
            console.warn('route fallback wrapper not installed', e?.message ?? e);
        }

        return mounted;
    },
    progress: {
        color: '#4B5563',
    },
});
