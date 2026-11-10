<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { formatQuantity, inReference, referenceUnit, relationText } from '@/lib/units';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

/**
 * Units of measure and how they relate. Standard units are fixed; owners add
 * their own (box, large box, small packet ...) by saying how many of a
 * smaller unit one of them holds.
 */
const props = defineProps({
    units: { type: Array, required: true },
});

const confirm = useConfirm();
const open = ref(false);
const editing = ref(null);
const TYPES = ['weight', 'volume', 'count'];

const form = useForm({
    name: '',
    name_bn: '',
    symbol: '',
    type: 'count',
    qty: '1',
    reference_unit_id: '',
});

const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';

const grouped = computed(() => TYPES.map((type) => ({
    type,
    units: props.units.filter((unit) => unit.type === type),
})).filter((group) => group.units.length > 0));

const locked = computed(() => editing.value?.in_use === true);
const referenceOptions = computed(() => props.units
    .filter((unit) => unit.type === form.type && unit.id !== editing.value?.id)
    .sort((a, b) => Number(a.factor) - Number(b.factor)));
const selectedReference = computed(() => props.units.find((unit) => unit.id === Number(form.reference_unit_id)) ?? null);

const sentence = computed(() => {
    if (! selectedReference.value || ! (Number(form.qty) > 0)) {
        return '';
    }

    const reference = referenceUnit(props.units, form.type);
    const total = Number(form.qty) * Number(selectedReference.value.factor) / Number(reference?.factor || 1);
    const name = form.symbol || form.name || t('units.this_unit');
    const base = `1 ${name} = ${formatQuantity(form.qty)} ${selectedReference.value.symbol}`;

    return selectedReference.value.id === reference?.id ? base : `${base} = ${formatQuantity(total)} ${reference?.symbol}`;
});

function usageText(unit) {
    const total = Object.values(unit.usage).reduce((sum, count) => sum + count, 0);

    return total === 0 ? t('units.unused') : t('units.used_in', { count: total });
}

function defaultReference(type) {
    return referenceUnit(props.units, type)?.id ?? '';
}

function startCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.type = 'count';
    form.reference_unit_id = defaultReference('count');
    open.value = true;
}

function startEdit(unit) {
    editing.value = unit;
    form.clearErrors();
    form.name = unit.name;
    form.name_bn = unit.name_bn ?? '';
    form.symbol = unit.symbol;
    form.type = unit.type;
    const reference = referenceUnit(props.units, unit.type);

    form.reference_unit_id = reference?.id ?? '';
    form.qty = String(inReference(unit, props.units));
    open.value = true;
}

function changeType() {
    form.reference_unit_id = defaultReference(form.type);
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            notify.success(t('units.saved'));
        },
    };

    if (editing.value === null) {
        form.post(route('admin.settings.units.store'), options);

        return;
    }

    form.put(route('admin.settings.units.update', editing.value.id), options);
}

async function remove(unit) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('units.delete_title'),
        message: unit.name,
        confirmText: t('common.delete'),
    });

    if (! result.confirmed) {
        return;
    }

    router.delete(route('admin.settings.units.destroy', unit.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = page.props.flash ?? {};

            flash.error ? notify.error(String(flash.error)) : notify.success(String(flash.success ?? t('units.deleted')));
        },
    });
}

</script>

