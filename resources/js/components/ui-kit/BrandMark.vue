<script setup>
/**
 * The Arman's Kitchen emblem (gold AK monogram on black, est. 2013).
 */
const emblem = '/images/brand/emblem-192.png';

defineProps({
    size: { type: [Number, String], default: 28 },
});
</script>

<template>
    <img
        :src="emblem"
        alt="Arman's Kitchen"
        :width="size"
        :height="size"
        class="shrink-0 rounded-full"
    >
</template>
