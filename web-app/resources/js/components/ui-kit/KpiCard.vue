<script setup>
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';
import InfoButton from '@/components/ui-kit/InfoButton.vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    change: { type: [String, Number], default: null }, // percent vs previous period
    trend: { type: String, default: null }, // up | down | flat (fallback derived from change)
    icon: { type: String, default: null },
    state: { type: String, default: null }, // success | danger (semantic target state)
    loading: { type: Boolean, default: false },
    sparkline: { type: Array, default: () => [] },
    info: { type: Boolean, default: false }, // show the "how is this calculated" button
    action: { type: Boolean, default: false }, // the whole card opens something when pressed
    tone: { type: String, default: null }, // danger: show the value in red
});

const emit = defineEmits(['click', 'info']);

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
        class="flex w-full min-w-0 flex-col gap-0.5 rounded-lg border bg-surface p-2.5 text-left transition-shadow md:p-3"
        :class="[
            icon || action ? 'cursor-pointer hover:shadow-md focus-visible:ring-2 focus-visible:ring-accent' : '',
            tone === 'danger' ? 'border-danger/50 bg-danger/5' : 'border-border',
        ]"
        :type="icon ? 'button' : undefined"
        :role="action && ! icon ? 'button' : undefined"
        :tabindex="action && ! icon ? 0 : undefined"
        @click="emit('click')"
        @keydown.enter.self="action && emit('click')"
    >
        <div class="flex items-center justify-between gap-2">
            <span class="truncate text-[11px] font-medium uppercase tracking-wider md:text-xs" :class="[$page.props.locale === 'bn' ? 'normal-case tracking-normal' : '', tone === 'danger' ? 'text-danger' : 'text-muted']">{{ label }}</span>

            <div class="flex items-center gap-1">
                <span
                    v-if="state"
                    class="flex h-5 w-5 items-center justify-center rounded-full"
                    :class="state === 'danger' ? 'bg-danger/10 text-danger' : 'bg-success/10 text-success'"
                >
                    <AppIcon :name="state === 'danger' ? 'triangle-exclamation' : 'circle-check'" size="10" />
                </span>

                <InfoButton v-if="info" @click="emit('info')" />
            </div>
        </div>

        <div v-if="loading" class="h-7 w-24 animate-pulse rounded bg-border/60" />
        <span v-else class="truncate text-xl font-bold tracking-tight tabular-nums md:text-[22px]" :class="tone === 'danger' ? 'text-danger' : 'text-foreground'">{{ value }}</span>

        <div v-if="(change !== null && change !== '') || points" class="flex items-end justify-between gap-2">
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
