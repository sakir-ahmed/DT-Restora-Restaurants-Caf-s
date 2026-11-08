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

        toast(t('common.demo_sms_title', { mobile: entry.mobile }), {
            description: `${entry.message}\n\n${t('common.demo_sms_note')}`,
            duration: 30000,
            closeButton: true,
            style: { whiteSpace: 'pre-line' },
        });
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
