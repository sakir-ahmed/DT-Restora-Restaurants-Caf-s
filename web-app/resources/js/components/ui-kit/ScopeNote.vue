<script setup>
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';

/**
 * Says where a group of settings applies: to every branch (shared) or to one
 * branch only. Shown above a form so nobody has to guess which kind it is.
 */
defineProps({
    scope: { type: String, required: true, validator: (value) => ['global', 'branch'].includes(value) },
    branch: { type: String, default: '' },
    /** Replaces the default sentence. */
    text: { type: String, default: '' },
});
</script>

<template>
    <div
        class="mb-3 flex items-start gap-2 rounded-md border px-2.5 py-1.5 text-xs"
        :class="scope === 'global' ? 'border-accent/40 bg-accent/10 text-foreground' : 'border-warning/50 bg-warning/10 text-foreground'"
        role="note"
    >
        <AppIcon :name="scope === 'global' ? 'globe' : 'building'" size="12" class="mt-0.5 shrink-0" />
        <p>
            <span class="font-semibold">{{ scope === 'global' ? t('settings.scope_global') : (branch ? t('settings.scope_branch_named', { branch }) : t('settings.scope_branch')) }}.</span>
            {{ text || (scope === 'global' ? t('settings.scope_global_note') : t('settings.scope_branch_note')) }}
        </p>
    </div>
</template>
