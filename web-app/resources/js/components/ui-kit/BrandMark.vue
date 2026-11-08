<script setup>
/**
 * The brand mark: the Arman's Kitchen emblem in production, the DT Restora
 * tile in demo mode. Files come from public/images/{mode}/brand through the
 * shared `brand` prop.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    size: { type: [Number, String], default: 28 },
});

const page = usePage();
const brand = computed(() => page.props.brand ?? {});
</script>

<template>
    <img
        :src="brand.emblem"
        :alt="page.props.appName"
        :width="size"
        :height="size"
        :class="['shrink-0', brand.markShape]"
    >
</template>
