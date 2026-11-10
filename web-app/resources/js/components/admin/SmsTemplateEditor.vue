<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { MAX_PARTS, analyzeSms, fillTemplate } from '@/lib/smsText';

/**
 * Edit the wording of every automatic SMS, per language. Variables are fixed
 * per template and inserted as {name}; a live count shows characters, words
 * and how many SMS parts the message will cost.
 */
const props = defineProps({
    template: { type: Object, required: true },
});

const LOCALES = ['en', 'bn'];
const MAX_CHARS = 480;
const lang = ref('en');
const area = ref(null);

const form = useForm({
    en: props.template.custom.en ?? props.template.defaults.en,
    bn: props.template.custom.bn ?? props.template.defaults.bn,
});

const samples = computed(() => Object.fromEntries(props.template.variables.map((variable) => [variable.name, variable.sample])));
const preview = computed(() => fillTemplate(form[lang.value], samples.value));
const stats = computed(() => analyzeSms(preview.value));
const isCustom = computed(() => LOCALES.some((locale) => props.template.custom[locale] !== null && props.template.custom[locale] !== undefined));
const isChanged = computed(() => LOCALES.some((locale) => form[locale] !== (props.template.custom[locale] ?? props.template.defaults[locale])));

function problems(template, locale) {
    const body = form[locale];
    const list = [];
    const used = [...body.matchAll(/\{(\w*)\}/g)].map((match) => match[1]);
    const known = template.variables.map((variable) => variable.name);
    const unknown = [...new Set(used.filter((name) => ! known.includes(name)))];

    if (unknown.length > 0) {
        list.push(t('sms.template_unknown_variable', { names: unknown.map((name) => `{${name}}`).join(', ') }));
    }

    const missing = template.variables.filter((variable) => variable.required && ! used.includes(variable.name));

    if (missing.length > 0) {
        list.push(t('sms.template_missing_variable', { names: missing.map((variable) => `{${variable.name}}`).join(', ') }));
    }

    if (body.length > MAX_CHARS) {
        list.push(t('sms.template_too_long', { max: MAX_CHARS }));
    } else if (analyzeSms(fillTemplate(body, samples.value)).segments > MAX_PARTS) {
        list.push(t('sms.template_too_many_parts', { max: MAX_PARTS }));
    }

    if (body.trim() === '') {
        list.push(t('sms.template_empty_resets'));
    }

    return list;
}

const blocking = computed(() => LOCALES.some((locale) => problems(props.template, locale).some((message) => message !== t('sms.template_empty_resets'))));

function insert(variable) {
    const token = `{${variable.name}}`;
    const body = form[lang.value];
    const element = area.value;

    if (! element) {
        form[lang.value] = body + token;

        return;
    }

    const start = element.selectionStart ?? body.length;
    const end = element.selectionEnd ?? body.length;

    form[lang.value] = body.slice(0, start) + token + body.slice(end);
    requestAnimationFrame(() => {
        element.focus();
        element.setSelectionRange(start + token.length, start + token.length);
    });
}

function save() {
    form.put(route('admin.settings.sms.templates', props.template.key), {
        preserveScroll: true,
        onSuccess: () => notify.success(t('sms.template_saved')),
    });
}

function reset() {
    form.en = props.template.defaults.en;
    form.bn = props.template.defaults.bn;
    save();
}

const encodingLabel = (encoding) => (encoding === 'unicode' ? t('sms.encoding_unicode') : t('sms.encoding_gsm'));
</script>

<template>
    <form novalidate class="space-y-3" @submit.prevent="save">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-[13px] font-medium text-foreground">
                {{ t('sms.template.' + template.key) }}
                <span v-if="isCustom" class="ml-1 rounded-full bg-accent/15 px-2 py-0.5 text-xs font-normal">{{ t('sms.customised') }}</span>
            </p>
            <div class="flex gap-1" role="tablist">
                <button v-for="locale in LOCALES" :key="locale" type="button" role="tab" :aria-selected="lang === locale" class="h-6 rounded-md border px-2.5 text-xs font-medium" :class="lang === locale ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-surface text-muted hover:text-foreground'" @click="lang = locale">
                    {{ locale === 'en' ? 'English' : 'বাংলা' }}
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-xs text-muted">{{ t('sms.variables') }}</span>
            <button v-for="variable in template.variables" :key="variable.name" type="button" class="rounded border border-border bg-background px-1.5 py-0.5 font-mono text-xs text-foreground hover:border-accent" :title="t('sms.variable_sample', { sample: variable.sample })" @click="insert(variable)">
                {{ '{' + variable.name + '}' }}<span v-if="variable.required" class="text-danger">*</span>
            </button>
        </div>

        <textarea ref="area" v-model="form[lang]" rows="3" :maxlength="2000" :aria-label="t('sms.template.' + template.key)" class="w-full resize-y rounded-md border border-border bg-surface px-2 py-1 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40" />

        <p class="text-xs tabular-nums" :class="stats.segments > 2 ? 'text-warning' : 'text-muted'">
            {{ t('sms.count_line_template', { chars: stats.chars, words: stats.words, parts: stats.segments, size: stats.perSegment }) }}
            {{ encodingLabel(stats.encoding) }}
        </p>
        <ul v-if="problems(template, lang).length > 0" class="space-y-0.5 text-xs" role="alert">
            <li v-for="message in problems(template, lang)" :key="message" :class="message === t('sms.template_empty_resets') ? 'text-muted' : 'text-danger'">{{ message }}</li>
        </ul>
        <p v-if="form.errors[lang]" class="text-xs text-danger" role="alert">{{ form.errors[lang] }}</p>

        <div class="rounded-md border border-dashed border-border bg-background px-2.5 py-2">
            <p class="mb-0.5 text-xs font-medium text-muted">{{ t('sms.preview') }}</p>
            <p class="whitespace-pre-wrap break-words text-[13px] text-foreground">{{ preview || t('sms.preview_empty') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button" :disabled="form.processing || ! isCustom" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background disabled:opacity-50" @click="reset">
                <AppIcon name="rotate" size="12" />
                {{ t('sms.reset_default') }}
            </button>
            <button type="submit" :disabled="form.processing || ! isChanged || blocking" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                <AppIcon v-if="form.processing" name="spinner" spin />
                {{ t('common.save') }}
            </button>
        </div>
    </form>
</template>
