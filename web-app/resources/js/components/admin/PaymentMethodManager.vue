<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { localized } from '@/lib/site';

defineProps({
    methods: { type: Array, required: true },
});

const confirm = useConfirm();
const open = ref(false);
const editing = ref(null);

const form = useForm({
    code: '',
    name: '',
    name_bn: '',
    type: 'wallet',
    is_active: true,
});

const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';
const TYPES = ['wallet', 'card', 'other'];

function startCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function startEdit(method) {
    editing.value = method;
    form.clearErrors();
    form.code = method.code;
    form.name = method.name;
    form.name_bn = method.name_bn ?? '';
    form.type = method.type;
    form.is_active = method.is_active;
    open.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            notify.success(t('settings.method_saved'));
        },
    };

    if (editing.value === null) {
        form.post(route('admin.settings.payment-methods.store'), options);

        return;
    }

    // The code and the type of a built-in method are fixed, so they are not sent.
    form.transform((data) => (editing.value.is_system ? { name: data.name, name_bn: data.name_bn, is_active: data.is_active } : { name: data.name, name_bn: data.name_bn, type: data.type, is_active: data.is_active }))
        .put(route('admin.settings.payment-methods.update', editing.value.id), options);
}

async function remove(method) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('settings.method_delete_title'),
        message: method.name,
        confirmText: t('common.delete'),
    });

    if (! result.confirmed) {
        return;
    }

    router.delete(route('admin.settings.payment-methods.destroy', method.id), {
        preserveScroll: true,
        onSuccess: (page) => notify.success(page.props.flash?.success ?? t('settings.method_deleted')),
    });
}
</script>

<template>
    <div class="max-w-3xl rounded-lg border border-border bg-surface p-4">
        <div class="mb-3 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-[13px] font-semibold text-foreground">{{ t('settings.methods_title') }}</h2>
                <p class="text-xs text-muted">{{ t('settings.methods_helper') }}</p>
            </div>
            <button type="button" class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90" @click="startCreate">
                <AppIcon name="plus" size="12" />
                {{ t('settings.method_add') }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-md border border-border">
            <table class="w-full text-[13px]">
                <thead class="bg-background text-xs text-muted">
                    <tr>
                        <th class="px-2 py-1.5 text-left font-medium">{{ t('settings.method_name') }}</th>
                        <th class="px-2 py-1.5 text-left font-medium">{{ t('settings.method_code') }}</th>
                        <th class="px-2 py-1.5 text-left font-medium">{{ t('settings.method_type') }}</th>
                        <th class="px-2 py-1.5 text-right font-medium">{{ t('settings.method_payments') }}</th>
                        <th class="w-20 px-2 py-1.5" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="method in methods" :key="method.id" class="border-t border-border">
                        <td class="px-2 py-1.5">
                            <span class="font-medium" :class="method.is_active ? 'text-foreground' : 'text-muted line-through'">{{ localized(method, 'name') }}</span>
                            <span v-if="method.is_system" class="ml-1.5 rounded bg-background px-1 text-[10px] uppercase tracking-wide text-muted">{{ t('settings.method_builtin') }}</span>
                        </td>
                        <td class="px-2 py-1.5 font-mono text-xs text-muted">{{ method.code }}</td>
                        <td class="px-2 py-1.5 text-muted">{{ t(`settings.type_short_${method.type}`) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-muted">{{ method.payments_count }}</td>
                        <td class="px-2 py-1.5">
                            <div class="flex justify-end gap-1">
                                <button type="button" class="flex h-6 w-6 items-center justify-center rounded-md border border-border text-muted hover:text-foreground" :aria-label="t('common.edit')" @click="startEdit(method)">
                                    <AppIcon name="pen-to-square" size="11" />
                                </button>
                                <button v-if="! method.is_system" type="button" class="flex h-6 w-6 items-center justify-center rounded-md border border-border text-muted hover:text-danger" :aria-label="t('common.delete')" @click="remove(method)">
                                    <AppIcon name="trash" size="11" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="open" :title="editing ? t('settings.method_edit') : t('settings.method_add')" @close="open = false">
            <form id="payment-method-form" novalidate class="space-y-3" @submit.prevent="save">
                <FormField :label="t('settings.method_name')" :error="form.errors.name" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.name" type="text" maxlength="60" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('settings.method_name_bn')" :error="form.errors.name_bn">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.name_bn" type="text" maxlength="60" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('settings.method_code')" :helper="t('settings.method_code_helper')" :error="form.errors.code" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.code" type="text" maxlength="20" :disabled="editing !== null" :class="[inputClass, 'font-mono disabled:opacity-60']">
                    </template>
                </FormField>
                <FormField v-if="! editing?.is_system" :label="t('settings.method_type')" :error="form.errors.type" required>
                    <template #default="{ id }">
                        <select :id="id" v-model="form.type" :class="inputClass">
                            <option v-for="type in TYPES" :key="type" :value="type">{{ t(`settings.type_${type}`) }}</option>
                        </select>
                    </template>
                </FormField>
                <SwitchField v-if="editing?.code !== 'cash'" v-model="form.is_active" :label="t('settings.method_active')" />
                <p v-if="editing?.is_system" class="text-xs text-muted">{{ t('settings.method_system') }}</p>
            </form>
            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border px-3 text-[13px] text-foreground hover:bg-background" @click="open = false">{{ t('common.cancel') }}</button>
                <button type="submit" form="payment-method-form" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-3 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" :disabled="form.processing">
                    <AppIcon v-if="form.processing" name="spinner" size="12" spin />
                    {{ t('common.save') }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>
