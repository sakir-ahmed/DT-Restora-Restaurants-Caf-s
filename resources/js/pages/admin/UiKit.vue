<script setup>
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ChartCard from '@/components/ui-kit/ChartCard.vue';
import Combobox from '@/components/ui-kit/Combobox.vue';
import DateRangePicker from '@/components/ui-kit/DateRangePicker.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import ErrorState from '@/components/ui-kit/ErrorState.vue';
import FileUpload from '@/components/ui-kit/FileUpload.vue';
import FilterBar from '@/components/ui-kit/FilterBar.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import KpiCard from '@/components/ui-kit/KpiCard.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import MoneyText from '@/components/ui-kit/MoneyText.vue';
import MultiSelect from '@/components/ui-kit/MultiSelect.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import QuantityStepper from '@/components/ui-kit/QuantityStepper.vue';
import RadioCards from '@/components/ui-kit/RadioCards.vue';
import SkeletonRows from '@/components/ui-kit/SkeletonRows.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    demoRows: { type: Array, default: () => [] },
});

const confirm = useConfirm();

const textValue = ref('');
const moneyValue = ref('1250.00');
const mobileValue = ref('01711000001');
const quantity = ref(2);
const switchOn = ref(true);
const paymentMethod = ref('cash');
const comboboxValue = ref(null);
const multiValue = ref(['dine-in']);
const dateFrom = ref('');
const dateTo = ref('');
const slideOverOpen = ref(false);

const comboboxOptions = [
    { value: 1, label: 'Chicken Burger', hint: 'Fast food' },
    { value: 2, label: 'Beef Burger', hint: 'Fast food' },
    { value: 3, label: 'Lacchi Sharbat', hint: 'Drinks' },
];

const multiOptions = [
    { value: 'dine-in', label: 'Dine-in' },
    { value: 'takeaway', label: 'Takeaway' },
    { value: 'delivery', label: 'Delivery' },
];

const tableColumns = [
    { key: 'invoice', label: t('ui_kit.invoice'), align: 'left', sortable: true, minWidth: '180px' },
    { key: 'customer', label: t('ui_kit.customer'), align: 'left', sortable: true },
    { key: 'items', label: t('ui_kit.items'), type: 'number', sortable: true, minWidth: '64px' },
    { key: 'total_display', label: t('ui_kit.total'), type: 'money', sortable: true, minWidth: '96px' },
    { key: 'status', label: t('ui_kit.status'), minWidth: '104px' },
    { key: 'created_at', label: t('ui_kit.created'), align: 'left', minWidth: '112px' },
];

const tableRows = props.demoRows.map((row) => ({ ...row, total_display: formatMoney(row.total), total: Number(row.total) }));

async function confirmSimple() {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('ui_kit.confirm_simple_title'),
        message: t('ui_kit.confirm_simple_message'),
        confirmText: t('ui_kit.confirm_simple_action'),
    });

    if (result.confirmed) {
        notify.success(t('ui_kit.confirmed'));
    }
}

async function confirmReason() {
    const result = await confirm({
        variant: 'warning',
        level: 'reason',
        title: t('ui_kit.confirm_reason_title'),
        message: t('ui_kit.confirm_reason_message'),
        confirmText: t('ui_kit.confirm_reason_action'),
        reasons: ['wrong_item', 'customer_changed_mind'],
    });

    if (result.confirmed) {
        notify.success(t('ui_kit.confirmed_with_reason', { reason: result.reason }));
    }
}

async function confirmTyped() {
    const result = await confirm({
        variant: 'danger',
        level: 'typed',
        title: t('ui_kit.confirm_typed_title'),
        message: t('ui_kit.confirm_typed_message'),
        confirmText: t('ui_kit.confirm_typed_action'),
        typedWord: 'CONFIRM',
    });

    if (result.confirmed) {
        notify.success(t('ui_kit.confirmed'));
    }
}

const chartHeaders = ['Day', 'Sales', 'Orders'];
const chartRows = [
    ['Mon', '12,500', '34'],
    ['Tue', '14,200', '41'],
    ['Wed', '11,800', '29'],
    ['Thu', '15,600', '45'],
    ['Fri', '19,300', '58'],
    ['Sat', '22,100', '66'],
    ['Sun', '17,400', '51'],
];
</script>

