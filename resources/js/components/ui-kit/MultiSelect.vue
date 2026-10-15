<script setup>
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] }, // [{value, label}]
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const selectedLabels = computed(() => props.options
    .filter((option) => props.modelValue.includes(option.value))
    .map((option) => option.label));

function toggle(value) {
    const next = props.modelValue.includes(value)
        ? props.modelValue.filter((item) => item !== value)
        : [...props.modelValue, value];

    emit('update:modelValue', next);
}
</script>

<template>
    <div>
        <div class="flex min-h-7 flex-wrap items-center gap-1 rounded-md border border-border bg-surface p-1 focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/40">
            <span
                v-for="(label, index) in selectedLabels"
                :key="label"
                class="inline-flex h-5 items-center gap-1 rounded bg-background px-1.5 text-xs text-foreground"
            >
                {{ label }}
                <button
                    type="button"
                    class="text-muted hover:text-danger"
                    :aria-label="t('common.remove')"
                    @click="toggle(modelValue[index] ?? options.find((o) => o.label === label)?.value)"
                >
                    <AppIcon name="xmark" size="10" />
                </button>
            </span>

            <select
                :value="''"
                class="h-5 min-w-24 flex-1 border-0 bg-transparent text-[13px] text-foreground outline-none"
                :aria-label="placeholder"
                @change="toggle($event.target.value)"
            >
                <option value="" disabled>{{ selectedLabels.length ? t('common.add_more') : (placeholder || t('common.select')) }}</option>
                <option v-for="option in options.filter((o) => !modelValue.includes(o.value))" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
        </div>
    </div>
</template>
