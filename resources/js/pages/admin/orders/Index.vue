<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import MoneyText from '@/components/ui-kit/MoneyText.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    orders: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    canVoid: { type: Boolean, default: false },
    canApproveVoid: { type: Boolean, default: false },
});

const confirm = useConfirm();

const voidDialogOpen = ref(false);
const voidTarget = ref(null);
const voidReason = ref('');
const voidNote = ref('');

const columns = computed(() => [
    { key: 'created_at', label: t('ui_kit.created'), align: 'left', minWidth: '112px' },
    { key: 'invoice_no', label: t('inventory.invoice_no'), align: 'left', sortable: true },
    { key: 'order_type', label: t('orders.type_label'), align: 'left' },
    { key: 'table_name', label: t('receipt.table'), align: 'left' },
    { key: 'items_count', label: t('inventory.items_label'), type: 'number', minWidth: '64px' },
    { key: 'total_display', label: t('inventory.total'), type: 'money', sortable: true, minWidth: '96px' },
    { key: 'status', label: t('common.status'), minWidth: '104px' },
    { key: 'actions', label: '', align: 'right', type: 'component', width: '80px' },
]);

const rows = computed(() => props.orders.map((order) => ({
    ...order,
    table_name: order.table?.name,
    items_count: order.items?.length ?? 0,
    total_display: formatMoney(order.total),
    sort_total: Number(order.total),
    order_type: t('orders.type.' + order.order_type),
    raw_status: order.status,
    has_pending_void: order.void_request?.status === 'pending',
})));

const statusFilters = [
    { value: '', label: t('common.status') },
    { value: 'pending', label: t('status.pending') },
    { value: 'preparing', label: t('status.preparing') },
    { value: 'ready', label: t('status.ready') },
    { value: 'paid', label: t('status.paid') },
    { value: 'void', label: t('status.void') },
];

function setFilter(key, value) {
    router.get(route('admin.orders.index'), { ...props.filters, [key]: value }, { preserveState: true });
}

function openVoidDialog(order) {
    voidTarget.value = order;
    voidReason.value = '';
    voidNote.value = '';
    voidDialogOpen.value = true;
}

function submitVoidRequest() {
    router.post(route('admin.orders.void-request', voidTarget.value.id), {
        reason_code: voidReason.value,
        note: voidNote.value || null,
    }, {
        preserveState: true,
        onSuccess: () => {
            voidDialogOpen.value = false;
            notify.success(t('orders.void_requested'));
        },
        onError: () => notify.error(t('common.error_occurred')),
    });
}

async function approveVoid(order) {
    const result = await confirm({
        variant: 'danger',
        level: 'reason',
        title: t('orders.approve_void_title', { invoice: order.invoice_no }),
        message: t('orders.approve_void_message'),
        confirmText: t('orders.void_confirm'),
        reasons: ['wrong_item', 'customer_changed_mind', 'kitchen_mistake', 'duplicate_order', 'payment_failed', 'other'],
        facts: [
            { label: t('inventory.invoice_no'), value: order.invoice_no },
            { label: t('inventory.total'), value: formatMoney(order.total) },
        ],
    });

    if (! result.confirmed) {
        return;
    }

    router.post(route('admin.orders.approve-void', order.id), {
        reason_code: result.reason,
        note: result.note,
    }, {
        preserveState: true,
        onSuccess: () => notify.success(t('orders.voided')),
        onError: () => notify.error(t('common.error_occurred')),
    });
}

function printReceipt(order) {
    window.open(route('admin.orders.receipt', order.id), '_blank', 'width=380,height=640');
}

function pay(order) {
    router.post(route('admin.orders.pay', order.id), {
        tendered: order.total,
    }, {
        preserveState: true,
        onSuccess: () => notify.success(t('orders.paid_success')),
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
                :class="(filters.status ?? '') === filter.value ? 'border-accent bg-accent/15 text-accent-foreground' : 'border-border bg-surface text-muted hover:text-foreground'"
                @click="setFilter('status', filter.value)"
            >
                {{ filter.label }}
            </button>
        </div>

        <DataTable :columns="columns" :rows="rows" :clickable="false" :empty-title="t('orders.no_orders')">
            <template #cell(created_at)="{ value }">
                <DateText :value="value" relative />
            </template>
            <template #cell(total_display)="{ row }">
                <MoneyText :value="row.total" :negative-danger="true" />
            </template>
            <template #cell(status)="{ row }">
                <div class="flex items-center gap-1">
                    <StatusBadge :status="row.raw_status" />
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
                        @click="pay(row)"
                    >
                        <AppIcon name="money-bill-wave" size="10" />
                        {{ t('orders.pay') }}
                    </button>
                    <button
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-background hover:text-foreground"
                        :aria-label="t('receipt.print')"
                        @click="printReceipt(row)"
                    >
                        <AppIcon name="print" size="12" />
                    </button>
                    <button
                        v-if="canVoid && row.raw_status !== 'void'"
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-danger/10 hover:text-danger"
                        :aria-label="t('orders.void_confirm')"
                        @click="row.has_pending_void && canApproveVoid ? approveVoid(row) : openVoidDialog(row)"
                    >
                        <AppIcon name="circle-xmark" size="12" />
                    </button>
                </div>
            </template>
        </DataTable>

        <!-- Void request dialog -->
        <Teleport to="body">
            <div v-if="voidDialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-md rounded-xl border border-border bg-surface p-4">
                    <h2 class="text-base font-semibold text-foreground">{{ t('orders.void_title', { invoice: voidTarget?.invoice_no }) }}</h2>
                    <p class="mt-1 text-[13px] text-muted">{{ t('orders.void_message') }}</p>

                    <div class="mt-3 space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-foreground" for="void-reason">{{ t('inventory.reason') }} <span class="text-danger">*</span></label>
                            <select id="void-reason" v-model="voidReason" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm text-foreground">
                                <option value="" disabled>{{ t('common.select_reason') }}</option>
                                <option value="wrong_item">{{ t('reasons.wrong_item') }}</option>
                                <option value="customer_changed_mind">{{ t('reasons.customer_changed_mind') }}</option>
                                <option value="kitchen_mistake">{{ t('reasons.kitchen_mistake') }}</option>
                                <option value="duplicate_order">{{ t('orders.reason_duplicate') }}</option>
                                <option value="payment_failed">{{ t('orders.reason_payment_failed') }}</option>
                                <option value="other">{{ t('reasons.other') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-foreground" for="void-note">{{ t('common.note') }}</label>
                            <textarea id="void-note" v-model="voidNote" rows="2" class="w-full resize-y rounded-md border border-border bg-background px-2 py-1 text-sm text-foreground" />
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="h-9 rounded-md border border-border px-3 text-sm text-foreground hover:bg-background" @click="voidDialogOpen = false">{{ t('common.cancel') }}</button>
                        <button
                            type="button"
                            :disabled="! voidReason"
                            class="h-9 rounded-lg bg-danger px-3 text-sm font-semibold text-white hover:bg-danger/90 disabled:opacity-50"
                            @click="submitVoidRequest"
                        >
                            {{ t('orders.send_request') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
