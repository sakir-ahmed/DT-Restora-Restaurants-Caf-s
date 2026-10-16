<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

defineProps({
    letters: { type: Array, required: true },
});

const confirm = useConfirm();
const open = ref(false);
const editing = ref(null);

const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';

function today() {
    const date = new Date();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

const form = useForm({
    title: '',
    recipient: '',
    subject: '',
    body: '',
    letter_date: today(),
});

const columns = computed(() => [
    { key: 'letter_date', label: t('letterhead.date'), align: 'left' },
    { key: 'title', label: t('letterhead.letter_title'), align: 'left', truncate: true },
    { key: 'recipient', label: t('letterhead.recipient'), align: 'left', truncate: true },
    { key: 'subject', label: t('letterhead.subject'), align: 'left', truncate: true },
    { key: 'actions', label: '', align: 'right' },
]);

function startCreate() {
    editing.value = null;
    form.clearErrors();
    form.defaults({ title: '', recipient: '', subject: '', body: '', letter_date: today() });
    form.reset();
    open.value = true;
}

function startEdit(letter) {
    editing.value = letter;
    form.clearErrors();
    form.defaults({
        title: letter.title,
        recipient: letter.recipient ?? '',
        subject: letter.subject ?? '',
        body: letter.body,
        letter_date: letter.letter_date,
    });
    form.reset();
    open.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            notify.success(t('letterhead.saved'));
        },
    };

    if (editing.value) {
        form.put(route('admin.letters.update', editing.value.id), options);
        return;
    }

    form.post(route('admin.letters.store'), options);
}

async function remove(letter) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('letterhead.delete_title'),
        message: t('letterhead.delete_message', { title: letter.title }),
        confirmText: t('common.delete'),
    });

    if (! result.confirmed) {
        return;
    }

    router.delete(route('admin.letters.destroy', letter.id), {
        preserveScroll: true,
        onSuccess: () => notify.success(t('letterhead.deleted')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('letterhead.title')" :description="t('letterhead.subtitle')">
            <template #actions>
                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90" @click="startCreate">
                    <AppIcon name="plus" />
                    {{ t('letterhead.new') }}
                </button>
            </template>
        </PageHeader>

        <DataTable :columns="columns" :rows="letters" row-key="id" :empty-title="t('letterhead.empty')" :empty-description="t('letterhead.empty_hint')">
            <template #cell(actions)="{ row }">
                <div class="flex justify-end gap-1">
                    <a :href="row.print_url" target="_blank" rel="noopener" class="inline-flex h-7 items-center gap-1 rounded-md border border-border bg-surface px-2 text-[13px] text-foreground hover:bg-background">
                        <AppIcon name="print" />
                        {{ t('letterhead.print') }}
                    </a>
                    <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-surface text-foreground hover:bg-background" :aria-label="t('common.edit')" @click="startEdit(row)">
                        <AppIcon name="pen-to-square" />
                    </button>
                    <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-surface text-foreground hover:bg-background" :aria-label="t('common.delete')" @click="remove(row)">
                        <AppIcon name="trash" />
                    </button>
                </div>
            </template>
        </DataTable>

        <SlideOver :open="open" :title="editing ? t('letterhead.edit') : t('letterhead.new')" @close="open = false">
            <form class="space-y-3" @submit.prevent="save">
                <FormField :label="t('letterhead.letter_title')" required :error="form.errors.title">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.title" type="text" maxlength="160" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('letterhead.date')" required :error="form.errors.letter_date">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.letter_date" type="date" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('letterhead.recipient')" :error="form.errors.recipient">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.recipient" type="text" maxlength="160" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('letterhead.subject')" :error="form.errors.subject">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.subject" type="text" maxlength="200" :class="inputClass">
                    </template>
                </FormField>
                <FormField :label="t('letterhead.body')" required :error="form.errors.body">
                    <template #default="{ id }">
                        <textarea :id="id" v-model="form.body" rows="8" maxlength="10000" class="w-full rounded-md border border-border bg-surface px-2 py-1.5 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40" />
                    </template>
                </FormField>
            </form>
            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px]" @click="open = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="form.processing" class="inline-flex h-7 items-center rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground disabled:opacity-60" @click="save">
                    {{ t('common.save') }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
