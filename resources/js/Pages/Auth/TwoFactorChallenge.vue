<script setup>
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import BRANDING from '@/branding';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    code: '',
});

const submit = () => {
    form.post(route('two-factor.verify'), {
        onFinish: () => form.reset('code'),
    });
};
</script>

<template>
    <Head title="Verificação em duas etapas" />

    <div class="relative min-h-screen overflow-x-hidden">
        <div class="veshop-shell absolute inset-0 -z-20"></div>
        <div class="veshop-grid absolute inset-0 -z-10 opacity-80"></div>

        <div class="relative mx-auto flex min-h-screen w-full max-w-xl items-center px-4 py-10 sm:px-6">
            <section class="veshop-card w-full p-6 sm:p-8">
                <div class="flex items-center gap-3">
                    <span
                        class="veshop-logo-badge grid h-11 w-11 place-content-center rounded-xl font-display text-sm font-bold tracking-[0.12em]"
                    >
                        VS
                    </span>
                    <div>
                        <p class="font-display text-lg font-extrabold text-slate-900">{{ BRANDING.appName }}</p>
                        <p class="veshop-kicker text-xs font-semibold uppercase tracking-[0.2em]">Verificação em duas etapas</p>
                    </div>
                </div>

                <h1 class="mt-7 font-display text-3xl font-extrabold text-slate-900">Confirme seu acesso</h1>
                <p class="mt-3 text-sm leading-relaxed text-slate-600">
                    Digite o código de 6 dígitos gerado pelo seu app autenticador para continuar.
                </p>

                <form class="mt-7" @submit.prevent="submit">
                    <label for="code" class="text-sm font-semibold text-slate-700">Código do autenticador</label>
                    <TextInput
                        id="code"
                        v-model="form.code"
                        type="text"
                        class="mt-2 block w-full !rounded-xl !border-slate-200 !bg-white/90 !px-3 !py-2 !text-sm !text-slate-900 focus:!border-sky-500 focus:!ring-sky-500/30"
                        inputmode="numeric"
                        maxlength="8"
                        autocomplete="one-time-code"
                        autofocus
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.code" />

                    <button
                        type="submit"
                        class="veshop-btn-primary mt-6 inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold transition"
                        :class="{ 'opacity-60': form.processing }"
                        :disabled="form.processing"
                    >
                        Validar e entrar
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="text-sm font-semibold text-slate-600 underline underline-offset-4 transition hover:text-slate-900"
                    >
                        Sair da conta
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>
