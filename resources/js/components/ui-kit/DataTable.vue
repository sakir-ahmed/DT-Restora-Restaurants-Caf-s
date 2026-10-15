<script setup>
import { computed, ref, watch } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';
import EmptyState from './EmptyState.vue';
import ErrorState from './ErrorState.vue';
import SkeletonRows from './SkeletonRows.vue';

const props = defineProps({
    columns: { type: Array, required: true }, // {key,label,align,type,sortable,width,format}
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    searchable: { type: Boolean, default: false },
    searchPlaceholder: { type: String, default: '' },
    rowKey: { type: String, default: 'id' },
    perPage: { type: Number, default: 25 },
    emptyTitle: { type: String, default: '' },
    emptyDescription: { type: String, default: '' },
    emptyAction: { type: String, default: '' },
    totalsRow: { type: Object, default: null }, // {columnKey: display}
    clickable: { type: Boolean, default: false },
});

const emit = defineEmits(['row-click', 'empty-action']);

const search = ref('');
const sortKey = ref(null);
const sortDir = ref('asc');
const page = ref(1);

watch([search, () => props.rows.length], () => {
    page.value = 1;
});

const filtered = computed(() => {
    if (! props.searchable || ! search.value.trim()) {
        return props.rows;
    }

    const needle = search.value.trim().toLowerCase();

    return props.rows.filter((row) => props.columns.some((column) => {
        const value = row[column.key];

        return value !== null && value !== undefined && String(value).toLowerCase().includes(needle);
    }));
});

