<script setup>
/**
 * Two-tone wordmark text as in the DT Restora logo: "DT" in the foreground colour, the rest in brand blue.
 */
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
});

const parts = computed(() => {
    const [first, ...rest] = props.name.split(' ');

    return { first, rest: rest.join(' ') };
});
</script>

<template>
    <span>{{ parts.first }}<span v-if="parts.rest" class="ms-[0.28em] text-primary">{{ parts.rest }}</span></span>
</template>
