<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import BranchCard from '@/components/site/BranchCard.vue';
import CountUp from '@/components/site/CountUp.vue';
import CtaBanner from '@/components/site/CtaBanner.vue';
import DishCard from '@/components/site/DishCard.vue';
import FaqList from '@/components/site/FaqList.vue';
import HeroShowcase from '@/components/site/HeroShowcase.vue';
import PostCard from '@/components/site/PostCard.vue';
import Reveal from '@/components/site/Reveal.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import TiltCard from '@/components/site/TiltCard.vue';
import { t, currentLocale } from '@/lib/i18n';
import { localized, menuUrl } from '@/lib/site';

const props = defineProps({
    branches: { type: Array, required: true },
    featured: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    gallery: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
    seo: { type: Object, default: null },
});

const firstBranch = computed(() => props.branches[0] ?? null);
const menuHref = computed(() => (firstBranch.value ? menuUrl(firstBranch.value.code) : '/menu'));
const categoryNames = computed(() => props.categories.map((category) => (currentLocale() === 'bn' && category.name_bn ? category.name_bn : category.name)));

const steps = [
    { icon: 'utensils', title: 'site.how_1_title', text: 'site.how_1_text' },
    { icon: 'bag-shopping', title: 'site.how_2_title', text: 'site.how_2_text' },
    { icon: 'heart', title: 'site.how_3_title', text: 'site.how_3_text' },
];

const features = [
    { icon: 'fire', key: 'fresh' },
    { icon: 'drumstick-bite', key: 'mandi' },
    { icon: 'pepper-hot', key: 'street' },
    { icon: 'mug-hot', key: 'breakfast' },
    { icon: 'truck-fast', key: 'delivery' },
    { icon: 'users', key: 'family' },
];

const statTiles = computed(() => [
    props.stats.years
        ? { value: props.stats.years, label: t('site.stat_years'), icon: 'award' }
        : { value: props.stats.branches, label: t('site.stat_branches'), icon: 'store' },
    { value: props.stats.dishes, label: t('site.stat_dishes'), icon: 'bowl-food' },
    { value: props.stats.categories, label: t('site.stat_categories'), icon: 'layer-group' },
]);
</script>

