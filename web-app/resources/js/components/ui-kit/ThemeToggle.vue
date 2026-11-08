<script setup>
import { onMounted, ref } from 'vue';
import { t } from '@/lib/i18n';
import AppIcon from '@/components/AppIcon.vue';

const dark = ref(false);

onMounted(() => {
    dark.value = document.documentElement.classList.contains('dark');
});

function toggle() {
    dark.value = !dark.value;

    document.documentElement.classList.toggle('dark', dark.value);

    localStorage.setItem('theme', dark.value ? 'dark' : 'light');
}
</script>

<template>
    <button
        type="button"
        class="flex h-8 w-8 items-center justify-center rounded-md text-muted hover:bg-background hover:text-foreground"
        :aria-label="t('common.toggle_theme')"
        @click="toggle"
    >
        <AppIcon :name="dark ? 'sun' : 'moon'" size="16" />
    </button>
</template>
