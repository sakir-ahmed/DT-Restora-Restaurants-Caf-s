<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    items: { type: Array, required: true },
    name: { type: String, default: '' },
    start: { type: Number, default: 0 },
    aspect: { type: String, default: 'aspect-[4/3]' },
});

const index = ref(Math.min(props.start, Math.max(props.items.length - 1, 0)));
const playing = ref(false);

watch(() => props.start, (value) => {
    index.value = Math.min(value, Math.max(props.items.length - 1, 0));
    playing.value = false;
});

const current = computed(() => props.items[index.value] ?? null);

/**
 * Embed address with autoplay, used only after the visitor presses play.
 */
const embedSrc = computed(() => {
    const item = current.value;

    if (! item?.embed_url) {
        return null;
    }

    if (item.provider === 'youtube') {
        return `${item.embed_url}?autoplay=1&rel=0`;
    }

    if (item.provider === 'vimeo') {
        return `${item.embed_url}?autoplay=1`;
    }

    if (item.provider === 'facebook') {
        return `${item.embed_url}&autoplay=true`;
    }

    return item.embed_url;
});

function select(next) {
    index.value = (next + props.items.length) % props.items.length;
    playing.value = false;
}

function onKeydown(event) {
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        select(index.value + 1);
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        select(index.value - 1);
    }
}
</script>

<template>
    <div v-if="current" role="group" :aria-label="t('site.gallery_viewer', { name })" tabindex="0" class="outline-none" @keydown="onKeydown">
        <div class="relative overflow-hidden rounded-xl border border-border bg-background" :class="aspect">
            <!-- Photo -->
            <img
                v-if="current.kind === 'image'"
                :key="current.id"
                :src="current.src"
                :alt="current.title || name"
                width="1200"
                height="900"
                :loading="index === 0 ? 'eager' : 'lazy'"
                decoding="async"
                class="h-full w-full object-cover"
            >

            <!-- Video: loads only after the visitor presses play -->
            <template v-else>
                <iframe
                    v-if="playing && embedSrc && current.provider !== 'file'"
                    :key="current.id"
                    :src="embedSrc"
                    :title="current.title || t('site.gallery_video')"
                    class="absolute inset-0 h-full w-full border-0"
                    allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                    allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin"
                    sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"
                />
                <video v-else-if="playing && current.provider === 'file'" :key="current.id" :src="current.embed_url" controls autoplay playsinline class="absolute inset-0 h-full w-full bg-black object-contain" />
                <button v-else type="button" class="group absolute inset-0 flex items-center justify-center bg-surface" :aria-label="t('site.gallery_play')" @click="playing = true">
                    <img v-if="current.thumbnail" :src="current.thumbnail" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                    <span class="relative flex h-14 w-14 items-center justify-center rounded-full bg-accent text-accent-foreground shadow-md transition-transform group-hover:scale-105">
                        <AppIcon name="play" size="20" />
                    </span>
                </button>
            </template>

            <template v-if="items.length > 1">
                <button type="button" class="absolute left-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg border border-border bg-surface/90 text-foreground hover:bg-surface" :aria-label="t('site.gallery_prev')" @click="select(index - 1)">
                    <AppIcon name="chevron-left" size="14" />
                </button>
                <button type="button" class="absolute right-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg border border-border bg-surface/90 text-foreground hover:bg-surface" :aria-label="t('site.gallery_next')" @click="select(index + 1)">
                    <AppIcon name="chevron-right" size="14" />
                </button>
                <span class="absolute bottom-2 right-2 rounded-md bg-black/60 px-2 py-0.5 text-xs font-medium tabular-nums text-white">
                    {{ t('site.gallery_count', { current: index + 1, total: items.length }) }}
                </span>
            </template>
        </div>

        <div v-if="current.title || current.label" class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
            <p class="text-muted">
                <span v-if="current.title" class="font-medium text-foreground">{{ current.title }}</span>
                <span v-if="current.title && current.label"> - </span>
                <span v-if="current.label">{{ current.label }}</span>
            </p>
            <Link v-if="current.href" :href="current.href" class="inline-flex items-center gap-1.5 font-medium hover:underline">
                {{ current.group === 'outlet' ? t('site.gallery_view_outlet') : t('site.gallery_view_dish') }}
                <AppIcon name="arrow-right" size="11" />
            </Link>
        </div>

        <ul v-if="items.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1 no-scrollbar">
            <li v-for="(item, position) in items" :key="item.id" class="shrink-0">
                <button
                    type="button"
                    class="relative block h-16 w-20 overflow-hidden rounded-lg border bg-background"
                    :class="position === index ? 'border-accent ring-2 ring-accent/40' : 'border-border opacity-80 hover:opacity-100'"
                    :aria-label="item.title || (item.kind === 'video' ? t('site.gallery_video') : name)"
                    :aria-current="position === index"
                    @click="select(position)"
                >
                    <img v-if="item.kind === 'image'" :src="item.src" alt="" width="160" height="128" loading="lazy" decoding="async" class="h-full w-full object-cover">
                    <img v-else-if="item.thumbnail" :src="item.thumbnail" alt="" loading="lazy" class="h-full w-full object-cover">
                    <span v-if="item.kind === 'video'" class="absolute inset-0 flex items-center justify-center bg-black/30 text-white"><AppIcon name="play" size="14" /></span>
                </button>
            </li>
        </ul>
    </div>
</template>
