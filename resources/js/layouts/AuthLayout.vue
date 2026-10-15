<script setup>
import LanguageSwitch from '@/components/ui-kit/LanguageSwitch.vue';
import ThemeToggle from '@/components/ui-kit/ThemeToggle.vue';
</script>

<template>
    <div class="relative min-h-dvh bg-background text-foreground">
        <div class="absolute top-4 right-4 z-20 flex items-center gap-1 rounded-md border border-border bg-surface p-1 sm:top-5 sm:right-5">
            <LanguageSwitch />
            <ThemeToggle />
        </div>

        <slot />
    </div>
</template>
