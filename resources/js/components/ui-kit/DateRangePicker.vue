<script setup>
import { computed } from 'vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    active: { type: String, default: '' },
});

const emit = defineEmits(['update:from', 'update:to', 'preset', 'change']);

function localIso(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

function daysAgo(count) {
    const date = new Date();
    date.setHours(12, 0, 0, 0);
    date.setDate(date.getDate() - count);

    return localIso(date);
}

function startOfMonth() {
    const date = new Date();

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-01`;
}

const presets = computed(() => [
    { key: 'today', label: t('common.period.today'), from: daysAgo(0), to: daysAgo(0) },
    { key: 'yesterday', label: t('common.period.yesterday'), from: daysAgo(1), to: daysAgo(1) },
    { key: '7days', label: t('common.period.7days'), from: daysAgo(6), to: daysAgo(0) },
    { key: 'month', label: t('common.period.this_month'), from: startOfMonth(), to: daysAgo(0) },
]);

function isActive(preset) {
    if (props.active) {
        return props.active === preset.key;
    }

    return props.from === preset.from && props.to === preset.to;
}

function publish(from, to, preset) {
    emit('update:from', from);
    emit('update:to', to);
    emit('preset', preset);
    emit('change', { from, to, preset });
}

function apply(preset) {
    publish(preset.from, preset.to, preset.key);
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
                :class="isActive(preset)
                    ? 'border-accent bg-accent/15 text-foreground'
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
                @input="publish($event.target.value, to, 'custom')"
            >
            <span class="text-muted" aria-hidden="true">&ndash;</span>
            <input
                type="date"
                :value="to"
                class="w-[118px] border-0 bg-transparent text-[13px] text-foreground outline-none"
                :aria-label="t('common.date_to')"
                @input="publish(from, $event.target.value, 'custom')"
            >
        </div>
    </div>
</template>
