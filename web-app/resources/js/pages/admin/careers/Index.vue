<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import DateText from '@/components/ui-kit/DateText.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

const props = defineProps({
    openings: { type: Array, required: true },
    applications: { type: Array, required: true },
    branches: { type: Array, required: true },
    types: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const confirm = useConfirm();

const tab = ref('openings');
const open = ref(false);
const editingId = ref(null);
const expanded = ref(null);

const blank = {
    branch_id: null, title: '', title_bn: '', department: '', employment_type: 'full_time',
    summary: '', summary_bn: '', description: '', description_bn: '',
    requirements: '', requirements_bn: '', is_active: true, closes_on: '',
};

const form = useForm({ ...blank });

function create() {
    editingId.value = null;
    form.defaults({ ...blank }).reset();
    form.clearErrors();
    open.value = true;
}

function edit(opening) {
    editingId.value = opening.id;
    form.defaults({
        branch_id: opening.branch_id,
        title: opening.title,
        title_bn: opening.title_bn ?? '',
        department: opening.department ?? '',
        employment_type: opening.employment_type,
        summary: opening.summary,
        summary_bn: opening.summary_bn ?? '',
        description: opening.description,
        description_bn: opening.description_bn ?? '',
        requirements: opening.requirements ?? '',
        requirements_bn: opening.requirements_bn ?? '',
        is_active: opening.is_active,
        closes_on: opening.closes_on ?? '',
    }).reset();
    form.clearErrors();
    open.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            notify.success(editingId.value ? t('careers.saved') : t('careers.created'));
        },
        onError: (errors) => notify.error(Object.values(errors)[0]),
    };

    if (editingId.value) {
        form.put(`/admin/careers/${editingId.value}`, options);
    } else {
        form.post('/admin/careers', options);
    }
}

async function removeOpening(opening) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('careers.delete_title'),
        message: t('careers.delete_message'),
        confirmText: t('careers.delete'),
    });

    if (result.confirmed) {
        router.delete(`/admin/careers/${opening.id}`, { preserveScroll: true, onSuccess: () => notify.success(t('careers.deleted')) });
    }
}

function setStatus(application, status) {
    router.put(`/admin/careers/applications/${application.id}`, { status }, {
        preserveScroll: true,
        onSuccess: () => notify.success(t('careers.saved')),
        onError: (errors) => notify.error(Object.values(errors)[0]),
    });
}

async function removeApplication(application) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('careers.delete_application_title'),
        message: t('careers.delete_application_message'),
        confirmText: t('careers.delete'),
    });

    if (result.confirmed) {
        router.delete(`/admin/careers/applications/${application.id}`, { preserveScroll: true, onSuccess: () => notify.success(t('careers.deleted')) });
    }
}

const input = (key) => ['h-8 w-full rounded-md border bg-surface px-2 text-[13px] outline-none focus:ring-2 focus:ring-accent/40', form.errors[key] ? 'border-danger' : 'border-border focus:border-accent'];
const area = (key) => ['w-full rounded-md border bg-surface px-2 py-1.5 text-[13px] outline-none focus:ring-2 focus:ring-accent/40', form.errors[key] ? 'border-danger' : 'border-border focus:border-accent'];
</script>

