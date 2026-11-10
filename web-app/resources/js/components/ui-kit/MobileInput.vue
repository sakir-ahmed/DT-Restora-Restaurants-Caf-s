<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    inputId: { type: String, default: null },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

// The prefix shows +88 and 01; the box holds the last nine digits and the model keeps all eleven.
const rest = computed(() => (props.modelValue ?? '').replace(/^01/, ''));

function onInput(event) {
    let digits = event.target.value.replace(/\D/g, '');

    // A pasted full number (01..., 8801... or +8801...) loses its prefix. Anything up to nine
    // digits is typed by hand and kept as is, since an 018 number really does continue with 8.
    if (digits.length > 9 && digits.startsWith('88')) {
        digits = digits.slice(2);
    }

    if (digits.length > 9 && digits.startsWith('01')) {
        digits = digits.slice(2);
    }

    digits = digits.slice(0, 9);

    event.target.value = digits;
    emit('update:modelValue', digits === '' ? '' : `01${digits}`);
}
</script>

<template>
    <div
        class="flex h-7 items-stretch overflow-hidden rounded-md border bg-surface focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/40"
        :class="invalid ? 'border-danger' : 'border-border'"
    >
        <span class="flex shrink-0 items-center whitespace-nowrap bg-background pl-2 pr-1 text-[13px] text-muted" aria-hidden="true">+88 01</span>

        <input
            :id="inputId"
            :aria-invalid="invalid || undefined"
            type="tel"
            inputmode="numeric"
            :value="rest"
            :disabled="disabled"
            maxlength="13"
            placeholder="XXXXXXXXX"
            class="h-full w-full border-0 bg-transparent px-2 text-[13px] tabular-nums text-foreground outline-none placeholder:text-muted/60 disabled:cursor-not-allowed disabled:opacity-60"
            @input="onInput"
        >
    </div>
</template>
