<script setup>
import { computed } from 'vue';

const props = defineProps({
    seats: { type: Number, default: 4 },
    tone: { type: String, default: 'free' }, // free | busy | selected | blocked
});

const chairCount = computed(() => Math.min(Math.max(props.seats, 2), 8));
const topChairs = computed(() => Math.ceil(chairCount.value / 2));
const bottomChairs = computed(() => Math.floor(chairCount.value / 2));

const chairClass = computed(() => ({
    free: 'bg-border',
    busy: 'bg-warning/70',
    selected: 'bg-accent',
    blocked: 'bg-danger/50',
})[props.tone] ?? 'bg-border');

const surfaceClass = computed(() => ({
    free: 'border-border bg-background',
    busy: 'border-warning/60 bg-warning/10',
    selected: 'border-accent bg-accent/15',
    blocked: 'border-danger/40 bg-danger/10',
})[props.tone] ?? 'border-border bg-background');
</script>

<template>
    <div class="flex flex-col items-center gap-[3px] px-2">
        <div class="flex w-full justify-around px-2" aria-hidden="true">
            <span v-for="n in topChairs" :key="`t${n}`" class="h-1.5 w-5 rounded-full" :class="chairClass" />
        </div>
        <div class="relative w-full rounded-xl border px-2 py-1.5" :class="surfaceClass">
            <slot />
        </div>
        <div class="flex w-full justify-around px-2" aria-hidden="true">
            <span v-for="n in bottomChairs" :key="`b${n}`" class="h-1.5 w-5 rounded-full" :class="chairClass" />
        </div>
    </div>
</template>
