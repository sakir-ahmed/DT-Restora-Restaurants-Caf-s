<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { useSafeDelete } from '@/lib/safeDelete';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    purchases: { type: Array, required: true },
    suppliers: { type: Array, default: () => [] },
    ingredients: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const slideOverOpen = ref(false);

const columns = computed(() => [
    { key: 'purchased_at', label: t('inventory.purchased_at'), align: 'left', sortable: true, minWidth: '112px' },
    { key: 'supplier_name', label: t('inventory.supplier'), align: 'left', sortable: true },
    { key: 'invoice_no', label: t('inventory.invoice_no'), align: 'left' },
    { key: 'items_count', label: t('inventory.items_label'), type: 'number', minWidth: '64px' },
    { key: 'total', label: t('inventory.total'), type: 'money', sortable: true, minWidth: '96px' },
    { key: 'status', label: t('common.status'), minWidth: '104px' },
    { key: 'actions', label: '', align: 'right', type: 'component', width: '88px' },
]);

const rows = computed(() => props.purchases.map((purchase) => ({
    ...purchase,
    supplier_name: purchase.supplier?.name ?? t('inventory.no_supplier_label'),
    items_count: purchase.items?.length ?? 0,
    created_by: purchase.user?.name,
    status: purchase.deleted_at ? 'deleted' : purchase.status,
    is_deleted: Boolean(purchase.deleted_at),
})));

const liveTotal = computed(() => rows.value.filter((row) => ! row.is_deleted).reduce((sum, row) => sum + Number(row.total), 0));

const safeDelete = useSafeDelete();

function removePurchase(purchase) {
    safeDelete({
        url: route('admin.purchases.destroy', purchase.id),
        title: t('inventory.purchase_delete_title', { invoice: purchase.invoice_no || ('#' + purchase.id) }),
        message: purchase.status === 'received' ? t('inventory.purchase_delete_received_message') : t('inventory.purchase_delete_draft_message'),
        facts: [
            { label: t('inventory.supplier'), value: purchase.supplier?.name ?? t('inventory.no_supplier_label') },
            { label: t('inventory.items_label'), value: String(purchase.items?.length ?? 0) },
            { label: t('inventory.total'), value: formatMoney(purchase.total) },
        ],
        success: t('inventory.purchase_deleted'),
    });
}

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    purchased_at: today,
    note: '',
    receive: true,
    items: [],
});

function openCreate() {
    form.defaults({
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
        category_id: '',
        ingredient_id: '',
        supplier_id: '',
        invoice_no: '',
        unit_id: '',
        qty: '1',
        unit_cost: '0.00',
    });
}

const total = computed(() => form.items.reduce((sum, line) => sum + Number(line.qty || 0) * Number(line.unit_cost || 0), 0));

// A line's category narrows its ingredient list: '' shows all, 'none' the uncategorised ones.
function ingredientsOptions(line) {
    return allIngredients.value
        .filter((ingredient) => {
            if (! line.category_id) {
                return true;
            }

            if (line.category_id === 'none') {
                return ingredient.ingredient_category_id === null;
            }

            return ingredient.ingredient_category_id === Number(line.category_id);
        })
        .map((ingredient) => ({
            value: ingredient.id,
            label: `${ingredient.name} (${ingredient.unit?.symbol ?? ''})`,
        }));
}

function onCategoryChange(line) {
    if (line.ingredient_id && ! ingredientsOptions(line).some((option) => option.value === Number(line.ingredient_id))) {
        line.ingredient_id = '';
        line.unit_id = '';
    }
}

// Suppliers linked to the line's ingredient come first choice; with no link recorded, any supplier can be picked.
function suppliersFor(line) {
    if (! line.ingredient_id) {
        return [];
    }

    const linked = props.suppliers.filter((supplier) => supplier.ingredient_ids.includes(Number(line.ingredient_id)));

    return [...(linked.length ? linked : props.suppliers), ...createdSuppliers.value];
}

