<script setup>
import { t } from '@/lib/i18n';
import { toBnDigits } from '@/lib/format';
import { currentLocale } from '@/lib/i18n';

defineProps({
    hours: { type: Array, default: () => [] },
});

function time(value) {
    return currentLocale() === 'bn' ? toBnDigits(value) : value;
}
</script>

<template>
    <ul v-if="hours.length" class="space-y-2 text-sm">
        <li v-for="(entry, index) in hours" :key="index" class="flex flex-wrap items-center justify-between gap-2">
            <span class="flex flex-wrap gap-1">
                <span v-for="day in entry.days" :key="day" class="rounded-md bg-accent/15 px-1.5 py-0.5 text-xs font-semibold">{{ t(`site.day_${day}`) }}</span>
            </span>
            <span class="tabular-nums text-muted">{{ time(entry.opens) }} - {{ time(entry.closes) }}</span>
        </li>
    </ul>
    <p v-else class="text-sm text-muted">{{ t('site.location_hours_unknown') }}</p>
</template>
