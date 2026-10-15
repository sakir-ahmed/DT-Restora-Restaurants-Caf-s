<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

defineProps({
    pendingCount: { type: Number, default: 0 },
});

const online = ref(navigator.onLine);
const syncing = ref(false);

function updateOnline() {
    online.value = navigator.onLine;
}

onMounted(() => {
    window.addEventListener('online', updateOnline);
    window.addEventListener('offline', updateOnline);
});

onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnline);
    window.removeEventListener('offline', updateOnline);
});

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

        <span v-if="pendingCount > 0" class="rounded-full bg-background/60 px-1 tabular-nums">
            {{ pendingCount }}
        </span>
    </span>
</template>
