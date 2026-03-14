<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section class="space-y-4">
        <header>
            <h2 class="text-sm font-semibold text-slate-900">
                Segurança da conta
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Defina uma senha forte para manter seu acesso protegido.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="space-y-4">
            <div class="space-y-1">
                <label for="current_password" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Senha atual
                </label>

                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    autocomplete="current-password"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:ring-slate-300"
                />

                <InputError :message="form.errors.current_password" class="mt-1" />
            </div>

            <div class="space-y-1">
                <label for="password" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Nova senha
                </label>

                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:ring-slate-300"
                />

                <InputError :message="form.errors.password" class="mt-1" />
            </div>

            <div class="space-y-1">
                <label for="password_confirmation" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Confirmar nova senha
                </label>

                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:ring-slate-300"
                />

                <InputError :message="form.errors.password_confirmation" class="mt-1" />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Atualizando...' : 'Atualizar senha' }}
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
                        Senha atualizada.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
