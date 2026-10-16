<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import PageHero from '@/components/site/PageHero.vue';
import PostCard from '@/components/site/PostCard.vue';
import Reveal from '@/components/site/Reveal.vue';
import { t } from '@/lib/i18n';

defineProps({
    posts: { type: Array, required: true },
    categories: { type: Array, default: () => [] },
    activeCategory: { type: String, default: null },
    pagination: { type: Object, required: true },
    seo: { type: Object, default: null },
});

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_blog') },
]);

const chip = (active) => ['inline-flex h-9 items-center rounded-lg border px-3 text-sm font-medium transition-colors', active ? 'border-accent bg-accent text-accent-foreground' : 'border-border bg-surface text-muted hover:text-foreground'];
</script>

<template>
    <div>
        <PageHero :eyebrow="t('site.blog_eyebrow')" :title="t('site.blog_title')" :text="t('site.blog_text')" :breadcrumbs="crumbs">
            <a href="/blog/feed.xml" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-foreground">
                <AppIcon name="rss" size="12" />
                {{ t('site.blog_rss') }}
            </a>
        </PageHero>

        <section class="mx-auto max-w-6xl px-4 py-8">
            <nav v-if="categories.length" class="flex flex-wrap gap-2" :aria-label="t('site.nav_blog')">
                <Link href="/blog" :class="chip(! activeCategory)" :aria-current="! activeCategory ? 'page' : undefined">{{ t('site.blog_all') }}</Link>
                <Link v-for="category in categories" :key="category" :href="`/blog?category=${encodeURIComponent(category)}`" :class="chip(activeCategory === category)" :aria-current="activeCategory === category ? 'page' : undefined">{{ category }}</Link>
            </nav>

            <p v-if="posts.length === 0" class="mt-8 rounded-xl border border-dashed border-border p-10 text-center text-muted">{{ t('site.blog_empty') }}</p>

            <ul v-else class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Reveal v-for="(post, index) in posts" :key="post.id" as="li" :delay="(index % 3) * 70">
                    <PostCard :post="post" />
                </Reveal>
            </ul>

            <nav v-if="pagination.last > 1" class="mt-8 flex flex-wrap items-center justify-between gap-3 text-sm" :aria-label="t('site.blog_page', { current: pagination.current, last: pagination.last })">
                <Link v-if="pagination.prev" :href="pagination.prev" rel="prev" class="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-surface px-4 font-medium hover:bg-background">
                    <AppIcon name="chevron-left" size="11" />
                    {{ t('site.blog_newer') }}
                </Link>
                <span v-else />
                <span class="text-muted">{{ t('site.blog_page', { current: pagination.current, last: pagination.last }) }}</span>
                <Link v-if="pagination.next" :href="pagination.next" rel="next" class="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-surface px-4 font-medium hover:bg-background">
                    {{ t('site.blog_older') }}
                    <AppIcon name="chevron-right" size="11" />
                </Link>
                <span v-else />
            </nav>
        </section>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
