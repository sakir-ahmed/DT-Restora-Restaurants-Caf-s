<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import QrCode from '@/components/QrCode.vue';
import { t } from '@/lib/i18n';
import { localized } from '@/lib/site';

function print() {
    window.print();
}

defineProps({
    tables: { type: Array, required: true },
    branch: { type: Object, required: true },
});
</script>

<template>
    <div class="sheet-page">
        <header class="sheet-bar">
            <div class="flex items-center gap-3">
                <Link :href="route('admin.tables.index')" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-surface px-3 text-[13px] text-foreground hover:bg-background">
                    <AppIcon name="chevron-left" size="12" />
                    {{ t('tables.title') }}
                </Link>
                <h1 class="text-base font-semibold text-foreground">{{ t('tables.qr_sheet_title') }}</h1>
            </div>
            <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-[13px] font-medium text-primary-foreground hover:opacity-90" @click="print">
                <AppIcon name="print" size="12" />
                {{ t('tables.qr_print') }}
            </button>
        </header>

        <p v-if="tables.length === 0" class="sheet-empty">{{ t('tables.qr_sheet_empty') }}</p>

        <main class="sheet-grid">
            <article v-for="table in tables" :key="table.id" class="card">
                <p class="card-brand">{{ localized(branch, 'name') }}</p>
                <div class="card-rule" />
                <p class="card-kicker">{{ t('tables.qr_scan_to_order') }}</p>
                <div class="card-qr">
                    <QrCode :value="table.qr_url" :size="190" :border="1" dark="#1a1208" :label="`${t('tables.qr_title')} ${table.name}`" />
                </div>
                <p class="card-table">{{ table.name }}</p>
                <p v-if="table.area" class="card-area">{{ table.area }}</p>
                <p class="card-line">{{ t('tables.qr_scan_line') }}</p>
            </article>
        </main>
    </div>
</template>

<script>
export default { layout: null };
</script>

<style scoped>
.sheet-page {
    min-height: 100vh;
    background: var(--book-tint-strong);
    font-family: Georgia, 'Noto Serif Bengali', 'Times New Roman', serif;
}

.sheet-bar {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--color-border);
    background: var(--color-surface);
    font-family: inherit;
}

.sheet-empty {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--book-ink-soft);
}

.sheet-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr));
    gap: 1.25rem;
    max-width: 62rem;
    margin: 0 auto;
    padding: 1.5rem;
}

.card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    padding: 1.4rem 1.2rem 1.2rem;
    text-align: center;
    break-inside: avoid;
    border-radius: 1rem;
    background: var(--book-paper);
    color: var(--book-ink);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.14), inset 0 0 0 1px var(--book-paper-edge), inset 0 0 0 6px var(--book-paper), inset 0 0 0 7px var(--book-accent);
}

.card-brand {
    font-size: 1.15rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.card-rule {
    width: 5rem;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--book-accent), transparent);
}

.card-kicker {
    margin-top: 0.2rem;
    font-size: 0.7rem;
    letter-spacing: 0.35em;
    text-transform: uppercase;
    color: var(--book-accent-dark);
}

.card-qr {
    padding: 0.5rem;
    border-radius: 0.8rem;
    border: 1px solid var(--book-paper-edge);
    background: var(--book-page);
}

.card-table {
    font-size: 2.2rem;
    line-height: 1;
    font-weight: 700;
}

.card-area {
    font-size: 0.85rem;
    letter-spacing: 0.08em;
    color: var(--book-ink-soft);
}

.card-line {
    max-width: 14rem;
    font-size: 0.78rem;
    font-style: italic;
    line-height: 1.4;
    color: var(--book-ink-soft);
}

@media print {
    .sheet-bar {
        display: none;
    }

    .sheet-page {
        background: var(--book-page);
    }

    .sheet-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8mm;
        max-width: none;
        padding: 0;
    }

    .card {
        box-shadow: inset 0 0 0 1px var(--book-accent);
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

@page {
    size: A4;
    margin: 10mm;
}
</style>
