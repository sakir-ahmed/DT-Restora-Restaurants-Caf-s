<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import OrderDetailPanel from '@/components/admin/OrderDetailPanel.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import MoneyText from '@/components/ui-kit/MoneyText.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import ChannelBadge from '@/components/ChannelBadge.vue';
import { notify } from '@/lib/notify';
import { printUrl } from '@/lib/print';

const props = defineProps({
    orders: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    canVoid: { type: Boolean, default: false },
    canApproveVoid: { type: Boolean, default: false },
    canApprovePayments: { type: Boolean, default: false },
    canPay: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
});

const selectedId = ref(null);

const confirm = useConfirm();

const columns = computed(() => [
    { key: 'created_at', label: t('ui_kit.created'), align: 'left', minWidth: '112px' },
    { key: 'invoice_no', label: t('inventory.invoice_no'), align: 'left', sortable: true },
    { key: 'channel', label: t('orders.channel_label'), align: 'left', minWidth: '96px' },
    { key: 'order_type', label: t('orders.type_label'), align: 'left' },
    { key: 'table_name', label: t('receipt.table'), align: 'left' },
    { key: 'items_count', label: t('inventory.items_label'), type: 'number', minWidth: '64px' },
    { key: 'total_display', label: t('inventory.total'), type: 'money', sortable: true, minWidth: '96px' },
    { key: 'order_status', label: t('orders.order_status'), minWidth: '104px' },
    { key: 'payment_status', label: t('orders.payment_status'), minWidth: '104px' },
    { key: 'actions', label: '', align: 'right', type: 'component', width: '80px' },
]);

function receivedAmount(order) {
    return (order.payments ?? [])
        .filter((payment) => payment.status === 'completed')
        .reduce((sum, payment) => sum + Number(payment.amount), 0);
}

// Where the kitchen has the order. It is kept apart from payment, so an order paid first still shows its kitchen step.
function orderStatusOf(order) {
    if (order.status === 'void') {
        return 'void';
    }

    return order.kitchen_status ?? (order.status === 'paid' ? 'served' : order.status);
}

// Whether the money is in: deleted, due (balance to collect), paid, part paid by booking money, or unpaid.
function paymentStatusOf(order) {
    if (order.status === 'void') {
        return 'deleted';
    }

    if (order.is_due) {
        return 'due';
    }

    if (order.status === 'paid') {
        return 'paid';
    }

    return receivedAmount(order) > 0 ? 'partial' : 'unpaid';
}

const rows = computed(() => props.orders.map((order) => ({
    ...order,
    order_status: orderStatusOf(order),
    payment_status: paymentStatusOf(order),
    table_name: order.table?.name,
    items_count: order.items?.length ?? 0,
    total_display: formatMoney(order.total),
    sort_total: Number(order.total),
    order_type: t('orders.type.' + order.order_type),
    raw_status: order.status,
    has_pending_void: order.void_request?.status === 'pending',
})));

const selected = computed(() => {
    const order = props.orders.find((entry) => entry.id === selectedId.value);

    if (! order) {
        return null;
    }

    const paid = receivedAmount(order);

    return {
        ...order,
        order_status: orderStatusOf(order),
        payment_status: paymentStatusOf(order),
        table_name: order.table?.name,
        cashier_name: order.cashier?.name,
        deleted: order.status === 'void',
        paid,
        balance: order.status === 'void' || (order.status === 'paid' && ! order.is_due) ? 0 : Math.max(0, Number(order.total) - paid),
    };
});

const statusFilters = [
    { value: '', label: t('common.status') },
    { value: 'pending', label: t('status.pending') },
    { value: 'preparing', label: t('status.preparing') },
    { value: 'ready', label: t('status.ready') },
    { value: 'served', label: t('status.served') },
    { value: 'paid', label: t('status.paid') },
    { value: 'void', label: t('status.void') },
];

function setFilter(key, value) {
    router.get(route('admin.orders.index'), { ...props.filters, [key]: value }, { preserveState: true });
}

