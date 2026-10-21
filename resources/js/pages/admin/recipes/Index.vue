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
import { router } from '@inertiajs/vue3';

const props = defineProps({
    items: { type: Array, required: true },
    ingredients: { type: Array, required: true },
    costs: { type: Object, default: () => ({}) },
});

const slideOverOpen = ref(false);
const editingItem = ref(null);

const columns = computed(() => [
    { key: 'name', label: t('menu.item'), align: 'left', sortable: true },
    { key: 'category_name', label: t('menu.category'), align: 'left' },
    { key: 'price_display', label: t('menu.price'), type: 'money', sortable: true, minWidth: '96px' },
    { key: 'recipe_status', label: t('inventory.recipes'), align: 'left' },
    { key: 'lines_count', label: t('inventory.lines'), type: 'number', minWidth: '64px' },
    { key: 'actions', label: '', align: 'right', minWidth: '96px' },
]);

const missingCount = computed(() => props.items.filter((item) => ! item.recipe).length);

const rows = computed(() => props.items.map((item) => ({
    ...item,
    category_name: item.category?.name,
    price_display: formatMoney(item.base_price),
    sort_price: Number(item.base_price),
    recipe_status: item.recipe ? 'set' : 'none',
    lines_count: item.recipe?.items?.length ?? 0,
})));

const form = useForm({
    menu_item_id: '',
    note: '',
    lines: [],
});

function openBuilder(item) {
    editingItem.value = item;
    form.defaults({
        menu_item_id: item.id,
        note: item.recipe?.note ?? '',
        lines: (item.recipe?.items ?? []).map((line) => ({
            ingredient_id: line.ingredient_id,
            qty: Number(line.qty),
        })),
    }).reset();
    slideOverOpen.value = true;

    if (! item.recipe) {
        addLine();
    }
}

const usedIngredients = computed(() => form.lines.map((line) => Number(line.ingredient_id)));

function addLine() {
    form.lines.push({ ingredient_id: '', qty: '1' });
}

function ingredientName(id) {
    return props.ingredients.find((ingredient) => ingredient.id === Number(id))?.name ?? '';
}

/**
 * Recipes, stock and costs are all kept in the base unit (grams for
 * kilograms), so that is the unit to show next to a quantity.
 */
function baseSymbol(ingredient) {
    return ingredient?.unit?.base_unit?.symbol ?? ingredient?.unit?.symbol ?? '';
}

function ingredientUnit(id) {
    return baseSymbol(props.ingredients.find((ingredient) => ingredient.id === Number(id)));
}

const sellingPrice = computed(() => {
    const item = editingItem.value;

    if (! item) {
        return 0;
    }

    const variants = (item.variants ?? []).map((variant) => Number(variant.price)).filter((price) => price > 0);

    return variants.length > 0 ? Math.min(...variants) : Number(item.base_price);
});

/**
 * Cost of the lines as they are typed, from the average purchase cost of
 * each ingredient in this branch. No save needed.
 */
const costing = computed(() => {
    const lines = form.lines
        .filter((line) => line.ingredient_id !== '' && Number(line.qty) > 0)
        .map((line) => {
            const ingredient = props.ingredients.find((entry) => entry.id === Number(line.ingredient_id));
            const unitCost = Number(props.costs[line.ingredient_id] ?? 0);

            return {
                key: line.ingredient_id,
                name: ingredient?.name ?? '',
                unit: baseSymbol(ingredient),
                qty: Number(line.qty),
                unitCost,
                lineCost: Number(line.qty) * unitCost,
            };
        });

    const total = lines.reduce((sum, line) => sum + line.lineCost, 0);
    const price = sellingPrice.value;

    return {
        lines,
        total,
        price,
        percent: price > 0 ? (total / price) * 100 : null,
        margin: price - total,
        missingCost: lines.filter((line) => line.unitCost === 0).map((line) => line.name),
    };
});

