<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import Breadcrumbs from '@/components/site/Breadcrumbs.vue';
import CtaBanner from '@/components/site/CtaBanner.vue';
import PostCard from '@/components/site/PostCard.vue';
import Reveal from '@/components/site/Reveal.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import { t } from '@/lib/i18n';
import { formatDay } from '@/lib/site';

const props = defineProps({
    post: { type: Object, required: true },
    related: { type: Array, default: () => [] },
    seo: { type: Object, default: null },
});

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_blog'), href: '/blog' },
    { label: props.post.title },
]);

const wasUpdated = computed(() => props.post.updated_at && props.post.published_at
    && formatDay(props.post.updated_at) !== formatDay(props.post.published_at));
</script>

<template>
    <div>
        <article>
            <header class="border-b border-border bg-surface">
                <div class="mx-auto max-w-3xl px-4 py-8 sm:py-10">
                    <Breadcrumbs :items="crumbs" class="mb-4" />

                    <p v-if="post.category" class="mb-2">
                        <Link :href="`/blog?category=${encodeURIComponent(post.category)}`" class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted hover:text-foreground">
                            <AppIcon name="tag" size="10" />
                            {{ post.category }}
                        </Link>
                    </p>
                    <h1 class="text-3xl font-bold leading-tight sm:text-4xl">{{ post.title }}</h1>
                    <p class="mt-3 text-lg leading-relaxed text-muted">{{ post.excerpt }}</p>

                    <p class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted">
                        <time :datetime="post.published_at">{{ t('site.blog_published', { date: formatDay(post.published_at) }) }}</time>
                        <span v-if="wasUpdated"><time :datetime="post.updated_at">{{ t('site.blog_updated', { date: formatDay(post.updated_at) }) }}</time></span>
                        <span class="inline-flex items-center gap-1.5"><AppIcon name="clock" size="11" />{{ t('site.blog_read_time', { minutes: post.reading_minutes }) }}</span>
                    </p>
                </div>
            </header>

            <div class="mx-auto max-w-3xl px-4 py-8">
                <img v-if="post.cover" :src="post.cover" :alt="post.cover_alt" width="1200" height="675" fetchpriority="high" decoding="async" class="mb-8 aspect-[16/9] w-full rounded-xl border border-border object-cover">

                <div class="post-body" v-html="post.html" />

                <Link href="/blog" class="mt-10 inline-flex items-center gap-2 text-sm font-medium hover:underline">
                    <AppIcon name="chevron-left" size="11" />
                    {{ t('site.blog_back') }}
                </Link>
            </div>
        </article>

        <section v-if="related.length" class="border-y border-border bg-surface py-10">
            <div class="mx-auto max-w-6xl px-4">
                <Reveal><SectionHeading :title="t('site.blog_related')" /></Reveal>
                <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <Reveal v-for="(entry, index) in related" :key="entry.id" as="li" :delay="index * 70">
                        <PostCard :post="entry" />
                    </Reveal>
                </ul>
            </div>
        </section>

        <CtaBanner />
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
