<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import AuthNativeShell from '@/Components/Public/Storefront/AuthNativeShell.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    contractor: { type: Object, required: true },
});

const page = usePage();
const flashStatus = computed(() => String(page.props?.flash?.status ?? '').trim());
const showPassword = ref(false);

const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const registerUrl = computed(() => `/shop/${storeSlug.value}/cadastro`);
const forgotPasswordUrl = computed(() => `/shop/${storeSlug.value}/esqueci-senha`);
const shopUrl = computed(() => `/shop/${storeSlug.value}`);

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post(loginUrl.value, {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="`Entrar | ${storeName}`" />

    <AuthNativeShell
        :contractor="contractor"
        badge="Acesso"
        title="Welcome Back"
        subtitle="Entre para acompanhar pedidos, favoritos e checkout rapido."
        :back-href="shopUrl"
        back-label="Voltar para loja"
        hero-title="Loja virtual no estilo app"
        hero-description="Mesmo padrao visual em mobile, tablet e desktop para comercio e servicos."
    >
        <div v-if="flashStatus" class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ flashStatus }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <label class="block rounded-2xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm transition focus-within:border-[var(--store-auth-primary-border)] focus-within:ring-2 focus-within:ring-[var(--store-auth-ring)]">
                <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Email</span>
                <input
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    autofocus
                    class="mt-1 w-full border-0 p-0 text-sm text-slate-900 outline-none"
                    placeholder="voce@exemplo.com"
                >
            </label>
            <InputError :message="form.errors.email" class="-mt-2" />

            <label class="block rounded-2xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm transition focus-within:border-[var(--store-auth-primary-border)] focus-within:ring-2 focus-within:ring-[var(--store-auth-ring)]">
                <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Senha</span>
                <div class="mt-1 flex items-center gap-2">
                    <input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                        class="w-full border-0 p-0 text-sm text-slate-900 outline-none"
                        placeholder="************"
                    >
                    <button type="button" class="text-xs font-semibold text-slate-500" @click="showPassword = !showPassword">
                        {{ showPassword ? 'Ocultar' : 'Mostrar' }}
                    </button>
                </div>
            </label>
            <InputError :message="form.errors.password" class="-mt-2" />

            <div class="flex items-center justify-between gap-2 pt-1 text-xs">
                <label class="inline-flex items-center gap-2 text-slate-500">
                    <Checkbox v-model:checked="form.remember" name="remember" class="rounded border-slate-300" />
                    Manter conectado
                </label>
                <Link :href="forgotPasswordUrl" class="font-semibold text-[var(--store-auth-primary)]">Esqueci a senha</Link>
            </div>

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60"
                style="background: var(--store-auth-primary-strong)"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Entrando...' : 'Entrar no painel' }}
            </button>

            <Link
                :href="registerUrl"
                class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Criar conta
            </Link>
        </form>
    </AuthNativeShell>
</template>
