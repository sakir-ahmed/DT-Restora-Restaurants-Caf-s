<script setup>
import { computed, ref, watch } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: null },
    options: { type: Array, default: () => [] }, // [{value, label, hint}]
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    allowCreate: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'create']);

const query = ref('');
const open = ref(false);
const highlighted = ref(0);
const rootEl = ref(null);

const selected = computed(() => props.options.find((option) => option.value === props.modelValue));

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (! needle) {
        return props.options;
    }

    return props.options.filter((option) => option.label.toLowerCase().includes(needle)
        || (option.hint ?? '').toLowerCase().includes(needle));
});

watch(open, (value) => {
    if (value) {
        query.value = '';
        highlighted.value = Math.max(0, filtered.value.findIndex((option) => option.value === props.modelValue));
    }
});

function select(option) {
    emit('update:modelValue', option.value);
    open.value = false;
}

function create() {
    emit('create', query.value.trim());
    open.value = false;
}

function onKeydown(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlighted.value = Math.min(highlighted.value + 1, filtered.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value = Math.max(highlighted.value - 1, 0);
    } else if (event.key === 'Enter') {
        event.preventDefault();

        if (filtered.value[highlighted.value]) {
            select(filtered.value[highlighted.value]);
        } else if (props.allowCreate && query.value.trim()) {
            create();
        }
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}

function onDocumentClick(event) {
    if (rootEl.value && ! rootEl.value.contains(event.target)) {
        open.value = false;
    }
}

watch(open, (value) => {
    if (value) {
        document.addEventListener('click', onDocumentClick);
    } else {
        document.removeEventListener('click', onDocumentClick);
    }
}, { immediate: true });
</script>

<template>
    <div ref="rootEl" class="relative">
        <button
            type="button"
            :disabled="disabled"
            class="flex h-7 w-full items-center justify-between gap-2 rounded-md border border-border bg-surface px-2 text-[13px] text-foreground hover:border-muted/60 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40 disabled:cursor-not-allowed disabled:opacity-60"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="!disabled && (open = !open)"
        >
            <span :class="selected ? '' : 'text-muted/70'">{{ selected?.label ?? placeholder ?? t('common.select') }}</span>
            <AppIcon name="chevron-down" size="12" />
        </button>

        <div
            v-if="open"
            class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-border bg-surface shadow-lg thin-scrollbar"
            role="listbox"
        >
            <div class="border-b border-border p-1.5">
                <div class="flex h-7 items-center gap-1.5 rounded border border-border bg-background px-2">
                    <AppIcon name="magnifying-glass" size="12" class="text-muted" />
                    <input
                        v-model="query"
                        type="text"
                        class="w-full border-0 bg-transparent text-[13px] text-foreground outline-none placeholder:text-muted/60"
                        :placeholder="t('common.search')"
                        @keydown="onKeydown"
                    >
                </div>
            </div>

            <ul class="p-1">
                <li v-if="filtered.length === 0 && ! (allowCreate && query.trim())" class="px-2 py-1.5 text-[13px] text-muted">
                    {{ t('common.no_results') }}
                </li>

                <li
                    v-for="(option, index) in filtered"
                    :key="option.value"
                    role="option"
                    :aria-selected="option.value === modelValue"
                    class="cursor-pointer rounded px-2 py-1.5 text-[13px]"
                    :class="index === highlighted ? 'bg-accent/15 text-foreground' : 'text-foreground hover:bg-background'"
                    @click="select(option)"
                    @mousemove="highlighted = index"
                >
                    {{ option.label }}
                    <span v-if="option.hint" class="ml-1 text-xs text-muted">{{ option.hint }}</span>
                </li>

                <li
                    v-if="allowCreate && query.trim() && filtered.length === 0"
                    class="cursor-pointer rounded px-2 py-1.5 text-[13px] font-medium text-accent hover:bg-background"
                    @click="create"
                >
                    {{ t('common.create_new', { value: query.trim() }) }}
                </li>
            </ul>
        </div>
    </div>
</template>
