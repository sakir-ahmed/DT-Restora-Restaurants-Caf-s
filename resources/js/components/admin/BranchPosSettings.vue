<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { localized } from '@/lib/site';
import { formatInvoice, patternUsesSequence, randomInvoiceCode } from '@/lib/invoice';
import { t } from '@/lib/i18n';
import { playSound } from '@/lib/sounds';

const props = defineProps({
    pos: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    layouts: { type: Array, default: () => [] },
    defaultPattern: { type: String, required: true },
    branchCode: { type: String, default: 'BR01' },
});

const cashText = defineModel('cashText', { type: String, default: '' });

const ACTION_ROWS = [
    { key: 'send_to_kitchen', exact: false },
    { key: 'confirm_payment', exact: true, hint: 'settings.action_confirm_payment_hint' },
    { key: 'quick_save', exact: 'locked', hint: 'settings.action_quick_save_hint' },
];

const SOUND_GROUPS = [
    { key: 'general', events: ['notification', 'success', 'error'] },
    { key: 'pos', events: ['item_added', 'order_created', 'order_paid', 'order_held', 'order_cancelled', 'online_order'] },
    { key: 'kds', events: ['kds_new', 'kds_preparing', 'kds_ready', 'kds_served'] },
];

function moveMethod(index, step) {
    const methods = props.pos.payment_methods;
    const target = index + step;

    if (target < 0 || target >= methods.length) {
        return;
    }

    [methods[index], methods[target]] = [methods[target], methods[index]];
}

function previewSound(event) {
    playSound(event, { preview: true, volume: props.pos.sounds.volume });
}

const invoicePreview = computed(() => formatInvoice(props.pos, {
    branchCode: props.branchCode || 'BR01',
    terminal: 'T01',
    day: new Date().toISOString().slice(0, 10).replaceAll('-', ''),
    time: new Date().toTimeString().slice(0, 8).replaceAll(':', ''),
    random: randomInvoiceCode(),
    number: 1,
}));

const checkboxClass = 'h-4 w-4 rounded border-border accent-[var(--color-primary)] disabled:opacity-40';
const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';
</script>

