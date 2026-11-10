<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import CountUp from '@/components/site/CountUp.vue';
import CtaBanner from '@/components/site/CtaBanner.vue';
import DishPlaceholder from '@/components/site/DishPlaceholder.vue';
import PageHero from '@/components/site/PageHero.vue';
import Reveal from '@/components/site/Reveal.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    photo: { type: Object, default: null },
    stats: { type: Object, required: true },
    seo: { type: Object, default: null },
});

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_about') },
]);

const values = [
    { key: 'fresh', icon: 'leaf' },
    { key: 'honest', icon: 'hand-holding-dollar' },
    { key: 'warm', icon: 'heart' },
    { key: 'consistent', icon: 'award' },
];

const process = [
    { number: 1, icon: 'wheat-awn' },
    { number: 2, icon: 'clipboard-list' },
    { number: 3, icon: 'fire' },
    { number: 4, icon: 'bell-concierge' },
];

const tiles = computed(() => [
    props.stats.years
        ? { value: props.stats.years, label: t('site.stat_years') }
        : { value: props.stats.branches, label: t('site.stat_branches') },
    { value: props.stats.dishes, label: t('site.stat_dishes') },
]);
</script>

<template>
    <div>
        <PageHero :eyebrow="t('site.about_eyebrow')" :title="t('site.about_title')" :text="t('site.about_text')" :breadcrumbs="crumbs" />

        <!-- Story -->
        <section class="mx-auto grid max-w-6xl items-center gap-8 px-4 py-12 lg:grid-cols-2">
            <Reveal>
                <SectionHeading :title="t('site.about_story_title')" />
                <p class="mt-5 text-base leading-relaxed text-muted">{{ t('site.about_story_p1') }}</p>
                <p class="mt-4 text-base leading-relaxed text-muted">{{ t('site.about_story_p2') }}</p>

                <dl class="mt-8 grid max-w-md grid-cols-2 gap-4">
                    <div v-for="tile in tiles" :key="tile.label" class="rounded-xl border border-border bg-surface p-4 shadow-sm">
                        <dd class="text-4xl font-bold"><CountUp :value="tile.value" /></dd>
                        <dt class="mt-1 text-sm text-muted">{{ tile.label }}</dt>
                    </div>
                </dl>
            </Reveal>

            <Reveal :delay="100">
                <figure v-if="photo" class="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <img :src="photo.src" :alt="photo.title || ''" width="960" height="720" loading="lazy" decoding="async" class="aspect-[4/3] w-full object-cover">
                    <figcaption v-if="photo.title" class="px-4 py-3 text-sm text-muted">{{ photo.title }}</figcaption>
                </figure>
                <div v-else class="rounded-xl border border-border bg-surface p-8">
                    <div class="mx-auto aspect-square w-full max-w-sm"><DishPlaceholder kind="bowl" :seed="5" /></div>
                </div>
            </Reveal>
        </section>

        <!-- Values -->
        <section class="border-y border-border bg-surface py-12">
            <div class="mx-auto max-w-6xl px-4">
                <Reveal><SectionHeading center :title="t('site.about_values_title')" /></Reveal>

                <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Reveal v-for="(value, index) in values" :key="value.key" as="li" :delay="index * 60">
                        <div class="h-full rounded-xl border border-border bg-background p-5">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent/15 text-primary">
                                <AppIcon :name="value.icon" size="16" />
                            </span>
                            <h3 class="mt-4 text-lg font-semibold">{{ t(`site.value_${value.key}_title`) }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ t(`site.value_${value.key}_text`) }}</p>
                        </div>
                    </Reveal>
                </ul>
            </div>
        </section>

        <!-- Process timeline -->
        <section class="mx-auto max-w-6xl px-4 py-12">
            <Reveal><SectionHeading center :title="t('site.process_title')" /></Reveal>

            <ol class="relative mt-8 grid gap-10 md:grid-cols-4">
                <Reveal v-for="step in process" :key="step.number" as="li" :delay="step.number * 100" class="relative text-center">
                    <div class="relative mx-auto flex h-14 w-14 items-center justify-center rounded-lg border border-border bg-accent/15 text-primary ">
                        <AppIcon :name="step.icon" size="22" />
                        <span class="absolute -right-1 -top-1 flex h-7 w-7 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">{{ step.number }}</span>
                    </div>
                    <h3 class="mt-5 text-xl font-bold">{{ t(`site.process_${step.number}_title`) }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted">{{ t(`site.process_${step.number}_text`) }}</p>
                </Reveal>
            </ol>
        </section>

        <CtaBanner />
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
