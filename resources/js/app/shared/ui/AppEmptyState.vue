<script setup>
defineProps({
    title: {
        type: String,
        default: 'Nenhum resultado encontrado',
    },
    description: {
        type: String,
        default: 'Ajuste os filtros ou cadastre novos dados para continuar.',
    },
});
</script>

<template>
    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
        <h3 class="text-base font-semibold text-slate-800">
            {{ title }}
        </h3>
        <p class="mt-2 text-sm text-slate-600">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
