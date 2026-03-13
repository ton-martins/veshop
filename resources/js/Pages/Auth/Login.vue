<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import BRANDING from '@/branding';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Entrar" />

    <div class="relative min-h-screen overflow-x-hidden">
        <div class="veshop-shell absolute inset-0 -z-20"></div>
        <div class="veshop-grid absolute inset-0 -z-10 opacity-80"></div>

        <div class="relative mx-auto flex min-h-screen w-full max-w-6xl items-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid w-full items-stretch gap-6 lg:grid-cols-[1.05fr_0.95fr]">
                <section class="veshop-card hidden p-8 lg:flex lg:flex-col lg:justify-between">
                    <div>
                        <p class="veshop-kicker text-xs font-bold uppercase tracking-[0.2em]">Acesso seguro</p>
                        <h1 class="mt-4 font-display text-4xl font-extrabold leading-tight text-slate-900">
                            Faça login e continue a operação da sua loja no {{ BRANDING.appName }}.
                        </h1>
                        <p class="mt-4 max-w-lg text-base leading-relaxed text-slate-600">
                            Centralize pedidos, financeiro, estoque e catálogo online em um único painel com foco em produtividade.
                        </p>
                    </div>

                    <div class="mt-10 grid gap-3">
                        <div class="veshop-metric-primary rounded-xl p-4">
                            <p class="veshop-metric-muted text-xs uppercase tracking-[0.16em]">Disponibilidade</p>
                            <p class="mt-2 font-display text-2xl font-bold">Sempre online</p>
                        </div>
                        <div class="veshop-chip rounded-xl px-4 py-3 text-sm font-semibold">
                            Gestão em pt-BR com padrões de segurança por padrão.
                        </div>
                    </div>
                </section>

                <section class="veshop-card p-6 sm:p-8">
                    <Link href="/" class="flex items-center gap-3">
                        <span
                            class="veshop-logo-badge grid h-11 w-11 place-content-center rounded-xl font-display text-sm font-bold tracking-[0.12em]"
                        >
                            VS
                        </span>
                        <div>
                            <p class="font-display text-lg font-extrabold text-slate-900">{{ BRANDING.appName }}</p>
                            <p class="veshop-kicker text-xs font-semibold uppercase tracking-[0.2em]">Gestão para varejo</p>
                        </div>
                    </Link>

                    <h2 class="mt-8 font-display text-3xl font-extrabold text-slate-900">Entrar na plataforma</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Use seu e-mail e senha para acessar o painel administrativo.
                    </p>

                    <div
                        v-if="status"
                        class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                    >
                        {{ status }}
                    </div>

                    <form class="mt-6" @submit.prevent="submit">
                        <div>
                            <label for="email" class="text-sm font-semibold text-slate-700">E-mail</label>
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-2 block w-full !rounded-xl !border-slate-200 !bg-white/90 !px-3 !py-2 !text-sm !text-slate-900 focus:!border-sky-500 focus:!ring-sky-500/30"
                                required
                                autofocus
                                autocomplete="username"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div class="mt-5">
                            <label for="password" class="text-sm font-semibold text-slate-700">Senha</label>
                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-2 block w-full !rounded-xl !border-slate-200 !bg-white/90 !px-3 !py-2 !text-sm !text-slate-900 focus:!border-sky-500 focus:!ring-sky-500/30"
                                required
                                autocomplete="current-password"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="mt-5 flex items-center justify-between gap-4">
                            <label class="flex items-center">
                                <Checkbox name="remember" v-model:checked="form.remember" />
                                <span class="ms-2 text-sm text-slate-600">Lembrar acesso</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm font-semibold text-slate-600 underline underline-offset-4 transition hover:text-slate-900"
                            >
                                Esqueceu a senha?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            class="veshop-btn-primary mt-7 inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold transition"
                            :class="{ 'opacity-60': form.processing }"
                            :disabled="form.processing"
                        >
                            Entrar
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</template>
