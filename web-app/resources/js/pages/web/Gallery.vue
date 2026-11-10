<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import MediaLightbox from '@/components/site/MediaLightbox.vue';
import PageHero from '@/components/site/PageHero.vue';
import Reveal from '@/components/site/Reveal.vue';
import { t } from '@/lib/i18n';
import { formatNumber } from '@/lib/format';

const props = defineProps({
    entries: { type: Array, required: true },
    seo: { type: Object, default: null },
});

const filter = ref('all');
const lightboxOpen = ref(false);
const lightboxStart = ref(0);

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_gallery') },
]);

const filters = computed(() => [
    { key: 'all', label: t('site.gallery_filter_all'), count: props.entries.length },
    { key: 'outlet', label: t('site.gallery_filter_outlets'), count: props.entries.filter((entry) => entry.group === 'outlet').length },
    { key: 'dish', label: t('site.gallery_filter_dishes'), count: props.entries.filter((entry) => entry.group === 'dish').length },
    { key: 'video', label: t('site.gallery_filter_videos'), count: props.entries.filter((entry) => entry.kind === 'video').length },
].filter((entry) => entry.key === 'all' || entry.count > 0));

const visible = computed(() => props.entries.filter((entry) => {
    if (filter.value === 'all') {
        return true;
    }

    return filter.value === 'video' ? entry.kind === 'video' : entry.group === filter.value;
}));

function open(position) {
    lightboxStart.value = position;
    lightboxOpen.value = true;
}
</script>

<template>
    <div>
        <PageHero :eyebrow="t('site.gallery_eyebrow')" :title="t('site.gallery_title')" :text="t('site.gallery_text')" :breadcrumbs="crumbs" />

        <section class="mx-auto max-w-6xl px-4 py-8">
            <div v-if="entries.length === 0" class="rounded-xl border border-dashed border-border p-10 text-center">
                <p class="text-muted">{{ t('site.gallery_empty') }}</p>
                <Link href="/menu" class="mt-4 inline-flex h-11 items-center gap-2 rounded-lg bg-accent px-5 font-semibold text-accent-foreground hover:opacity-90">
                    <AppIcon name="bag-shopping" size="14" />
                    {{ t('site.hero_cta_menu') }}
                </Link>
            </div>

            <template v-else>
                <div class="flex flex-wrap gap-2" role="group" :aria-label="t('site.nav_gallery')">
                    <button
                        v-for="entry in filters"
                        :key="entry.key"
                        type="button"
                        class="inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-sm font-medium transition-colors"
                        :class="filter === entry.key ? 'border-accent bg-accent text-accent-foreground' : 'border-border bg-surface text-muted hover:text-foreground'"
                        :aria-pressed="filter === entry.key"
                        @click="filter = entry.key"
                    >
                        {{ entry.label }}
                        <span class="text-xs tabular-nums opacity-70">{{ formatNumber(entry.count) }}</span>
                    </button>
                </div>

                <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <Reveal v-for="(entry, position) in visible" :key="entry.id" as="li">
                        <button
                            type="button"
                            class="group relative block aspect-[4/3] w-full overflow-hidden rounded-xl border border-border bg-surface text-left"
                            :aria-label="t('site.gallery_open', { name: entry.title || entry.label || t('site.nav_gallery') })"
                            @click="open(position)"
                        >
                            <img v-if="entry.kind === 'image'" :src="entry.src" :alt="entry.title || entry.label || ''" width="640" height="480" loading="lazy" decoding="async" class="h-full w-full object-cover">
                            <img v-else-if="entry.thumbnail" :src="entry.thumbnail" alt="" loading="lazy" class="h-full w-full object-cover">
                            <span v-else class="flex h-full w-full items-center justify-center text-muted"><AppIcon name="video" size="28" /></span>

                            <span v-if="entry.kind === 'video'" class="absolute inset-0 flex items-center justify-center bg-black/25">
                                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-accent text-accent-foreground"><AppIcon name="play" size="16" /></span>
                            </span>

                            <span v-if="entry.title || entry.label" class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-2.5 pt-8 text-sm font-medium text-white">
                                {{ entry.title || entry.label }}
                            </span>
                        </button>
                    </Reveal>
                </ul>
            </template>
        </section>

        <MediaLightbox :open="lightboxOpen" :items="visible" :start="lightboxStart" @close="lightboxOpen = false" />
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
