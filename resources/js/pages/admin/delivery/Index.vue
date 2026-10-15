<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    deliveries: { type: Array, required: true },
    unassigned: { type: Array, required: true },
    riders: { type: Array, required: true },
    statusFilter: { type: String, default: '' },
});

const columns = computed(() => [
    { key: 'created_at', label: t('ui_kit.created'), align: 'left', minWidth: '112px' },
    { key: 'invoice_no', label: t('inventory.invoice_no'), align: 'left' },
    { key: 'customer_name', label: t('inventory.customer'), align: 'left' },
    { key: 'address', label: t('web.address'), align: 'left', truncate: true },
    { key: 'rider_name', label: t('delivery.rider_label'), align: 'left' },
    { key: 'status', label: t('common.status'), minWidth: '104px' },
    { key: 'cod_display', label: t('web.cod'), type: 'money', minWidth: '96px' },
    { key: 'actions', label: '', align: 'right', type: 'component', width: '120px' },
]);

const rows = computed(() => props.deliveries.map((delivery) => ({
    ...delivery,
    invoice_no: delivery.order?.invoice_no,
    customer_name: delivery.order?.customer?.name,
    address: delivery.order?.delivery_address,
    rider_name: delivery.rider?.user?.name,
    cod_display: Number(delivery.cod_amount) > 0 ? formatMoney(delivery.cod_amount) : '\u2013',
})));

function assign(order, riderId) {
    router.post(route('admin.delivery.assign', order.id), { rider_id: riderId }, {
        preserveState: true,
        onSuccess: () => notify.success(t('delivery.assigned')),
    });
}

function reconcile(delivery) {
    router.post(route('admin.delivery.reconcile-cod', delivery.id), {}, {
        preserveState: true,
        onSuccess: () => notify.success(t('delivery.cod_reconciled')),
    });
}

const statusFilters = ['', 'pending', 'assigned', 'picked_up', 'on_the_way', 'delivered', 'failed'];

function riderLabel(rider) {
    const busy = rider.active_deliveries > 0 ? ` (${rider.active_deliveries})` : '';

    return `${rider.user?.name}${busy}`;
}
</script>

<template>
    <div>
        <PageHeader :title="t('delivery.title')" :description="t('delivery.subtitle')">
            <template #actions>
                <div class="flex items-center gap-3 text-[13px] text-muted">
                    <span class="flex items-center gap-1"><AppIcon name="motorcycle" size="12" /> {{ riders.filter((r) => r.is_on_duty).length }}/{{ riders.length }}</span>
                </div>
            </template>
        </PageHeader>

        <!-- Unassigned queue -->
        <section class="mb-4 overflow-hidden rounded-lg border border-border bg-surface">
            <header class="border-b border-border px-3 py-2">
                <h2 class="text-sm font-semibold text-foreground">{{ t('delivery.queue') }} ({{ unassigned.length }})</h2>
            </header>

            <p v-if="unassigned.length === 0" class="px-3 py-4 text-center text-[13px] text-muted">{{ t('delivery.queue_empty') }}</p>

            <ul>
                <li v-for="order in unassigned" :key="order.id" class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 px-3 py-2 last:border-0">
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium">{{ order.invoice_no }} <span class="text-muted">- {{ formatMoney(order.total) }}</span></p>
                        <p class="truncate text-xs text-muted">{{ order.customer?.name }} - {{ order.delivery_address }}</p>
                    </div>

                    <select
                        class="h-7 rounded-md border border-border bg-background px-1.5 text-[13px]"
                        :aria-label="t('delivery.assign_rider')"
                        @change="assign(order, Number($event.target.value))"
                    >
                        <option value="" disabled selected>{{ t('delivery.assign_rider') }}</option>
                        <option v-for="rider in riders" :key="rider.id" :value="rider.id">{{ riderLabel(rider) }}</option>
                    </select>
                </li>
            </ul>
        </section>

        <!-- Status filter -->
        <div class="mb-3 flex flex-wrap gap-1.5">
            <button
                v-for="status in statusFilters"
                :key="status"
                type="button"
                class="h-7 rounded-full border px-2.5 text-xs font-medium"
                :class="(statusFilter ?? '') === status ? 'border-accent bg-accent/15 text-accent-foreground' : 'border-border bg-surface text-muted'"
                @click="router.get(route('admin.delivery.index'), { status }, { preserveState: true })"
            >
                {{ status === '' ? t('common.status') : t('status.' + status, {}) === 'status.' + status ? status : t('status.' + status) }}
            </button>
        </div>

        <DataTable :columns="columns" :rows="rows" :clickable="false" :empty-title="t('delivery.no_deliveries')">
            <template #cell(created_at)="{ value }">
                <DateText :value="value" relative />
            </template>
            <template #cell(status)="{ value }">
                <StatusBadge :status="value === 'on_the_way' ? 'preparing' : value === 'assigned' ? 'pending' : value === 'picked_up' ? 'preparing' : value" />
            </template>
            <template #cell(actions)="{ row }">
                <button
                    v-if="row.status === 'delivered' && row.cod_amount > 0 && ! row.cod_received_at"
                    type="button"
                    class="flex h-6 items-center gap-1 rounded border border-success/50 px-1.5 text-[11px] text-success hover:bg-success/10"
                    @click="reconcile(row)"
                >
                    <AppIcon name="money-bill-wave" size="10" />
                    {{ t('delivery.reconcile_cod') }}
                </button>
            </template>
        </DataTable>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
