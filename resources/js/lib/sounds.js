/**
 * Sound effects for the back office.
 *
 * The sounds are tiny synthesized tones. Each one is rendered to a WAV file the
 * first time it is needed and kept in local storage, so after the first visit
 * nothing has to be generated or downloaded again. Decoded audio is then kept in
 * memory for the rest of the session, which makes playback instant.
 *
 * Which sounds are on, and how loud, is a branch setting (Settings > Branch POS).
 * The last copy of it is also kept in local storage so the first sound after a
 * page load already follows it. Each device can mute everything on its own.
 */

const SAMPLE_RATE = 12000;
const ASSET_PREFIX = 'dt-sound:';
const SETTINGS_KEY = 'dt-sound-settings';
const MUTE_KEY = 'dt-sound-muted';

/**
 * Notes are [frequency Hz, start s, length s, wave]. Every event has its own
 * melody so staff can tell them apart without looking at the screen.
 */
export const SOUND_DEFINITIONS = {
    notification: [[880, 0, 0.12], [1318, 0.12, 0.2]],
    success: [[660, 0, 0.1], [990, 0.1, 0.18]],
    error: [[220, 0, 0.18, 'square'], [165, 0.2, 0.3, 'square']],
    item_added: [[1200, 0, 0.05]],
    order_created: [[523, 0, 0.1], [659, 0.1, 0.1], [784, 0.2, 0.24]],
    order_paid: [[1568, 0, 0.08], [2093, 0.09, 0.4]],
    order_held: [[440, 0, 0.12], [330, 0.14, 0.22]],
    order_cancelled: [[392, 0, 0.14], [294, 0.16, 0.14], [220, 0.32, 0.3]],
    online_order: [[880, 0, 0.16], [1175, 0.2, 0.16], [880, 0.4, 0.16], [1175, 0.6, 0.3]],
    kds_new: [[988, 0, 0.1], [988, 0.18, 0.1], [1319, 0.36, 0.26]],
    kds_preparing: [[392, 0, 0.1, 'triangle'], [523, 0.12, 0.2, 'triangle']],
    kds_ready: [[784, 0, 0.12], [988, 0.14, 0.12], [1319, 0.28, 0.34]],
    kds_served: [[659, 0, 0.08, 'triangle'], [523, 0.1, 0.22, 'triangle']],
};

export const SOUND_EVENTS = Object.keys(SOUND_DEFINITIONS);

const DEFAULT_SETTINGS = {
    enabled: true,
    volume: 70,
    events: {
        notification: true,
        success: false,
        error: true,
        item_added: false,
        order_created: true,
        order_paid: true,
        order_held: true,
        order_cancelled: true,
        online_order: true,
        kds_new: true,
        kds_preparing: true,
        kds_ready: true,
        kds_served: true,
    },
};

function readJson(key) {
    try {
        return JSON.parse(localStorage.getItem(key) ?? 'null');
    } catch {
        return null;
    }
}

let settings = { ...DEFAULT_SETTINGS, ...(readJson(SETTINGS_KEY) ?? {}) };
let context = null;
let unlockBound = false;
const buffers = new Map();
const loading = new Map();
const lastPlayed = new Map();

function checksum(text) {
    let hash = 0;

    for (let index = 0; index < text.length; index += 1) {
        hash = ((hash << 5) - hash + text.charCodeAt(index)) | 0;
    }

    return (hash >>> 0).toString(36);
}

/**
 * Render a melody into a 16-bit mono WAV file.
 */
