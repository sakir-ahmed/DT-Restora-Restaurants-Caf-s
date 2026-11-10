<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import SuccessDialog from './SuccessDialog.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    openingId: { type: Number, default: null },
    title: { type: String, default: null },
});

const form = useForm({
    job_opening_id: props.openingId,
    name: '',
    mobile: '',
    email: '',
    message: '',
    cv: null,
    website: '',
    turnstile_token: null,
});

const fileInput = ref(null);
const localErrors = ref({});
const done = ref(false);

const FIELD_ORDER = ['name', 'mobile', 'email', 'cv', 'message'];

const errors = computed(() => ({ ...form.errors, ...localErrors.value }));
const messages = computed(() => [...new Set(Object.values(errors.value).filter(Boolean))]);

function onFile(event) {
    form.cv = event.target.files?.[0] ?? null;
}

function validate() {
    const found = {};

    if (! form.name.trim()) {
        found.name = t('site.err_name');
    }

    if (! /^(\+?88)?01[3-9]\d{8}$/.test(form.mobile.trim())) {
        found.mobile = t('site.err_mobile');
    }

    if (form.email.trim() && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
        found.email = t('site.err_email');
    }

    if (form.cv) {
        if (! /\.(pdf|docx?)$/i.test(form.cv.name)) {
            found.cv = t('site.err_cv_type');
        } else if (form.cv.size > 2 * 1024 * 1024) {
            found.cv = t('site.err_cv_size');
        }
    }

    return found;
}

async function focusFirst(keys) {
    await nextTick();

    const first = FIELD_ORDER.find((key) => keys.includes(key)) ?? keys[0];
    const field = document.getElementById(`apply-${first}`);

    field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    field?.focus({ preventScroll: true });
}

function submit() {
    form.clearErrors();
    localErrors.value = validate();

    if (Object.keys(localErrors.value).length > 0) {
        focusFirst(Object.keys(localErrors.value));

        return;
    }

    form.post('/careers/apply', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'mobile', 'email', 'message', 'cv', 'website');

            if (fileInput.value) {
                fileInput.value.value = '';
            }

            done.value = true;
        },
        onError: (serverErrors) => focusFirst(Object.keys(serverErrors)),
    });
}

const inputClass = (key) => ['w-full rounded-lg border bg-surface px-3 text-base outline-none focus:ring-2 focus:ring-accent/40', errors.value[key] ? 'border-danger' : 'border-border focus:border-accent'];
</script>

<template>
    <form novalidate class="space-y-4 rounded-xl border border-border bg-surface p-5" @submit.prevent="submit">
        <h2 class="text-xl font-bold">{{ title ?? t('site.apply_title') }}</h2>

        <div v-if="messages.length" role="alert" class="rounded-lg border border-danger/40 bg-danger/10 p-3">
            <p class="flex items-center gap-2 text-sm font-semibold text-danger">
                <AppIcon name="triangle-exclamation" size="13" />
                {{ t('site.apply_fix') }}
            </p>
            <ul class="mt-1.5 list-disc space-y-0.5 pl-8 text-sm">
                <li v-for="message in messages" :key="message">{{ message }}</li>
            </ul>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <FormField :label="t('site.apply_name')" required for="apply-name" :error="errors.name">
                <input id="apply-name" v-model="form.name" type="text" autocomplete="name" maxlength="150" class="h-11" :class="inputClass('name')" :aria-invalid="!! errors.name">
            </FormField>

            <FormField :label="t('site.apply_mobile')" required for="apply-mobile" :error="errors.mobile">
                <input id="apply-mobile" v-model="form.mobile" type="tel" inputmode="numeric" autocomplete="tel" placeholder="01XXXXXXXXX" maxlength="14" class="h-11 tabular-nums" :class="inputClass('mobile')" :aria-invalid="!! errors.mobile">
            </FormField>
        </div>

        <FormField :label="t('site.apply_email')" for="apply-email" :error="errors.email">
            <input id="apply-email" v-model="form.email" type="email" autocomplete="email" maxlength="150" class="h-11" :class="inputClass('email')" :aria-invalid="!! errors.email">
        </FormField>

        <FormField :label="t('site.apply_message')" for="apply-message" :error="errors.message">
            <textarea id="apply-message" v-model="form.message" rows="4" maxlength="2000" class="py-2" :class="inputClass('message')" />
        </FormField>

        <FormField :label="t('site.apply_cv')" for="apply-cv" :helper="t('site.apply_cv_hint')" :error="errors.cv">
            <input id="apply-cv" ref="fileInput" type="file" accept=".pdf,.doc,.docx,application/pdf" class="block w-full text-sm file:mr-3 file:h-10 file:rounded-lg file:border file:border-border file:bg-background file:px-3 file:text-sm" :aria-invalid="!! errors.cv" @change="onFile">
        </FormField>

        <!-- Hidden trap for bots; people never see or fill it. -->
        <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
            <label>Website <input v-model="form.website" type="text" tabindex="-1" autocomplete="off"></label>
        </div>

        <button type="submit" :disabled="form.processing" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-accent px-5 text-base font-semibold text-accent-foreground hover:opacity-90 disabled:opacity-60 sm:w-auto">
            <AppIcon v-if="form.processing" name="spinner" size="14" spin />
            <AppIcon v-else name="paper-plane" size="14" />
            {{ form.processing ? t('site.apply_sending') : t('site.apply_submit') }}
        </button>

        <SuccessDialog :open="done" :title="t('site.apply_success_title')" :text="t('site.apply_success_text')" :close-label="t('site.apply_close')" @close="done = false" />
    </form>
</template>
