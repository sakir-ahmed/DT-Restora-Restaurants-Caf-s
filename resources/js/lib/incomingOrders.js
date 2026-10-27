import { onBeforeUnmount, onMounted } from 'vue';
import { initEcho } from '@/lib/echo.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { playSound } from '@/lib/sounds';

/**
 * Chime for an order that came from the website or a delivery platform.
 */
export function playOrderChime() {
    playSound('online_order');
}

/**
 * Watches one branch for website and platform orders and alerts the
 * terminal with a sound and a notification. Websockets give the instant
 * path; a light poll covers servers without them.
 */
export function useIncomingOrders(branchId, { pollMs = 15000 } = {}) {
    let lastId = null;
    let timer = null;
    let echo = null;
    const seen = new Set();

    function alertFor(order) {
        const key = order.order_id ?? order.id;

        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        playOrderChime();
        notify.info(t('orders.incoming_order', {
            source: order.aggregator ?? order.aggregator_channel?.name ?? (order.channel === 'qr'
                ? `${t('orders.channel_qr')}${(order.table?.name ?? order.table) ? ` ${order.table?.name ?? order.table}` : ''}`
                : t(order.channel === 'website' ? 'orders.channel_website' : 'orders.channel_pos')),
            invoice: order.invoice_no,
        }), null, { silent: true });
    }

    async function poll() {
        try {
            const response = await fetch(`${route('admin.orders.incoming')}?after=${lastId ?? 0}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();
            const first = lastId === null;

            lastId = data.last_id;

            if (! first) {
                data.orders.forEach(alertFor);
            }
        } catch {
            // offline: try again on the next tick
        }
    }

    onMounted(() => {
        poll();
        timer = setInterval(poll, pollMs);
        echo = initEcho();

        echo?.private(`branch.${branchId}.kitchen`).listen('.OrderCreated', (event) => {
            if (event.channel && event.channel !== 'pos') {
                alertFor(event);
            }
        });
    });

    onBeforeUnmount(() => {
        clearInterval(timer);
        echo?.private(`branch.${branchId}.kitchen`).stopListening('.OrderCreated');
    });
}
