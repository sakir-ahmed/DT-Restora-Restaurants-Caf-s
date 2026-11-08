import { computed, ref } from 'vue';

/**
 * Installable app and service worker.
 *
 * The worker keeps the app shell and the screens already opened, so the POS opens
 * and runs without internet. Orders made offline wait in IndexedDB (lib/offline.js).
 */

const hasWindow = typeof window !== 'undefined';

// Caches that hold pages of a signed-in session. They are emptied at sign out.
const PRIVATE_CACHES = ['pages', 'screens'];

// Look for a new release this often while the app stays open.
const UPDATE_CHECK_MS = 60 * 60 * 1000;

const deferredInstall = ref(null);

const standalone = hasWindow && (window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true);
const installed = ref(Boolean(standalone));

// Safari on iPhone and iPad has no install prompt; the person adds it from the Share menu.
const iosBrowser = hasWindow && /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !('MSStream' in window);

export const canInstall = computed(() => !installed.value && deferredInstall.value !== null);
export const needsIosInstallSteps = computed(() => !installed.value && iosBrowser);

if (hasWindow) {
    // The browser fires this once the app is installable. Keep it to offer our own button.
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstall.value = event;
    });

    window.addEventListener('appinstalled', () => {
        deferredInstall.value = null;
        installed.value = true;
    });
}

/**
 * Ask the browser to install the app. Resolves to true when the person accepted.
 */
export async function promptInstall() {
    const event = deferredInstall.value;

    if (event === null) {
        return false;
    }

    deferredInstall.value = null;
    await event.prompt();

    const { outcome } = await event.userChoice;

    return outcome === 'accepted';
}

/**
 * Forget saved pages. Call before signing out so the next person on a shared
 * device cannot open the previous person's screens while offline.
 */
export async function clearPrivateCaches() {
    if (!hasWindow || !('caches' in window)) {
        return;
    }

    try {
        await Promise.all(PRIVATE_CACHES.map((name) => caches.delete(name)));
    } catch {
        /* Nothing to clear when storage is blocked. */
    }
}

function isAppScreen(pathname) {
    return pathname.startsWith('/admin') || pathname.startsWith('/rider') || pathname === '/login';
}

let registering = null;

/**
 * Register the worker for staff screens only, so visitors of the public website
 * never download the offline files. A new version is announced with the
 * `pwa-update` event and applied only when the cashier chooses, never mid-order.
 */
export function registerServiceWorker() {
    if (!hasWindow || !import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return null;
    }

    if (!isAppScreen(window.location.pathname)) {
        // A visitor may reach the sign-in page by an in-app link without a page load.
        document.addEventListener('inertia:navigate', () => registerServiceWorker(), { once: true });

        return null;
    }

    registering ??= startWorker();

    return registering;
}

async function startWorker() {
    const { Workbox } = await import('workbox-window');
    const worker = new Workbox('/sw.js', { scope: '/' });

    worker.addEventListener('waiting', () => {
        window.dispatchEvent(new CustomEvent('pwa-update', {
            detail: {
                apply: () => {
                    worker.addEventListener('controlling', () => window.location.reload());
                    worker.messageSkipWaiting();
                },
            },
        }));
    });

    const registration = await worker.register();

    if (registration) {
        const check = () => registration.update().catch(() => {});

        setInterval(check, UPDATE_CHECK_MS);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                check();
            }
        });
    }

    return worker;
}
