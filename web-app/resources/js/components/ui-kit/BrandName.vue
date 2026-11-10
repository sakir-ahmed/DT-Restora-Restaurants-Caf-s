<script setup>
/**
 * Two-tone wordmark text: the first word in the foreground colour, the rest in brand gold.
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
    <span>{{ parts.first }}<span v-if="parts.rest" class="ms-[0.28em] text-accent">{{ parts.rest }}</span></span>
</template>
