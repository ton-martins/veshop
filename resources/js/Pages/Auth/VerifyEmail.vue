<script setup>
import BRANDING from '@/branding';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({});
const verificationLinkSent = computed(() => props.status === 'verification-link-sent');

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <GuestLayout>
        <Head :title="`Verificar e-mail | ${BRANDING.appName}`" />

        <span class="veshop-login-pill">Validação de conta</span>
        <h1 class="veshop-login-title">Verificar e-mail</h1>
        <p class="veshop-login-subtitle">
            Confirme seu endereço de e-mail para continuar no sistema.
        </p>

        <div v-if="verificationLinkSent" class="alert alert-success mt-3 mb-0 py-2" role="alert">
            Um novo link de verificação foi enviado com sucesso.
        </div>

        <form class="mt-3" @submit.prevent="submit">
            <button
                type="submit"
                class="btn btn-primary veshop-login-submit w-100"
                :class="{ 'opacity-75': form.processing }"
                :disabled="form.processing"
            >
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                {{ form.processing ? 'Enviando...' : 'Reenviar e-mail de verificação' }}
            </button>
        </form>

        <div class="veshop-login-note mt-3">
            Se não encontrar o e-mail, verifique a caixa de spam e tente novamente.
        </div>

        <div class="mt-3 text-center">
            <Link :href="route('logout')" method="post" as="button" class="veshop-login-link">
                Sair da conta
            </Link>
        </div>
    </GuestLayout>
</template>
