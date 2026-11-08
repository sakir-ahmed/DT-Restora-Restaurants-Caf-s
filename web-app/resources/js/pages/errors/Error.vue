<script setup>
import ErrorPage from '@/components/ui-kit/ErrorPage.vue';

defineProps({
    status: { type: [Number, String], default: 500 },
});
</script>

<template>
    <ErrorPage :status="status" />
</template>
