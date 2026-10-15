<script setup>
import ConfirmDialog from './ConfirmDialog.vue';
import { useConfirmState } from './useConfirm';

const { state, cancel, confirm } = useConfirmState();

function onConfirm(payload) {
    confirm(payload);
}
</script>

<template>
    <ConfirmDialog
        :open="state.open"
        :variant="state.options?.variant ?? 'info'"
        :level="state.options?.level ?? 'simple'"
        :title="state.options?.title ?? ''"
        :message="state.options?.message ?? ''"
        :facts="state.options?.facts ?? []"
        :confirm-text="state.options?.confirmText"
        :reasons="state.options?.reasons ?? []"
        :typed-word="state.options?.typedWord ?? 'CONFIRM'"
        :busy="state.options?.busy ?? false"
        @cancel="cancel"
        @confirm="onConfirm"
    />
</template>
