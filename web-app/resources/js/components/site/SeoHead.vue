<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { pageTitle } from '@/lib/title';

/**
 * Keeps the document head in sync during client-side navigation. The first
 * render is produced by the Blade root view (crawlers read that), so this
 * component only runs in the browser and adopts those tags by key.
 */
const page = usePage();
const isBrowser = typeof window !== 'undefined';

const seo = computed(() => page.props.seo ?? null);
const brand = computed(() => page.props.appName ?? "Arman's Kitchen");
const fullTitle = computed(() => (seo.value ? pageTitle(seo.value.title, page) : brand.value));
const robots = computed(() => (seo.value && ! seo.value.noindex ? 'index,follow,max-image-preview:large' : 'noindex,nofollow'));
</script>

<template>
    <Head v-if="seo && ! isBrowser" :title="seo.title" />
    <Head v-else-if="seo" :title="seo.title">
        <meta head-key="robots" name="robots" :content="robots">
        <meta head-key="description" name="description" :content="seo.description">
        <link head-key="canonical" rel="canonical" :href="seo.canonical">
        <meta head-key="og:site_name" property="og:site_name" :content="brand">
        <meta head-key="og:type" property="og:type" :content="seo.type">
        <meta head-key="og:locale" property="og:locale" :content="seo.locale">
        <meta head-key="og:title" property="og:title" :content="fullTitle">
        <meta head-key="og:description" property="og:description" :content="seo.description">
        <meta head-key="og:url" property="og:url" :content="seo.canonical">
        <meta head-key="og:image" property="og:image" :content="seo.image">
        <meta head-key="og:image:alt" property="og:image:alt" :content="seo.image_alt ?? brand">
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
        <meta head-key="twitter:title" name="twitter:title" :content="fullTitle">
        <meta head-key="twitter:description" name="twitter:description" :content="seo.description">
        <meta head-key="twitter:image" name="twitter:image" :content="seo.image">
        <meta head-key="twitter:image:alt" name="twitter:image:alt" :content="seo.image_alt ?? brand">
    </Head>
    <Head v-else-if="isBrowser">
        <meta head-key="robots" name="robots" content="noindex,nofollow">
    </Head>
</template>
