import { reactive, readonly } from 'vue';

let counter = 0;

const state = reactive({
    open: false,
    options: null,
    resolving: null,
});

/**
 * Resolve what the user chose:
 * confirmed=false, or confirmed=true with reason/pin/typedText depending
 * on the confirmation level (see design.md section 12).
 */
function close(result) {
    const resolve = state.resolving;

    state.open = false;
    state.options = null;
    state.resolving = null;

    resolve?.(result);
}

/**
 * Promise-based confirmation usable from any page or component:
 *
 *   const confirm = useConfirm();
 *   const result = await confirm({ variant: "danger", level: "reason", ... });
 */
export function useConfirm() {
    function ask(options) {
        return new Promise((resolve) => {
            counter += 1;

            state.options = { ...options, uid: counter };
            state.resolving = resolve;
            state.open = true;
        });
    }

    return ask;
}

export function useConfirmState() {
    return {
        state: readonly(state),
        cancel: () => close({ confirmed: false }),
        confirm: (payload = {}) => close({ confirmed: true, ...payload }),
    };
}
