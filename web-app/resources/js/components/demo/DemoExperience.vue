<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { activateTracker, deactivateTracker, progress, track } from '@/lib/demoTracker';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import DemoGateModal from './DemoGateModal.vue';
import DemoGuide from './DemoGuide.vue';
import DemoNudge from './DemoNudge.vue';

const SEEN_KEY = 'demo-seen';
const SKIP_KEY = 'demo-gate-skipped';
const HIDE_KEY = 'demo-launcher-hidden';
const GATE_DELAY_MS = 1800;
const NUDGE_DELAY_MS = 9000;
const NUDGE_VISIBLE_MS = 16000;

const FEATURE_ICONS = {
    pos: 'cash-register',
    kds: 'utensils',
    inventory: 'boxes-stacked',
    accounting: 'file-invoice-dollar',
    delivery: 'truck-fast',
    reports: 'chart-line',
};

const FEATURE_ORDER = ['pos', 'kds', 'inventory', 'accounting', 'delivery', 'reports'];

const page = usePage();

const session = computed(() => page.props.demoSession ?? null);
const hasSession = computed(() => session.value !== null);
const component = computed(() => page.component ?? '');
const isPublic = computed(() => component.value.startsWith('web/'));
const isAdmin = computed(() => component.value.startsWith('admin/'));
// The POS and kitchen screens are dense touch layouts; nothing floats over them.
const hideChrome = computed(() => /^admin\/(pos|kds)\//.test(component.value));
const lifted = computed(() => ['web/Menu', 'web/Item'].includes(component.value));

const gateOpen = ref(false);
const guideOpen = ref(false);
const nudge = ref(null);
const attention = ref(true);
const launcherHidden = ref(readFlag(HIDE_KEY));

function readFlag(key) {
    try {
        return sessionStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

function hideLauncher() {
    launcherHidden.value = true;
    guideOpen.value = false;
    clearNudge();

    try {
        sessionStorage.setItem(HIDE_KEY, '1');
    } catch {
        // The button may come back on the next page load.
    }
}

let gateTimer = null;
let nudgeTimer = null;
let hideTimer = null;
let featureCursor = 0;

function readSet(storage, key) {
    try {
        return new Set(JSON.parse(storage.getItem(key) ?? '[]'));
    } catch {
        return new Set();
    }
}

function remember(key) {
    try {
        const seen = readSet(sessionStorage, SEEN_KEY);
        seen.add(key);
        sessionStorage.setItem(SEEN_KEY, JSON.stringify([...seen]));
    } catch {
        // Nudges may repeat in private mode; harmless.
    }
}

function wasSeen(key) {
    return readSet(sessionStorage, SEEN_KEY).has(key);
}

function tipKey() {
    const name = component.value;

    if (isAdmin.value) {
        const match = [
            [/pos/, 'pos'], [/kds/, 'kds'], [/ingredients|stock|purchases|recipes|suppliers|wastage|transfers/, 'inventory'],
            [/accounting|expenses|payroll/, 'accounting'], [/delivery/, 'delivery'], [/reports|Dashboard/, 'reports'],
        ].find(([pattern]) => pattern.test(name));

        return match ? `feature:${match[1]}` : 'admin';
    }

    return {
        'web/Home': 'home', 'web/Menu': 'menu', 'web/Item': 'item', 'web/Checkout': 'checkout',
        'web/Track': 'track', 'web/Account': 'account',
    }[name] ?? 'default';
}

function buildNudge(key) {
    if (key.startsWith('feature:')) {
        const feature = key.slice(8);

        return { kind: 'tip', icon: FEATURE_ICONS[feature], title: t(`demo.features.${feature}.title`), body: t(`demo.features.${feature}.body`) };
    }

    return { kind: 'tip', icon: 'lightbulb', title: t(`demo.tips.${key}.title`), body: t(`demo.tips.${key}.body`) };
}

function clearNudge() {
    clearTimeout(nudgeTimer);
    clearTimeout(hideTimer);
    nudge.value = null;
}

function scheduleNudge() {
    clearNudge();

    if (hideChrome.value || gateOpen.value) {
        return;
    }

    nudgeTimer = setTimeout(() => {
        if (guideOpen.value) {
            return;
        }

        const orderDone = progress.done.includes('order');
        const adminDone = progress.done.includes('admin');

        if (isPublic.value && orderDone && ! adminDone && ! wasSeen('admin-cta')) {
            remember('admin-cta');
            nudge.value = { kind: 'admin', icon: 'user-shield', title: t('demo.admin_cta_title'), body: t('demo.admin_cta_body') };
        } else {
            const key = tipKey();

            if (wasSeen(key)) {
                return;
            }

            remember(key);
            nudge.value = buildNudge(key);
        }

        track('feature_click', `nudge:${nudge.value.kind}`);
        hideTimer = setTimeout(() => (nudge.value = null), NUDGE_VISIBLE_MS);
    }, NUDGE_DELAY_MS);
}

function moreIdeas() {
    const feature = FEATURE_ORDER[featureCursor % FEATURE_ORDER.length];
    featureCursor += 1;

    clearTimeout(hideTimer);
    nudge.value = buildNudge(`feature:${feature}`);
    track('feature_click', `idea:${feature}`);
    hideTimer = setTimeout(() => (nudge.value = null), NUDGE_VISIBLE_MS);
}

function openGate() {
    clearNudge();
    guideOpen.value = false;
    gateOpen.value = true;
}

function skipGate() {
    gateOpen.value = false;

    try {
        sessionStorage.setItem(SKIP_KEY, '1');
    } catch {
        // Gate may show again on the next page load.
    }
}

function onStarted() {
    gateOpen.value = false;
    router.reload({
        only: ['demoSession'],
        onSuccess: () => {
            notify.success(t('demo.welcome_toast'));
            guideOpen.value = true;
        },
    });
}

function toggleGuide() {
    attention.value = false;
    clearNudge();
    guideOpen.value = ! guideOpen.value;

    if (guideOpen.value) {
        track('feature_click', 'guide_open');
    }
}

function scheduleGate() {
    clearTimeout(gateTimer);

    let skipped = false;

    try {
        skipped = sessionStorage.getItem(SKIP_KEY) === '1';
    } catch {
        skipped = false;
    }

    if (hasSession.value || ! isPublic.value || skipped) {
        return;
    }

    gateTimer = setTimeout(() => {
        if (! hasSession.value && isPublic.value) {
            gateOpen.value = true;
        }
    }, GATE_DELAY_MS);
}

watch(hasSession, (active) => {
    if (active) {
        activateTracker(page);
    } else {
        deactivateTracker();
    }
}, { immediate: true });

watch(() => page.url, () => {
    scheduleNudge();
    scheduleGate();
});

onMounted(() => {
    scheduleGate();
    scheduleNudge();
    window.addEventListener('demo:open-gate', openGate);
});

onBeforeUnmount(() => {
    clearTimeout(gateTimer);
    clearNudge();
    window.removeEventListener('demo:open-gate', openGate);
});
</script>

<template>
    <div :style="{ '--demo-fab': lifted ? '5rem' : '1rem' }" class="print:hidden">
        <DemoGateModal :open="gateOpen" @started="onStarted" @skip="skipGate" />

        <template v-if="! hideChrome && ! gateOpen">
            <DemoNudge v-if="! launcherHidden" :nudge="nudge" @dismiss="clearNudge" @more="moreIdeas" />
            <DemoGuide
                :open="guideOpen && ! launcherHidden"
                :has-session="hasSession"
                :mobile="session?.mobile ?? ''"
                @close="guideOpen = false"
                @join="openGate"
            />

            <div v-if="! launcherHidden" class="fixed right-3 z-[60] sm:right-4" style="bottom: var(--demo-fab, 1rem)">
                <button
                    type="button"
                    class="relative flex h-11 items-center gap-2 rounded-full bg-accent px-3.5 text-accent-foreground shadow-lg transition hover:scale-105 focus-visible:ring-2 focus-visible:ring-accent"
                    :aria-label="t('demo.launcher_open')"
                    :aria-expanded="guideOpen"
                    @click="toggleGuide"
                >
                    <AppIcon name="route" size="15" />
                    <span class="hidden text-[13px] font-semibold sm:inline">{{ t('demo.launcher') }}</span>
                </button>
                <span v-if="attention" class="pointer-events-none absolute -left-0.5 -top-0.5 flex h-3 w-3" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-danger opacity-75" />
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-danger" />
                </span>
                <button
                    type="button"
                    class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full border border-border bg-surface text-muted shadow hover:text-foreground"
                    :aria-label="t('demo.launcher_hide')"
                    @click="hideLauncher"
                >
                    <AppIcon name="xmark" size="10" />
                </button>
            </div>
        </template>
    </div>
</template>
