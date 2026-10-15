<script setup>
import { computed } from 'vue';
import { formatMoney } from '@/lib/format';

const props = defineProps({
    value: { type: [String, Number], default: 0 },
    decimals: { type: Number, default: 2 },
    symbol: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    negativeDanger: { type: Boolean, default: false },
});

const formatted = computed(() => formatMoney(props.value, {
    decimals: props.decimals,
    symbol: props.symbol,
    compact: props.compact,
}));

const classes = computed(() => ({
    'text-danger': props.negativeDanger && Number(props.value) < 0,
    'text-muted': Number(props.value) === 0,
}));
</script>

<template>
    <span class="tabular-nums" :class="classes" :title="formatted">{{ formatted }}</span>
</template>
