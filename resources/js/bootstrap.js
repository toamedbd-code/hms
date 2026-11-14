/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
import 'toastr/build/toastr.min.css';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

let lastToastMessage = null;
let lastToastAt = 0;
const toastDedupeMs = 1000;

window.toast = function (message, opts = {}) {
	const type = opts.type || 'success';
	try {
		const messageText = typeof message === 'string' ? message : JSON.stringify(message);
		if (messageText === lastToastMessage && (Date.now() - lastToastAt) < (opts.dedupeTimeout || toastDedupeMs)) {
			return;
		}
		lastToastMessage = messageText;
		lastToastAt = Date.now();
	} catch {
		// Continue to display messages that cannot be serialized for deduplication.
	}

	import('./responseMessage.js')
		.then(({ showToast }) => showToast(type, message))
		.catch((error) => {
			console.error('Unable to display the notification.', error);
			window.alert(String(message || ''));
		});
};

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
