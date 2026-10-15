<script setup>
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import AppIcon from '@/components/AppIcon.vue';
import ChartCard from '@/components/ui-kit/ChartCard.vue';
import DateRangePicker from '@/components/ui-kit/DateRangePicker.vue';
import KpiCard from '@/components/ui-kit/KpiCard.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import MoneyText from '@/components/ui-kit/MoneyText.vue';
import { t } from '@/lib/i18n';
import { formatMoney, formatNumber } from '@/lib/format';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Tooltip, Legend, Filler);

const props = defineProps({
    period: { type: String, default: 'today' },
    from: { type: String, required: true },
    to: { type: String, required: true },
    summary: { type: Object, required: true },
    previous: { type: Object, required: true },
    salesByDay: { type: Array, default: () => [] },
    salesByChannel: { type: Array, default: () => [] },
    topItems: { type: Array, default: () => [] },
    branchComparison: { type: Array, default: () => [] },
    alerts: { type: Object, required: true },
    setup: { type: Object, required: true },
});

function change(key) {
    const current = Number(props.summary[key] ?? 0);
    const previous = Number(props.previous[key] ?? 0);

    if (previous <= 0) {
        return null;
    }

    return Number((((current - previous) / previous) * 100).toFixed(1));
}

const foodCostPercent = computed(() => {
    const net = Number(props.summary.net_sales);

    return net > 0 ? ((Number(props.summary.food_cost) / net) * 100).toFixed(1) : '0.0';
});

function setPeriod(from, to, preset) {
    router.get(route('admin.dashboard'), { period: preset, from, to }, { preserveState: true });
}

function applyCustom(from, to) {
    router.get(route('admin.dashboard'), { period: 'custom', from, to }, { preserveState: true });
}

const chartColors = {
    primary: 'rgba(15, 23, 42, 0.85)',
    primaryFill: 'rgba(15, 23, 42, 0.08)',
    accent: 'rgba(217, 119, 6, 0.85)',
    success: 'rgba(5, 150, 105, 0.85)',
    danger: 'rgba(225, 29, 72, 0.85)',
    muted: 'rgba(100, 116, 139, 0.7)',
};

const salesTrendData = computed(() => ({
    labels: props.salesByDay.map((row) => row.date.slice(5)),
    datasets: [{
        label: t('dashboard.gross_sales'),
        data: props.salesByDay.map((row) => Number(row.total)),
        borderColor: chartColors.primary,
        backgroundColor: chartColors.primaryFill,
        fill: true,
        tension: 0.3,
        pointRadius: 2,
    }],
}));

const channelData = computed(() => {
    const palette = [chartColors.primary, chartColors.accent, chartColors.success, chartColors.danger, chartColors.muted];

    return {
        labels: props.salesByChannel.map((row) => t('dashboard.channel.' + row.channel)),
        datasets: [{
            data: props.salesByChannel.map((row) => Number(row.total)),
            backgroundColor: props.salesByChannel.map((_, index) => palette[index % palette.length]),
        }],
    };
});

const topItemsData = computed(() => ({
    labels: props.topItems.map((row) => row.name),
    datasets: [{
        data: props.topItems.map((row) => Number(row.revenue)),
        backgroundColor: chartColors.accent,
    }],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { grid: { display: false } }, y: { beginAtZero: true } },
};

const donutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '62%',
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
};

const barOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true } },
};

const setupChecklist = computed(() => [
    { label: t('dashboard.setup.branch'), done: props.setup.branches > 0, icon: 'building' },
    { label: t('dashboard.setup.menu'), done: props.setup.menuItems > 0, icon: 'book-open' },
    { label: props.setup.menuItems > 0 ? t('dashboard.setup.more_items') : t('dashboard.setup.menu'), done: props.setup.menuItems > 2, icon: 'book-open' },
    { label: t('dashboard.setup.ingredients'), done: props.setup.ingredients > 0, icon: 'boxes-stacked' },
    { label: t('dashboard.setup.recipes'), done: props.setup.recipes > 0, icon: 'list-check' },
    { label: t('dashboard.setup.staff'), done: props.setup.users > 1, icon: 'user-shield' },
]);

const isFreshInstall = computed(() => props.summary.orders === 0 && props.setup.menuItems === 0);

