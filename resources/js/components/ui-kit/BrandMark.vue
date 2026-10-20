<script setup>
/**
 * The DT Restora product mark (Datatech brand kit): blue tile, serving cloche with three rising bars.
 */
defineProps({
    size: { type: [Number, String], default: 28 },
});
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 120 120" role="img" aria-label="DT Restora" class="shrink-0">
        <defs>
            <linearGradient id="dt-restora-mark-gradient" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#1A78E6" />
                <stop offset="1" stop-color="#0B52B0" />
            </linearGradient>
        </defs>
        <rect width="120" height="120" rx="28" fill="url(#dt-restora-mark-gradient)" />
        <g transform="translate(18 18) scale(0.84)">
            <path d="M20 62a30 30 0 0 1 60 0z" fill="#ffffff" />
            <rect x="16" y="64" width="68" height="8" rx="4" fill="#ffffff" />
            <circle cx="50" cy="27" r="5.5" fill="#7dd3fc" />
            <rect x="36" y="52" width="7" height="10" rx="3.5" fill="#0b63ce" />
            <rect x="47" y="46" width="7" height="16" rx="3.5" fill="#0b63ce" />
            <rect x="58" y="40" width="7" height="22" rx="3.5" fill="#0b63ce" />
        </g>
    </svg>
</template>
