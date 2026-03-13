<script setup>
import InputError from '@/Components/InputError.vue';
import BRANDING from '@/branding';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="`Redefinir senha | ${BRANDING.appName}`" />

        <span class="veshop-login-pill">Nova credencial</span>
        <h1 class="veshop-login-title">Criar nova senha</h1>
        <p class="veshop-login-subtitle">
            Defina uma senha forte para continuar com segurança.
        </p>

        <form class="mt-3" @submit.prevent="submit">
            <div class="mb-2">
                <label for="email" class="veshop-login-label">E-mail</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    autofocus
                    class="form-control veshop-login-input"
                />
                <InputError :message="form.errors.email" class="mt-1" />
            </div>

            <div class="mb-2">
                <label for="password" class="veshop-login-label">Nova senha</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="form-control veshop-login-input"
                />
                <InputError :message="form.errors.password" class="mt-1" />
            </div>

            <div class="mb-2">
                <label for="password_confirmation" class="veshop-login-label">Confirmar nova senha</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="form-control veshop-login-input"
                />
                <InputError :message="form.errors.password_confirmation" class="mt-1" />
            </div>

            <button
                type="submit"
                class="btn btn-primary veshop-login-submit mt-3 w-100"
                :class="{ 'opacity-75': form.processing }"
                :disabled="form.processing"
            >
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                {{ form.processing ? 'Salvando...' : 'Salvar nova senha' }}
            </button>
        </form>

        <div class="mt-3 text-end">
            <Link :href="route('login')" class="veshop-login-link">Voltar para login</Link>
        </div>
    </GuestLayout>
</template>
