<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-6 sm:px-6 lg:px-8">
        <a href="/" class="flex items-center gap-3">
            <span
                class="veshop-logo-badge grid h-11 w-11 place-content-center rounded-xl font-display text-sm font-bold tracking-[0.12em]"
            >
                VS
            </span>
            <div>
                <p class="font-display text-lg font-extrabold text-slate-900">Veshop</p>
                <p class="veshop-kicker text-xs font-semibold uppercase tracking-[0.2em]">Gestão para varejo</p>
            </div>
        </a>

        <nav class="hidden items-center gap-8 text-sm font-semibold text-slate-700 md:flex">
            <a href="#simulador" class="transition hover:text-slate-900">Simulador</a>
            <a href="#modulos" class="transition hover:text-slate-900">Módulos</a>
            <a href="#nichos" class="transition hover:text-slate-900">Nichos</a>
            <a href="#como-funciona" class="transition hover:text-slate-900">Como funciona</a>
        </nav>

        <div class="flex items-center gap-3">
            <Link
                v-if="$page.props.auth.user"
                :href="route('dashboard')"
                class="veshop-btn-secondary rounded-xl px-4 py-2 text-sm font-semibold transition"
            >
                Dashboard
            </Link>

            <template v-else-if="canLogin">
                <Link
                    :href="route('login')"
                    class="veshop-btn-secondary rounded-xl px-4 py-2 text-sm font-semibold transition"
                >
                    Entrar
                </Link>
            </template>
        </div>
    </header>
</template>
