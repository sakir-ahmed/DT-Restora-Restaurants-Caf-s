<script setup>
import { Head } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { addToCart } from '@/lib/cart';
import { notify } from '@/lib/notify';

const props = defineProps({
    branch: { type: Object, required: true },
    item: { type: Object, required: true },
});

function add() {
    addToCart({
        id: props.item.id,
        name: props.item.name,
        base_price: props.item.base_price,
    });
    notify.success(t('web.added_to_cart', { name: props.item.name }));
}
</script>

<template>
    <Head :title="item.name" />

    <article class="mx-auto max-w-lg rounded-xl border border-border bg-surface p-5">
        <p class="text-xs text-muted">{{ branch.name }}<span v-if="item.category"> / {{ item.category }}</span></p>
        <h1 class="mt-1 text-xl font-bold">{{ item.name }}</h1>
        <p v-if="item.description" class="mt-2 text-sm text-muted">{{ item.description }}</p>
        <p class="mt-3 text-lg font-bold tabular-nums">{{ formatMoney(item.base_price) }}</p>

        <ul v-if="item.variants?.length" class="mt-4 space-y-1 text-sm">
            <li v-for="variant in item.variants" :key="variant.id" class="flex justify-between">
                <span>{{ variant.name }}</span>
                <span class="tabular-nums">{{ formatMoney(variant.price) }}</span>
            </li>
        </ul>

        <button type="button" class="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-accent px-4 text-sm font-semibold text-accent-foreground" @click="add">
            <AppIcon name="plus" size="12" />
            {{ t('web.add_to_cart') }}
        </button>
    </article>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
