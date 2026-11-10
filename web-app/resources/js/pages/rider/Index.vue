<script setup>
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    deliveries: { type: Array, required: true },
    rider: { type: Object, required: true },
    codDue: { type: String, required: true },
});

const otpDialog = ref(null);
const otpValue = ref('');
const sharing = ref(false);
let watchId = null;

const activeStatuses = ['assigned', 'picked_up', 'on_the_way'];

const active = () => props.deliveries.filter((delivery) => activeStatuses.includes(delivery.status));
const history = () => props.deliveries.filter((delivery) => ! activeStatuses.includes(delivery.status));

function updateStatus(delivery, status, reason = null) {
    router.post(route('rider.status', delivery.id), { status, reason }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => notify.success(t('delivery.status_updated')),
        onError: (errors) => notify.error(Object.values(errors)[0] ?? t('delivery.otp_mismatch')),
    });
}

function requestOtp(delivery) {
    otpDialog.value = delivery;
    otpValue.value = '';
}

function confirmOtp() {
    router.post(route('rider.status', otpDialog.value.id), { status: 'delivered', otp: otpValue.value }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            otpDialog.value = null;
            notify.success(t('delivery.status_updated'));
        },
        onError: () => notify.error(t('delivery.otp_mismatch')),
    });
}

function toggleSharing() {
    if (sharing.value) {
        navigator.geolocation?.clearWatch(watchId);
        sharing.value = false;

        return;
    }

    if (! navigator.geolocation) {
        notify.error(t('delivery.no_gps'));

        return;
    }

    let lastSent = 0;

    watchId = navigator.geolocation.watchPosition((position) => {
        const now = Date.now();

        if (now - lastSent < 5000) {
            return;
        }

        lastSent = now;

        router.post('/rider/location', {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy: position.coords.accuracy,
            recorded_at: new Date(position.timestamp).toISOString(),
        }, {
            preserveState: true,
            only: [],
            onError: () => {}, // throttling is expected; ignore
        });
    }, () => notify.error(t('delivery.no_gps')), { enableHighAccuracy: true });

    sharing.value = true;
}

onMounted(() => {
    const interval = setInterval(() => router.reload({ only: ['deliveries'] }), 30000);

    onBeforeUnmount(() => {
        clearInterval(interval);

        if (watchId !== null) {
            navigator.geolocation?.clearWatch(watchId);
        }
    });
});
</script>

