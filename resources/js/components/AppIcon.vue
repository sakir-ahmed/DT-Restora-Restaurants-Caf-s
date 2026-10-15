<script setup>
import { computed } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { getIcon } from './icons';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 14 },
    spin: { type: Boolean, default: false },
});

const definition = computed(() => getIcon(props.name));

const sizeClass = computed(() => {
    const map = {
        xs: 'text-[12px]',
        sm: 'text-[12px]',
        md: 'text-[14px]',
        lg: 'text-[16px]',
        xl: 'text-[20px]',
        '2xl': 'text-[24px]',
    };

    return map[props.size] ?? `text-[${props.size}px]`;
});
</script>

<template>
    <FontAwesomeIcon
        :icon="definition"
        :class="[sizeClass, { 'fa-spin': spin }]"
        :spin="spin"
        aria-hidden="true"
    />
</template>
