<script setup>
import { nextTick, ref, watch } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    formula: { type: String, default: '' },
    facts: { type: Array, default: () => [] }, // [{label, value, strong}]
    comparison: { type: Object, default: null }, // {current, previous, range, text}
    notes: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const root = ref(null);

watch(() => props.open, (open) => {
    if (open) {
        nextTick(() => root.value?.focus());
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[100] flex items-end justify-center bg-black/40 p-0 backdrop-blur-sm sm:items-center sm:p-4"
                @click.self="emit('close')"
                @keydown.esc="emit('close')"
            >
                <div
                    ref="root"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    tabindex="-1"
                    class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-t-xl border border-border bg-surface shadow-lg outline-none sm:rounded-xl"
                >
                    <header class="flex items-start gap-3 border-b border-border p-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted/10 text-foreground">
                            <AppIcon name="circle-info" size="18" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold text-foreground">{{ title }}</h2>
                            <p v-if="description" class="mt-1 text-[13px] leading-relaxed text-muted">{{ description }}</p>
                        </div>
                    </header>

                    <div class="thin-scrollbar flex-1 space-y-4 overflow-y-auto p-4 text-[13px]">
                        <section v-if="formula">
                            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ t('dashboard.info.formula') }}</h3>
                            <p class="rounded-lg border border-border bg-background/50 px-3 py-2 leading-relaxed text-foreground">{{ formula }}</p>
                        </section>

                        <section v-if="facts.length">
                            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ t('dashboard.info.numbers') }}</h3>
                            <dl class="space-y-1 rounded-lg border border-border bg-background/50 p-3">
                                <div v-for="fact in facts" :key="fact.label" class="flex items-center justify-between gap-4">
                                    <dt class="text-muted">{{ fact.label }}</dt>
                                    <dd class="tabular-nums text-foreground" :class="fact.strong ? 'font-bold' : 'font-medium'">{{ fact.value }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section v-if="comparison">
                            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ t('dashboard.info.compare') }}</h3>
                            <dl class="space-y-1 rounded-lg border border-border bg-background/50 p-3">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-muted">{{ t('dashboard.info.current') }}</dt>
                                    <dd class="font-medium tabular-nums text-foreground">{{ comparison.current }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-muted">{{ t('dashboard.info.previous') }} ({{ comparison.range }})</dt>
                                    <dd class="font-medium tabular-nums text-foreground">{{ comparison.previous }}</dd>
                                </div>
                            </dl>
                            <p class="mt-2 leading-relaxed text-foreground">{{ comparison.text }}</p>
                        </section>

                        <section v-if="notes.length">
                            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ t('dashboard.info.notes') }}</h3>
                            <ul class="list-disc space-y-1 pl-4 leading-relaxed text-muted">
                                <li v-for="note in notes" :key="note">{{ note }}</li>
                            </ul>
                        </section>
                    </div>

                    <footer class="flex justify-end border-t border-border p-3">
                        <button
                            type="button"
                            class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background focus-visible:ring-2 focus-visible:ring-accent"
                            @click="emit('close')"
                        >
                            {{ t('dashboard.info.close') }}
                        </button>
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