function renderWav(notes) {
    const length = Math.ceil((Math.max(...notes.map(([, start, duration]) => start + duration)) + 0.04) * SAMPLE_RATE);
    const samples = new Float32Array(length);

    notes.forEach(([frequency, start, duration, wave = 'sine']) => {
        const first = Math.floor(start * SAMPLE_RATE);
        const count = Math.floor(duration * SAMPLE_RATE);

        for (let index = 0; index < count && first + index < length; index += 1) {
            const time = index / SAMPLE_RATE;
            const phase = 2 * Math.PI * frequency * time;
            const attack = Math.min(1, index / (0.006 * SAMPLE_RATE));
            const decay = Math.exp((-3.5 * time) / duration);
            const raw = wave === 'square' ? Math.sign(Math.sin(phase)) * 0.55
                : wave === 'triangle' ? (2 / Math.PI) * Math.asin(Math.sin(phase))
                    : Math.sin(phase);

            samples[first + index] += raw * attack * decay * 0.55;
        }
    });

    const bytes = new Uint8Array(44 + length * 2);
    const view = new DataView(bytes.buffer);
    const write = (offset, text) => [...text].forEach((character, position) => view.setUint8(offset + position, character.charCodeAt(0)));

    write(0, 'RIFF');
    view.setUint32(4, 36 + length * 2, true);
    write(8, 'WAVE');
    write(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, SAMPLE_RATE, true);
    view.setUint32(28, SAMPLE_RATE * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    write(36, 'data');
    view.setUint32(40, length * 2, true);

    samples.forEach((sample, index) => {
        view.setInt16(44 + index * 2, Math.max(-1, Math.min(1, sample)) * 32767, true);
    });

    return bytes;
}

function toBase64(bytes) {
    let binary = '';

    for (let index = 0; index < bytes.length; index += 0x8000) {
        binary += String.fromCharCode(...bytes.subarray(index, index + 0x8000));
    }

    return btoa(binary);
}

function fromBase64(text) {
    const binary = atob(text);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return bytes;
}

/**
 * The WAV bytes of one sound: from local storage when it is there, rendered
 * and stored when it is not. The key carries a checksum of the melody, so
 * changing a melody replaces the stored copy.
 */
function assetBytes(event) {
    const key = `${ASSET_PREFIX}${event}:${checksum(JSON.stringify(SOUND_DEFINITIONS[event]))}`;

    try {
        const stored = localStorage.getItem(key);

        if (stored) {
            return fromBase64(stored);
        }
    } catch {
        // storage unavailable: render each time
    }

    const bytes = renderWav(SOUND_DEFINITIONS[event]);

    try {
        Object.keys(localStorage)
            .filter((name) => name.startsWith(`${ASSET_PREFIX}${event}:`))
            .forEach((name) => localStorage.removeItem(name));
        localStorage.setItem(key, toBase64(bytes));
    } catch {
        // storage full or blocked: the sound still plays this session
    }

    return bytes;
}

function audioContext() {
    if (! context && typeof window !== 'undefined' && (window.AudioContext || window.webkitAudioContext)) {
        context = new (window.AudioContext || window.webkitAudioContext)();
    }

    if (! unlockBound && typeof window !== 'undefined') {
        unlockBound = true;

        const unlock = () => {
            if (context?.state === 'suspended') {
                context.resume();
            }
        };

        ['pointerdown', 'keydown', 'touchstart'].forEach((name) => window.addEventListener(name, unlock, { passive: true }));
    }

    return context;
}

function bufferFor(event) {
    if (buffers.has(event)) {
        return Promise.resolve(buffers.get(event));
    }

    if (! loading.has(event)) {
        const ctx = audioContext();

        loading.set(event, ctx
            ? ctx.decodeAudioData(assetBytes(event).buffer.slice(0)).then((buffer) => {
                buffers.set(event, buffer);

                return buffer;
            })
            : Promise.resolve(null));
    }

    return loading.get(event);
}

/**
 * Apply the branch sound settings and keep a copy for the next page load.
 */
export function configureSounds(next) {
    if (! next) {
        return;
    }

    settings = {
        enabled: next.enabled !== false,
        volume: Math.max(0, Math.min(100, Number(next.volume ?? DEFAULT_SETTINGS.volume))),
        events: { ...DEFAULT_SETTINGS.events, ...(next.events ?? {}) },
    };

    try {
        localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
    } catch {
        // storage unavailable
    }
}

export function isSoundMuted() {
    try {
        return localStorage.getItem(MUTE_KEY) === '1' || localStorage.getItem('kds-muted') === '1';
    } catch {
        return false;
    }
}

export function setSoundMuted(muted) {
    try {
        localStorage.setItem(MUTE_KEY, muted ? '1' : '0');
        localStorage.setItem('kds-muted', muted ? '1' : '0');
    } catch {
        // storage unavailable
    }
}

/**
 * Warm the cache: render, store and decode the sounds that are switched on, so
 * the first real sound has no delay. Runs when the browser is idle.
 */
export function preloadSounds() {
    const run = () => SOUND_EVENTS
        .filter((event) => settings.enabled && settings.events[event])
        .forEach((event) => bufferFor(event).catch(() => null));

    if (typeof window !== 'undefined' && 'requestIdleCallback' in window) {
        window.requestIdleCallback(run, { timeout: 3000 });
    } else {
        setTimeout(run, 500);
    }
}

/**
 * Play one sound effect, if the branch has it on and this device is not muted.
 * `preview` plays it regardless, for the Test button in settings.
 */
export async function playSound(event, { preview = false, volume = null } = {}) {
    if (! SOUND_DEFINITIONS[event]) {
        return;
    }

    if (! preview && (! settings.enabled || ! settings.events[event] || isSoundMuted())) {
        return;
    }

    const now = Date.now();

    if (! preview && now - (lastPlayed.get(event) ?? 0) < 80) {
        return;
    }

    lastPlayed.set(event, now);

    try {
        const ctx = audioContext();
        const buffer = await bufferFor(event);

        if (! ctx || ! buffer) {
            return;
        }

        if (ctx.state === 'suspended') {
            await ctx.resume();
        }

        const source = ctx.createBufferSource();
        const gain = ctx.createGain();

        source.buffer = buffer;
        gain.gain.value = (volume ?? settings.volume) / 100;
        source.connect(gain).connect(ctx.destination);
        source.start();
    } catch {
        // audio unavailable or still locked by the browser
    }
}
