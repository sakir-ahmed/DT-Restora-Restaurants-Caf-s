<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { t } from '@/lib/i18n';

const visible = ref(false);
let applyUpdate = null;

function onUpdate(event) {
    applyUpdate = event.detail?.apply ?? null;
    visible.value = true;
}

function later() {
    visible.value = false;
}

function now() {
    visible.value = false;

    if (typeof applyUpdate === 'function') {
        applyUpdate();
    }
}

onMounted(() => window.addEventListener('pwa-update', onUpdate));
onBeforeUnmount(() => window.removeEventListener('pwa-update', onUpdate));
</script>

<template>
    <div
        v-if="visible"
        class="fixed bottom-3 right-3 z-[80] flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-[13px] shadow-lg"
        role="status"
    >
        <span>{{ t('sync.update_available') }}</span>
        <button type="button" class="h-7 rounded-md border border-border px-2" @click="later">{{ t('sync.update_later') }}</button>
        <button type="button" class="h-7 rounded-md bg-primary px-2 font-semibold text-primary-foreground" @click="now">{{ t('sync.update_now') }}</button>
    </div>
</template>
