<script setup>
const stats = [
    {
        value: '5x',
        label: 'menos planilhas operacionais',
    },
    {
        value: '100%',
        label: 'vendas conectadas ao financeiro',
    },
    {
        value: '< 10 min',
        label: 'para cadastrar o primeiro catálogo',
    },
];
</script>

<template>
    <section class="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="veshop-card grid gap-4 p-5 sm:grid-cols-3 sm:p-6">
            <article v-for="item in stats" :key="item.label" class="veshop-chip rounded-xl p-5">
                <p class="font-display text-3xl font-extrabold text-slate-900">{{ item.value }}</p>
                <p class="mt-2 text-sm font-medium text-slate-600">{{ item.label }}</p>
            </article>
        </div>
    </section>
</template>
