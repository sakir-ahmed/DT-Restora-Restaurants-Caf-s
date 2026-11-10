<script setup>
import { computed } from 'vue';
import { formatDateTime, formatRelative } from '@/lib/format';

const props = defineProps({
    value: { type: [String, Date], default: '' },
    relative: { type: Boolean, default: false },
});

const display = computed(() => (props.relative ? formatRelative(props.value) : formatDateTime(props.value)));

const tooltip = computed(() => formatDateTime(props.value));
</script>

<template>
    <span :title="tooltip">{{ display }}</span>
</template>
