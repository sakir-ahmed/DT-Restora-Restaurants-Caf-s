<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import QrCode from '@/components/QrCode.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { qrMatrix, qrPath } from '@/lib/qr';
import { localized } from '@/lib/site';

/**
 * The public menu QR code of the current branch. It points to /{branch code}/menu, so a code
 * printed once keeps working. It can be used as the bare QR code (any size, or vector) or as a
 * visiting card with the brand, a scan line and the contact details. Either one can be saved,
 * copied as a picture, shared, or the link can be texted to a guest.
 */
const props = defineProps({
    menuQr: { type: Object, required: true },
});

const QUIET_ZONE = 4;
const DPI = 300;
const CARD_MM = { width: 90, height: 55 };
const CARD_PIXELS = 2126; // 90 mm at 600 dpi

const SIZES = [
    { key: 'screen', pixels: 1024, label: 'menu.qr_size_screen' },
    { key: 'small', pixels: 2048, label: 'menu.qr_size_small' },
    { key: 'large', pixels: 4096, label: 'menu.qr_size_large' },
    { key: 'poster', pixels: 8192, label: 'menu.qr_size_poster' },
    { key: 'vector', pixels: 0, label: 'menu.qr_size_vector' },
];

const COLORS = [
    { key: 'black', hex: '#000000', label: 'menu.qr_color_black' },
    { key: 'brown', hex: '#3b1f0a', label: 'menu.qr_color_brown' },
    { key: 'navy', hex: '#0b2a6f', label: 'menu.qr_color_navy' },
    { key: 'green', hex: '#0b5a35', label: 'menu.qr_color_green' },
];

const canNativeShare = computed(() => typeof navigator !== 'undefined' && typeof navigator.share === 'function');
const busy = ref(false);
const mode = ref('qr');
const sizeKey = ref('large');
const colorKey = ref('black');
const cardPreview = ref('');
const sms = useForm({ mobile: '' });

const baseName = computed(() => `menu-qr-${String(props.menuQr.code).toLowerCase()}`);
const color = computed(() => COLORS.find((item) => item.key === colorKey.value) ?? COLORS[0]);
const modules = computed(() => qrMatrix(props.menuQr.url).size + QUIET_ZONE * 2);

/**
 * Whole pixels per module so every square is crisp; the real size is a little under the target.
 */
function pixelsFor(target) {
    return modules.value * Math.max(1, Math.floor(target / modules.value));
}

const sizes = computed(() => SIZES.map((size) => {
    const pixels = size.pixels === 0 ? 0 : pixelsFor(size.pixels);

    return { ...size, pixels, centimetres: pixels === 0 ? 0 : Math.floor((pixels / DPI) * 2.54) };
}));

const selected = computed(() => sizes.value.find((size) => size.key === sizeKey.value) ?? sizes.value[2]);

/**
 * One line under the options: the real pixel size and what it prints at.
 */
const detail = computed(() => {
    if (mode.value === 'card') {
        return t('menu.qr_card_note');
    }

    if (selected.value.pixels === 0) {
        return `SVG - ${t('menu.qr_size_vector')}`;
    }

    return `${selected.value.pixels} x ${selected.value.pixels} px - ${t(selected.value.label)} - ${t('menu.qr_prints_at', { size: selected.value.centimetres })}`;
});

function toBlob(canvas) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('no image'))), 'image/png');
    });
}

function drawQr(context, x, y, side, hex, border) {
    const { size, modules: grid } = qrMatrix(props.menuQr.url);
    const cell = side / (size + border * 2);

    context.fillStyle = '#ffffff';
    context.fillRect(x, y, side, side);
    context.fillStyle = hex;
    grid.forEach((row, row_y) => row.forEach((dark, col_x) => {
        if (dark) {
            context.fillRect(x + (col_x + border) * cell, y + (row_y + border) * cell, cell, cell);
        }
    }));
}

function makeQr(pixels) {
    const canvas = document.createElement('canvas');

    canvas.width = pixels;
    canvas.height = pixels;
    drawQr(canvas.getContext('2d'), 0, 0, pixels, color.value.hex, QUIET_ZONE);

    return toBlob(canvas);
}

function makeSvg() {
    const { path, viewBox } = qrPath(props.menuQr.url, QUIET_ZONE);
    const svg = `<?xml version="1.0" encoding="UTF-8"?>\n<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${viewBox} ${viewBox}" width="${viewBox * 20}" height="${viewBox * 20}" shape-rendering="crispEdges"><rect width="${viewBox}" height="${viewBox}" fill="#ffffff"/><path d="${path}" fill="${color.value.hex}"/></svg>\n`;

    return new Blob([svg], { type: 'image/svg+xml' });
}

