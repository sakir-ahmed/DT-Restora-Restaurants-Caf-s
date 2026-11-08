/**
 * QR code generator (byte mode, error correction level M, versions 1 to 10).
 * Plenty for a table link of up to 213 characters. Written out here so the
 * app needs no extra package and the QR codes never leave the browser.
 */

// Version -> [error correction codewords per block, [[blocks, data codewords per block], ...]]
const BLOCKS = {
    1: [10, [[1, 16]]],
    2: [16, [[1, 28]]],
    3: [26, [[1, 44]]],
    4: [18, [[2, 32]]],
    5: [24, [[2, 43]]],
    6: [16, [[4, 27]]],
    7: [18, [[4, 31]]],
    8: [22, [[2, 38], [2, 39]]],
    9: [22, [[3, 36], [2, 37]]],
    10: [26, [[4, 43], [1, 44]]],
};

const ALIGNMENT = {
    1: [], 2: [6, 18], 3: [6, 22], 4: [6, 26], 5: [6, 30], 6: [6, 34], 7: [6, 22, 38], 8: [6, 24, 42], 9: [6, 26, 46], 10: [6, 28, 50],
};

const EXP = new Uint8Array(512);
const LOG = new Uint8Array(256);

(() => {
    let value = 1;

    for (let index = 0; index < 255; index += 1) {
        EXP[index] = value;
        LOG[value] = index;
        value <<= 1;

        if (value & 0x100) {
            value ^= 0x11d;
        }
    }

    for (let index = 255; index < 512; index += 1) {
        EXP[index] = EXP[index - 255];
    }
})();

function multiply(left, right) {
    return left === 0 || right === 0 ? 0 : EXP[LOG[left] + LOG[right]];
}

function generator(degree) {
    let poly = [1];

    for (let index = 0; index < degree; index += 1) {
        const next = new Array(poly.length + 1).fill(0);

        poly.forEach((coefficient, position) => {
            next[position] ^= coefficient;
            next[position + 1] ^= multiply(coefficient, EXP[index]);
        });
        poly = next;
    }

    return poly;
}

function reedSolomon(data, degree) {
    const poly = generator(degree);
    const result = new Array(degree).fill(0);

    data.forEach((byte) => {
        const factor = byte ^ result.shift();

        result.push(0);
        poly.slice(1).forEach((coefficient, position) => {
            result[position] ^= multiply(coefficient, factor);
        });
    });

    return result;
}

function dataCapacity(version) {
    const [, groups] = BLOCKS[version];

    return groups.reduce((sum, [count, size]) => sum + count * size, 0);
}

function toBytes(text) {
    return [...new TextEncoder().encode(text)];
}

function buildCodewords(bytes, version) {
    const capacityBits = dataCapacity(version) * 8;
    const bits = [];
    const push = (value, length) => {
        for (let position = length - 1; position >= 0; position -= 1) {
            bits.push((value >>> position) & 1);
        }
    };

    push(0b0100, 4);
    push(bytes.length, version < 10 ? 8 : 16);
    bytes.forEach((byte) => push(byte, 8));
    push(0, Math.min(4, capacityBits - bits.length));

    while (bits.length % 8 !== 0) {
        bits.push(0);
    }

    const data = [];

    for (let index = 0; index < bits.length; index += 8) {
        data.push(parseInt(bits.slice(index, index + 8).join(''), 2));
    }

    for (let pad = 0xec; data.length < dataCapacity(version); pad ^= 0xec ^ 0x11) {
        data.push(pad);
    }

    const [ecPerBlock, groups] = BLOCKS[version];
    const blocks = [];
    let offset = 0;

    groups.forEach(([count, size]) => {
        for (let index = 0; index < count; index += 1) {
            const chunk = data.slice(offset, offset + size);

            offset += size;
            blocks.push({ data: chunk, ec: reedSolomon(chunk, ecPerBlock) });
        }
    });

    const longest = Math.max(...blocks.map((block) => block.data.length));
    const result = [];

    for (let index = 0; index < longest; index += 1) {
        blocks.forEach((block) => {
            if (index < block.data.length) {
                result.push(block.data[index]);
            }
        });
    }

    for (let index = 0; index < ecPerBlock; index += 1) {
        blocks.forEach((block) => result.push(block.ec[index]));
    }

    return result;
}

function bit(value, index) {
    return ((value >>> index) & 1) !== 0;
}

function emptyMatrix(size) {
    return Array.from({ length: size }, () => new Array(size).fill(false));
}

