import { currentLocale, t } from './i18n';

const BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

export function toBnDigits(text) {
    return String(text).replace(/\d/g, (digit) => BN_DIGITS[Number(digit)]);
}

/**
 * Digits in the visitor's language: Bangla digits in bn, unchanged otherwise.
 */
export function localDigits(text) {
    return localize(text);
}

function localize(text) {
    return currentLocale() === 'bn' ? toBnDigits(text) : String(text);
}

/**
 * Bangladesh-style digit grouping: last three digits, then pairs.
 * 1234567 -> 12,34,567
 */
export function groupBD(intPart) {
    if (intPart.length <= 3) {
        return intPart;
    }

    const last3 = intPart.slice(-3);
    const rest = intPart.slice(0, -3);

    return rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3;
}

/**
 * Format money in BDT. Returns "1,250.00" (or Bangla digits in bn).
 */
export function formatMoney(value, { decimals = 2, symbol = false, compact = false } = {}) {
    const amount = Number(value ?? 0);

    if (!Number.isFinite(amount)) {
        return localize('0' + (decimals > 0 ? '.' + '0'.repeat(decimals) : ''));
    }

    const negative = amount < 0;
    const abs = Math.abs(amount);

    let formatted;

    if (compact && abs >= 10000000) {
        formatted = trimTrailingZero((abs / 10000000).toFixed(2)) + (currentLocale() === 'bn' ? ' কোটি' : 'Cr');
    } else if (compact && abs >= 100000) {
        formatted = trimTrailingZero((abs / 100000).toFixed(2)) + (currentLocale() === 'bn' ? ' লাখ' : 'L');
    } else if (compact && abs >= 1000) {
        formatted = trimTrailingZero((abs / 1000).toFixed(1)) + 'K';
    } else {
        const fixed = abs.toFixed(decimals);
        const [intPart, decPart] = fixed.split('.');
        formatted = groupBD(intPart) + (decPart ? '.' + decPart : '');
    }

    const sign = negative ? '-' : '';
    const taka = symbol ? '\u09F3' : '';

    return localize(sign + taka + formatted);
}

function trimTrailingZero(text) {
    return text.replace(/\.?0+$/, '');
}

/**
 * Format a number with BD grouping and optional decimals.
 */
export function formatNumber(value, decimals = 0) {
    const amount = Number(value ?? 0);

    if (!Number.isFinite(amount)) {
        return localize('0');
    }

    const fixed = Math.abs(amount).toFixed(decimals);
    const [intPart, decPart] = fixed.split('.');

    return localize((amount < 0 ? '-' : '') + groupBD(intPart) + (decPart ? '.' + decPart : ''));
}

export function formatPercent(value, decimals = 1) {
    return localize(`${Number(value ?? 0).toFixed(decimals)}%`);
}

const MONTHS_EN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const MONTHS_BN = ['জানু', 'ফেব', 'মার্চ', 'এপ্রি', 'মে', 'জুন', 'জুল', 'আগ', 'সেপ', 'অক্টো', 'নভে', 'ডিসে'];

/**
 * "15 Oct 2026, 2:30 PM" in Asia/Dhaka style formatting.
 */
export function formatDateTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const locale = currentLocale();
    const months = locale === 'bn' ? MONTHS_BN : MONTHS_EN;

    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear();

    let hours = date.getHours();
    const minutes = date.getMinutes().toString().padStart(2, '0');

    const isAm = hours < 12;
    hours = hours % 12 || 12;

    const datePart = locale === 'bn'
        ? `${toBnDigits(day)} ${month} ${toBnDigits(year)}`
        : `${day} ${month} ${year}`;

    return localize(`${datePart}, ${hours}:${minutes} ${isAm ? 'AM' : 'PM'}`);
}

export function formatDate(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const locale = currentLocale();
    const months = locale === 'bn' ? MONTHS_BN : MONTHS_EN;
    const part = `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;

    return localize(part);
}

/**
 * Relative time like "5 min ago".
 */
export function formatRelative(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (seconds < 60) {
        return t('common.relative.now');
    }

    if (minutes < 60) {
        return t('common.relative.min', { n: localize(String(minutes)) });
    }

    if (hours < 24) {
        return t('common.relative.hour', { n: localize(String(hours)) });
    }

    return t('common.relative.day', { n: localize(String(days)) });
}
