<script setup>
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import BranchPosSettings from '@/components/admin/BranchPosSettings.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

/**
 * POS and billing options of one branch, edited from Settings. The same
 * options are in the branch edit form; both save to the same branch record.
 */
const props = defineProps({
    pos: { type: Object, required: true },
    layouts: { type: Array, default: () => [] },
    defaultPattern: { type: String, required: true },
    branchCode: { type: String, default: '' },
});

const form = useForm({
    pos: JSON.parse(JSON.stringify(props.pos)),
    cash_suggestions_text: (props.pos.cash_suggestions ?? []).join(', '),
    allow_negative_stock: Boolean(props.pos.allow_negative_stock),
    vat_percent: props.pos.vat_percent,
    vat_inclusive: Boolean(props.pos.vat_inclusive),
    service_charge_percent: props.pos.service_charge_percent,
    online_ordering_enabled: Boolean(props.pos.online_ordering_enabled),
});

function save() {
    form.transform((data) => {
        const { cash_suggestions_text: suggestions, ...rest } = data;

        return {
            ...rest,
            pos: {
                ...rest.pos,
                cash_suggestions: suggestions.split(/[,\s]+/).filter(Boolean).map(Number).filter((value) => Number.isFinite(value)),
            },
        };
    }).put(route('admin.settings.branch-pos'), {
        preserveScroll: true,
        onSuccess: () => notify.success(t('settings.saved')),
    });
}
</script>

<template>
    <form novalidate class="max-w-3xl rounded-lg border border-border bg-surface p-4" @submit.prevent="save">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted">{{ t('settings.section_tax_service') }}</h3>
        <div class="mb-4 grid grid-cols-2 gap-x-4 gap-y-3 border-b border-border pb-4">
            <FormField :label="t('branches.vat_percent')" required :error="form.errors.vat_percent">
                <MoneyInput v-model="form.vat_percent" prefix="%" />
            </FormField>
            <FormField :label="t('branches.service_charge')" required :error="form.errors.service_charge_percent">
                <MoneyInput v-model="form.service_charge_percent" prefix="%" />
            </FormField>
            <SwitchField v-model="form.vat_inclusive" :label="t('branches.vat_inclusive')" />
            <SwitchField v-model="form.online_ordering_enabled" :label="t('branches.online_ordering_enabled')" />
        </div>

        <BranchPosSettings
            v-model:cash-text="form.cash_suggestions_text"
            v-model:allow-negative-stock="form.allow_negative_stock"
            :pos="form.pos"
            :errors="form.errors"
            :layouts="layouts"
            :default-pattern="defaultPattern"
            :branch-code="branchCode"
        />

        <div class="mt-4 flex justify-end border-t border-border pt-3">
            <button type="submit" :disabled="form.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                <AppIcon v-if="form.processing" name="spinner" spin />
                {{ t('common.save') }}
            </button>
        </div>
    </form>
</template>
