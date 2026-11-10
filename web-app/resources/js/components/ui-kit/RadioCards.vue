<script setup>
defineProps({
    modelValue: { type: [String, Number], default: null },
    options: { type: Array, default: () => [] }, // [{value, label, icon, hint}]
    name: { type: String, default: 'radio-cards' },
    touch: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="grid gap-2" :class="options.length > 2 ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-2'" role="radiogroup">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="modelValue === option.value"
            class="flex flex-col items-start gap-1 rounded-lg border p-2.5 text-left transition-colors focus-visible:ring-2 focus-visible:ring-accent"
            :class="[
                modelValue === option.value
                    ? 'border-accent bg-accent/10 text-foreground'
                    : 'border-border bg-surface text-foreground hover:border-muted/60',
                touch ? 'min-h-11' : '',
            ]"
            @click="emit('update:modelValue', option.value)"
        >
            <span class="flex w-full items-center justify-between gap-2">
                <span class="text-[13px] font-medium">{{ option.label }}</span>
                <slot name="icon" :option="option" />
            </span>
            <span v-if="option.hint" class="text-xs text-muted">{{ option.hint }}</span>
        </button>
    </div>
</template>
