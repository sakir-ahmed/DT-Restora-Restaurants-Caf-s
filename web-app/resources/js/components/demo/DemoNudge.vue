<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import { track } from '@/lib/demoTracker';
import { t } from '@/lib/i18n';

defineProps({
    nudge: { type: Object, default: null }, // { kind: 'tip' | 'admin', title, body, icon }
});

const emit = defineEmits(['dismiss', 'more']);
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 scale-95 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-y-2 opacity-0"
    >
        <aside
            v-if="nudge"
            role="status"
            class="demo-pop fixed bottom-[calc(var(--demo-fab,1rem)+3.25rem)] left-3 z-[55] w-[calc(100vw-1.5rem)] max-w-xs rounded-2xl border border-border bg-surface p-3 shadow-lg print:hidden sm:left-4"
        >
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-accent/15 text-accent">
                    <AppIcon :name="nudge.icon" size="15" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-accent">{{ t('demo.nudge_tag') }}</p>
                    <h3 class="text-[13px] font-semibold leading-snug text-foreground">{{ nudge.title }}</h3>
                    <p class="mt-1 text-[12px] leading-relaxed text-muted">{{ nudge.body }}</p>
                </div>
                <button type="button" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-muted hover:bg-background hover:text-foreground" :aria-label="t('demo.nudge_dismiss')" @click="emit('dismiss')">
                    <AppIcon name="xmark" size="12" />
                </button>
            </div>

            <div class="mt-3 flex items-center justify-end gap-2">
                <Link
                    v-if="nudge.kind === 'admin'"
                    href="/login"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md bg-accent px-3 text-[12px] font-semibold text-accent-foreground hover:opacity-90"
                    @click="track('cta_click', 'admin_nudge'); emit('dismiss')"
                >
                    <AppIcon name="user-shield" size="12" />
                    {{ t('demo.admin_cta_button') }}
                </Link>
                <button
                    v-else
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-[12px] font-medium text-foreground hover:bg-background"
                    @click="emit('more')"
                >
                    <AppIcon name="lightbulb" size="12" />
                    {{ t('demo.nudge_more') }}
                </button>
            </div>
        </aside>
    </Transition>
</template>
