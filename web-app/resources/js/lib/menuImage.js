import { qrMatrix } from './qr';
import { book } from './bookColors.js';

const SERIF = 'Georgia, "Noto Serif Bengali", "Times New Roman", serif';

// A fixed 4:5 portrait picture: the size social apps and chats show without cropping.
const WIDTH = 1080;
const HEIGHT = 1350;
const PAD = 64;
const HEADER = 250;
const FOOTER = 250;
const GAP = 56;
const COLUMN = (WIDTH - PAD * 2 - GAP) / 2;
const BODY_TOP = HEADER + 44;
const BODY_HEIGHT = HEIGHT - HEADER - FOOTER - 88;
const HEAD_COST = 72;
const ROW_COST = 52;
const MAX_ROWS = 40;

const groupHeight = (group) => HEAD_COST + group.items.length * ROW_COST + 14;

/**
 * Take dishes round by round so every category shows, then place whole categories in two
 * columns, trimming the longest category until everything fits the page.
 */
function layoutRows(categories, name, price) {
    const queues = categories
        .map((category) => ({ title: name(category), items: (category.items ?? []).map((item) => ({ name: name(item), price: price(item) })) }))
        .filter((entry) => entry.items.length > 0);

    const groups = queues.map((entry) => ({ title: entry.title, items: [] }));
    let rows = 0;

    for (let round = 0; rows < MAX_ROWS; round += 1) {
        let added = false;

        queues.forEach((entry, index) => {
            if (rows < MAX_ROWS && entry.items[round]) {
                groups[index].items.push(entry.items[round]);
                rows += 1;
                added = true;
            }
        });

        if (! added) {
            break;
        }
    }

    let shown = groups.filter((group) => group.items.length > 0);

    for (;;) {
        let best = null;

        for (let split = 0; split <= shown.length; split += 1) {
            const left = shown.slice(0, split).reduce((sum, group) => sum + groupHeight(group), 0);
            const right = shown.slice(split).reduce((sum, group) => sum + groupHeight(group), 0);
            const tallest = Math.max(left, right);

            if (tallest <= BODY_HEIGHT && (best === null || tallest < best.tallest)) {
                best = { split, tallest };
            }
        }

        if (best !== null || shown.length === 0) {
            return best === null ? [[], []] : [shown.slice(0, best.split), shown.slice(best.split)];
        }

        // Too much for the page: drop a dish from the longest category.
        const longest = shown.reduce((top, group) => (group.items.length > top.items.length ? group : top), shown[0]);

        longest.items.pop();
        shown = shown.filter((group) => group.items.length > 0);
    }
}

function fit(context, text, maxWidth) {
    if (context.measureText(text).width <= maxWidth) {
        return text;
    }

    let result = text;

    while (result.length > 1 && context.measureText(`${result}...`).width > maxWidth) {
        result = result.slice(0, -1);
    }

    return `${result.trimEnd()}...`;
}

function drawColumn(context, groups, left) {
    let y = BODY_TOP;

    groups.forEach((group) => {
        context.textAlign = 'left';
        context.fillStyle = book.ink;
        context.font = `700 34px ${SERIF}`;
        context.fillText(fit(context, group.title, COLUMN), left, y + 34);
        context.fillStyle = book.accent;
        context.fillRect(left, y + 48, COLUMN, 3);
        y += HEAD_COST;

        group.items.forEach((item) => {
            context.font = `500 27px ${SERIF}`;
            context.fillStyle = book.ink;
            context.textAlign = 'right';
            const priceText = String(item.price);
            const priceWidth = context.measureText(priceText).width;
            context.fillText(priceText, left + COLUMN, y + 30);

            context.textAlign = 'left';
            const label = fit(context, item.name, COLUMN - priceWidth - 24);
            context.fillText(label, left, y + 30);

            const start = left + context.measureText(label).width + 10;
            const end = left + COLUMN - priceWidth - 10;
            context.fillStyle = book.inkSoft;

            for (let x = start; x < end; x += 9) {
                context.fillRect(x, y + 28, 3, 3);
            }

            y += ROW_COST;
        });

        y += 14;
    });
}

