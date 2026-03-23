<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="space-y-6">
        <header v-if="title || subtitle" class="space-y-1">
            <h1 v-if="title" class="text-2xl font-bold text-slate-900">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="text-sm text-slate-600">
                {{ subtitle }}
            </p>
        </header>

        <div class="space-y-6">
            <slot />
        </div>
    </section>
</template>
