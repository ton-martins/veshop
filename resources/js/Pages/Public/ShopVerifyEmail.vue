<script setup>
import AuthNativeShell from '@/Components/Public/Storefront/AuthNativeShell.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    contractor: { type: Object, required: true },
    customer: { type: Object, required: true },
});

const page = usePage();
const status = computed(() => String(page.props?.flash?.status ?? '').trim());
const verificationLinkSent = computed(() => status.value === 'verification-link-sent');

const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const customerEmail = computed(() => String(props.customer?.email || '').trim());

const shopUrl = computed(() => `/shop/${storeSlug.value}`);
const accountUrl = computed(() => `/shop/${storeSlug.value}/conta`);
const resendUrl = computed(() => `/shop/${storeSlug.value}/email/verificacao/reenviar`);
const logoutUrl = computed(() => `/shop/${storeSlug.value}/sair`);

const resendForm = useForm({});
const logoutForm = useForm({});

const resendVerification = () => {
    resendForm.post(resendUrl.value, {
        preserveScroll: true,
    });
};

const logout = () => {
    logoutForm.post(logoutUrl.value);
};
</script>

<template>
    <Head :title="`Verificar e-mail | ${storeName}`" />

    <AuthNativeShell
        :contractor="contractor"
        badge="Validação"
        title="Verifique seu e-mail"
        subtitle="Confirme o e-mail para liberar checkout e recursos da conta."
        :back-href="shopUrl"
        back-label="Voltar para loja"
        hero-title="Proteção ativa de conta"
        hero-description="A verificação de e-mail faz parte do fluxo padrão e garante segurança no cadastro."
    >
        <div class="space-y-4">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Link enviado para <span class="font-semibold">{{ customerEmail || 'seu e-mail cadastrado' }}</span>.
            </div>

            <div
                v-if="verificationLinkSent"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
            >
                Enviamos um novo link de verificação para seu e-mail.
            </div>

            <div
                v-else-if="status && status !== 'verification-link-sent'"
                class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700"
            >
                {{ status }}
            </div>

            <button
                type="button"
                class="inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                style="background: var(--store-auth-primary-strong)"
                :disabled="resendForm.processing"
                @click="resendVerification"
            >
                {{ resendForm.processing ? 'Enviando...' : 'Reenviar verificação' }}
            </button>

            <Link
                :href="accountUrl"
                class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Já confirmei meu e-mail
            </Link>

            <button
                type="button"
                class="inline-flex w-full items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="logoutForm.processing"
                @click="logout"
            >
                Sair da conta
            </button>
        </div>
    </AuthNativeShell>
</template>
