<script setup>
defineProps({
    rows: { type: Number, default: 5 },
});
</script>

<template>
    <div class="space-y-2" aria-hidden="true">
        <div
            v-for="index in rows"
            :key="index"
            class="h-8 animate-pulse rounded bg-border/60"
            :style="{ opacity: 1 - index * 0.12 }"
        />
    </div>
</template>