/**
 * Cut a line of text into lines no wider than the space allows.
 */
function wrap(context, text, width) {
    const lines = [];
    let line = '';

    String(text).split(/\s+/).filter(Boolean).forEach((word) => {
        const next = line === '' ? word : `${line} ${word}`;

        if (context.measureText(next).width > width && line !== '') {
            lines.push(line);
            line = word;
        } else {
            line = next;
        }
    });

    if (line !== '') {
        lines.push(line);
    }

    return lines;
}

/**
 * The visiting card, drawn in millimetres: brand and contact details on the left, the QR code on a
 * dark panel on the right with the "scan to see our menu" line.
 */
function drawCard(widthPixels) {
    const canvas = document.createElement('canvas');
    const scale = widthPixels / CARD_MM.width;

    canvas.width = widthPixels;
    canvas.height = Math.round(CARD_MM.height * scale);

    const context = canvas.getContext('2d');
    const serif = 'Georgia, "Noto Serif Bengali", "Times New Roman", serif';
    const sans = '"Helvetica Neue", Arial, "Noto Sans Bengali", sans-serif';
    const gold = '#b8892f';
    const ink = '#1a1208';
    const panelX = 55;

    context.scale(scale, scale);
    context.textBaseline = 'alphabetic';

    context.fillStyle = '#fbf6ea';
    context.fillRect(0, 0, CARD_MM.width, CARD_MM.height);

    // dark scan panel
    context.fillStyle = ink;
    context.fillRect(panelX, 0, CARD_MM.width - panelX, CARD_MM.height);
    context.fillStyle = gold;
    context.fillRect(panelX, 0, 0.6, CARD_MM.height);

    // brand
    const left = 6;
    const textWidth = panelX - left - 5;

    context.fillStyle = gold;
    context.font = `600 2.1px ${sans}`;
    context.fillText(String(props.menuQr.code).toUpperCase(), left, 8.4);

    context.fillStyle = ink;
    context.font = `700 5.6px ${serif}`;
    let y = 15.2;

    wrap(context, localized(props.menuQr, 'name'), textWidth).slice(0, 2).forEach((line) => {
        context.fillText(line, left, y);
        y += 6.4;
    });

    context.fillStyle = gold;
    context.fillRect(left, y - 3.4, 16, 0.3);

    context.fillStyle = '#6d5b45';
    context.font = `italic 3px ${serif}`;
    context.fillText(t('menu.card_tagline'), left, y + 1.6);

    // contact
    let contactY = 36;
    const phone = props.menuQr.phone;
    const address = [localized(props.menuQr, 'address'), localized(props.menuQr, 'city')].filter(Boolean).join(', ');

    const row = (label, value) => {
        context.fillStyle = gold;
        context.font = `600 1.9px ${sans}`;
        context.fillText(label.toUpperCase(), left, contactY);
        context.fillStyle = ink;
        context.font = `500 2.7px ${sans}`;
        wrap(context, value, textWidth).slice(0, 2).forEach((line, index) => {
            context.fillText(line, left, contactY + 3.4 + index * 3.2);
        });
        contactY += 3.4 + Math.min(2, Math.max(1, wrap(context, value, textWidth).length)) * 3.2 + 2;
    };

    if (phone) {
        row(t('menu.card_phone'), phone);
    }

    if (address) {
        row(t('menu.card_address'), address);
    }

    // outer frame
    context.strokeStyle = gold;
    context.lineWidth = 0.25;
    context.strokeRect(1.4, 1.4, CARD_MM.width - 2.8, CARD_MM.height - 2.8);

    // QR on a white tile
    const tile = 30;
    const tileX = panelX + (CARD_MM.width - panelX - tile) / 2;

    context.fillStyle = gold;
    context.font = `600 2.1px ${sans}`;
    context.textAlign = 'center';
    context.fillText(t('menu.card_scan').toUpperCase(), panelX + (CARD_MM.width - panelX) / 2, 13.5);
    context.textAlign = 'left';

    drawQr(context, tileX, 17, tile, color.value.hex, 3);

    return canvas;
}

function makeCard() {
    return toBlob(drawCard(CARD_PIXELS));
}

function refreshPreview() {
    try {
        cardPreview.value = drawCard(900).toDataURL('image/png');
    } catch {
        cardPreview.value = '';
    }
}

onMounted(refreshPreview);
watch([colorKey, () => props.menuQr], refreshPreview, { deep: true });

function save(blob, name) {
    const link = document.createElement('a');

    link.href = URL.createObjectURL(blob);
    link.download = name;
    link.click();
    setTimeout(() => URL.revokeObjectURL(link.href), 1000);
}

