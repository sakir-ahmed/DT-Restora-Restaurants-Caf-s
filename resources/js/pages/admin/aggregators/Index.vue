<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ChannelBadge from '@/components/ChannelBadge.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
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
const editingHasSecret = ref(false);
const editingFields = ref([]);
const editingChannel = ref(null);
const hasField = (field) => editingFields.value.includes(field);
const hasApiFields = computed(() => hasField('client_id'));
const apiReady = (channel) => channel.has_client_secret && (! channel.fields.includes('chain_id') || channel.chain_id);
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
    { key: 'actions', label: '', align: 'right', minWidth: '120px' },
]);

const orderRows = computed(() => props.orders.map((order) => ({
    ...order,
    channel_name: order.aggregator_channel?.name,
    platform_id: order.aggregator_order_id,
    commission: formatMoney(order.commission_amount),
    sort_total: Number(order.total),
})));

const emptyChannel = () => ({
    commission_percent: '0', webhook_secret: '',
    environment: 'sandbox', chain_id: '', client_id: '', client_secret: '', vendor_map: {},
});

const channelForm = useForm(emptyChannel());

function openChannel(channel = null) {
    editingChannelId.value = channel?.id ?? null;
    editingChannel.value = channel;
    editingFields.value = channel?.fields ?? [];
    channelForm.defaults(channel ? {
        commission_percent: String(channel.commission_percent),
        webhook_secret: channel.webhook_secret ?? '',
        environment: channel.environment ?? 'sandbox',
        chain_id: channel.chain_id ?? '',
        client_id: channel.client_id ?? '',
        client_secret: '',
        vendor_map: { ...(channel.vendor_map ?? {}) },
    } : emptyChannel()).reset();
    editingHasSecret.value = channel?.has_client_secret ?? false;
    channelOpen.value = true;
}

function saveChannel() {
    const fields = ['commission_percent', ...editingFields.value];
    const payload = Object.fromEntries(Object.entries(channelForm.data()).filter(([key]) => fields.includes(key)));

    channelForm.transform(() => payload).put(route('admin.aggregators.channels.update', editingChannelId.value), {
        onSuccess: () => {
            channelOpen.value = false;
            notify.success(t('aggregators.channel_updated'));
        },
    });
}

const copied = ref(false);

async function copyWebhook() {
    const url = `${props.webhookBase}/${editingChannel.value.key}`;

    try {
        await navigator.clipboard.writeText(url);
    } catch {
        const field = document.createElement('textarea');
        field.value = url;
        document.body.appendChild(field);
        field.select();
        document.execCommand('copy');
        field.remove();
    }

    copied.value = true;
    setTimeout(() => { copied.value = false; }, 1500);
}

