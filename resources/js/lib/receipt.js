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

/**
 * 80mm receipt for a sale that has not reached the server yet.
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
<style>
@page { size: 80mm auto; margin: 0; }
body { font-family: 'Noto Sans Bengali', 'Courier New', monospace; width: 72mm; margin: 0 auto; padding: 4mm 0; color: #000; font-size: 11px; line-height: 1.45; }
.center { text-align: center; }
.bold { font-weight: 700; }
.large { font-size: 13px; }
.hr { border-top: 1px dashed #000; margin: 6px 0; }
table { width: 100%; border-collapse: collapse; }
td { padding: 1px 0; vertical-align: top; }
td.right { text-align: right; }
</style>
</head>
<body>
<div class="center bold large">${escapeHtml(branch.name ?? '')}</div>
${branch.address ? `<div class="center">${escapeHtml(branch.address)}</div>` : ''}
${branch.phone ? `<div class="center">${escapeHtml(t('receipt.phone'))}: ${escapeHtml(branch.phone)}</div>` : ''}
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
<tr><td class="bold large">${escapeHtml(t('receipt.grand_total'))}</td><td class="right bold large">Tk ${money(receipt.total)}</td></tr>
${change}
</table>
<div class="hr"></div>
${branch.receipt_footer ? `<div class="center">${escapeHtml(branch.receipt_footer)}</div>` : ''}
<div class="center">${escapeHtml(t('receipt.thank_you'))}</div>
<div class="center">${escapeHtml(t('sync.queued'))}</div>
</body>
</html>`;
}
