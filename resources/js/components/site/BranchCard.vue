<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import TiltCard from './TiltCard.vue';
import { t } from '@/lib/i18n';
import { localized, locationUrl, menuUrl } from '@/lib/site';

const props = defineProps({
    branch: { type: Object, required: true },
});

const name = computed(() => localized(props.branch, 'name'));
const zoneNames = computed(() => (props.branch.zones ?? []).map((zone) => zone.name).slice(0, 4).join(', '));
</script>

<template>
    <TiltCard as="article" class="flex h-full flex-col rounded-xl border border-border bg-surface p-6 shadow-sm">
        <div class="flex items-start gap-4">
            <span class="flex h-11 w-14 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground ">
                <AppIcon name="store" size="22" />
            </span>
            <div class="min-w-0">
                <h3 class="text-xl font-bold leading-snug">
                    <Link :href="locationUrl(branch.slug)" class="hover:underline">{{ name }}</Link>
                </h3>
                <p class="mt-1 flex items-start gap-1.5 text-sm text-muted">
                    <AppIcon name="location-dot" size="12" class="mt-1 shrink-0" />
                    <span>{{ [branch.address, branch.city].filter(Boolean).join(', ') }}</span>
                </p>
                <p v-if="branch.phone" class="mt-1 flex items-center gap-1.5 text-sm text-muted">
                    <AppIcon name="phone" size="12" class="shrink-0" />
                    <a :href="`tel:${branch.phone}`" class="hover:text-foreground">{{ branch.phone }}</a>
                </p>
            </div>
        </div>

        <p v-if="zoneNames" class="mt-4 rounded-xl bg-surface px-3 py-2 text-xs text-muted">
            <span class="font-semibold text-foreground">{{ t('site.delivery_areas') }}:</span>
            {{ zoneNames }}
        </p>

        <div class="mt-auto flex flex-wrap gap-2 pt-5">
            <Link
                v-if="branch.online_ordering_enabled !== false"
                :href="menuUrl(branch.code)"
                class="inline-flex h-11 items-center gap-2 rounded-lg bg-accent px-5 text-sm font-semibold text-accent-foreground "
            >
                <AppIcon name="bag-shopping" size="13" />
                {{ t('site.order_from_branch') }}
            </Link>
            <Link :href="locationUrl(branch.slug)" class="inline-flex h-11 items-center gap-2 rounded-lg border border-border px-5 text-sm font-medium transition-colors hover:border-accent hover:bg-accent/10">
                {{ t('site.view_location') }}
                <AppIcon name="arrow-right" size="11" />
            </Link>
        </div>
    </TiltCard>
</template>
