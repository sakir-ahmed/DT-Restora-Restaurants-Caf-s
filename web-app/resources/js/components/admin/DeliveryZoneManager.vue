<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { localized } from '@/lib/site';

defineProps({
    zones: { type: Array, required: true },
    branchName: { type: String, default: '' },
    deliveryEnabled: { type: Boolean, default: true },
});

const confirm = useConfirm();
const open = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    name_bn: '',
    fee: '0',
    fee_type: 'fixed',
    min_order: '0',
    eta_minutes: '',
    is_active: true,
});

const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';

function startCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function startEdit(zone) {
    editing.value = zone;
    form.clearErrors();
    form.name = zone.name;
    form.name_bn = zone.name_bn ?? '';
    form.fee = zone.fee;
    form.fee_type = zone.fee_type;
    form.min_order = zone.min_order;
    form.eta_minutes = zone.eta_minutes ?? '';
    form.is_active = zone.is_active;
    open.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            notify.success(t('settings.zone_saved'));
        },
    };

    if (editing.value === null) {
        form.post(route('admin.settings.delivery-zones.store'), options);

        return;
    }

    form.put(route('admin.settings.delivery-zones.update', editing.value.id), options);
}

async function remove(zone) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('settings.zone_delete_title'),
        message: zone.name,
        confirmText: t('common.delete'),
    });

    if (! result.confirmed) {
        return;
    }

    router.delete(route('admin.settings.delivery-zones.destroy', zone.id), {
        preserveScroll: true,
        onSuccess: (page) => notify.success(page.props.flash?.success ?? t('settings.zone_deleted')),
    });
}
</script>

<template>
    <div class="max-w-3xl rounded-lg border border-border bg-surface p-4">
        <div class="mb-3 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-[13px] font-semibold text-foreground">{{ t('settings.zones_title') }}</h2>
                <p class="text-xs text-muted">{{ t('settings.zones_helper', { branch: branchName }) }}</p>
            </div>
            <button type="button" class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90" @click="startCreate">
                <AppIcon name="plus" size="12" />
                {{ t('settings.zone_add') }}
            </button>
        </div>

        <p v-if="! deliveryEnabled" class="mb-3 rounded-md border border-border bg-background px-2.5 py-1.5 text-xs text-muted">{{ t('settings.zones_delivery_off') }}</p>
        <p v-else-if="! zones.some((zone) => zone.is_active)" class="mb-3 rounded-md border border-warning/50 bg-warning/10 px-2.5 py-1.5 text-xs text-foreground">{{ t('settings.zones_none_active') }}</p>

        <div class="overflow-x-auto rounded-md border border-border">
            <table class="w-full text-[13px]">
                <thead class="bg-background text-xs text-muted">
                    <tr>
                        <th class="px-2 py-1.5 text-left font-medium">{{ t('settings.zone_name') }}</th>
                        <th class="px-2 py-1.5 text-left font-medium">{{ t('settings.zone_fee_type') }}</th>
                        <th class="px-2 py-1.5 text-right font-medium">{{ t('settings.zone_fee') }}</th>
                        <th class="px-2 py-1.5 text-right font-medium">{{ t('settings.zone_min_order') }}</th>
                        <th class="px-2 py-1.5 text-right font-medium">{{ t('settings.zone_eta') }}</th>
                        <th class="w-20 px-2 py-1.5" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="zones.length === 0">
                        <td colspan="6" class="px-2 py-4 text-center text-muted">{{ t('settings.zones_empty') }}</td>
                    </tr>
                    <tr v-for="zone in zones" :key="zone.id" class="border-t border-border">
                        <td class="px-2 py-1.5">
                            <span class="font-medium" :class="zone.is_active ? 'text-foreground' : 'text-muted line-through'">{{ localized(zone, 'name') }}</span>
                        </td>
                        <td class="px-2 py-1.5 text-muted">{{ t(`settings.zone_type_${zone.fee_type}`) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums">{{ formatMoney(zone.fee, { decimals: 0 }) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-muted">{{ formatMoney(zone.min_order, { decimals: 0 }) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-muted">{{ zone.eta_minutes ?? '-' }}</td>
                        <td class="px-2 py-1.5">
                            <div class="flex justify-end gap-1">
                                <button type="button" class="flex h-6 w-6 items-center justify-center rounded-md border border-border text-muted hover:text-foreground" :aria-label="t('common.edit')" @click="startEdit(zone)">
                                    <AppIcon name="pen-to-square" size="11" />
                                </button>
                                <button type="button" class="flex h-6 w-6 items-center justify-center rounded-md border border-border text-muted hover:text-danger" :aria-label="t('common.delete')" @click="remove(zone)">
                                    <AppIcon name="trash" size="11" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="open" :title="editing ? t('settings.zone_edit') : t('settings.zone_add')" @close="open = false">
            <form id="delivery-zone-form" novalidate class="space-y-3" @submit.prevent="save">
                <FormField :label="t('settings.zone_name')" :error="form.errors.name" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.name" type="text" maxlength="120" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('settings.zone_name_bn')" :error="form.errors.name_bn">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.name_bn" type="text" maxlength="120" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('settings.zone_fee_type')" :helper="form.fee_type === 'fixed' ? t('settings.zone_type_fixed_helper') : t('settings.zone_type_flexible_helper')" :error="form.errors.fee_type" required>
                    <template #default="{ id }">
                        <select :id="id" v-model="form.fee_type" :class="inputClass">
                            <option value="fixed">{{ t('settings.zone_type_fixed') }}</option>
                            <option value="flexible">{{ t('settings.zone_type_flexible') }}</option>
                        </select>
                    </template>
                </FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField :label="form.fee_type === 'fixed' ? t('settings.zone_fee') : t('settings.zone_fee_from')" :error="form.errors.fee" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="form.fee" type="number" min="0" step="0.01" :class="[inputClass, 'text-right tabular-nums']">
                        </template>
                    </FormField>
                    <FormField :label="t('settings.zone_min_order')" :error="form.errors.min_order" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="form.min_order" type="number" min="0" step="0.01" :class="[inputClass, 'text-right tabular-nums']">
                        </template>
                    </FormField>
                </div>
                <FormField :label="t('settings.zone_eta')" :error="form.errors.eta_minutes">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.eta_minutes" type="number" min="1" max="600" step="1" :class="[inputClass, 'text-right tabular-nums']">
                    </template>
                </FormField>
                <SwitchField v-model="form.is_active" :label="t('settings.zone_active')" />
            </form>
            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border px-3 text-[13px] text-foreground hover:bg-background" @click="open = false">{{ t('common.cancel') }}</button>
                <button type="submit" form="delivery-zone-form" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-3 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" :disabled="form.processing">
                    <AppIcon v-if="form.processing" name="spinner" size="12" spin />
                    {{ t('common.save') }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>
