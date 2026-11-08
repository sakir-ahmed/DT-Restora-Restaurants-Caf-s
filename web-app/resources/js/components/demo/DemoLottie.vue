<script setup>
import { onMounted, shallowRef } from 'vue';

const props = defineProps({
    name: { type: String, required: true }, // welcome | journey | success
    size: { type: Number, default: 120 },
    loop: { type: Boolean, default: true },
});

const player = shallowRef(null);
const reduceMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

// The player and its wasm are fetched only after the page is interactive, and only in demo mode.
onMounted(async () => {
    if (reduceMotion) {
        return;
    }

    try {
        const { DotLottieVue, setWasmUrl } = await import('@lottiefiles/dotlottie-vue');

        // Serve the renderer from our own origin instead of a public CDN.
        setWasmUrl(new URL('../../../../node_modules/@lottiefiles/dotlottie-web/dist/dotlottie-player.wasm', import.meta.url).href);
        player.value = DotLottieVue;
    } catch {
        // Animation is decoration only.
    }
});
</script>

<template>
    <div class="shrink-0" :style="{ width: `${size}px`, height: `${size}px` }" aria-hidden="true">
        <component
            :is="player"
            v-if="player"
            :src="`/demo/lottie/${name}.json`"
            :loop="loop"
            autoplay
            :style="{ width: `${size}px`, height: `${size}px` }"
        />
    </div>
</template>
