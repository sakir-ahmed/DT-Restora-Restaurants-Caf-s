import { currentLocale } from './i18n';

const brandNames = [import.meta.env.VITE_APP_NAME ?? "Arman's Kitchen", "Arman's Kitchen", 'আরমানস কিচেন'];

/**
 * Document title: "Page - Brand", without repeating the brand when the page
 * title already carries it (the home and about pages do).
 */
export function pageTitle(title) {
    const brand = currentLocale() === 'bn' ? brandNames[2] : brandNames[0];

    if (! title) {
        return brand;
    }

    // Inertia hands the callback an HTML-escaped title, so "'" arrives as &#039;.
    const plain = title.replace(/&#0?39;|&apos;/g, "'");

    return brandNames.some((name) => plain.includes(name)) ? title : `${title} - ${brand}`;
}
