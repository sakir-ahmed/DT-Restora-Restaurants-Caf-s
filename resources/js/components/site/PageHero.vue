<script setup>
import Breadcrumbs from './Breadcrumbs.vue';

defineProps({
    eyebrow: { type: String, default: null },
    title: { type: String, required: true },
    text: { type: String, default: null },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="border-b border-border bg-surface">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:py-10">
            <Breadcrumbs v-if="breadcrumbs.length" :items="breadcrumbs" class="mb-4" />

            <p v-if="eyebrow" class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted">{{ eyebrow }}</p>
            <h1 class="max-w-3xl text-3xl font-bold leading-tight">{{ title }}</h1>
            <p v-if="text" class="mt-3 max-w-2xl text-base leading-relaxed text-muted">{{ text }}</p>
            <slot />
        </div>
    </section>
</template>
