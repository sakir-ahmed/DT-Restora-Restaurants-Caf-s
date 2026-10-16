<script setup>
import { computed } from 'vue';
import BranchCard from '@/components/site/BranchCard.vue';
import CtaBanner from '@/components/site/CtaBanner.vue';
import PageHero from '@/components/site/PageHero.vue';
import Reveal from '@/components/site/Reveal.vue';
import { t } from '@/lib/i18n';

defineProps({
    branches: { type: Array, required: true },
    seo: { type: Object, default: null },
});

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_locations') },
]);
</script>

<template>
    <div>
        <PageHero :eyebrow="t('site.locations_eyebrow')" :title="t('site.locations_title')" :text="t('site.locations_text')" :breadcrumbs="crumbs" />

        <section class="mx-auto max-w-6xl px-4 py-10">
            <ul class="grid gap-6 md:grid-cols-2">
                <Reveal v-for="(branch, index) in branches" :key="branch.id" as="li" :delay="(index % 2) * 100">
                    <BranchCard :branch="branch" />
                </Reveal>
            </ul>
        </section>

        <CtaBanner />
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
