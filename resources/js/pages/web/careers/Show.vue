<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ApplyForm from '@/components/site/ApplyForm.vue';
import PageHero from '@/components/site/PageHero.vue';
import Reveal from '@/components/site/Reveal.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';

const props = defineProps({
    opening: { type: Object, required: true },
    seo: { type: Object, default: null },
});

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_careers'), href: '/careers' },
    { label: props.opening.title },
]);

const paragraphs = (text) => (text ?? '').split(/\n{2,}/).filter(Boolean);
</script>

<template>
    <div>
        <PageHero :eyebrow="t(`site.job_type_${opening.employment_type}`)" :title="opening.title" :text="opening.summary" :breadcrumbs="crumbs">
            <p class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-muted">
                <span v-if="opening.department" class="inline-flex items-center gap-1.5"><AppIcon name="briefcase" size="12" />{{ opening.department }}</span>
                <span v-if="opening.location" class="inline-flex items-center gap-1.5"><AppIcon name="location-dot" size="12" />{{ opening.location }}</span>
                <span v-if="opening.closes_on" class="inline-flex items-center gap-1.5"><AppIcon name="calendar-days" size="12" />{{ t('site.job_closes', { date: opening.closes_on }) }}</span>
            </p>
        </PageHero>

        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 lg:grid-cols-[1.2fr_1fr]">
            <article class="space-y-6">
                <Reveal as="section" class="rounded-xl border border-border bg-surface p-5">
                    <h2 class="text-lg font-semibold">{{ t('site.job_about') }}</h2>
                    <div class="mt-2 space-y-3 leading-relaxed text-muted">
                        <p v-for="(paragraph, index) in paragraphs(opening.description)" :key="index" class="whitespace-pre-line">{{ paragraph }}</p>
                    </div>
                </Reveal>

                <Reveal v-if="opening.requirements" as="section" class="rounded-xl border border-border bg-surface p-5">
                    <h2 class="text-lg font-semibold">{{ t('site.job_requirements') }}</h2>
                    <div class="mt-2 space-y-3 leading-relaxed text-muted">
                        <p v-for="(paragraph, index) in paragraphs(opening.requirements)" :key="index" class="whitespace-pre-line">{{ paragraph }}</p>
                    </div>
                </Reveal>

                <Link href="/careers" class="inline-flex items-center gap-2 text-sm font-medium hover:underline">
                    <AppIcon name="chevron-left" size="11" />
                    {{ t('site.job_back') }}
                </Link>
            </article>

            <Reveal :delay="80"><ApplyForm :opening-id="opening.id" :title="t('site.apply_title_job')" /></Reveal>
        </div>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
