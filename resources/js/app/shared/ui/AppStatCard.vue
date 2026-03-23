<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    hint: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            {{ label }}
        </p>
        <p class="mt-2 text-2xl font-bold text-slate-900">
            {{ value }}
        </p>
        <p v-if="hint" class="mt-1 text-xs text-slate-500">
            {{ hint }}
        </p>
    </article>
</template>
