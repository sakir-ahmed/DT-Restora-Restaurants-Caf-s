const brandNames = [import.meta.env.VITE_APP_NAME ?? "Arman's Kitchen", "Arman's Kitchen", 'আরমানস কিচেন'];

/**
 * Document title: "Page - Brand", without repeating the brand when the page
 * title already carries it (the home and about pages do).
 */
export function pageTitle(title) {
    const brand = brandNames[0];

    if (! title) {
        return brand;
    }

    return brandNames.some((name) => title.includes(name)) ? title : `${title} - ${brand}`;
}
