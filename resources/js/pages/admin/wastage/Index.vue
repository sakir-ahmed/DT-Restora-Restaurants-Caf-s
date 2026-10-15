<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    wastages: { type: Array, required: true },
    ingredients: { type: Array, required: true },
    units: { type: Array, required: true },
    reasons: { type: Array, required: true },
    canLog: { type: Boolean, default: false },
});

const slideOverOpen = ref(false);

const columns = computed(() => [
    { key: 'created_at', label: t('ui_kit.created'), align: 'left', minWidth: '112px' },
    { key: 'ingredient_name', label: t('inventory.ingredient'), align: 'left', sortable: true },
    { key: 'qty_display', label: t('inventory.qty'), type: 'number', minWidth: '80px' },
    { key: 'reason', label: t('inventory.reason'), align: 'left' },
    { key: 'user_name', label: t('staff.name'), align: 'left' },
    { key: 'cost', label: t('inventory.cost'), type: 'money', sortable: true, minWidth: '96px' },
]);

const rows = computed(() => props.wastages.map((wastage) => ({
    ...wastage,
    ingredient_name: wastage.ingredient?.name,
    qty_display: `${Number(wastage.qty)} ${wastage.unit?.symbol ?? ''}`,
    reason: t('reasons.' + wastage.reason_code) === 'reasons.' + wastage.reason_code ? wastage.reason_code : t('reasons.' + wastage.reason_code),
    user_name: wastage.user?.name,
    sort_cost: Number(wastage.cost),
})));

const totalCost = computed(() => rows.value.reduce((sum, row) => sum + row.sort_cost, 0));

const form = useForm({
    ingredient_id: '',
    unit_id: '',
    qty: '1',
    reason_code: '',
    note: '',
});

function openLog() {
    form.defaults({ ingredient_id: '', unit_id: '', qty: '1', reason_code: '', note: '' }).reset();
    slideOverOpen.value = true;
}

function unitsForSelected() {
    const ingredient = props.ingredients.find((i) => i.id === Number(form.ingredient_id));

    if (! ingredient) {
        return props.units;
    }

    const sameType = props.units.filter((unit) => unit.type === ingredient.unit?.type);

    return sameType.length ? sameType : props.units;
}

function save() {
    form.post(route('admin.wastage.store'), {
        onSuccess: () => {
            slideOverOpen.value = false;
            notify.success(t('inventory.wastage_logged'));
        },
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('inventory.wastage_title')" :description="t('inventory.wastage_subtitle')">
            <template #actions>
                <button
                    v-if="canLog"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-danger px-2.5 text-[13px] font-medium text-white hover:bg-danger/90"
                    @click="openLog"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('inventory.log_wastage') }}
                </button>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="rows"
            searchable
            :empty-title="t('inventory.no_wastage')"
            :totals-row="{ label: t('common.total'), cost: formatMoney(totalCost) }"
        >
            <template #cell(created_at)="{ value }">
                <DateText :value="value" relative />
            </template>
            <template #cell(cost)="{ value }">
                <span class="text-danger">{{ formatMoney(value) }}</span>
            </template>
        </DataTable>

        <SlideOver :open="slideOverOpen" :title="t('inventory.log_wastage')" @close="slideOverOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="save">
                <FormField :label="t('inventory.ingredient')" required :error="form.errors.ingredient_id">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.ingredient_id" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.ingredient_id ? 'border-danger' : 'border-border focus:border-accent'">
                            <option value="" disabled>{{ t('common.select') }}</option>
                            <option v-for="ingredient in ingredients" :key="ingredient.id" :value="ingredient.id">{{ ingredient.name }}</option>
                        </select>
                    </template>
                </FormField>

                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('inventory.unit')" required :error="form.errors.unit_id">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.unit_id" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.unit_id ? 'border-danger' : 'border-border focus:border-accent'">
                                <option value="" disabled>{{ t('common.select') }}</option>
                                <option v-for="unit in unitsForSelected()" :key="unit.id" :value="unit.id">{{ unit.name }} ({{ unit.symbol }})</option>
                            </select>
                        </template>
                    </FormField>

                    <FormField :label="t('inventory.qty')" required :error="form.errors.qty">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.qty" type="number" step="0.001" min="0" class="h-7 w-full rounded-md border bg-surface px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.qty ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>
                </div>

                <FormField :label="t('inventory.reason')" required :helper="t('inventory.reason_required')" :error="form.errors.reason_code">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.reason_code" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.reason_code ? 'border-danger' : 'border-border focus:border-accent'">
                            <option value="" disabled>{{ t('common.select_reason') }}</option>
                            <option v-for="reason in reasons" :key="reason" :value="reason">{{ t('reasons.' + reason) === 'reasons.' + reason ? reason : t('reasons.' + reason) }}</option>
                        </select>
                    </template>
                </FormField>

                <FormField :label="t('common.note')" :error="form.errors.note">
                    <template #default="{ id }">
                        <textarea :id="id" v-model="form.note" rows="2" class="w-full resize-y rounded-md border border-border bg-surface px-2 py-1 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40" />
                    </template>
                </FormField>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="slideOverOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="form.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-danger px-2.5 text-[13px] font-medium text-white hover:bg-danger/90 disabled:opacity-60" @click="save">
                    <AppIcon v-if="form.processing" name="spinner" spin />
                    {{ t('inventory.log_wastage') }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
