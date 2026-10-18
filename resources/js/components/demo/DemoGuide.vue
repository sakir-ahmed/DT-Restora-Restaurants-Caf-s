<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { progress, track } from '@/lib/demoTracker';
import { t } from '@/lib/i18n';
import DemoLottie from './DemoLottie.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    hasSession: { type: Boolean, default: false },
    mobile: { type: String, default: '' },
});

const emit = defineEmits(['close', 'join']);

const steps = computed(() => [
    { key: 'menu', icon: 'utensils', href: '/menu' },
    { key: 'cart', icon: 'cart-shopping', href: '/menu' },
    { key: 'checkout', icon: 'bag-shopping', href: '/checkout' },
    { key: 'order', icon: 'receipt', href: '/checkout' },
    { key: 'admin', icon: 'user-shield', href: '/login' },
].map((step) => ({ ...step, done: progress.done.includes(step.key) })));

const doneCount = computed(() => steps.value.filter((step) => step.done).length);
const percent = computed(() => Math.round((doneCount.value / steps.value.length) * 100));

function go(step) {
    track('tour_step', step.key);
    emit('close');
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-3 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-y-3 opacity-0"
    >
        <section
            v-if="open"
            role="dialog"
            :aria-label="t('demo.guide_title')"
            class="fixed bottom-[calc(var(--demo-fab,1rem)+3.25rem)] right-3 z-[60] flex max-h-[calc(100dvh-6rem)] w-[calc(100vw-1.5rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-lg print:hidden sm:right-4"
            @keydown.esc="emit('close')"
        >
            <header class="flex items-center gap-3 bg-primary px-4 py-3 text-primary-foreground">
                <DemoLottie name="journey" :size="64" />
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-bold">{{ t('demo.guide_title') }}</h2>
                    <p v-if="hasSession" class="text-[12px] opacity-80">
                        {{ t('demo.guide_subtitle', { done: doneCount, total: steps.length }) }}
                        <span v-if="mobile" class="tabular-nums"> - {{ mobile }}</span>
                    </p>
                    <p v-else class="text-[12px] opacity-80">{{ t('demo.join_hint') }}</p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/20" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full bg-accent transition-all duration-500" :style="{ width: `${percent}%` }" />
                    </div>
                </div>
                <button type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md hover:bg-white/15" :aria-label="t('demo.close')" @click="emit('close')">
                    <AppIcon name="xmark" size="14" />
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto p-3">
                <button
                    v-if="! hasSession"
                    type="button"
                    class="mb-3 inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-accent text-sm font-semibold text-accent-foreground hover:opacity-90"
                    @click="emit('join')"
                >
                    <AppIcon name="rocket" size="14" />
                    {{ t('demo.join') }}
                </button>

                <ol class="space-y-1.5">
                    <li v-for="step in steps" :key="step.key">
                        <Link
                            :href="step.href"
                            class="group flex items-center gap-3 rounded-lg border border-border px-3 py-2 transition hover:border-accent hover:bg-background"
                            :class="step.done ? 'bg-success/5' : 'bg-surface'"
                            @click="go(step)"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                :class="step.done ? 'bg-success text-white' : 'bg-accent/15 text-accent'"
                            >
                                <AppIcon :name="step.done ? 'check' : step.icon" size="13" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13px] font-semibold text-foreground">{{ t(`demo.steps.${step.key}.title`) }}</span>
                                <span class="block text-[12px] leading-snug text-muted">{{ t(`demo.steps.${step.key}.hint`) }}</span>
                            </span>
                            <span class="shrink-0 text-[12px] font-medium" :class="step.done ? 'text-success' : 'text-muted group-hover:text-foreground'">
                                {{ step.done ? t('demo.step_done') : t('demo.step_go') }}
                            </span>
                        </Link>
                    </li>
                </ol>

                <p v-if="doneCount === steps.length" class="mt-3 text-center text-[12px] text-success">{{ t('demo.guide_all_done') }}</p>

                <div class="mt-3 flex items-center gap-3 rounded-lg border border-border bg-background/60 p-3">
                    <AppIcon name="headset" size="18" class="shrink-0 text-accent" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-semibold text-foreground">{{ t('demo.call_title') }}</p>
                        <p class="text-[12px] leading-snug text-muted">{{ t('demo.call_body') }}</p>
                    </div>
                    <a
                        href="tel:01304734623"
                        class="inline-flex h-8 shrink-0 items-center rounded-md bg-primary px-2.5 text-[12px] font-semibold text-primary-foreground hover:opacity-90"
                        @click="track('cta_click', 'call')"
                    >
                        <AppIcon name="phone" size="12" class="mr-1.5" />
                        01304734623
                    </a>
                </div>
            </div>
        </section>
    </Transition>
</template>
