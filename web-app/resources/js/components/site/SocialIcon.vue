<script setup>
/**
 * Social network glyphs drawn inline (24px grid), so the site needs no brand
 * icon package. Line icons use the current text colour.
 */
defineProps({
    network: { type: String, required: true },
    size: { type: [Number, String], default: 18 },
});
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path v-if="network === 'facebook'" fill="currentColor" d="M13.6 21v-7.6h2.6l.5-3.1h-3.1V8.4c0-.9.4-1.6 1.7-1.6h1.5V4.1c-.3 0-1.2-.2-2.3-.2-2.5 0-4.1 1.5-4.1 4.2v2.2H7.7v3.1h2.7V21z" />
        <g v-else-if="network === 'instagram'" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3.5" y="3.5" width="17" height="17" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <circle cx="17.3" cy="6.7" r=".6" fill="currentColor" />
        </g>
        <path v-else-if="network === 'youtube'" fill="currentColor" fill-rule="evenodd" d="M8.7 5h6.6c2.8 0 5 .2 5.7 2.7.4 1.4.4 2.9.4 4.3s0 2.9-.4 4.3c-.7 2.5-2.9 2.7-5.7 2.7H8.7c-2.8 0-5-.2-5.7-2.7C2.6 14.9 2.6 13.4 2.6 12s0-2.9.4-4.3C3.7 5.2 5.9 5 8.7 5zM10.2 8.9v6.2L15.6 12z" />
        <g v-else-if="network === 'tiktok'" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.2 3.5v11.2a3.8 3.8 0 1 1-3.8-3.8" />
            <path d="M14.2 3.5c.3 2.5 1.9 4.2 4.6 4.5" />
        </g>
        <g v-else-if="network === 'whatsapp'" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3.8 20.2l1.2-4.3A8.5 8.5 0 1 1 8.4 19z" />
            <path d="M9.3 8.4c-.4.9-.2 2.2.9 3.7 1.2 1.6 2.6 2.5 3.9 2.6.7 0 1.3-.5 1.5-1.1l-2-1-.8.8c-.8-.3-1.8-1.1-2.3-2l.7-.9z" fill="currentColor" stroke-width="0" />
        </g>
    </svg>
</template>
