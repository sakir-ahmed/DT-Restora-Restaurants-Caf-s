<script setup>
import { usePage } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import LanguageSwitch from '@/components/ui-kit/LanguageSwitch.vue';
import ThemeToggle from '@/components/ui-kit/ThemeToggle.vue';
import { t } from '@/lib/i18n';

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
                <a
                    href="/login"
                    class="ml-2 inline-flex h-7 items-center rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                >
                    {{ t('common.sign_in') }}
                </a>
            </div>
        </header>

        <main class="mx-auto flex max-w-3xl flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
            <h1 class="text-3xl font-bold tracking-tight">{{ page.props.appName }}</h1>
            <p class="max-w-xl text-[15px] text-muted">{{ t('welcome.intro') }}</p>

            <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
                <a
                    href="/login"
                    class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    <AppIcon name="cash-register" size="16" />
                    {{ t('welcome.open_admin') }}
                </a>
                <a
                    href="/api-docs"
                    class="inline-flex h-9 items-center gap-2 rounded-md border border-border bg-surface px-4 text-sm font-medium text-foreground hover:bg-background/60"
                >
                    <AppIcon name="book-open" size="16" />
                    {{ t('welcome.api_docs') }}
                </a>
            </div>
        </main>

        <footer class="p-4 text-center text-xs text-muted">Datatech BD Ltd, Dhaka, Bangladesh</footer>
    </div>
</template>
