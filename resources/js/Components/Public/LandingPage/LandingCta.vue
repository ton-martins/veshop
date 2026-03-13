<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <section id="cta" class="mx-auto w-full max-w-6xl px-4 pb-12 pt-14 sm:px-6 lg:px-8 lg:pb-16 lg:pt-16">
        <div class="veshop-cta-shell overflow-hidden rounded-3xl p-8 sm:p-10">
            <p class="veshop-cta-kicker text-xs font-bold uppercase tracking-[0.2em]">Lançamento Veshop</p>
            <h2 class="mt-4 max-w-2xl font-display text-3xl font-extrabold leading-tight sm:text-4xl">
                Tire sua operação do improviso e gerencie seu comércio de ponta a ponta.
            </h2>
            <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-300 sm:text-base">
                Cadastre seus produtos, publique o catálogo online e feche pedidos via WhatsApp em uma plataforma única.
            </p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <Link
                    :href="route('login')"
                    class="veshop-btn-highlight rounded-xl px-6 py-3 text-sm font-semibold transition"
                >
                    Entrar no painel
                </Link>
                <a
                    href="#modulos"
                    class="veshop-btn-dark-ghost rounded-xl px-6 py-3 text-sm font-semibold transition"
                >
                    Revisar módulos
                </a>
            </div>
        </div>
    </section>
</template>
