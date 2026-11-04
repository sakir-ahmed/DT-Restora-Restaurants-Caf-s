<script setup>
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

defineEmits(['click']);
</script>

<template>
    <button
        type="button"
        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-muted transition-colors hover:bg-border/60 hover:text-foreground focus-visible:ring-2 focus-visible:ring-accent"
        :title="t('dashboard.info.button')"
        :aria-label="t('dashboard.info.button')"
        @click.stop="$emit('click')"
    >
        <AppIcon name="circle-info" size="12" />
    </button>
</template>
