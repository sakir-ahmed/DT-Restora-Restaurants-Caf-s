import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Single Laravel Echo instance for Reverb (Pusher protocol). Returns
 * null when no app key is configured so pages can fall back to polling.
 */
export function initEcho() {
    if (window.Echo) {
        return window.Echo;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (! key) {
        return null;
    }

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return window.Echo;
}
