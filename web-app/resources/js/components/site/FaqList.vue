<script setup>
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    count: { type: Number, default: 6 },
});

const numbers = Array.from({ length: props.count }, (_, index) => index + 1);
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-3">
        <details v-for="number in numbers" :key="number" class="group rounded-xl border border-border bg-surface shadow-sm transition-shadow open:shadow-sm">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 px-5 py-3 text-left font-semibold [&::-webkit-details-marker]:hidden">
                <h3 class="font-sans text-base font-semibold">{{ t(`site.faq.q${number}`) }}</h3>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-accent/15 text-muted duration-300 group-open:rotate-180">
                    <AppIcon name="chevron-down" size="12" />
                </span>
            </summary>
            <p class="px-5 pb-5 text-sm leading-relaxed text-muted sm:text-base">{{ t(`site.faq.a${number}`) }}</p>
        </details>
    </div>
</template>
