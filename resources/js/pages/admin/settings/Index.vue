<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import PaymentMethodManager from '@/components/admin/PaymentMethodManager.vue';
import { localized } from '@/lib/site';
import { DEFAULT_INVOICE_PATTERN, formatInvoice } from '@/lib/invoice';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { playSound } from '@/lib/sounds';

const props = defineProps({
    settings: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
    branchSettings: { type: Object, default: null },
    paymentMethods: { type: Array, default: () => [] },
    layouts: { type: Array, default: () => [] },
    defaultPattern: { type: String, default: DEFAULT_INVOICE_PATTERN },
});

const tab = ref('branch');

const form = useForm({
    discount_limit_percent: props.settings.discount_limit_percent,
    wastage_spike_multiplier: props.settings.wastage_spike_multiplier,
    pos_pin_minutes: props.settings.pos_pin_minutes,
    delivery_otp_enabled: Boolean(props.settings.delivery_otp_enabled),
});

function save() {
    form.put(route('admin.settings.update'), {
        onSuccess: () => notify.success(t('settings.saved')),
    });
}

const ACTION_ROWS = [
    { key: 'send_to_kitchen', exact: false },
    { key: 'confirm_payment', exact: true, hint: 'settings.action_confirm_payment_hint' },
    { key: 'quick_save', exact: 'locked', hint: 'settings.action_quick_save_hint' },
];

function branchFormValues() {
    const branch = props.branchSettings;

    return {
        cash_suggestions_text: (branch?.pos?.cash_suggestions ?? [1000, 500, 200, 100]).join(', '),
        vat_percent: branch?.vat_percent ?? '0.00',
        vat_inclusive: Boolean(branch?.vat_inclusive),
        service_charge_percent: branch?.service_charge_percent ?? '0.00',
        receipt_footer: branch?.receipt_footer ?? '',
        pos: JSON.parse(JSON.stringify(branch?.pos ?? { actions: {}, invoice_layout: 'classic', invoice_pattern: props.defaultPattern, invoice_seq_digits: 4 })),
    };
}

const branchForm = useForm(branchFormValues());

watch(() => props.branchSettings, () => {
    branchForm.defaults(branchFormValues());
    branchForm.reset();
    branchForm.clearErrors();
});

function chooseBranch(event) {
    router.get(route('admin.settings.index'), { branch: event.target.value }, { preserveState: true, preserveScroll: true, replace: true });
}

function saveBranch() {
    branchForm.transform((data) => {
        const { cash_suggestions_text: suggestions, ...rest } = data;

        return {
            ...rest,
            pos: {
                ...rest.pos,
                cash_suggestions: suggestions.split(/[,\s]+/).filter(Boolean).map((value) => Number(value)).filter((value) => Number.isFinite(value)),
            },
        };
    }).put(route('admin.settings.branch', props.branchSettings.id), {
        preserveScroll: true,
        onSuccess: () => notify.success(t('settings.saved')),
    });
}

function moveMethod(index, step) {
    const methods = branchForm.pos.payment_methods;
    const target = index + step;

    if (target < 0 || target >= methods.length) {
        return;
    }

    [methods[index], methods[target]] = [methods[target], methods[index]];
}

const SOUND_GROUPS = [
    { key: 'general', events: ['notification', 'success', 'error'] },
    { key: 'pos', events: ['item_added', 'order_created', 'order_paid', 'order_held', 'order_cancelled', 'online_order'] },
    { key: 'kds', events: ['kds_new', 'kds_preparing', 'kds_ready', 'kds_served'] },
];

function previewSound(event) {
    playSound(event, { preview: true, volume: branchForm.pos.sounds.volume });
}

const selectedBranch = computed(() => props.branches.find((branch) => branch.id === props.branchSettings?.id) ?? null);

const invoicePreview = computed(() => formatInvoice(branchForm.pos, {
    branchCode: selectedBranch.value?.code ?? 'BR01',
    terminal: 'T01',
    day: new Date().toISOString().slice(0, 10).replaceAll('-', ''),
    number: 1,
}));

const checkboxClass = 'h-4 w-4 rounded border-border accent-[var(--color-primary)] disabled:opacity-40';
const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';
</script>

