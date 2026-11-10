<script setup>
import { ref } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

defineProps({
    notifications: { type: Array, default: () => [] }, // [{id, icon, title, body, href}]
});

const open = ref(false);
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative flex h-8 w-8 items-center justify-center rounded-md text-muted hover:bg-background hover:text-foreground"
            :aria-label="t('nav.notifications')"
            :aria-expanded="open"
            @click="open = !open"
        >
            <AppIcon name="bell" size="16" />
            <span
                v-if="notifications.length"
                class="absolute right-1 top-1 flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-danger px-0.5 text-[9px] font-bold text-white"
            >
                {{ notifications.length > 9 ? '9+' : notifications.length }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-40 mt-1 w-80 rounded-lg border border-border bg-surface shadow-lg"
            @keydown.escape="open = false"
        >
            <div class="flex items-center justify-between border-b border-border px-3 py-2">
                <span class="text-sm font-semibold text-foreground">{{ t('nav.notifications') }}</span>
                <button type="button" class="text-xs text-muted hover:text-foreground">{{ t('common.mark_all_read') }}</button>
            </div>

            <ul v-if="notifications.length" class="thin-scrollbar max-h-72 overflow-y-auto p-1">
                <li v-for="item in notifications" :key="item.id">
                    <a
                        :href="item.href ?? '#'"
                        class="flex items-start gap-2 rounded-md px-2 py-2 hover:bg-background"
                    >
                        <span class="mt-0.5 text-warning">
                            <AppIcon :name="item.icon ?? 'bell'" size="14" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-[13px] font-medium text-foreground">{{ item.title }}</span>
                            <span v-if="item.body" class="block truncate text-xs text-muted">{{ item.body }}</span>
                        </span>
                    </a>
                </li>
            </ul>

            <p v-else class="px-3 py-6 text-center text-[13px] text-muted">{{ t('nav.no_notifications') }}</p>
        </div>
    </div>
</template>
