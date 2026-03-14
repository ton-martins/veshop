<script setup>
import InputError from '@/Components/InputError.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <section class="space-y-4">
        <header>
            <h2 class="text-sm font-semibold text-slate-900">
                Dados do perfil
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Atualize nome e e-mail utilizados para acesso.
            </p>
        </header>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-1">
                <label for="name" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Nome
                </label>

                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:ring-slate-300"
                />

                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div class="space-y-1">
                <label for="email" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    E-mail
                </label>

                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:ring-slate-300"
                />

                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-xs text-amber-800">
                <p>
                    Seu e-mail ainda não foi verificado.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-semibold underline underline-offset-2 hover:text-amber-900"
                    >
                        Reenviar e-mail de verificação
                    </Link>
                </p>

                <p
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 font-semibold text-emerald-700"
                >
                    Novo link de verificação enviado com sucesso.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Salvando...' : 'Salvar alterações' }}
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-xs font-semibold text-emerald-700"
                    >
                        Dados atualizados.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