async function download() {
    if (mode.value === 'qr' && selected.value.pixels === 0) {
        save(makeSvg(), `${baseName.value}.svg`);

        return;
    }

    busy.value = true;

    try {
        const card = mode.value === 'card';
        const pixels = card ? CARD_PIXELS : selected.value.pixels;
        const name = `${card ? `menu-card-${String(props.menuQr.code).toLowerCase()}` : baseName.value}-${pixels}px.png`;

        save(await (card ? makeCard() : makeQr(pixels)), name);
        notify.success(t('menu.qr_downloaded', { name, px: pixels }));
    } catch {
        notify.error(t('menu.qr_too_big'));
    } finally {
        busy.value = false;
    }
}

async function copyImage() {
    busy.value = true;

    try {
        // The clipboard takes a normal sized picture; the vector choice is copied as a 2048 px PNG.
        const maker = mode.value === 'card' ? makeCard() : makeQr(pixelsFor(Math.min(selected.value.pixels || 2048, 4096)));

        await navigator.clipboard.write([new ClipboardItem({ 'image/png': maker })]);
        notify.success(t('menu.qr_image_copied'));
    } catch {
        notify.error(t('menu.qr_copy_image_failed'));
    } finally {
        busy.value = false;
    }
}

async function shareImage() {
    busy.value = true;

    try {
        const card = mode.value === 'card';
        const blob = await (card ? makeCard() : makeQr(pixelsFor(2048)));
        const file = new File([blob], `${card ? 'menu-card' : baseName.value}.png`, { type: 'image/png' });

        if (navigator.canShare?.({ files: [file] })) {
            try {
                await navigator.share({ files: [file], title: localized(props.menuQr, 'name'), url: props.menuQr.url });
            } catch {
                // the person closed the share sheet
            }
        } else {
            save(blob, file.name);
        }
    } catch {
        notify.error(t('menu.qr_image_failed'));
    } finally {
        busy.value = false;
    }
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.menuQr.url);
        notify.success(t('menu.qr_copied'));
    } catch {
        document.querySelector('[data-menu-link]')?.select();
    }
}

async function shareLink() {
    try {
        await navigator.share({ title: localized(props.menuQr, 'name'), url: props.menuQr.url });
    } catch {
        // the person closed the share sheet
    }
}

function sendSms() {
    sms.post(route('admin.menu.qr-sms'), {
        preserveScroll: true,
        onSuccess: () => {
            notify.success(t('menu.qr_sms_sent'));
            sms.reset();
        },
    });
}

const button = 'inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background disabled:opacity-60';
const primaryButton = 'inline-flex h-8 items-center justify-center gap-1.5 rounded-md bg-primary px-3 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60';
</script>

