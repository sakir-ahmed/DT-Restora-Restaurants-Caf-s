<script setup>
const props = defineProps({
    modelValue: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function onInput(event) {
    let value = event.target.value.replace(/\D/g, '');

    if (value.startsWith('88')) {
        value = value.slice(2);
    }

    if (value.length > 11) {
        value = value.slice(0, 11);
    }

    if (! value.startsWith('01')) {
        value = '01' + value.replace(/^0+/, '');
    }

    emit('update:modelValue', value);
}
</script>

<template>
    <div class="flex h-7 items-stretch overflow-hidden rounded-md border border-border bg-surface focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/40">
        <span class="flex items-center bg-background px-2 text-[13px] text-muted" aria-hidden="true">+88</span>

        <input
            type="tel"
            inputmode="numeric"
            :value="modelValue"
            :disabled="disabled"
            maxlength="11"
            placeholder="01XXXXXXXXX"
            class="h-full w-full border-0 bg-transparent px-2 text-[13px] tabular-nums text-foreground outline-none placeholder:text-muted/60 disabled:cursor-not-allowed disabled:opacity-60"
            @input="onInput"
        >
    </div>
</template>
