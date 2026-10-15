<script setup>
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

defineProps({
    message: { type: String, default: '' },
});

defineEmits(['retry']);
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-2 py-8 text-center" role="alert">
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-danger/10 text-danger">
            <AppIcon name="triangle-exclamation" size="24" />
        </span>

        <p class="text-sm font-semibold text-foreground">{{ t('common.error_title') }}</p>
        <p class="max-w-sm text-[13px] text-muted">{{ message || t('common.error_generic') }}</p>

        <button
            type="button"
            class="mt-1 inline-flex h-7 items-center gap-1 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background"
            @click="$emit('retry')"
        >
            <AppIcon name="rotate" size="12" />
            {{ t('common.retry') }}
        </button>
    </div>
</template>
