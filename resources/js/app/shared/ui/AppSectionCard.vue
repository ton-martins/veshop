<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <header v-if="title || description" class="mb-4 space-y-1">
            <h2 v-if="title" class="text-base font-semibold text-slate-900">
                {{ title }}
            </h2>
            <p v-if="description" class="text-sm text-slate-600">
                {{ description }}
            </p>
        </header>
        <slot />
    </article>
</template>
