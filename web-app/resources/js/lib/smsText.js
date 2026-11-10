/**
 * Length arithmetic for SMS: 160 characters in one part for the GSM 7-bit
 * alphabet (153 per part when split), 70 (67) once any other character such
 * as Bangla is used. Mirrors App\Services\Sms\SmsText (PHP).
 */
const GSM_BASIC = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
const GSM_EXTENDED = '^{}\\[~]|€\f';

export const MAX_PARTS = 4;

function gsmUnits(text) {
    let units = 0;

    for (const character of text) {
        if (GSM_BASIC.includes(character)) {
            units += 1;
        } else if (GSM_EXTENDED.includes(character)) {
            units += 2;
        } else {
            return null;
        }
    }

    return units;
}

export function analyzeSms(text) {
    const value = String(text ?? '');
    const chars = [...value].length;
    const words = (value.match(/\S+/gu) ?? []).length;
    const gsm = gsmUnits(value);
    const single = gsm !== null ? 160 : 70;
    const multi = gsm !== null ? 153 : 67;
    // String length counts UTF-16 code units, which is how Unicode SMS is measured.
    const units = gsm !== null ? gsm : value.length;
    const segments = units === 0 ? 0 : (units <= single ? 1 : Math.ceil(units / multi));

    return {
        chars,
        words,
        encoding: gsm !== null ? 'gsm7' : 'unicode',
        segments,
        perSegment: segments > 1 ? multi : single,
        units,
    };
}

/** Replace {name} with a value; unknown names become empty, as on the server. */
export function fillTemplate(body, values) {
    return String(body ?? '').replace(/\{(\w+)\}/g, (match, name) => String(values[name] ?? ''));
}
