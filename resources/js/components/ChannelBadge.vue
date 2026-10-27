<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    channel: { type: String, default: 'pos' },
    aggregator: { type: Object, default: null },
    compact: { type: Boolean, default: false },
});

// Brand colours of the delivery platforms; own channels use theme tokens.
const BRANDS = {
    foodpanda: { color: '#d70f64', short: 'FP' },
    pathao_food: { color: '#e5262b', short: 'PF' },
    ubereats: { color: '#06c167', short: 'UE' },
    foodi: { color: '#f58220', short: 'FD' },
    hungrynaki: { color: '#7a3ff2', short: 'HN' },
    shohoz_food: { color: '#00a651', short: 'SF' },
};

const brand = computed(() => (props.aggregator ? BRANDS[props.aggregator.key] ?? { color: '#475569', short: props.aggregator.name.slice(0, 2).toUpperCase() } : null));

const label = computed(() => {
    if (props.aggregator) {
        return props.aggregator.name;
    }

    return t({ website: 'orders.channel_website', qr: 'orders.channel_qr' }[props.channel] ?? 'orders.channel_pos');
});
</script>

<template>
    <span
        v-if="channel !== 'pos' || aggregator"
        class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white"
        :style="{ backgroundColor: brand ? brand.color : 'var(--color-accent)' }"
        :title="label"
    >
        <span v-if="brand" class="flex h-3.5 w-3.5 items-center justify-center rounded-sm bg-white/25 text-[8px] font-bold">{{ brand.short }}</span>
        <AppIcon v-else :name="channel === 'qr' ? 'qrcode' : 'globe'" size="10" />
        <span v-if="! compact">{{ label }}</span>
    </span>
</template>