const sorted = computed(() => {
    if (! sortKey.value) {
        return filtered.value;
    }

    const key = sortKey.value;
    const direction = sortDir.value === 'asc' ? 1 : -1;

    return [...filtered.value].sort((a, b) => {
        const av = a[key];
        const bv = b[key];

        if (typeof av === 'number' && typeof bv === 'number') {
            return (av - bv) * direction;
        }

        return String(av ?? '').localeCompare(String(bv ?? '')) * direction;
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(sorted.value.length / props.perPage)));

const paged = computed(() => sorted.value.slice((page.value - 1) * props.perPage, page.value * props.perPage));

const rangeStart = computed(() => (page.value - 1) * props.perPage + 1);

const rangeEnd = computed(() => Math.min(page.value * props.perPage, sorted.value.length));

function toggleSort(column) {
    if (! column.sortable) {
        return;
    }

    if (sortKey.value === column.key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = column.key;
        sortDir.value = 'asc';
    }
}

const alignClass = (column) => ({
    left: 'text-left',
    right: 'text-right',
    center: 'text-center',
})[column.align ?? (['money', 'number', 'percent'].includes(column.type) ? 'right' : 'left')];

function cellDisplay(row, column) {
    const value = row[column.key];

    if (column.type === 'money') {
        return value ?? '\u2013';
    }

    if ((value === null || value === undefined || value === '') && column.type !== 'component') {
        return '\u2013';
    }

    return value;
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-border bg-surface">
        <div v-if="searchable" class="flex items-center gap-2 border-b border-border p-2">
            <div class="flex h-7 w-64 items-center gap-1.5 rounded-md border border-border bg-background px-2">
                <AppIcon name="magnifying-glass" size="12" class="text-muted" />
                <input
                    v-model="search"
                    type="search"
                    :placeholder="searchPlaceholder || t('common.search')"
                    class="w-full border-0 bg-transparent text-[13px] text-foreground outline-none placeholder:text-muted/60"
                >
            </div>

            <div class="ml-auto flex items-center gap-2 text-xs text-muted">
                <span>{{ t('common.showing_range', { from: rangeStart, to: rangeEnd, total: sorted.length }) }}</span>
            </div>
        </div>

        <div class="thin-scrollbar overflow-x-auto">
            <table class="w-full min-w-max border-collapse text-[13px]">
                <thead>
                    <tr class="sticky top-0 z-10 border-b border-border bg-surface">
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="whitespace-nowrap px-3 py-2 text-xs font-semibold text-muted"
                            :class="[alignClass(column), column.nowrap !== false && 'whitespace-nowrap']"
                            :style="column.width ? { width: column.width } : { minWidth: column.minWidth }"
                            :aria-sort="sortKey === column.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : undefined"
                        >
                            <button
                                v-if="column.sortable"
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="toggleSort(column)"
                            >
                                <AppIcon
                                    v-if="sortKey === column.key"
                                    :name="sortDir === 'asc' ? 'arrow-up' : 'arrow-down'"
                                    size="10"
                                />
                                {{ column.label }}
                            </button>
                            <template v-else>{{ column.label }}</template>
                        </th>
                    </tr>
                </thead>

                <tbody v-if="loading">
                    <tr v-for="index in 5" :key="index" class="border-b border-border/60">
                        <td v-for="column in columns" :key="column.key" class="px-3 py-1.5">
                            <div class="h-4 animate-pulse rounded bg-border/60" />
                        </td>
                    </tr>
                </tbody>

                <tbody v-else-if="error">
                    <tr>
                        <td :colspan="columns.length" class="p-4">
                            <ErrorState :message="error" @retry="$emit('update')" />
                        </td>
                    </tr>
                </tbody>

                <tbody v-else-if="paged.length === 0">
                    <tr>
                        <td :colspan="columns.length" class="p-4">
                            <EmptyState
                                :icon="'magnifying-glass'"
                                :title="emptyTitle || t('common.no_results')"
                                :description="search ? t('common.no_results_filters') : (emptyDescription || t('common.empty_default'))"
                                :action-label="! search && emptyAction ? emptyAction : ''"
                                @action="emit('empty-action')"
                            />
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr
                        v-for="row in paged"
                        :key="row[rowKey]"
                        class="border-b border-border/60 hover:bg-background/60"
                        :class="clickable ? 'cursor-pointer' : ''"
                        @click="clickable && emit('row-click', row)"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="max-w-0 px-3 py-1.5 align-middle text-foreground"
                            :class="[alignClass(column), column.type === 'component' ? 'p-0' : '']"
                        >
                            <slot
                                :name="`cell(${column.key})`"
                                :row="row"
                                :value="row[column.key]"
                            >
                                <span
                                    v-if="column.type === 'money'"
                                    class="tabular-nums"
                                >{{ cellDisplay(row, column) }}</span>
                                <span
                                    v-else-if="column.type === 'number' || column.type === 'percent'"
                                    class="tabular-nums"
                                >{{ cellDisplay(row, column) }}</span>
                                <span v-else-if="column.truncate" class="block truncate" :title="String(row[column.key] ?? '')">{{ cellDisplay(row, column) }}</span>
                                <span v-else>{{ cellDisplay(row, column) }}</span>
                            </slot>
                        </td>
                    </tr>
                </tbody>

                <tfoot v-if="totalsRow && ! loading && paged.length > 0">
                    <tr class="border-t-2 border-border font-semibold">
                        <td class="px-3 py-2 text-left text-foreground">
                            {{ totalsRow.label ?? t('common.total') }}
                        </td>
                        <td
                            v-for="column in columns.slice(1)"
                            :key="column.key"
                            class="px-3 py-2 text-foreground"
                            :class="alignClass(column)"
                        >
                            <span class="tabular-nums">{{ totalsRow[column.key] ?? '' }}</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div v-if="!loading && !error && sorted.length > perPage" class="flex items-center justify-between border-t border-border px-3 py-2">
            <span class="text-xs text-muted">{{ t('common.showing_range', { from: rangeStart, to: rangeEnd, total: sorted.length }) }}</span>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="flex h-6 w-6 items-center justify-center rounded border border-border text-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="page <= 1"
                    :aria-label="t('common.previous_page')"
                    @click="page -= 1"
                >
                    <AppIcon name="chevron-left" size="10" />
                </button>

                <span class="px-2 text-xs tabular-nums text-foreground">{{ page }} / {{ totalPages }}</span>

                <button
                    type="button"
                    class="flex h-6 w-6 items-center justify-center rounded border border-border text-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="page >= totalPages"
                    :aria-label="t('common.next_page')"
                    @click="page += 1"
                >
                    <AppIcon name="chevron-right" size="10" />
                </button>
            </div>
        </div>
    </div>

    <SkeletonRows v-if="false" />
</template>
