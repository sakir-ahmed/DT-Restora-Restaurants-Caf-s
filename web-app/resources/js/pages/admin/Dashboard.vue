<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
import InfoButton from '@/components/ui-kit/InfoButton.vue';
import InfoModal from '@/components/ui-kit/InfoModal.vue';
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
    previousFrom: { type: String, required: true },
    previousTo: { type: String, required: true },
    summary: { type: Object, required: true },
    previous: { type: Object, required: true },
    salesByDay: { type: Array, default: () => [] },
    salesByChannel: { type: Array, default: () => [] },
    topItems: { type: Array, default: () => [] },
    branchComparison: { type: Array, default: () => [] },
    expensesByCategory: { type: Array, default: () => [] },
    dues: { type: Object, required: true },
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

function setRange({ from, to, preset }) {
    router.get(route('admin.dashboard'), { period: preset, from, to }, { preserveState: true, preserveScroll: true });
}

const infoKey = ref(null);

const money = (value) => formatMoney(value, { symbol: true });

const previousRange = computed(() => props.previousFrom + ' - ' + props.previousTo);

function comparisonFor(key, format = money) {
    const current = Number(props.summary[key] ?? 0);
    const previous = Number(props.previous[key] ?? 0);

    let text = t('dashboard.info.cmp_normal', { change: change(key) });

    if (previous <= 0 && current <= 0) {
        text = t('dashboard.info.cmp_both_zero');
    } else if (previous <= 0) {
        text = t('dashboard.info.cmp_no_baseline');
    } else if (current === 0) {
        text = t('dashboard.info.cmp_dropped_zero', { previous: format(previous) });
    }

    return { current: format(current), previous: format(previous), range: previousRange.value, text };
}

function fact(label, value, strong = false) {
    return { label, value, strong };
}

function profitFacts() {
    const s = props.summary;
    const margin = Number(s.net_sales) > 0 ? (Number(s.net_profit) / Number(s.net_sales) * 100).toFixed(1) + '%' : '0.0%';

    return [
        fact(t('dashboard.net_sales'), money(s.net_sales)),
        fact('- ' + t('dashboard.food_cost'), money(s.food_cost)),
        fact('- ' + t('dashboard.wastage'), money(s.wastage_cost)),
        fact('- ' + t('dashboard.salary'), money(s.salary_cost)),
        fact('- ' + t('dashboard.expenses'), money(s.expenses)),
        fact('- ' + t('dashboard.commission'), money(s.commission)),
        fact('= ' + t('dashboard.net_profit'), money(s.net_profit), true),
        fact(t('dashboard.margin'), margin),
    ];
}

