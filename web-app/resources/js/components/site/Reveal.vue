<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { revealWhenVisible } from '@/lib/reveal';

const props = defineProps({
    as: { type: String, default: 'div' },
    delay: { type: Number, default: 0 },
});

const el = ref(null);
const state = ref('idle');
let stop = null;

/**
 * Fade and rise into view on scroll. Content that is already on screen
 * (and everything without JavaScript or with reduced motion) is never hidden.
 */
onMounted(() => {
    const node = el.value;

    if (! node || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    if (node.getBoundingClientRect().top < window.innerHeight * 0.92) {
        return;
    }

    state.value = 'pending';
    stop = revealWhenVisible(node, () => {
        state.value = 'in';
    });
});

onBeforeUnmount(() => stop?.());
</script>

<template>
    <component
        :is="props.as"
        ref="el"
        :class="{ 'reveal-pending': state === 'pending', 'reveal-in': state === 'in' }"
        :style="{ '--reveal-delay': `${props.delay}ms` }"
    >
        <slot />
    </component>
</template>
