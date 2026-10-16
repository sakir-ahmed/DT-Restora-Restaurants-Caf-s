<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import QuantityStepper from '@/components/ui-kit/QuantityStepper.vue';
import SkeletonRows from '@/components/ui-kit/SkeletonRows.vue';
import MediaManager from '@/components/admin/MediaManager.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    categories: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    canViewMedia: { type: Boolean, default: false },
    canManageMedia: { type: Boolean, default: false },
});

const confirm = useConfirm();

const loading = ref(false);
const search = ref('');
const activeCategory = ref(null);

const filteredCategories = computed(() => {
    const needle = search.value.trim().toLowerCase();

    if (! needle) {
        return props.categories;
    }

    return props.categories
        .map((category) => ({
            ...category,
            items: category.items.filter((item) => item.name.toLowerCase().includes(needle)
                || (item.name_bn ?? '').includes(search.value.trim())
                || (item.barcode ?? '').includes(needle)),
        }))
        .filter((category) => category.items.length > 0 || category.name.toLowerCase().includes(needle));
});

// Category dialog
const categoryOpen = ref(false);
const editingCategoryId = ref(null);
const categoryForm = useForm({ name: '', name_bn: '', sort_order: 0, is_active: true });

function openCategory(category = null) {
    editingCategoryId.value = category?.id ?? null;
    categoryForm.defaults({
        name: category?.name ?? '',
        name_bn: category?.name_bn ?? '',
        sort_order: category?.sort_order ?? 0,
        is_active: category?.is_active ?? true,
    }).reset();
    categoryOpen.value = true;
}

function saveCategory() {
    if (editingCategoryId.value === null) {
        categoryForm.post(route('admin.menu.categories.store'), {
            onSuccess: () => {
                categoryOpen.value = false;
                notify.success(t('menu.category_created'));
            },
        });
    } else {
        categoryForm.put(route('admin.menu.categories.update', editingCategoryId.value), {
            onSuccess: () => {
                categoryOpen.value = false;
                notify.success(t('menu.category_updated'));
            },
        });
    }
}

async function removeCategory(category) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('menu.category_delete_title', { name: category.name }),
        message: t('menu.category_delete_message'),
        confirmText: t('menu.category_delete'),
    });

    if (! result.confirmed) {
        return;
    }

    router.delete(route('admin.menu.categories.destroy', category.id), {
        onSuccess: () => notify.success(t('menu.category_deleted')),
        onError: () => notify.error(t('menu.category_delete_not_empty')),
    });
}

// Item dialog
const itemOpen = ref(false);
const editingItemId = ref(null);

const emptyItem = () => ({
    menu_category_id: '',
    name: '', name_bn: '', description: '', description_bn: '',
    barcode: '', base_price: '0.00', is_available: true, is_vegetarian: false,
    sort_order: 0,
    variants: [],
    addons: [],
});

const itemForm = useForm(emptyItem());

// Photos and videos of the dish being edited. Read from the page props so the
// list refreshes after every upload without closing the form.
const editingItemMedia = computed(() => {
    for (const category of props.categories) {
        const found = category.items.find((entry) => entry.id === editingItemId.value);

        if (found) {
            return found.media_gallery ?? [];
        }
    }

    return [];
});

function openItem(item = null, categoryId = null) {
    editingItemId.value = item?.id ?? null;
    const defaults = item
        ? {
            menu_category_id: item.menu_category_id,
            name: item.name,
            name_bn: item.name_bn ?? '',
            description: item.description ?? '',
            description_bn: item.description_bn ?? '',
            barcode: item.barcode ?? '',
            base_price: item.base_price,
            is_available: item.is_available,
            is_vegetarian: item.is_vegetarian,
            sort_order: item.sort_order,
            variants: (item.variants ?? []).map((variant) => ({ ...variant })),
            addons: (item.add_ons ?? []).map((addOn) => ({ ...addOn })),
        }
        : { ...emptyItem(), menu_category_id: categoryId ?? '' };

    itemForm.defaults(defaults).reset();
    itemOpen.value = true;
}

