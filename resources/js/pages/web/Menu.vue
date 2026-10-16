<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DishCard from '@/components/site/DishCard.vue';
import PageHero from '@/components/site/PageHero.vue';
import Reveal from '@/components/site/Reveal.vue';
import { t, currentLocale } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { addToCart, cartCount, cartSubtotal } from '@/lib/cart';
import { localized, menuUrl } from '@/lib/site';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    branch: { type: Object, required: true },
    categories: { type: Array, required: true },
    table: { type: Object, default: null },
    seo: { type: Object, default: null },
});

const mounted = ref(false);
const search = ref('');
const vegOnly = ref(false);
const activeCategory = ref(null);
const sheetItem = ref(null);
const selectedVariant = ref(null);
const selectedAddOns = ref([]);
const chipRail = ref(null);
let observer = null;

const branchName = computed(() => localized(props.branch, 'name'));
const otherBranches = computed(() => (page.props.site?.branches ?? []).filter((entry) => entry.code !== props.branch.code));

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_menu') },
]);

const filteredCategories = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return props.categories
        .map((category) => ({
            ...category,
            items: category.items.filter((item) => (! vegOnly.value || item.is_vegetarian)
                && (! needle
                    || item.name.toLowerCase().includes(needle)
                    || (item.name_bn ?? '').includes(search.value.trim()))),
        }))
        .filter((category) => category.items.length > 0);
});

const dishCount = computed(() => props.categories.reduce((sum, category) => sum + category.items.length, 0));

const sheetTotal = computed(() => {
    if (! sheetItem.value) {
        return 0;
    }

    const addOns = selectedAddOns.value.reduce((sum, addOn) => sum + Number(addOn.price), 0);

    return Number(selectedVariant.value?.price ?? sheetItem.value.base_price) + addOns;
});

function openItem(item) {
    if (item.variants?.length || item.add_ons?.length) {
        sheetItem.value = item;
        selectedVariant.value = item.variants?.find((variant) => variant.is_default) ?? item.variants?.[0] ?? null;
        selectedAddOns.value = [];
    } else {
        addToCart(item);
    }
}

function confirmSheet() {
    addToCart(sheetItem.value, selectedVariant.value, selectedAddOns.value);
    sheetItem.value = null;
}

function toggleAddOn(addOn) {
    if (selectedAddOns.value.some((entry) => entry.id === addOn.id)) {
        selectedAddOns.value = selectedAddOns.value.filter((entry) => entry.id !== addOn.id);
    } else {
        selectedAddOns.value = [...selectedAddOns.value, addOn];
    }
}

function jumpTo(event, category) {
    const target = document.getElementById(`cat-${category.id}`);

    if (! target) {
        return;
    }

    event.preventDefault();
    target.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
}

function observeSections() {
    observer?.disconnect();

    if (! ('IntersectionObserver' in window)) {
        return;
    }

    observer = new IntersectionObserver((entries) => {
        const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];

        if (visible) {
            activeCategory.value = Number(visible.target.dataset.categoryId);
        }
    }, { rootMargin: '-150px 0px -65% 0px' });

    document.querySelectorAll('[data-category-id]').forEach((section) => observer.observe(section));
}

watch(activeCategory, async (id) => {
    await nextTick();
    chipRail.value?.querySelector(`[data-chip="${id}"]`)?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
});

watch(filteredCategories, async () => {
    await nextTick();
    observeSections();
});

function onKeydown(event) {
    if (event.key === 'Escape') {
        sheetItem.value = null;
    }
}

