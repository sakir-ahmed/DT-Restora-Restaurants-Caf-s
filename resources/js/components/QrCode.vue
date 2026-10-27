<script setup>
import { computed } from 'vue';
import { qrPath } from '@/lib/qr';

const props = defineProps({
    value: { type: String, required: true },
    size: { type: Number, default: 192 },
    dark: { type: String, default: '#1a1208' },
    light: { type: String, default: '#ffffff' },
    border: { type: Number, default: 2 },
    label: { type: String, default: 'QR code' },
});

const drawing = computed(() => qrPath(props.value, props.border));
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 ${drawing.viewBox} ${drawing.viewBox}`"
        shape-rendering="crispEdges"
        role="img"
        :aria-label="label"
    >
        <rect width="100%" height="100%" :fill="light" />
        <path :d="drawing.path" :fill="dark" />
    </svg>
</template>
