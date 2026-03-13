<script setup>
const steps = [
    {
        number: '01',
        title: 'Configure sua empresa',
        description: 'Escolha o nicho, cadastre loja e defina usuários com permissões por papel.',
    },
    {
        number: '02',
        title: 'Cadastre produtos e estoque',
        description: 'Suba o catálogo interno com variações e tenha o saldo de cada produto em tempo real.',
    },
    {
        number: '03',
        title: 'Venda e acompanhe o caixa',
        description: 'Feche pedidos, baixe contas e acompanhe faturamento e inadimplência no dashboard.',
    },
];
</script>

<template>
    <section id="como-funciona" class="mx-auto w-full max-w-6xl px-4 pb-6 pt-14 sm:px-6 lg:px-8 lg:pt-16">
        <div class="mb-8">
            <p class="veshop-kicker text-xs font-bold uppercase tracking-[0.2em]">Como funciona</p>
            <h2 class="mt-3 font-display text-3xl font-extrabold text-slate-900 sm:text-4xl">
                Comece rápido e evolua por fases.
            </h2>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <article v-for="step in steps" :key="step.number" class="veshop-card p-5">
                <p class="font-display text-2xl font-extrabold text-slate-300">{{ step.number }}</p>
                <h3 class="mt-4 font-display text-xl font-bold text-slate-900">{{ step.title }}</h3>
                <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ step.description }}</p>
            </article>
        </div>
    </section>
</template>
