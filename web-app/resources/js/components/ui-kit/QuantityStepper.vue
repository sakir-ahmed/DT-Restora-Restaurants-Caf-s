<script setup>
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: 0 },
    step: { type: Number, default: 1 },
    min: { type: Number, default: 0 },
    max: { type: Number, default: null },
    touch: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const value = computed(() => Number(props.modelValue ?? 0));

function change(delta) {
    if (props.disabled) {
        return;
    }

    let next = value.value + delta * props.step;

    if (next < props.min) {
        next = props.min;
    }

    if (props.max !== null && next > props.max) {
        next = props.max;
    }

    emit('update:modelValue', next);
}

const size = computed(() => (props.touch ? 'h-11 w-11 text-base' : 'h-7 w-7 text-[13px]'));
</script>

<template>
    <div class="inline-flex items-center gap-1" role="group">
        <button
            type="button"
            :class="size"
            :disabled="disabled || value <= min"
            class="flex items-center justify-center rounded-md border border-border bg-surface text-foreground hover:bg-background disabled:cursor-not-allowed disabled:opacity-50"
            :aria-label="t('common.decrease')"
            @click="change(-1)"
        >
            <span aria-hidden="true">&ndash;</span>
        </button>

        <input
            type="text"
            inputmode="decimal"
            :value="modelValue"
            :disabled="disabled"
            class="h-7 w-14 rounded-md border border-border bg-surface text-center text-[13px] tabular-nums text-foreground outline-none focus:border-accent disabled:cursor-not-allowed disabled:opacity-60"
            :class="touch ? 'h-11 w-16 text-base' : ''"
            aria-live="polite"
            readonly
        >

        <button
            type="button"
            :class="size"
            :disabled="disabled || (max !== null && value >= max)"
            class="flex items-center justify-center rounded-md border border-border bg-surface text-foreground hover:bg-background disabled:cursor-not-allowed disabled:opacity-50"
            :aria-label="t('common.increase')"
            @click="change(1)"
        >
            <AppIcon name="plus" :size="touch ? 16 : 12" />
        </button>
    </div>
</template>
