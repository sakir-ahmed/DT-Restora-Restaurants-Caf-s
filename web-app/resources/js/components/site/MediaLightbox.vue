<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import MediaViewer from './MediaViewer.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    start: { type: Number, default: 0 },
});

const emit = defineEmits(['close']);

const closeButton = ref(null);

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

watch(() => props.open, async (open) => {
    document.documentElement.classList.toggle('overflow-hidden', open);

    if (open) {
        await nextTick();
        closeButton.value?.focus();
    }
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.documentElement.classList.remove('overflow-hidden');
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
            <div v-if="open" class="site fixed inset-0 z-[70] flex items-center justify-center bg-black/80 p-3 text-foreground sm:p-6" @click.self="emit('close')">
                <div class="max-h-full w-full max-w-4xl overflow-y-auto rounded-xl border border-border bg-surface p-3 shadow-xl sm:p-4" role="dialog" aria-modal="true" :aria-label="t('site.nav_gallery')">
                    <div class="mb-3 flex justify-end">
                        <button ref="closeButton" type="button" class="inline-flex h-9 items-center gap-2 rounded-lg border border-border px-3 text-sm font-medium hover:bg-background" @click="emit('close')">
                            <AppIcon name="xmark" size="13" />
                            {{ t('site.gallery_close') }}
                        </button>
                    </div>
                    <MediaViewer :items="items" :start="start" aspect="aspect-video" />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
