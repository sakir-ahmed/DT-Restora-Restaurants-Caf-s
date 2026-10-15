<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    channels: { type: Array, required: true },
    orders: { type: Array, required: true },
    commissionTotals: { type: Object, required: true },
    webhookBase: { type: String, required: true },
    branches: { type: Array, required: true },
    items: { type: Array, required: true },
});

const channelOpen = ref(false);
const editingChannelId = ref(null);
const orderOpen = ref(false);
const payoutChannel = ref(null);
const payoutOpen = ref(false);

const orderColumns = computed(() => [
    { key: 'created_at', label: t('ui_kit.created'), align: 'left', minWidth: '112px' },
    { key: 'invoice_no', label: t('inventory.invoice_no'), align: 'left' },
    { key: 'channel_name', label: t('aggregators.channel_label'), align: 'left' },
    { key: 'platform_id', label: t('aggregators.platform_order_id'), align: 'left' },
    { key: 'total', label: t('inventory.total'), type: 'money', minWidth: '96px' },
    { key: 'commission', label: t('aggregators.commission'), type: 'money', minWidth: '96px' },
    { key: 'status', label: t('common.status'), minWidth: '104px' },
]);

const orderRows = computed(() => props.orders.map((order) => ({
    ...order,
    channel_name: order.aggregator_channel?.name,
    platform_id: order.aggregator_order_id,
    commission: formatMoney(order.commission_amount),
    sort_total: Number(order.total),
})));

const emptyChannel = () => ({ key: '', name: '', commission_percent: '0', webhook_secret: '', is_active: true });

const channelForm = useForm(emptyChannel());

function openChannel(channel = null) {
    editingChannelId.value = channel?.id ?? null;
    channelForm.defaults(channel ? {
        key: channel.key,
        name: channel.name,
        commission_percent: String(channel.commission_percent),
        webhook_secret: channel.webhook_secret ?? '',
        is_active: channel.is_active,
    } : emptyChannel()).reset();
    channelOpen.value = true;
}

function saveChannel() {
    const payload = { ...channelForm.data() };

    const options = {
        onSuccess: () => {
            channelOpen.value = false;
            notify.success(editingChannelId.value === null ? t('aggregators.channel_created') : t('aggregators.channel_updated'));
        },
    };

    if (editingChannelId.value === null) {
        channelForm.post(route('aggregators.channels.store'), { ...options, data: payload });
    } else {
        channelForm.put(route('aggregators.channels.update', editingChannelId.value), { ...options, data: payload });
    }
}

const emptyOrder = () => ({
    aggregator_channel_id: '', platform_order_id: '', branch_id: '',
    customer_name: '', customer_mobile: '', address: '', note: '',
    items: [],
});

const orderForm = useForm(emptyOrder());

function openOrder() {
    orderForm.defaults({
        ...emptyOrder(),
        aggregator_channel_id: props.channels[0]?.id ?? '',
        branch_id: props.branches[0]?.id ?? '',
    }).reset();
    orderForm.items = [];
    orderOpen.value = true;
}

function addLine() {
    orderForm.items.push({ menu_item_id: '', quantity: 1 });
}

function saveOrder() {
    orderForm.post(route('aggregators.orders.manual'), {
        onSuccess: () => {
            orderOpen.value = false;
            notify.success(t('aggregators.order_created'));
        },
        onError: () => notify.error(t('common.error_occurred')),
    });
}

const payoutForm = useForm({ period_from: '', period_to: '', received_amount: '0.00' });

function openPayout(channel) {
    payoutChannel.value = channel;
    payoutForm.defaults({
        period_from: now().slice(0, 7) + '-01',
        period_to: now().slice(0, 10),
        received_amount: '0.00',
    }).reset();
    payoutOpen.value = true;
}

function now() {
    return new Date().toISOString();
}

function savePayout() {
    router.post(route('aggregators.payouts', payoutChannel.value.id), payoutForm.data(), {
        onSuccess: () => {
            payoutOpen.value = false;
            notify.success(t('aggregators.payout_recorded'));
        },
    });
}

function syncMenu(channel) {
    router.post(route('aggregators.menu-sync', channel.id), {}, {
        onSuccess: () => notify.success(t('aggregators.menu_synced', { count: 0 })),
    });
}

