<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { toBnDigits } from '@/lib/format';
import { currentLocale } from '@/lib/i18n';

const props = defineProps({
    value: { type: Number, required: true },
    duration: { type: Number, default: 1400 },
});

const el = ref(null);
const shown = ref(props.value);
let observer = null;
let frame = null;

function format(number) {
    const text = String(Math.round(number));

    return currentLocale() === 'bn' ? toBnDigits(text) : text;
}

function run() {
    const start = performance.now();

    const step = (now) => {
        const progress = Math.min((now - start) / props.duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);

        shown.value = props.value * eased;

        if (progress < 1) {
            frame = requestAnimationFrame(step);
        }
    };

    frame = requestAnimationFrame(step);
}

onMounted(() => {
    if (! el.value || ! ('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            shown.value = 0;
            run();
            observer?.disconnect();
        }
    }, { threshold: 0.4 });

    observer.observe(el.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();

    if (frame) {
        cancelAnimationFrame(frame);
    }
});
</script>

<template>
    <span ref="el" class="tabular-nums">{{ format(shown) }}</span>
</template>
