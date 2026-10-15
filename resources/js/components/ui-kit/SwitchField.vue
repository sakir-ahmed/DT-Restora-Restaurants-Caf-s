<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const on = computed(() => props.modelValue);
</script>

<template>
    <label class="inline-flex cursor-pointer items-center gap-2" :class="disabled ? 'cursor-not-allowed opacity-60' : ''">
        <button
            type="button"
            role="switch"
            :aria-checked="on"
            :aria-label="label"
            :disabled="disabled"
            class="relative h-[18px] w-8 rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-accent"
            :class="on ? 'bg-success' : 'bg-border'"
            @click="!disabled && emit('update:modelValue', !on)"
        >
            <span
                class="absolute top-0.5 h-3.5 w-3.5 rounded-full bg-white transition-transform"
                :class="on ? 'translate-x-[15px]' : 'translate-x-0.5'"
            />
        </button>

        <span v-if="label" class="text-[13px] text-foreground">{{ label }}</span>
    </label>
</template>
