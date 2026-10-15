<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { addToCart, cartCount } from '@/lib/cart';
import { notify } from '@/lib/notify';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    branch: { type: Object, required: true },
    categories: { type: Array, required: true },
    table: { type: Object, default: null },
});

const search = ref('');
const activeCategory = ref(null);
const sheetItem = ref(null);
const selectedVariant = ref(null);
const selectedAddOns = ref([]);

const filteredCategories = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return props.categories
        .map((category) => ({
            ...category,
            items: category.items.filter((item) => ! needle
                || item.name.toLowerCase().includes(needle)
                || (item.name_bn ?? '').includes(search.value.trim())),
        }))
        .filter((category) => category.items.length > 0);
});

const sheetTotal = computed(() => {
    if (! sheetItem.value) {
        return 0;
    }

    const addOns = selectedAddOns.value.reduce((sum, addOn) => sum + Number(addOn.price), 0);

    return (Number(selectedVariant.value?.price ?? sheetItem.value.base_price) + addOns);
});

function openItem(item) {
    if (item.variants?.length || item.add_ons?.length) {
        sheetItem.value = item;
        selectedVariant.value = item.variants?.find((v) => v.is_default) ?? item.variants?.[0] ?? null;
        selectedAddOns.value = [];
    } else {
        addToCart(item);
        notify.success(t('web.added_to_cart', { name: item.name }));
    }
}

function confirmSheet() {
    addToCart(sheetItem.value, selectedVariant.value, selectedAddOns.value);
    notify.success(t('web.added_to_cart', { name: sheetItem.value.name }));
    sheetItem.value = null;
}

function toggleAddOn(addOn) {
    const exists = selectedAddOns.value.find((entry) => entry.id === addOn.id);

    if (exists) {
        selectedAddOns.value = selectedAddOns.value.filter((entry) => entry.id !== addOn.id);
    } else {
        selectedAddOns.value.push(addOn);
    }
}
</script>

<template>
    <Head :title="t('web.seo_menu_title')" />

    <div>
        <header class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="text-xl font-bold">{{ branch.name }}</h1>
                <p v-if="table" class="mt-0.5 flex items-center gap-1 text-sm text-muted">
                    <AppIcon name="utensils" size="12" />
                    {{ t('web.dine_in_table', { table: table.name }) }}
                </p>
            </div>
            <Link href="/checkout" class="inline-flex h-10 items-center gap-2 rounded-lg bg-accent px-4 text-sm font-semibold text-accent-foreground">
                <AppIcon name="cart-shopping" size="14" />
                {{ t('web.view_cart') }} ({{ cartCount() }})
            </Link>
        </header>

        <div class="sticky top-14 z-20 -mx-4 mt-4 bg-background/95 px-4 py-2 backdrop-blur">
            <div class="flex h-10 items-center gap-2 rounded-lg border border-border bg-surface px-3">
                <AppIcon name="magnifying-glass" size="14" class="text-muted" />
                <input v-model="search" type="search" :placeholder="t('web.search_menu')" class="w-full border-0 bg-transparent text-sm outline-none placeholder:text-muted/60">
            </div>

            <div class="thin-scrollbar mt-2 flex gap-1.5 overflow-x-auto pb-1">
                <button
                    type="button"
                    class="h-8 shrink-0 rounded-full border px-3 text-xs font-medium"
                    :class="activeCategory === null ? 'border-accent bg-accent/15 text-accent-foreground' : 'border-border bg-surface text-muted'"
                    @click="activeCategory = null"
                >
                    {{ t('menu.all_items') }}
                </button>
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="h-8 shrink-0 rounded-full border px-3 text-xs font-medium"
                    :class="activeCategory === category.id ? 'border-accent bg-accent/15 text-accent-foreground' : 'border-border bg-surface text-muted'"
                    @click="activeCategory = category.id"
                >
                    {{ category.name }}
                </button>
            </div>
        </div>

        <section v-for="category in filteredCategories" :key="category.id" class="mt-5">
            <h2 v-if="activeCategory === null" class="text-base font-semibold">{{ category.name }}</h2>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <button
                    v-for="item in (activeCategory === null || activeCategory === category.id) ? category.items : []"
                    :key="item.id"
                    type="button"
                    class="flex flex-col rounded-xl border border-border bg-surface p-3 text-left transition-shadow hover:shadow-md"
                    @click="openItem(item)"
                >
                    <span class="line-clamp-2 text-sm font-medium">{{ item.name }}</span>
                    <span v-if="item.is_vegetarian" class="mt-1 w-fit rounded bg-success/10 px-1.5 py-0.5 text-[10px] font-medium text-success">{{ t('menu.is_vegetarian') }}</span>
                    <span class="mt-auto flex items-center justify-between pt-2">
                        <span class="text-sm font-bold tabular-nums">{{ formatMoney(item.base_price) }}</span>
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-accent text-accent-foreground">
                            <AppIcon name="plus" size="12" />
                        </span>
                    </span>
                </button>
            </div>
        </section>

        <!-- Item sheet -->
        <Teleport to="body">
            <div v-if="sheetItem" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center" @click.self="sheetItem = null">
                <div class="w-full max-w-md rounded-t-2xl border border-border bg-surface p-4 sm:rounded-2xl">
                    <div class="flex items-start justify-between">
                        <h3 class="text-lg font-semibold">{{ sheetItem.name }}</h3>
                        <button type="button" class="p-1 text-muted" :aria-label="t('common.close')" @click="sheetItem = null">
                            <AppIcon name="xmark" size="16" />
                        </button>
                    </div>

                    <p v-if="sheetItem.description" class="mt-1 text-sm text-muted">{{ sheetItem.description }}</p>

                    <div v-if="sheetItem.variants?.length" class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ t('menu.variants') }}</p>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            <button
                                v-for="variant in sheetItem.variants"
                                :key="variant.id"
                                type="button"
                                class="flex h-10 items-center gap-2 rounded-lg border px-3 text-sm"
                                :class="selectedVariant?.id === variant.id ? 'border-accent bg-accent/15' : 'border-border'"
                                @click="selectedVariant = variant"
                            >
                                <span>{{ variant.name }}</span>
                                <span class="font-semibold tabular-nums">{{ formatMoney(variant.price) }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="sheetItem.add_ons?.length" class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ t('menu.addons') }}</p>
                        <div class="mt-1.5 space-y-1">
                            <button
                                v-for="addOn in sheetItem.add_ons"
                                :key="addOn.id"
                                type="button"
                                class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-sm"
                                :class="selectedAddOns.some((entry) => entry.id === addOn.id) ? 'border-accent bg-accent/15' : 'border-border'"
                                @click="toggleAddOn(addOn)"
                            >
                                <span class="flex items-center gap-2">
                                    <AppIcon v-if="selectedAddOns.some((entry) => entry.id === addOn.id)" name="check" size="12" class="text-success" />
                                    {{ addOn.name }}
                                </span>
                                <span class="font-medium tabular-nums">+{{ formatMoney(addOn.price) }}</span>
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-5 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-accent text-base font-semibold text-accent-foreground"
                        @click="confirmSheet"
                    >
                        <AppIcon name="plus" size="14" />
                        {{ t('web.add_to_cart') }} - {{ formatMoney(sheetTotal) }}
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