const profitLines = computed(() => ([
    { label: t('dashboard.net_sales'), value: props.summary.net_sales, kind: 'base' },
    { label: t('dashboard.food_cost'), value: props.summary.food_cost, kind: 'cost' },
    { label: t('dashboard.wastage'), value: props.summary.wastage_cost, kind: 'cost' },
    { label: t('dashboard.salary'), value: props.summary.salary_cost, kind: 'cost' },
    { label: t('dashboard.expenses'), value: props.summary.expenses, kind: 'cost' },
    { label: t('dashboard.net_profit'), value: props.summary.net_profit, kind: 'profit' },
]));
</script>

<template>
    <div>
        <PageHeader :title="t('dashboard.title')" :description="t('dashboard.subtitle')">
            <template #default>
                <div class="mt-2">
                    <DateRangePicker :from="from" :to="to" @update:from="applyCustom($event, to)" @update:to="applyCustom(from, $event)" />
                </div>
            </template>
        </PageHeader>

        <!-- KPI row -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <KpiCard :label="t('dashboard.gross_sales')" :value="formatMoney(summary.gross_sales, { compact: true, symbol: true })" :change="change('gross_sales')" :sparkline="salesByDay.map((row) => Number(row.total))" />
            <KpiCard :label="t('dashboard.net_sales')" :value="formatMoney(summary.net_sales, { compact: true, symbol: true })" :change="change('net_sales')" />
            <KpiCard :label="t('dashboard.food_cost')" :value="foodCostPercent + '%'" :state="Number(foodCostPercent) > 40 ? 'danger' : 'success'" />
            <KpiCard :label="t('dashboard.wastage')" :value="formatMoney(summary.wastage_cost, { compact: true, symbol: true })" :change="change('wastage_cost')" />
            <KpiCard :label="t('dashboard.net_profit')" :value="formatMoney(summary.net_profit, { compact: true, symbol: true })" :change="change('net_profit')" :state="Number(summary.net_profit) < 0 ? 'danger' : null" />
            <KpiCard :label="t('dashboard.orders')" :value="formatNumber(summary.orders)" :change="change('orders')" />
        </div>

        <!-- Alerts -->
        <div v-if="alerts.wastageSpike || alerts.lowStock.length" class="mt-4 grid gap-2 lg:grid-cols-2">
            <div v-if="alerts.wastageSpike" class="flex items-center gap-2 rounded-lg border border-danger/40 bg-danger/5 px-3 py-2 text-[13px]">
                <AppIcon name="triangle-exclamation" size="16" class="text-danger" />
                <span>{{ t('dashboard.alerts.wastage_spike') }}
                    <strong class="tabular-nums">{{ formatMoney(alerts.wastageSpike.today, { symbol: true }) }}</strong>
                    ({{ t('dashboard.alerts.week_average') }}: {{ formatMoney(alerts.wastageSpike.week_average, { symbol: true }) }})
                </span>
            </div>

            <div v-if="alerts.lowStock.length" class="rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-[13px]">
                <p class="flex items-center gap-2 font-medium text-foreground">
                    <AppIcon name="triangle-exclamation" size="14" class="text-warning" />
                    {{ t('dashboard.alerts.low_stock_count', { count: alerts.lowStock.length }) }}
                </p>
                <ul class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-muted">
                    <li v-for="item in alerts.lowStock.slice(0, 5)" :key="item.id">
                        {{ item.name }}: {{ Number(item.stock_qty) }} / {{ Number(item.reorder_level) }} {{ item.unit_symbol }}
                    </li>
                </ul>
            </div>
        </div>

        <!-- Charts -->
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            <ChartCard
                :title="t('dashboard.sales_trend')"
                :subtitle="from + ' - ' + to"
                :table-headers="[t('reports.date'), t('dashboard.gross_sales'), t('dashboard.orders')]"
                :table-rows="salesByDay.map((row) => [row.date, row.total, row.orders])"
                export-name="sales-trend"
            >
                <template #chart>
                    <Line :data="salesTrendData" :options="chartOptions" />
                </template>
            </ChartCard>

            <ChartCard
                :title="t('dashboard.sales_by_channel')"
                :table-headers="[t('dashboard.channel_label'), t('dashboard.gross_sales'), t('dashboard.orders')]"
                :table-rows="salesByChannel.map((row) => [t('dashboard.channel.' + row.channel), row.total, row.orders])"
                export-name="sales-by-channel"
            >
                <template #chart>
                    <Doughnut :data="channelData" :options="donutOptions" />
                </template>
            </ChartCard>

            <ChartCard
                :title="t('dashboard.top_items')"
                :table-headers="[t('reports.item'), t('reports.quantity'), t('reports.revenue')]"
                :table-rows="topItems.map((row) => [row.name, row.qty, row.revenue])"
                export-name="top-items"
            >
                <template #chart>
                    <Bar :data="topItemsData" :options="barOptions" />
                </template>
            </ChartCard>

            <section class="rounded-lg border border-border bg-surface p-3">
                <h3 class="text-sm font-semibold text-foreground">{{ t('dashboard.profit_breakdown') }}</h3>

                <ul class="mt-2 space-y-1.5">
                    <li v-for="line in profitLines" :key="line.label" class="flex items-center justify-between text-[13px]">
                        <span class="flex items-center gap-1.5" :class="line.kind === 'profit' ? 'font-semibold' : 'text-muted'">
                            <AppIcon v-if="line.kind === 'cost'" name="arrow-down" size="10" class="text-danger" />
                            <AppIcon v-else-if="line.kind === 'profit'" name="arrow-up" size="10" :class="Number(line.value) >= 0 ? 'text-success' : 'text-danger'" />
                            {{ line.label }}
                        </span>
                        <MoneyText :value="line.value" :negative-danger="true" :class="line.kind === 'profit' ? 'font-bold' : ''" />
                    </li>
                </ul>

                <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-border/60">
                    <div
                        v-if="Number(summary.net_sales) > 0"
                        class="h-full bg-success"
                        :style="{ width: Math.max(0, Math.min(100, Number(summary.net_profit) / Number(summary.net_sales) * 100)) + '%' }"
                    />
                </div>
                <p class="mt-1 text-right text-xs text-muted">
                    {{ t('dashboard.margin') }}:
                    {{ Number(summary.net_sales) > 0 ? (Number(summary.net_profit) / Number(summary.net_sales) * 100).toFixed(1) : '0.0' }}%
                </p>
            </section>
        </div>

        <!-- Branch comparison (owners) -->
        <section v-if="branchComparison.length" class="mt-4 overflow-hidden rounded-lg border border-border bg-surface">
            <header class="flex items-center justify-between border-b border-border px-3 py-2">
                <h3 class="text-sm font-semibold text-foreground">{{ t('dashboard.branch_comparison') }}</h3>
                <a href="/admin/reports?type=branch-pl" class="text-xs text-muted hover:text-foreground">{{ t('common.view') }}</a>
            </header>

            <div class="thin-scrollbar overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-muted">
                            <th class="px-3 py-2">{{ t('branches.code') }}</th>
                            <th class="px-3 py-2">{{ t('branches.name') }}</th>
                            <th class="px-3 py-2 text-right">{{ t('dashboard.net_sales') }}</th>
                            <th class="px-3 py-2 text-right">{{ t('dashboard.food_cost') }}</th>
                            <th class="px-3 py-2 text-right">{{ t('dashboard.net_profit') }}</th>
                            <th class="px-3 py-2 text-right">{{ t('dashboard.orders') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in branchComparison" :key="row.branch_id" class="border-t border-border/60">
                            <td class="px-3 py-1.5 font-medium">{{ row.code }}</td>
                            <td class="px-3 py-1.5">{{ row.name }}</td>
                            <td class="px-3 py-1.5 text-right"><MoneyText :value="row.net_sales" /></td>
                            <td class="px-3 py-1.5 text-right"><MoneyText :value="row.food_cost" /></td>
                            <td class="px-3 py-1.5 text-right"><MoneyText :value="row.net_profit" :negative-danger="true" /></td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ row.orders }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Fresh install setup checklist -->
        <section v-if="isFreshInstall" class="mt-4 rounded-lg border border-border bg-surface p-4">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                <AppIcon name="clipboard-list" size="14" class="text-accent" />
                {{ t('dashboard.setup.title') }}
            </h2>

            <ul class="space-y-1.5">
                <li v-for="item in setupChecklist" :key="item.label" class="flex items-center gap-2 rounded-md border border-border bg-background/50 px-3 py-2 text-[13px]">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full" :class="item.done ? 'bg-success/15 text-success' : 'bg-muted/10 text-muted'">
                        <AppIcon :name="item.done ? 'check' : item.icon" size="10" />
                    </span>
                    <span :class="item.done ? 'text-muted line-through' : 'text-foreground'">{{ item.label }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
