<script setup>
import { computed, ref, watch } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    variant: { type: String, default: 'info' }, // danger | warning | info | success
    level: { type: String, default: 'simple' }, // simple | reason | approval | typed
    title: { type: String, required: true },
    message: { type: String, default: '' },
    facts: { type: Array, default: () => [] }, // [{label, value}]
    confirmText: { type: String, default: null },
    cancelText: { type: String, default: null },
    reasons: { type: Array, default: () => [] }, // reason codes
    typedWord: { type: String, default: 'CONFIRM' },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['cancel', 'confirm']);

const reason = ref('');
const note = ref('');
const pin = ref('');
const typed = ref('');
const root = ref(null);

const variantMeta = computed(() => ({
    danger: { icon: 'trash', classes: 'bg-danger/10 text-danger' },
    warning: { icon: 'triangle-exclamation', classes: 'bg-warning/10 text-warning' },
    info: { icon: 'circle-info', classes: 'bg-muted/10 text-foreground' },
    success: { icon: 'circle-check', classes: 'bg-success/10 text-success' },
})[props.variant] ?? { icon: 'circle-info', classes: 'bg-muted/10 text-foreground' });

const buttonClasses = computed(() => ({
    danger: 'bg-danger text-white hover:bg-danger/90',
    warning: 'bg-warning text-accent-foreground hover:bg-warning/90',
    info: 'bg-primary text-primary-foreground hover:bg-primary/90',
    success: 'bg-success text-white hover:bg-success/90',
})[props.variant] ?? 'bg-primary text-primary-foreground hover:bg-primary/90');

const resolvedConfirmText = computed(() => props.confirmText ?? t('common.confirm'));

const resolvedCancelText = computed(() => props.cancelText ?? t('common.cancel'));

const canConfirm = computed(() => {
    if (props.busy) {
        return false;
    }

    if (props.level === 'reason') {
        return reason.value.trim() !== '';
    }

    if (props.level === 'approval') {
        return reason.value.trim() !== '' && pin.value.trim().length >= 4;
    }

    if (props.level === 'typed') {
        return typed.value.trim() === props.typedWord;
    }

    return true;
});

watch(() => props.open, (open) => {
    if (! open) {
        return;
    }

    reason.value = '';
    note.value = '';
    pin.value = '';
    typed.value = '';

    queueMicrotask(() => {
        const dialog = root.value;

        if (! dialog) {
            return;
        }

        dialog.focus();

        // Focus starts on Cancel for destructive dialogs.
        if (props.variant === 'danger') {
            dialog.querySelector('[data-cancel]')?.focus();
        } else {
            dialog.querySelector('[data-confirm]')?.focus();
        }
    });
});

function submit() {
    if (! canConfirm.value) {
        return;
    }

    emit('confirm', {
        reason: reason.value || null,
        note: note.value || null,
        pin: pin.value || null,
    });
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
                @keydown.esc="!busy && emit('cancel')"
            >
                <div
                    ref="root"
                    role="alertdialog"
                    aria-modal="true"
                    :aria-label="title"
                    tabindex="-1"
                    class="w-full max-w-md rounded-xl border border-border bg-surface p-4 shadow-lg outline-none"
                    @keydown.enter="submit"
                >
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full" :class="variantMeta.classes">
                            <AppIcon :name="variantMeta.icon" size="20" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold text-foreground">{{ title }}</h2>
                            <p v-if="message" class="mt-1 text-[13px] leading-relaxed text-muted">{{ message }}</p>
                        </div>
                    </div>

                    <dl v-if="facts.length" class="mt-3 space-y-1 rounded-lg border border-border bg-background/50 p-3">
                        <div v-for="fact in facts" :key="fact.label" class="flex items-center justify-between gap-4 text-[13px]">
                            <dt class="text-muted">{{ fact.label }}</dt>
                            <dd class="font-medium text-foreground tabular-nums">{{ fact.value }}</dd>
                        </div>
                    </dl>

                    <div v-if="level === 'reason' || level === 'approval'" class="mt-3 space-y-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-foreground" for="confirm-reason">
                                {{ t('common.reason') }} <span class="text-danger">*</span>
                            </label>
                            <select
                                id="confirm-reason"
                                v-model="reason"
                                class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40"
                            >
                                <option value="" disabled>{{ t('common.select_reason') }}</option>
                                <option v-for="code in reasons" :key="code" :value="code">{{ t('reasons.' + code, {}) === 'reasons.' + code ? code : t('reasons.' + code) }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-foreground" for="confirm-note">
                                {{ t('common.note') }} <span class="text-muted">({{ t('common.optional') }})</span>
                            </label>
                            <textarea
                                id="confirm-note"
                                v-model="note"
                                rows="2"
                                class="w-full resize-y rounded-md border border-border bg-surface px-2 py-1 text-[13px] text-foreground focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40"
                            />
                        </div>
                    </div>

                    <div v-if="level === 'approval'" class="mt-2">
                        <label class="mb-1 block text-xs font-medium text-foreground" for="confirm-pin">
                            {{ t('common.manager_pin') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            id="confirm-pin"
                            v-model="pin"
                            type="password"
                            inputmode="numeric"
                            autocomplete="off"
                            class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40"
                        >
                    </div>

                    <div v-if="level === 'typed'" class="mt-3">
                        <label class="mb-1 block text-xs font-medium text-foreground" for="confirm-typed">
                            {{ t('common.type_to_confirm', { word: typedWord }) }}
                        </label>
                        <input
                            id="confirm-typed"
                            v-model="typed"
                            type="text"
                            autocomplete="off"
                            :placeholder="typedWord"
                            class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/40"
                        >
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            data-cancel
                            :disabled="busy"
                            class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background focus-visible:ring-2 focus-visible:ring-accent disabled:cursor-not-allowed disabled:opacity-50"
                            @click="emit('cancel')"
                        >
                            {{ resolvedCancelText }}
                        </button>

                        <button
                            type="button"
                            data-confirm
                            :disabled="!canConfirm"
                            class="inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-[13px] font-medium disabled:cursor-not-allowed disabled:opacity-50"
                            :class="buttonClasses"
                            @click="submit"
                        >
                            <AppIcon v-if="busy" name="spinner" spin />
                            {{ resolvedConfirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
