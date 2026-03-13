<script setup>
import InputError from '@/Components/InputError.vue';
import BRANDING from '@/branding';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="`Confirmar senha | ${BRANDING.appName}`" />

        <span class="veshop-login-pill">Validação de segurança</span>
        <h1 class="veshop-login-title">Confirmar senha</h1>
        <p class="veshop-login-subtitle">
            Digite sua senha para continuar.
        </p>

        <form class="mt-3" @submit.prevent="submit">
            <div class="mb-2">
                <label for="password" class="veshop-login-label">Senha atual</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    autofocus
                    class="form-control veshop-login-input"
                />
                <InputError :message="form.errors.password" class="mt-1" />
            </div>

            <button
                type="submit"
                class="btn btn-primary veshop-login-submit mt-3 w-100"
                :class="{ 'opacity-75': form.processing }"
                :disabled="form.processing"
            >
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                {{ form.processing ? 'Validando...' : 'Confirmar e continuar' }}
            </button>
        </form>
    </GuestLayout>
</template>
