<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <section class="mx-auto w-full max-w-6xl px-4 pb-16 pt-8 sm:px-6 lg:px-8 lg:pb-20 lg:pt-12">
        <div class="grid items-center gap-12 lg:grid-cols-[1.05fr_0.95fr]">
            <div class="reveal-up">
                <p class="veshop-badge inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-[0.18em]">
                    ERP + Catálogo + WhatsApp
                </p>
                <h1 class="mt-5 font-display text-4xl font-extrabold leading-tight text-slate-900 sm:text-5xl">
                    Gestão completa para varejo, em um sistema feito para vender.
                </h1>
                <p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-600">
                    Controle estoque, pedidos, faturamento e financeiro da sua empresa em um único painel.
                    Publique um catálogo online e converta vendas direto no WhatsApp.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('login')"
                        class="veshop-btn-primary rounded-xl px-6 py-3 text-sm font-semibold transition"
                    >
                        Entrar no painel
                    </Link>
                    <a
                        href="#modulos"
                        class="veshop-btn-secondary rounded-xl px-6 py-3 text-sm font-semibold transition"
                    >
                        Ver módulos
                    </a>
                </div>

                <div class="mt-8 flex flex-wrap gap-2 text-sm font-medium text-slate-600">
                    <span class="veshop-chip rounded-full px-3 py-1.5">Lojas de roupas</span>
                    <span class="veshop-chip rounded-full px-3 py-1.5">Bazares</span>
                    <span class="veshop-chip rounded-full px-3 py-1.5">Mercados</span>
                    <span class="veshop-chip rounded-full px-3 py-1.5">Autoelétricas</span>
                    <span class="veshop-chip rounded-full px-3 py-1.5">Outros comércios</span>
                </div>
            </div>

            <div class="reveal-up relative">
                <div class="veshop-card p-6 sm:p-7">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Painel operacional</p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="veshop-metric-primary rounded-xl p-4">
                            <p class="veshop-metric-muted text-xs uppercase tracking-[0.16em]">Faturamento hoje</p>
                            <p class="mt-2 font-display text-2xl font-bold">R$ 8.740</p>
                            <p class="veshop-metric-muted mt-1 text-xs">+18% vs. ontem</p>
                        </div>

                        <div class="veshop-metric-accent rounded-xl p-4">
                            <p class="veshop-metric-accent-label text-xs uppercase tracking-[0.16em]">Pedidos em aberto</p>
                            <p class="mt-2 font-display text-2xl font-bold">24</p>
                            <p class="veshop-metric-accent-label mt-1 text-xs">7 aguardando pagamento</p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:col-span-2">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Mensagem pré-preenchida</p>
                            <p class="mt-2 text-sm text-slate-700">
                                "Oi, quero fechar o pedido #128 com 3 itens do catálogo."
                            </p>
                            <p class="veshop-inline-note mt-2 text-xs font-semibold">Origem: catálogo online</p>
                        </div>
                    </div>
                </div>

                <div class="veshop-float-badge float-slow absolute -right-3 -top-5 rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-[0.16em]">
                    WhatsApp integrado
                </div>
            </div>
        </div>
    </section>
</template>
