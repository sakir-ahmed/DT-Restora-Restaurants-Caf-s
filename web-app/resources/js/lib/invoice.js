export const DEFAULT_INVOICE_PATTERN = '{BRANCH}-{TERMINAL}-{YYYY}{MM}{DD}-{TIME}-{RAND}';

/** No I, L, O or U, so a printed number is never misread. Matches the server. */
const RANDOM_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
const RANDOM_LENGTH = 4;

function escapeRegex(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\/]/g, '\\$&');
}

/**
 * True when the pattern numbers invoices with a counter (the old style, which
 * needs the server's sequence). Random patterns work fully offline.
 */
export function patternUsesSequence(settings) {
    return (settings?.invoice_pattern || DEFAULT_INVOICE_PATTERN).includes('{SEQ}');
}

/**
 * A short random code from the device's secure random source. Two terminals
 * that never talk to each other still get different codes.
 */
export function randomInvoiceCode(length = RANDOM_LENGTH) {
    const bytes = crypto.getRandomValues(new Uint8Array(length));

    return Array.from(bytes, (byte) => RANDOM_ALPHABET[byte % RANDOM_ALPHABET.length]).join('');
}

/**
 * Build an invoice number from the branch pattern. `day` is YYYYMMDD,
 * `time` is HHMMSS, `random` is the {RAND} code and `number` the {SEQ} counter.
 */
export function formatInvoice(settings, { branchCode, terminal, day, time = '000000', random = '', number = 0 }) {
    const pattern = settings?.invoice_pattern || DEFAULT_INVOICE_PATTERN;
    const digits = Math.max(1, Number(settings?.invoice_seq_digits) || 4);
    const values = {
        BRANCH: branchCode,
        TERMINAL: String(terminal).toUpperCase(),
        YYYY: day.slice(0, 4),
        YY: day.slice(2, 4),
        MM: day.slice(4, 6),
        DD: day.slice(6, 8),
        TIME: time,
        RAND: random,
        SEQ: String(number).padStart(digits, '0'),
    };

    return pattern.replace(/\{([A-Z]+)\}/g, (match, token) => values[token] ?? match);
}

/**
 * Read the sequence back out of an invoice number made with a counter pattern.
 * Random patterns have no sequence and return null.
 */
export function parseInvoiceSequence(settings, invoiceNo, branchCode, terminal) {
    const pattern = settings?.invoice_pattern || DEFAULT_INVOICE_PATTERN;

    if (! pattern.includes('{SEQ}')) {
        return null;
    }

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
                case '{TIME}': return '\\d{6}';
                case '{RAND}': return `[${RANDOM_ALPHABET}]{${RANDOM_LENGTH}}`;
                case '{SEQ}': return '(\\d+)';
                default: return escapeRegex(part);
            }
        })
        .join('');
    const match = new RegExp(`^${source}$`).exec(String(invoiceNo));

    return match ? Number(match[1]) : null;
}