const MASKS = [
    (x, y) => (x + y) % 2 === 0,
    (x, y) => y % 2 === 0,
    (x) => x % 3 === 0,
    (x, y) => (x + y) % 3 === 0,
    (x, y) => (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0,
    (x, y) => ((x * y) % 2) + ((x * y) % 3) === 0,
    (x, y) => (((x * y) % 2) + ((x * y) % 3)) % 2 === 0,
    (x, y) => (((x + y) % 2) + ((x * y) % 3)) % 2 === 0,
];

function penalty(modules) {
    const size = modules.length;
    let score = 0;
    const lines = [];

    for (let index = 0; index < size; index += 1) {
        lines.push(modules[index]);
        lines.push(modules.map((row) => row[index]));
    }

    lines.forEach((line) => {
        let run = 1;

        for (let index = 1; index <= size; index += 1) {
            if (index < size && line[index] === line[index - 1]) {
                run += 1;
            } else {
                if (run >= 5) {
                    score += run - 2;
                }

                run = 1;
            }
        }

        const text = line.map((dark) => (dark ? '1' : '0')).join('');

        ['10111010000', '00001011101'].forEach((pattern) => {
            let from = text.indexOf(pattern);

            while (from !== -1) {
                score += 40;
                from = text.indexOf(pattern, from + 1);
            }
        });
    });

    for (let y = 0; y < size - 1; y += 1) {
        for (let x = 0; x < size - 1; x += 1) {
            const color = modules[y][x];

            if (color === modules[y][x + 1] && color === modules[y + 1][x] && color === modules[y + 1][x + 1]) {
                score += 3;
            }
        }
    }

    const dark = modules.flat().filter(Boolean).length;

    score += Math.floor(Math.abs((dark * 20) / (size * size) - 10)) * 10;

    return score;
}

/**
 * The QR matrix for a text. `modules[y][x]` is true for a dark module.
 */
export function qrMatrix(text) {
    const bytes = toBytes(text);
    const version = Object.keys(BLOCKS).map(Number).find((candidate) => {
        const overhead = candidate < 10 ? 12 : 20;

        return bytes.length * 8 + overhead <= dataCapacity(candidate) * 8;
    });

    if (version === undefined) {
        throw new Error('QR text is too long');
    }

    const size = 17 + 4 * version;
    const modules = emptyMatrix(size);
    const reserved = emptyMatrix(size);
    const setFunction = (x, y, dark) => {
        if (x >= 0 && x < size && y >= 0 && y < size) {
            modules[y][x] = dark;
            reserved[y][x] = true;
        }
    };

    for (let index = 0; index < size; index += 1) {
        setFunction(6, index, index % 2 === 0);
        setFunction(index, 6, index % 2 === 0);
    }

    [[3, 3], [size - 4, 3], [3, size - 4]].forEach(([cx, cy]) => {
        for (let dy = -4; dy <= 4; dy += 1) {
            for (let dx = -4; dx <= 4; dx += 1) {
                const distance = Math.max(Math.abs(dx), Math.abs(dy));

                setFunction(cx + dx, cy + dy, distance !== 2 && distance !== 4);
            }
        }
    });

    const positions = ALIGNMENT[version];

    positions.forEach((cy, row) => {
        positions.forEach((cx, column) => {
            const onFinder = (row === 0 && column === 0) || (row === 0 && column === positions.length - 1) || (row === positions.length - 1 && column === 0);

            if (onFinder) {
                return;
            }

            for (let dy = -2; dy <= 2; dy += 1) {
                for (let dx = -2; dx <= 2; dx += 1) {
                    setFunction(cx + dx, cy + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
                }
            }
        });
    });

    const drawFormat = (mask) => {
        const data = (0 << 3) | mask;
        let remainder = data;

        for (let index = 0; index < 10; index += 1) {
            remainder = (remainder << 1) ^ ((remainder >>> 9) * 0x537);
        }

        const formatBits = ((data << 10) | remainder) ^ 0x5412;

        for (let index = 0; index <= 5; index += 1) {
            setFunction(8, index, bit(formatBits, index));
        }

        setFunction(8, 7, bit(formatBits, 6));
        setFunction(8, 8, bit(formatBits, 7));
        setFunction(7, 8, bit(formatBits, 8));

        for (let index = 9; index < 15; index += 1) {
            setFunction(14 - index, 8, bit(formatBits, index));
        }

        for (let index = 0; index < 8; index += 1) {
            setFunction(size - 1 - index, 8, bit(formatBits, index));
        }

        for (let index = 8; index < 15; index += 1) {
            setFunction(8, size - 15 + index, bit(formatBits, index));
        }

        setFunction(8, size - 8, true);
    };

    drawFormat(0);

    if (version >= 7) {
        let remainder = version;

        for (let index = 0; index < 12; index += 1) {
            remainder = (remainder << 1) ^ ((remainder >>> 11) * 0x1f25);
        }

        const versionBits = (version << 12) | remainder;

        for (let index = 0; index < 18; index += 1) {
            const dark = bit(versionBits, index);
            const a = size - 11 + (index % 3);
            const b = Math.floor(index / 3);

            setFunction(a, b, dark);
            setFunction(b, a, dark);
        }
    }

    const codewords = buildCodewords(bytes, version);
    const totalBits = codewords.length * 8;
    let written = 0;

    for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) {
            right = 5;
        }

        for (let vertical = 0; vertical < size; vertical += 1) {
            for (let offset = 0; offset < 2; offset += 1) {
                const x = right - offset;
                const upward = ((right + 1) & 2) === 0;
                const y = upward ? size - 1 - vertical : vertical;

                if (! reserved[y][x] && written < totalBits) {
                    modules[y][x] = bit(codewords[written >>> 3], 7 - (written & 7));
                    written += 1;
                }
            }
        }
    }

    const apply = (mask) => modules.map((row, y) => row.map((dark, x) => (reserved[y][x] ? dark : dark !== MASKS[mask](x, y))));

    let best = 0;
    let bestScore = Infinity;

    for (let mask = 0; mask < 8; mask += 1) {
        const candidate = apply(mask);
        const score = penalty(candidate);

        if (score < bestScore) {
            best = mask;
            bestScore = score;
        }
    }

    const masked = apply(best);

    modules.forEach((row, y) => row.forEach((_, x) => {
        modules[y][x] = masked[y][x];
    }));
    drawFormat(best);

    return { size, version, modules };
}

/**
 * SVG path data for a QR code, one unit per module with a quiet zone around it.
 */
export function qrPath(text, border = 4) {
    const { size, modules } = qrMatrix(text);
    const parts = [];

    modules.forEach((row, y) => row.forEach((dark, x) => {
        if (dark) {
            parts.push(`M${x + border},${y + border}h1v1h-1z`);
        }
    }));

    return { path: parts.join(''), viewBox: size + border * 2 };
}
