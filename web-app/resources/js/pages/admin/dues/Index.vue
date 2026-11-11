<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import OrderDetailPanel from '@/components/admin/OrderDetailPanel.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import KpiCard from '@/components/ui-kit/KpiCard.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import MoneyText from '@/components/ui-kit/MoneyText.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { localized } from '@/lib/site';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { printUrl } from '@/lib/print';

const props = defineProps({
    orders: { type: Array, required: true },
    methods: { type: Array, default: () => [] },
    canCollect: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
});

const selectedId = ref(null);
const selected = computed(() => {
    const order = props.orders.find((entry) => entry.id === selectedId.value);

    return order ? { ...order, is_due: ! order.deleted, created_at: order.sold_at, order_status: order.deleted ? 'void' : (order.kitchen_status ?? 'served'), payment_status: order.deleted ? 'deleted' : 'due' } : null;
});

const collecting = ref(null);

const form = useForm({ method: '', amount: '', reference: '' });

const liveOrders = computed(() => props.orders.filter((order) => ! order.deleted));
const totalDue = computed(() => liveOrders.value.reduce((sum, order) => sum + order.balance, 0));
const customerCount = computed(() => new Set(liveOrders.value.map((order) => order.customer?.id)).size);

const columns = computed(() => [
    { key: 'invoice_no', label: t('dues.invoice'), align: 'left', sortable: true },
    { key: 'customer_label', label: t('dues.customer'), align: 'left', sortable: true },
    { key: 'sold_at', label: t('dues.sold'), align: 'left', sortable: true },
    { key: 'total', label: t('dues.total'), align: 'right', type: 'money' },
    { key: 'paid', label: t('dues.paid'), align: 'right', type: 'money' },
    { key: 'balance', label: t('dues.balance'), align: 'right', type: 'money', sortable: true },
    ...(props.canCollect || props.canDelete ? [{ key: 'actions', label: '', align: 'right', type: 'component', width: '136px' }] : []),
]);

const rows = computed(() => props.orders.map((order) => ({
    ...order,
    customer_label: [order.customer?.name, order.customer?.mobile].filter(Boolean).join(' '),
})));

const confirm = useConfirm();

async function removeDue(order) {
    const result = await confirm({
        variant: 'danger',
        level: 'reason',
        title: t('dues.delete_title', { invoice: order.invoice_no }),
        message: t('dues.delete_message'),
        confirmText: t('common.delete'),
        reasons: ['wrong_item', 'customer_changed_mind', 'duplicate_order', 'other'],
        facts: [
            { label: t('dues.invoice'), value: order.invoice_no },
            { label: t('dues.total'), value: formatMoney(order.total) },
            { label: t('dues.paid'), value: formatMoney(order.paid) },
            { label: t('dues.balance'), value: formatMoney(order.balance) },
        ],
    });

    if (! result.confirmed) {
        return;
    }

    router.post(route('admin.orders.delete', order.id), { reason_code: result.reason, note: result.note ?? null }, {
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.flash?.error) {
                notify.error(String(page.props.flash.error));

                return;
            }

            selectedId.value = null;
            notify.success(t('orders.deleted'));
        },
        onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
    });
}

function methodLabel(code) {
    const entry = props.methods.find((method) => method.method === code);

    return entry ? localized(entry, 'name') : code;
}

function printReceipt(order) {
    printUrl(route('admin.orders.receipt', order.id));
}

function editOrder(order) {
    router.get(route('admin.pos.index'), { edit: order.id });
}

function openCollect(order) {
    selectedId.value = null;
    collecting.value = order;
    form.defaults({ method: props.methods[0]?.method ?? 'cash', amount: order.balance.toFixed(2), reference: '' }).reset();
    form.clearErrors();
}

