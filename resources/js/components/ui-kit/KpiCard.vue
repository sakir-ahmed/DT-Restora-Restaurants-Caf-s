<script setup>
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    change: { type: [String, Number], default: null }, // percent vs previous period
    trend: { type: String, default: null }, // up | down | flat (fallback derived from change)
    icon: { type: String, default: null },
    state: { type: String, default: null }, // success | danger (semantic target state)
    loading: { type: Boolean, default: false },
    sparkline: { type: Array, default: () => [] },
});

const emit = defineEmits(['click']);

const numericChange = computed(() => Number(props.change));

const direction = computed(() => props.trend ?? (numericChange.value > 0 ? 'up' : numericChange.value < 0 ? 'down' : 'flat'));

const changeClasses = computed(() => (direction.value === 'up' ? 'text-success' : direction.value === 'down' ? 'text-danger' : 'text-muted'));

const points = computed(() => {
    if (props.sparkline.length < 2) {
        return '';
    }

    const max = Math.max(...props.sparkline);
    const min = Math.min(...props.sparkline);
    const span = max - min || 1;

    return props.sparkline
        .map((point, index) => `${(index / (props.sparkline.length - 1)) * 60},${20 - ((point - min) / span) * 18}`)
        .join(' ');
});
</script>

<template>
    <component
        :is="icon ? 'button' : 'div'"
        class="flex w-full flex-col gap-1 rounded-lg border border-border bg-surface p-3 text-left transition-shadow"
        :class="icon ? 'hover:shadow-md focus-visible:ring-2 focus-visible:ring-accent' : ''"
        :type="icon ? 'button' : undefined"
        @click="emit('click')"
    >
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ label }}</span>

            <span
                v-if="state"
                class="flex h-5 w-5 items-center justify-center rounded-full"
                :class="state === 'danger' ? 'bg-danger/10 text-danger' : 'bg-success/10 text-success'"
            >
                <AppIcon :name="state === 'danger' ? 'triangle-exclamation' : 'circle-check'" size="10" />
            </span>
        </div>

        <div v-if="loading" class="h-7 w-24 animate-pulse rounded bg-border/60" />
        <span v-else class="text-2xl font-bold tracking-tight text-foreground tabular-nums">{{ value }}</span>

        <div class="flex items-end justify-between gap-2">
            <span
                v-if="change !== null && change !== ''"
                class="flex items-center gap-0.5 text-xs font-medium tabular-nums"
                :class="changeClasses"
            >
                <AppIcon :name="direction === 'up' ? 'arrow-up' : 'arrow-down'" size="10" />
                {{ numericChange > 0 ? '+' : '' }}{{ change }}%
            </span>
            <span v-else />

            <svg v-if="points" viewBox="0 0 60 20" class="h-5 w-16" preserveAspectRatio="none" aria-hidden="true">
                <polyline :points="points" fill="none" stroke="currentColor" stroke-width="1.5" class="text-muted" />
            </svg>
        </div>
    </component>
</template>
