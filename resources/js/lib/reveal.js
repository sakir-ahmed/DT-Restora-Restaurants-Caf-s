/**
 * One shared scroll check for every element waiting to fade in. Anything
 * at or above the bottom edge of the screen is revealed, so content that is
 * jumped past (anchor links, restored scroll position, End key) never stays
 * hidden.
 */
const waiting = new Map();
let scheduled = false;
let listening = false;

function check() {
    scheduled = false;

    const limit = window.innerHeight * 0.92;

    waiting.forEach((reveal, node) => {
        if (node.getBoundingClientRect().top < limit) {
            waiting.delete(node);
            reveal();
        }
    });

    if (waiting.size === 0 && listening) {
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
        listening = false;
    }
}

function schedule() {
    if (! scheduled) {
        scheduled = true;
        requestAnimationFrame(check);
    }
}

export function revealWhenVisible(node, reveal) {
    waiting.set(node, reveal);

    if (! listening) {
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule, { passive: true });
        listening = true;
    }

    schedule();

    return () => waiting.delete(node);
}
