<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    ingredients: { type: Array, required: true },
    units: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
});

const slideOverOpen = ref(false);
const editingId = ref(null);

const columns = computed(() => [
    { key: 'name', label: t('inventory.ingredient'), align: 'left', sortable: true },
    { key: 'unit_symbol', label: t('inventory.unit'), align: 'left', minWidth: '80px' },
    { key: 'stock_display', label: t('inventory.stock'), type: 'number', sortable: true, minWidth: '96px' },
    { key: 'avg_cost_display', label: t('inventory.avg_cost'), type: 'money', sortable: true, minWidth: '96px' },
    { key: 'value_display', label: t('inventory.stock_value'), type: 'money', sortable: true, minWidth: '96px' },
    { key: 'reorder_display', label: t('inventory.reorder_level'), type: 'number', minWidth: '96px' },
    { key: 'is_active', label: t('common.status'), minWidth: '104px' },
]);

const rows = computed(() => props.ingredients.map((ingredient) => {
    const stock = ingredient.stocks?.[0];
    const factor = Number(ingredient.unit?.factor ?? 1);

    const displayQty = factor > 0 ? Number(stock?.stock_qty ?? 0) / factor : Number(stock?.stock_qty ?? 0);

    return {
        ...ingredient,
        unit_symbol: ingredient.unit?.symbol,
        stock_display: `${Number(displayQty.toFixed(3)).toString()}`,
        sort_stock: Number(stock?.stock_qty ?? 0),
        low_stock: Number(stock?.stock_qty ?? 0) <= Number(ingredient.reorder_level) && Number(ingredient.reorder_level) > 0,
        avg_cost_display: formatMoney(stock?.avg_cost ?? 0, { decimals: 2 }),
        sort_avg: Number(stock?.avg_cost ?? 0),
        value_display: formatMoney(Number(stock?.stock_qty ?? 0) * Number(stock?.avg_cost ?? 0)),
        sort_value: Number(stock?.stock_qty ?? 0) * Number(stock?.avg_cost ?? 0),
        reorder_display: Number(ingredient.reorder_level).toString(),
    };
}));

const totalValue = computed(() => rows.value.reduce((sum, row) => sum + row.sort_value, 0));
const lowStockCount = computed(() => rows.value.filter((row) => row.low_stock).length);

const form = useForm({
    name: '', name_bn: '', unit_id: '', reorder_level: '0', is_active: true,
});

function openCreate() {
    editingId.value = null;
    form.defaults({ name: '', name_bn: '', unit_id: '', reorder_level: '0', is_active: true }).reset();
    slideOverOpen.value = true;
}

function openEdit(ingredient) {
    editingId.value = ingredient.id;
    form.defaults({
        name: ingredient.name,
        name_bn: ingredient.name_bn ?? '',
        unit_id: ingredient.unit_id,
        reorder_level: ingredient.reorder_level,
        is_active: ingredient.is_active,
    }).reset();
    slideOverOpen.value = true;
}

function save() {
    if (editingId.value === null) {
        form.post(route('admin.ingredients.store'), {
            onSuccess: () => {
                slideOverOpen.value = false;
                notify.success(t('inventory.ingredient_created'));
            },
        });
    } else {
        form.put(route('admin.ingredients.update', editingId.value), {
            onSuccess: () => {
                slideOverOpen.value = false;
                notify.success(t('inventory.ingredient_updated'));
            },
        });
    }
}
</script>

<template>
    <div>
        <PageHeader :title="t('inventory.title')" :description="t('inventory.subtitle')">
            <template #actions>
                <a
                    href="/admin/suppliers"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background"
                >
                    <AppIcon name="truck-fast" size="12" />
                    {{ t('inventory.suppliers') }}
                </a>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="openCreate"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('inventory.add_ingredient') }}
                </button>
            </template>
        </PageHeader>

        <div v-if="lowStockCount > 0" class="mb-3 flex items-center gap-2 rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-[13px] text-foreground">
            <AppIcon name="triangle-exclamation" size="14" class="text-warning" />
            {{ t('dashboard.alerts.low_stock_count', { count: lowStockCount }) }}
        </div>

        <DataTable
            :columns="columns"
            :rows="rows"
            searchable
            :clickable="canManage"
            :empty-title="t('inventory.no_ingredients')"
            :empty-description="t('inventory.no_ingredients_hint')"
            :empty-action="canManage ? t('inventory.add_ingredient') : ''"
            :totals-row="{ label: t('common.total'), value_display: formatMoney(totalValue) }"
            @row-click="canManage && openEdit($event)"
            @empty-action="openCreate"
        >
            <template #cell(name)="{ row }">
                <span class="flex items-center gap-1.5 truncate font-medium">
                    <AppIcon v-if="row.low_stock" name="triangle-exclamation" size="12" class="text-warning" />
                    {{ row.name }}
                </span>
                <span v-if="row.name_bn" class="block truncate text-xs text-muted">{{ row.name_bn }}</span>
            </template>
            <template #cell(is_active)="{ value }">
                <StatusBadge :status="value ? 'active' : 'inactive'" />
            </template>
        </DataTable>

        <SlideOver :open="slideOverOpen" :title="editingId === null ? t('inventory.add_ingredient') : t('inventory.edit_ingredient')" @close="slideOverOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="save">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('inventory.ingredient')" required :error="form.errors.name">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.name" type="text" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.name ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('branches.name_bn')" :error="form.errors.name_bn">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.name_bn" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>

                    <FormField :label="t('inventory.unit')" required :error="form.errors.unit_id" :helper="t('inventory.unit_helper')">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.unit_id" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.unit_id ? 'border-danger' : 'border-border focus:border-accent'">
                                <option value="" disabled>{{ t('common.select') }}</option>
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }} ({{ unit.symbol }})</option>
                            </select>
                        </template>
                    </FormField>

                    <FormField :label="t('inventory.reorder_level')" :helper="t('inventory.reorder_helper')" :error="form.errors.reorder_level">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.reorder_level" type="number" step="0.001" min="0" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>
                </div>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="slideOverOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="form.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="save">
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
