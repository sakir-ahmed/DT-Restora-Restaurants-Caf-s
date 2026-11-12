<script setup>
import { nextTick, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    icon: { type: String, default: 'circle-info' },
    tone: { type: String, default: null }, // danger: red header icon
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
                class="flex max-h-[85vh] w-full max-w-md flex-col rounded-t-xl border border-border bg-surface shadow-lg outline-none sm:rounded-xl"
            >
                <header class="flex items-start gap-2.5 border-b border-border px-4 py-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="tone === 'danger' ? 'bg-danger/10 text-danger' : 'bg-muted/10 text-foreground'">
                        <AppIcon :name="icon" size="15" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-[15px] font-semibold text-foreground">{{ title }}</h2>
                        <p v-if="description" class="mt-0.5 text-xs text-muted">{{ description }}</p>
                    </div>
                    <button type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted hover:bg-border/60 hover:text-foreground" :aria-label="t('common.close')" @click="emit('close')">
                        <AppIcon name="xmark" size="13" />
                    </button>
                </header>

                <div class="thin-scrollbar min-h-0 flex-1 overflow-y-auto px-4 py-2 text-[13px]">
                    <slot />
                </div>

                <footer v-if="$slots.footer" class="border-t border-border px-4 py-2.5">
                    <slot name="footer" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
