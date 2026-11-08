<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import LanguageSwitch from '@/components/ui-kit/LanguageSwitch.vue';
import { t } from '@/lib/i18n';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-[#2c1810] px-5 py-10 text-[#f4ead2]">
        <div class="absolute right-4 top-4">
            <LanguageSwitch />
        </div>

        <div class="w-full max-w-sm rounded-2xl border border-[#b8892f]/60 bg-[#3b2418] p-7 text-center shadow-2xl">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-[#b8892f] bg-[#2c1810] text-[#d9b25a]">
                <AppIcon name="qrcode" size="28" />
            </span>

            <h1 class="mt-5 font-serif text-2xl font-semibold leading-snug">{{ t('tablemenu.expired_title') }}</h1>
            <p class="mx-auto mt-3 max-w-xs text-sm leading-relaxed text-[#e8d9b5]/80">{{ t('tablemenu.expired_line') }}</p>

            <div class="mx-auto my-6 h-px w-24 bg-gradient-to-r from-transparent via-[#b8892f] to-transparent" />

            <Link :href="route('web.menu')" class="inline-flex h-10 items-center gap-2 rounded-full border border-[#b8892f] px-5 text-sm font-medium text-[#f4ead2] hover:bg-[#b8892f]/15">
                <AppIcon name="book-open" size="14" />
                {{ t('tablemenu.expired_menu') }}
            </Link>
        </div>
    </div>
</template>

<script>
export default { layout: null };
</script>