<template>
    <Head :title="t('delivery.rider_title')" />

    <div class="mx-auto max-w-md px-4 py-4">
        <!-- Header -->
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold">{{ rider.name }}</h1>
                <p class="text-xs text-muted">{{ t('delivery.rider_title') }}</p>
            </div>

            <button
                type="button"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium"
                :class="rider.on_duty ? 'bg-success/15 text-success' : 'bg-border text-muted'"
                @click="router.post(route('rider.duty'))"
            >
                <AppIcon name="circle-check" size="12" />
                {{ rider.on_duty ? t('delivery.on_duty') : t('delivery.off_duty') }}
            </button>
        </header>

        <!-- GPS + COD -->
        <div class="mt-3 grid grid-cols-2 gap-2">
            <button
                type="button"
                class="flex h-16 flex-col items-center justify-center gap-1 rounded-xl border text-sm font-medium"
                :class="sharing ? 'border-success bg-success/10 text-success' : 'border-border bg-surface text-foreground'"
                @click="toggleSharing"
            >
                <AppIcon name="location-dot" size="16" />
                {{ sharing ? t('delivery.sharing_on') : t('delivery.share_location') }}
            </button>

            <div class="flex h-16 flex-col items-center justify-center rounded-xl border border-border bg-surface">
                <span class="text-xs text-muted">{{ t('delivery.cod_due') }}</span>
                <span class="text-base font-bold tabular-nums text-danger">{{ formatMoney(codDue) }}</span>
            </div>
        </div>

        <p v-if="sharing" class="mt-1.5 text-center text-xs text-muted">{{ t('delivery.sharing_note') }}</p>

        <!-- Active deliveries -->
        <h2 class="mt-5 text-sm font-semibold">{{ t('delivery.active_deliveries') }}</h2>

        <p v-if="active().length === 0" class="mt-2 rounded-xl border border-border bg-surface p-6 text-center text-sm text-muted">
            {{ t('delivery.no_active') }}
        </p>

        <article v-for="delivery in active()" :key="delivery.id" class="mt-2 rounded-xl border border-border bg-surface p-3">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="font-semibold">{{ delivery.order?.invoice_no }}</p>
                    <p class="mt-0.5 truncate text-sm text-muted">{{ delivery.order?.delivery_address }}</p>
                    <p class="mt-0.5 text-xs text-muted">{{ delivery.order?.customer?.name }} - {{ delivery.order?.customer?.mobile }}</p>
                </div>
                <span v-if="Number(delivery.cod_amount) > 0" class="shrink-0 rounded-lg bg-danger/10 px-2 py-1 text-xs font-bold tabular-nums text-danger">
                    {{ t('web.cod') }} {{ formatMoney(delivery.cod_amount) }}
                </span>
            </div>

            <div class="mt-3 grid gap-1.5">
                <button
                    v-if="delivery.status === 'assigned'"
                    type="button"
                    class="flex h-12 items-center justify-center gap-2 rounded-lg bg-accent text-base font-semibold text-accent-foreground"
                    @click="updateStatus(delivery, 'picked_up')"
                >
                    <AppIcon name="boxes-stacked" size="14" />
                    {{ t('delivery.picked_up') }}
                </button>

                <button
                    v-if="delivery.status === 'picked_up'"
                    type="button"
                    class="flex h-12 items-center justify-center gap-2 rounded-lg bg-accent text-base font-semibold text-accent-foreground"
                    @click="updateStatus(delivery, 'on_the_way')"
                >
                    <AppIcon name="motorcycle" size="14" />
                    {{ t('delivery.on_the_way') }}
                </button>

                <button
                    v-if="delivery.status === 'on_the_way'"
                    type="button"
                    class="flex h-12 items-center justify-center gap-2 rounded-lg bg-success text-base font-semibold text-white"
                    @click="delivery.otp_code ? requestOtp(delivery) : updateStatus(delivery, 'delivered')"
                >
                    <AppIcon name="circle-check" size="14" />
                    {{ t('delivery.delivered') }}
                </button>

                <div class="flex gap-1.5">
                    <a
                        :href="`tel:${delivery.order?.customer?.mobile}`"
                        class="flex h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border border-border text-sm font-medium"
                    >
                        <AppIcon name="phone" size="12" />
                        {{ t('delivery.call') }}
                    </a>
                    <a
                        :href="`https://maps.google.com/?q=${encodeURIComponent(delivery.order?.delivery_address ?? '')}`"
                        target="_blank"
                        class="flex h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border border-border text-sm font-medium"
                    >
                        <AppIcon name="map-location-dot" size="12" />
                        {{ t('delivery.navigate') }}
                    </a>
                    <button
                        type="button"
                        class="flex h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border border-danger/40 text-sm font-medium text-danger"
                        @click="updateStatus(delivery, 'failed', t('delivery.default_fail_reason'))"
                    >
                        <AppIcon name="circle-xmark" size="12" />
                        {{ t('delivery.failed') }}
                    </button>
                </div>
            </div>
        </article>

        <!-- History -->
        <details v-if="history().length" class="mt-5">
            <summary class="cursor-pointer text-sm font-semibold">{{ t('delivery.history') }} ({{ history().length }})</summary>
            <ul class="mt-2 space-y-1">
                <li v-for="delivery in history()" :key="delivery.id" class="flex items-center justify-between rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                    <span>{{ delivery.order?.invoice_no }}</span>
                    <span :class="delivery.status === 'delivered' ? 'text-success' : 'text-danger'">{{ t('status.' + (delivery.status === 'delivered' ? 'delivered' : 'failed')) }}</span>
                </li>
            </ul>
        </details>

        <!-- OTP dialog -->
        <Teleport to="body">
            <div v-if="otpDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="otpDialog = null">
                <div class="w-full max-w-xs rounded-xl border border-border bg-surface p-4 text-center">
                    <h3 class="font-semibold">{{ t('delivery.otp_title') }}</h3>
                    <p class="mt-1 text-xs text-muted">{{ t('delivery.otp_hint') }}</p>

                    <input
                        v-model="otpValue"
                        type="text"
                        inputmode="numeric"
                        maxlength="4"
                        class="mt-3 h-12 w-full rounded-md border border-border bg-background text-center text-2xl tracking-[0.5em] tabular-nums"
                        autofocus
                    >

                    <div class="mt-3 flex justify-center gap-2">
                        <button type="button" class="h-10 rounded-lg border border-border px-4 text-sm" @click="otpDialog = null">{{ t('common.cancel') }}</button>
                        <button type="button" :disabled="otpValue.length !== 4" class="h-10 rounded-lg bg-success px-4 text-sm font-semibold text-white disabled:opacity-50" @click="confirmOtp">
                            {{ t('delivery.delivered') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script>
export default { layout: null };
</script>
