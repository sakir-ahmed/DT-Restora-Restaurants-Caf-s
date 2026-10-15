<script setup>
import { ref } from 'vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    accept: { type: String, default: 'image/*' },
    maxSizeMb: { type: Number, default: 5 },
    modelValue: { type: [File, Object], default: null },
    label: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const dragging = ref(false);
const previewUrl = ref(null);
const inputEl = ref(null);

function onDrop(event) {
    dragging.value = false;
    handle(event.dataTransfer?.files?.[0]);
}

function onChoose(event) {
    handle(event.target.files?.[0]);
    event.target.value = '';
}

function handle(file) {
    if (! file) {
        return;
    }

    if (file.size > props.maxSizeMb * 1024 * 1024) {
        notify.error(t('common.file_too_large', { size: props.maxSizeMb }));

        return;
    }

    if (props.accept !== '*' && ! file.type.match(props.accept.replace('*', ''))) {
        notify.error(t('common.file_type_invalid'));

        return;
    }

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    if (file.type.startsWith('image/')) {
        previewUrl.value = URL.createObjectURL(file);
    }

    emit('update:modelValue', file);
}

function clear() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    previewUrl.value = null;
    emit('update:modelValue', null);
}
</script>

<template>
    <div>
        <div
            class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-4 text-center transition-colors"
            :class="dragging ? 'border-accent bg-accent/5' : 'border-border bg-surface'"
            role="button"
            tabindex="0"
            :aria-label="label || t('common.upload')"
            @keydown.enter="inputEl?.click()"
            @click="inputEl?.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <template v-if="previewUrl">
                <img :src="previewUrl" alt="" class="h-24 w-24 rounded-md object-cover">
            </template>
            <template v-else>
                <AppIcon name="download" size="20" class="text-muted" />
                <p class="text-[13px] text-muted">{{ label || t('common.drop_file') }}</p>
            </template>

            <input ref="inputEl" type="file" :accept="accept" class="hidden" @change="onChoose">
        </div>

        <button
            v-if="modelValue"
            type="button"
            class="mt-1 text-xs text-danger hover:underline"
            @click="clear"
        >
            {{ t('common.remove') }}
        </button>
    </div>
</template>
