<script setup>
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import SmsTemplateEditor from '@/components/admin/SmsTemplateEditor.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

/**
 * Every kind of SMS in one list: switch it on or off (saved at once) and edit
 * its wording in place. Messages without editable wording, such as sign-in
 * codes and promotions, only have the switch.
 */
const props = defineProps({
    features: { type: Array, required: true },
    templates: { type: Array, required: true },
});

const GROUPS = ['customer', 'owner', 'system'];

const states = reactive(Object.fromEntries(props.features.map((feature) => [feature.key, feature.enabled])));
const opened = ref(null);
const saving = ref(false);

const grouped = computed(() => GROUPS
    .map((group) => ({ group, items: props.features.filter((feature) => feature.group === group) }))
    .filter((entry) => entry.items.length > 0));

function templatesFor(feature) {
    return props.templates.filter((template) => template.feature === feature.key);
}

function editedCount(feature) {
    return templatesFor(feature).filter((template) => Object.values(template.custom).some((value) => value !== null && value !== undefined)).length;
}

function toggle(feature, value) {
    const previous = states[feature.key];

    states[feature.key] = value;
    saving.value = true;

    router.put(route('admin.settings.sms.features'), { features: { ...states } }, {
        preserveScroll: true,
        onSuccess: () => notify.success(t('sms.features_saved')),
        onError: () => { states[feature.key] = previous; },
        onFinish: () => { saving.value = false; },
    });
}

function toggleEditor(feature) {
    opened.value = opened.value === feature.key ? null : feature.key;
}
</script>

<template>
    <div class="space-y-4">
        <div v-for="entry in grouped" :key="entry.group">
            <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ t('sms.groups.' + entry.group) }}</h3>

            <ul class="divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface">
                <li v-for="feature in entry.items" :key="feature.key">
                    <div class="flex items-start justify-between gap-3 px-3 py-2.5">
                        <div class="min-w-0">
                            <p class="text-[13px] font-medium text-foreground">
                                {{ t('sms.feature.' + feature.key + '.name') }}
                                <span v-if="feature.locked" class="ml-1 rounded-full bg-muted/10 px-2 py-0.5 text-xs font-normal text-muted">{{ t('sms.always_on') }}</span>
                                <span v-if="editedCount(feature) > 0" class="ml-1 rounded-full bg-accent/15 px-2 py-0.5 text-xs font-normal text-foreground">{{ t('sms.customised') }}</span>
                            </p>
                            <p class="text-xs text-muted">{{ t('sms.feature.' + feature.key + '.desc') }}</p>
                            <button v-if="templatesFor(feature).length > 0" type="button" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-accent hover:underline" :aria-expanded="opened === feature.key" @click="toggleEditor(feature)">
                                <AppIcon :name="opened === feature.key ? 'chevron-up' : 'pen-to-square'" size="10" />
                                {{ opened === feature.key ? t('sms.hide_wording') : t('sms.edit_wording', { count: templatesFor(feature).length }) }}
                            </button>
                        </div>
                        <SwitchField :model-value="states[feature.key]" :disabled="feature.locked || saving" :aria-label="t('sms.feature.' + feature.key + '.name')" class="mt-0.5 shrink-0" @update:model-value="(value) => toggle(feature, value)" />
                    </div>

                    <div v-if="opened === feature.key" class="space-y-4 border-t border-border bg-background/50 px-3 py-3">
                        <p v-if="! states[feature.key]" class="rounded-md border border-warning/50 bg-warning/10 px-2.5 py-1.5 text-xs text-foreground">{{ t('sms.feature_off_note') }}</p>
                        <template v-for="(template, index) in templatesFor(feature)" :key="template.key">
                            <hr v-if="index > 0" class="border-border">
                            <SmsTemplateEditor :template="template" />
                        </template>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
