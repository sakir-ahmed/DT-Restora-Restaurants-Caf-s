import { t } from '@/lib/i18n';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function money(value) {
    return Number(value || 0).toFixed(2);
}

function row(label, value) {
    return `<tr><td>${escapeHtml(label)}</td><td class="right">${escapeHtml(value)}</td></tr>`;
}

const PAGES = {
    '58mm': '@page { size: 58mm auto; margin: 0; } body { width: 50mm; padding: 3mm 0; font-size: 10px; line-height: 1.35; }',
    '80mm': '@page { size: 80mm auto; margin: 0; } body { width: 72mm; padding: 4mm 0; font-size: 11px; line-height: 1.45; }',
    a5: '@page { size: A5; margin: 10mm; } body { width: auto; max-width: 128mm; padding: 0; font-size: 13px; line-height: 1.5; }',
    a4: '@page { size: A4; margin: 14mm; } body { width: auto; max-width: 182mm; padding: 0; font-size: 14px; line-height: 1.5; }',
};

const STYLES = {
    compact: 'body { font-size: 90%; line-height: 1.3; } .hr { margin: 3px 0; }',
    bold: '.hr { border-top: 2px solid #000; } .grand td { font-size: 150%; padding: 4px 0; border-top: 2px solid #000; border-bottom: 2px solid #000; }',
    modern: '.brand { background: #000; color: #fff; padding: 6px 4px; margin-bottom: 6px; } .hr { border-top: 1px solid #999; } .grand td { background: #000; color: #fff; padding: 5px 6px; font-size: 125%; }',
    detailed: '.hr { border-top: 1px solid #000; } .grand td { border-top: 1px solid #000; border-bottom: 3px double #000; padding: 4px 0; }',
    minimal: '.hr { border-top: 0; margin: 8px 0; } .bold { font-weight: 600; } .grand td { border-top: 1px solid #000; padding-top: 4px; }',
};

/**
 * Print CSS for a paper size and style. Mirrors App\Support\ReceiptStyle
 * (PHP); keep the two in step.
 */
export function receiptCss(layout = 'classic', paper = '80mm') {
    return `${PAGES[paper] ?? PAGES['80mm']}
body { font-family: 'Noto Sans Bengali', 'Courier New', monospace; margin: 0 auto; color: #000; }
.center { text-align: center; }
.bold { font-weight: 700; }
.large { font-size: 118%; }
.hr { border-top: 1px dashed #000; margin: 6px 0; }
table { width: 100%; border-collapse: collapse; }
td { padding: 1px 0; vertical-align: top; }
td.right { text-align: right; }
${STYLES[layout] ?? ''}`;
}

/**
 * Receipt for a sale that has not reached the server yet.
 *
 * @param {object} receipt
 */
export function localReceiptHtml(receipt) {
    const branch = receipt.branch ?? {};
    const lines = (receipt.lines ?? []).map((line) => `
        <tr>
            <td class="bold">${escapeHtml(line.quantity)} x ${escapeHtml(line.name)}</td>
            <td class="right">${money(line.total)}</td>
        </tr>
    `).join('');

    const extras = [
        Number(receipt.discount) > 0 ? row(t('receipt.discount'), `-${money(receipt.discount)}`) : '',
        Number(receipt.service) > 0 ? row(t('receipt.service_charge'), money(receipt.service)) : '',
        Number(receipt.extra) > 0 ? row(t('receipt.other_charge'), money(receipt.extra)) : '',
        Number(receipt.vat) > 0 ? row(t('receipt.vat'), money(receipt.vat)) : '',
    ].join('');

    const change = Number(receipt.change) > 0
        ? row(t('receipt.change'), money(receipt.change))
        : '';

    return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>${escapeHtml(receipt.invoiceNo)}</title>
<style>${receiptCss(receipt.layout, receipt.paper)}</style>
</head>
<body class="layout-${escapeHtml(receipt.layout ?? 'classic')} paper-${escapeHtml(receipt.paper ?? '80mm')}">
<div class="brand center"><div class="bold large">${escapeHtml(branch.name ?? '')}</div>
${branch.address ? `<div class="center">${escapeHtml(branch.address)}</div>` : ''}
${branch.tax_registration_no ? `<div class="center">${escapeHtml(t('receipt.bin'))}: ${escapeHtml(branch.tax_registration_no)}</div>` : ''}
${branch.phone ? `<div class="center">${escapeHtml(t('receipt.phone'))}: ${escapeHtml(branch.phone)}</div>` : ''}
</div>
<div class="hr"></div>
<div class="bold">${escapeHtml(receipt.invoiceNo)}</div>
<div>${escapeHtml(receipt.when ?? '')}</div>
<div>${escapeHtml(receipt.orderType ?? '')}</div>
<div class="hr"></div>
<table>${lines}</table>
<div class="hr"></div>
<table>
${row(t('receipt.subtotal'), money(receipt.subtotal))}
${extras}
<tr class="grand"><td class="bold large">${escapeHtml(t('receipt.grand_total'))}</td><td class="right bold large">Tk ${money(receipt.total)}</td></tr>
${change}
</table>
<div class="hr"></div>
${branch.receipt_footer ? `<div class="center">${escapeHtml(branch.receipt_footer)}</div>` : ''}
<div class="center">${escapeHtml(t('receipt.thank_you'))}</div>
<div class="center">${escapeHtml(t('sync.queued'))}</div>
</body>
</html>`;
}

/**
 * Small kitchen token for a sale that has not reached the server yet.
 *
 * @param {object} token
 */
export function localKitchenTokenHtml(token) {
    const lines = (token.lines ?? []).map((line) => `
        <div class="item">${escapeHtml(line.quantity)} x ${escapeHtml(line.name)}</div>
        ${(line.addOns ?? []).map((addOn) => `<div class="addon">+ ${escapeHtml(addOn.qty)} x ${escapeHtml(addOn.name)}</div>`).join('')}
    `).join('');

    return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>${escapeHtml(token.invoiceNo)}</title>
<style>
@page { size: 58mm auto; margin: 0; }
body { font-family: 'Noto Sans Bengali', 'Courier New', monospace; width: 52mm; margin: 0 auto; padding: 3mm 0; color: #000; font-size: 13px; line-height: 1.35; }
.center { text-align: center; }
.bold { font-weight: 700; }
.large { font-size: 16px; }
.hr { border-top: 2px dashed #000; margin: 5px 0; }
.item { font-weight: 700; font-size: 14px; }
.addon { padding-left: 10px; font-size: 12px; }
</style>
</head>
<body>
<div class="center bold large">${escapeHtml(t('receipt.kitchen_token'))}</div>
<div class="center bold">${escapeHtml(token.invoiceNo)}</div>
<div class="center">${escapeHtml(token.when ?? '')}</div>
<div class="hr"></div>
<div class="bold">${escapeHtml(token.orderType ?? '')}${token.table ? ` - ${escapeHtml(token.table)}` : ''}</div>
<div class="hr"></div>
${lines}
<div class="hr"></div>
</body>
</html>`;
}