onMounted(() => {
    mounted.value = true;
    observeSections();
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div>
        <PageHero
            :eyebrow="t('site.menu_eyebrow')"
            :title="t('site.seo_menu_page_title', { branch: branchName })"
            :text="t('site.menu_subtitle', { branch: branchName })"
            :breadcrumbs="crumbs"
        >
            <p v-if="table" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-success px-4 py-2 text-sm font-semibold text-white ">
                <AppIcon name="qrcode" size="13" />
                {{ t('web.dine_in_table', { table: table.name }) }}
            </p>

            <div v-if="otherBranches.length" class="mt-6 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-muted">{{ t('site.menu_other_branch') }}</span>
                <Link
                    v-for="other in otherBranches"
                    :key="other.code"
                    :href="menuUrl(other.code)"
                    class="bg-surface border border-border rounded-lg px-3.5 py-1.5 font-medium transition-colors hover:border-accent"
                >
                    {{ localized(other, 'name') }}
                </Link>
            </div>
        </PageHero>

        <!-- Sticky filters -->
        <div class="bg-surface border border-border sticky top-16 z-20 border-x-0 border-t-0">
            <div class="mx-auto max-w-6xl space-y-2 px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <label class="flex h-11 min-w-0 flex-1 items-center gap-2 rounded-lg border border-border bg-surface px-4 shadow-sm focus-within:border-accent">
                        <AppIcon name="magnifying-bg-surface border border-border " size="14" class="shrink-0 text-muted" />
                        <input v-model="search" type="search" :placeholder="t('site.menu_search')" :aria-label="t('site.menu_search')" class="w-full min-w-0 border-0 bg-transparent text-base outline-none placeholder:text-muted/70">
                    </label>

                    <button
                        type="button"
                        class="inline-flex h-11 items-center gap-2 rounded-lg border px-4 text-sm font-semibold transition-colors"
                        :class="vegOnly ? 'border-success bg-success text-white' : 'border-border bg-surface hover:border-success'"
                        :aria-pressed="vegOnly"
                        @click="vegOnly = ! vegOnly"
                    >
                        <AppIcon name="leaf" size="13" />
                        {{ t('site.menu_vegetarian_only') }}
                    </button>
                </div>

                <div ref="chipRail" class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                    <a
                        v-for="category in filteredCategories"
                        :key="category.id"
                        :href="`#cat-${category.id}`"
                        :data-chip="category.id"
                        class="shrink-0 rounded-lg border px-4 py-2 text-sm font-medium transition-all"
                        :class="activeCategory === category.id ? 'border-accent bg-accent text-accent-foreground ' : 'border-border bg-surface text-muted hover:text-foreground'"
                        @click="jumpTo($event, category)"
                    >
                        {{ localized(category, 'name') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Menu sections -->
        <div class="mx-auto max-w-6xl px-4 pb-32 pt-10">
            <p class="text-sm text-muted">{{ t('site.menu_dishes_count', { count: formatMoney(dishCount, { decimals: 0 }) }) }}</p>

            <section
                v-for="category in filteredCategories"
                :id="`cat-${category.id}`"
                :key="category.id"
                :data-category-id="category.id"
                class="scroll-mt-44 pt-8"
            >
                <Reveal as="div">
                    <h2 class="flex items-center gap-3 text-3xl font-bold">
                        {{ localized(category, 'name') }}
                        <span class="h-px flex-1 bg-gradient-to-r from-border to-transparent" aria-hidden="true" />
                    </h2>
                </Reveal>

                <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <Reveal v-for="(item, index) in category.items" :key="item.id" as="li" :delay="(index % 3) * 80">
                        <DishCard :item="item" :branch-code="branch.code" :category-name="category.name" show-add @add="openItem" />
                    </Reveal>
                </ul>
            </section>

            <p v-if="filteredCategories.length === 0" class="mt-16 rounded-xl border border-dashed border-border p-10 text-center text-muted">
                {{ t('site.menu_no_results') }}
            </p>
        </div>

        <!-- Floating cart -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-6 opacity-0"
            leave-active-class="transition duration-200 ease-in"
            leave-to-class="translate-y-6 opacity-0"
        >
            <Link
                v-if="mounted && cartCount() > 0"
                href="/checkout"
                class="fixed inset-x-4 bottom-4 z-30 mx-auto flex h-11 max-w-md items-center justify-between rounded-lg bg-primary px-6 text-primary-foreground sm:left-auto sm:right-6 sm:mx-0"
            >
                <span class="flex items-center gap-2.5 font-semibold">
                    <span class="flex h-7 min-w-7 items-center justify-center rounded-lg bg-accent px-1.5 text-sm font-bold text-accent-foreground">{{ cartCount() }}</span>
                    {{ t('site.menu_view_cart') }}
                </span>
                <span class="font-bold tabular-nums">{{ formatMoney(cartSubtotal(), { decimals: 0 }) }} {{ t('site.currency') }}</span>
            </Link>
        </Transition>

        <!-- Item sheet -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <div v-if="sheetItem" class="site fixed inset-0 z-50 flex items-end justify-center bg-black/60 text-foreground sm:items-center" @click.self="sheetItem = null">
                    <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-[2rem] border border-border bg-surface p-6 sm:rounded-xl" role="dialog" aria-modal="true" :aria-label="localized(sheetItem, 'name')">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-2xl font-bold">{{ localized(sheetItem, 'name') }}</h3>
                            <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-border text-muted hover:text-foreground" :aria-label="t('common.close')" @click="sheetItem = null">
                                <AppIcon name="xmark" size="15" />
                            </button>
                        </div>

                        <p v-if="localized(sheetItem, 'description')" class="mt-2 text-muted">{{ localized(sheetItem, 'description') }}</p>

                        <div v-if="sheetItem.variants?.length" class="mt-5">
                            <p class="text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.item_choose_size') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="variant in sheetItem.variants"
                                    :key="variant.id"
                                    type="button"
                                    class="flex h-10 items-center gap-2 rounded-xl border px-4 text-sm transition-colors"
                                    :class="selectedVariant?.id === variant.id ? 'border-accent bg-accent/15' : 'border-border hover:border-accent/60'"
                                    :aria-pressed="selectedVariant?.id === variant.id"
                                    @click="selectedVariant = variant"
                                >
                                    <span>{{ variant.name }}</span>
                                    <span class="font-semibold tabular-nums">{{ formatMoney(variant.price, { decimals: 0 }) }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="sheetItem.add_ons?.length" class="mt-5">
                            <p class="text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.item_extras') }}</p>
                            <div class="mt-2 space-y-2">
                                <button
                                    v-for="addOn in sheetItem.add_ons"
                                    :key="addOn.id"
                                    type="button"
                                    class="flex min-h-10 w-full items-center justify-between rounded-xl border px-4 py-2 text-sm transition-colors"
                                    :class="selectedAddOns.some((entry) => entry.id === addOn.id) ? 'border-accent bg-accent/15' : 'border-border hover:border-accent/60'"
                                    :aria-pressed="selectedAddOns.some((entry) => entry.id === addOn.id)"
                                    @click="toggleAddOn(addOn)"
                                >
                                    <span class="flex items-center gap-2">
                                        <AppIcon v-if="selectedAddOns.some((entry) => entry.id === addOn.id)" name="check" size="12" class="text-success" />
                                        {{ addOn.name }}
                                    </span>
                                    <span class="font-medium tabular-nums">+{{ formatMoney(addOn.price, { decimals: 0 }) }}</span>
                                </button>
                            </div>
                        </div>

                        <button type="button" class="mt-6 flex h-11 w-full items-center justify-center gap-2.5 rounded-lg bg-accent text-base font-semibold text-accent-foreground active:scale-[0.98]" @click="confirmSheet">
                            <AppIcon name="plus" size="14" />
                            {{ t('web.add_to_cart') }} &middot; {{ formatMoney(sheetTotal, { decimals: 0 }) }} {{ t('site.currency') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