const infoDetails = computed(() => {
    const s = props.summary;
    const channelName = (row) => t('dashboard.channel.' + row.channel);
    const categoryName = (row) => t('expenses.categories.' + row.category);
    const averageOrder = s.orders > 0 ? Number(s.gross_sales) / s.orders : 0;
    const bestDay = props.salesByDay.reduce((best, row) => (Number(row.total) > Number(best?.total ?? -1) ? row : best), null);

    return {
        gross_sales: {
            facts: [
                fact(t('dashboard.orders'), formatNumber(s.orders)),
                fact(t('dashboard.net_sales'), money(s.net_sales)),
                fact(t('dashboard.discount'), money(s.discount)),
                fact(t('dashboard.vat'), money(s.vat)),
                fact(t('dashboard.service_charge'), money(s.service_charge)),
                fact(t('dashboard.gross_sales'), money(s.gross_sales), true),
            ],
            compare: comparisonFor('gross_sales'),
        },
        net_sales: {
            facts: [
                fact(t('dashboard.gross_sales'), money(s.gross_sales)),
                fact(t('dashboard.discount'), money(s.discount)),
                fact(t('dashboard.vat'), money(s.vat)),
                fact(t('dashboard.service_charge'), money(s.service_charge)),
                fact(t('dashboard.net_sales'), money(s.net_sales), true),
            ],
            compare: comparisonFor('net_sales'),
        },
        food_cost: {
            facts: [
                fact(t('dashboard.food_cost'), money(s.food_cost)),
                fact(t('dashboard.net_sales'), money(s.net_sales)),
                fact('%', foodCostPercent.value + '%', true),
            ],
        },
        wastage: {
            facts: [fact(t('dashboard.wastage'), money(s.wastage_cost), true)],
            compare: comparisonFor('wastage_cost'),
        },
        net_profit: { facts: profitFacts(), compare: comparisonFor('net_profit') },
        orders: {
            facts: [
                fact(t('dashboard.orders'), formatNumber(s.orders), true),
                fact(t('dashboard.info.order_lines'), formatNumber(s.order_items)),
                fact(t('dashboard.info.average_order'), money(averageOrder)),
            ],
            compare: comparisonFor('orders', formatNumber),
        },
        expenses: {
            facts: [
                ...props.expensesByCategory.map((row) => fact(categoryName(row), money(row.total))),
                fact(t('dashboard.expenses'), money(s.expenses), true),
            ],
            compare: comparisonFor('expenses'),
        },
        dues_outstanding: {
            facts: [
                fact(t('dashboard.dues_open_count'), formatNumber(props.dues.open_count)),
                fact(t('dashboard.dues_outstanding'), money(props.dues.outstanding), true),
            ],
        },
        dues_collected: { facts: [fact(t('dashboard.dues_collected'), money(props.dues.collected), true)] },
        dues: {
            facts: [
                fact(t('dashboard.dues_open_count'), formatNumber(props.dues.open_count)),
                fact(t('dashboard.dues_outstanding'), money(props.dues.outstanding), true),
                fact(t('dashboard.dues_new'), money(props.dues.new_due)),
                fact(t('dashboard.dues_collected'), money(props.dues.collected)),
            ],
        },
        sales_trend: {
            facts: [
                fact(t('dashboard.info.days_with_sales'), formatNumber(props.salesByDay.length)),
                fact(t('dashboard.info.best_day'), bestDay ? bestDay.date + ' - ' + money(bestDay.total) : '-'),
                fact(t('dashboard.gross_sales'), money(s.gross_sales), true),
            ],
        },
        sales_by_channel: {
            facts: props.salesByChannel.map((row) => fact(channelName(row), money(row.total) + ' / ' + formatNumber(row.orders))),
        },
        top_items: {
            facts: props.topItems.map((row) => fact(row.name, money(row.revenue) + ' / ' + formatNumber(row.qty))),
        },
        expenses_by_category: {
            facts: [
                ...props.expensesByCategory.map((row) => fact(categoryName(row), money(row.total))),
                fact(t('dashboard.expenses'), money(s.expenses), true),
            ],
        },
        profit_breakdown: { facts: profitFacts(), compare: comparisonFor('net_profit') },
        branch_comparison: {
            facts: props.branchComparison.map((row) => fact(row.code + ' - ' + row.name, money(row.net_profit))),
        },
        low_stock: {
            facts: props.alerts.lowStock.map((item) => fact(item.name, Number(item.stock_qty) + ' / ' + Number(item.reorder_level) + ' ' + item.unit_symbol)),
        },
        wastage_spike: {
            facts: props.alerts.wastageSpike
                ? [
                    fact(t('dashboard.alerts.today'), money(props.alerts.wastageSpike.today)),
                    fact(t('dashboard.alerts.week_average'), money(props.alerts.wastageSpike.week_average)),
                ]
                : [],
        },
    };
});

const infoContent = computed(() => {
    const key = infoKey.value;

    if (! key) {
        return null;
    }

    const base = 'dashboard.info.items.' + key + '.';
    const details = infoDetails.value[key] ?? {};

    return {
        title: t(base + 'title'),
        description: t(base + 'what'),
        formula: t(base + 'formula'),
        facts: details.facts ?? [],
        comparison: details.compare ?? null,
        notes: ['note1', 'note2', 'note3']
            .map((note) => t(base + note))
            .filter((text) => ! text.startsWith('dashboard.info.items.')),
    };
});

