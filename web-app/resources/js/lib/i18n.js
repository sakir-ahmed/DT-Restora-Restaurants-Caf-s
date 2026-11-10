import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();

const translations = computed(() => page.props?.translations ?? {});
const locale = ref('en');

function interpolate(template, replacements = {}) {
    return template.replace(/:(\w+)/g, (match, key) => String(replacements[key] ?? match));
}

/**
 * Keep the html lang attribute and local locale in sync with the page.
 */
watch(() => page.props?.locale ?? 'en', (value) => {
    locale.value = value;

    if (typeof document !== 'undefined') {
        document.documentElement.lang = value;
    }
}, { immediate: true });

/**
 * Translate a key using the flattened lang files shared by Inertia.
 */
export function t(key, replacements = {}) {
    const value = translations.value[key] ?? key;

    return interpolate(value, replacements);
}

export function currentLocale() {
    return locale.value;
}
