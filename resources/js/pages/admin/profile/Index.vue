<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    profile: { type: Object, required: true },
});

const form = useForm({
    name: props.profile.name ?? '',
    email: props.profile.email ?? '',
    username: props.profile.username ?? '',
    mobile: props.profile.mobile ?? '',
    locale: props.profile.locale ?? 'en',
    avatar: null,
    remove_avatar: false,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const newPreview = ref(null);
const shownAvatar = computed(() => newPreview.value ?? (form.remove_avatar ? null : props.profile.avatar_url));
const initials = computed(() => {
    const words = String(form.name || '?').trim().split(/\s+/);

    return `${words[0]?.[0] ?? ''}${words.length > 1 ? words[words.length - 1][0] : ''}`.toUpperCase();
});

function revokePreview() {
    if (newPreview.value) {
        URL.revokeObjectURL(newPreview.value);
    }
}

function chooseAvatar(event) {
    const file = event.target.files?.[0] ?? null;

    revokePreview();
    form.avatar = file;
    form.remove_avatar = false;
    newPreview.value = file ? URL.createObjectURL(file) : null;
}

function removeAvatar() {
    revokePreview();
    form.avatar = null;
    form.remove_avatar = true;
    newPreview.value = null;
}

function save() {
    form.post(route('admin.profile.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            revokePreview();
            newPreview.value = null;
            form.avatar = null;
            form.remove_avatar = false;
        },
    });
}

function changePassword() {
    passwordForm.put(route('admin.profile.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => passwordForm.reset('password', 'password_confirmation'),
    });
}

onBeforeUnmount(revokePreview);

const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';
const submitClass = 'inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60';
</script>

<template>
    <div>
        <PageHeader :title="t('profile.title')" :description="t('profile.subtitle')" />

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 lg:items-start">
            <form novalidate class="rounded-lg border border-border bg-surface p-4" @submit.prevent="save">
                <h2 class="mb-3 text-[13px] font-semibold text-foreground">{{ t('profile.details') }}</h2>

                <div class="mb-3 flex items-center gap-3">
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-lg font-semibold text-primary">
                        <img v-if="shownAvatar" :src="shownAvatar" :alt="form.name" class="h-full w-full object-cover">
                        <span v-else>{{ initials }}</span>
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="inline-flex h-7 cursor-pointer items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:border-accent">
                                <AppIcon name="plus" size="11" />
                                {{ t('profile.photo_choose') }}
                                <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="chooseAvatar">
                            </label>
                            <button v-if="shownAvatar" type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px] text-muted hover:text-danger" @click="removeAvatar">{{ t('profile.photo_remove') }}</button>
                        </div>
                        <p class="mt-1 text-xs text-muted">{{ t('profile.photo_hint') }}</p>
                        <p v-if="form.errors.avatar" class="mt-1 text-xs text-danger">{{ form.errors.avatar }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <FormField :label="t('profile.name')" :error="form.errors.name" required class="sm:col-span-2">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.name" type="text" maxlength="150" autocomplete="name" :class="inputClass">
                        </template>
                    </FormField>
                    <FormField :label="t('profile.email')" :error="form.errors.email">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.email" type="email" maxlength="150" autocomplete="email" :class="inputClass">
                        </template>
                    </FormField>
                    <FormField :label="t('profile.username')" :error="form.errors.username">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.username" type="text" maxlength="60" autocomplete="username" :class="inputClass">
                        </template>
                    </FormField>
                    <FormField :label="t('profile.mobile')" :error="form.errors.mobile">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.mobile" type="tel" maxlength="20" autocomplete="tel" :class="inputClass">
                        </template>
                    </FormField>
                    <FormField :label="t('profile.language')" :error="form.errors.locale">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.locale" :class="inputClass">
                                <option value="en">English</option>
                                <option value="bn">বাংলা</option>
                            </select>
                        </template>
                    </FormField>
                </div>

                <p class="mt-3 text-xs text-muted">
                    {{ t('profile.role') }}: <span class="text-foreground">{{ profile.roles.join(', ') }}</span>
                    <span class="mx-1">|</span>
                    {{ t('profile.branch') }}: <span class="text-foreground">{{ profile.branch ?? t('profile.all_branches') }}</span>
                </p>

                <div class="mt-3 flex justify-end">
                    <button type="submit" :class="submitClass" :disabled="form.processing">
                        <AppIcon v-if="form.processing" name="spinner" size="12" spin />
                        {{ t('profile.save') }}
                    </button>
                </div>
            </form>

            <form novalidate class="rounded-lg border border-border bg-surface p-4" @submit.prevent="changePassword">
                <h2 class="mb-3 text-[13px] font-semibold text-foreground">{{ t('profile.password_section') }}</h2>

                <div class="space-y-3">
                    <FormField :label="t('profile.current_password')" :error="passwordForm.errors.current_password" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="passwordForm.current_password" type="password" autocomplete="current-password" :class="inputClass">
                        </template>
                    </FormField>
                    <FormField :label="t('profile.new_password')" :helper="t('profile.password_helper')" :error="passwordForm.errors.password" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="passwordForm.password" type="password" autocomplete="new-password" :class="inputClass">
                        </template>
                    </FormField>
                    <FormField :label="t('profile.confirm_password')" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" :class="inputClass">
                        </template>
                    </FormField>
                </div>

                <div class="mt-3 flex justify-end">
                    <button type="submit" :class="submitClass" :disabled="passwordForm.processing">
                        <AppIcon v-if="passwordForm.processing" name="spinner" size="12" spin />
                        {{ t('profile.change_password') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
