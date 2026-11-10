<script setup>
import AppIcon from '@/components/AppIcon.vue';

defineProps({
    icon: { type: String, default: 'circle-info' },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    actionLabel: { type: String, default: '' },
});

defineEmits(['action']);
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-background text-muted">
            <AppIcon :name="icon" size="24" />
        </span>

        <p class="text-sm font-semibold text-foreground">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-[13px] text-muted">{{ description }}</p>

        <button
            v-if="actionLabel"
            type="button"
            class="mt-1 inline-flex h-7 items-center gap-1 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
            @click="$emit('action')"
        >
            <AppIcon name="plus" size="12" />
            {{ actionLabel }}
        </button>
    </div>
</template>
