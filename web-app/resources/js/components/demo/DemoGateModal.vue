<script setup>
import { nextTick, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import { startSession } from '@/lib/demoTracker';
import { t } from '@/lib/i18n';
import DemoLottie from './DemoLottie.vue';

const props = defineProps({ open: { type: Boolean, default: false } });
const emit = defineEmits(['started', 'skip']);

const mobile = ref('01');
const consent = ref(true);
const busy = ref(false);
const errors = ref({});
const root = ref(null);

const points = [
    { icon: 'cash-register', key: 'pos' },
    { icon: 'utensils', key: 'kds' },
    { icon: 'chart-line', key: 'reports' },
];

watch(() => props.open, (open) => {
    if (open) {
        nextTick(() => root.value?.querySelector('input[type="tel"]')?.focus());
    }
});

async function submit() {
    if (busy.value) {
        return;
    }

    busy.value = true;
    errors.value = {};

    try {
        const result = await startSession(mobile.value, consent.value);

        if (result.ok) {
            emit('started');

            return;
        }

        errors.value = Object.keys(result.errors).length ? result.errors : { mobile: [t('demo.start_failed')] };
        nextTick(() => root.value?.querySelector('[aria-invalid="true"]')?.focus());
    } catch {
        errors.value = { mobile: [t('demo.start_failed')] };
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[70] flex items-end justify-center bg-black/50 p-3 backdrop-blur-sm sm:items-center sm:p-4"
                @keydown.esc="emit('skip')"
            >
                <div
                    ref="root"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="t('demo.gate_title')"
                    class="demo-pop w-full max-w-md overflow-hidden rounded-2xl border border-border bg-surface shadow-lg"
                >
                    <div class="relative flex items-center gap-3 bg-primary px-5 py-4 text-primary-foreground">
                        <DemoLottie name="welcome" :size="84" />
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold leading-tight">{{ t('demo.gate_title') }}</h2>
                            <p class="mt-1 text-[13px] leading-snug opacity-80">{{ t('demo.gate_subtitle') }}</p>
                        </div>
                    </div>

                    <form class="space-y-3 px-5 py-4" novalidate @submit.prevent="submit">
                        <FormField :label="t('demo.mobile_label')" required :error="errors.mobile" for="demo-mobile">
                            <MobileInput v-model="mobile" input-id="demo-mobile" :invalid="!! errors.mobile" :disabled="busy" />
                        </FormField>

                        <div>
                            <label class="flex cursor-pointer items-start gap-2 text-[13px] leading-snug text-foreground">
                                <input
                                    v-model="consent"
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 shrink-0 accent-[var(--color-accent)]"
                                    :aria-invalid="errors.consent ? 'true' : undefined"
                                >
                                <span>{{ t('demo.consent') }}</span>
                            </label>
                            <p v-if="errors.consent" class="mt-1 text-xs text-danger" role="alert">{{ errors.consent[0] }}</p>
                        </div>

                        <ul class="flex flex-col gap-1 rounded-lg border border-border bg-background/60 px-3 py-2 text-[12px] text-muted">
                            <li v-for="point in points" :key="point.key" class="flex items-center gap-1.5">
                                <AppIcon :name="point.icon" size="12" class="text-accent" />
                                {{ t(`demo.features.${point.key}.title`) }}
                            </li>
                        </ul>

                        <button
                            type="submit"
                            :disabled="busy"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-accent text-sm font-semibold text-accent-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <AppIcon :name="busy ? 'spinner' : 'rocket'" size="14" :spin="busy" />
                            {{ busy ? t('demo.starting') : t('demo.start') }}
                        </button>

                        <button
                            type="button"
                            class="block w-full text-center text-[13px] font-medium text-muted underline-offset-2 hover:text-foreground hover:underline"
                            @click="emit('skip')"
                        >
                            {{ t('demo.skip') }}
                        </button>

                        <p class="text-center text-[11px] leading-snug text-muted">{{ t('demo.privacy_note') }}</p>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
