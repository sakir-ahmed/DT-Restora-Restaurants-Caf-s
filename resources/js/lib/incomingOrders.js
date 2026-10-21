import { onBeforeUnmount, onMounted } from 'vue';
import { initEcho } from '@/lib/echo.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

let audio = null;

/**
 * Two-tone chime for an order that came from the website or a delivery
 * platform. Browsers keep audio locked until the page has been touched.
 */
export function playOrderChime() {
    try {
        audio ??= new AudioContext();

        if (audio.state === 'suspended') {
            audio.resume();
        }

        [[880, 0], [1175, 0.2], [880, 0.4], [1175, 0.6]].forEach(([frequency, offset]) => {
            const osc = audio.createOscillator();
            const gain = audio.createGain();
            const start = audio.currentTime + offset;

            osc.frequency.value = frequency;
            gain.gain.setValueAtTime(0.15, start);
            gain.gain.exponentialRampToValueAtTime(0.001, start + 0.18);
            osc.connect(gain).connect(audio.destination);
            osc.start(start);
            osc.stop(start + 0.19);
        });
    } catch {
        // audio unavailable
    }
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
            source: order.aggregator ?? order.aggregator_channel?.name ?? t(order.channel === 'website' ? 'orders.channel_website' : 'orders.channel_pos'),
            invoice: order.invoice_no,
        }));
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
