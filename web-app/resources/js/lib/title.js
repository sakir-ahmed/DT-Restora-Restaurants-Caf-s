/**
 * Document title: "Page - Brand", without repeating the brand when the page
 * title already carries it (the home and about pages do). The brand comes
 * from the shared `brand` prop, so it follows the language and the mode.
 */
export function pageTitle(title, page) {
    const props = page?.props ?? {};
    const brand = props.brand?.name ?? props.appName ?? import.meta.env.VITE_APP_NAME ?? '';

    if (! title) {
        return brand;
    }

    // Inertia hands the callback an HTML-escaped title, so "'" arrives as &#039;.
    const plain = title.replace(/&#0?39;|&apos;/g, "'");
    const names = [brand, props.appName].filter(Boolean);

    return names.some((name) => plain.includes(name)) ? title : `${title} - ${brand}`;
}
