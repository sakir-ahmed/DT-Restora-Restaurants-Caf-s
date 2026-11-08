<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';
import { categoryLabel, formatDay } from '@/lib/site';
import { formatNumber } from '@/lib/format';

defineProps({
    post: { type: Object, required: true },
});
</script>

<template>
    <article class="group flex h-full flex-col overflow-hidden rounded-xl border border-border bg-surface transition-shadow hover:shadow-md">
        <Link :href="`/blog/${post.slug}`" class="relative block aspect-[16/9] overflow-hidden bg-background" :aria-label="post.title" tabindex="-1">
            <img v-if="post.cover" :src="post.cover" :alt="post.cover_alt" width="800" height="450" loading="lazy" decoding="async" class="h-full w-full object-cover">
            <span v-else class="flex h-full w-full items-center justify-center text-muted"><AppIcon name="newspaper" size="28" /></span>
            <span v-if="post.category" class="absolute left-3 top-3 rounded-md bg-surface/95 px-2 py-0.5 text-xs font-semibold">{{ categoryLabel(post.category) }}</span>
        </Link>

        <div class="flex flex-1 flex-col p-4">
            <p class="flex flex-wrap items-center gap-x-3 text-xs text-muted">
                <time :datetime="post.published_at">{{ formatDay(post.published_at) }}</time>
                <span class="inline-flex items-center gap-1"><AppIcon name="clock" size="10" />{{ t('site.blog_read_time', { minutes: formatNumber(post.reading_minutes) }) }}</span>
            </p>
            <h3 class="mt-2 text-lg font-semibold leading-snug">
                <Link :href="`/blog/${post.slug}`" class="hover:underline">{{ post.title }}</Link>
            </h3>
            <p class="mt-1.5 line-clamp-3 text-sm leading-relaxed text-muted">{{ post.excerpt }}</p>
            <Link :href="`/blog/${post.slug}`" class="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-semibold hover:underline">
                {{ t('site.blog_read_more') }}
                <AppIcon name="arrow-right" size="11" />
            </Link>
        </div>
    </article>
</template>