<template>
    <div class="space-y-4">
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
                        <td class="px-2 py-1.5 text-center"><input v-model="pos.actions[row.key].enabled" type="checkbox" :class="checkboxClass" :aria-label="`${t(`settings.action_${row.key}`)} - ${t('settings.col_enabled')}`"></td>
                        <td class="px-2 py-1.5 text-center">
                            <input v-if="row.exact === true" v-model="pos.actions[row.key].exact_amount" type="checkbox" :class="checkboxClass" :aria-label="`${t(`settings.action_${row.key}`)} - ${t('settings.col_exact')}`">
                            <input v-else-if="row.exact === 'locked'" type="checkbox" checked disabled :class="checkboxClass" :aria-label="`${t(`settings.action_${row.key}`)} - ${t('settings.col_exact')}`">
                            <span v-else class="text-muted">-</span>
                        </td>
                        <td class="px-2 py-1.5 text-center"><input v-model="pos.actions[row.key].print_invoice" type="checkbox" :class="checkboxClass" :aria-label="`${t(`settings.action_${row.key}`)} - ${t('settings.col_invoice')}`"></td>
                        <td class="px-2 py-1.5 text-center"><input v-model="pos.actions[row.key].print_kitchen" type="checkbox" :class="checkboxClass" :aria-label="`${t(`settings.action_${row.key}`)} - ${t('settings.col_kitchen')}`"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2 class="text-[13px] font-semibold text-foreground">{{ t('settings.section_payments') }}</h2>
        <p class="mb-2 text-xs text-muted">{{ t('settings.section_payments_helper') }}</p>
        <ul class="divide-y divide-border rounded-md border border-border">
            <li v-for="(entry, index) in pos.payment_methods" :key="entry.method" class="flex items-center gap-2 px-2 py-1.5">
                <input v-model="entry.enabled" type="checkbox" :class="checkboxClass" :aria-label="entry.name">
                <span class="min-w-0 flex-1 text-[13px] font-medium" :class="entry.enabled ? 'text-foreground' : 'text-muted line-through'">{{ localized({ name: entry.name, name_bn: entry.name_bn }, 'name') }}</span>
                <button type="button" class="flex h-6 w-6 items-center justify-center rounded-md border border-border text-muted hover:text-foreground disabled:opacity-40" :disabled="index === 0" :aria-label="t('settings.move_up')" @click="moveMethod(index, -1)">
                    <AppIcon name="chevron-up" size="10" />
                </button>
                <button type="button" class="flex h-6 w-6 items-center justify-center rounded-md border border-border text-muted hover:text-foreground disabled:opacity-40" :disabled="index === pos.payment_methods.length - 1" :aria-label="t('settings.move_down')" @click="moveMethod(index, 1)">
                    <AppIcon name="chevron-down" size="10" />
                </button>
            </li>
        </ul>
        <p v-if="errors['pos.payment_methods']" class="mt-1 text-xs text-danger">{{ errors['pos.payment_methods'] }}</p>
    </section>

    <section class="space-y-3 border-t border-border pt-3">
        <div>
            <h2 class="text-[13px] font-semibold text-foreground">{{ t('settings.section_sounds') }}</h2>
            <p class="text-xs text-muted">{{ t('settings.sounds_helper') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:items-center">
            <SwitchField v-model="pos.sounds.enabled" :label="t('settings.sounds_enabled')" />
            <label class="flex items-center gap-2 text-[13px] text-foreground">
                <span class="shrink-0">{{ t('settings.sounds_volume') }}</span>
                <input v-model.number="pos.sounds.volume" type="range" min="0" max="100" step="5" :disabled="! pos.sounds.enabled" class="w-full accent-[var(--color-primary)] disabled:opacity-50">
                <span class="w-9 shrink-0 text-right tabular-nums text-muted">{{ pos.sounds.volume }}%</span>
            </label>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3" :class="pos.sounds.enabled ? '' : 'opacity-50'">
            <div v-for="group in SOUND_GROUPS" :key="group.key">
                <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ t(`settings.sounds_group_${group.key}`) }}</h3>
                <ul class="divide-y divide-border rounded-md border border-border">
                    <li v-for="event in group.events" :key="event" class="flex items-center gap-2 px-2 py-1.5">
                        <input v-model="pos.sounds.events[event]" type="checkbox" :class="checkboxClass" :disabled="! pos.sounds.enabled" :aria-label="t(`settings.sound_${event}`)">
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
        <FormField :label="t('settings.cash_suggestions')" :helper="t('settings.cash_suggestions_helper')" :error="errors['pos.cash_suggestions'] ?? errors['pos.cash_suggestions.0']">
            <template #default="{ id }">
                <input :id="id" v-model="cashText" type="text" placeholder="1000, 500, 200, 100" :class="[inputClass, 'max-w-xs tabular-nums']">
            </template>
        </FormField>
    </section>

    <section class="space-y-3 border-t border-border pt-3">
        <h2 class="text-[13px] font-semibold text-foreground">{{ t('settings.section_invoice') }}</h2>

        <div role="radiogroup" :aria-label="t('settings.invoice_layout')" class="grid grid-cols-3 gap-2">
            <label
                v-for="layout in layouts"
                :key="layout"
                class="flex cursor-pointer items-center gap-2 rounded-md border px-2 py-1.5 text-[13px]"
                :class="pos.invoice_layout === layout ? 'border-accent bg-accent/10' : 'border-border bg-background'"
            >
                <input v-model="pos.invoice_layout" type="radio" name="invoice_layout" :value="layout" class="accent-[var(--color-primary)]">
                {{ t(`settings.layout_${layout}`) }}
            </label>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_8rem]">
            <FormField :label="t('settings.invoice_pattern')" :helper="t('settings.invoice_pattern_helper')" :error="errors['pos.invoice_pattern']" required>
                <template #default="{ id }">
                    <div class="flex gap-1">
                        <input :id="id" v-model="pos.invoice_pattern" type="text" maxlength="60" :class="[inputClass, 'font-mono']">
                        <button type="button" class="h-7 shrink-0 rounded-md border border-border px-2 text-xs text-foreground hover:border-accent" @click="pos.invoice_pattern = defaultPattern">{{ t('settings.reset_pattern') }}</button>
                    </div>
                </template>
            </FormField>
            <FormField v-if="patternUsesSequence(pos)" :label="t('settings.invoice_digits')" :error="errors['pos.invoice_seq_digits']" required>
                <template #default="{ id }">
                    <input :id="id" v-model.number="pos.invoice_seq_digits" type="number" min="1" max="8" :class="[inputClass, 'text-right tabular-nums']">
                </template>
            </FormField>
        </div>
        <p class="text-xs text-muted">{{ t('settings.invoice_preview') }}: <span class="font-mono text-foreground">{{ invoicePreview }}</span></p>
    </section>
    </div>
</template>
