/**
 * Helpers that show how units relate: "1 kg = 1,000 g", "1 box = 12 pcs".
 * Within a type (weight, volume, count) every unit is described against one
 * reference unit (g, ml, pcs), the unit people count the small things in.
 */
export function formatQuantity(value) {
    return Number(Number(value).toFixed(6)).toLocaleString('en-US', { maximumFractionDigits: 6 });
}

/** The unit everything of a kind is described against: g, ml or pcs. */
const REFERENCE_SYMBOL = { weight: 'g', volume: 'ml', count: 'pcs' };

export function referenceUnit(units, type) {
    const ofType = units.filter((unit) => unit.type === type);

    return ofType.find((unit) => unit.symbol === REFERENCE_SYMBOL[type])
        ?? ofType.sort((a, b) => Number(a.factor) - Number(b.factor) || a.id - b.id)[0]
        ?? null;
}

/** How many reference units one of this unit holds. */
export function inReference(unit, units) {
    const reference = referenceUnit(units, unit.type);

    return reference ? Number(unit.factor) / Number(reference.factor) : Number(unit.factor);
}

/** "1 box = 12 pcs", or null for the reference unit itself. */
export function relationText(unit, units) {
    const reference = referenceUnit(units, unit.type);

    if (! reference || reference.id === unit.id) {
        return null;
    }

    return `1 ${unit.symbol} = ${formatQuantity(inReference(unit, units))} ${reference.symbol}`;
}

/** Label for a unit in a picker: "box (12 pcs)" so the size is visible at a glance. */
export function unitLabel(unit, units) {
    const reference = referenceUnit(units, unit.type);

    if (! reference || reference.id === unit.id) {
        return unit.symbol;
    }

    return `${unit.symbol} (${formatQuantity(inReference(unit, units))} ${reference.symbol})`;
}
