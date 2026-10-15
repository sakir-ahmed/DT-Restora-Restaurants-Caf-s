<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    wide: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

watch(() => props.open, (open) => {
    if (open) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}
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
                class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm"
                @click="emit('close')"
            />
        </Transition>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-x-full"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="open"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                class="fixed inset-y-0 right-0 z-50 flex w-full flex-col border-l border-border bg-surface shadow-xl outline-none sm:w-[40%] sm:min-w-[420px]"
                :class="wide ? 'sm:w-[56%]' : ''"
                tabindex="-1"
                @keydown="onKeydown"
            >
                <header class="flex h-12 shrink-0 items-center justify-between gap-3 border-b border-border px-4">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-foreground">{{ title }}</h2>
                        <p v-if="subtitle" class="truncate text-xs text-muted">{{ subtitle }}</p>
                    </div>

                    <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded-md text-muted hover:bg-background hover:text-foreground"
                        :aria-label="t('common.close')"
                        @click="emit('close')"
                    >
                        <AppIcon name="xmark" />
                    </button>
                </header>

                <div class="thin-scrollbar flex-1 overflow-y-auto p-4">
                    <slot />
                </div>

                <footer v-if="$slots.footer" class="shrink-0 border-t border-border p-4">
                    <div class="flex items-center justify-end gap-2">
                        <slot name="footer" />
                    </div>
                </footer>
            </aside>
        </Transition>
    </Teleport>
</template>
