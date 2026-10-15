import { toast } from 'vue-sonner';
import { t } from './i18n';

/**
 * Single toast wrapper around vue-sonner so duration, position and
 * microcopy stay consistent. Errors stay longer than successes.
 */
export const notify = {
    success(message, detail = null) {
        toast.success(message ?? t('common.saved'), { description: detail });
    },

    error(message, detail = null, action = null) {
        toast.error(message ?? t('common.error_occurred'), {
            description: detail,
            duration: 8000,
            action: action
                ? { label: action.label, onClick: action.onClick }
                : undefined,
        });
    },

    warning(message, detail = null) {
        toast.warning(message, { description: detail, duration: 6000 });
    },

    info(message, detail = null) {
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
