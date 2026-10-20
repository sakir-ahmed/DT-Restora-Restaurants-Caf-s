<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DishArt from './DishArt.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { dishKind, localized, photoSrc } from '@/lib/site';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const fallbackKinds = ['bowl', 'burger', 'coffee', 'pizza'];
const INTERVAL_MS = 3800;

const slides = computed(() => {
    const dishes = props.items.slice(0, 6).map((item) => {
        const prices = (item.variants ?? []).map((variant) => Number(variant.price));

        return {
            key: `item-${item.id}`,
            name: localized(item, 'name'),
            photo: item.cover_url ?? photoSrc(item.photo_path),
            kind: dishKind(item.category?.name ?? item.category ?? ''),
            seed: item.id,
            price: prices.length ? Math.min(...prices) : Number(item.base_price),
        };
    });

    return dishes.length
        ? dishes
        : fallbackKinds.map((kind, index) => ({ key: `art-${kind}`, name: null, photo: null, kind, seed: index + 2, price: null }));
});

const active = ref(0);
const paused = ref(false);
const reduceMotion = ref(false);
let timer = null;

const current = computed(() => slides.value[active.value] ?? slides.value[0]);

function go(index) {
    active.value = (index + slides.value.length) % slides.value.length;
}

function start() {
    stop();

    if (reduceMotion.value || slides.value.length < 2) {
        return;
    }

    timer = window.setInterval(() => {
        if (! paused.value) {
            go(active.value + 1);
        }
    }, INTERVAL_MS);
}

function stop() {
    if (timer) {
        window.clearInterval(timer);
        timer = null;
    }
}

onMounted(() => {
    reduceMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    start();
});

onBeforeUnmount(stop);
</script>

<template>
    <div
        class="hero-showcase relative overflow-hidden rounded-xl border border-border bg-background"
        role="group"
        :aria-label="t('site.hero_showcase')"
        @mouseenter="paused = true"
        @mouseleave="paused = false"
        @focusin="paused = true"
        @focusout="paused = false"
    >
        <div class="hero-stage relative aspect-[4/3] w-full overflow-hidden">
            <span class="hero-glow hero-glow-a" aria-hidden="true" />
            <span class="hero-glow hero-glow-b" aria-hidden="true" />

            <div
                v-for="(slide, index) in slides"
                :key="slide.key"
                class="hero-slide absolute inset-0 flex items-center justify-center"
                :class="{ 'is-active': index === active }"
                :aria-hidden="index !== active"
            >
                <img
                    v-if="slide.photo"
                    :src="slide.photo"
                    :alt="slide.name ?? ''"
                    width="640"
                    height="480"
                    :loading="index === 0 ? 'eager' : 'lazy'"
                    decoding="async"
                    class="hero-photo h-full w-full object-cover"
                >
                <div v-else class="hero-art h-3/5 w-3/5 max-w-72"><DishArt :kind="slide.kind" :seed="slide.seed" /></div>
            </div>

            <span class="hero-chip hero-chip-a" aria-hidden="true"><AppIcon name="fire" size="12" />{{ t('site.hero_badge_fresh') }}</span>
            <span class="hero-chip hero-chip-b" aria-hidden="true"><AppIcon name="truck-fast" size="12" />{{ t('site.hero_badge_tracking') }}</span>
            <span class="hero-chip hero-chip-c" aria-hidden="true"><AppIcon name="qrcode" size="12" />{{ t('site.hero_badge_qr') }}</span>

            <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 bg-gradient-to-t from-black/70 via-black/30 to-transparent px-4 pb-4 pt-12 text-white">
                <div class="min-w-0">
                    <Transition name="hero-caption" mode="out-in">
                        <p :key="current.key" class="truncate text-lg font-semibold leading-tight">{{ current.name ?? t('site.hero_title_short') }}</p>
                    </Transition>
                    <p v-if="current.price !== null" class="text-sm tabular-nums text-white/80">
                        {{ formatMoney(current.price, { decimals: 0 }) }} {{ t('site.currency') }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1.5" role="tablist">
                    <button
                        v-for="(slide, index) in slides"
                        :key="slide.key"
                        type="button"
                        role="tab"
                        :aria-selected="index === active"
                        :aria-label="slide.name ?? String(index + 1)"
                        class="hero-dot h-1.5 rounded-full"
                        :class="index === active ? 'w-6 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/80'"
                        @click="go(index)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.hero-stage {
    background: linear-gradient(135deg, var(--color-surface), var(--color-background));
}

.hero-slide {
    opacity: 0;
    transition: opacity 0.9s ease;
}

.hero-slide.is-active {
    opacity: 1;
}

.hero-slide.is-active .hero-photo {
    animation: hero-zoom 7s ease-out forwards;
}

.hero-slide.is-active .hero-art {
    animation: hero-bob 3.6s ease-in-out infinite;
}

.hero-glow {
    position: absolute;
    border-radius: 9999px;
    filter: blur(48px);
    opacity: 0.45;
    background: var(--color-accent);
}

.hero-glow-a {
    width: 55%;
    height: 55%;
    left: -10%;
    top: -10%;
    animation: hero-drift 9s ease-in-out infinite;
}

.hero-glow-b {
    width: 45%;
    height: 45%;
    right: -8%;
    bottom: 10%;
    animation: hero-drift 11s ease-in-out infinite reverse;
}

.hero-chip {
    position: absolute;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    border-radius: 0.5rem;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    color: var(--color-foreground);
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    box-shadow: 0 4px 14px rgb(0 0 0 / 0.12);
    animation: hero-float 5s ease-in-out infinite;
}

.hero-chip-a {
    left: 0.75rem;
    top: 0.75rem;
}

.hero-chip-b {
    right: 0.75rem;
    top: 28%;
    animation-delay: -1.7s;
}

.hero-chip-c {
    left: 0.75rem;
    top: 52%;
    animation-delay: -3.2s;
}

.hero-caption-enter-active,
.hero-caption-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.hero-caption-enter-from {
    opacity: 0;
    transform: translateY(6px);
}

.hero-caption-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.hero-dot {
    transition: width 0.3s ease, background-color 0.3s ease;
}

@keyframes hero-zoom {
    from { transform: scale(1); }
    to { transform: scale(1.1); }
}

@keyframes hero-bob {
    0%, 100% { transform: translateY(0) rotate(-1.5deg); }
    50% { transform: translateY(-10px) rotate(1.5deg); }
}

@keyframes hero-float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}

@keyframes hero-drift {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(14px, 10px); }
}

@media (prefers-reduced-motion: reduce) {
    .hero-slide,
    .hero-slide.is-active .hero-photo,
    .hero-slide.is-active .hero-art,
    .hero-glow,
    .hero-chip {
        animation: none;
        transition: none;
    }
}
</style>