async function deleteOrder(order) {
    const result = await confirm({
        variant: 'danger',
        level: 'reason',
        title: t('orders.delete_title', { invoice: order.invoice_no }),
        message: t('orders.delete_restore_message'),
        confirmText: t('orders.delete_order'),
        reasons: ['wrong_item', 'customer_changed_mind', 'kitchen_mistake', 'duplicate_order', 'payment_failed', 'other'],
        facts: [
            { label: t('inventory.invoice_no'), value: order.invoice_no },
            { label: t('inventory.total'), value: formatMoney(order.total) },
        ],
    });

    if (! result.confirmed) {
        return;
    }

    router.post(route('admin.orders.delete', order.id), {
        reason_code: result.reason,
        note: result.note ?? null,
    }, {
        preserveState: true,
        onSuccess: () => {
            selectedId.value = null;
            notify.success(t('orders.deleted'));
        },
        onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
    });
}

function approvePayment(payment) {
    router.post(route('admin.payments.approve', payment.id), {}, {
        preserveState: true,
        onSuccess: () => notify.success(t('payments.manual_approved')),
    });
}

function printReceipt(order) {
    printUrl(route('admin.orders.receipt', order.id));
}

const KITCHEN_STEPS = ['pending', 'preparing', 'ready', 'served'];

async function changeStatus(order, status) {
    // Going back is allowed, but it is asked about first: the kitchen and the table see the order change.
    if (KITCHEN_STEPS.indexOf(status) < KITCHEN_STEPS.indexOf(order.order_status)) {
        const result = await confirm({
            variant: 'warning',
            level: 'simple',
            title: t('orders.move_back_title', { invoice: order.invoice_no, status: t('status.' + status) }),
            message: t('orders.move_back_message'),
            confirmText: t('orders.move_back', { status: t('status.' + status) }),
            facts: [
                { label: t('orders.order_status'), value: t('status.' + order.order_status) + ' > ' + t('status.' + status) },
            ],
        });

        if (! result.confirmed) {
            return;
        }
    }

    router.patch(route('admin.orders.status', order.id), { status }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            if (! page.props.flash?.error) {
                notify.success(t('orders.status_updated'));
            }
        },
        onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
    });
}

function editOrder(order) {
    router.get(route('admin.pos.index'), { edit: order.id });
}

async function pay(order) {
    const result = await confirm({
        variant: 'info',
        level: 'simple',
        title: t('orders.pay_confirm_title', { invoice: order.invoice_no }),
        message: t('orders.pay_confirm_message'),
        confirmText: t('orders.pay'),
        facts: [
            { label: t('inventory.invoice_no'), value: order.invoice_no },
            { label: t('inventory.total'), value: formatMoney(order.total) },
        ],
    });

    if (! result.confirmed) {
        return;
    }

    router.post(route('admin.orders.pay', order.id), {
        tendered: order.total,
    }, {
        preserveState: true,
        onSuccess: (page) => {
            if (page.props.flash?.error) {
                notify.error(String(page.props.flash.error));

                return;
            }

            notify.success(t('orders.paid_success'));
        },
        onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
    });
}

