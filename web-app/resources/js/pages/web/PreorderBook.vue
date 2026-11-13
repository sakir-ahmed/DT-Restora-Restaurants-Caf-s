<script setup>
import { Head } from '@inertiajs/vue3';
import MenuBook from '@/components/preorder/MenuBook.vue';
import { t } from '@/lib/i18n';
import { localized } from '@/lib/site';

const props = defineProps({
    branch: { type: Object, required: true },
    categories: { type: Array, required: true },
    shareUrl: { type: String, required: true },
    placed: { type: Object, default: null },
    contact: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head>
        <title>{{ t('preorders.menu_word') }} - {{ localized(branch, 'name') }}</title>
        <meta name="robots" content="noindex,nofollow">
        <meta property="og:title" :content="`${localized(branch, 'name')} - ${t('preorders.menu_word')}`">
        <meta property="og:description" :content="t('preorders.welcome_line')">
    </Head>

    <MenuBook
        :branch="props.branch"
        :categories="props.categories"
        :contact="props.contact"
        :share-url="props.shareUrl"
        :post-url="route('web.preorder.store', { branchCode: props.branch.code.toLowerCase() })"
        :placed="props.placed"
        :storage-key="`dt-preorder-public:${props.branch.id}`"
        show-language
    />
</template>
