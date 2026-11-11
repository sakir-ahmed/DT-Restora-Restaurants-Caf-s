<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ChannelBadge from '@/components/ChannelBadge.vue';
import OrderJourney from '@/components/admin/OrderJourney.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import MoneyText from '@/components/ui-kit/MoneyText.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { t } from '@/lib/i18n';
import { localized } from '@/lib/site';

// One order, in full, with the actions that make sense for its state.
// The page decides what each action does; this panel only shows and emits.
const props = defineProps({
    order: { type: Object, default: null },
    methods: { type: Array, default: () => [] },
    canPay: { type: Boolean, default: false },
    canCollect: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    canApprovePayments: { type: Boolean, default: false },
    canChangeStatus: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'pay', 'collect', 'edit', 'print', 'delete', 'approve', 'status', 'due']);

const status = computed(() => props.order?.status ?? '');
const isVoid = computed(() => status.value === 'void' || props.order?.deleted === true);
const isOpen = computed(() => ! isVoid.value && status.value !== 'paid' && ! props.order?.is_due);
// Every order that is not deleted: open ones with no payment yet, and any paid or due sale (its payments are kept).
const isEditable = computed(() => ! isVoid.value && (
    (isOpen.value && (props.order?.payments ?? []).length === 0)
    || status.value === 'paid'
));
const hasDue = computed(() => ! isVoid.value && !! props.order?.is_due && Number(props.order?.balance ?? 0) > 0);
const pendingPayment = computed(() => (props.order?.payments ?? []).find((payment) => payment.status === 'pending_verification') ?? null);

const charges = computed(() => {
    const order = props.order;

    if (! order) {
        return [];
    }

    return [
        { label: t('receipt.subtotal'), value: order.subtotal },
        { label: t('receipt.discount'), value: -Number(order.discount_amount ?? 0), skipZero: true },
        { label: t('receipt.service_charge'), value: order.service_charge, skipZero: true },
        { label: t('receipt.other_charge'), value: order.extra_charge, skipZero: true },
        { label: t('receipt.vat'), value: order.vat_amount, skipZero: true },
        { label: t('orders.delivery_fee'), value: order.delivery_fee, skipZero: true },
        { label: t('receipt.rounding'), value: order.rounding, skipZero: true },
    ].filter((line) => ! (line.skipZero && Number(line.value ?? 0) === 0));
});

function methodLabel(code) {
    const entry = props.methods.find((method) => method.method === code);

    return entry ? localized(entry, 'name') : code;
}

function itemName(item) {
    return localized(item, 'name') || item.name;
}

function addOnText(item) {
    return (item.add_ons ?? []).map((addOn) => (addOn.qty > 1 ? `${addOn.name} x${addOn.qty}` : addOn.name)).join(', ');
}

const buttonBase = 'inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-[13px] font-medium disabled:opacity-60';
</script>

