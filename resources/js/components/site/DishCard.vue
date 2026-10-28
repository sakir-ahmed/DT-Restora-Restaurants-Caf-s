<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DishPlaceholder from './DishPlaceholder.vue';
import TiltCard from './TiltCard.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { dishKind, itemUrl, localized, photoSrc } from '@/lib/site';

const props = defineProps({
    item: { type: Object, required: true },
    branchCode: { type: String, required: true },
    showAdd: { type: Boolean, default: false },
    categoryName: { type: String, default: null },
    imageFit: { type: String, default: 'cover' },
});

const emit = defineEmits(['add']);

const name = computed(() => localized(props.item, 'name'));
const description = computed(() => localized(props.item, 'description'));
const photo = computed(() => props.item.cover_url ?? photoSrc(props.item.photo_path));
const kind = computed(() => dishKind(props.categoryName ?? props.item.category?.name ?? props.item.category ?? ''));
const hasVariants = computed(() => (props.item.variants?.length ?? 0) > 0);
const lowestPrice = computed(() => {
    const prices = (props.item.variants ?? []).map((variant) => Number(variant.price));

    return prices.length ? Math.min(...prices) : Number(props.item.base_price);
});
</script>

<template>
    <TiltCard as="article" class="group flex h-full flex-col overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
        <Link :href="itemUrl(branchCode, item.id)" class="relative block aspect-[4/3] overflow-hidden bg-surface" :aria-label="name" tabindex="-1">
            <img
                v-if="photo"
                :src="photo"
                :alt="name"
                width="640"
                height="480"
                loading="lazy"
                decoding="async"
                :class="imageFit === 'contain' ? 'h-full w-full object-contain p-3' : 'h-full w-full object-cover'"
            >
            <div v-else class="h-full w-full p-3">
                <DishPlaceholder :kind="kind" :seed="item.id" />
            </div>

            <span v-if="item.is_vegetarian" class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-lg bg-success px-2.5 py-1 text-[11px] font-semibold text-white">
                <AppIcon name="leaf" size="10" />
                {{ t('site.item_vegetarian') }}
            </span>
        </Link>

        <div class="flex flex-1 flex-col p-4">
            <h3 class="text-lg font-semibold leading-snug">
                <Link :href="itemUrl(branchCode, item.id)" class="hover:underline">{{ name }}</Link>
            </h3>
            <p v-if="description" class="mt-1 line-clamp-2 text-sm leading-relaxed text-muted">{{ description }}</p>

            <div class="mt-auto flex items-center justify-between gap-3 pt-4">
                <p class="text-lg font-bold tabular-nums">
                    <span v-if="hasVariants" class="mr-1 text-xs font-medium text-muted">{{ t('site.from_price') }}</span>
                    {{ formatMoney(lowestPrice, { decimals: 0 }) }}
                    <span class="text-xs font-medium text-muted">{{ t('site.currency') }}</span>
                </p>

                <button
                    v-if="showAdd"
                    type="button"
                    class="flex h-11 w-11 items-center justify-center rounded-lg bg-accent text-accent-foreground "
                    :aria-label="`${t('web.add_to_cart')}: ${name}`"
                    @click="emit('add', item)"
                >
                    <AppIcon name="plus" size="14" />
                </button>
                <Link
                    v-else
                    :href="itemUrl(branchCode, item.id)"
                    class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-border px-3.5 text-sm font-medium transition-colors hover:border-accent hover:bg-accent/10"
                >
                    {{ t('site.view_dish') }}
                    <AppIcon name="arrow-right" size="11" />
                </Link>
            </div>
        </div>
    </TiltCard>
</template>
