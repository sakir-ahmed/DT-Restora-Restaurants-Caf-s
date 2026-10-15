<script setup>
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    status: { type: [Number, String], default: 500 },
});

const meta = computed(() => ({
    403: { icon: 'lock', title: t('errors.403_title'), message: t('errors.403_message') },
    404: { icon: 'magnifying-glass', title: t('errors.404_title'), message: t('errors.404_message') },
    500: { icon: 'triangle-exclamation', title: t('errors.500_title'), message: t('errors.500_message') },
    503: { icon: 'plug-circle-xmark', title: t('errors.503_title'), message: t('errors.503_message') },
}[Number(props.status)] ?? {
    icon: 'triangle-exclamation',
    title: t('errors.500_title'),
    message: t('errors.500_message'),
}));

const reference = computed(() => `DT-${Date.now().toString(36).toUpperCase()}`);
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-3 bg-background p-6 text-center">
        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-surface text-muted">
            <AppIcon :name="meta.icon" size="28" />
        </span>

        <p class="text-5xl font-bold tracking-tight text-foreground">{{ status }}</p>
        <h1 class="text-lg font-semibold text-foreground">{{ meta.title }}</h1>
        <p class="max-w-md text-[13px] text-muted">{{ meta.message }}</p>
        <p class="text-xs text-muted/70">{{ t('errors.reference', { id: reference }) }}</p>

        <div class="mt-2 flex items-center gap-2">
            <a
                href="/admin"
                class="inline-flex h-8 items-center rounded-md bg-primary px-3 text-[13px] font-medium text-primary-foreground hover:opacity-90"
            >
                {{ t('errors.go_dashboard') }}
            </a>
            <button
                type="button"
                class="inline-flex h-8 items-center rounded-md border border-border bg-surface px-3 text-[13px] font-medium text-foreground hover:bg-background"
                @click="$router?.back?.() ?? window.history.back()"
            >
                {{ t('errors.go_back') }}
            </button>
        </div>
    </div>
</template>
