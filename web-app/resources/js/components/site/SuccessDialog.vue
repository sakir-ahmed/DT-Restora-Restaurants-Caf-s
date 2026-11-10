<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    text: { type: String, default: '' },
    closeLabel: { type: String, required: true },
});

const emit = defineEmits(['close']);

const button = ref(null);

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

watch(() => props.open, async (open) => {
    if (open) {
        await nextTick();
        button.value?.focus();
    }
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="site fixed inset-0 z-[70] flex items-center justify-center bg-black/60 p-4 text-foreground" @click.self="emit('close')">
                <div class="w-full max-w-sm rounded-xl border border-border bg-surface p-6 text-center shadow-xl" role="dialog" aria-modal="true" aria-labelledby="success-dialog-title">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success/15 text-success">
                        <AppIcon name="circle-check" size="28" />
                    </span>
                    <h2 id="success-dialog-title" class="mt-4 text-xl font-bold">{{ title }}</h2>
                    <p v-if="text" class="mt-1 text-sm text-muted">{{ text }}</p>
                    <button ref="button" type="button" class="mt-5 inline-flex h-11 w-full items-center justify-center rounded-lg bg-accent px-4 text-base font-semibold text-accent-foreground hover:opacity-90" @click="emit('close')">
                        {{ closeLabel }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