function toggleChannel(channel) {
    router.post(route('admin.aggregators.channels.toggle', channel.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => reportFlash(page, ''),
    });
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
    orderForm.post(route('admin.aggregators.orders.manual'), {
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
    router.post(route('admin.aggregators.payouts', payoutChannel.value.id), payoutForm.data(), {
        onSuccess: () => {
            payoutOpen.value = false;
            notify.success(t('aggregators.payout_recorded'));
        },
    });
}

/**
 * Show the server's flash message: a platform failure comes back as an
 * error flash on a normal redirect.
 */
function reportFlash(page, fallback) {
    const flash = page.props.flash ?? {};

    if (flash.error) {
        notify.error(String(flash.error));

        return;
    }

    notify.success(String(flash.success ?? fallback));
}

function syncMenu(channel) {
    router.post(route('admin.aggregators.menu-sync', channel.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => reportFlash(page, t('aggregators.menu_synced', { count: 0 })),
    });
}

function testConnection(channel) {
    router.post(route('admin.aggregators.connection-test', channel.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => reportFlash(page, t('aggregators.connection_ok', { environment: channel.environment })),
    });
}

function importOrders(channel) {
    router.post(route('admin.aggregators.import-orders', channel.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => reportFlash(page, ''),
    });
}

// Outlet open / close on the platform
const outletOpen = ref(false);
const outletChannel = ref(null);
const outletForm = useForm({ branch_id: '', status: 'CLOSED_TODAY', closed_reason: 'TOO_BUSY_KITCHEN', closed_until: '' });
const closedReasons = ['TOO_BUSY_NO_DRIVERS', 'TOO_BUSY_KITCHEN', 'UPDATES_IN_MENU', 'TECHNICAL_PROBLEM', 'CLOSED', 'OTHER', 'BAD_WEATHER', 'HOLIDAY_SPECIAL_DAY'];
const mappedBranches = computed(() => props.branches.filter((branch) => outletChannel.value?.vendor_map?.[String(branch.id)]));

function openOutlet(channel) {
    outletChannel.value = channel;
    outletForm.defaults({
        branch_id: props.branches.find((branch) => channel.vendor_map?.[String(branch.id)])?.id ?? '',
        status: 'CLOSED_TODAY',
        closed_reason: 'TOO_BUSY_KITCHEN',
        closed_until: '',
    }).reset();
    outletOpen.value = true;
}

function saveOutlet() {
    outletForm.post(route('admin.aggregators.outlet-status', outletChannel.value.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            outletOpen.value = false;
            reportFlash(page, t('aggregators.outlet_updated'));
        },
    });
}

function decide(order, decision) {
    router.post(route('admin.aggregators.orders.decision', order.id), { decision }, {
        preserveScroll: true,
        onSuccess: (page) => reportFlash(page, t('aggregators.decision_pushed')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('aggregators.title')" :description="t('aggregators.subtitle')">
            <template #actions>
                <span class="text-[13px] text-muted">{{ t('aggregators.commission_month') }}: {{ formatMoney(commissionTotals.month) }}</span>
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
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-8 w-16 shrink-0 items-center justify-center rounded-md border border-border bg-white px-1.5">
                            <img v-if="channel.logo" :src="channel.logo" :alt="channel.name" class="max-h-5 w-full object-contain">
                            <ChannelBadge v-else channel="aggregator" :aggregator="channel" compact />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ channel.name }}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="channel.is_active"
                        :aria-label="channel.is_active ? t('aggregators.disable') : t('aggregators.enable')"
                        class="relative h-5 w-9 shrink-0 rounded-full transition-colors"
                        :class="channel.is_active ? 'bg-success' : 'bg-border'"
                        @click="toggleChannel(channel)"
                    >
                        <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all" :class="channel.is_active ? 'left-[18px]' : 'left-0.5'" />
                    </button>
                </div>

                <p class="mt-2 text-[13px] text-muted">
                    {{ t('aggregators.commission') }}:
                    <span class="font-semibold text-foreground tabular-nums">{{ Number(channel.commission_percent).toFixed(2) }}%</span>
                </p>

                <div v-if="channel.has_api" class="mt-2 space-y-1 text-xs">
                    <p class="flex items-center gap-1.5 text-muted">
                        <AppIcon :name="apiReady(channel) ? 'circle-check' : 'circle-xmark'" size="11" :class="apiReady(channel) ? 'text-success' : 'text-muted'" />
                        {{ apiReady(channel) ? t('aggregators.api_connected', { environment: channel.environment }) : t('aggregators.api_not_set') }}
                    </p>
                    <p v-if="channel.last_synced_at" class="text-muted">{{ t('aggregators.last_synced') }}: <DateText :value="channel.last_synced_at" relative /></p>
                    <p v-if="channel.last_error" class="break-words text-danger">{{ channel.last_error }}</p>
                </div>

                <div class="mt-2 flex flex-wrap gap-1.5">
                    <button type="button" class="h-6 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="openChannel(channel)">{{ t('common.edit') }}</button>
                    <button type="button" class="h-6 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="syncMenu(channel)">{{ t('aggregators.sync_menu') }}</button>
                    <button type="button" class="h-6 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="openPayout(channel)">{{ t('aggregators.payout') }}</button>
                    <template v-if="channel.has_api && channel.has_client_secret">
                        <button type="button" class="h-6 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="testConnection(channel)">{{ t('aggregators.test_connection') }}</button>
                        <button type="button" class="h-6 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="importOrders(channel)">{{ t('aggregators.import_orders') }}</button>
                        <button type="button" class="h-6 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="openOutlet(channel)">{{ t('aggregators.outlet_status') }}</button>
                    </template>
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
            <template #cell(actions)="{ row }">
                <button v-if="row.status === 'pending'" type="button" class="h-6 rounded border border-border px-1.5 text-[11px] text-danger hover:bg-background" @click="decide(row, 'reject')">{{ t('aggregators.reject') }}</button>
            </template>
        </DataTable>

        <!-- Channel dialog -->
        <SlideOver :open="channelOpen" :title="editingChannel ? editingChannel.name : ''" @close="channelOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="saveChannel">
                <FormField v-if="editingChannel" :label="t('aggregators.webhook_url')" :helper="t('aggregators.webhook_url_helper')">
                    <template #default="{ id }">
                        <div class="flex gap-1.5">
                            <input :id="id" :value="`${webhookBase}/${editingChannel.key}`" type="text" readonly class="h-7 min-w-0 flex-1 rounded-md border border-border bg-background px-2 font-mono text-[12px]" @focus="$event.target.select()">
                            <button type="button" class="inline-flex h-7 shrink-0 items-center gap-1 rounded-md border border-border px-2 text-[13px] hover:bg-background" @click="copyWebhook">
                                <AppIcon :name="copied ? 'check' : 'copy'" size="12" />
                                {{ copied ? t('aggregators.copied') : t('aggregators.copy') }}
                            </button>
                        </div>
                    </template>
                </FormField>

                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('aggregators.commission_percent')" required :error="channelForm.errors.commission_percent">
                        <MoneyInput v-model="channelForm.commission_percent" prefix="%" />
                    </FormField>

                    <FormField v-if="hasField('webhook_secret')" :label="t('aggregators.webhook_secret')" :helper="t('aggregators.webhook_secret_helper')" :error="channelForm.errors.webhook_secret">
                        <template #default="{ id }">
                            <input :id="id" v-model="channelForm.webhook_secret" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 font-mono text-[13px]">
                        </template>
                    </FormField>
                </div>

                <section v-if="hasApiFields" class="space-y-3 border-t border-border pt-3">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted">{{ t('aggregators.platform_api') }}</h3>
                        <p class="mt-0.5 text-xs text-muted">{{ t('aggregators.platform_api_hint') }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <FormField v-if="hasField('environment')" :label="t('aggregators.environment')" :error="channelForm.errors.environment">
                            <template #default="{ id }">
                                <select :id="id" v-model="channelForm.environment" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                                    <option value="sandbox">{{ t('aggregators.env_sandbox') }}</option>
                                    <option value="production">{{ t('aggregators.env_production') }}</option>
                                </select>
                            </template>
                        </FormField>

                        <FormField v-if="hasField('chain_id')" :label="t('aggregators.chain_id')" :error="channelForm.errors.chain_id">
                            <template #default="{ id }">
                                <input :id="id" v-model="channelForm.chain_id" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 font-mono text-[13px]">
                            </template>
                        </FormField>

                        <FormField :label="t('aggregators.client_id')" :error="channelForm.errors.client_id">
                            <template #default="{ id }">
                                <input :id="id" v-model="channelForm.client_id" type="text" autocomplete="off" class="h-7 w-full rounded-md border border-border bg-surface px-2 font-mono text-[13px]">
                            </template>
                        </FormField>

                        <FormField :label="t('aggregators.client_secret')" :helper="editingHasSecret ? t('aggregators.secret_saved') : ''" :error="channelForm.errors.client_secret">
                            <template #default="{ id }">
                                <input :id="id" v-model="channelForm.client_secret" type="password" autocomplete="new-password" class="h-7 w-full rounded-md border border-border bg-surface px-2 font-mono text-[13px]">
                            </template>
                        </FormField>
                    </div>

                    <div v-if="hasField('vendor_map')">
                        <h4 class="mb-1.5 text-xs font-semibold text-muted">{{ t('aggregators.vendor_map') }}</h4>
                        <div class="space-y-1.5">
                            <div v-for="branch in branches" :key="branch.id" class="grid grid-cols-[1fr_1fr] items-center gap-2">
                                <span class="truncate text-[13px] text-foreground">{{ branch.code }} - {{ branch.name }}</span>
                                <input v-model="channelForm.vendor_map[String(branch.id)]" type="text" :placeholder="t('aggregators.vendor_id')" class="h-7 w-full rounded-md border border-border bg-surface px-2 font-mono text-[13px]">
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-muted">{{ t('aggregators.vendor_map_hint') }}</p>
                    </div>
                </section>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px]" @click="channelOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="channelForm.processing" class="h-7 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground" @click="saveChannel">{{ t('common.save') }}</button>
            </template>
        </SlideOver>

        <!-- Manual order dialog -->
        <SlideOver :open="orderOpen" :title="t('aggregators.manual_order')" :subtitle="t('aggregators.manual_order_hint')" wide @close="orderOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="saveOrder">
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

        <!-- Outlet status dialog -->
        <Teleport to="body">
            <div v-if="outletOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="outletOpen = false">
                <form class="w-full max-w-sm rounded-xl border border-border bg-surface p-4" @submit.prevent="saveOutlet">
                    <h2 class="text-base font-semibold">{{ t('aggregators.outlet_status') }} - {{ outletChannel?.name }}</h2>
                    <p class="mt-0.5 text-xs text-muted">{{ t('aggregators.outlet_hint') }}</p>

                    <div class="mt-3 space-y-3">
                        <FormField :label="t('nav.branch')" required :error="outletForm.errors.branch_id">
                            <template #default="{ id }">
                                <select :id="id" v-model="outletForm.branch_id" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                                    <option v-for="branch in mappedBranches" :key="branch.id" :value="branch.id">{{ branch.code }} - {{ branch.name }}</option>
                                </select>
                            </template>
                        </FormField>

                        <FormField :label="t('common.status')" required>
                            <template #default="{ id }">
                                <select :id="id" v-model="outletForm.status" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                                    <option value="OPEN">{{ t('aggregators.outlet_open') }}</option>
                                    <option value="CLOSED_TODAY">{{ t('aggregators.outlet_closed_today') }}</option>
                                    <option value="CLOSED_UNTIL">{{ t('aggregators.outlet_closed_until') }}</option>
                                    <option value="CHECKIN">{{ t('aggregators.outlet_checkin') }}</option>
                                </select>
                            </template>
                        </FormField>

                        <FormField v-if="['CLOSED_TODAY', 'CLOSED_UNTIL'].includes(outletForm.status)" :label="t('aggregators.closed_reason')" :error="outletForm.errors.closed_reason">
                            <template #default="{ id }">
                                <select :id="id" v-model="outletForm.closed_reason" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                                    <option v-for="reason in closedReasons" :key="reason" :value="reason">{{ t(`aggregators.reason_${reason}`) }}</option>
                                </select>
                            </template>
                        </FormField>

                        <FormField v-if="outletForm.status === 'CLOSED_UNTIL'" :label="t('aggregators.closed_until')" required :error="outletForm.errors.closed_until">
                            <template #default="{ id }">
                                <input :id="id" v-model="outletForm.closed_until" type="datetime-local" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                            </template>
                        </FormField>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px]" @click="outletOpen = false">{{ t('common.cancel') }}</button>
                        <button type="submit" :disabled="outletForm.processing || ! outletForm.branch_id" class="h-7 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground disabled:opacity-60">{{ t('common.save') }}</button>
                    </div>
                </form>
            </div>
        </Teleport>

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