<template>
    <div>
        <PageHeader :title="t('careers.title')" :description="t('careers.subtitle')">
            <template #actions>
                <button v-if="tab === 'openings'" type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-accent px-3 text-[13px] font-medium text-accent-foreground" @click="create">
                    <AppIcon name="plus" size="12" />
                    {{ t('careers.new_opening') }}
                </button>
            </template>
        </PageHeader>

        <div class="mb-3 flex gap-1 border-b border-border" role="tablist">
            <button
                v-for="entry in [{ key: 'openings', label: t('careers.tab_openings'), count: openings.length }, { key: 'applications', label: t('careers.tab_applications'), count: applications.length }]"
                :key="entry.key"
                type="button"
                role="tab"
                :aria-selected="tab === entry.key"
                class="-mb-px border-b-2 px-3 py-2 text-[13px] font-medium"
                :class="tab === entry.key ? 'border-accent text-foreground' : 'border-transparent text-muted hover:text-foreground'"
                @click="tab = entry.key"
            >
                {{ entry.label }} <span class="ml-1 text-xs tabular-nums text-muted">{{ entry.count }}</span>
            </button>
        </div>

        <!-- Openings -->
        <template v-if="tab === 'openings'">
            <EmptyState v-if="openings.length === 0" icon="briefcase" :title="t('careers.no_openings')" />

            <div v-else class="overflow-x-auto rounded-xl border border-border bg-surface">
                <table class="w-full min-w-[40rem] text-left text-[13px]">
                    <thead>
                        <tr class="border-b border-border text-xs uppercase tracking-wider text-muted">
                            <th scope="col" class="px-3 py-2 font-semibold">{{ t('careers.job_title') }}</th>
                            <th scope="col" class="px-3 py-2 font-semibold">{{ t('careers.type') }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-semibold">{{ t('careers.applications') }}</th>
                            <th scope="col" class="px-3 py-2 font-semibold">{{ t('common.status') }}</th>
                            <th scope="col" class="w-24 px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="opening in openings" :key="opening.id" class="border-b border-border/60 last:border-0">
                            <td class="px-3 py-2"><span class="font-medium">{{ opening.title }}</span><span v-if="opening.department" class="block text-xs text-muted">{{ opening.department }}</span></td>
                            <td class="px-3 py-2">{{ t(`site.job_type_${opening.employment_type}`) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ opening.applications_count }}</td>
                            <td class="px-3 py-2"><StatusBadge :status="opening.is_active ? 'active' : 'inactive'" /></td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-1">
                                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-muted hover:text-foreground" :aria-label="t('careers.edit')" @click="edit(opening)"><AppIcon name="pen-to-square" size="11" /></button>
                                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-danger hover:bg-danger/10" :aria-label="t('careers.delete')" @click="removeOpening(opening)"><AppIcon name="trash" size="11" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- Applications -->
        <template v-else>
            <EmptyState v-if="applications.length === 0" icon="users" :title="t('careers.no_applications')" />

            <div v-else class="overflow-x-auto rounded-xl border border-border bg-surface">
                <table class="w-full min-w-[48rem] text-left text-[13px]">
                    <thead>
                        <tr class="border-b border-border text-xs uppercase tracking-wider text-muted">
                            <th scope="col" class="px-3 py-2 font-semibold">{{ t('careers.applicant') }}</th>
                            <th scope="col" class="px-3 py-2 font-semibold">{{ t('careers.job') }}</th>
                            <th scope="col" class="px-3 py-2 font-semibold">{{ t('careers.received') }}</th>
                            <th scope="col" class="px-3 py-2 font-semibold">{{ t('careers.status') }}</th>
                            <th scope="col" class="w-28 px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="application in applications" :key="application.id">
                            <tr class="border-b border-border/60">
                                <td class="px-3 py-2">
                                    <button type="button" class="text-left font-medium hover:underline" @click="expanded = expanded === application.id ? null : application.id">{{ application.name }}</button>
                                    <span class="block text-xs tabular-nums text-muted">{{ application.mobile }}</span>
                                </td>
                                <td class="px-3 py-2">{{ application.job ?? t('careers.general_application') }}</td>
                                <td class="px-3 py-2 text-muted"><DateText :value="application.created_at" /></td>
                                <td class="px-3 py-2">
                                    <select :value="application.status" class="h-7 rounded-md border border-border bg-surface px-1.5 text-[13px]" :aria-label="t('careers.status')" @change="setStatus(application, $event.target.value)">
                                        <option v-for="status in statuses" :key="status" :value="status">{{ t(`careers.status_${status}`) }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-1">
                                        <a v-if="application.has_cv" :href="`/admin/careers/applications/${application.id}/cv`" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-muted hover:text-foreground" :aria-label="t('careers.download_cv')" :title="t('careers.download_cv')"><AppIcon name="download" size="11" /></a>
                                        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-danger hover:bg-danger/10" :aria-label="t('careers.delete')" @click="removeApplication(application)"><AppIcon name="trash" size="11" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expanded === application.id" class="border-b border-border/60 bg-background">
                                <td colspan="5" class="px-3 py-2.5 text-[13px]">
                                    <p v-if="application.email" class="text-muted">{{ application.email }}</p>
                                    <p class="mt-1 whitespace-pre-line">{{ application.message || '-' }}</p>
                                    <p v-if="! application.has_cv" class="mt-1 text-xs text-muted">{{ t('careers.no_cv') }}</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        <SlideOver :open="open" :title="editingId ? t('careers.edit_opening') : t('careers.new_opening')" wide @close="open = false">
            <form novalidate class="space-y-3" @submit.prevent="save">
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField :label="t('careers.job_title')" required for="career-title" :error="form.errors.title">
                        <input id="career-title" v-model="form.title" type="text" maxlength="150" :class="input('title')">
                    </FormField>
                    <FormField :label="t('careers.job_title_bn')" for="career-title-bn" :error="form.errors.title_bn">
                        <input id="career-title-bn" v-model="form.title_bn" type="text" maxlength="150" :class="input('title_bn')">
                    </FormField>
                    <FormField :label="t('careers.department')" for="career-department" :error="form.errors.department">
                        <input id="career-department" v-model="form.department" type="text" maxlength="100" :class="input('department')">
                    </FormField>
                    <FormField :label="t('careers.type')" required for="career-type" :error="form.errors.employment_type">
                        <select id="career-type" v-model="form.employment_type" :class="input('employment_type')">
                            <option v-for="type in types" :key="type" :value="type">{{ t(`site.job_type_${type}`) }}</option>
                        </select>
                    </FormField>
                    <FormField :label="t('careers.branch')" for="career-branch" :error="form.errors.branch_id">
                        <select id="career-branch" v-model="form.branch_id" :class="input('branch_id')">
                            <option :value="null">{{ t('careers.any_branch') }}</option>
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                        </select>
                    </FormField>
                    <FormField :label="t('careers.closes_on')" for="career-closes" :error="form.errors.closes_on">
                        <input id="career-closes" v-model="form.closes_on" type="date" :class="input('closes_on')">
                    </FormField>
                </div>

                <FormField :label="t('careers.summary')" required for="career-summary" :error="form.errors.summary">
                    <input id="career-summary" v-model="form.summary" type="text" maxlength="300" :class="input('summary')">
                </FormField>
                <FormField :label="t('careers.summary_bn')" for="career-summary-bn" :error="form.errors.summary_bn">
                    <input id="career-summary-bn" v-model="form.summary_bn" type="text" maxlength="300" :class="input('summary_bn')">
                </FormField>
                <FormField :label="t('careers.description')" required for="career-description" :error="form.errors.description">
                    <textarea id="career-description" v-model="form.description" rows="5" :class="area('description')" />
                </FormField>
                <FormField :label="t('careers.description_bn')" for="career-description-bn" :error="form.errors.description_bn">
                    <textarea id="career-description-bn" v-model="form.description_bn" rows="5" :class="area('description_bn')" />
                </FormField>
                <FormField :label="t('careers.requirements')" for="career-requirements" :error="form.errors.requirements">
                    <textarea id="career-requirements" v-model="form.requirements" rows="4" :class="area('requirements')" />
                </FormField>
                <FormField :label="t('careers.requirements_bn')" for="career-requirements-bn" :error="form.errors.requirements_bn">
                    <textarea id="career-requirements-bn" v-model="form.requirements_bn" rows="4" :class="area('requirements_bn')" />
                </FormField>

                <SwitchField v-model="form.is_active" :label="t('careers.active')" />

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="h-8 rounded-md border border-border px-3 text-[13px]" @click="open = false">{{ t('careers.cancel') }}</button>
                    <button type="submit" :disabled="form.processing" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-accent px-3 text-[13px] font-medium text-accent-foreground disabled:opacity-60">
                        <AppIcon v-if="form.processing" name="spinner" size="12" spin />
                        {{ form.processing ? t('careers.saving') : t('careers.save') }}
                    </button>
                </div>
            </form>
        </SlideOver>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
