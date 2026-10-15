<script setup>
import { Head } from '@inertiajs/vue3';
import { t } from '@/lib/i18n';
</script>

<template>
    <Head :title="t('web.terms')" />

    <article class="prose prose-sm max-w-2xl">
        <h1 class="text-xl font-bold">{{ t('web.terms_title') }}</h1>

        <div class="mt-4 space-y-3 text-sm leading-relaxed text-muted">
            <p>{{ t('web.terms_intro') }}</p>
            <p>{{ t('web.terms_orders') }}</p>
            <p>{{ t('web.terms_payments') }}</p>
        </div>
    </article>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
