/**
 * The brand colours of the menu book, for drawing on a canvas. They are the same
 * --book-* tokens the pages use (see resources/css/app.css), resolved to a real
 * colour at the moment they are read, so the shared pictures follow the brand
 * of the install and the current light or dark mode.
 */
const TOKENS = {
    paper: '--book-paper',
    page: '--book-page',
    edge: '--book-paper-edge',
    ink: '--book-ink',
    inkSoft: '--book-ink-soft',
    accent: '--book-accent',
    accentLight: '--book-accent-light',
    cover: '--book-cover',
    coverSoft: '--book-cover-soft',
    coverInk: '--book-cover-ink',
    tint: '--book-tint',
};

function resolve(token) {
    const probe = document.createElement('span');

    probe.style.color = `var(${token})`;
    probe.style.display = 'none';
    document.body.appendChild(probe);

    const value = getComputedStyle(probe).color;

    probe.remove();

    return value;
}

export const book = new Proxy({}, {
    get: (_, key) => (key in TOKENS ? resolve(TOKENS[key]) : undefined),
});
