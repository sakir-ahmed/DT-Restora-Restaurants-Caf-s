<script setup>
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';

/**
 * The quantity control used inside POS dialogs (sizes, add-ons, item quantity):
 * a bordered group with a neutral minus, the count and an accent plus.
 * The parent decides what a click does, so it works for live and drafted quantities.
 */
defineProps({
    value: { type: Number, default: 0 },
    canDecrease: { type: Boolean, default: true },
    canIncrease: { type: Boolean, default: true },
});

defineEmits(['decrease', 'increase']);
</script>

<template>
    <div class="flex shrink-0 items-center overflow-hidden rounded-md border border-border bg-surface" role="group">
        <button type="button" class="flex h-9 w-9 items-center justify-center text-foreground disabled:opacity-40" :disabled="! canDecrease" :aria-label="t('common.decrease')" @click="$emit('decrease')">
            <AppIcon name="minus" size="12" />
        </button>
        <span class="w-6 text-center text-sm font-semibold tabular-nums text-foreground" aria-live="polite">{{ value }}</span>
        <button type="button" class="flex h-9 w-9 items-center justify-center bg-accent text-accent-foreground disabled:opacity-40" :disabled="! canIncrease" :aria-label="t('common.increase')" @click="$emit('increase')">
            <AppIcon name="plus" size="12" />
        </button>
    </div>
</template>
