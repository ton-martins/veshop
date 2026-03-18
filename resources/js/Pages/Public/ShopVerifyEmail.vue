<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useBranding } from '@/branding';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    contractor: { type: Object, required: true },
    customer: { type: Object, required: true },
});

const page = usePage();
const status = computed(() => String(page.props?.flash?.status ?? '').trim());
const verificationLinkSent = computed(() => status.value === 'verification-link-sent');

const { normalizeHex, primaryColor, withAlpha, themeStyles } = useBranding();

const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const normalizeBrandAsset = (value) => {
    const safe = String(value ?? '').trim();
    return safe !== '' ? safe : null;
};
const storeLogoLoadFailed = ref(false);
const rawStoreLogo = computed(() =>
    normalizeBrandAsset(props.contractor?.avatar_url) || normalizeBrandAsset(props.contractor?.logo_url),
);
const storeLogo = computed(() => (storeLogoLoadFailed.value ? null : rawStoreLogo.value));
const customerEmail = computed(() => String(props.customer?.email || '').trim());
const storePrimaryColor = computed(() => normalizeHex(props.contractor?.primary_color || '', primaryColor.value));
const currentYear = new Date().getFullYear();

const pageStyles = computed(() => {
    const c = storePrimaryColor.value;

    return {
        ...themeStyles.value,
        '--shop-auth-primary': c,
        '--shop-auth-primary-hover': withAlpha(c, 0.9),
    };
});

const storeInitials = computed(() => {
    const safe = String(storeName.value || '').trim();
    if (!safe) return 'LJ';

    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) || '';
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';

    return `${first}${last}`.toUpperCase() || 'LJ';
});
const storeInitialsColor = computed(() => {
    const normalized = storePrimaryColor.value.slice(1);
    const red = Number.parseInt(normalized.slice(0, 2), 16);
    const green = Number.parseInt(normalized.slice(2, 4), 16);
    const blue = Number.parseInt(normalized.slice(4, 6), 16);
    const luminance = ((red * 299) + (green * 587) + (blue * 114)) / 255000;

    return luminance > 0.62 ? '#0f172a' : '#ffffff';
});
const storeIconStyle = computed(() => {
    if (storeLogo.value) return null;

    return {
        background: storePrimaryColor.value,
        color: storeInitialsColor.value,
        borderColor: withAlpha(storePrimaryColor.value, 0.6),
    };
});

const shopUrl = computed(() => `/shop/${storeSlug.value}`);
const accountUrl = computed(() => `/shop/${storeSlug.value}/conta`);
const resendUrl = computed(() => `/shop/${storeSlug.value}/email/verificacao/reenviar`);
const logoutUrl = computed(() => `/shop/${storeSlug.value}/sair`);

const resendForm = useForm({});
const logoutForm = useForm({});

watch(
    () => [props.contractor?.avatar_url, props.contractor?.logo_url],
    () => {
        storeLogoLoadFailed.value = false;
    },
);

const handleStoreLogoError = () => {
    storeLogoLoadFailed.value = true;
};

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
    <GuestLayout :show-aside="false">
        <Head :title="`Verificar e-mail | ${storeName}`" />

        <template #brand>
            <Link :href="shopUrl" class="d-inline-flex align-items-center gap-3 text-decoration-none">
                <span class="veshop-auth-logo" :style="storeIconStyle">
                    <img
                        v-if="storeLogo"
                        :src="storeLogo"
                        :alt="storeName"
                        class="veshop-auth-logo-img"
                        @error="handleStoreLogoError"
                    />
                    <span v-else class="shop-contractor-initials">{{ storeInitials }}</span>
                </span>
                <span class="d-block fs-5 fw-bold text-primary ls-1">{{ storeName }}</span>
            </Link>
        </template>

        <template #footer>
            &copy; {{ currentYear }} {{ storeName }}. Todos os direitos reservados.
        </template>

        <div class="shop-auth-theme" :style="pageStyles">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="veshop-login-pill">Validação de conta</span>
                <Link :href="shopUrl" class="veshop-login-link">Voltar para a loja</Link>
            </div>

            <h1 class="veshop-login-title">Verificar e-mail</h1>
            <p class="veshop-login-subtitle">
                Confirme seu endereço de e-mail para continuar seu cadastro e finalizar pedidos.
            </p>

            <div class="alert alert-warning mt-3 mb-0 py-2" role="alert">
                Link enviado para: <strong>{{ customerEmail || 'seu e-mail cadastrado' }}</strong>.
            </div>

            <div
                v-if="verificationLinkSent"
                class="alert alert-success mt-3 mb-0 py-2"
                role="alert"
            >
                Enviamos um novo link de verificação para seu e-mail.
            </div>

            <div
                v-else-if="status && status !== 'verification-link-sent'"
                class="alert alert-info mt-3 mb-0 py-2"
                role="alert"
            >
                {{ status }}
            </div>

            <div class="d-grid gap-2 mt-3">
                <button
                    type="button"
                    class="btn btn-primary veshop-login-submit shop-auth-submit w-100"
                    :class="{ 'opacity-75': resendForm.processing }"
                    :disabled="resendForm.processing"
                    @click="resendVerification"
                >
                    <span v-if="resendForm.processing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    {{ resendForm.processing ? 'Enviando...' : 'Reenviar verificação' }}
                </button>

                <Link
                    :href="accountUrl"
                    class="btn btn-outline-secondary veshop-login-submit w-100"
                >
                    Já confirmei meu e-mail
                </Link>

                <button
                    type="button"
                    class="btn btn-outline-danger veshop-login-submit w-100"
                    :class="{ 'opacity-75': logoutForm.processing }"
                    :disabled="logoutForm.processing"
                    @click="logout"
                >
                    Sair da conta
                </button>
            </div>
        </div>
    </GuestLayout>
</template>

<style scoped>
.shop-auth-submit {
    background-color: var(--shop-auth-primary) !important;
    border-color: var(--shop-auth-primary) !important;
}

.shop-auth-submit:hover,
.shop-auth-submit:focus {
    background-color: var(--shop-auth-primary-hover) !important;
    border-color: var(--shop-auth-primary-hover) !important;
}

.shop-contractor-initials {
    font-size: 0.78rem;
    font-weight: 700;
    color: #ffffff;
}
</style>