function saveItem() {
    const options = {
        onSuccess: () => {
            itemOpen.value = false;
            notify.success(editingItemId.value === null ? t('menu.item_created') : t('menu.item_updated'));
        },
    };

    if (editingItemId.value === null) {
        itemForm.post(route('admin.menu.items.store'), options);
    } else {
        itemForm.put(route('admin.menu.items.update', editingItemId.value), options);
    }
}

async function removeItem(item) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('menu.item_delete_title', { name: item.name }),
        message: t('menu.item_delete_message'),
        confirmText: t('menu.item_delete'),
    });

    if (! result.confirmed) {
        return;
    }

    router.delete(route('admin.menu.items.destroy', item.id), {
        onSuccess: () => notify.success(t('menu.item_deleted')),
    });
}

function addVariant() {
    itemForm.variants.push({ id: null, name: '', name_bn: '', price: '0.00', is_default: itemForm.variants.length === 0, sort_order: itemForm.variants.length + 1 });
}

function addAddOn() {
    itemForm.addons.push({ id: null, name: '', name_bn: '', price: '0.00', is_active: true, sort_order: itemForm.addons.length + 1 });
}

function setDefaultVariant(index) {
    itemForm.variants.forEach((variant, i) => {
        variant.is_default = i === index;
    });
}

function reload() {
    loading.value = true;
    router.reload({ onFinish: () => (loading.value = false) });
}
</script>