<template>
    <div class="max-w-4xl space-y-4">
        <div class="rounded-lg border border-border bg-surface p-4">
            <h2 class="mb-1.5 text-[13px] font-semibold text-foreground">{{ t('units.notes_title') }}</h2>
            <ul class="list-disc space-y-1 pl-4 text-xs text-muted">
                <li v-for="note in ['note_base', 'note_buy', 'note_box', 'note_fixed', 'note_locked']" :key="note">{{ t('units.' + note) }}</li>
            </ul>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-[13px] font-semibold text-foreground">{{ t('units.title') }}</h2>
            <div class="flex gap-2">
                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90" @click="startCreate">
                    <AppIcon name="plus" size="12" />
                    {{ t('units.add') }}
                </button>
            </div>
        </div>

        <section v-for="group in grouped" :key="group.type" class="rounded-lg border border-border bg-surface">
            <header class="border-b border-border px-3 py-2">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ t('units.type_' + group.type) }}</h3>
                <p class="text-xs text-muted">{{ t('units.type_' + group.type + '_helper') }}</p>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="px-3 py-1.5">{{ t('units.name') }}</th>
                            <th class="px-3 py-1.5">{{ t('units.symbol') }}</th>
                            <th class="px-3 py-1.5">{{ t('units.relation') }}</th>
                            <th class="px-3 py-1.5">{{ t('units.usage') }}</th>
                            <th class="px-3 py-1.5 text-right"><span class="sr-only">{{ t('common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="unit in group.units" :key="unit.id" class="border-t border-border align-middle">
                            <td class="px-3 py-1.5">
                                <span class="font-medium text-foreground">{{ unit.name }}</span>
                                <span v-if="unit.name_bn" class="ml-1 text-xs text-muted">{{ unit.name_bn }}</span>
                            </td>
                            <td class="px-3 py-1.5 font-mono text-xs">{{ unit.symbol }}</td>
                            <td class="px-3 py-1.5 tabular-nums">{{ relationText(unit, units) ?? t('units.smallest') }}</td>
                            <td class="px-3 py-1.5 text-xs text-muted">{{ usageText(unit) }}</td>
                            <td class="px-3 py-1.5 text-right">
                                <span v-if="unit.is_system" class="inline-flex items-center gap-1 rounded-full bg-muted/10 px-2 py-0.5 text-xs text-muted" :title="t('units.fixed_note')">
                                    <AppIcon name="lock" size="10" />
                                    {{ t('units.fixed') }}
                                </span>
                                <span v-else class="inline-flex items-center gap-1">
                                    <button type="button" class="inline-flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="startEdit(unit)">
                                        <AppIcon name="pen-to-square" size="10" />
                                        {{ t('common.edit') }}
                                    </button>
                                    <button type="button" :disabled="unit.in_use" class="inline-flex h-6 w-6 items-center justify-center rounded text-muted hover:text-danger disabled:opacity-40 disabled:hover:text-muted" :title="unit.in_use ? t('units.in_use', { name: unit.name }) : t('common.delete')" :aria-label="t('common.delete')" @click="remove(unit)">
                                        <AppIcon name="trash" size="12" />
                                    </button>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <SlideOver :open="open" :title="editing ? t('units.edit') : t('units.add')" @close="open = false">
            <form id="unit-form" novalidate class="space-y-3" @submit.prevent="save">
                <FormField :label="t('units.name')" :error="form.errors.name" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.name" type="text" maxlength="60" placeholder="Large box" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('units.name_bn')" :error="form.errors.name_bn">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.name_bn" type="text" maxlength="60" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('units.symbol')" :helper="t('units.symbol_helper')" :error="form.errors.symbol" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.symbol" type="text" maxlength="20" placeholder="lbox" :class="inputClass">
                    </template>
                </FormField>

                <template v-if="! locked">
                    <FormField v-if="! editing" :label="t('units.type')" :helper="t('units.type_helper')" :error="form.errors.type" required>
                        <template #default="{ id }">
                            <select :id="id" v-model="form.type" :class="inputClass" @change="changeType">
                                <option v-for="type in TYPES" :key="type" :value="type">{{ t('units.type_' + type) }}</option>
                            </select>
                        </template>
                    </FormField>

                    <div class="rounded-md border border-border bg-background p-3">
                        <p class="mb-2 text-xs font-medium text-foreground">{{ t('units.size_title') }}</p>
                        <div class="grid grid-cols-[auto_1fr_1fr] items-start gap-2">
                            <span class="flex h-7 items-center text-[13px] tabular-nums">1 {{ form.symbol || t('units.this_unit') }} =</span>
                            <FormField :error="form.errors.qty">
                                <template #default="{ id }">
                                    <input :id="id" v-model="form.qty" type="text" inputmode="decimal" :aria-label="t('units.quantity')" :class="[inputClass, 'text-right tabular-nums']">
                                </template>
                            </FormField>
                            <FormField :error="form.errors.reference_unit_id">
                                <template #default="{ id }">
                                    <select :id="id" v-model="form.reference_unit_id" :aria-label="t('units.reference')" :class="inputClass">
                                        <option v-for="unit in referenceOptions" :key="unit.id" :value="unit.id">{{ unit.symbol }} ({{ unit.name }})</option>
                                    </select>
                                </template>
                            </FormField>
                        </div>
                        <p v-if="sentence" class="mt-2 text-xs text-muted">{{ t('units.preview') }} <span class="font-medium text-foreground tabular-nums">{{ sentence }}</span></p>
                    </div>
                </template>
                <p v-else class="rounded-md border border-warning/50 bg-warning/10 px-2.5 py-1.5 text-xs text-foreground">{{ t('units.locked_note') }}</p>
            </form>
            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border px-3 text-[13px] text-foreground hover:bg-background" @click="open = false">{{ t('common.cancel') }}</button>
                <button type="submit" form="unit-form" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-3 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" :disabled="form.processing">
                    <AppIcon v-if="form.processing" name="spinner" size="12" spin />
                    {{ t('common.save') }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>