/**
 * Draws the menu book as a picture a guest can share in a chat: the restaurant name, a page of
 * dishes with prices and a QR code that opens the public book. Always 1080 x 1350. Returns a PNG blob.
 *
 * @param {{branchName: string, tagline: string, scanText: string, url: string, categories: Array, name: Function, price: Function}} options
 */
export async function makeMenuImage({ branchName, tagline, scanText, url, categories, name, price }) {
    if (document.fonts?.ready) {
        await document.fonts.ready;
    }

    const [leftGroups, rightGroups] = layoutRows(categories, name, price);

    const canvas = document.createElement('canvas');
    canvas.width = WIDTH;
    canvas.height = HEIGHT;

    const context = canvas.getContext('2d');

    if (! context) {
        throw new Error('Canvas is not available');
    }

    // Paper and leather cover band
    context.fillStyle = book.paper;
    context.fillRect(0, 0, WIDTH, HEIGHT);

    const band = context.createLinearGradient(0, 0, 0, HEADER);
    band.addColorStop(0, book.coverSoft);
    band.addColorStop(1, book.cover);
    context.fillStyle = band;
    context.fillRect(0, 0, WIDTH, HEADER);
    context.fillStyle = book.accent;
    context.fillRect(0, HEADER - 8, WIDTH, 8);

    context.textAlign = 'center';
    context.fillStyle = book.accentLight;
    context.font = `600 26px ${SERIF}`;
    context.fillText(tagline.toUpperCase(), WIDTH / 2, 70);

    context.fillStyle = book.coverInk;
    context.font = `700 62px ${SERIF}`;
    context.fillText(fit(context, branchName, WIDTH - PAD * 2), WIDTH / 2, 148);

    context.fillStyle = book.accentLight;
    context.fillRect(WIDTH / 2 - 60, 174, 120, 4);
    context.fillStyle = book.coverInk;
    context.font = `italic 400 36px ${SERIF}`;
    context.fillText('Menu', WIDTH / 2, 224);

    // Dishes in two columns
    drawColumn(context, leftGroups, PAD);
    drawColumn(context, rightGroups, PAD + COLUMN + GAP);

    // Footer with the QR code
    const footerTop = HEIGHT - FOOTER;
    context.fillStyle = book.cover;
    context.fillRect(0, footerTop, WIDTH, FOOTER);
    context.fillStyle = book.accent;
    context.fillRect(0, footerTop, WIDTH, 6);

    const { size, modules } = qrMatrix(url);
    const cell = Math.max(3, Math.floor(190 / (size + 2)));
    const box = (size + 2) * cell;
    const qrX = WIDTH - PAD - box;
    const qrY = footerTop + (FOOTER - box) / 2 + 3;

    context.fillStyle = '#ffffff';
    context.fillRect(qrX, qrY, box, box);
    context.fillStyle = '#1a1208';

    modules.forEach((row, rowIndex) => row.forEach((dark, columnIndex) => {
        if (dark) {
            context.fillRect(qrX + (columnIndex + 1) * cell, qrY + (rowIndex + 1) * cell, cell, cell);
        }
    }));

    const textWidth = qrX - PAD - 40;

    context.textAlign = 'left';
    context.fillStyle = book.coverInk;
    context.font = `700 36px ${SERIF}`;
    wrap(context, scanText, PAD, footerTop + 96, textWidth, 48);
    context.fillStyle = book.accentLight;
    context.font = `400 23px ${SERIF}`;
    context.fillText(fit(context, url.replace(/^https?:\/\//, ''), textWidth), PAD, footerTop + FOOTER - 52);

    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Could not make the image'))), 'image/png');
    });
}

function wrap(context, text, x, y, maxWidth, lineHeight) {
    const words = String(text).split(/\s+/);
    let line = '';
    let top = y;

    words.forEach((word) => {
        const next = line ? `${line} ${word}` : word;

        if (context.measureText(next).width > maxWidth && line) {
            context.fillText(line, x, top);
            line = word;
            top += lineHeight;
        } else {
            line = next;
        }
    });

    context.fillText(line, x, top);
}
