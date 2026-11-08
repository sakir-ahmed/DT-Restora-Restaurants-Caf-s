<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FaqList from '@/components/site/FaqList.vue';
import PageHero from '@/components/site/PageHero.vue';
import { t } from '@/lib/i18n';

defineProps({
    seo: { type: Object, default: null },
});

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_faq') },
]);
</script>

<template>
    <div>
        <PageHero :eyebrow="t('site.faq_eyebrow')" :title="t('site.faq_page_title')" :text="t('site.faq_page_text')" :breadcrumbs="crumbs" />

        <section class="mx-auto max-w-6xl px-4 py-10">
            <FaqList :count="10" />

            <div class="mx-auto mt-8 max-w-3xl text-center">
                <Link href="/contact" class="inline-flex h-11 items-center gap-2 rounded-lg bg-accent px-5 font-semibold text-accent-foreground hover:opacity-90">
                    <AppIcon name="phone" size="14" />
                    {{ t('site.faq_contact') }}
                </Link>
            </div>
        </section>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