// Suppliers made from this form show on every line straight away, before the page reloads.
const createdSuppliers = ref([]);

// Ingredients made from this form join every line's list straight away, before the page reloads.
const createdIngredients = ref([]);
const ingredientDialog = ref(null);
const allIngredients = computed(() => {
    const known = new Set(props.ingredients.map((ingredient) => ingredient.id));

    return [...props.ingredients, ...createdIngredients.value.filter((ingredient) => ! known.has(ingredient.id))];
});
const supplierDialog = ref(null);

function onSupplierChange(line) {
    if (line.supplier_id === '__new') {
        line.supplier_id = '';
        supplierDialog.value = { line, name: '', mobile: '', error: '', busy: false };
    }
}

function xsrfToken() {
    const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

async function createIngredient() {
    const dialog = ingredientDialog.value;

    if (! dialog || dialog.busy) {
        return;
    }

    if (! dialog.name.trim()) {
        dialog.error = t('inventory.ingredient_name_required');

        return;
    }

    if (! dialog.unit_id) {
        dialog.error = t('inventory.ingredient_unit_required');

        return;
    }

    dialog.busy = true;
    dialog.error = '';

    try {
        const response = await fetch(route('admin.purchases.ingredients.store'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
            credentials: 'same-origin',
            body: JSON.stringify({ name: dialog.name.trim(), unit_id: Number(dialog.unit_id), ingredient_category_id: dialog.category_id ? Number(dialog.category_id) : null }),
        });
        const body = await response.json();

        if (! response.ok) {
            dialog.error = String(Object.values(body.errors ?? {})[0]?.[0] ?? body.message ?? t('common.error_occurred'));
            dialog.busy = false;

            return;
        }

        createdIngredients.value.push(body);
        dialog.line.category_id = '';
        dialog.line.ingredient_id = body.id;
        onIngredientChange(dialog.line);
        ingredientDialog.value = null;
        notify.success(t('inventory.ingredient_created'));
    } catch {
        dialog.error = t('common.error_occurred');
        dialog.busy = false;
    }
}

async function createSupplier() {
    const dialog = supplierDialog.value;

    if (! dialog || dialog.busy) {
        return;
    }

    if (! dialog.name.trim()) {
        dialog.error = t('inventory.supplier_name_required');

        return;
    }

    dialog.busy = true;
    dialog.error = '';

    try {
        const response = await fetch(route('admin.purchases.suppliers.store'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
            credentials: 'same-origin',
            body: JSON.stringify({ name: dialog.name.trim(), mobile: dialog.mobile || null }),
        });
        const body = await response.json();

        if (! response.ok) {
            dialog.error = String(Object.values(body.errors ?? {})[0]?.[0] ?? body.message ?? t('common.error_occurred'));
            dialog.busy = false;

            return;
        }

        createdSuppliers.value.push(body);
        dialog.line.supplier_id = body.id;
        supplierDialog.value = null;
        notify.success(t('inventory.supplier_created'));
    } catch {
        dialog.error = t('common.error_occurred');
        dialog.busy = false;
    }
}

function onIngredientChange(line) {
    if (line.ingredient_id === '__new') {
        line.ingredient_id = '';
        line.unit_id = '';
        ingredientDialog.value = { line, name: '', unit_id: '', category_id: line.category_id && line.category_id !== 'none' ? line.category_id : '', error: '', busy: false };

        return;
    }

    const ingredient = allIngredients.value.find((i) => i.id === Number(line.ingredient_id));

    line.unit_id = ingredient?.unit_id ?? '';

    const options = suppliersFor(line);

    line.supplier_id = options.length === 1 ? options[0].id : '';
}

function unitsFor(line) {
    const ingredient = allIngredients.value.find((i) => i.id === Number(line.ingredient_id));

    if (! ingredient) {
        return props.units;
    }

    const sameType = props.units.filter((unit) => unit.type === ingredient.unit?.type);

    return sameType.length ? sameType : props.units;
}

function save() {
    // Every line needs an answer: a supplier, or "without supplier".
    const missing = form.items.findIndex((line) => line.supplier_id === '');

    if (missing !== -1) {
        form.clearErrors();
        form.setError(`items.${missing}.supplier_id`, t('inventory.line_supplier_required'));

        return;
    }

    form.transform((data) => ({
        ...data,
        items: data.items.map((line) => ({ ...line, supplier_id: line.supplier_id === 'none' ? null : line.supplier_id })),
    })).post(route('admin.purchases.store'), {
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

const confirm = useConfirm();
const editing = ref(null);

const editForm = useForm({
    supplier_id: '',
    invoice_no: '',
    purchased_at: today,
    note: '',
    items: [],
});

const editIsDraft = computed(() => editing.value?.status === 'draft');
const editIsReceived = computed(() => editing.value?.status === 'received');
const editIsDeleted = computed(() => Boolean(editing.value?.deleted_at));
const editIsFull = computed(() => (editIsDraft.value || editIsReceived.value) && ! editIsDeleted.value);
const editIsLocked = computed(() => editing.value?.status === 'cancelled' || editIsDeleted.value);
const editTotal = computed(() => editForm.items.reduce((sum, line) => sum + Number(line.qty || 0) * Number(line.unit_cost || 0), 0));

function openEdit(purchase) {
    editForm.clearErrors();
    editForm.defaults({
        supplier_id: purchase.supplier_id ?? '',
        invoice_no: purchase.invoice_no ?? '',
        purchased_at: String(purchase.purchased_at).slice(0, 10),
        note: purchase.note ?? '',
        items: (purchase.items ?? []).map((item) => ({
            category_id: '',
            ingredient_id: item.ingredient_id,
            unit_id: item.unit_id,
            qty: String(Number(item.qty)),
            unit_cost: Number(item.unit_cost).toFixed(2),
        })),
    }).reset();
    editing.value = purchase;
}

function addEditLine() {
    editForm.items.push({ category_id: '', ingredient_id: '', unit_id: '', qty: '1', unit_cost: '0.00' });
}

async function saveEdit() {
    if (editIsReceived.value) {
        const result = await confirm({
            variant: 'warning',
            level: 'simple',
            title: t('inventory.correct_purchase_title'),
            message: t('inventory.purchase_correct_warning'),
            facts: [
                { label: t('common.total'), value: formatMoney(editTotal.value) },
            ],
            confirmText: t('inventory.save_changes'),
        });

        if (! result?.confirmed) {
            return;
        }
    }

    editForm.transform((data) => ({
        ...data,
        supplier_id: data.supplier_id === '' ? null : data.supplier_id,
    })).put(route('admin.purchases.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
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
            :totals-row="{ label: t('common.total'), total: formatMoney(liveTotal) }"
            @row-click="openEdit"
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
                <div class="flex items-center justify-end gap-1">
                <button
                    v-if="row.status === 'draft'"
                    type="button"
                    class="flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-foreground hover:bg-background"
                    @click.stop="receive(row)"
                >
                    <AppIcon name="check" size="10" />
                    {{ t('inventory.receive_action') }}
                </button>
                <button
                    v-if="canManage && ! row.is_deleted"
                    type="button"
                    class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-danger/10 hover:text-danger"
                    :aria-label="t('common.delete')"
                    :title="t('common.delete')"
                    @click.stop="removePurchase(row)"
                >
                    <AppIcon name="trash" size="12" />
                </button>
                </div>
            </template>
        </DataTable>

        <SlideOver :open="slideOverOpen" :title="t('inventory.add_purchase')" extra-wide @close="slideOverOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="save">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('inventory.purchased_at')" required :error="form.errors.purchased_at">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.purchased_at" type="date" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>

                    <div class="flex items-end pb-1">
                        <SwitchField v-model="form.receive" :label="t('inventory.receive')" />
                    </div>

                    <div class="col-span-2">
                        <FormField :label="t('inventory.note_all_items')" :error="form.errors.note">
                            <template #default="{ id }">
                                <textarea :id="id" v-model="form.note" rows="2" maxlength="1000" class="w-full resize-y rounded-md border border-border bg-surface px-2 py-1.5 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40" />
                            </template>
                        </FormField>
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

                    <div class="mb-2 overflow-x-auto rounded-md border border-border">
                        <table class="w-full min-w-[900px] text-[13px]">
                            <thead>
                                <tr class="border-b border-border bg-background text-xs font-semibold text-muted">
                                    <th class="px-2 py-1.5 text-left">{{ t('inventory.category') }}</th>
                                    <th class="px-2 py-1.5 text-left">{{ t('inventory.ingredient') }}</th>
                                    <th class="px-2 py-1.5 text-left">{{ t('inventory.supplier') }}</th>
                                    <th class="px-2 py-1.5 text-left">{{ t('inventory.invoice_no') }}</th>
                                    <th class="px-2 py-1.5 text-left">{{ t('inventory.unit') }}</th>
                                    <th class="px-2 py-1.5 text-right">{{ t('inventory.qty') }}</th>
                                    <th class="px-2 py-1.5 text-right">{{ t('inventory.unit_cost') }}</th>
                                    <th class="px-2 py-1.5 text-right">{{ t('inventory.line_total') }}</th>
                                    <th class="w-8 px-1 py-1.5" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, index) in form.items" :key="index" class="border-b border-border last:border-0">
                                <td class="px-1 py-1 align-top">
                                    <select v-model="line.category_id" :aria-label="t('inventory.choose_category')" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent" @change="onCategoryChange(line)">
                                        <option value="">{{ t('inventory.all_categories') }}</option>
                                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                                        <option value="none">{{ t('inventory.category_none') }}</option>
                                    </select>
                                </td>
                                <td class="px-1 py-1 align-top">
                                    <select v-model="line.ingredient_id" :aria-label="t('inventory.ingredient')" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent" :class="form.errors['items.' + index + '.ingredient_id'] ? 'border-danger' : ''" @change="onIngredientChange(line)">
                                        <option value="" disabled>{{ t('common.select') }}</option>
                                        <option v-for="option in ingredientsOptions(line)" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        <option value="__new">{{ t('inventory.create_ingredient_option') }}</option>
                                    </select>
                                    <p v-if="form.errors['items.' + index + '.ingredient_id']" class="mt-0.5 text-xs text-danger">{{ form.errors['items.' + index + '.ingredient_id'] }}</p>
                                </td>
                                <td class="px-1 py-1 align-top">
                                    <select v-model="line.supplier_id" :disabled="! line.ingredient_id" :aria-label="t('inventory.supplier')" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent disabled:opacity-60" :class="form.errors['items.' + index + '.supplier_id'] ? 'border-danger' : ''" @change="onSupplierChange(line)">
                                        <option value="" disabled>{{ t('inventory.choose_supplier') }}</option>
                                        <option value="none">{{ t('inventory.without_supplier') }}</option>
                                        <option v-for="supplier in suppliersFor(line)" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                                        <option value="__new">{{ t('inventory.create_supplier_option') }}</option>
                                    </select>
                                    <p v-if="form.errors['items.' + index + '.supplier_id']" class="mt-0.5 text-xs text-danger">{{ form.errors['items.' + index + '.supplier_id'] }}</p>
                                </td>
                                <td class="px-1 py-1 align-top">
                                    <input v-model="line.invoice_no" type="text" maxlength="100" :aria-label="t('inventory.invoice_no')" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent">
                                </td>
                                <td class="px-1 py-1 align-top w-20">
                                    <select v-model="line.unit_id" :aria-label="t('inventory.unit')" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent" :class="form.errors['items.' + index + '.unit_id'] ? 'border-danger' : ''">
                                        <option value="" disabled>---</option>
                                        <option v-for="unit in unitsFor(line)" :key="unit.id" :value="unit.id">{{ unit.symbol }}</option>
                                    </select>
                                </td>
                                <td class="px-1 py-1 align-top w-24">
                                    <input v-model="line.qty" type="text" inputmode="decimal" v-numeric step="0.001" min="0" :aria-label="t('inventory.qty')" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent text-right tabular-nums" :class="form.errors['items.' + index + '.qty'] ? 'border-danger' : ''">
                                </td>
                                <td class="px-1 py-1 align-top w-32">
                                    <MoneyInput v-model="line.unit_cost" />
                                    <p v-if="form.errors['items.' + index + '.unit_cost']" class="mt-0.5 text-xs text-danger">{{ form.errors['items.' + index + '.unit_cost'] }}</p>
                                </td>
                                <td class="px-1 py-1 align-top w-28">
                                    <span class="flex h-7 items-center justify-end tabular-nums">{{ formatMoney(Number(line.qty || 0) * Number(line.unit_cost || 0)) }}</span>
                                </td>
                                <td class="px-1 py-1 align-top w-8">
                                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-muted hover:text-danger" :aria-label="t('common.remove')" @click="form.items.splice(index, 1)">
                                        <AppIcon name="xmark" size="12" />
                                    </button>
                                </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mb-2 text-xs text-muted">{{ t('inventory.purchase_split_note') }}</p>

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

        <SlideOver :open="editing !== null" :title="t('inventory.edit_purchase')" extra-wide @close="editing = null">
            <form v-if="editing" novalidate class="space-y-3" @submit.prevent="saveEdit">
                <p v-if="editIsLocked" class="rounded-md border border-border bg-background px-2.5 py-2 text-xs text-muted">{{ t('inventory.purchase_not_editable') }}</p>
                <p v-else-if="editIsReceived" class="rounded-md border border-warning/40 bg-warning/10 px-2.5 py-2 text-xs text-foreground">{{ t('inventory.purchase_correct_warning') }}</p>

                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField v-if="editIsFull" :label="t('inventory.purchased_at')" required :error="editForm.errors.purchased_at">
                        <template #default="{ id }">
                            <input :id="id" v-model="editForm.purchased_at" type="date" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>
                    <FormField v-if="editIsFull" :label="t('inventory.supplier')" :error="editForm.errors.supplier_id">
                        <template #default="{ id }">
                            <select :id="id" v-model="editForm.supplier_id" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent">
                                <option value="">{{ t('inventory.without_supplier') }}</option>
                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField :label="t('inventory.invoice_no')" :error="editForm.errors.invoice_no">
                        <template #default="{ id }">
                            <input :id="id" v-model="editForm.invoice_no" type="text" maxlength="100" :disabled="editIsLocked" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>
                    <div class="col-span-2">
                        <FormField :label="t('common.note')" :error="editForm.errors.note">
                            <template #default="{ id }">
                                <textarea :id="id" v-model="editForm.note" rows="2" maxlength="1000" :disabled="editIsLocked" class="w-full resize-y rounded-md border border-border bg-surface px-2 py-1.5 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40" />
                            </template>
                        </FormField>
                    </div>
                </div>

                <div v-if="editIsFull" class="border-t border-border pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ t('inventory.items_label') }}</h3>
                        <button type="button" class="inline-flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-foreground hover:bg-background" @click="addEditLine">
                            <AppIcon name="plus" size="10" />
                            {{ t('inventory.add_line') }}
                        </button>
                    </div>
                    <p v-if="editForm.errors.items" class="mb-1 text-xs text-danger">{{ editForm.errors.items }}</p>

                    <div class="mb-2 overflow-x-auto rounded-md border border-border">
                        <table class="w-full min-w-[720px] text-[13px]">
                            <thead>
                                <tr class="border-b border-border bg-background text-xs font-semibold text-muted">
                                    <th class="px-2 py-1.5 text-left">{{ t('inventory.ingredient') }}</th>
                                    <th class="px-2 py-1.5 text-left">{{ t('inventory.unit') }}</th>
                                    <th class="px-2 py-1.5 text-right">{{ t('inventory.qty') }}</th>
                                    <th class="px-2 py-1.5 text-right">{{ t('inventory.unit_cost') }}</th>
                                    <th class="px-2 py-1.5 text-right">{{ t('inventory.line_total') }}</th>
                                    <th class="w-8 px-1 py-1.5" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, index) in editForm.items" :key="index" class="border-b border-border last:border-0">
                                    <td class="px-1 py-1 align-top">
                                        <select v-model="line.ingredient_id" :aria-label="t('inventory.ingredient')" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent" :class="editForm.errors['items.' + index + '.ingredient_id'] ? 'border-danger' : ''" @change="onIngredientChange(line)">
                                            <option value="" disabled>{{ t('common.select') }}</option>
                                            <option v-for="option in ingredientsOptions(line)" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        <option value="__new">{{ t('inventory.create_ingredient_option') }}</option>
                                        </select>
                                        <p v-if="editForm.errors['items.' + index + '.ingredient_id']" class="mt-0.5 text-xs text-danger">{{ editForm.errors['items.' + index + '.ingredient_id'] }}</p>
                                    </td>
                                    <td class="w-20 px-1 py-1 align-top">
                                        <select v-model="line.unit_id" :aria-label="t('inventory.unit')" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent" :class="editForm.errors['items.' + index + '.unit_id'] ? 'border-danger' : ''">
                                            <option value="" disabled>---</option>
                                            <option v-for="unit in unitsFor(line)" :key="unit.id" :value="unit.id">{{ unit.symbol }}</option>
                                        </select>
                                    </td>
                                    <td class="w-24 px-1 py-1 align-top">
                                        <input v-model="line.qty" type="text" inputmode="decimal" v-numeric step="0.001" min="0" :aria-label="t('inventory.qty')" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent" :class="editForm.errors['items.' + index + '.qty'] ? 'border-danger' : ''">
                                        <p v-if="editForm.errors['items.' + index + '.qty']" class="mt-0.5 text-xs text-danger">{{ editForm.errors['items.' + index + '.qty'] }}</p>
                                    </td>
                                    <td class="w-32 px-1 py-1 align-top">
                                        <MoneyInput v-model="line.unit_cost" />
                                    </td>
                                    <td class="w-28 px-1 py-1 align-top">
                                        <span class="flex h-7 items-center justify-end tabular-nums">{{ formatMoney(Number(line.qty || 0) * Number(line.unit_cost || 0)) }}</span>
                                    </td>
                                    <td class="w-8 px-1 py-1 align-top">
                                        <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-muted hover:text-danger" :aria-label="t('common.remove')" @click="editForm.items.splice(index, 1)">
                                            <AppIcon name="xmark" size="12" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end border-t border-border pt-2 text-[13px] font-semibold text-foreground">
                        {{ t('common.total') }}: {{ formatMoney(editTotal) }}
                    </div>
                </div>
                <div v-else class="border-t border-border pt-3">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ t('inventory.items_label') }}</h3>
                    <dl class="mb-3 grid grid-cols-2 gap-x-4 gap-y-2 text-[13px]">
                        <div>
                            <dt class="text-xs text-muted">{{ t('inventory.purchased_at') }}</dt>
                            <dd class="text-foreground"><DateText :value="editing.purchased_at" /></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">{{ t('inventory.supplier') }}</dt>
                            <dd class="text-foreground">{{ editing.supplier?.name ?? t('inventory.no_supplier_label') }}</dd>
                        </div>
                    </dl>
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="border-b border-border bg-background text-xs font-semibold text-muted">
                                <th class="px-2 py-1.5 text-left">{{ t('inventory.ingredient') }}</th>
                                <th class="px-2 py-1.5 text-right">{{ t('inventory.qty') }}</th>
                                <th class="px-2 py-1.5 text-right">{{ t('inventory.unit_cost') }}</th>
                                <th class="px-2 py-1.5 text-right">{{ t('inventory.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in editing.items" :key="item.id" class="border-b border-border last:border-0">
                                <td class="px-2 py-1.5 text-foreground">{{ item.ingredient?.name }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums">{{ Number(item.qty) }} {{ item.unit?.symbol }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums">{{ formatMoney(item.unit_cost) }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums">{{ formatMoney(item.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="mt-3 text-right text-sm font-semibold text-foreground">
                        {{ t('common.total') }}: {{ formatMoney(editing.total) }}
                    </div>
                </div>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="editing = null">{{ t('common.cancel') }}</button>
                <button v-if="! editIsLocked" type="button" :disabled="editForm.processing || (editIsFull && editForm.items.length === 0)" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="saveEdit">
                    <AppIcon v-if="editForm.processing" name="spinner" spin />
                    {{ t('inventory.save_changes') }}
                </button>
            </template>
        </SlideOver>

        <Teleport to="body">
            <div v-if="ingredientDialog" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4" @click.self="ingredientDialog = null" @keydown.esc="ingredientDialog = null">
                <form class="w-full max-w-sm space-y-3 rounded-xl border border-border bg-surface p-4" role="dialog" aria-modal="true" :aria-label="t('inventory.quick_ingredient_title')" novalidate @submit.prevent="createIngredient">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">{{ t('inventory.quick_ingredient_title') }}</h2>
                        <p class="text-xs text-muted">{{ t('inventory.quick_ingredient_hint') }}</p>
                    </div>

                    <FormField :label="t('inventory.ingredient')" required :error="ingredientDialog.error">
                        <template #default="{ id }">
                            <input :id="id" v-model="ingredientDialog.name" type="text" maxlength="150" autofocus class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="ingredientDialog.error ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <div class="grid grid-cols-2 gap-x-3">
                        <FormField :label="t('inventory.unit')" required>
                            <template #default="{ id }">
                                <select :id="id" v-model="ingredientDialog.unit_id" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                                    <option value="" disabled>{{ t('common.select') }}</option>
                                    <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }} ({{ unit.symbol }})</option>
                                </select>
                            </template>
                        </FormField>

                        <FormField :label="t('inventory.category')">
                            <template #default="{ id }">
                                <select :id="id" v-model="ingredientDialog.category_id" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                                    <option value="">{{ t('inventory.category_none') }}</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                                </select>
                            </template>
                        </FormField>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="ingredientDialog = null">{{ t('common.cancel') }}</button>
                        <button type="submit" :disabled="ingredientDialog.busy" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                            <AppIcon v-if="ingredientDialog.busy" name="spinner" spin />
                            {{ t('common.save') }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="supplierDialog" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4" @click.self="supplierDialog = null" @keydown.esc="supplierDialog = null">
                <form class="w-full max-w-sm space-y-3 rounded-xl border border-border bg-surface p-4" role="dialog" aria-modal="true" :aria-label="t('inventory.quick_supplier_title')" novalidate @submit.prevent="createSupplier">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">{{ t('inventory.quick_supplier_title') }}</h2>
                        <p class="text-xs text-muted">{{ t('inventory.quick_supplier_hint') }}</p>
                    </div>

                    <FormField :label="t('inventory.supplier')" required :error="supplierDialog.error">
                        <template #default="{ id }">
                            <input :id="id" v-model="supplierDialog.name" type="text" maxlength="150" autofocus class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="supplierDialog.error ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.mobile')">
                        <MobileInput v-model="supplierDialog.mobile" />
                    </FormField>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="supplierDialog = null">{{ t('common.cancel') }}</button>
                        <button type="submit" :disabled="supplierDialog.busy" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                            <AppIcon v-if="supplierDialog.busy" name="spinner" spin />
                            {{ t('common.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
