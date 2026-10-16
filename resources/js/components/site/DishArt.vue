<script setup>
import { computed, useId } from 'vue';

/**
 * Illustrated placeholder shown when a dish has no photo yet. Keeps cards
 * rich and layout-stable until the owner uploads real photography.
 */
const props = defineProps({
    kind: { type: String, default: 'bowl' },
    seed: { type: Number, default: 0 },
    steam: { type: Boolean, default: true },
});

const uid = useId();
const hue = computed(() => 18 + ((Number(props.seed) * 37) % 36));
const sauce = computed(() => `oklch(0.62 0.17 ${hue.value})`);
const sauceDark = computed(() => `oklch(0.46 0.15 ${hue.value})`);
const berry = computed(() => `oklch(0.78 0.11 ${(hue.value + 330) % 360})`);
</script>

<template>
    <svg viewBox="0 0 200 200" class="h-full w-full" role="img" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient :id="`${uid}-plate`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#ffffff" />
                <stop offset="1" stop-color="#e9dfd0" />
            </linearGradient>
            <linearGradient :id="`${uid}-rice`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#fff8e7" />
                <stop offset="1" stop-color="#ecd9b0" />
            </linearGradient>
            <linearGradient :id="`${uid}-sauce`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" :stop-color="sauce" />
                <stop offset="1" :stop-color="sauceDark" />
            </linearGradient>
            <linearGradient :id="`${uid}-bun`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#f6b95a" />
                <stop offset="1" stop-color="#d98a2b" />
            </linearGradient>
            <linearGradient :id="`${uid}-coffee`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#8a5a3a" />
                <stop offset="1" stop-color="#4a2a18" />
            </linearGradient>
            <radialGradient :id="`${uid}-scoop`" cx="0.35" cy="0.3" r="0.8">
                <stop offset="0" stop-color="#ffffff" stop-opacity="0.9" />
                <stop offset="0.45" :stop-color="berry" />
                <stop offset="1" :stop-color="berry" stop-opacity="0.85" />
            </radialGradient>
        </defs>

        <ellipse cx="100" cy="170" rx="72" ry="10" fill="rgb(0 0 0 / 0.16)" />

        <!-- Curry and rice bowl -->
        <g v-if="kind === 'bowl'">
            <ellipse cx="100" cy="124" rx="88" ry="40" :fill="`url(#${uid}-plate)`" />
            <ellipse cx="100" cy="122" rx="70" ry="30" fill="#f3ead9" />
            <ellipse cx="100" cy="116" rx="54" ry="24" :fill="`url(#${uid}-rice)`" />
            <ellipse cx="108" cy="108" rx="40" ry="17" :fill="`url(#${uid}-sauce)`" />
            <ellipse cx="94" cy="104" rx="9" ry="6" fill="#7b4a2a" />
            <ellipse cx="116" cy="108" rx="10" ry="6" fill="#8a5630" />
            <ellipse cx="104" cy="113" rx="8" ry="5" fill="#6f4125" />
            <ellipse cx="122" cy="101" rx="6" ry="3.5" fill="#5da05a" />
            <ellipse cx="88" cy="112" rx="5" ry="3" fill="#5da05a" />
            <path d="M72 120c10 6 18 6 28 4" stroke="#c0392b" stroke-width="3" stroke-linecap="round" fill="none" />
        </g>

        <!-- Coffee cup -->
        <g v-else-if="kind === 'cup'">
            <ellipse cx="100" cy="158" rx="76" ry="14" :fill="`url(#${uid}-plate)`" />
            <path d="M142 104c28 0 28 36-4 36" stroke="#efe6d6" stroke-width="9" stroke-linecap="round" fill="none" />
            <path d="M58 88h84c0 44-14 68-42 68s-42-24-42-68z" :fill="`url(#${uid}-plate)`" />
            <ellipse cx="100" cy="88" rx="42" ry="10" fill="#f3ead9" />
            <ellipse cx="100" cy="88" rx="37" ry="8" :fill="`url(#${uid}-coffee)`" />
            <path d="M100 92c-9-3-12-8-6-10 3-1 6 1 6 3 0-2 3-4 6-3 6 2 3 7-6 10z" fill="#f3d9b6" opacity="0.9" />
        </g>

        <!-- Burger -->
        <g v-else-if="kind === 'burger'">
            <path d="M48 128c0 14 22 22 52 22s52-8 52-22z" :fill="`url(#${uid}-bun)`" />
            <path d="M44 114c8 8 20 6 28 12 8-6 18 0 28-2 10 2 20-6 28 2 8-4 16-2 28-12v10c-12 10-24 8-28 8-8 4-18-2-28 2-10-4-20 2-28-2-8 2-20 2-28-8z" fill="#6bb04e" />
            <ellipse cx="100" cy="108" rx="56" ry="11" fill="#6b3b22" />
            <path d="M46 98h108l-10 12H56z" fill="#f2b632" />
            <path d="M44 90c0-34 24-52 56-52s56 18 56 52z" :fill="`url(#${uid}-bun)`" />
            <ellipse cx="84" cy="62" rx="3.5" ry="2" fill="#fff4d6" transform="rotate(-20 84 62)" />
            <ellipse cx="104" cy="54" rx="3.5" ry="2" fill="#fff4d6" />
            <ellipse cx="122" cy="66" rx="3.5" ry="2" fill="#fff4d6" transform="rotate(20 122 66)" />
            <ellipse cx="98" cy="74" rx="3.5" ry="2" fill="#fff4d6" transform="rotate(-10 98 74)" />
        </g>

        <!-- Ice cream -->
        <g v-else>
            <path d="M70 100h60l-30 70z" fill="#e0a85a" />
            <path d="M78 112l22 44M92 106l8 20M108 106l-8 20M122 112l-22 44M70 100l30 36M130 100l-30 36" stroke="#c88a3a" stroke-width="2" fill="none" />
            <circle cx="100" cy="88" r="34" :fill="`url(#${uid}-scoop)`" />
            <circle cx="100" cy="52" r="28" fill="#fff2dc" />
            <circle cx="92" cy="44" r="8" fill="#fff" opacity="0.7" />
            <circle cx="100" cy="24" r="8" fill="#c0392b" />
            <path d="M100 18c4-8 10-10 14-8" stroke="#3d7a3a" stroke-width="2" fill="none" />
        </g>

        <g v-if="steam && kind !== 'dessert'" stroke="rgb(255 255 255 / 0.85)" stroke-width="4" stroke-linecap="round" fill="none">
            <path class="" d="M84 84c-6-8 6-12 0-20" style="transform-origin: 84px 84px" />
            <path class="" d="M102 80c-6-8 6-12 0-20" style="animation-delay: 1s; transform-origin: 102px 80px" />
            <path class="" d="M120 84c-6-8 6-12 0-20" style="animation-delay: 2s; transform-origin: 120px 84px" />
        </g>
    </svg>
</template>
