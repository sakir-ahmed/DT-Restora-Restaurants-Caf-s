import { reactive, watch } from 'vue';

const STORAGE_KEY = 'web-cart';

function load() {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
    } catch {
        return [];
    }
}

/**
 * Client-side cart persisted in localStorage. The server re-prices
 * everything on checkout; this only holds the selection.
 */
export const cart = reactive({ lines: load() });

watch(() => cart.lines, (lines) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
}, { deep: true });

export function addToCart(item, variant = null, addOns = []) {
    const variantId = variant?.id ?? null;
    const addOnKey = addOns.map((addOn) => addOn.id).sort().join('-');

    const existing = cart.lines.find((line) => line.itemId === item.id
        && (line.variantId ?? null) === variantId
        && (line.addOns.map((a) => a.id).sort().join('-') ?? '') === addOnKey);

    if (existing) {
        existing.quantity += 1;
    } else {
        cart.lines.push({
            itemId: item.id,
            name: item.name,
            variantId,
            variantName: variant?.name ?? null,
            unitPrice: Number(variant?.price ?? item.base_price),
            addOns: addOns.map((addOn) => ({ id: addOn.id, name: addOn.name, price: Number(addOn.price) })),
            quantity: 1,
        });
    }
}

export function setQuantity(index, quantity) {
    if (quantity <= 0) {
        cart.lines.splice(index, 1);
    } else {
        cart.lines[index].quantity = quantity;
    }
}

export function clearCart() {
    cart.lines.splice(0);
}

export function cartCount() {
    return cart.lines.reduce((sum, line) => sum + line.quantity, 0);
}

export function cartSubtotal() {
    return cart.lines.reduce((sum, line) => sum + (line.unitPrice + line.addOns.reduce((s, a) => s + a.price, 0)) * line.quantity, 0);
}
