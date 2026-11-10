<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { printUrl } from '@/lib/print';

/**
 * Invoice style and paper size of one branch, with a live preview of a sample
 * invoice and a test print on the real printer.
 */
const props = defineProps({
    invoicePrint: { type: Object, required: true },
    layouts: { type: Array, required: true },
    papers: { type: Array, required: true },
});

const form = useForm({
    layout: props.invoicePrint.layout,
    paper: props.invoicePrint.paper,
});

// On-screen width of each paper at 96 dpi, so the preview shows the real proportions.
const PAPER_WIDTH = { '58mm': 220, '80mm': 302, a5: 560, a4: 794 };

const previewUrl = computed(() => route('admin.settings.invoice-preview', { layout: form.layout, paper: form.paper }));
const previewWidth = computed(() => PAPER_WIDTH[form.paper] ?? 302);
const dirty = computed(() => form.layout !== props.invoicePrint.layout || form.paper !== props.invoicePrint.paper);

function save() {
    form.put(route('admin.settings.invoice-print'), {
        preserveScroll: true,
        onSuccess: () => notify.success(t('settings.saved')),
    });
}

function testPrint() {
    printUrl(previewUrl.value);
}

const cardClass = (active) => [
    'flex cursor-pointer flex-col gap-0.5 rounded-md border px-2.5 py-1.5 text-[13px]',
    active ? 'border-accent bg-accent/10' : 'border-border bg-background hover:border-accent/60',
];
</script>

<template>
    <form novalidate class="grid max-w-6xl gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]" @submit.prevent="save">
        <div class="space-y-4 rounded-lg border border-border bg-surface p-4">
            <FormField :label="t('settings.paper_size')" :helper="t('settings.paper_size_helper')" :error="form.errors.paper">
                <template #default>
                    <div role="radiogroup" :aria-label="t('settings.paper_size')" class="grid grid-cols-2 gap-2">
                        <label v-for="paper in papers" :key="paper" :class="cardClass(form.paper === paper)">
                            <span class="flex items-center gap-2 font-medium">
                                <input v-model="form.paper" type="radio" name="invoice_paper" :value="paper" class="accent-[var(--color-primary)]">
                                {{ t(`settings.paper_${paper}`) }}
                            </span>
                            <span class="text-xs text-muted">{{ t(`settings.paper_${paper}_hint`) }}</span>
                        </label>
                    </div>
                </template>
            </FormField>

            <FormField :label="t('settings.invoice_layout')" :error="form.errors.layout">
                <template #default>
                    <div role="radiogroup" :aria-label="t('settings.invoice_layout')" class="grid grid-cols-1 gap-2">
                        <label v-for="layout in layouts" :key="layout" :class="cardClass(form.layout === layout)">
                            <span class="flex items-center gap-2 font-medium">
                                <input v-model="form.layout" type="radio" name="invoice_layout" :value="layout" class="accent-[var(--color-primary)]">
                                {{ t(`settings.layout_${layout}`) }}
                            </span>
                            <span class="text-xs text-muted">{{ t(`settings.layout_${layout}_hint`) }}</span>
                        </label>
                    </div>
                </template>
            </FormField>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-3">
                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="testPrint">
                    <AppIcon name="print" size="12" />
                    {{ t('settings.test_print') }}
                </button>
                <button type="submit" :disabled="form.processing || ! dirty" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                    <AppIcon v-if="form.processing" name="spinner" spin />
                    {{ t('common.save') }}
                </button>
            </div>
        </div>

        <div class="min-w-0 rounded-lg border border-border bg-surface p-4">
            <h2 class="mb-2 text-[13px] font-semibold text-foreground">{{ t('settings.invoice_preview') }}</h2>
            <p class="mb-3 text-xs text-muted">{{ t('settings.invoice_preview_helper') }}</p>
            <div class="overflow-auto rounded-md border border-border bg-background p-3" style="max-height: 640px;">
                <iframe
                    :key="previewUrl"
                    :src="previewUrl"
                    :title="t('settings.invoice_preview')"
                    class="mx-auto block border-0 bg-white"
                    :style="{ width: previewWidth + 'px', height: (form.paper === 'a4' ? 1000 : form.paper === 'a5' ? 760 : 620) + 'px' }"
                />
            </div>
        </div>
    </form>
</template>
