/**
 * Numeric text input: behaves like a plain text box that only accepts digits
 * and one decimal point (and a leading minus when the field allows negatives).
 * A field that holds a zero such as 0 or 0.00 is selected on focus so the first
 * keystroke replaces it instead of landing after it.
 */
const ZERO_LIKE = /^0*\.?0*$/;

export function sanitizeNumeric(value, allowNegative = false) {
    const negative = allowNegative && value.trimStart().startsWith('-');
    const cleaned = value.replace(/[^0-9.]/g, '');
    const [whole, ...rest] = cleaned.split('.');
    const body = rest.length ? `${whole}.${rest.join('')}` : whole;

    return negative ? `-${body}` : body;
}

export function selectIfZero(input) {
    if (ZERO_LIKE.test(input.value.trim()) && input.value !== '') {
        // Wait one tick: the browser places the caret after the focus event.
        setTimeout(() => input.select(), 0);
    }
}

function allowsNegative(el) {
    const min = el.getAttribute('min');

    return min === null || Number(min) < 0;
}

export const vNumeric = {
    created(el) {
        el.addEventListener('input', () => {
            const before = el.value;
            const after = sanitizeNumeric(before, allowsNegative(el));

            if (before !== after) {
                const caret = (el.selectionStart ?? before.length) - (before.length - after.length);

                el.value = after;
                el.setSelectionRange?.(Math.max(0, caret), Math.max(0, caret));
            }
        }, true);

        el.addEventListener('focus', () => selectIfZero(el));
    },
};