<template>
    <div>
        <PageHeader :title="t('menu.title')" :description="t('menu.subtitle')">
            <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background"
                    @click="openCategory()"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('menu.add_category') }}
                </button>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="openItem()"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('menu.add_item') }}
                </button>
            </template>
        </PageHeader>

        <div class="mb-3 flex h-7 max-w-xs items-center gap-1.5 rounded-md border border-border bg-surface px-2">
            <AppIcon name="magnifying-glass" size="12" class="text-muted" />
            <input
                v-model="search"
                type="search"
                :placeholder="t('menu.search_items')"
                class="w-full border-0 bg-transparent text-[13px] text-foreground outline-none placeholder:text-muted/60"
            >
        </div>

        <SkeletonRows v-if="loading" :rows="6" />

        <div v-else-if="filteredCategories.length === 0" class="rounded-lg border border-border bg-surface p-8 text-center">
            <AppIcon name="book-open" size="24" class="text-muted" />
            <p class="mt-2 text-sm font-semibold text-foreground">{{ t('menu.empty') }}</p>
            <p class="mt-1 text-[13px] text-muted">{{ t('menu.empty_hint') }}</p>
        </div>

        <div v-else class="space-y-4">
            <section
                v-for="category in filteredCategories"
                :key="category.id"
                class="overflow-hidden rounded-lg border border-border bg-surface"
            >
                <header class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
                    <h2 class="text-sm font-semibold text-foreground">
                        {{ category.name }}
                        <span v-if="category.name_bn" class="ml-1 text-xs font-normal text-muted">{{ category.name_bn }}</span>
                    </h2>

                    <div class="flex items-center gap-1">
                        <span class="text-xs text-muted">{{ category.items.length }} {{ t('menu.items_count') }}</span>
                        <template v-if="canManage">
                            <button
                                type="button"
                                class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-background hover:text-foreground"
                                :aria-label="t('common.edit')"
                                @click="openCategory(category)"
                            >
                                <AppIcon name="pen-to-square" size="12" />
                            </button>
                            <button
                                type="button"
                                class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-danger/10 hover:text-danger"
                                :aria-label="t('common.delete')"
                                @click="removeCategory(category)"
                            >
                                <AppIcon name="trash" size="12" />
                            </button>
                        </template>
                    </div>
                </header>

                <div v-if="category.items.length === 0" class="px-3 py-4 text-center text-[13px] text-muted">
                    {{ t('menu.category_empty') }}
                </div>

                <table v-else class="w-full text-[13px]">
                    <thead>
                        <tr class="text-left text-xs font-semibold text-muted">
                            <th class="px-3 py-2">{{ t('menu.item') }}</th>
                            <th class="px-3 py-2 text-right" style="min-width: 96px">{{ t('menu.price') }}</th>
                            <th class="px-3 py-2" style="min-width: 104px">{{ t('common.status') }}</th>
                            <th v-if="canManage" class="w-10 px-3 py-2 text-right" style="width: 40px" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in category.items"
                            :key="item.id"
                            class="border-t border-border/60 hover:bg-background/60"
                            :class="canManage ? 'cursor-pointer' : ''"
                            @click="canManage && openItem(item)"
                        >
                            <td class="px-3 py-1.5">
                                <span class="block truncate font-medium text-foreground">{{ item.name }}</span>
                                <span v-if="item.name_bn" class="block truncate text-xs text-muted">{{ item.name_bn }}</span>
                                <span v-if="(item.variants ?? []).length" class="text-xs text-muted">
                                    {{ item.variants.length }} {{ t('menu.variants_count') }}
                                </span>
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-foreground">{{ formatMoney(item.base_price) }}</td>
                            <td class="px-3 py-1.5">
                                <StatusBadge :status="item.is_available ? 'active' : 'inactive'" />
                            </td>
                            <td v-if="canManage" class="px-3 py-1.5 text-right" @click.stop>
                                <button
                                    type="button"
                                    class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-danger/10 hover:text-danger"
                                    :aria-label="t('common.delete')"
                                    @click="removeItem(item)"
                                >
                                    <AppIcon name="trash" size="12" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <!-- Category dialog -->
        <SlideOver :open="categoryOpen" :title="editingCategoryId === null ? t('menu.add_category') : t('menu.edit_category')" @close="categoryOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="saveCategory">
                <FormField :label="t('menu.category_name')" required :error="categoryForm.errors.name">
                    <template #default="{ id }">
                        <input :id="id" v-model="categoryForm.name" type="text" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="categoryForm.errors.name ? 'border-danger' : 'border-border focus:border-accent'">
                    </template>
                </FormField>

                <FormField :label="t('branches.name_bn')" :error="categoryForm.errors.name_bn">
                    <template #default="{ id }">
                        <input :id="id" v-model="categoryForm.name_bn" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                    </template>
                </FormField>

                <FormField :label="t('menu.sort_order')" :error="categoryForm.errors.sort_order">
                    <template #default="{ id }">
                        <input :id="id" v-model="categoryForm.sort_order" type="number" min="0" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                    </template>
                </FormField>

                <SwitchField v-model="categoryForm.is_active" :label="t('branches.is_active')" />
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="categoryOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="categoryForm.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="saveCategory">
                    <AppIcon v-if="categoryForm.processing" name="spinner" spin />
                    {{ t('common.save') }}
                </button>
            </template>
        </SlideOver>

        <!-- Item dialog -->
        <SlideOver :open="itemOpen" :title="editingItemId === null ? t('menu.add_item') : t('menu.edit_item')" wide @close="itemOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="saveItem">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('menu.category')" required :error="itemForm.errors.menu_category_id">
                        <template #default="{ id }">
                            <select :id="id" v-model="itemForm.menu_category_id" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="itemForm.errors.menu_category_id ? 'border-danger' : 'border-border focus:border-accent'">
                                <option value="" disabled>{{ t('common.select') }}</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </template>
                    </FormField>

                    <FormField :label="t('menu.barcode')" :helper="t('menu.barcode_helper')" :error="itemForm.errors.barcode">
                        <template #default="{ id }">
                            <input :id="id" v-model="itemForm.barcode" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] tabular-nums text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>

                    <FormField :label="t('menu.item_name')" required :error="itemForm.errors.name">
                        <template #default="{ id }">
                            <input :id="id" v-model="itemForm.name" type="text" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="itemForm.errors.name ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('branches.name_bn')" :error="itemForm.errors.name_bn">
                        <template #default="{ id }">
                            <input :id="id" v-model="itemForm.name_bn" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>

                    <div class="col-span-2">
                        <FormField :label="t('menu.description')" :error="itemForm.errors.description">
                            <template #default="{ id }">
                                <textarea :id="id" v-model="itemForm.description" rows="2" class="w-full resize-y rounded-md border border-border bg-surface px-2 py-1 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40" />
                            </template>
                        </FormField>
                    </div>

                    <FormField :label="t('menu.price')" required :error="itemForm.errors.base_price">
                        <MoneyInput v-model="itemForm.base_price" />
                    </FormField>

                    <FormField :label="t('menu.sort_order')" :error="itemForm.errors.sort_order">
                        <template #default="{ id }">
                            <input :id="id" v-model="itemForm.sort_order" type="number" min="0" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>
                </div>

                <div class="flex gap-4">
                    <SwitchField v-model="itemForm.is_available" :label="t('menu.is_available')" />
                    <SwitchField v-model="itemForm.is_vegetarian" :label="t('menu.is_vegetarian')" />
                </div>

                <!-- Variants -->
                <div class="border-t border-border pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ t('menu.variants') }}</h3>
                        <button type="button" class="inline-flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-foreground hover:bg-background" @click="addVariant">
                            <AppIcon name="plus" size="10" />
                            {{ t('menu.add_variant') }}
                        </button>
                    </div>

                    <div v-for="(variant, index) in itemForm.variants" :key="index" class="mb-2 grid grid-cols-[1fr_1fr_120px_32px] items-start gap-2">
                        <FormField :error="itemForm.errors['variants.' + index + '.name']">
                            <template #default="{ id }">
                                <input :id="id" v-model="variant.name" :placeholder="t('menu.variant_name')" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent">
                            </template>
                        </FormField>
                        <FormField>
                            <template #default="{ id }">
                                <input :id="id" v-model="variant.name_bn" :placeholder="t('branches.name_bn')" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent">
                            </template>
                        </FormField>
                        <FormField :error="itemForm.errors['variants.' + index + '.price']">
                            <MoneyInput v-model="variant.price" />
                        </FormField>
                        <div class="flex h-7 items-center gap-1">
                            <button
                                type="button"
                                class="flex h-6 w-6 items-center justify-center rounded"
                                :class="variant.is_default ? 'text-success' : 'text-muted hover:text-foreground'"
                                :aria-label="t('menu.default_variant')"
                                :title="t('menu.default_variant')"
                                @click="setDefaultVariant(index)"
                            >
                                <AppIcon name="circle-check" size="14" />
                            </button>
                            <button type="button" class="flex h-6 w-6 items-center justify-center rounded text-muted hover:text-danger" :aria-label="t('common.remove')" @click="itemForm.variants.splice(index, 1)">
                                <AppIcon name="xmark" size="12" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Add-ons -->
                <div class="border-t border-border pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ t('menu.addons') }}</h3>
                        <button type="button" class="inline-flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-foreground hover:bg-background" @click="addAddOn">
                            <AppIcon name="plus" size="10" />
                            {{ t('menu.add_addon') }}
                        </button>
                    </div>

                    <div v-for="(addOn, index) in itemForm.addons" :key="index" class="mb-2 grid grid-cols-[1fr_1fr_120px_32px] items-start gap-2">
                        <FormField :error="itemForm.errors['addons.' + index + '.name']">
                            <template #default="{ id }">
                                <input :id="id" v-model="addOn.name" :placeholder="t('menu.addon_name')" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent">
                            </template>
                        </FormField>
                        <FormField>
                            <template #default="{ id }">
                                <input :id="id" v-model="addOn.name_bn" :placeholder="t('branches.name_bn')" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent">
                            </template>
                        </FormField>
                        <FormField :error="itemForm.errors['addons.' + index + '.price']">
                            <MoneyInput v-model="addOn.price" />
                        </FormField>
                        <div class="flex h-7 items-center">
                            <button type="button" class="flex h-6 w-6 items-center justify-center rounded text-muted hover:text-danger" :aria-label="t('common.remove')" @click="itemForm.addons.splice(index, 1)">
                                <AppIcon name="xmark" size="12" />
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Photos and videos sit outside the dish form so their own forms are not nested inside it -->
            <div v-if="canViewMedia" class="mt-3 border-t border-border pt-3">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ t('media.section') }}</h3>
                <p class="mb-2 mt-0.5 text-[13px] text-muted">{{ editingItemId === null ? t('media.save_first') : t('media.section_hint') }}</p>

                <MediaManager v-if="editingItemId !== null" :owner="`item:${editingItemId}`" :media="editingItemMedia" :can-manage="canManageMedia" compact />
            </div>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="itemOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="itemForm.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="saveItem">
                    <AppIcon v-if="itemForm.processing" name="spinner" spin />
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