<template>
    <div class="space-y-3">
        <section class="rounded-lg border border-border bg-surface p-3" :aria-label="t('menu.qr_title')">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="truncate text-[13px] font-semibold leading-tight text-foreground">{{ localized(menuQr, 'name') }}</p>
                    <p class="text-[11px] text-muted">{{ menuQr.code }} &middot; {{ t('menu.qr_hint_short') }}</p>
                </div>
                <div class="inline-flex shrink-0 rounded-md border border-border bg-background p-0.5" role="tablist">
                    <button
                        v-for="item in [{ key: 'qr', label: 'menu.qr_mode_qr' }, { key: 'card', label: 'menu.qr_mode_card' }]"
                        :key="item.key"
                        type="button"
                        role="tab"
                        :aria-selected="mode === item.key"
                        class="rounded px-2.5 py-1 text-xs font-medium transition"
                        :class="mode === item.key ? 'bg-surface text-foreground shadow-sm' : 'text-muted hover:text-foreground'"
                        @click="mode = item.key"
                    >
                        {{ t(item.label) }}
                    </button>
                </div>
            </div>

            <div class="flex min-h-[200px] items-center justify-center rounded-md border border-border bg-background p-3">
                <QrCode v-if="mode === 'qr'" :value="menuQr.url" :size="176" :border="1" :dark="color.hex" :label="t('menu.qr_title')" class="rounded-md bg-white p-1.5 shadow-sm ring-1 ring-border" />
                <img v-else-if="cardPreview" :src="cardPreview" :alt="t('menu.qr_mode_card')" class="block w-full max-w-[400px] rounded-sm shadow-sm ring-1 ring-border">
            </div>

            <div class="mt-3 flex items-center gap-1.5">
                <input data-menu-link type="text" readonly :value="menuQr.url" class="h-8 min-w-0 flex-1 rounded-md border border-border bg-background px-2 font-mono text-[11px] text-foreground" @focus="$event.target.select()">
                <button type="button" :class="button" :title="t('menu.qr_copy')" @click="copyLink">
                    <AppIcon name="copy" size="11" />
                    <span class="sr-only sm:not-sr-only">{{ t('menu.qr_copy') }}</span>
                </button>
                <button v-if="canNativeShare" type="button" :class="button" :title="t('menu.qr_share_link')" @click="shareLink">
                    <AppIcon name="link" size="11" />
                    <span class="sr-only">{{ t('menu.qr_share_link') }}</span>
                </button>
                <a :href="menuQr.url" target="_blank" rel="noopener" :class="button" :title="t('menu.qr_open')">
                    <AppIcon name="arrow-up-right" size="11" />
                    <span class="sr-only">{{ t('menu.qr_open') }}</span>
                </a>
            </div>
        </section>

        <section class="rounded-lg border border-border bg-surface p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div v-if="mode === 'qr'" class="inline-flex rounded-md border border-border bg-background p-0.5" role="radiogroup" :aria-label="t('menu.qr_choose_size')">
                    <button
                        v-for="size in sizes"
                        :key="size.key"
                        type="button"
                        role="radio"
                        :aria-checked="sizeKey === size.key"
                        class="rounded px-2 py-1 text-xs font-medium transition"
                        :class="sizeKey === size.key ? 'bg-surface text-foreground shadow-sm' : 'text-muted hover:text-foreground'"
                        @click="sizeKey = size.key"
                    >
                        {{ size.pixels === 0 ? 'SVG' : SIZES.find((item) => item.key === size.key).pixels }}
                    </button>
                </div>
                <p v-else class="text-xs font-medium text-foreground">90 x 55 mm</p>

                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] text-muted">{{ t('menu.qr_color') }}</span>
                    <button
                        v-for="item in COLORS"
                        :key="item.key"
                        type="button"
                        :title="t(item.label)"
                        :aria-label="t(item.label)"
                        :aria-pressed="colorKey === item.key"
                        class="h-4 w-4 rounded-full border border-border transition"
                        :class="colorKey === item.key ? 'ring-2 ring-primary ring-offset-1' : ''"
                        :style="{ backgroundColor: item.hex }"
                        @click="colorKey = item.key"
                    />
                </div>
            </div>

            <p class="mt-2 text-[11px] leading-snug text-muted">{{ detail }}</p>

            <div class="mt-3 grid grid-cols-2 gap-1.5">
                <button type="button" :disabled="busy" :class="primaryButton" @click="download">
                    <AppIcon :name="busy ? 'spinner' : 'download'" size="12" :class="busy ? 'animate-spin' : ''" />
                    {{ t('menu.qr_download') }}
                </button>
                <button type="button" :disabled="busy" :class="button" @click="copyImage">
                    <AppIcon name="copy" size="12" />
                    {{ t('menu.qr_copy_image') }}
                </button>
            </div>
            <button v-if="canNativeShare" type="button" :disabled="busy" :class="[button, 'mt-1.5 w-full']" @click="shareImage">
                <AppIcon name="share-nodes" size="11" />
                {{ t('menu.qr_share_image') }}
            </button>
        </section>

        <section class="rounded-lg border border-border bg-surface p-3">
            <form novalidate @submit.prevent="sendSms">
                <p class="text-[13px] font-semibold text-foreground">{{ t('menu.qr_sms_label') }}</p>
                <p class="mb-1.5 text-[11px] text-muted">{{ t('menu.qr_sms_message_note') }}</p>
                <textarea
                    readonly
                    rows="2"
                    :value="menuQr.sms_text"
                    :aria-label="t('menu.qr_sms_message')"
                    class="w-full resize-none rounded-md border border-border bg-background px-2 py-1.5 text-xs leading-snug text-foreground"
                />
                <div class="mt-2 flex items-center gap-1.5">
                    <input
                        id="menu-sms-mobile"
                        v-model="sms.mobile"
                        type="tel"
                        inputmode="tel"
                        autocomplete="off"
                        :aria-label="t('menu.qr_sms_label')"
                        :placeholder="t('menu.qr_sms_placeholder')"
                        class="h-8 min-w-0 flex-1 rounded-md border border-border bg-surface px-2 text-[13px] text-foreground"
                    >
                    <button type="submit" :class="primaryButton" :disabled="sms.processing || sms.mobile.trim() === ''">
                        <AppIcon name="paper-plane" size="11" />
                        {{ t('menu.qr_sms_send') }}
                    </button>
                </div>
                <p v-if="sms.errors.mobile" class="mt-1 text-[11px] text-danger">{{ sms.errors.mobile }}</p>
            </form>
        </section>
    </div>
</template>
