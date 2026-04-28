/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
import 'toastr/build/toastr.min.css';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Toaster helper: ensure a simple `window.toast(message, { type: 'success'|'error' })`
// is available across the app. Prefer `toastr` when present, otherwise
// fall back to `alert`.
try {
	// Import toastr so Vite bundles it; if it's already available on
	// `window.toastr` this is a no-op.
	// eslint-disable-next-line import/no-extraneous-dependencies
	import('toastr').then((toastrModule) => {
		const toastr = (toastrModule && (toastrModule.default || toastrModule)) || window.toastr;
		try {
			// Basic default options; projects can override globally later.
			if (toastr && typeof toastr === 'object') {
				toastr.options = toastr.options || {};
				toastr.options.closeButton = true;
				toastr.options.progressBar = true;
				toastr.options.positionClass = toastr.options.positionClass || 'toast-top-right';
				window.toastr = toastr;
			}
		} catch (e) {
			// ignore
		}
		if (!window.toast) {
			// Simple deduping wrapper to avoid showing the exact same
			// message multiple times within a short interval.
			(function () {
				let _lastMessage = null;
				let _lastAt = 0;
				const DEDUPE_MS = 1000;

				window.toast = function (message, opts) {
					opts = opts || {};
					const type = (opts && opts.type) || 'success';
					try {
						const now = Date.now();
						const msgStr = (typeof message === 'string') ? message : JSON.stringify(message);
						if (msgStr === _lastMessage && (now - _lastAt) < (opts.dedupeTimeout || DEDUPE_MS)) {
							return; // skip duplicate
						}
						_lastMessage = msgStr;
						_lastAt = now;

						if (window.toastr && typeof window.toastr[type] === 'function') {
							window.toastr[type](message);
							return;
						}
					} catch (e) {
						// ignore
					}
					try { alert(String(message || '')); } catch (e) { /* ignore */ }
				};
			})();
		}
	}).catch(() => {
		if (!window.toast) {
			window.toast = function (message) { try { alert(String(message || '')); } catch (e) {} };
		}
	});
} catch (e) {
	if (!window.toast) {
		window.toast = function (message) { try { alert(String(message || '')); } catch (er) {} };
	}
}

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

// import Echo from 'laravel-echo';

// import Pusher from 'pusher-js';
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
//     wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
//     wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
//     wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });
