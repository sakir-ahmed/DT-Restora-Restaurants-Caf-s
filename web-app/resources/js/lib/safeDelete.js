import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { notify } from '@/lib/notify';
import { t } from '@/lib/i18n';

export const DELETE_REASONS = ['entry_mistake', 'duplicate_entry', 'wrong_amount', 'other'];

/**
 * One delete flow for every list page: a confirmation that says what will be undone, a mandatory
 * reason, then a DELETE request. The server undoes stock and accounts and keeps the row, marked deleted.
 *
 *   const safeDelete = useSafeDelete();
 *   safeDelete({ url: route('admin.wastage.destroy', row.id), title, message, facts, success });
 */
export function useSafeDelete() {
    const confirm = useConfirm();

    return async function safeDelete({ url, title, message, facts = [], success, reasons = DELETE_REASONS, method = 'delete' }) {
        const result = await confirm({
            variant: 'danger',
            level: 'reason',
            title,
            message,
            confirmText: t('common.delete'),
            reasons,
            facts,
        });

        if (! result.confirmed) {
            return;
        }

        router[method](url, {
            data: { reason_code: result.reason, note: result.note ?? null },
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                // A refused delete comes back as a normal redirect carrying an error flash.
                const flash = page.props.flash ?? {};

                if (flash.error) {
                    notify.error(String(flash.error));

                    return;
                }

                notify.success(success);
            },
            onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
        });
    };
}
