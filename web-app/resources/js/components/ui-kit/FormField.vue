<script setup>
import { computed, useId } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    label: { type: String, default: '' },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false },
    helper: { type: String, default: '' },
    error: { type: [String, Array], default: '' },
    for: { type: String, default: '' },
});

const slots = defineSlots();

const errorText = computed(() => {
    if (Array.isArray(props.error)) {
        return props.error[0] ?? '';
    }

    return props.error;
});

const inputId = useId();

const targetId = computed(() => props.for || inputId);

const describedBy = computed(() => {
    const ids = [];

    if (errorText.value) {
        ids.push(`${targetId.value}-error`);
    } else if (props.helper) {
        ids.push(`${targetId.value}-helper`);
    }

    return ids.length ? ids.join(' ') : undefined;
});
</script>

<template>
    <div class="min-w-0">
        <label
            v-if="label"
            :for="targetId"
            class="mb-1 block text-xs font-medium text-foreground"
        >
            {{ label }}
            <span v-if="required" class="text-danger" aria-hidden="true">*</span>
            <span v-if="optional && !required" class="text-muted">({{ t('common.optional') }})</span>
        </label>

        <slot :id="targetId" :aria-invalid="errorText ? 'true' : undefined" :aria-describedby="describedBy" />

        <p
            v-if="errorText"
            :id="`${targetId}-error`"
            class="mt-1 flex items-center gap-1 text-xs text-danger"
            role="alert"
        >
            <AppIcon name="triangle-exclamation" size="12" />
            {{ errorText }}
        </p>
        <p
            v-else-if="helper"
            :id="`${targetId}-helper`"
            class="mt-1 text-xs text-muted"
        >
            {{ helper }}
        </p>
    </div>
</template>
