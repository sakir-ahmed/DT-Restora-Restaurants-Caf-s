import Dexie from 'dexie';
import { formatInvoice, patternUsesSequence, randomInvoiceCode } from '@/lib/invoice';

const db = new Dexie('dt-restora-pos');

db.version(1).stores({
    // Reference data cache
    categories: 'id, sort_order',
    tables: 'id, name',
    meta: 'key',
    // Local outbox: every write gets a ULID client_id
    outbox: 'client_id, type, created_at_device',
    // Local copies of orders created offline
    orders: 'local_id, client_id, synced',
});

/**
 * ULID-ish unique id generator (sortable, 26 chars).
 */
export function newClientId() {
    const time = Date.now().toString(36).padStart(10, '0');
    const rand = Array.from(crypto.getRandomValues(new Uint8Array(10)))
        .map((b) => b.toString(36).padStart(2, '0'))
        .join('')
        .slice(0, 16);

    return time + rand;
}

export async function cacheBootstrap(data) {
    await db.transaction('rw', db.categories, db.tables, db.meta, async () => {
        await db.categories.clear();
        await db.categories.bulkPut(data.categories ?? []);

        await db.tables.clear();
        await db.tables.bulkPut(data.tables ?? []);

        await db.meta.put({ key: 'bootstrap', value: { branch: data.branch, at: Date.now() } });
        await db.meta.put({ key: 'data_version', value: data.data_version });
    });
}

export async function getDataVersion() {
    const row = await db.meta.get('data_version');

    return row?.value ?? 0;
}

export async function getCachedMenu() {
    const categories = await db.categories.toArray();
    const tables = await db.tables.toArray();
    const meta = await db.meta.get('bootstrap');

    return { categories, tables, branch: meta?.value?.branch };
}

function deviceDay(date = new Date()) {
    return new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(date).replaceAll('-', '');
}

function invoiceCursorKey(branchCode, terminal, day) {
    return `invoice:${branchCode}:${String(terminal).toUpperCase()}:${day}`;
}

/**
 * Remember the server's last invoice number so the next offline
 * receipt continues that terminal's series.
 */
export async function rememberInvoiceCursor(branchCode, terminal, lastNumber, forDate) {
    const day = String(forDate ?? '').replaceAll('-', '').slice(0, 8) || deviceDay();

    if (day !== deviceDay()) {
        return;
    }

    const key = invoiceCursorKey(branchCode, terminal, day);
    const current = Number((await db.meta.get(key))?.value ?? 0);

    await db.meta.put({ key, value: Math.max(current, Number(lastNumber) || 0) });
}

/**
 * A stable id for this browser, made once and kept in IndexedDB. It travels with
 * every offline order so the back office can tell which terminal made it.
 */
export async function getDeviceId() {
    const row = await db.meta.get('device_id');

    if (row?.value) {
        return row.value;
    }

    const id = crypto.randomUUID ? crypto.randomUUID() : newClientId();

    await db.meta.put({ key: 'device_id', value: id });

    return id;
}

function deviceTime(date = new Date()) {
    const pad = (value) => String(value).padStart(2, '0');

    return `${pad(date.getHours())}${pad(date.getMinutes())}${pad(date.getSeconds())}`;
}

const ISSUED_KEY = 'issued_invoices';
const ISSUED_LIMIT = 500;

async function issuedInvoices() {
    return (await db.meta.get(ISSUED_KEY))?.value ?? [];
}

/**
 * Remember an invoice number this device handed out, so it is never handed out twice,
 * even after the browser is closed or the page reloaded.
 */
async function rememberIssuedInvoice(invoiceNo) {
    const issued = await issuedInvoices();

    await db.meta.put({ key: ISSUED_KEY, value: [...issued, invoiceNo].slice(-ISSUED_LIMIT) });
}

/**
 * The next invoice number for this device.
 *
 * Random patterns (the default, AK01-20261028-143205-K7Q4) are made right here
 * from the clock and a random code, so any number of terminals can sell offline
 * at the same time with nothing to agree on. Counter patterns (the old style)
 * continue the series kept in IndexedDB.
 */
export async function nextLocalInvoice(branchCode, terminal, settings = null) {
    const now = new Date();
    const day = deviceDay(now);

    if (patternUsesSequence(settings)) {
        const key = invoiceCursorKey(branchCode, terminal, day);
        const current = Number((await db.meta.get(key))?.value ?? 0);
        const number = current + 1;

        await db.meta.put({ key, value: number });

        return formatInvoice(settings, { branchCode, terminal, day, time: deviceTime(now), number });
    }

    const issued = new Set(await issuedInvoices());
    let invoiceNo = '';

    do {
        invoiceNo = formatInvoice(settings, { branchCode, terminal, day, time: deviceTime(new Date()), random: randomInvoiceCode() });
    } while (issued.has(invoiceNo));

    await rememberIssuedInvoice(invoiceNo);

    return invoiceNo;
}

export async function rememberLocalShift(shift) {
    await db.meta.put({ key: 'local_shift', value: shift });
}

export async function getLocalShift() {
    const row = await db.meta.get('local_shift');

    return row?.value ?? null;
}

export async function saveLocalOrder(order) {
    await db.orders.put(order);
}

export async function findOutbox(clientId) {
    return db.outbox.get(clientId);
}

export async function updateOutboxPayload(clientId, patch) {
    const row = await db.outbox.get(clientId);

    if (! row) {
        return null;
    }

    const payload = { ...row.payload, ...patch };

    await db.outbox.update(clientId, { payload });

    if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('outbox-changed'));
    }

    return { ...row, payload };
}

/**
 * Queue a write locally. The POS keeps working offline; sync pushes
 * the outbox in the background.
 */
export async function queueOutbox(type, payload) {
    const item = {
        client_id: newClientId(),
        type,
        payload,
        created_at_device: new Date().toISOString(),
        data_version: await getDataVersion(),
    };

    await db.outbox.add(item);

    if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('outbox-changed'));
    }

    return item;
}

export async function outboxCount() {
    return db.outbox.count();
}

/**
 * Push every queued item. One failing item never blocks the others;
 * conflicts stay in the box for the manager screen.
 */
export async function pushOutbox() {
    const items = await db.outbox.orderBy('created_at_device').limit(50).toArray();

    if (items.length === 0) {
        return { pushed: 0, conflicts: 0 };
    }

    const token = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const xsrf = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token,
    };

    if (xsrf) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf[1]);
    }

    const response = await fetch('/api/v1/sync/push', {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify({ items }),
    });

    if (! response.ok) {
        throw new Error('sync push failed');
    }

    const body = await response.json();

    const results = body.data?.results ?? [];

    for (const result of results) {
        if (result.status === 'conflict') {
            await db.outbox.update(result.id, { conflict_reason: result.reason });
        } else {
            await db.outbox.delete(result.id);
        }

        // The server keeps a device's number whenever it can. If another sale already had it,
        // the server issued a new one: update the local copy and tell the screen.
        if (result.invoice_changed && result.invoice_no) {
            await db.orders.where('client_id').equals(result.id).modify({ invoice_no: result.invoice_no });
            await rememberIssuedInvoice(result.invoice_no);

            if (typeof window !== 'undefined') {
                window.dispatchEvent(new CustomEvent('invoice-renumbered', { detail: { client_id: result.id, invoice_no: result.invoice_no } }));
            }
        }
    }

    return { pushed: results.length, conflicts: body.data?.conflicts ?? 0 };
}
