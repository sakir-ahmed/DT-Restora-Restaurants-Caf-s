<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DishPlaceholder from '@/components/site/DishPlaceholder.vue';
import DishCard from '@/components/site/DishCard.vue';
import MediaViewer from '@/components/site/MediaViewer.vue';
import PageHero from '@/components/site/PageHero.vue';
import Reveal from '@/components/site/Reveal.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { addToCart } from '@/lib/cart';
import { dishKind, localized, menuUrl } from '@/lib/site';

const props = defineProps({
    branch: { type: Object, required: true },
    item: { type: Object, required: true },
    gallery: { type: Array, default: () => [] },
    related: { type: Array, default: () => [] },
    seo: { type: Object, default: null },
});

const selectedVariant = ref(props.item.variants?.find((variant) => variant.is_default) ?? props.item.variants?.[0] ?? null);
const selectedAddOns = ref([]);

const name = computed(() => localized(props.item, 'name'));
const description = computed(() => localized(props.item, 'description'));
const category = computed(() => localized({ name: props.item.category, name_bn: props.item.category_bn }, 'name'));
const total = computed(() => Number(selectedVariant.value?.price ?? props.item.base_price)
    + selectedAddOns.value.reduce((sum, addOn) => sum + Number(addOn.price), 0));

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_menu'), href: menuUrl(props.branch.code) },
    { label: name.value },
]);

function toggleAddOn(addOn) {
    if (selectedAddOns.value.some((entry) => entry.id === addOn.id)) {
        selectedAddOns.value = selectedAddOns.value.filter((entry) => entry.id !== addOn.id);
    } else {
        selectedAddOns.value = [...selectedAddOns.value, addOn];
    }
}

const added = ref(false);
let addedTimer = null;

function add() {
    addToCart(
        { id: props.item.id, name: props.item.name, base_price: props.item.base_price },
        selectedVariant.value,
        selectedAddOns.value,
    );
    added.value = true;
    clearTimeout(addedTimer);
    addedTimer = setTimeout(() => {
        added.value = false;
    }, 1800);
}
</script>

<template>
    <div>
        <PageHero :title="name" :text="category || null" :breadcrumbs="crumbs" />

        <section class="mx-auto grid max-w-6xl items-start gap-10 px-4 py-14 lg:grid-cols-2">
            <Reveal>
                <div class="relative">
                    <MediaViewer v-if="gallery.length" :items="gallery" :name="name" />
                    <div v-else class="relative aspect-square overflow-hidden rounded-xl border border-border bg-surface">
                        <div class="flex h-full w-full items-center justify-center p-12">
                            <div class="h-full w-full"><DishPlaceholder :kind="dishKind(category)" :seed="item.id" /></div>
                        </div>
                    </div>
                </div>
            </Reveal>

            <Reveal :delay="120">
                <p class="text-sm text-muted">
                    <Link :href="menuUrl(branch.code)" class="inline-flex items-center gap-1.5 hover:text-foreground">
                        <AppIcon name="chevron-left" size="10" />
                        {{ t('site.item_back') }}
                    </Link>
                    <span aria-hidden="true"> / </span>{{ localized(branch, 'name') }}
                </p>

                <p v-if="description" class="mt-4 text-base leading-relaxed text-muted">{{ description }}</p>

                <div v-if="item.variants?.length" class="mt-7">
                    <h2 class="font-sans text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.item_choose_size') }}</h2>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="variant in item.variants"
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

                <div v-if="item.add_ons?.length" class="mt-6">
                    <h2 class="font-sans text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.item_extras') }}</h2>
                    <div class="mt-2 space-y-2">
                        <button
                            v-for="addOn in item.add_ons"
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

                <div class="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-surface p-5 shadow-sm">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.item_total') }}</p>
                        <p class="text-3xl font-bold tabular-nums">{{ formatMoney(total, { decimals: 0 }) }} <span class="text-base font-medium text-muted">{{ t('site.currency') }}</span></p>
                    </div>
                    <button type="button" class="inline-flex h-11 items-center gap-2.5 rounded-lg bg-accent px-8 text-base font-semibold text-accent-foreground " @click="add">
                        <AppIcon :name="added ? 'check' : 'cart-shopping'" size="15" />
                        {{ added ? t('site.item_added_short') : t('web.add_to_cart') }}
                    </button>
                </div>
            </Reveal>
        </section>

        <section v-if="related.length" class="border-y border-border bg-surface py-12">
            <div class="mx-auto max-w-6xl px-4">
                <Reveal><SectionHeading :title="t('site.item_related_title')" /></Reveal>
                <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <Reveal v-for="(entry, index) in related" :key="entry.id" as="li" :delay="index * 90">
                        <DishCard :item="entry" :branch-code="branch.code" />
                    </Reveal>
                </ul>
            </div>
        </section>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
