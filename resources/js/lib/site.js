import { currentLocale } from './i18n';

/**
 * Pick the Bangla value of a bilingual field when the page is in Bangla.
 */
export function localized(record, field) {
    if (! record) {
        return '';
    }

    return currentLocale() === 'bn' && record[`${field}_bn`] ? record[`${field}_bn`] : (record[field] ?? '');
}

/**
 * Public URL for an uploaded menu photo, or null when the dish has none.
 */
export function photoSrc(path) {
    if (! path) {
        return null;
    }

    if (/^https?:\/\//.test(path) || path.startsWith('/')) {
        return path;
    }

    return `/storage/${String(path).replace(/^\/+/, '')}`;
}

export function menuUrl(branchCode) {
    return `/menu/${String(branchCode).toLowerCase()}`;
}

export function itemUrl(branchCode, itemId) {
    return `/menu/${String(branchCode).toLowerCase()}/items/${itemId}`;
}

export function locationUrl(slug) {
    return `/locations/${slug}`;
}

export function directionsUrl(branch) {
    const query = branch.latitude && branch.longitude
        ? `${branch.latitude},${branch.longitude}`
        : [branch.name, branch.address, branch.city].filter(Boolean).join(', ');

    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
}

/**
 * Pick an illustration style for a dish from its category name.
 */
export function dishKind(categoryName = '') {
    const name = String(categoryName).toLowerCase();

    if (/coffee|tea|drink|juice|shake|beverage|latte|cafe|lassi/.test(name)) {
        return 'cup';
    }

    if (/burger|sandwich|wrap|fast|roll/.test(name)) {
        return 'burger';
    }

    if (/dessert|sweet|cake|ice|pastry|mishti/.test(name)) {
        return 'dessert';
    }

    return 'bowl';
}

/**
 * Calendar date in the visitor's language. The time zone is fixed so the
 * server render and the browser always print the same day.
 */
export function formatDay(iso) {
    if (! iso) {
        return '';
    }

    return new Intl.DateTimeFormat(currentLocale() === 'bn' ? 'bn-BD' : 'en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'Asia/Dhaka',
    }).format(new Date(iso));
}
