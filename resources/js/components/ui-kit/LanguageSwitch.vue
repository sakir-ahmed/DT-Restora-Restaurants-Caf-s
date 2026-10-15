<script setup>
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const locale = computed(() => page.props.locale ?? 'en');

function switchTo(next) {
    if (next === locale.value) {
        return;
    }

    router.post('/locale', { locale: next }, {
        preserveState: false,
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="flex h-6 items-center rounded-md border border-border bg-surface p-0.5" role="group" aria-label="Language">
        <button
            v-for="code in ['en', 'bn']"
            :key="code"
            type="button"
            class="h-5 rounded px-1.5 text-[11px] font-medium transition-colors"
            :class="locale === code ? 'bg-primary text-primary-foreground' : 'text-muted hover:text-foreground'"
            :aria-pressed="locale === code"
            @click="switchTo(code)"
        >
            {{ code === 'en' ? 'EN' : 'বাং' }}
        </button>
    </div>
</template>
