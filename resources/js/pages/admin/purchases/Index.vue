<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    purchases: { type: Array, required: true },
    suppliers: { type: Array, default: () => [] },
    ingredients: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const slideOverOpen = ref(false);
const expandedId = ref(null);

const columns = computed(() => [
    { key: 'purchased_at', label: t('inventory.purchased_at'), align: 'left', sortable: true, minWidth: '112px' },
    { key: 'supplier_name', label: t('inventory.supplier'), align: 'left', sortable: true },
    { key: 'invoice_no', label: t('inventory.invoice_no'), align: 'left' },
    { key: 'items_count', label: t('inventory.items_label'), type: 'number', minWidth: '64px' },
    { key: 'total', label: t('inventory.total'), type: 'money', sortable: true, minWidth: '96px' },
    { key: 'status', label: t('common.status'), minWidth: '104px' },
    { key: 'actions', label: '', align: 'right', type: 'component', width: '40px' },
]);

const rows = computed(() => props.purchases.map((purchase) => ({
    ...purchase,
    supplier_name: purchase.supplier?.name,
    items_count: purchase.items?.length ?? 0,
    created_by: purchase.user?.name,
})));

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    supplier_id: '',
    invoice_no: '',
    purchased_at: today,
    note: '',
    receive: true,
    items: [],
});

function openCreate() {
    form.defaults({
        supplier_id: props.suppliers[0]?.id ?? '',
        invoice_no: '',
        purchased_at: today,
        note: '',
        receive: true,
        items: [],
    }).reset();
    addLine();
    slideOverOpen.value = true;
}

function addLine() {
    form.items.push({
        ingredient_id: '',
        unit_id: '',
        qty: '1',
        unit_cost: '0.00',
    });
}

const total = computed(() => form.items.reduce((sum, line) => sum + Number(line.qty || 0) * Number(line.unit_cost || 0), 0));

function ingredientsOptions() {
    return props.ingredients.map((ingredient) => ({
        value: ingredient.id,
        label: `${ingredient.name} (${ingredient.unit?.symbol ?? ''})`,
    }));
}

function unitsFor(line) {
    const ingredient = props.ingredients.find((i) => i.id === Number(line.ingredient_id));

    if (! ingredient) {
        return props.units;
    }

    const sameType = props.units.filter((unit) => unit.type === ingredient.unit?.type);

    return sameType.length ? sameType : props.units;
}

function save() {
    form.post(route('admin.purchases.store'), {
        onSuccess: () => {
            slideOverOpen.value = false;
            notify.success(form.receive ? t('inventory.purchase_received') : t('inventory.purchase_created'));
        },
    });
}

function receive(purchase) {
    router.post(route('admin.purchases.receive', purchase.id), {}, {
        onSuccess: () => notify.success(t('inventory.purchase_received')),
    });
}

function toggleExpand(row) {
    expandedId.value = expandedId.value === row.id ? null : row.id;
}
</script>

<template>
    <div>
        <PageHeader :title="t('inventory.purchases')" :description="t('inventory.subtitle_purchases')">
            <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="openCreate"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('inventory.add_purchase') }}
                </button>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="rows"
            searchable
            clickable
            :empty-title="t('inventory.no_purchases')"
            :empty-action="canManage ? t('inventory.add_purchase') : ''"
            :totals-row="{ label: t('common.total'), total: formatMoney(rows.reduce((sum, row) => sum + Number(row.total), 0)) }"
            @row-click="toggleExpand"
            @empty-action="openCreate"
        >
            <template #cell(purchased_at)="{ value }">
                <DateText :value="value" />
            </template>
            <template #cell(total)="{ value }">
                {{ formatMoney(value) }}
            </template>
            <template #cell(status)="{ value }">
                <StatusBadge :status="value" />
            </template>
            <template #cell(actions)="{ row }">
                <button
                    v-if="row.status === 'draft'"
                    type="button"
                    class="flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-foreground hover:bg-background"
                    @click.stop="receive(row)"
                >
                    <AppIcon name="check" size="10" />
                    {{ t('inventory.receive_action') }}
                </button>
            </template>
        </DataTable>

        <SlideOver :open="slideOverOpen" :title="t('inventory.add_purchase')" wide @close="slideOverOpen = false">
            <form class="space-y-3" @submit.prevent="save">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('inventory.supplier')" required :error="form.errors.supplier_id">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.supplier_id" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.supplier_id ? 'border-danger' : 'border-border focus:border-accent'">
                                <option value="" disabled>{{ t('common.select') }}</option>
                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                            </select>
                        </template>
                    </FormField>

                    <FormField :label="t('inventory.invoice_no')" :error="form.errors.invoice_no">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.invoice_no" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>

                    <FormField :label="t('inventory.purchased_at')" required :error="form.errors.purchased_at">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.purchased_at" type="date" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>

                    <div class="flex items-end pb-1">
                        <SwitchField v-model="form.receive" :label="t('inventory.receive')" />
                    </div>
                </div>

                <div class="border-t border-border pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ t('inventory.items_label') }}</h3>
                        <button type="button" class="inline-flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-foreground hover:bg-background" @click="addLine">
                            <AppIcon name="plus" size="10" />
                            {{ t('inventory.add_line') }}
                        </button>
                    </div>

                    <div v-for="(line, index) in form.items" :key="index" class="mb-2 grid grid-cols-[1.4fr_0.8fr_0.7fr_0.9fr_28px] items-start gap-2">
                        <FormField :error="form.errors['items.' + index + '.ingredient_id']">
                            <template #default="{ id }">
                                <select :id="id" v-model="line.ingredient_id" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent">
                                    <option value="" disabled>{{ t('common.select') }}</option>
                                    <option v-for="option in ingredientsOptions()" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </template>
                        </FormField>

                        <FormField :error="form.errors['items.' + index + '.unit_id']">
                            <template #default="{ id }">
                                <select :id="id" v-model="line.unit_id" class="h-7 w-full rounded-md border border-border bg-surface px-1 text-[12px] text-foreground outline-none focus:border-accent">
                                    <option value="" disabled>---</option>
                                    <option v-for="unit in unitsFor(line)" :key="unit.id" :value="unit.id">{{ unit.symbol }}</option>
                                </select>
                            </template>
                        </FormField>

                        <FormField :error="form.errors['items.' + index + '.qty']">
                            <template #default="{ id }">
                                <input :id="id" v-model="line.qty" type="number" step="0.001" min="0" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                            </template>
                        </FormField>

                        <FormField :error="form.errors['items.' + index + '.unit_cost']">
                            <MoneyInput v-model="line.unit_cost" />
                        </FormField>

                        <div class="flex h-7 items-center">
                            <button type="button" class="flex h-6 w-6 items-center justify-center rounded text-muted hover:text-danger" :aria-label="t('common.remove')" @click="form.items.splice(index, 1)">
                                <AppIcon name="xmark" size="12" />
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-border pt-2 text-[13px] font-semibold text-foreground">
                        {{ t('common.total') }}: {{ formatMoney(total) }}
                    </div>
                </div>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="slideOverOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="form.processing || form.items.length === 0" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="save">
                    <AppIcon v-if="form.processing" name="spinner" spin />
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
