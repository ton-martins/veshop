<script setup>
import InputError from '@/Components/InputError.vue';
import BRANDING from '@/branding';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head :title="`Recuperar senha | ${BRANDING.appName}`" />

        <span class="veshop-login-pill">Recuperação de acesso</span>
        <h1 class="veshop-login-title">Esqueci minha senha</h1>
        <p class="veshop-login-subtitle">
            Informe seu e-mail para receber um link seguro de redefinição.
        </p>

        <div v-if="props.status" class="alert alert-success mt-3 mb-0 py-2" role="alert">
            {{ props.status }}
        </div>

        <form class="mt-3" @submit.prevent="submit">
            <div class="mb-2">
                <label for="email" class="veshop-login-label">E-mail de acesso</label>
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

            <button
                type="submit"
                class="btn btn-primary veshop-login-submit mt-3 w-100"
                :class="{ 'opacity-75': form.processing }"
                :disabled="form.processing"
            >
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                {{ form.processing ? 'Enviando...' : 'Enviar link de recuperação' }}
            </button>
        </form>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <span class="text-muted small">Você poderá redefinir sua senha em alguns minutos.</span>
            <Link :href="route('login')" class="veshop-login-link">Voltar para login</Link>
        </div>
    </GuestLayout>
</template>
