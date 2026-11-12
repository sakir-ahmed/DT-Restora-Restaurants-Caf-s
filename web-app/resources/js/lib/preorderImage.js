const SERIF = 'Georgia, "Noto Serif Bengali", "Times New Roman", serif';
const WIDTH = 1080;
const PAD = 64;
const INNER = WIDTH - PAD * 2;
const LINE = 46;

function wrap(context, text, maxWidth) {
    const lines = [];
    let line = '';

    String(text).split(/\s+/).filter(Boolean).forEach((word) => {
        const next = line ? `${line} ${word}` : word;

        if (context.measureText(next).width > maxWidth && line) {
            lines.push(line);
            line = word;
        } else {
            line = next;
        }
    });

    if (line) {
        lines.push(line);
    }

    return lines;
}

/**
 * Draws a finished pre-order as a picture that can be sent in a chat: branch, pre-order number,
 * guest details, items, totals and the advance. The height follows the content; the width is fixed.
 * Returns a PNG blob.
 *
 * @param {{branchName: string, branchLine: string, number: string, labels: object, details: Array<[string, string]>, items: Array<{name: string, amount: string}>, totals: Array<{label: string, value: string, strong?: boolean}>, note: string, footer: string}} data
 */
export async function makePreorderImage(data) {
    if (document.fonts?.ready) {
        await document.fonts.ready;
    }

    const measure = document.createElement('canvas').getContext('2d');

    if (! measure) {
        throw new Error('Canvas is not available');
    }

    // First pass: measure the height of every block.
    measure.font = `500 30px ${SERIF}`;
    const detailLines = data.details.map(([label, value]) => ({ label, lines: wrap(measure, value, INNER * 0.6) }));
    const itemLines = data.items.map((item) => ({ ...item, lines: wrap(measure, item.name, INNER - 220) }));
    measure.font = `400 26px ${SERIF}`;
    const noteLines = data.note ? wrap(measure, data.note, INNER) : [];
    const footerLines = wrap(measure, data.footer, INNER);

    const detailsHeight = detailLines.reduce((sum, row) => sum + Math.max(1, row.lines.length) * 42 + 8, 0);
    const itemsHeight = itemLines.reduce((sum, row) => sum + row.lines.length * 40 + 24, 0);
    const totalsHeight = data.totals.length * 56;
    const notesHeight = noteLines.length ? noteLines.length * 36 + 40 : 0;
    const footerHeight = footerLines.length * 36 + 50;

    const height = 330 + 150 + detailsHeight + 70 + itemsHeight + 40 + totalsHeight + notesHeight + footerHeight;

    const canvas = document.createElement('canvas');
    canvas.width = WIDTH;
    canvas.height = height;

    const context = canvas.getContext('2d');

    context.fillStyle = '#fbf6ea';
    context.fillRect(0, 0, WIDTH, height);

    // Leather header
    const band = context.createLinearGradient(0, 0, 0, 220);
    band.addColorStop(0, '#3b2418');
    band.addColorStop(1, '#2c1810');
    context.fillStyle = band;
    context.fillRect(0, 0, WIDTH, 220);
    context.fillStyle = '#b8892f';
    context.fillRect(0, 212, WIDTH, 8);

    context.textAlign = 'center';
    context.fillStyle = '#d9b25a';
    context.font = `600 26px ${SERIF}`;
    context.fillText(data.labels.title.toUpperCase(), WIDTH / 2, 66);
    context.fillStyle = '#f4ead2';
    context.font = `700 56px ${SERIF}`;
    let branch = data.branchName;

    while (context.measureText(branch).width > INNER && branch.length > 4) {
        branch = branch.slice(0, -1);
    }

    context.fillText(branch === data.branchName ? branch : `${branch}...`, WIDTH / 2, 138);
    context.fillStyle = '#d9b25a';
    context.font = `400 24px ${SERIF}`;
    context.fillText(data.branchLine.slice(0, 80), WIDTH / 2, 184);

    // Number box
    let y = 256;
    context.fillStyle = '#fff7e0';
    context.strokeStyle = '#b8892f';
    context.lineWidth = 3;
    context.setLineDash([12, 8]);
    context.strokeRect(PAD, y, INNER, 150);
    context.setLineDash([]);
    context.fillStyle = '#6d5b45';
    context.font = `600 24px ${SERIF}`;
    context.fillText(data.labels.number.toUpperCase(), WIDTH / 2, y + 48);
    context.fillStyle = '#2b2118';
    context.font = `700 64px ${SERIF}`;
    context.fillText(data.number, WIDTH / 2, y + 116);

    // Details
    y += 150 + 52;
    context.textAlign = 'left';

    detailLines.forEach((row) => {
        context.font = `400 28px ${SERIF}`;
        context.fillStyle = '#6d5b45';
        context.fillText(row.label, PAD, y);
        context.font = `600 30px ${SERIF}`;
        context.fillStyle = '#2b2118';
        context.textAlign = 'right';
        (row.lines.length ? row.lines : ['']).forEach((text, index) => {
            context.fillText(text, WIDTH - PAD, y + index * 42);
        });
        context.textAlign = 'left';
        y += Math.max(1, row.lines.length) * 42 + 8;
    });

    // Items
    y += 18;
    context.fillStyle = '#b8892f';
    context.fillRect(PAD, y, INNER, 3);
    y += 52;

    itemLines.forEach((item) => {
        context.font = `600 31px ${SERIF}`;
        context.fillStyle = '#2b2118';
        context.textAlign = 'left';
        item.lines.forEach((text, index) => context.fillText(text, PAD, y + index * 40));
        context.textAlign = 'right';
        context.fillText(item.amount, WIDTH - PAD, y);
        const lastBaseline = y + (item.lines.length - 1) * 40;

        context.fillStyle = '#e4d6b3';
        context.fillRect(PAD, lastBaseline + 18, INNER, 2);
        y = lastBaseline + 64;
    });

    // Totals
    y += 24;

    data.totals.forEach((row) => {
        context.font = `${row.strong ? '700' : '400'} ${row.strong ? 36 : 30}px ${SERIF}`;
        context.fillStyle = row.strong ? '#2b2118' : '#6d5b45';
        context.textAlign = 'left';
        context.fillText(row.label, PAD, y);
        context.textAlign = 'right';
        context.fillText(row.value, WIDTH - PAD, y);
        y += 56;
    });

    // Note and footer
    context.textAlign = 'left';

    if (noteLines.length) {
        y += 6;
        context.font = `400 26px ${SERIF}`;
        context.fillStyle = '#6d5b45';
        noteLines.forEach((text, index) => context.fillText(text, PAD, y + index * 36));
        y += noteLines.length * 36 + 34;
    }

    context.fillStyle = '#e4d6b3';
    context.fillRect(PAD, y - 10, INNER, 2);
    context.textAlign = 'center';
    context.font = `italic 400 26px ${SERIF}`;
    context.fillStyle = '#6d5b45';
    footerLines.forEach((text, index) => context.fillText(text, WIDTH / 2, y + 40 + index * 36));

    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Could not make the image'))), 'image/png');
    });
}