function decide(order, decision) {
    router.post(route('aggregators.orders.decision', order.id), { decision }, {
        onSuccess: () => notify.success(t('aggregators.decision_pushed')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('aggregators.title')" :description="t('aggregators.subtitle')">
            <template #actions>
                <span class="text-[13px] text-muted">{{ t('aggregators.commission_month') }}: {{ formatMoney(commissionTotals.month) }}</span>
                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background" @click="openChannel()">
                    <AppIcon name="plus" size="12" />
                    {{ t('aggregators.add_channel') }}
                </button>
                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90" @click="openOrder">
                    <AppIcon name="receipt" size="12" />
                    {{ t('aggregators.manual_order') }}
                </button>
            </template>
        </PageHeader>

        <!-- Channels -->
        <section class="mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="channel in channels" :key="channel.id" class="rounded-lg border border-border bg-surface p-3">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold">{{ channel.name }}</p>
                        <p class="text-xs text-muted">{{ webhookBase }}/{{ channel.key }}</p>
                    </div>
                    <StatusBadge :status="channel.is_active ? 'active' : 'inactive'" />
                </div>

                <p class="mt-2 text-[13px] text-muted">
                    {{ t('aggregators.commission') }}:
                    <span class="font-semibold text-foreground tabular-nums">{{ Number(channel.commission_percent).toFixed(2) }}%</span>
                </p>

                <div class="mt-2 flex gap-1.5">
                    <button type="button" class="h-6 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="openChannel(channel)">{{ t('common.edit') }}</button>
                    <button type="button" class="h-6 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="syncMenu(channel)">{{ t('aggregators.sync_menu') }}</button>
                    <button type="button" class="h-6 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="openPayout(channel)">{{ t('aggregators.payout') }}</button>
                </div>
            </article>
        </section>

        <DataTable :columns="orderColumns" :rows="orderRows" searchable :empty-title="t('aggregators.no_orders')">
            <template #cell(created_at)="{ value }">
                <DateText :value="value" relative />
            </template>
            <template #cell(total)="{ value }">
                {{ formatMoney(value) }}
            </template>
            <template #cell(status)="{ value }">
                <StatusBadge :status="value" />
            </template>
        </DataTable>

        <!-- Channel dialog -->
        <SlideOver :open="channelOpen" :title="editingChannelId === null ? t('aggregators.add_channel') : t('common.edit')" @close="channelOpen = false">
            <form class="space-y-3" @submit.prevent="saveChannel">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField v-if="editingChannelId === null" :label="t('aggregators.channel_key')" required :error="channelForm.errors.key" :helper="t('aggregators.channel_key_helper')">
                        <template #default="{ id }">
                            <input :id="id" v-model="channelForm.key" type="text" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px]" :class="channelForm.errors.key ? 'border-danger' : 'border-border'">
                        </template>
                    </FormField>

                    <FormField :label="t('aggregators.channel_name')" required :error="channelForm.errors.name">
                        <template #default="{ id }">
                            <input :id="id" v-model="channelForm.name" type="text" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px]" :class="channelForm.errors.name ? 'border-danger' : 'border-border'">
                        </template>
                    </FormField>

                    <FormField :label="t('aggregators.commission_percent')" required :error="channelForm.errors.commission_percent">
                        <MoneyInput v-model="channelForm.commission_percent" prefix="%" />
                    </FormField>

                    <FormField :label="t('aggregators.webhook_secret')" :helper="t('aggregators.webhook_secret_helper')" :error="channelForm.errors.webhook_secret">
                        <template #default="{ id }">
                            <input :id="id" v-model="channelForm.webhook_secret" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 font-mono text-[13px]">
                        </template>
                    </FormField>
                </div>

                <SwitchField v-model="channelForm.is_active" :label="t('branches.is_active')" />
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px]" @click="channelOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="channelForm.processing" class="h-7 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground" @click="saveChannel">{{ t('common.save') }}</button>
            </template>
        </SlideOver>

        <!-- Manual order dialog -->
        <SlideOver :open="orderOpen" :title="t('aggregators.manual_order')" :subtitle="t('aggregators.manual_order_hint')" wide @close="orderOpen = false">
            <form class="space-y-3" @submit.prevent="saveOrder">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('aggregators.channel_label')" required :error="orderForm.errors.aggregator_channel_id">
                        <template #default="{ id }">
                            <select :id="id" v-model="orderForm.aggregator_channel_id" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px]" :class="orderForm.errors.aggregator_channel_id ? 'border-danger' : 'border-border'">
                                <option v-for="channel in channels" :key="channel.id" :value="channel.id">{{ channel.name }}</option>
                            </select>
                        </template>
                    </FormField>

                    <FormField :label="t('aggregators.platform_order_id')" required :error="orderForm.errors.platform_order_id">
                        <template #default="{ id }">
                            <input :id="id" v-model="orderForm.platform_order_id" type="text" class="h-7 w-full rounded-md border bg-surface px-2 font-mono text-[13px]" :class="orderForm.errors.platform_order_id ? 'border-danger' : 'border-border'">
                        </template>
                    </FormField>

                    <FormField :label="t('nav.branch')" required :error="orderForm.errors.branch_id">
                        <template #default="{ id }">
                            <select :id="id" v-model="orderForm.branch_id" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px]" :class="orderForm.errors.branch_id ? 'border-danger' : 'border-border'">
                                <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.code }} - {{ branch.name }}</option>
                            </select>
                        </template>
                    </FormField>

                    <FormField :label="t('staff.name')">
                        <template #default="{ id }">
                            <input :id="id" v-model="orderForm.customer_name" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.mobile')">
                        <MobileInput v-model="orderForm.customer_mobile" />
                    </FormField>

                    <div class="col-span-2">
                        <FormField :label="t('web.address')">
                            <template #default="{ id }">
                                <input :id="id" v-model="orderForm.address" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                            </template>
                        </FormField>
                    </div>
                </div>

                <div class="border-t border-border pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted">{{ t('inventory.items_label') }}</h3>
                        <button type="button" class="inline-flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px]" @click="addLine">
                            <AppIcon name="plus" size="10" />
                            {{ t('inventory.add_line') }}
                        </button>
                    </div>

                    <div v-for="(line, index) in orderForm.items" :key="index" class="mb-2 grid grid-cols-[1fr_80px_28px] items-center gap-2">
                        <select v-model="line.menu_item_id" class="h-7 rounded-md border border-border bg-surface px-1.5 text-[13px]">
                            <option value="" disabled>{{ t('common.select') }}</option>
                            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                        <input v-model.number="line.quantity" type="number" min="1" class="h-7 rounded-md border border-border bg-surface px-1.5 text-right text-[13px] tabular-nums">
                        <button type="button" class="flex h-6 w-6 items-center justify-center rounded text-muted hover:text-danger" @click="orderForm.items.splice(index, 1)">
                            <AppIcon name="xmark" size="12" />
                        </button>
                    </div>
                </div>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px]" @click="orderOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="orderForm.processing || orderForm.items.length === 0" class="h-7 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground" @click="saveOrder">{{ t('common.save') }}</button>
            </template>
        </SlideOver>

        <!-- Payout dialog -->
        <Teleport to="body">
            <div v-if="payoutOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="payoutOpen = false">
                <div class="w-full max-w-sm rounded-xl border border-border bg-surface p-4">
                    <h2 class="text-base font-semibold">{{ t('aggregators.payout') }} - {{ payoutChannel?.name }}</h2>

                    <div class="mt-3 space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <FormField :label="t('aggregators.period_from')" required>
                                <template #default="{ id }">
                                    <input :id="id" v-model="payoutForm.period_from" type="date" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm">
                                </template>
                            </FormField>
                            <FormField :label="t('aggregators.period_to')" required>
                                <template #default="{ id }">
                                    <input :id="id" v-model="payoutForm.period_to" type="date" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm">
                                </template>
                            </FormField>
                        </div>

                        <FormField :label="t('aggregators.received_amount')" required>
                            <MoneyInput v-model="payoutForm.received_amount" />
                        </FormField>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="h-9 rounded-md border border-border px-3 text-sm" @click="payoutOpen = false">{{ t('common.cancel') }}</button>
                        <button type="button" class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground" @click="savePayout">{{ t('common.save') }}</button>
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
