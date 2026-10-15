<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [Boolean, Number, String], default: false },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const on = computed(() => props.modelValue === true || props.modelValue === 1 || props.modelValue === '1');

function toggle() {
    if (props.disabled) {
        return;
    }

    emit('update:modelValue', ! on.value);
}
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="on"
        :aria-label="label || undefined"
        :disabled="disabled"
        class="inline-flex items-center gap-2 border-0 bg-transparent p-0 text-left disabled:cursor-not-allowed disabled:opacity-60"
        @click="toggle"
    >
        <span
            class="relative h-[18px] w-8 shrink-0 rounded-full transition-colors"
            :class="on ? 'bg-success' : 'bg-foreground/25'"
        >
            <span
                class="absolute top-0.5 left-0.5 h-3.5 w-3.5 rounded-full bg-white shadow-sm transition-transform"
                :class="on ? 'translate-x-3.5' : 'translate-x-0'"
            />
        </span>

        <span v-if="label" class="text-[13px] text-foreground">{{ label }}</span>
    </button>
</template>