function submit() {
    if (! collecting.value) {
        return;
    }

    form.post(route('admin.dues.collect', collecting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            collecting.value = null;
            notify.success(t('orders.due_collected'));
        },
        onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('dues.title')" :description="t('dues.subtitle')" />

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <KpiCard :label="t('dues.total_due')" :value="formatMoney(totalDue)" icon="hand-holding-dollar" />
            <KpiCard :label="t('dues.open_sales')" :value="String(liveOrders.length)" icon="receipt" />
            <KpiCard :label="t('dues.customers')" :value="String(customerCount)" icon="users" />
        </div>

        <DataTable
            :columns="columns"
            :rows="rows"
            searchable
            :search-placeholder="t('dues.search')"
            :empty-title="t('dues.empty')"
            clickable
            @row-click="(row) => selectedId = row.id"
        >
            <template #cell(customer_label)="{ row }">
                <span class="block truncate font-medium">{{ row.customer?.name }}</span>
                <span class="block truncate text-xs text-muted">{{ row.customer?.mobile }}</span>
            </template>
            <template #cell(sold_at)="{ row }">
                <DateText :value="row.sold_at" />
            </template>
            <template #cell(balance)="{ row }">
                <span class="font-semibold" :class="row.deleted ? 'text-muted line-through' : 'text-danger'"><MoneyText :value="row.balance" /></span>
            </template>
            <template #cell(actions)="{ row }">
                <div class="flex items-center justify-end gap-1 px-2">
                    <StatusBadge v-if="row.deleted" status="deleted" />
                    <template v-else>
                        <button v-if="canCollect" type="button" class="inline-flex h-6 items-center gap-1 rounded-md bg-primary px-2 text-xs font-medium text-primary-foreground hover:opacity-90" @click.stop="openCollect(row)">
                            <AppIcon name="money-bill-wave" size="11" />
                            {{ t('dues.collect') }}
                        </button>
                        <button
                            v-if="canDelete"
                            type="button"
                            class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-danger/10 hover:text-danger"
                            :aria-label="t('common.delete')"
                            :title="t('common.delete')"
                            @click.stop="removeDue(row)"
                        >
                            <AppIcon name="trash" size="12" />
                        </button>
                    </template>
                </div>
            </template>
        </DataTable>

        <OrderDetailPanel
            :order="selected"
            :methods="methods"
            :can-collect="canCollect"
            :can-edit="canEdit"
            :can-delete="canDelete"
            @close="selectedId = null"
            @collect="openCollect"
            @edit="editOrder"
            @print="printReceipt"
            @delete="removeDue"
        />

        <SlideOver :open="!! collecting" :title="t('dues.collect_title')" @close="collecting = null">
            <form v-if="collecting" novalidate class="space-y-3" @submit.prevent="submit">
                <p class="text-[13px] text-muted">{{ t('dues.collect_for', { invoice: collecting.invoice_no, balance: formatMoney(collecting.balance) }) }}</p>
                <p class="text-[13px] font-medium text-foreground">{{ collecting.customer?.name }} {{ collecting.customer?.mobile }}</p>

                <FormField :label="t('dues.method')" :error="form.errors.method">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.method" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                            <option v-for="entry in methods" :key="entry.method" :value="entry.method">{{ localized(entry, 'name') }}</option>
                        </select>
                    </template>
                </FormField>

                <FormField :label="t('dues.amount')" required :error="form.errors.amount">
                    <MoneyInput v-model="form.amount" />
                    <button type="button" class="mt-1 text-xs font-medium text-accent hover:underline" @click="form.amount = collecting.balance.toFixed(2)">{{ t('dues.full_balance') }}: {{ formatMoney(collecting.balance) }}</button>
                </FormField>

                <FormField v-if="form.method !== 'cash'" :label="t('dues.reference')" :error="form.errors.reference">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.reference" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                    </template>
                </FormField>

                <div v-if="collecting.collections.length">
                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ t('dues.collections') }}</h3>
                    <ul class="divide-y divide-border rounded-md border border-border text-[13px]">
                        <li v-for="(entry, index) in collecting.collections" :key="index" class="flex items-center justify-between px-2 py-1">
                            <span class="text-muted">{{ methodLabel(entry.method) }}</span>
                            <MoneyText :value="entry.amount" />
                        </li>
                    </ul>
                </div>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="collecting = null">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="form.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="submit">
                    <AppIcon v-if="form.processing" name="spinner" spin />
                    {{ t('dues.collect') }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
