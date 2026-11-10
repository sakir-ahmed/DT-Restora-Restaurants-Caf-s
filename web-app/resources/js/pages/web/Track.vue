<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref } from 'vue';
import OrderPlacedDialog from '@/components/site/OrderPlacedDialog.vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';

const props = defineProps({
    order: { type: Object, required: true },
    map: { type: Object, default: () => ({}) },
});

const page = usePage();
const placed = ref(page.props.flash?.placed ?? null);
const showPlaced = ref(placed.value !== null);

let mapInstance = null;

onMounted(async () => {
    const location = props.order.rider_location;

    if (! location) {
        return;
    }

    const leaflet = await import('leaflet');
    await import('leaflet/dist/leaflet.css');
    const L = leaflet.default;
    const node = document.getElementById('rider-map');

    if (! node) {
        return;
    }

    const lat = Number(location.latitude);
    const lng = Number(location.longitude);
    mapInstance = L.map(node).setView([lat, lng], 15);
    L.tileLayer(props.map.tiles, { attribution: props.map.attribution }).addTo(mapInstance);
    L.marker([lat, lng]).addTo(mapInstance);
});

onBeforeUnmount(() => {
    mapInstance?.remove();
    mapInstance = null;
});

const steps = [
    { key: 'pending', label: t('web.track.placed'), icon: 'receipt' },
    { key: 'preparing', label: t('web.track.preparing'), icon: 'hourglass-half' },
    { key: 'ready', label: t('web.track.ready'), icon: 'bell' },
    { key: 'served', label: t('web.track.on_the_way'), icon: 'motorcycle' },
    { key: 'paid', label: t('web.track.completed'), icon: 'circle-check' },
];

function activeIndex(status) {
    const map = { pending: 0, preparing: 1, ready: 2, served: 3, paid: 4 };

    return map[status] ?? 0;
}
</script>

<template>
    <Head :title="t('web.track_title')" />

    <OrderPlacedDialog :open="showPlaced" :order="placed" @close="showPlaced = false" />

    <div class="mx-auto max-w-lg">
        <div class="rounded-xl border border-border bg-surface p-5">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-lg font-bold">{{ order.invoice_no }}</h1>
                    <p class="mt-0.5 text-sm text-muted">{{ order.branch?.name }}</p>
                </div>
                <span class="text-xl font-bold tabular-nums">{{ formatMoney(order.total) }}</span>
            </div>

            <!-- Timeline -->
            <ol class="mt-6 space-y-0">
                <li v-for="(step, index) in steps" :key="step.key" class="relative flex gap-3 pb-6 last:pb-0">
                    <span
                        v-if="index < steps.length - 1"
                        class="absolute left-[15px] top-8 h-full w-0.5"
                        :class="index < activeIndex(order.status) ? 'bg-success' : 'bg-border'"
                        aria-hidden="true"
                    />
                    <span
                        class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                        :class="index <= activeIndex(order.status) ? 'bg-success text-white' : 'bg-border text-muted'"
                    >
                        <AppIcon :name="step.icon" size="12" />
                    </span>
                    <div class="pt-1">
                        <p class="text-sm font-medium" :class="index <= activeIndex(order.status) ? 'text-foreground' : 'text-muted'">{{ step.label }}</p>
                        <p v-if="index === activeIndex(order.status) && order.status !== 'paid'" class="mt-0.5 text-xs text-success">
                            {{ t('web.track.current') }}
                        </p>
                    </div>
                </li>
            </ol>

            <div v-if="order.rider_location" id="rider-map" class="mt-4 h-56 overflow-hidden rounded-lg border border-border" />

            <div class="mt-4 border-t border-border pt-3">
                <p class="text-sm font-semibold">{{ t('web.order_summary') }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="(item, index) in order.items" :key="index" class="flex justify-between">
                        <span class="text-muted">{{ item.quantity }}x {{ item.name }}</span>
                        <span class="tabular-nums">{{ formatMoney(item.line_total) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <p class="mt-3 text-center text-xs text-muted">{{ t('web.track_note') }}</p>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
