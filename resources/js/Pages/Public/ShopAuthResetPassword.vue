<script setup>
import InputError from '@/Components/InputError.vue';
import AuthNativeShell from '@/Components/Public/Storefront/AuthNativeShell.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    contractor: { type: Object, required: true },
    email: {
        type: String,
        default: '',
    },
    token: {
        type: String,
        required: true,
    },
    status: {
        type: String,
        default: null,
    },
});

const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const resetUrl = computed(() => `/shop/${storeSlug.value}/redefinir-senha`);
const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(resetUrl.value, {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head :title="`Redefinir senha | ${storeName}`" />

    <AuthNativeShell
        :contractor="contractor"
        badge="Nova senha"
        title="Redefinir senha"
        subtitle="Atualize suas credenciais para voltar ao painel da loja."
        :back-href="loginUrl"
        back-label="Voltar para login"
        hero-title="Recuperação com padrão nativo"
        hero-description="Mesmo sistema de componentes usado na vitrine, carrinho e navegação da loja."
    >
        <div v-if="props.status" class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ props.status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Email</span>
                <input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                    placeholder="você@exemplo.com"
                >
            </label>
            <InputError :message="form.errors.email" class="-mt-2" />

            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Nova senha</span>
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                    placeholder="Digite a nova senha"
                >
            </label>
            <InputError :message="form.errors.password" class="-mt-2" />

            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Confirmar nova senha</span>
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                    placeholder="Repita a nova senha"
                >
            </label>
            <InputError :message="form.errors.password_confirmation" class="-mt-2" />

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                style="background: var(--store-auth-primary-strong)"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Salvando...' : 'Salvar nova senha' }}
            </button>
        </form>

        <p class="mt-5 text-center text-sm text-slate-500">
            Já possui acesso?
            <Link :href="loginUrl" class="font-semibold text-[var(--store-auth-primary)]">Entrar</Link>
        </p>
    </AuthNativeShell>
</template>
