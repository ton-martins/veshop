<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
    status: {
        type: String,
        default: 'Planejamento inicial',
    },
});
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">{{ title }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ description }}</p>
            </div>
            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700">
                {{ status }}
            </span>
        </header>

        <ul v-if="items.length" class="mt-6 grid gap-3 md:grid-cols-2">
            <li
                v-for="item in items"
                :key="item"
                class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
            >
                {{ item }}
            </li>
        </ul>
    </section>
</template>

