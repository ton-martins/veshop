<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import BRANDING from '@/branding';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const capsLockOn = ref(false);

const hasErrors = computed(() => Boolean(form.errors?.email || form.errors?.password));
const disableForm = computed(() => form.processing);

const submit = () => {
    form.post(route('login'), {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
};

const onKeyEvents = (event) => {
    if (typeof event.getModifierState === 'function') {
        capsLockOn.value = event.getModifierState('CapsLock');
    }
};

const pageKeyHandler = (event) => {
    if (event.key === 'Escape') {
        capsLockOn.value = false;
    }
};

onMounted(() => {
    window.addEventListener('keydown', pageKeyHandler);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', pageKeyHandler);
});
</script>

<template>
    <GuestLayout :show-aside="true">
        <Head :title="`Entrar | ${BRANDING.appName}`" />

        <span class="veshop-login-pill">Acesso seguro ao ERP</span>

        <h1 class="veshop-login-title">Entrar na plataforma</h1>
        <p class="veshop-login-subtitle">
            Acesse seu ambiente para acompanhar vendas, estoque, caixa e financeiro com agilidade.
        </p>

        <div v-if="props.status" class="alert alert-success mt-3 mb-0 py-2" role="alert">
            {{ props.status }}
        </div>

        <form class="mt-3" @submit.prevent="submit">
            <div class="mb-2">
                <label for="email" class="veshop-login-label">E-mail</label>
                <div class="position-relative" :class="{ 'animate-shake': hasErrors && form.errors.email }">
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        autofocus
                        class="form-control veshop-login-input pe-5"
                        :disabled="disableForm"
                        @keydown="onKeyEvents"
                    />
                    <span class="veshop-login-input-icon" aria-hidden="true">
                        <i class="ri-mail-line"></i>
                    </span>
                </div>
                <InputError :message="form.errors.email" class="mt-1" />
            </div>

            <div class="mb-2">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <label for="password" class="veshop-login-label mb-0">Senha</label>
                    <span v-if="capsLockOn" class="veshop-caps-lock">Caps Lock ativo</span>
                </div>

                <div class="position-relative mt-1" :class="{ 'animate-shake': hasErrors && form.errors.password }">
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                        class="form-control veshop-login-input pe-5"
                        :disabled="disableForm"
                        @keydown="onKeyEvents"
                    />

                    <button
                        type="button"
                        class="veshop-login-toggle"
                        :disabled="disableForm"
                        @click="showPassword = !showPassword"
                    >
                        {{ showPassword ? 'Ocultar' : 'Mostrar' }}
                    </button>
                </div>
                <InputError :message="form.errors.password" class="mt-1" />
            </div>

            <div class="d-flex align-items-center justify-content-between gap-3 mt-2">
                <label class="d-inline-flex align-items-center gap-2 text-muted small">
                    <Checkbox
                        v-model:checked="form.remember"
                        name="remember"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        :disabled="disableForm"
                    />
                    Manter conectado
                </label>

                <Link v-if="props.canResetPassword" :href="route('password.request')" class="veshop-login-link">
                    Esqueci a senha
                </Link>
            </div>

            <button
                type="submit"
                class="btn btn-primary veshop-login-submit mt-3 w-100"
                :class="{ 'opacity-75': disableForm }"
                :disabled="disableForm"
            >
                <span v-if="disableForm" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                {{ disableForm ? 'Entrando...' : 'Entrar no painel' }}
            </button>
        </form>

        <div class="veshop-login-note mt-3">
            Seu acesso é protegido por autenticação em duas etapas e validação contínua de sessão.
        </div>
    </GuestLayout>
</template>
