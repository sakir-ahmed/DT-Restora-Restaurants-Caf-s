<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    commands: { type: Array, default: () => [] }, // [{label, icon, href | action}]
});

const open = ref(false);
const query = ref('');
const highlighted = ref(0);
const inputEl = ref(null);

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (! needle) {
        return props.commands;
    }

    return props.commands.filter((command) => command.label.toLowerCase().includes(needle));
});

function run(command) {
    if (command.href) {
        router.get(command.href);
    }

    command.action?.();

    open.value = false;
}

function openPalette() {
    open.value = true;
}

function onKeydown(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value = !open.value;

        return;
    }

    if (! open.value) {
        return;
    }

    if (event.key === 'Escape') {
        open.value = false;
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlighted.value = Math.min(highlighted.value + 1, filtered.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value = Math.max(highlighted.value - 1, 0);
    } else if (event.key === 'Enter' && filtered.value[highlighted.value]) {
        run(filtered.value[highlighted.value]);
    }
}

watch(open, (value) => {
    if (value) {
        query.value = '';
        highlighted.value = 0;

        queueMicrotask(() => inputEl.value?.focus());
    }
});

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('command-palette', openPalette);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('command-palette', openPalette);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-75 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-[12vh] backdrop-blur-sm"
                @click.self="open = false"
            >
                <div class="w-full max-w-lg overflow-hidden rounded-xl border border-border bg-surface shadow-xl">
                    <div class="flex h-11 items-center gap-2 border-b border-border px-3">
                        <AppIcon name="magnifying-glass" size="14" class="text-muted" />
                        <input
                            ref="inputEl"
                            v-model="query"
                            type="text"
                            class="w-full border-0 bg-transparent text-sm text-foreground outline-none placeholder:text-muted/60"
                            :placeholder="t('common.command_hint')"
                            @keydown="onKeydown"
                        >
                        <kbd class="rounded border border-border px-1.5 py-0.5 text-[10px] text-muted">Esc</kbd>
                    </div>

                    <ul v-if="filtered.length" class="thin-scrollbar max-h-72 overflow-y-auto p-1.5">
                        <li
                            v-for="(command, index) in filtered"
                            :key="command.label"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-[13px]"
                                :class="index === highlighted ? 'bg-accent/15 text-foreground' : 'text-foreground hover:bg-background'"
                                @mouseenter="highlighted = index"
                                @click="run(command)"
                            >
                                <AppIcon :name="command.icon ?? 'circle-info'" size="14" class="text-muted" />
                                {{ command.label }}
                            </button>
                        </li>
                    </ul>

                    <p v-else class="px-3 py-6 text-center text-[13px] text-muted">{{ t('common.no_results') }}</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
