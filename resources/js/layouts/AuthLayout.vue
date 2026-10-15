<script setup>
import { usePage } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import LanguageSwitch from '@/components/ui-kit/LanguageSwitch.vue';
import ThemeToggle from '@/components/ui-kit/ThemeToggle.vue';

const page = usePage();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="flex items-center justify-between p-4">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <AppIcon name="utensils" size="16" />
                </span>
                <span class="text-sm font-semibold">{{ page.props.appName }}</span>
            </div>

            <div class="flex items-center gap-1">
                <LanguageSwitch />
                <ThemeToggle />
            </div>
        </header>

        <main class="flex flex-1 items-center justify-center p-4">
            <slot />
        </main>

        <footer class="p-4 text-center text-xs text-muted">
            Datatech BD Ltd, Dhaka
        </footer>
    </div>
</template>
