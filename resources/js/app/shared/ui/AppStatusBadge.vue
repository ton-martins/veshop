<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    tone: {
        type: String,
        default: 'neutral',
        validator: (value) => ['neutral', 'success', 'warning', 'danger', 'info'].includes(value),
    },
});

const classes = computed(() => {
    const map = {
        neutral: 'bg-slate-100 text-slate-700',
        success: 'bg-emerald-100 text-emerald-700',
        warning: 'bg-amber-100 text-amber-700',
        danger: 'bg-rose-100 text-rose-700',
        info: 'bg-sky-100 text-sky-700',
    };

    return map[props.tone] ?? map.neutral;
});
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="classes">
        {{ label }}
    </span>
</template>