<template>
    <div>
        <!-- Hero -->
        <section class="border-b border-border bg-surface">
            <div class="mx-auto grid max-w-6xl items-center gap-8 px-4 py-10 sm:py-10 lg:grid-cols-2">
                <div>
                    <p class="mb-4 inline-flex items-center gap-2 rounded-lg border border-border bg-background px-2.5 py-1 text-xs font-medium text-muted">
                        <span class="h-2 w-2 rounded-full bg-success" />
                        {{ t('site.hero_eyebrow') }}
                    </p>

                    <h1 class="text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">{{ t('site.hero_title') }}</h1>
                    <p class="mt-4 max-w-xl text-base leading-relaxed text-muted sm:text-lg">{{ t('site.hero_subtitle') }}</p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link :href="menuHref" class="inline-flex h-11 items-center gap-2 rounded-lg bg-accent px-5 text-base font-semibold text-accent-foreground hover:opacity-90">
                            <AppIcon name="bag-shopping" size="15" />
                            {{ t('site.hero_cta_menu') }}
                        </Link>
                        <Link href="/locations" class="inline-flex h-11 items-center gap-2 rounded-lg border border-border bg-surface px-5 text-base font-medium hover:bg-background">
                            <AppIcon name="location-dot" size="14" />
                            {{ t('site.hero_cta_locations') }}
                        </Link>
                    </div>

                    <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted">
                        <li class="flex items-center gap-2"><AppIcon name="fire" size="13" />{{ t('site.hero_badge_fresh') }}</li>
                        <li class="flex items-center gap-2"><AppIcon name="utensils" size="13" />{{ t('site.hero_badge_modes') }}</li>
                        <li class="flex items-center gap-2"><AppIcon name="clock" size="13" />{{ t('site.hero_badge_hours') }}</li>
                    </ul>
                </div>

                <HeroShowcase :items="featured" class="w-full lg:max-w-lg lg:justify-self-end" />
            </div>
        </section>

        <!-- Categories -->
        <section v-if="categoryNames.length" class="border-b border-border" :aria-label="t('site.categories_title')">
            <ul class="mx-auto flex max-w-6xl flex-wrap gap-2 px-4 py-4">
                <li v-for="name in categoryNames" :key="name" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-muted">{{ name }}</li>
            </ul>
        </section>

        <!-- Stats -->
        <section class="mx-auto max-w-6xl px-4 py-10">
            <div class="grid gap-4 sm:grid-cols-3">
                <Reveal v-for="(tile, index) in statTiles" :key="tile.label" :delay="index * 100">
                    <TiltCard class="bg-surface border border-border flex items-center gap-4 rounded-xl p-6 shadow-sm">
                        <span class="flex h-11 w-14 shrink-0 items-center justify-center rounded-xl bg-accent/15 text-primary">
                            <AppIcon :name="tile.icon" size="22" />
                        </span>
                        <div>
                            <p class="text-3xl font-bold leading-none"><CountUp :value="tile.value" /></p>
                            <p class="mt-1.5 text-sm text-muted">{{ tile.label }}</p>
                        </div>
                    </TiltCard>
                </Reveal>
            </div>
        </section>

        <!-- Featured dishes -->
        <section v-if="featured.length && firstBranch" class="mx-auto max-w-6xl px-4 pb-12">
            <Reveal class="flex flex-wrap items-end justify-between gap-4">
                <SectionHeading :eyebrow="t('site.dishes_eyebrow')" :title="t('site.dishes_title')" :subtitle="t('site.dishes_subtitle')" />
                <Link :href="menuHref" class="inline-flex h-11 items-center gap-2 rounded-lg border border-border px-5 text-sm font-semibold transition-colors hover:border-accent hover:bg-accent/10">
                    {{ t('site.view_full_menu') }}
                    <AppIcon name="arrow-right" size="12" />
                </Link>
            </Reveal>

            <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <Reveal v-for="(item, index) in featured" :key="item.id" as="li" :delay="(index % 4) * 90">
                    <DishCard :item="item" :branch-code="branches.find((branch) => branch.id === item.branch_id)?.code ?? firstBranch.code" image-fit="contain" />
                </Reveal>
            </ul>
        </section>

        <!-- How it works -->
        <section class="border-y border-border bg-surface py-12">
            <div class="mx-auto max-w-6xl px-4">
                <Reveal><SectionHeading center :eyebrow="t('site.how_eyebrow')" :title="t('site.how_title')" /></Reveal>

                <ol class="relative mt-10 grid gap-8 md:grid-cols-3">
                    <Reveal v-for="(step, index) in steps" :key="step.title" as="li" :delay="index * 120" class="relative text-center">
                        <div class="relative mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-accent text-accent-foreground ">
                            <AppIcon :name="step.icon" size="26" />
                            <span class="absolute -right-2 -top-2 flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground">{{ index + 1 }}</span>
                        </div>
                        <h3 class="mt-4 text-xl font-bold">{{ t(step.title) }}</h3>
                        <p class="mx-auto mt-2 max-w-xs text-muted">{{ t(step.text) }}</p>
                    </Reveal>
                </ol>
            </div>
        </section>

        <!-- Features -->
        <section class="mx-auto max-w-6xl px-4 py-12">
            <Reveal><SectionHeading center :eyebrow="t('site.features_eyebrow')" :title="t('site.features_title')" /></Reveal>

            <ul class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Reveal v-for="(feature, index) in features" :key="feature.key" as="li" :delay="(index % 3) * 100">
                    <TiltCard class="h-full rounded-xl border border-border bg-surface p-5 shadow-sm">
                        <span class="flex h-11 w-14 items-center justify-center rounded-xl bg-accent/15 text-primary">
                            <AppIcon :name="feature.icon" size="22" />
                        </span>
                        <h3 class="mt-5 text-xl font-bold">{{ t(`site.feature_${feature.key}_title`) }}</h3>
                        <p class="mt-2 leading-relaxed text-muted">{{ t(`site.feature_${feature.key}_text`) }}</p>
                    </TiltCard>
                </Reveal>
            </ul>
        </section>

        <!-- Gallery preview -->
        <section v-if="gallery.length" class="mx-auto max-w-6xl px-4 py-12">
            <Reveal class="flex flex-wrap items-end justify-between gap-4">
                <SectionHeading :title="t('site.gallery_home_title')" />
                <Link href="/gallery" class="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-medium hover:bg-background">
                    {{ t('site.gallery_home_link') }}
                    <AppIcon name="arrow-right" size="12" />
                </Link>
            </Reveal>

            <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <Reveal v-for="(entry, index) in gallery" :key="entry.id" as="li" :delay="(index % 3) * 60">
                    <Link href="/gallery" class="block aspect-[4/3] overflow-hidden rounded-xl border border-border bg-surface" :aria-label="entry.title || t('site.nav_gallery')">
                        <img :src="entry.src" :alt="entry.title || ''" width="640" height="480" loading="lazy" decoding="async" class="h-full w-full object-cover">
                    </Link>
                </Reveal>
            </ul>
        </section>

        <!-- Branches -->
        <section v-if="branches.length" class="border-y border-border bg-surface py-12">
            <div class="mx-auto max-w-6xl px-4">
                <Reveal class="flex flex-wrap items-end justify-between gap-4">
                    <SectionHeading :eyebrow="t('site.branches_eyebrow')" :title="t('site.branches_title')" :subtitle="t('site.branches_subtitle')" />
                    <Link href="/locations" class="inline-flex h-11 items-center gap-2 rounded-lg border border-border bg-surface px-5 text-sm font-semibold transition-colors hover:border-accent">
                        {{ t('site.nav_locations') }}
                        <AppIcon name="arrow-right" size="12" />
                    </Link>
                </Reveal>

                <ul class="mt-10 grid gap-5 md:grid-cols-2">
                    <Reveal v-for="(branch, index) in branches" :key="branch.id" as="li" :delay="(index % 2) * 100">
                        <BranchCard :branch="branch" />
                    </Reveal>
                </ul>
            </div>
        </section>

        <!-- Blog -->
        <section v-if="posts.length" class="mx-auto max-w-6xl px-4 py-12">
            <Reveal class="flex flex-wrap items-end justify-between gap-4">
                <SectionHeading :title="t('site.blog_home_title')" />
                <Link href="/blog" class="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-medium hover:bg-background">
                    {{ t('site.blog_home_link') }}
                    <AppIcon name="arrow-right" size="12" />
                </Link>
            </Reveal>

            <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Reveal v-for="(post, index) in posts" :key="post.id" as="li" :delay="index * 70">
                    <PostCard :post="post" />
                </Reveal>
            </ul>
        </section>

        <!-- FAQ -->
        <section class="mx-auto max-w-6xl px-4 py-12">
            <Reveal><SectionHeading center :eyebrow="t('site.faq_eyebrow')" :title="t('site.faq_title')" /></Reveal>
            <Reveal class="mt-10"><FaqList /></Reveal>
        </section>

        <CtaBanner :href="menuHref" />
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
