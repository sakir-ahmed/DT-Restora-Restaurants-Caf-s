<script setup>
/**
 * Empty-image placeholder for a dish without a photo: the restaurant emblem,
 * faded, on a soft neutral tile. It carries no food illustration, so it never
 * suggests a dish that is not on the plate.
 */
import { usePage } from '@inertiajs/vue3';

const page = usePage();

defineProps({
    kind: { type: String, default: '' },
    seed: { type: Number, default: 0 },
    steam: { type: Boolean, default: true },
});
</script>

<template>
    <div class="flex h-full w-full items-center justify-center rounded-xl bg-gradient-to-br from-border/40 to-border/10" role="img" aria-hidden="true">
        <img
            :src="page.props.brand?.emblem"
            alt=""
            width="96"
            height="96"
            loading="lazy"
            decoding="async"
            class="aspect-square h-auto max-h-[45%] w-auto max-w-[45%] rounded-full opacity-35 grayscale"
        >
    </div>
</template>