function save() {
    form.post(route('admin.recipes.store'), {
        onSuccess: (page) => {
            notify.success(String(page.props.flash?.success ?? t('inventory.recipe_saved')));
            slideOverOpen.value = false;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('inventory.recipes')" :description="t('inventory.recipes_subtitle')">
            <template #actions>
                <span v-if="missingCount > 0" class="text-xs text-warning">{{ t('inventory.recipes_missing', { count: missingCount }) }}</span>
                <span v-else class="text-xs text-muted">{{ t('inventory.recipes_hint') }}</span>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="rows"
            searchable
            clickable
            @row-click="openBuilder"
            :empty-title="t('menu.empty')"
            :empty-description="t('menu.empty_hint')"
        >
            <template #cell(name)="{ row }">
                <span class="block truncate font-medium">{{ row.name }}</span>
                <span v-if="row.name_bn" class="block truncate text-xs text-muted">{{ row.name_bn }}</span>
            </template>
            <template #cell(recipe_status)="{ value }">
                <StatusBadge :status="value === 'set' ? 'active' : 'draft'" />
            </template>
            <template #cell(actions)="{ row }">
                <button type="button" class="inline-flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click.stop="openBuilder(row)">
                    <AppIcon name="pen-to-square" size="10" />
                    {{ t('inventory.edit_recipe') }}
                </button>
            </template>
            <template #cell(lines_count)="{ row, value }">
                <span v-if="row.recipe_status === 'set'" class="tabular-nums">{{ value }}</span>
                <span v-else class="text-muted">&ndash;</span>
            </template>
        </DataTable>

        <SlideOver :open="slideOverOpen" :title="t('inventory.recipes') + ': ' + (editingItem?.name ?? '')" wide @close="slideOverOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="save">
                <div class="border-b border-border pb-3">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ t('inventory.lines') }}</h3>
                        <button type="button" class="inline-flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-foreground hover:bg-background" @click="addLine">
                            <AppIcon name="plus" size="10" />
                            {{ t('inventory.add_recipe_line') }}
                        </button>
                    </div>

                    <div v-for="(line, index) in form.lines" :key="index" class="mb-2 grid grid-cols-[1.6fr_0.8fr_28px] items-start gap-2">
                        <FormField :error="form.errors['lines.' + index + '.ingredient_id']">
                            <template #default="{ id }">
                                <select :id="id" v-model="line.ingredient_id" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent">
                                    <option value="" disabled>{{ t('common.select') }}</option>
                                    <option v-for="ingredient in ingredients" :key="ingredient.id" :value="ingredient.id" :disabled="usedIngredients.includes(ingredient.id) && Number(line.ingredient_id) !== ingredient.id">{{ ingredient.name }} ({{ baseSymbol(ingredient) }})</option>
                                </select>
                            </template>
                        </FormField>

                        <FormField :error="form.errors['lines.' + index + '.qty']">
                            <template #default="{ id }">
                                <div class="relative">
                                    <input :id="id" v-model="line.qty" type="number" step="0.000001" min="0" class="h-7 w-full rounded-md border border-border bg-surface py-0 pl-1.5 pr-7 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                                    <span class="pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2 text-[11px] text-muted">{{ ingredientUnit(line.ingredient_id) }}</span>
                                </div>
                            </template>
                        </FormField>

                        <div class="flex h-7 items-center">
                            <button type="button" class="flex h-6 w-6 items-center justify-center rounded text-muted hover:text-danger" :aria-label="t('common.remove')" @click="form.lines.splice(index, 1)">
                                <AppIcon name="xmark" size="12" />
                            </button>
                        </div>
                    </div>

                    <p v-if="form.lines.length === 0" class="text-[13px] text-muted">{{ editingItem?.recipe ? t('inventory.recipe_removed_hint') : t('inventory.no_recipe') }}</p>
                </div>

                <FormField :label="t('common.note')" :error="form.errors.note">
                    <template #default="{ id }">
                        <textarea :id="id" v-model="form.note" rows="2" class="w-full resize-y rounded-md border border-border bg-surface px-2 py-1 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40" />
                    </template>
                </FormField>

                <div class="rounded-lg border border-border bg-background/50 p-3">
                    <h3 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">
                        <AppIcon name="sack-dollar" size="12" />
                        {{ t('inventory.current_cost') }}
                    </h3>

                    <p v-if="costing.lines.length === 0" class="text-[13px] text-muted">{{ t('inventory.cost_empty') }}</p>

                    <template v-else>
                        <table class="w-full text-[13px]">
                            <tbody>
                                <tr v-for="line in costing.lines" :key="line.key" class="border-b border-border/50">
                                    <td class="py-1 text-foreground">{{ line.name }}</td>
                                    <td class="py-1 text-right tabular-nums text-muted">{{ line.qty }} {{ line.unit }} x {{ line.unitCost.toFixed(2) }} / {{ line.unit }}</td>
                                    <td class="py-1 text-right tabular-nums text-foreground" style="min-width: 80px">{{ formatMoney(line.lineCost) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-border font-semibold">
                                    <td class="py-1.5 text-foreground" colspan="2">{{ t('inventory.food_cost') }}</td>
                                    <td class="py-1.5 text-right tabular-nums text-foreground">{{ formatMoney(costing.total) }}</td>
                                </tr>
                                <tr>
                                    <td class="py-1 text-muted" colspan="2">{{ t('inventory.selling_price') }}</td>
                                    <td class="py-1 text-right tabular-nums text-foreground">{{ formatMoney(costing.price) }}</td>
                                </tr>
                                <tr>
                                    <td class="py-1 text-muted" colspan="2">{{ t('inventory.margin') }}</td>
                                    <td class="py-1 text-right tabular-nums" :class="costing.margin < 0 ? 'font-semibold text-danger' : 'text-foreground'">{{ formatMoney(costing.margin) }}</td>
                                </tr>
                                <tr>
                                    <td class="py-1 text-muted" colspan="2">{{ t('inventory.food_cost_percent') }}</td>
                                    <td class="py-1 text-right tabular-nums" :class="costing.percent > 40 ? 'font-semibold text-danger' : 'text-success'">
                                        {{ costing.percent === null ? '\u2013' : costing.percent.toFixed(1) + '%' }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>

                        <p v-if="costing.missingCost.length > 0" class="mt-2 text-xs text-warning">
                            {{ t('inventory.cost_missing', { names: costing.missingCost.join(', ') }) }}
                        </p>
                    </template>
                </div>
            </form>

            <template #footer>
                <button type="button" :disabled="form.processing || (form.lines.length === 0 && ! editingItem?.recipe)" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="save">
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
