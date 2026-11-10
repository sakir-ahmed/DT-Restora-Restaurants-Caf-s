<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateRangePicker from '@/components/ui-kit/DateRangePicker.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

const props = defineProps({
    type: { type: String, default: 'sales' },
    from: { type: String, required: true },
    to: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    summaryCards: { type: Array, default: () => [] },
    branches: { type: Array, required: true },
    branchId: { type: Number, default: null },
});

const applying = ref(false);

const reportTypes = [
    { key: 'sales', label: t('reports.types.sales') },
    { key: 'item-sales', label: t('reports.types.item_sales') },
    { key: 'wastage', label: t('reports.types.wastage') },
    { key: 'stock-valuation', label: t('reports.types.stock_valuation') },
    { key: 'food-cost', label: t('reports.types.food_cost') },
    { key: 'branch-pl', label: t('reports.types.branch_pl') },
    { key: 'channel', label: t('reports.types.channel') },
    { key: 'payment-method', label: t('reports.types.payment_method') },
    { key: 'cashier', label: t('reports.types.cashier') },
    { key: 'discount-void', label: t('reports.types.discount_void') },
    { key: 'delivery', label: t('reports.types.delivery') },
    { key: 'loyalty', label: t('reports.types.loyalty') },
];

const tableColumns = computed(() => props.columns.map((column) => ({
    key: column.key,
    label: column.label,
    type: ['money', 'number'].includes(column.type) ? column.type : 'text',
    sortable: true,
    minWidth: column.type === 'money' ? '96px' : undefined,
})));

function apply(filters = {}) {
    applying.value = true;

    router.get(route('admin.reports.index'), {
        type: props.type,
        from: props.from,
        to: props.to,
        branch: props.branchId ?? '',
        ...filters,
    }, {
        preserveState: true,
        onFinish: () => (applying.value = false),
    });
}

function exportReport(format) {
    const query = new URLSearchParams({
        type: props.type,
        from: props.from,
        to: props.to,
        branch: props.branchId ?? '',
        format,
    });

    window.open(route('admin.reports.export') + '?' + query.toString(), '_blank');

    notify.info(t('reports.export_started'));
}
</script>

<template>
    <div>
        <PageHeader :title="title" :description="description">
            <template #actions>
                <div class="flex items-center gap-1.5">
                    <button type="button" class="inline-flex h-7 items-center gap-1 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="exportReport('pdf')">
                        <AppIcon name="file-export" size="12" />
                        PDF
                    </button>
                    <button type="button" class="inline-flex h-7 items-center gap-1 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="exportReport('xlsx')">
                        <AppIcon name="file-export" size="12" />
                        Excel
                    </button>
                    <button type="button" class="inline-flex h-7 items-center gap-1 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="exportReport('csv')">
                        <AppIcon name="download" size="12" />
                        CSV
                    </button>
                </div>
            </template>
        </PageHeader>

        <!-- Filter bar -->
        <div class="sticky top-12 z-20 mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-border bg-surface p-2">
            <select
                :value="type"
                class="h-7 rounded-md border border-border bg-background px-2 text-[13px] text-foreground"
                :aria-label="t('reports.type_label')"
                @change="apply({ type: $event.target.value })"
            >
                <option v-for="reportType in reportTypes" :key="reportType.key" :value="reportType.key">{{ reportType.label }}</option>
            </select>

            <DateRangePicker :from="from" :to="to" @change="({ from, to }) => apply({ from, to })" />

            <select
                v-if="branchId !== undefined"
                :value="branchId ?? ''"
                class="h-7 rounded-md border border-border bg-background px-2 text-[13px] text-foreground"
                :aria-label="t('nav.branch')"
                @change="apply({ branch: $event.target.value || '' })"
            >
                <option value="">{{ t('reports.all_branches') }}</option>
                <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.code }} - {{ branch.name }}</option>
            </select>

            <div class="ml-auto flex items-center gap-2">
                <span v-if="applying" class="flex items-center gap-1 text-xs text-muted">
                    <AppIcon name="spinner" spin size="12" />
                    {{ t('common.working') }}
                </span>
            </div>
        </div>

        <!-- Summary cards -->
        <div v-if="summaryCards.length" class="mb-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="card in summaryCards" :key="card.label" class="rounded-lg border border-border bg-surface p-3">
                <p class="text-xs font-medium text-muted">{{ card.label }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-foreground">{{ card.value }}</p>
            </div>
        </div>

        <!-- Data table with pinned totals -->
        <DataTable :columns="tableColumns" :rows="rows" searchable :per-page="50" :empty-title="t('common.no_results')">
            <template #cell(total)="{ value }">
                <strong class="tabular-nums">{{ value }}</strong>
            </template>
        </DataTable>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
