<script setup>
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    status: { type: String, required: true },
});

/**
 * Shared mapping of enum-ish status values to color, icon and label key.
 */
const MAP = {
    active: { classes: 'bg-success/10 text-success', icon: 'circle-check' },
    inactive: { classes: 'bg-muted/10 text-muted', icon: 'circle-xmark' },
    paid: { classes: 'bg-success/10 text-success', icon: 'circle-check' },
    pending: { classes: 'bg-warning/15 text-warning', icon: 'clock' },
    preparing: { classes: 'bg-warning/15 text-warning', icon: 'hourglass-half' },
    ready: { classes: 'bg-accent/15 text-accent-foreground', icon: 'bell' },
    served: { classes: 'bg-success/10 text-success', icon: 'circle-check' },
    delivered: { classes: 'bg-success/10 text-success', icon: 'motorcycle' },
    void: { classes: 'bg-danger/10 text-danger', icon: 'circle-xmark' },
    cancelled: { classes: 'bg-danger/10 text-danger', icon: 'circle-xmark' },
    failed: { classes: 'bg-danger/10 text-danger', icon: 'triangle-exclamation' },
    draft: { classes: 'bg-muted/10 text-muted', icon: 'pen-to-square' },
    approved: { classes: 'bg-success/10 text-success', icon: 'circle-check' },
    rejected: { classes: 'bg-danger/10 text-danger', icon: 'circle-xmark' },
    open: { classes: 'bg-warning/15 text-warning', icon: 'lock' },
    closed: { classes: 'bg-muted/10 text-muted', icon: 'lock' },
    free: { classes: 'bg-success/10 text-success', icon: 'circle-check' },
    occupied: { classes: 'bg-warning/15 text-warning', icon: 'utensils' },
    reserved: { classes: 'bg-accent/15 text-accent-foreground', icon: 'bell-concierge' },
    low: { classes: 'bg-warning/15 text-warning', icon: 'triangle-exclamation' },
    high: { classes: 'bg-danger/10 text-danger', icon: 'triangle-exclamation' },
};

const meta = computed(() => MAP[props.status] ?? { classes: 'bg-muted/10 text-muted', icon: 'circle-info' });

const label = computed(() => {
    const key = `status.${props.status}`;

    return t(key) === key ? props.status : t(key);
});
</script>

<template>
    <span
        class="inline-flex h-5 min-w-[104px] items-center justify-center gap-1 rounded-md px-2 text-[11px] font-medium"
        :class="meta.classes"
    >
        <AppIcon :name="meta.icon" size="10" />
        {{ label }}
    </span>
</template>
