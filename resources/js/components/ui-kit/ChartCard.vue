<script setup>
import { computed, ref } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    empty: { type: Boolean, default: false },
    exportName: { type: String, default: 'chart' },
    tableHeaders: { type: Array, default: () => [] },
    tableRows: { type: Array, default: () => [] },
});

const showTable = ref(false);

const slots = defineSlots();

const hasChart = computed(() => Boolean(slots.chart));

function downloadCsv() {
    const rows = [props.tableHeaders, ...props.tableRows]
        .map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(','))
        .join('\n');

    const blob = new Blob([rows], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `${props.exportName}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <section class="rounded-lg border border-border bg-surface p-3">
        <header class="mb-2 flex items-start justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
                <p v-if="subtitle" class="text-xs text-muted">{{ subtitle }}</p>
            </div>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-muted hover:text-foreground"
                    :aria-pressed="showTable"
                    :title="t('common.view_as_table')"
                    @click="showTable = !showTable"
                >
                    <AppIcon name="list-check" size="10" />
                    {{ t('common.table') }}
                </button>

                <button
                    type="button"
                    class="flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-muted hover:text-foreground"
                    :title="t('common.export_csv')"
                    @click="downloadCsv"
                >
                    <AppIcon name="file-export" size="10" />
                    CSV
                </button>
            </div>
        </header>

        <div v-if="loading" class="flex h-48 items-end gap-2 px-1 pb-1" aria-hidden="true">
            <div v-for="index in 8" :key="index" class="flex-1 animate-pulse rounded bg-border/60" :style="{ height: `${30 + ((index * 37) % 60)}%` }" />
        </div>

        <div v-else-if="error" class="flex h-48 flex-col items-center justify-center gap-2 text-center">
            <AppIcon name="triangle-exclamation" size="24" class="text-danger" />
            <p class="text-[13px] text-muted">{{ error }}</p>
            <button type="button" class="text-xs text-foreground underline" @click="$emit('retry')">{{ t('common.retry') }}</button>
        </div>

        <div v-else-if="empty" class="flex h-48 flex-col items-center justify-center gap-1 text-center">
            <AppIcon name="chart-line" size="24" class="text-muted" />
            <p class="text-[13px] text-muted">{{ t('common.no_data_period') }}</p>
        </div>

        <div v-else-if="showTable" class="thin-scrollbar max-h-56 overflow-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="border-b border-border text-left text-xs text-muted">
                        <th v-for="header in tableHeaders" :key="header" class="px-2 py-1 font-semibold">{{ header }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in tableRows" :key="index" class="border-b border-border/50">
                        <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="px-2 py-1 text-foreground" :class="cellIndex > 0 ? 'text-right tabular-nums' : ''">
                            {{ cell }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="relative h-48" :aria-label="title">
            <slot name="chart" />
        </div>
    </section>
</template>
