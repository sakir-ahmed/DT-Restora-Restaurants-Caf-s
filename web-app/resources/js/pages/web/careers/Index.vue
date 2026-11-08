<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ApplyForm from '@/components/site/ApplyForm.vue';
import PageHero from '@/components/site/PageHero.vue';
import Reveal from '@/components/site/Reveal.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import { t } from '@/lib/i18n';

defineProps({
    openings: { type: Array, required: true },
    seo: { type: Object, default: null },
});

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_careers') },
]);

const benefits = [
    { n: 1, icon: 'book-open' },
    { n: 2, icon: 'users' },
    { n: 3, icon: 'arrow-trend-up' },
    { n: 4, icon: 'mobile-screen' },
];
</script>

<template>
    <div>
        <PageHero :eyebrow="t('site.careers_eyebrow')" :title="t('site.careers_title')" :text="t('site.careers_text')" :breadcrumbs="crumbs" />

        <section class="mx-auto max-w-6xl px-4 py-10">
            <Reveal><SectionHeading :title="t('site.careers_why_title')" /></Reveal>
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Reveal v-for="benefit in benefits" :key="benefit.n" as="li" :delay="benefit.n * 50">
                    <div class="h-full rounded-xl border border-border bg-surface p-5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent/15 text-primary"><AppIcon :name="benefit.icon" size="16" /></span>
                        <h3 class="mt-4 text-lg font-semibold">{{ t(`site.careers_benefit_${benefit.n}_title`) }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ t(`site.careers_benefit_${benefit.n}_text`) }}</p>
                    </div>
                </Reveal>
            </ul>
        </section>

        <section class="border-y border-border bg-surface py-10">
            <div class="mx-auto max-w-6xl px-4">
                <Reveal><SectionHeading :title="t('site.careers_openings_title')" /></Reveal>

                <p v-if="openings.length === 0" class="mt-6 rounded-xl border border-dashed border-border p-8 text-center text-muted">{{ t('site.careers_no_openings') }}</p>

                <ul v-else class="mt-6 grid gap-4 md:grid-cols-2">
                    <Reveal v-for="(opening, index) in openings" :key="opening.id" as="li" :delay="(index % 2) * 60">
                        <article class="flex h-full flex-col rounded-xl border border-border bg-background p-5">
                            <div class="flex flex-wrap gap-2 text-xs font-medium">
                                <span class="rounded-md bg-accent/15 px-2 py-0.5 text-primary">{{ t(`site.job_type_${opening.employment_type}`) }}</span>
                                <span v-if="opening.department" class="rounded-md border border-border px-2 py-0.5 text-muted">{{ opening.department }}</span>
                            </div>
                            <h3 class="mt-3 text-xl font-semibold">
                                <Link :href="`/careers/${opening.id}/${opening.slug}`" class="hover:underline">{{ opening.title }}</Link>
                            </h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ opening.summary }}</p>
                            <p v-if="opening.location" class="mt-3 flex items-center gap-1.5 text-sm text-muted"><AppIcon name="location-dot" size="12" />{{ opening.location }}</p>
                            <Link :href="`/careers/${opening.id}/${opening.slug}`" class="mt-4 inline-flex h-10 w-fit items-center gap-2 rounded-lg bg-accent px-4 text-sm font-semibold text-accent-foreground hover:opacity-90">
                                {{ t('site.job_view') }}
                                <AppIcon name="arrow-right" size="12" />
                            </Link>
                        </article>
                    </Reveal>
                </ul>
            </div>
        </section>

        <section class="mx-auto grid max-w-6xl gap-8 px-4 py-10 lg:grid-cols-[1fr_1.2fr]">
            <Reveal>
                <SectionHeading :title="t('site.careers_general_title')" :subtitle="t('site.careers_general_text')" />
            </Reveal>
            <Reveal :delay="80"><ApplyForm /></Reveal>
        </section>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