<template>
    <div>
        <PageHeader :title="t('settings.title')" :description="t('settings.subtitle')" />

        <div class="mb-3 flex gap-1" role="tablist">
            <button
                v-for="entry in [{ key: 'branch', label: t('settings.tab_branch') }, { key: 'methods', label: t('settings.tab_methods') }, { key: 'general', label: t('settings.tab_general') }]"
                :key="entry.key"
                type="button"
                role="tab"
                :aria-selected="tab === entry.key"
                class="h-7 rounded-md border px-3 text-[13px] font-medium"
                :class="tab === entry.key ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-surface text-muted hover:text-foreground'"
                @click="tab = entry.key"
            >
                {{ entry.label }}
            </button>
        </div>

        <form v-if="branchSettings" v-show="tab === 'branch'" novalidate class="max-w-3xl space-y-4 rounded-lg border border-border bg-surface p-4" @submit.prevent="saveBranch">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,16rem)_minmax(0,1fr)] sm:items-end">
                <FormField :label="t('settings.branch')" :helper="t('settings.branch_helper')">
                    <template #default="{ id }">
                        <select :id="id" :value="branchSettings.id" :class="inputClass" @change="chooseBranch">
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }} ({{ branch.code }})</option>
                        </select>
                    </template>
                </FormField>
            </div>

            <section>
                <h2 class="text-[13px] font-semibold text-foreground">{{ t('settings.section_buttons') }}</h2>
                <p class="mb-2 text-xs text-muted">{{ t('settings.section_buttons_helper') }}</p>
                <div class="overflow-x-auto rounded-md border border-border">
                    <table class="w-full text-[13px]">
                        <thead class="bg-background text-xs text-muted">
                            <tr>
                                <th class="px-2 py-1.5 text-left font-medium">{{ t('settings.col_button') }}</th>
                                <th class="px-2 py-1.5 text-center font-medium">{{ t('settings.col_enabled') }}</th>
                                <th class="px-2 py-1.5 text-center font-medium">{{ t('settings.col_exact') }}</th>
                                <th class="px-2 py-1.5 text-center font-medium">{{ t('settings.col_invoice') }}</th>
                                <th class="px-2 py-1.5 text-center font-medium">{{ t('settings.col_kitchen') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in ACTION_ROWS" :key="row.key" class="border-t border-border">
                                <td class="px-2 py-1.5">
                                    <p class="font-medium text-foreground">{{ t(`settings.action_${row.key}`) }}</p>
                                    <p v-if="row.hint" class="text-xs text-muted">{{ t(row.hint) }}</p>
                                </td>
                                <td class="px-2 py-1.5 text-center"><input v-model="branchForm.pos.actions[row.key].enabled" type="checkbox" :class="checkboxClass" :aria-label="`${t(`settings.action_${row.key}`)} - ${t('settings.col_enabled')}`"></td>
                                <td class="px-2 py-1.5 text-center">
                                    <input v-if="row.exact === true" v-model="branchForm.pos.actions[row.key].exact_amount" type="checkbox" :class="checkboxClass" :aria-label="`${t(`settings.action_${row.key}`)} - ${t('settings.col_exact')}`">
                                    <input v-else-if="row.exact === 'locked'" type="checkbox" checked disabled :class="checkboxClass" :aria-label="`${t(`settings.action_${row.key}`)} - ${t('settings.col_exact')}`">
                                    <span v-else class="text-muted">-</span>
                                </td>
                                <td class="px-2 py-1.5 text-center"><input v-model="branchForm.pos.actions[row.key].print_invoice" type="checkbox" :class="checkboxClass" :aria-label="`${t(`settings.action_${row.key}`)} - ${t('settings.col_invoice')}`"></td>
                                <td class="px-2 py-1.5 text-center"><input v-model="branchForm.pos.actions[row.key].print_kitchen" type="checkbox" :class="checkboxClass" :aria-label="`${t(`settings.action_${row.key}`)} - ${t('settings.col_kitchen')}`"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h2 class="text-[13px] font-semibold text-foreground">{{ t('settings.section_payments') }}</h2>
                <p class="mb-2 text-xs text-muted">{{ t('settings.section_payments_helper') }}</p>
                <ul class="divide-y divide-border rounded-md border border-border">
                    <li v-for="(entry, index) in branchForm.pos.payment_methods" :key="entry.method" class="flex items-center gap-2 px-2 py-1.5">
                        <input v-model="entry.enabled" type="checkbox" :class="checkboxClass" :aria-label="entry.name">
                        <span class="min-w-0 flex-1 text-[13px] font-medium" :class="entry.enabled ? 'text-foreground' : 'text-muted line-through'">{{ localized({ name: entry.name, name_bn: entry.name_bn }, 'name') }}</span>
                        <button type="button" class="flex h-6 w-6 items-center justify-center rounded-md border border-border text-muted hover:text-foreground disabled:opacity-40" :disabled="index === 0" :aria-label="t('settings.move_up')" @click="moveMethod(index, -1)">
                            <AppIcon name="chevron-up" size="10" />
                        </button>
                        <button type="button" class="flex h-6 w-6 items-center justify-center rounded-md border border-border text-muted hover:text-foreground disabled:opacity-40" :disabled="index === branchForm.pos.payment_methods.length - 1" :aria-label="t('settings.move_down')" @click="moveMethod(index, 1)">
                            <AppIcon name="chevron-down" size="10" />
                        </button>
                    </li>
                </ul>
                <p v-if="branchForm.errors['pos.payment_methods']" class="mt-1 text-xs text-danger">{{ branchForm.errors['pos.payment_methods'] }}</p>
            </section>

            <section class="space-y-3 border-t border-border pt-3">
                <div>
                    <h2 class="text-[13px] font-semibold text-foreground">{{ t('settings.section_sounds') }}</h2>
                    <p class="text-xs text-muted">{{ t('settings.sounds_helper') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:items-center">
                    <SwitchField v-model="branchForm.pos.sounds.enabled" :label="t('settings.sounds_enabled')" />
                    <label class="flex items-center gap-2 text-[13px] text-foreground">
                        <span class="shrink-0">{{ t('settings.sounds_volume') }}</span>
                        <input v-model.number="branchForm.pos.sounds.volume" type="range" min="0" max="100" step="5" :disabled="! branchForm.pos.sounds.enabled" class="w-full accent-[var(--color-primary)] disabled:opacity-50">
                        <span class="w-9 shrink-0 text-right tabular-nums text-muted">{{ branchForm.pos.sounds.volume }}%</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-3" :class="branchForm.pos.sounds.enabled ? '' : 'opacity-50'">
                    <div v-for="group in SOUND_GROUPS" :key="group.key">
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ t(`settings.sounds_group_${group.key}`) }}</h3>
                        <ul class="divide-y divide-border rounded-md border border-border">
                            <li v-for="event in group.events" :key="event" class="flex items-center gap-2 px-2 py-1.5">
                                <input v-model="branchForm.pos.sounds.events[event]" type="checkbox" :class="checkboxClass" :disabled="! branchForm.pos.sounds.enabled" :aria-label="t(`settings.sound_${event}`)">
                                <span class="min-w-0 flex-1 text-[13px] text-foreground">{{ t(`settings.sound_${event}`) }}</span>
                                <button type="button" class="flex h-6 shrink-0 items-center gap-1 rounded-md border border-border px-1.5 text-xs text-foreground hover:border-accent" :aria-label="`${t('settings.sounds_test')}: ${t(`settings.sound_${event}`)}`" @click="previewSound(event)">
                                    <AppIcon name="bell" size="10" />
                                    {{ t('settings.sounds_test') }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <p class="text-xs text-muted">{{ t('settings.sounds_device_note') }}</p>
            </section>

            <section>
                <FormField :label="t('settings.cash_suggestions')" :helper="t('settings.cash_suggestions_helper')" :error="branchForm.errors['pos.cash_suggestions'] ?? branchForm.errors['pos.cash_suggestions.0']">
                    <template #default="{ id }">
                        <input :id="id" v-model="branchForm.cash_suggestions_text" type="text" placeholder="1000, 500, 200, 100" :class="[inputClass, 'max-w-xs tabular-nums']">
                    </template>
                </FormField>
            </section>

            <section class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
                <FormField :label="t('settings.vat_percent')" :error="branchForm.errors.vat_percent" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="branchForm.vat_percent" type="number" min="0" max="100" step="0.01" :class="[inputClass, 'text-right tabular-nums']">
                    </template>
                </FormField>
                <FormField :label="t('settings.service_charge')" :error="branchForm.errors.service_charge_percent" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="branchForm.service_charge_percent" type="number" min="0" max="100" step="0.01" :class="[inputClass, 'text-right tabular-nums']">
                    </template>
                </FormField>
                <div class="flex items-end pb-1">
                    <SwitchField v-model="branchForm.vat_inclusive" :label="t('settings.vat_inclusive')" />
                </div>
            </section>

            <section class="space-y-3 border-t border-border pt-3">
                <h2 class="text-[13px] font-semibold text-foreground">{{ t('settings.section_invoice') }}</h2>

                <div role="radiogroup" :aria-label="t('settings.invoice_layout')" class="grid grid-cols-3 gap-2">
                    <label
                        v-for="layout in layouts"
                        :key="layout"
                        class="flex cursor-pointer items-center gap-2 rounded-md border px-2 py-1.5 text-[13px]"
                        :class="branchForm.pos.invoice_layout === layout ? 'border-accent bg-accent/10' : 'border-border bg-background'"
                    >
                        <input v-model="branchForm.pos.invoice_layout" type="radio" name="invoice_layout" :value="layout" class="accent-[var(--color-primary)]">
                        {{ t(`settings.layout_${layout}`) }}
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_8rem]">
                    <FormField :label="t('settings.invoice_pattern')" :helper="t('settings.invoice_pattern_helper')" :error="branchForm.errors['pos.invoice_pattern']" required>
                        <template #default="{ id }">
                            <div class="flex gap-1">
                                <input :id="id" v-model="branchForm.pos.invoice_pattern" type="text" maxlength="40" :class="[inputClass, 'font-mono']">
                                <button type="button" class="h-7 shrink-0 rounded-md border border-border px-2 text-xs text-foreground hover:border-accent" @click="branchForm.pos.invoice_pattern = defaultPattern">{{ t('settings.reset_pattern') }}</button>
                            </div>
                        </template>
                    </FormField>
                    <FormField :label="t('settings.invoice_digits')" :error="branchForm.errors['pos.invoice_seq_digits']" required>
                        <template #default="{ id }">
                            <input :id="id" v-model.number="branchForm.pos.invoice_seq_digits" type="number" min="1" max="8" :class="[inputClass, 'text-right tabular-nums']">
                        </template>
                    </FormField>
                </div>
                <p class="text-xs text-muted">{{ t('settings.invoice_preview') }}: <span class="font-mono text-foreground">{{ invoicePreview }}</span></p>

                <FormField :label="t('settings.receipt_footer')" :error="branchForm.errors.receipt_footer">
                    <template #default="{ id }">
                        <input :id="id" v-model="branchForm.receipt_footer" type="text" maxlength="255" :class="inputClass">
                    </template>
                </FormField>
            </section>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="branchForm.processing"
                >
                    <AppIcon v-if="branchForm.processing" name="spinner" size="12" spin />
                    {{ t('common.save') }}
                </button>
            </div>
        </form>

        <PaymentMethodManager v-show="tab === 'methods'" :methods="paymentMethods" />

        <form v-show="tab === 'general'" novalidate class="max-w-2xl rounded-lg border border-border bg-surface p-4" @submit.prevent="save">
            <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                <FormField :label="t('settings.discount_limit')" :helper="t('settings.discount_limit_helper')" :error="form.errors.discount_limit_percent" required>
                    <template #default="{ id }">
                        <input
                            :id="id"
                            v-model="form.discount_limit_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="h-7 w-full rounded-md border bg-surface px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                            :class="form.errors.discount_limit_percent ? 'border-danger' : 'border-border focus:border-accent'"
                        >
                    </template>
                </FormField>

                <FormField :label="t('settings.wastage_spike')" :helper="t('settings.wastage_spike_helper')" :error="form.errors.wastage_spike_multiplier" required>
                    <template #default="{ id }">
                        <input
                            :id="id"
                            v-model="form.wastage_spike_multiplier"
                            type="number"
                            min="1"
                            max="10"
                            step="0.1"
                            class="h-7 w-full rounded-md border bg-surface px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                            :class="form.errors.wastage_spike_multiplier ? 'border-danger' : 'border-border focus:border-accent'"
                        >
                    </template>
                </FormField>

                <FormField :label="t('settings.pos_pin')" :helper="t('settings.pos_pin_helper')" :error="form.errors.pos_pin_minutes" required>
                    <template #default="{ id }">
                        <input
                            :id="id"
                            v-model="form.pos_pin_minutes"
                            type="number"
                            min="1"
                            max="60"
                            class="h-7 w-full rounded-md border bg-surface px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                            :class="form.errors.pos_pin_minutes ? 'border-danger' : 'border-border focus:border-accent'"
                        >
                    </template>
                </FormField>

            </div>

            <div class="mt-3 border-t border-border pt-3">
                <SwitchField v-model="form.delivery_otp_enabled" :label="t('settings.delivery_otp')" />
            </div>

            <div class="mt-3 flex justify-end">
                <button
                    type="submit"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <AppIcon v-if="form.processing" name="spinner" size="12" spin />
                    {{ t('common.save') }}
                </button>
            </div>
        </form>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
