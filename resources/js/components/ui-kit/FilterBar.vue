<script setup>
defineProps({
    sticky: { type: Boolean, default: true },
});
</script>

<template>
    <div
        class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-border bg-surface p-2"
        :class="sticky ? 'sticky top-12 z-20' : ''"
    >
        <slot />

        <div v-if="$slots.actions" class="ml-auto flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
