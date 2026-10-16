<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';

const bar = ref(null);
let observer = null;

function syncHeight() {
    const height = bar.value?.offsetHeight ?? 0;
    document.documentElement.style.setProperty('--demo-banner-h', `${height}px`);
}

onMounted(() => {
    syncHeight();

    if (bar.value !== null && typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(syncHeight);
        observer.observe(bar.value);
    }
});

onBeforeUnmount(() => {
    observer?.disconnect();
    document.documentElement.style.setProperty('--demo-banner-h', '0px');
});
</script>

<template>
    <div
        ref="bar"
        role="status"
        class="demo-banner sticky top-0 z-20 border-b border-accent-foreground/15 bg-accent px-3 py-1 text-center text-[11px] leading-tight font-medium text-accent-foreground print:hidden"
    >
        <p class="mx-auto flex max-w-6xl items-start justify-center gap-1.5 sm:items-center">
            <AppIcon name="circle-info" size="12" class="mt-px shrink-0 sm:mt-0" />
            <span>
                <span class="font-semibold">{{ t('common.demo_banner_title') }}</span>
                {{ t('common.demo_banner') }}
                {{ t('common.demo_banner_call') }}
                <a href="tel:01304734623" class="font-semibold underline underline-offset-2">01304734623</a>.
            </span>
        </p>
    </div>
</template>
