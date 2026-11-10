import { h } from 'vue';
import { toast } from 'vue-sonner';
import { t } from './i18n';
import { playSound } from './sounds';

/**
 * Single toast wrapper around vue-sonner so duration, position and
 * microcopy stay consistent. Errors stay longer than successes.
 */
let lastToast = { key: '', at: 0 };
const shownSms = new Set();

/**
 * Skip a toast that repeats the previous one within a moment, so a flash
 * message and a manual toast for the same event show once.
 */
function isDuplicate(type, message) {
    const key = `${type}:${message}`;
    const now = Date.now();
    const duplicate = lastToast.key === key && now - lastToast.at < 1500;

    lastToast = { key, at: now };

    return duplicate;
}

export const notify = {
    success(message, detail = null) {
        if (isDuplicate('success', message)) {
            return;
        }

        playSound('success');
        toast.success(message ?? t('common.saved'), { description: detail });
    },

    error(message, detail = null, action = null) {
        if (isDuplicate('error', message)) {
            return;
        }

        playSound('error');
        toast.error(message ?? t('common.error_occurred'), {
            description: detail,
            duration: 8000,
            action: action
                ? { label: action.label, onClick: action.onClick }
                : undefined,
        });
    },

    /**
     * Show an SMS that demo mode captured instead of sending. Each message
     * is shown once, with its full text.
     */
    sms(entry) {
        if (shownSms.has(entry.id)) {
            return;
        }

        shownSms.add(entry.id);

        const bangla = document.documentElement.lang === 'bn';
        let id = null;

        // A small chat card: who it went to, the message as a bubble, and a quiet line that nothing was really sent.
        id = toast.custom({
            render: () => h('div', {
                role: 'status',
                class: 'flex w-[340px] max-w-[calc(100vw-2rem)] flex-col gap-1.5 rounded-xl border border-border bg-surface p-3 text-foreground shadow-lg',
            }, [
                h('div', { class: 'flex items-center justify-between gap-2' }, [
                    h('span', { class: `inline-flex items-center gap-1.5 text-[10px] font-semibold text-muted ${bangla ? '' : 'uppercase tracking-wider'}` }, [
                        h('span', { class: 'h-1.5 w-1.5 rounded-full bg-accent', 'aria-hidden': 'true' }),
                        t('common.demo_sms_title'),
                    ]),
                    h('span', { class: 'flex items-center gap-2' }, [
                        h('span', { class: 'text-[11px] tabular-nums text-muted' }, t('common.demo_sms_to', { mobile: entry.mobile })),
                        h('button', {
                            type: 'button',
                            class: 'flex h-5 w-5 items-center justify-center rounded text-muted hover:text-foreground',
                            'aria-label': t('common.close'),
                            onClick: () => toast.dismiss(id),
                        }, '\u00d7'),
                    ]),
                ]),
                h('div', { class: 'whitespace-pre-line break-words rounded-2xl rounded-tl-sm bg-accent/15 px-3 py-2 text-[13px] leading-snug' }, entry.message),
                h('p', { class: 'text-[10px] italic text-muted' }, t('common.demo_sms_note')),
            ]),
        }, { duration: 30000 });
    },

    warning(message, detail = null) {
        playSound('notification');
        toast.warning(message, { description: detail, duration: 6000 });
    },

    /**
     * `silent` is for a toast that comes with a sound of its own (a new order, a status change).
     */
    info(message, detail = null, { silent = false } = {}) {
        if (! silent) {
            playSound('notification');
        }

        toast.info(message, { description: detail });
    },

    promise(promise, { loading, success, error } = {}) {
        return toast.promise(promise, {
            loading: loading ?? t('common.working'),
            success: (data) => (typeof success === 'function' ? success(data) : success ?? t('common.done')),
            error: (err) => (typeof error === 'function' ? error(err) : error ?? t('common.error_occurred')),
        });
    },
};