<template>
    <div class="space-y-6">
        <PageHeader :title="t('ui_kit.title')" :description="t('ui_kit.description')" />

        <!-- Buttons -->
        <section class="rounded-lg border border-border bg-surface p-4">
            <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.buttons') }}</h2>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90">
                    <AppIcon name="plus" size="12" />
                    {{ t('ui_kit.primary') }}
                </button>

                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background">
                    {{ t('ui_kit.secondary') }}
                </button>

                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-[13px] font-medium text-muted hover:bg-background hover:text-foreground">
                    {{ t('ui_kit.ghost') }}
                </button>

                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-danger px-2.5 text-[13px] font-medium text-white hover:bg-danger/90">
                    <AppIcon name="trash" size="12" />
                    {{ t('ui_kit.destructive') }}
                </button>

                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-accent px-2.5 text-[13px] font-medium text-accent-foreground hover:opacity-90">
                    <AppIcon name="cash-register" size="12" />
                    {{ t('ui_kit.accent') }}
                </button>

                <button type="button" disabled class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground opacity-50 cursor-not-allowed">
                    {{ t('ui_kit.disabled') }}
                </button>

                <button type="button" class="inline-flex h-6 items-center gap-1 rounded-md border border-border px-1.5 text-xs text-foreground hover:bg-background">
                    {{ t('ui_kit.xs') }}
                </button>

                <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">
                    {{ t('ui_kit.lg') }}
                </button>

                <button type="button" class="inline-flex h-11 items-center gap-1.5 rounded-lg bg-accent px-4 text-base font-medium text-accent-foreground">
                    <AppIcon name="money-bill-wave" size="16" />
                    {{ t('ui_kit.touch') }}
                </button>
            </div>
        </section>

        <!-- Form inputs -->
        <section class="rounded-lg border border-border bg-surface p-4">
            <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.form') }}</h2>

            <form class="grid max-w-2xl grid-cols-2 gap-x-4 gap-y-3" @submit.prevent>
                <FormField :label="t('ui_kit.text_label')" required :helper="t('ui_kit.text_helper')" :error="textValue === 'error' ? t('ui_kit.text_error') : ''">
                    <template #default="{ id }">
                        <input
                            :id="id"
                            v-model="textValue"
                            type="text"
                            class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40"
                        >
                    </template>
                </FormField>

                <FormField :label="t('ui_kit.money_label')" required>
                    <MoneyInput v-model="moneyValue" />
                </FormField>

                <FormField :label="t('ui_kit.mobile_label')" :helper="t('ui_kit.mobile_helper')">
                    <MobileInput v-model="mobileValue" />
                </FormField>

                <FormField :label="t('ui_kit.quantity_label')">
                    <QuantityStepper v-model="quantity" />
                </FormField>

                <FormField :label="t('ui_kit.combobox_label')">
                    <Combobox v-model="comboboxValue" :options="comboboxOptions" allow-create />
                </FormField>

                <FormField :label="t('ui_kit.multiselect_label')">
                    <MultiSelect v-model="multiValue" :options="multiOptions" />
                </FormField>

                <FormField :label="t('ui_kit.select_label')">
                    <select class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground">
                        <option>{{ t('ui_kit.option_one') }}</option>
                        <option>{{ t('ui_kit.option_two') }}</option>
                    </select>
                </FormField>

                <FormField :label="t('ui_kit.file_label')">
                    <FileUpload />
                </FormField>

                <div class="col-span-2">
                    <SwitchField v-model="switchOn" :label="t('ui_kit.switch_label')" />
                </div>
            </form>

            <div class="mt-4 max-w-md">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted">{{ t('ui_kit.radio_cards') }}</h3>
                <RadioCards
                    v-model="paymentMethod"
                    :options="[
                        { value: 'cash', label: t('payment_method.cash'), icon: 'money-bill-wave' },
                        { value: 'bkash', label: t('payment_method.bkash'), icon: 'mobile-screen' },
                        { value: 'card', label: t('payment_method.card'), icon: 'credit-card' },
                    ]"
                />
            </div>
        </section>

        <!-- Confirmations -->
        <section class="rounded-lg border border-border bg-surface p-4">
            <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.confirmations') }}</h2>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px] hover:bg-background" @click="confirmSimple">
                    {{ t('ui_kit.level_simple') }}
                </button>
                <button type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px] hover:bg-background" @click="confirmReason">
                    {{ t('ui_kit.level_reason') }}
                </button>
                <button type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px] hover:bg-background" @click="confirmTyped">
                    {{ t('ui_kit.level_typed') }}
                </button>
            </div>
        </section>

        <!-- Badges & display -->
        <section class="rounded-lg border border-border bg-surface p-4">
            <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.badges') }}</h2>

            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge status="paid" />
                <StatusBadge status="pending" />
                <StatusBadge status="preparing" />
                <StatusBadge status="void" />
                <StatusBadge status="active" />
                <StatusBadge status="inactive" />
                <StatusBadge status="approved" />
                <StatusBadge status="failed" />
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-4 text-[13px]">
                <span>{{ t('ui_kit.money_examples') }}:
                    <MoneyText value="1234567.5" symbol /> |
                    <MoneyText value="-1250.25" negative-danger /> |
                    <MoneyText value="0" /> |
                    <MoneyText value="12500000" compact symbol />
                </span>
                <span>{{ t('ui_kit.date_example') }}: <DateText value="2026-01-01T10:30:00+06:00" /></span>
            </div>
        </section>

        <!-- KPI cards -->
        <section>
            <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.kpi') }}</h2>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard :label="t('dashboard.gross_sales')" :value="formatMoney(234500, { compact: true, symbol: true })" :change="8.2" :sparkline="[10, 12, 9, 14, 13, 16, 18]" />
                <KpiCard :label="t('dashboard.net_profit')" :value="formatMoney(45200, { compact: true, symbol: true })" :change="-3.1" state="danger" :sparkline="[12, 11, 13, 10, 9, 11, 9]" />
                <KpiCard :label="t('dashboard.food_cost')" value="31.8%" state="danger" />
                <KpiCard :label="t('dashboard.orders')" :value="324" :change="2.4" loading />
            </div>
        </section>

        <!-- Chart card -->
        <section>
            <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.chart') }}</h2>

            <div class="grid gap-3 lg:grid-cols-2">
                <ChartCard
                    :title="t('ui_kit.sales_trend')"
                    :subtitle="t('ui_kit.last_7_days')"
                    :table-headers="chartHeaders"
                    :table-rows="chartRows"
                    export-name="sales-trend"
                >
                    <template #chart>
                        <div class="flex h-full items-end gap-1.5 p-2" aria-hidden="true">
                            <div
                                v-for="(row, index) in chartRows"
                                :key="index"
                                class="flex-1 rounded-t bg-primary/80 dark:bg-primary/60"
                                :style="{ height: `${(Number(row[1].replace(/[^\d]/g, '')) / 250) * 100}%` }"
                            />
                        </div>
                    </template>
                </ChartCard>

                <ChartCard :title="t('ui_kit.empty_chart')" :empty="true" />
            </div>
        </section>

        <!-- Filter bar -->
        <section>
            <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.filter_bar') }}</h2>

            <FilterBar>
                <DateRangePicker v-model:from="dateFrom" v-model:to="dateTo" />
                <select class="h-7 rounded-md border border-border bg-surface px-2 text-[13px] text-foreground">
                    <option>{{ t('ui_kit.all_branches') }}</option>
                    <option>BR01</option>
                    <option>BR02</option>
                </select>

                <template #actions>
                    <button type="button" class="h-7 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground">
                        {{ t('common.apply') }}
                    </button>
                    <button type="button" class="text-xs text-muted hover:text-foreground">
                        {{ t('common.reset') }}
                    </button>
                </template>
            </FilterBar>
        </section>

        <!-- Data table -->
        <section>
            <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.data_table') }}</h2>

            <DataTable
                :columns="tableColumns"
                :rows="tableRows"
                searchable
                clickable
                :totals-row="{ label: t('common.total'), items: tableRows.reduce((sum, row) => sum + row.items, 0), total_display: formatMoney(tableRows.reduce((sum, row) => sum + row.total, 0)) }"
            >
                <template #cell(status)="{ value }">
                    <StatusBadge :status="value" />
                </template>
                <template #cell(created_at)="{ value }">
                    <DateText :value="value" relative />
                </template>
            </DataTable>
        </section>

        <!-- States -->
        <section class="grid gap-3 lg:grid-cols-3">
            <div class="rounded-lg border border-border bg-surface p-4">
                <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.empty_state') }}</h2>
                <EmptyState icon="boxes-stacked" :title="t('ui_kit.no_suppliers')" :description="t('ui_kit.no_suppliers_hint')" :action-label="t('ui_kit.add_supplier')" />
            </div>

            <div class="rounded-lg border border-border bg-surface p-4">
                <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.error_state') }}</h2>
                <ErrorState />
            </div>

            <div class="rounded-lg border border-border bg-surface p-4">
                <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.skeleton') }}</h2>
                <SkeletonRows :rows="5" />
            </div>
        </section>

        <!-- Slide-over -->
        <section class="rounded-lg border border-border bg-surface p-4">
            <h2 class="mb-3 text-sm font-semibold text-foreground">{{ t('ui_kit.slide_over') }}</h2>
            <button type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px] hover:bg-background" @click="slideOverOpen = true">
                {{ t('ui_kit.open_slide_over') }}
            </button>

            <SlideOver :open="slideOverOpen" :title="t('ui_kit.slide_over_title')" :subtitle="t('ui_kit.slide_over_subtitle')" @close="slideOverOpen = false">
                <p class="text-[13px] text-muted">{{ t('ui_kit.slide_over_body') }}</p>
                <div class="mt-3">
                    <SkeletonRows :rows="3" />
                </div>

                <template #footer>
                    <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="slideOverOpen = false">
                        {{ t('common.cancel') }}
                    </button>
                    <button type="button" class="h-7 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground" @click="slideOverOpen = false; notify.success(t('common.saved'));">
                        {{ t('common.save') }}
                    </button>
                </template>
            </SlideOver>
        </section>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
