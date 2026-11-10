<script setup>
defineProps({
    eyebrow: { type: String, default: null },
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    center: { type: Boolean, default: false },
    light: { type: Boolean, default: false },
});
</script>

<template>
    <div :class="center ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl'">
        <p v-if="eyebrow" class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ eyebrow }}</p>
        <h2 class="text-2xl font-bold leading-tight sm:text-3xl" :class="light ? 'text-white' : ''">{{ title }}</h2>
        <p v-if="subtitle" class="mt-3 text-base leading-relaxed" :class="light ? 'text-white/75' : 'text-muted'">{{ subtitle }}</p>
    </div>
</template>
