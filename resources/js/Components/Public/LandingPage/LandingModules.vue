<script setup>
const modules = [
    {
        title: 'Cadastro por nicho',
        description: 'Onboarding com tipo de comércio para iniciar com campos e fluxos mais aderentes.',
    },
    {
        title: 'Produtos e estoque',
        description: 'Controle de SKU, variações, estoque mínimo, entradas, saídas e transferência entre lojas.',
    },
    {
        title: 'Pedidos e faturamento',
        description: 'Orçamento, pedido, venda e faturamento no mesmo fluxo com status claros para o time.',
    },
    {
        title: 'Contas a receber e a pagar',
        description: 'Baixa parcial e total, vencimentos, inadimplência e fluxo de caixa previsto x realizado.',
    },
    {
        title: 'Catálogo online',
        description: 'Página pública da loja com produtos, filtros e CTA para fechar compra pelo WhatsApp.',
    },
    {
        title: 'Dashboard executivo',
        description: 'Acompanhe receita, margem, giro e produtos mais vendidos sem depender de planilhas.',
    },
];
</script>

<template>
    <section id="modulos" class="mx-auto w-full max-w-6xl px-4 pb-6 pt-16 sm:px-6 lg:px-8 lg:pt-20">
        <div class="mb-8">
            <p class="veshop-kicker text-xs font-bold uppercase tracking-[0.2em]">Módulos do Veshop</p>
            <h2 class="mt-3 font-display text-3xl font-extrabold text-slate-900 sm:text-4xl">
                Um sistema para rodar a operação inteira da empresa.
            </h2>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <article v-for="item in modules" :key="item.title" class="veshop-card p-5">
                <h3 class="font-display text-xl font-bold text-slate-900">{{ item.title }}</h3>
                <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ item.description }}</p>
            </article>
        </div>
    </section>
</template>
