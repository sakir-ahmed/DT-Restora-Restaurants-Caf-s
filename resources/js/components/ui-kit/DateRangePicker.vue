<script setup>
import { computed } from 'vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
});

const emit = defineEmits(['update:from', 'update:to', 'preset']);

function iso(daysAgo) {
    const date = new Date();
    date.setDate(date.getDate() - daysAgo);

    return date.toISOString().slice(0, 10);
}

const presets = computed(() => [
    { key: 'today', label: t('common.period.today'), from: iso(0), to: iso(0) },
    { key: 'yesterday', label: t('common.period.yesterday'), from: iso(1), to: iso(1) },
    { key: '7days', label: t('common.period.7days'), from: iso(6), to: iso(0) },
    { key: 'month', label: t('common.period.this_month'), from: iso(new Date().getDate() - 1), to: iso(0) },
]);

function apply(preset) {
    emit('update:from', preset.from);
    emit('update:to', preset.to);
    emit('preset', preset.key);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <div
            v-for="preset in presets"
            :key="preset.key"
            class="flex"
        >
            <button
                type="button"
                class="h-7 rounded-md border px-2 text-xs font-medium"
                :class="from === preset.from && to === preset.to
                    ? 'border-accent bg-accent/15 text-accent-foreground'
                    : 'border-border bg-surface text-muted hover:text-foreground'"
                @click="apply(preset)"
            >
                {{ preset.label }}
            </button>
        </div>

        <div class="flex h-7 items-center gap-1 rounded-md border border-border bg-surface px-2">
            <input
                type="date"
                :value="from"
                class="w-[118px] border-0 bg-transparent text-[13px] text-foreground outline-none"
                :aria-label="t('common.date_from')"
                @input="emit('update:from', $event.target.value)"
            >
            <span class="text-muted" aria-hidden="true">&ndash;</span>
            <input
                type="date"
                :value="to"
                class="w-[118px] border-0 bg-transparent text-[13px] text-foreground outline-none"
                :aria-label="t('common.date_to')"
                @input="emit('update:to', $event.target.value)"
            >
        </div>
    </div>
</template>
