<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import MultiSelect from '@/components/ui-kit/MultiSelect.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    coupons: { type: Array, required: true },
});

const slideOverOpen = ref(false);
const editingId = ref(null);

const columns = computed(() => [
    { key: 'code', label: t('coupons.code'), align: 'left', sortable: true },
    { key: 'value_display', label: t('coupons.value_label'), type: 'money', sortable: true, minWidth: '96px' },
    { key: 'min_order', label: t('coupons.min_order_label'), type: 'money', minWidth: '96px' },
    { key: 'redemptions_count', label: t('coupons.used'), type: 'number', minWidth: '64px' },
    { key: 'limit_display', label: t('coupons.limit'), align: 'left' },
    { key: 'ends_at', label: t('coupons.ends'), align: 'left' },
    { key: 'is_active', label: t('common.status'), minWidth: '104px' },
]);

const rows = computed(() => props.coupons.map((coupon) => ({
    ...coupon,
    value_display: coupon.type === 'percent' ? coupon.value + '%' : formatMoney(coupon.value),
    sort_value: Number(coupon.value),
    limit_display: [coupon.usage_limit_total ? `${t('coupons.total')}: ${coupon.usage_limit_total}` : null,
        coupon.usage_limit_per_customer ? `${t('loyalty.points_per_customer')}: ${coupon.usage_limit_per_customer}` : null].filter(Boolean).join(' ') || '\u2013',
    ends_at: coupon.ends_at ?? null,
})));

const emptyForm = () => ({
    code: '', type: 'percent', value: '10', min_order: '0', starts_at: '', ends_at: '',
    usage_limit_total: '', usage_limit_per_customer: '', channels: [], branch_id: '', is_active: true,
});

const form = useForm(emptyForm());

function openCreate() {
    editingId.value = null;
    form.defaults(emptyForm()).reset();
    slideOverOpen.value = true;
}

function openEdit(coupon) {
    editingId.value = coupon.id;
    form.defaults({
        code: coupon.code,
        type: coupon.type,
        value: String(coupon.value),
        min_order: String(coupon.min_order ?? 0),
        starts_at: coupon.starts_at?.slice(0, 10) ?? '',
        ends_at: coupon.ends_at?.slice(0, 10) ?? '',
        usage_limit_total: coupon.usage_limit_total ?? '',
        usage_limit_per_customer: coupon.usage_limit_per_customer ?? '',
        channels: coupon.channels ?? [],
        branch_id: coupon.branch_id ?? '',
        is_active: coupon.is_active,
    }).reset();
    slideOverOpen.value = true;
}

function save() {
    const payload = {
        ...form.data(),
        min_order: form.min_order || '0',
        usage_limit_total: form.usage_limit_total || null,
        usage_limit_per_customer: form.usage_limit_per_customer || null,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
        branch_id: form.branch_id || null,
        channels: form.channels.length ? form.channels : null,
    };

    const options = {
        onSuccess: () => {
            slideOverOpen.value = false;
            notify.success(editingId.value === null ? t('coupons.created') : t('coupons.updated'));
        },
    };

    if (editingId.value === null) {
        form.post(route('admin.coupons.store'), { ...options, data: payload });
    } else {
        form.put(route('admin.coupons.update', editingId.value), { ...options, data: payload });
    }
}
</script>

<template>
    <div>
        <PageHeader :title="t('coupons.title')" :description="t('coupons.subtitle')">
            <template #actions>
                <button
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="openCreate"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('coupons.add') }}
                </button>
            </template>
        </PageHeader>

        <DataTable :columns="columns" :rows="rows" searchable clickable :empty-title="t('coupons.empty')" @row-click="openEdit">
            <template #cell(code)="{ row }">
                <span class="font-mono font-medium">{{ row.code }}</span>
            </template>
            <template #cell(is_active)="{ value }">
                <StatusBadge :status="value ? 'active' : 'inactive'" />
            </template>
            <template #cell(ends_at)="{ value }">
                <span v-if="value">{{ value.slice(0, 10) }}</span>
                <span v-else class="text-muted">&ndash;</span>
            </template>
        </DataTable>

        <Teleport to="body">
            <div v-if="slideOverOpen" class="fixed inset-0 z-50 flex justify-end bg-black/40" @click.self="slideOverOpen = false">
                <aside class="h-full w-full max-w-md overflow-y-auto border-l border-border bg-surface p-4">
                    <h2 class="text-base font-semibold">{{ editingId === null ? t('coupons.add') : t('coupons.edit') }}</h2>

                    <form class="mt-4 space-y-3" @submit.prevent="save">
                        <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                            <FormField :label="t('coupons.code')" required :error="form.errors.code">
                                <template #default="{ id }">
                                    <input :id="id" v-model="form.code" type="text" placeholder="SAVE10" class="h-7 w-full rounded-md border bg-surface px-2 font-mono uppercase text-[13px]" :class="form.errors.code ? 'border-danger' : 'border-border'">
                                </template>
                            </FormField>

                            <FormField :label="t('coupons.type_label')" required>
                                <template #default="{ id }">
                                    <select :id="id" v-model="form.type" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                                        <option value="percent">%</option>
                                        <option value="fixed">{{ t('orders.fixed_amount') }}</option>
                                    </select>
                                </template>
                            </FormField>

                            <FormField :label="t('coupons.value_label')" required :error="form.errors.value">
                                <MoneyInput v-model="form.value" :prefix="form.type === 'percent' ? '%' : ''" />
                            </FormField>

                            <FormField :label="t('coupons.min_order_label')">
                                <MoneyInput v-model="form.min_order" />
                            </FormField>

                            <FormField :label="t('coupons.starts')">
                                <template #default="{ id }">
                                    <input :id="id" v-model="form.starts_at" type="date" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                                </template>
                            </FormField>

                            <FormField :label="t('coupons.ends')">
                                <template #default="{ id }">
                                    <input :id="id" v-model="form.ends_at" type="date" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                                </template>
                            </FormField>

                            <FormField :label="t('coupons.limit_total')">
                                <template #default="{ id }">
                                    <input :id="id" v-model="form.usage_limit_total" type="number" min="1" placeholder="\u221E" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-right text-[13px] tabular-nums">
                                </template>
                            </FormField>

                            <FormField :label="t('coupons.limit_per_customer')">
                                <template #default="{ id }">
                                    <input :id="id" v-model="form.usage_limit_per_customer" type="number" min="1" placeholder="\u221E" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-right text-[13px] tabular-nums">
                                </template>
                            </FormField>

                            <div class="col-span-2">
                                <FormField :label="t('coupons.channels')" :helper="t('coupons.channels_helper')">
                                    <MultiSelect
                                        v-model="form.channels"
                                        :options="[
                                            { value: 'pos', label: 'POS' },
                                            { value: 'website', label: t('dashboard.channel.website') },
                                            { value: 'aggregator', label: t('dashboard.channel.aggregator') },
                                        ]"
                                    />
                                </FormField>
                            </div>
                        </div>

                        <SwitchField v-model="form.is_active" :label="t('branches.is_active')" />

                        <div class="flex justify-end gap-2 border-t border-border pt-3">
                            <button type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px]" @click="slideOverOpen = false">{{ t('common.cancel') }}</button>
                            <button type="submit" :disabled="form.processing" class="h-7 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground disabled:opacity-50">{{ t('common.save') }}</button>
                        </div>
                    </form>
                </aside>
            </div>
        </Teleport>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