async function putOnDue(order) {
    const result = await confirm({
        variant: 'warning',
        level: 'simple',
        title: t('orders.due_confirm_title', { invoice: order.invoice_no }),
        message: t('orders.due_confirm_message'),
        confirmText: t('orders.put_on_due'),
        facts: [
            { label: t('dues.customer'), value: [order.customer?.name, order.customer?.mobile].filter(Boolean).join(' ') },
            { label: t('inventory.total'), value: formatMoney(order.total) },
        ],
    });

    if (! result.confirmed) {
        return;
    }

    router.post(route('admin.orders.due', order.id), { lines: [] }, {
        preserveState: true,
        onSuccess: (page) => {
            if (page.props.flash?.error) {
                notify.error(String(page.props.flash.error));

                return;
            }

            notify.success(t('orders.due_recorded', { due: formatMoney(order.total) }));
        },
        onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('nav.orders')" :description="t('orders.subtitle')">
            <template #actions>
                <a
                    href="/admin/pos"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                >
                    <AppIcon name="cash-register" size="12" />
                    {{ t('nav.pos') }}
                </a>
            </template>
        </PageHeader>

        <div class="mb-3 flex flex-wrap items-center gap-1.5">
            <button
                v-for="filter in statusFilters"
                :key="filter.value"
                type="button"
                class="h-7 rounded-full border px-2.5 text-xs font-medium"
                :class="(filters.status ?? '') === filter.value ? 'border-accent bg-accent/15 text-primary' : 'border-border bg-surface text-muted hover:text-foreground'"
                @click="setFilter('status', filter.value)"
            >
                {{ filter.label }}
            </button>
        </div>

        <DataTable :columns="columns" :rows="rows" :empty-title="t('orders.no_orders')" clickable @row-click="(row) => selectedId = row.id">
            <template #cell(created_at)="{ value }">
                <DateText :value="value" relative />
            </template>
            <template #cell(channel)="{ row }">
                <ChannelBadge v-if="row.channel !== 'pos' || row.aggregator_channel" :channel="row.channel" :aggregator="row.aggregator_channel" />
                <span v-else class="text-muted">{{ t('orders.channel_pos') }}</span>
            </template>
            <template #cell(total_display)="{ row }">
                <MoneyText :value="row.total" :negative-danger="true" />
            </template>
            <template #cell(order_status)="{ row }">
                <StatusBadge :status="row.order_status" />
            </template>
            <template #cell(payment_status)="{ row }">
                <div class="flex items-center gap-1">
                    <StatusBadge :status="row.payment_status" />
                    <span v-if="row.has_pending_void" class="text-warning" :title="t('orders.void_pending')">
                        <AppIcon name="triangle-exclamation" size="12" />
                    </span>
                </div>
            </template>
            <template #cell(actions)="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <button
                        v-if="row.raw_status !== 'paid' && row.raw_status !== 'void'"
                        type="button"
                        class="flex h-6 items-center gap-1 rounded border border-success/50 px-1.5 text-[11px] text-success hover:bg-success/10"
                        @click.stop="pay(row)"
                    >
                        <AppIcon name="money-bill-wave" size="10" />
                        {{ t('orders.pay') }}
                    </button>
                    <button
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-background hover:text-foreground"
                        :aria-label="t('receipt.print')"
                        @click.stop="printReceipt(row)"
                    >
                        <AppIcon name="print" size="12" />
                    </button>
                    <button
                        v-if="canApprovePayments && (row.payments ?? []).some((p) => p.status === 'pending_verification')"
                        type="button"
                        class="flex h-6 items-center gap-1 rounded border border-warning/60 px-1.5 text-[11px] text-warning hover:bg-warning/10"
                        @click.stop="approvePayment((row.payments ?? []).find((p) => p.status === 'pending_verification'))"
                    >
                        <AppIcon name="user-shield" size="10" />
                        {{ t('payments.verify') }}
                    </button>
                    <button
                        v-if="canVoid && row.raw_status !== 'void'"
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-danger/10 hover:text-danger"
                        :aria-label="t('orders.delete_order')"
                        :title="t('orders.delete_order')"
                        @click.stop="deleteOrder(row)"
                    >
                        <AppIcon name="circle-xmark" size="12" />
                    </button>
                </div>
            </template>
        </DataTable>

        <OrderDetailPanel
            :order="selected"
            :can-pay="canPay"
            :can-edit="canEdit"
            :can-delete="canVoid"
            :can-approve-payments="canApprovePayments"
            :can-change-status="canPay"
            @close="selectedId = null"
            @pay="pay"
            @edit="editOrder"
            @print="printReceipt"
            @delete="deleteOrder"
            @approve="approvePayment"
            @status="changeStatus"
            @due="putOnDue"
        />
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
