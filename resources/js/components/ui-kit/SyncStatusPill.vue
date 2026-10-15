<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { outboxCount, pushOutbox } from '@/lib/offline';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    pendingCount: { type: Number, default: 0 },
});

const online = ref(navigator.onLine);
const syncing = ref(false);
const localPending = ref(0);

async function refreshPending() {
    try {
        localPending.value = await outboxCount();
    } catch {
        localPending.value = 0;
    }
}

async function updateOnline() {
    online.value = navigator.onLine;

    if (! online.value) {
        return;
    }

    syncing.value = true;

    try {
        await pushOutbox();
    } catch {
        // The outbox stays until the next successful push.
    }

    syncing.value = false;
    await refreshPending();
}

onMounted(() => {
    window.addEventListener('online', updateOnline);
    window.addEventListener('offline', updateOnline);
    window.addEventListener('outbox-changed', refreshPending);
    refreshPending();
    setInterval(refreshPending, 10000);
});

onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnline);
    window.removeEventListener('offline', updateOnline);
    window.removeEventListener('outbox-changed', refreshPending);
});

const waiting = computed(() => props.pendingCount + localPending.value);

const meta = computed(() => {
    if (syncing.value) {
        return { icon: 'rotate', classes: 'bg-accent/15 text-accent-foreground', label: t('sync.syncing') };
    }

    return online.value
        ? { icon: 'wifi', classes: 'bg-success/10 text-success', label: t('sync.online') }
        : { icon: 'plug-circle-xmark', classes: 'bg-danger/10 text-danger', label: t('sync.offline') };
});
</script>

<template>
    <span
        class="inline-flex h-6 items-center gap-1 rounded-full px-2 text-xs font-medium"
        :class="meta.classes"
        :aria-label="meta.label"
    >
        <AppIcon :name="meta.icon" size="10" :spin="syncing" />
        {{ meta.label }}

        <span v-if="waiting > 0" class="rounded-full bg-background/60 px-1 tabular-nums">
            {{ waiting }}
        </span>
    </span>
</template>
