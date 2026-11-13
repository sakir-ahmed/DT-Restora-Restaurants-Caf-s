<script setup>
/**
 * The brand mark: the customer emblem when the install has one, otherwise the
 * DT Restora mark. The file comes from the shared `brand` prop (see App\Support\Brand).
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
