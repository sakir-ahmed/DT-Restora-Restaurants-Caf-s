<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <header class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <nav v-if="breadcrumbs.length" class="mb-1 flex items-center gap-1 text-xs text-muted" aria-label="breadcrumb">
                <template v-for="(crumb, index) in breadcrumbs" :key="index">
                    <span v-if="index > 0" class="text-muted/60" aria-hidden="true">/</span>
                    <span :aria-current="index === breadcrumbs.length - 1 ? 'page' : undefined">{{ crumb }}</span>
                </template>
            </nav>

            <h1 class="text-lg font-semibold text-foreground">{{ title }}</h1>
            <p v-if="description" class="mt-0.5 text-[13px] text-muted">{{ description }}</p>

            <div v-if="$slots.default" class="mt-1">
                <slot />
            </div>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