<template>
    <SlideOver :open="!! order" :title="order ? t('orders.details_title', { invoice: order.invoice_no }) : ''" @close="emit('close')">
        <div v-if="order" class="space-y-4 text-[13px]">
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="order.order_status ?? (isVoid ? 'deleted' : status)" :title="t('orders.order_status')" />
                <StatusBadge v-if="order.payment_status" :status="order.payment_status" :title="t('orders.payment_status')" />
                <ChannelBadge v-if="order.channel && (order.channel !== 'pos' || order.aggregator_channel)" :channel="order.channel" :aggregator="order.aggregator_channel" />
                <span v-else-if="order.channel" class="text-muted">{{ t('orders.channel_pos') }}</span>
                <span v-if="order.order_type" class="rounded-full border border-border px-2 py-0.5 text-xs text-muted">{{ t('orders.type.' + order.order_type) }}</span>
            </div>

            <OrderJourney :order="order" :can-change="canChangeStatus" @status="(target, next) => emit('status', target, next)" />

            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 rounded-md border border-border p-3">
                <div v-if="order.created_at">
                    <dt class="text-xs text-muted">{{ t('orders.created_at') }}</dt>
                    <dd><DateText :value="order.created_at" /></dd>
                </div>
                <div v-if="order.table_name">
                    <dt class="text-xs text-muted">{{ t('receipt.table') }}</dt>
                    <dd class="font-medium">{{ order.table_name }}</dd>
                </div>
                <div v-if="order.customer">
                    <dt class="text-xs text-muted">{{ t('dues.customer') }}</dt>
                    <dd class="font-medium">{{ order.customer.name }}</dd>
                    <dd class="text-xs text-muted">{{ order.customer.mobile }}</dd>
                </div>
                <div v-if="order.cashier_name">
                    <dt class="text-xs text-muted">{{ t('receipt.served_by') }}</dt>
                    <dd class="font-medium">{{ order.cashier_name }}</dd>
                </div>
                <div v-if="order.delivery_address" class="col-span-2">
                    <dt class="text-xs text-muted">{{ t('orders.type.delivery') }}</dt>
                    <dd>{{ order.delivery_address }}</dd>
                </div>
                <div v-if="order.note" class="col-span-2">
                    <dt class="text-xs text-muted">{{ t('common.note') }}</dt>
                    <dd>{{ order.note }}</dd>
                </div>
            </dl>

            <section>
                <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ t('inventory.items_label') }}</h3>
                <ul class="divide-y divide-border rounded-md border border-border">
                    <li v-for="(item, index) in order.items" :key="item.id ?? index" class="flex items-start justify-between gap-3 px-2 py-1.5">
                        <div class="min-w-0">
                            <p class="font-medium">{{ item.quantity }} x {{ itemName(item) }}</p>
                            <p v-if="addOnText(item)" class="text-xs text-muted">{{ addOnText(item) }}</p>
                            <p class="text-xs text-muted"><MoneyText :value="item.unit_price" /></p>
                        </div>
                        <MoneyText :value="item.line_total" class="shrink-0 font-medium" />
                    </li>
                </ul>
            </section>

            <section class="space-y-1 rounded-md border border-border p-3">
                <div v-for="line in charges" :key="line.label" class="flex items-center justify-between">
                    <span class="text-muted">{{ line.label }}</span>
                    <MoneyText :value="line.value" />
                </div>
                <div class="flex items-center justify-between border-t border-border pt-1 text-sm font-semibold">
                    <span>{{ t('receipt.grand_total') }}</span>
                    <MoneyText :value="order.total" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted">{{ t('dues.paid') }}</span>
                    <MoneyText :value="order.paid ?? 0" />
                </div>
                <div v-if="Number(order.balance ?? 0) > 0" class="flex items-center justify-between font-semibold text-danger">
                    <span>{{ t('dues.balance') }}</span>
                    <MoneyText :value="order.balance" />
                </div>
            </section>

            <section>
                <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ t('orders.payments_heading') }}</h3>
                <ul v-if="(order.payments ?? []).length" class="divide-y divide-border rounded-md border border-border">
                    <li v-for="(payment, index) in order.payments" :key="payment.id ?? index" class="flex items-center justify-between gap-3 px-2 py-1.5">
                        <div class="min-w-0">
                            <p class="font-medium">{{ methodLabel(payment.method) }}</p>
                            <p class="text-xs text-muted">
                                <DateText v-if="payment.created_at ?? payment.at" :value="payment.created_at ?? payment.at" />
                                <span v-if="payment.is_due_collection"> / {{ t('orders.due_collection') }}</span>
                                <span v-if="payment.status && payment.status !== 'completed'"> / {{ payment.status }}</span>
                            </p>
                        </div>
                        <MoneyText :value="payment.amount" class="shrink-0" />
                    </li>
                </ul>
                <p v-else class="text-muted">{{ t('orders.no_payments') }}</p>
            </section>
        </div>

        <template v-if="order" #footer>
            <div class="flex w-full flex-wrap items-center justify-end gap-2">
                <button v-if="canDelete && ! isVoid" type="button" :class="[buttonBase, 'mr-auto border border-danger/50 text-danger hover:bg-danger/10']" @click="emit('delete', order)">
                    <AppIcon name="trash" size="12" />
                    {{ t('common.delete') }}
                </button>
                <button type="button" :class="[buttonBase, 'border border-border bg-surface text-foreground hover:bg-background']" @click="emit('print', order)">
                    <AppIcon name="print" size="12" />
                    {{ t('receipt.print') }}
                </button>
                <button v-if="canEdit && isEditable" type="button" :class="[buttonBase, 'border border-border bg-surface text-foreground hover:bg-background']" @click="emit('edit', order)">
                    <AppIcon name="pen-to-square" size="12" />
                    {{ t('common.edit') }}
                </button>
                <button v-if="canApprovePayments && pendingPayment" type="button" :class="[buttonBase, 'border border-warning/60 text-warning hover:bg-warning/10']" @click="emit('approve', pendingPayment)">
                    <AppIcon name="user-shield" size="12" />
                    {{ t('payments.verify') }}
                </button>
                <button v-if="canPay && isOpen && order.customer" type="button" :class="[buttonBase, 'border border-warning/60 text-warning hover:bg-warning/10']" @click="emit('due', order)">
                    <AppIcon name="hand-holding-dollar" size="12" />
                    {{ t('orders.put_on_due') }}
                </button>
                <button v-if="canPay && isOpen" type="button" :class="[buttonBase, 'bg-primary text-primary-foreground hover:opacity-90']" @click="emit('pay', order)">
                    <AppIcon name="money-bill-wave" size="12" />
                    {{ t('orders.pay') }}
                </button>
                <button v-if="canCollect && hasDue" type="button" :class="[buttonBase, 'bg-primary text-primary-foreground hover:opacity-90']" @click="emit('collect', order)">
                    <AppIcon name="money-bill-wave" size="12" />
                    {{ t('dues.collect') }}
                </button>
            </div>
        </template>
    </SlideOver>
</template>
