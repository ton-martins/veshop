<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-4">
        <header>
            <h2 class="text-sm font-semibold text-rose-700">
                Zona de risco
            </h2>

            <p class="mt-1 text-xs text-slate-600">
                Ao excluir a conta, todos os dados vinculados serão removidos de forma permanente.
            </p>
        </header>

        <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-3 text-xs text-rose-700">
            Esta ação é irreversível. Verifique se não há informações importantes pendentes.
        </div>

        <button
            type="button"
            class="inline-flex items-center rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-700"
            @click="confirmUserDeletion"
        >
            Excluir conta
        </button>

        <Modal :show="confirmingUserDeletion" max-width="lg" @close="closeModal">
            <div class="space-y-4 p-6">
                <h2 class="text-base font-semibold text-slate-900">
                    Confirmar exclusão da conta
                </h2>

                <p class="text-sm text-slate-600">
                    Digite sua senha para confirmar a exclusão permanente da conta.
                </p>

                <div class="space-y-1">
                    <label for="password" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Senha
                    </label>

                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-rose-400 focus:ring-rose-300"
                        placeholder="Digite sua senha"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-1" />
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Cancelar
                    </button>

                    <button
                        type="button"
                        class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        {{ form.processing ? 'Excluindo...' : 'Confirmar exclusão' }}
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
