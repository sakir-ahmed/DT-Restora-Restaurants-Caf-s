<script setup>
defineProps({
    as: { type: String, default: 'div' },
});
</script>

<template>
    <component :is="as" class="transition-shadow duration-200 hover:shadow-md">
        <slot />
    </component>
</template>
