import Dexie from 'dexie';

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
    const meta = await db.meta.get('bootstrap');

    return { categories, branch: meta?.value?.branch };
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

    const response = await fetch('/api/v1/sync/push', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        },
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
    }

    return { pushed: results.length, conflicts: body.data?.conflicts ?? 0 };
}
