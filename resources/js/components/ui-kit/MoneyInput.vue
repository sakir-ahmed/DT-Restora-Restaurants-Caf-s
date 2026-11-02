<script setup>
import { computed, ref } from 'vue';
import { groupBD } from '@/lib/format';
import { selectIfZero } from '@/lib/numericInput';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    disabled: { type: Boolean, default: false },
    readOnly: { type: Boolean, default: false },
    prefix: { type: String, default: '৳' },
});

const emit = defineEmits(['update:modelValue']);

const focused = ref(false);
const typed = ref('');

const display = computed(() => {
    if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) {
        return '';
    }

    const fixed = Number(props.modelValue).toFixed(2);
    const [intPart, decPart] = fixed.split('.');

    return groupBD(intPart) + '.' + decPart;
});

// While the field has focus it shows exactly what the user typed; the grouped
// two-decimal format only appears once they leave it.
const shown = computed(() => (focused.value ? typed.value : display.value));

function onFocus(event) {
    typed.value = props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue);
    focused.value = true;
    selectIfZero(event.target);
}

function onInput(event) {
    const raw = event.target.value.replace(/[^0-9.]/g, '');
    const parts = raw.split('.');

    const normalized = parts.length > 1
        ? parts[0] + '.' + parts.slice(1).join('').slice(0, 2)
        : parts[0];

    typed.value = normalized;
    event.target.value = normalized;
    emit('update:modelValue', normalized);
}
</script>

<template>
    <div class="flex h-7 items-stretch overflow-hidden rounded-md border border-border bg-surface focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/40">
        <span v-if="prefix" class="flex items-center bg-background px-2 text-[13px] text-muted" aria-hidden="true">{{ prefix }}</span>

        <input
            type="text"
            inputmode="decimal"
            :value="shown"
            :disabled="disabled || readOnly"
            class="h-full w-full border-0 bg-transparent px-2 text-right text-[13px] tabular-nums text-foreground outline-none placeholder:text-muted/60 disabled:cursor-not-allowed disabled:opacity-60"
            @focus="onFocus"
            @blur="focused = false"
            @input="onInput"
        >
    </div>
</template>
