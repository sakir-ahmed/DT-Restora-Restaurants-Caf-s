<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
    rows: { type: Array, required: true },
    employees: { type: Array, required: true },
});

const filters = ref({ from: props.from, to: props.to });
const advanceOpen = ref(false);

const advanceForm = useForm({
    employee_id: '',
    amount: '',
    advanced_on: props.to,
    note: '',
});

const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';

const columns = computed(() => [
    { key: 'name', label: t('attendance.employee'), align: 'left', sortable: true },
    { key: 'basis_label', label: t('payroll.basis'), align: 'left' },
    { key: 'rate_display', label: t('payroll.rate'), align: 'right' },
    { key: 'days', label: t('payroll.days'), align: 'right' },
    { key: 'hours', label: t('payroll.hours'), align: 'right' },
    { key: 'gross_display', label: t('payroll.gross'), align: 'right' },
    { key: 'advances_display', label: t('payroll.advances'), align: 'right' },
    { key: 'net_display', label: t('payroll.net'), align: 'right' },
    { key: 'slip', label: t('payroll.slip'), align: 'left' },
]);

const tableRows = computed(() => props.rows.map((row) => ({
    ...row,
    basis_label: t(`staff.basis_${row.basis}`),
    rate_display: formatMoney(row.rate),
    gross_display: formatMoney(row.gross),
    advances_display: formatMoney(row.advances),
    net_display: formatMoney(row.net),
})));

function applyRange() {
    router.get(route('admin.payroll.index'), { from: filters.value.from, to: filters.value.to }, { preserveState: true, preserveScroll: true });
}

function slipHref(row) {
    return route('admin.payroll.slip', { employee: row.id, from: props.from, to: props.to });
}

function saveAdvance() {
    advanceForm.post(route('admin.payroll.advances.store'), {
        preserveScroll: true,
        onSuccess: () => {
            advanceOpen.value = false;
            advanceForm.reset('amount', 'note');
            notify.success(t('payroll.advance_recorded'));
        },
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('payroll.title')" :description="t('payroll.subtitle')">
            <template #actions>
                <button type="button" class="inline-flex h-7 items-center rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90" @click="advanceOpen = true">
                    {{ t('payroll.record_advance') }}
                </button>
            </template>
        </PageHeader>

        <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="applyRange">
            <FormField :label="t('payroll.from')">
                <template #default="{ id }">
                    <input :id="id" v-model="filters.from" type="date" :class="inputClass">
                </template>
            </FormField>
            <FormField :label="t('payroll.to')">
                <template #default="{ id }">
                    <input :id="id" v-model="filters.to" type="date" :class="inputClass">
                </template>
            </FormField>
            <button type="submit" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground">{{ t('payroll.apply') }}</button>
        </form>

        <DataTable :columns="columns" :rows="tableRows" :empty-title="t('payroll.empty')" :empty-description="t('payroll.empty_hint')">
            <template #cell(net_display)="{ row }">
                <span class="tabular-nums">{{ row.net_display }}</span>
            </template>
            <template #cell(slip)="{ row }">
                <a :href="slipHref(row)" target="_blank" rel="noopener" class="whitespace-nowrap text-[13px] text-accent underline">{{ t('payroll.slip') }}</a>
            </template>
        </DataTable>

        <SlideOver :open="advanceOpen" :title="t('payroll.record_advance')" @close="advanceOpen = false">
            <form class="space-y-3" @submit.prevent="saveAdvance">
                <FormField :label="t('attendance.employee')" required :error="advanceForm.errors.employee_id">
                    <template #default="{ id }">
                        <select :id="id" v-model="advanceForm.employee_id" :class="inputClass">
                            <option value="">{{ t('common.select') }}</option>
                            <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField :label="t('payroll.amount')" required :error="advanceForm.errors.amount">
                    <MoneyInput v-model="advanceForm.amount" />
                </FormField>
                <FormField :label="t('payroll.advanced_on')" required :error="advanceForm.errors.advanced_on">
                    <template #default="{ id }">
                        <input :id="id" v-model="advanceForm.advanced_on" type="date" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('payroll.note')" :error="advanceForm.errors.note">
                    <template #default="{ id }">
                        <input :id="id" v-model="advanceForm.note" type="text" :class="inputClass">
                    </template>
                </FormField>
            </form>
            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px]" @click="advanceOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="advanceForm.processing" class="inline-flex h-7 items-center rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground disabled:opacity-60" @click="saveAdvance">
                    {{ t('common.save') }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
