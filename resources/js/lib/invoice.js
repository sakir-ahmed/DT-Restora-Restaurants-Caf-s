export const DEFAULT_INVOICE_PATTERN = '{BRANCH}-{TERMINAL}-{YYYY}{MM}{DD}-{SEQ}';

function escapeRegex(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\/]/g, '\\$&');
}

/**
 * Build an invoice number from the branch pattern. `day` is YYYYMMDD.
 */
export function formatInvoice(settings, { branchCode, terminal, day, number }) {
    const pattern = settings?.invoice_pattern || DEFAULT_INVOICE_PATTERN;
    const digits = Math.max(1, Number(settings?.invoice_seq_digits) || 4);
    const values = {
        BRANCH: branchCode,
        TERMINAL: String(terminal).toUpperCase(),
        YYYY: day.slice(0, 4),
        YY: day.slice(2, 4),
        MM: day.slice(4, 6),
        DD: day.slice(6, 8),
        SEQ: String(number).padStart(digits, '0'),
    };

    return pattern.replace(/\{([A-Z]+)\}/g, (match, token) => values[token] ?? match);
}

/**
 * Read the sequence back out of an invoice number made with the pattern.
 */
export function parseInvoiceSequence(settings, invoiceNo, branchCode, terminal) {
    const pattern = settings?.invoice_pattern || DEFAULT_INVOICE_PATTERN;
    const source = pattern
        .split(/(\{[A-Z]+\})/)
        .filter((part) => part !== '')
        .map((part) => {
            switch (part) {
                case '{BRANCH}': return escapeRegex(branchCode);
                case '{TERMINAL}': return escapeRegex(String(terminal).toUpperCase());
                case '{YYYY}': return '\\d{4}';
                case '{YY}':
                case '{MM}':
                case '{DD}': return '\\d{2}';
                case '{SEQ}': return '(\\d+)';
                default: return escapeRegex(part);
            }
        })
        .join('');
    const match = new RegExp(`^${source}$`).exec(String(invoiceNo));

    return match ? Number(match[1]) : null;
}
