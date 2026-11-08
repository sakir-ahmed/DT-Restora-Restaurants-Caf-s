<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ChannelBadge from '@/components/ChannelBadge.vue';
import { initEcho } from '@/lib/echo.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { isSoundMuted, playSound, setSoundMuted } from '@/lib/sounds';

const props = defineProps({
    orders: { type: Array, required: true },
    branchId: { type: Number, required: true },
});

const orders = ref([...props.orders]);
const muted = ref(isSoundMuted());
const soundEnabled = ref(false);
const POLL_SECONDS = 15000;
let pollTimer = null;
let echo = null;

const statusFlow = ['pending', 'preparing', 'ready'];

const byStatus = computed(() => ({
    pending: orders.value.filter((order) => order.status === 'pending'),
    preparing: orders.value.filter((order) => order.status === 'preparing'),
    ready: orders.value.filter((order) => order.status === 'ready' || order.status === 'served'),
}));

function elapsed(order) {
    const minutes = Math.floor((Date.now() - new Date(order.created_at).getTime()) / 60000);

    if (minutes < 60) {
        return `${minutes} min`;
    }

    return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
}

// What each order's status was at the last refresh, to hear what changed since.
let known = new Map(orders.value.map((order) => [order.id, order.status]));
const STATUS_SOUND = { preparing: 'kds_preparing', ready: 'kds_ready', served: 'kds_served' };

/**
 * A new ticket, a status change or a finished order each has its own sound. Comparing two
 * refreshes covers every way a change arrives: this screen, another screen or a website order.
 */
function playChanges(next) {
    const nextIds = new Set(next.map((order) => order.id));

    next.forEach((order) => {
        const before = known.get(order.id);

        if (before === undefined) {
            playSound(order.channel && order.channel !== 'pos' ? 'online_order' : 'kds_new');
        } else if (before !== order.status && STATUS_SOUND[order.status]) {
            playSound(STATUS_SOUND[order.status]);
        }
    });

    // A ready order that left the board was served.
    known.forEach((status, id) => {
        if (! nextIds.has(id) && status === 'ready') {
            playSound('kds_served');
        }
    });

    known = new Map(next.map((order) => [order.id, order.status]));
}

function toggleMute() {
    muted.value = ! muted.value;
    setSoundMuted(muted.value);
}

function reload() {
    router.reload({
        only: ['orders'],
        onSuccess: (page) => {
            playChanges(page.props.orders);
            orders.value = [...page.props.orders];
        },
    });
}

function advance(order) {
    const index = statusFlow.indexOf(order.status);

    const next = statusFlow[Math.min(index + 1, statusFlow.length - 1)] ?? 'served';

    router.patch(route('admin.orders.status', order.id), { status: next }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            orders.value = orders.value.filter((entry) => entry.id !== order.id || next === 'preparing');
            reload();
        },
    });
}

function enableSound() {
    soundEnabled.value = true;
    playSound('notification', { preview: true });
}

onMounted(() => {
    echo = initEcho();

    if (echo) {
        echo.private(`branch.${props.branchId}.kitchen`)
            .listen('.OrderCreated', (event) => {
                notify.info(t('kds.new_order', { invoice: event.invoice_no }), null, { silent: true });
                reload();
            })
            .listen('.OrderStatusChanged', () => reload());
    }

    pollTimer = setInterval(reload, POLL_SECONDS);

    setInterval(() => {
        // force re-render of elapsed timers
        orders.value = [...orders.value];
    }, 30000);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);

    echo?.leave(`branch.${props.branchId}.kitchen`);
});
</script>

<template>
    <div class="flex h-full flex-col gap-3">
        <header class="flex items-center justify-between">
            <h1 class="flex items-center gap-2 text-lg font-semibold text-foreground">
                <AppIcon name="utensils" size="18" class="text-accent" />
                {{ t('kds.title') }}
            </h1>

            <div class="flex items-center gap-2">
                <button
                    v-if="! soundEnabled"
                    type="button"
                    class="h-8 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground"
                    @click="enableSound"
                >
                    {{ t('kds.enable_sound') }}
                </button>
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-md text-muted hover:bg-surface hover:text-foreground"
                    :class="muted ? 'text-danger' : ''"
                    :aria-label="t('kds.toggle_sound')"
                    @click="toggleMute"
                >
                    <AppIcon :name="muted ? 'circle-xmark' : 'bell'" size="16" />
                </button>
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-md text-muted hover:bg-surface hover:text-foreground"
                    :aria-label="t('common.retry')"
                    @click="reload"
                >
                    <AppIcon name="rotate" size="16" />
                </button>
            </div>
        </header>

        <div class="grid flex-1 grid-cols-1 gap-3 md:grid-cols-3">
            <section
                v-for="(status, key) in byStatus"
                :key="key"
                class="flex flex-col overflow-hidden rounded-lg border border-border bg-surface"
            >
                <header class="flex items-center justify-between border-b border-border px-3 py-2">
                    <h2 class="text-sm font-semibold text-foreground">{{ t('status.' + (key === 'ready' ? 'ready' : key)) }}</h2>
                    <span class="rounded-full bg-background px-2 py-0.5 text-xs font-semibold tabular-nums text-muted">{{ status.length }}</span>
                </header>

                <div class="thin-scrollbar flex-1 space-y-2 overflow-y-auto p-2">
                    <p v-if="status.length === 0" class="py-6 text-center text-sm text-muted">{{ t('common.empty_default') }}</p>

                    <article
                        v-for="order in status"
                        :key="order.id"
                        class="rounded-lg border p-3"
                        :class="elapsed(order) > 15 ? 'border-danger/60 bg-danger/5' : 'border-border bg-background/50'"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-foreground">{{ order.table ? order.table.name : t('orders.type.' + order.order_type) }}</p>
                                <p class="flex items-center gap-1.5 text-xs text-muted">
                                    {{ order.invoice_no }}
                                    <ChannelBadge :channel="order.channel" :aggregator="order.aggregator_channel" />
                                </p>
                            </div>
                            <span class="flex items-center gap-1 text-xs font-semibold tabular-nums" :class="elapsed(order) > 15 ? 'text-danger' : 'text-muted'">
                                <AppIcon name="clock" size="10" />
                                {{ elapsed(order) }}
                            </span>
                        </div>

                        <ul class="mt-2 space-y-1">
                            <li v-for="item in order.items" :key="item.id" class="flex items-baseline justify-between gap-2 text-sm">
                                <span class="text-foreground">
                                    <span class="font-bold">{{ item.quantity }}x</span>
                                    {{ item.name }}
                                </span>
                                <span v-if="item.add_ons?.length" class="text-right text-xs text-muted">{{ item.add_ons.map((a) => a.name).join(', ') }}</span>
                            </li>
                        </ul>

                        <button
                            v-if="order.status !== 'served'"
                            type="button"
                            class="mt-3 flex h-11 w-full items-center justify-center gap-1.5 rounded-lg text-base font-semibold"
                            :class="order.status === 'ready'
                                ? 'bg-success text-white'
                                : 'bg-accent text-accent-foreground'"
                            @click="advance(order)"
                        >
                            <AppIcon :name="order.status === 'pending' ? 'hourglass-half' : order.status === 'preparing' ? 'bell' : 'circle-check'" size="16" />
                            {{ order.status === 'pending' ? t('kds.start_preparing') : order.status === 'preparing' ? t('kds.mark_ready') : t('kds.mark_served') }}
                        </button>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
