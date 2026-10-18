import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const FLUSH_DELAY_MS = 4000;
const BATCH_SIZE = 10;
const PROGRESS_KEY = 'demo-progress';

const queue = [];
let timer = null;
let active = false;
let started = false;

const STEP_BY_EVENT = {
    menu_view: 'menu',
    item_view: 'menu',
    add_to_cart: 'cart',
    checkout_start: 'checkout',
    order_placed: 'order',
    admin_login: 'admin',
    admin_view: 'admin',
};

function loadProgress() {
    try {
        return JSON.parse(localStorage.getItem(PROGRESS_KEY) ?? '[]');
    } catch {
        return [];
    }
}

/** Steps of the guided journey the visitor has completed in this browser. */
export const progress = reactive({ done: loadProgress() });

export function markStep(step) {
    if (progress.done.includes(step)) {
        return;
    }

    progress.done.push(step);

    try {
        localStorage.setItem(PROGRESS_KEY, JSON.stringify(progress.done));
    } catch {
        // Private mode: progress simply lives for this page.
    }
}

function csrfHeader() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {};
}

export function flush() {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }

    if (! active || queue.length === 0) {
        return;
    }

    const now = Date.now();
    const events = queue.splice(0, 30).map(({ at, ...event }) => ({ ...event, age_ms: Math.max(0, now - at) }));

    fetch('/demo/events', {
        method: 'POST',
        keepalive: true,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeader() },
        body: JSON.stringify({ events }),
    }).catch(() => {
        // Tracking must never break the demo; drop the batch.
    });

    if (queue.length > 0) {
        flush();
    }
}

export function track(type, name = null, meta = null, path = null) {
    const step = STEP_BY_EVENT[type];

    if (step) {
        markStep(step);
    }

    if (! active) {
        return;
    }

    queue.push({ type, name, meta, path: path ?? window.location.pathname, at: Date.now() });

    if (queue.length >= BATCH_SIZE) {
        flush();
    } else if (timer === null) {
        timer = setTimeout(flush, FLUSH_DELAY_MS);
    }
}

function milestoneFor(component) {
    if (component === 'web/Menu') {
        return 'menu_view';
    }

    if (component === 'web/Item') {
        return 'item_view';
    }

    if (component === 'web/Checkout') {
        return 'checkout_start';
    }

    return component.startsWith('admin/') ? 'admin_view' : 'page_view';
}

function trackPage(page) {
    track(milestoneFor(page.component), page.component, null, page.url.split('?')[0]);

    if (page.props?.flash?.placed) {
        markStep('order');
    }
}

/** Turn tracking on once a demo session exists; safe to call repeatedly. */
export function activateTracker(page) {
    active = true;

    if (started) {
        return;
    }

    started = true;
    router.on('navigate', (event) => trackPage(event.detail.page));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            flush();
        }
    });
    window.addEventListener('pagehide', flush);

    if (page) {
        trackPage(page);
    }
}

export function deactivateTracker() {
    active = false;
    queue.length = 0;
}

export async function startSession(mobile, consent) {
    const params = new URLSearchParams(window.location.search);

    const response = await fetch('/demo/session', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeader() },
        body: JSON.stringify({
            mobile,
            consent,
            utm_source: params.get('utm_source'),
            utm_campaign: params.get('utm_campaign'),
        }),
    });

    const body = await response.json().catch(() => ({}));

    return { ok: response.ok, errors: body.errors ?? {}, message: body.message ?? '' };
}
