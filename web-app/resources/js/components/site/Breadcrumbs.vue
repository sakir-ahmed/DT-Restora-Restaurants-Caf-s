<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';

defineProps({
    items: { type: Array, required: true },
});
</script>

<template>
    <nav :aria-label="t('site.breadcrumb')">
        <ol class="flex flex-wrap items-center gap-1.5 text-sm text-muted">
            <li v-for="(crumb, index) in items" :key="crumb.href ?? crumb.label" class="flex items-center gap-1.5">
                <AppIcon v-if="index > 0" name="chevron-right" size="10" class="opacity-60" />
                <Link v-if="crumb.href" :href="crumb.href" class="rounded px-0.5 hover:text-foreground">{{ crumb.label }}</Link>
                <span v-else aria-current="page" class="font-medium text-foreground">{{ crumb.label }}</span>
            </li>
        </ol>
    </nav>
</template>
