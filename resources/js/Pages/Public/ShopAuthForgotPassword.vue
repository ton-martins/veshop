<script setup>
import InputError from '@/Components/InputError.vue';
import AuthNativeShell from '@/Components/Public/Storefront/AuthNativeShell.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    contractor: { type: Object, required: true },
    status: {
        type: String,
        default: null,
    },
});

const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const forgotUrl = computed(() => `/shop/${storeSlug.value}/esqueci-senha`);
const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const shopUrl = computed(() => `/shop/${storeSlug.value}`);

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(forgotUrl.value, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Recuperar senha | ${storeName}`" />

    <AuthNativeShell
        :contractor="contractor"
        badge="Recuperação"
        title="Esqueci minha senha"
        subtitle="Informe o e-mail da conta para receber o link de recuperação."
        :back-href="loginUrl"
        back-label="Voltar para login"
        hero-title="Recuperação de senha"
        hero-description="Acesse sua conta e recupere sua senha."
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
                    autofocus
                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                    placeholder="você@exemplo.com"
                >
            </label>
            <InputError :message="form.errors.email" class="-mt-2" />

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                style="background: var(--store-auth-primary-strong)"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Enviando...' : 'Enviar link de recuperação' }}
            </button>
        </form>

        <p class="mt-5 text-center text-sm text-slate-500">
            Lembrou da senha?
            <Link :href="loginUrl" class="font-semibold text-[var(--store-auth-primary)]">Entrar</Link>
        </p>

        <template #hero-footer>
            <Link
                :href="shopUrl"
                class="inline-flex items-center justify-center rounded-xl border border-white/30 bg-white/10 px-3 py-2 text-xs font-semibold text-white hover:bg-white/20"
            >
                Ver vitrine pública
            </Link>
        </template>
    </AuthNativeShell>
</template>