const chartColors = {
    primary: 'rgba(11, 99, 206, 0.9)',
    primaryFill: 'rgba(11, 99, 206, 0.10)',
    accent: 'rgba(56, 168, 255, 0.9)',
    success: 'rgba(5, 150, 105, 0.85)',
    danger: 'rgba(225, 29, 72, 0.85)',
    muted: 'rgba(74, 85, 104, 0.7)',
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

const expenseData = computed(() => {
    const palette = [chartColors.primary, chartColors.accent, chartColors.success, chartColors.danger, chartColors.muted];

    return {
        labels: props.expensesByCategory.map((row) => t('expenses.categories.' + row.category)),
        datasets: [{
            data: props.expensesByCategory.map((row) => Number(row.total)),
            backgroundColor: props.expensesByCategory.map((_, index) => palette[index % palette.length]),
        }],
    };
});

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
                    <DateRangePicker :from="from" :to="to" :active="period" @change="setRange" />
                </div>
            </template>
        </PageHeader>

        <!-- KPI row -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <KpiCard info @info="infoKey = 'gross_sales'" :label="t('dashboard.gross_sales')" :value="formatMoney(summary.gross_sales, { symbol: true })" :change="change('gross_sales')" :sparkline="salesByDay.map((row) => Number(row.total))" />
            <KpiCard info @info="infoKey = 'net_sales'" :label="t('dashboard.net_sales')" :value="formatMoney(summary.net_sales, { symbol: true })" :change="change('net_sales')" />
            <KpiCard info @info="infoKey = 'food_cost'" :label="t('dashboard.food_cost')" :value="foodCostPercent + '%'" :state="Number(foodCostPercent) > 40 ? 'danger' : 'success'" />
            <KpiCard info @info="infoKey = 'wastage'" :label="t('dashboard.wastage')" :value="formatMoney(summary.wastage_cost, { symbol: true })" :change="change('wastage_cost')" />
            <KpiCard info @info="infoKey = 'net_profit'" :label="t('dashboard.net_profit')" :value="formatMoney(summary.net_profit, { symbol: true })" :change="change('net_profit')" :state="Number(summary.net_profit) < 0 ? 'danger' : null" />
            <KpiCard info @info="infoKey = 'orders'" :label="t('dashboard.orders')" :value="formatNumber(summary.orders)" :change="change('orders')" />
            <KpiCard info @info="infoKey = 'expenses'" :label="t('dashboard.expenses')" :value="formatMoney(summary.expenses, { symbol: true })" :change="change('expenses')" />
            <KpiCard info @info="infoKey = 'dues_outstanding'" :label="t('dashboard.dues_outstanding')" :value="formatMoney(dues.outstanding, { symbol: true })" :state="Number(dues.outstanding) > 0 ? 'danger' : 'success'" />
            <KpiCard info @info="infoKey = 'dues_collected'" :label="t('dashboard.dues_collected')" :value="formatMoney(dues.collected, { symbol: true })" />
        </div>

        <!-- Alerts -->
        <div v-if="alerts.wastageSpike || alerts.lowStock.length" class="mt-4 grid gap-2 lg:grid-cols-2">
            <div v-if="alerts.wastageSpike" class="flex items-center gap-2 rounded-lg border border-danger/40 bg-danger/5 px-3 py-2 text-[13px]">
                <AppIcon name="triangle-exclamation" size="16" class="text-danger" />
                <span class="flex-1">{{ t('dashboard.alerts.wastage_spike') }}
                    <strong class="tabular-nums">{{ formatMoney(alerts.wastageSpike.today, { symbol: true }) }}</strong>
                    ({{ t('dashboard.alerts.week_average') }}: {{ formatMoney(alerts.wastageSpike.week_average, { symbol: true }) }})
                </span>
                <InfoButton @click="infoKey = 'wastage_spike'" />
            </div>

            <div v-if="alerts.lowStock.length" class="rounded-lg border border-danger/50 bg-danger/10 px-3 py-2 text-[13px]" role="alert">
                <p class="flex items-center gap-2 font-semibold text-danger">
                    <AppIcon name="triangle-exclamation" size="14" />
                    <span class="flex-1">{{ t('dashboard.alerts.low_stock_count', { count: alerts.lowStock.length }) }}</span>
                    <InfoButton @click="infoKey = 'low_stock'" />
                </p>
                <ul class="mt-1.5 flex flex-wrap gap-1.5">
                    <li v-for="item in alerts.lowStock.slice(0, 8)" :key="item.id" class="inline-flex items-center gap-1 rounded-md border border-danger/40 bg-surface px-2 py-0.5 text-xs text-foreground">
                        <span class="h-1.5 w-1.5 rounded-full bg-danger" />
                        {{ item.name }}
                        <span class="font-semibold tabular-nums text-danger">{{ Number(item.stock_qty) }} / {{ Number(item.reorder_level) }} {{ item.unit_symbol }}</span>
                    </li>
                    <li v-if="alerts.lowStock.length > 8" class="px-1 text-xs text-muted">+{{ alerts.lowStock.length - 8 }}</li>
                </ul>
                <Link :href="route('admin.ingredients.index', { low: 1 })" class="mt-1.5 inline-block text-xs font-medium text-danger underline">{{ t('dashboard.alerts.view_low_stock') }}</Link>
            </div>
        </div>

        <!-- Charts -->
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            <ChartCard
                info
                :title="t('dashboard.sales_trend')"
                @info="infoKey = 'sales_trend'"
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
                info
                :title="t('dashboard.sales_by_channel')"
                @info="infoKey = 'sales_by_channel'"
                :table-headers="[t('dashboard.channel_label'), t('dashboard.gross_sales'), t('dashboard.orders')]"
                :table-rows="salesByChannel.map((row) => [t('dashboard.channel.' + row.channel), row.total, row.orders])"
                export-name="sales-by-channel"
            >
                <template #chart>
                    <Doughnut :data="channelData" :options="donutOptions" />
                </template>
            </ChartCard>

            <ChartCard
                info
                :title="t('dashboard.top_items')"
                @info="infoKey = 'top_items'"
                :table-headers="[t('reports.item'), t('reports.quantity'), t('reports.revenue')]"
                :table-rows="topItems.map((row) => [row.name, row.qty, row.revenue])"
                export-name="top-items"
            >
                <template #chart>
                    <Bar :data="topItemsData" :options="barOptions" />
                </template>
            </ChartCard>

            <ChartCard
                info
                :title="t('dashboard.expenses_by_category')"
                @info="infoKey = 'expenses_by_category'"
                :table-headers="[t('expenses.category'), t('dashboard.expenses')]"
                :table-rows="expensesByCategory.map((row) => [t('expenses.categories.' + row.category), row.total])"
                export-name="expenses-by-category"
            >
                <template #chart>
                    <Doughnut :data="expenseData" :options="donutOptions" />
                </template>
            </ChartCard>

            <section class="rounded-lg border border-border bg-surface p-3">
                <header class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-foreground">{{ t('dashboard.dues_title') }}</h3>
                    <div class="flex items-center gap-2">
                        <a :href="route('admin.dues.index')" class="text-xs text-muted hover:text-foreground">{{ t('common.view') }}</a>
                        <InfoButton @click="infoKey = 'dues'" />
                    </div>
                </header>

                <ul class="mt-2 space-y-1.5 text-[13px]">
                    <li class="flex items-center justify-between">
                        <span class="text-muted">{{ t('dashboard.dues_open_count') }}</span>
                        <span class="tabular-nums">{{ formatNumber(dues.open_count) }}</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-muted">{{ t('dashboard.dues_outstanding') }}</span>
                        <MoneyText :value="dues.outstanding" class="font-semibold" />
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-muted">{{ t('dashboard.dues_new') }}</span>
                        <MoneyText :value="dues.new_due" />
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-muted">{{ t('dashboard.dues_collected') }}</span>
                        <MoneyText :value="dues.collected" />
                    </li>
                </ul>
            </section>

            <section class="rounded-lg border border-border bg-surface p-3">
                <header class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-foreground">{{ t('dashboard.profit_breakdown') }}</h3>
                    <InfoButton @click="infoKey = 'profit_breakdown'" />
                </header>

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
                <div class="flex items-center gap-2">
                    <a href="/admin/reports?type=branch-pl" class="text-xs text-muted hover:text-foreground">{{ t('common.view') }}</a>
                    <InfoButton @click="infoKey = 'branch_comparison'" />
                </div>
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

        <InfoModal
            :open="infoKey !== null"
            :title="infoContent?.title ?? ''"
            :description="infoContent?.description ?? ''"
            :formula="infoContent?.formula ?? ''"
            :facts="infoContent?.facts ?? []"
            :comparison="infoContent?.comparison ?? null"
            :notes="infoContent?.notes ?? []"
            @close="infoKey = null"
        />

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
