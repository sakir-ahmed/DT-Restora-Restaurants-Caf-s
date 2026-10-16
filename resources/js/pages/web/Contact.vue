<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import CtaBanner from '@/components/site/CtaBanner.vue';
import FaqList from '@/components/site/FaqList.vue';
import HoursList from '@/components/site/HoursList.vue';
import PageHero from '@/components/site/PageHero.vue';
import Reveal from '@/components/site/Reveal.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import TiltCard from '@/components/site/TiltCard.vue';
import { t } from '@/lib/i18n';
import { directionsUrl, localized, locationUrl, menuUrl } from '@/lib/site';

defineProps({
    branches: { type: Array, required: true },
    seo: { type: Object, default: null },
});

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_contact') },
]);
</script>

<template>
    <div>
        <PageHero :eyebrow="t('site.contact_eyebrow')" :title="t('site.contact_title')" :text="t('site.contact_text')" :breadcrumbs="crumbs" />

        <section class="mx-auto max-w-6xl px-4 py-10">
            <ul class="grid gap-6 md:grid-cols-2">
                <Reveal v-for="(branch, index) in branches" :key="branch.id" as="li" :delay="(index % 2) * 100">
                    <TiltCard as="article" class="h-full rounded-xl border border-border bg-surface p-5 shadow-sm">
                        <h2 class="text-2xl font-bold">
                            <Link :href="locationUrl(branch.slug)" class="hover:underline">{{ localized(branch, 'name') }}</Link>
                        </h2>

                        <address class="mt-5 space-y-3 text-base not-italic">
                            <p class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-accent/15 text-accent-foreground"><AppIcon name="location-dot" size="14" /></span>
                                <span><span class="block text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.contact_visit') }}</span>{{ [branch.address, branch.city].filter(Boolean).join(', ') }}</span>
                            </p>
                            <p v-if="branch.phone" class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-accent/15 text-accent-foreground"><AppIcon name="phone" size="14" /></span>
                                <span><span class="block text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.contact_call') }}</span><a :href="`tel:${branch.phone}`" class="font-semibold hover:underline">{{ branch.phone }}</a></span>
                            </p>
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-accent/15 text-accent-foreground"><AppIcon name="clock" size="14" /></span>
                                <div class="flex-1">
                                    <span class="mb-1 block text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.location_hours_title') }}</span>
                                    <HoursList :hours="branch.hours" />
                                </div>
                            </div>
                        </address>

                        <div class="mt-6 flex flex-wrap gap-2">
                            <a v-if="branch.phone" :href="`tel:${branch.phone}`" class="inline-flex h-11 items-center gap-2 rounded-lg bg-accent px-5 text-sm font-semibold text-accent-foreground ">
                                <AppIcon name="phone" size="13" />
                                {{ t('site.call_now') }}
                            </a>
                            <a :href="directionsUrl(branch)" target="_blank" rel="noopener" class="inline-flex h-11 items-center gap-2 rounded-lg border border-border px-5 text-sm font-medium hover:border-accent hover:bg-accent/10">
                                <AppIcon name="map-pin" size="13" />
                                {{ t('site.directions') }}
                            </a>
                            <Link :href="menuUrl(branch.code)" class="inline-flex h-11 items-center gap-2 rounded-lg border border-border px-5 text-sm font-medium hover:border-accent hover:bg-accent/10">
                                {{ t('site.order_from_branch') }}
                            </Link>
                        </div>
                    </TiltCard>
                </Reveal>
            </ul>

            <Reveal class="mt-10">
                <div class="flex flex-col items-start gap-4 rounded-xl border border-border bg-surface p-5 sm:flex-row sm:items-center">
                    <span class="flex h-11 w-14 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground"><AppIcon name="truck-fast" size="22" /></span>
                    <div>
                        <h2 class="text-xl font-bold">{{ t('site.contact_help_title') }}</h2>
                        <p class="mt-1 text-muted">{{ t('site.contact_help_text') }}</p>
                    </div>
                </div>
            </Reveal>
        </section>

        <section class="border-y border-border bg-surface py-12">
            <div class="mx-auto max-w-6xl px-4">
                <Reveal><SectionHeading center :eyebrow="t('site.faq_eyebrow')" :title="t('site.faq_title')" /></Reveal>
                <Reveal class="mt-10"><FaqList /></Reveal>
            </div>
        </section>

        <CtaBanner />
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
