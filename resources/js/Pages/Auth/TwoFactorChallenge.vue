<script setup>
import InputError from '@/Components/InputError.vue';
import BRANDING from '@/branding';
import GuestLayout from '@/Layouts/GuestLayout.vue';
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
    <GuestLayout>
        <Head :title="`Verificação em duas etapas | ${BRANDING.appName}`" />

        <span class="veshop-login-pill">Segundo fator</span>
        <h1 class="veshop-login-title">Validar código</h1>
        <p class="veshop-login-subtitle">
            Digite o código do autenticador para concluir seu login.
        </p>

        <form class="mt-3" @submit.prevent="submit">
            <div class="mb-2">
                <label for="code" class="veshop-login-label">Código do autenticador</label>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    maxlength="8"
                    autocomplete="one-time-code"
                    required
                    autofocus
                    class="form-control veshop-login-input text-center"
                    style="letter-spacing: 0.34em; font-size: 1.02rem"
                />
                <InputError :message="form.errors.code" class="mt-1" />
            </div>

            <button
                type="submit"
                class="btn btn-primary veshop-login-submit mt-3 w-100"
                :class="{ 'opacity-75': form.processing }"
                :disabled="form.processing"
            >
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                {{ form.processing ? 'Validando...' : 'Validar e entrar' }}
            </button>
        </form>

        <div class="veshop-login-note mt-3">
            Se o código expirar, aguarde a próxima rotação no autenticador.
        </div>

        <div class="mt-3 text-center">
            <Link :href="route('logout')" method="post" as="button" class="veshop-login-link">
                Sair da conta
            </Link>
        </div>
    </GuestLayout>
</template>
