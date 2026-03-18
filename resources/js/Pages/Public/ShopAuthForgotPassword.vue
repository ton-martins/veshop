<script setup>
import InputError from '@/Components/InputError.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useBranding } from '@/branding';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    contractor: { type: Object, required: true },
    status: {
        type: String,
        default: null,
    },
});

const { normalizeHex, primaryColor, themeStyles, withAlpha } = useBranding();

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
const storePrimaryColor = computed(() => normalizeHex(props.contractor?.primary_color || '', primaryColor.value));
const currentYear = new Date().getFullYear();

const forgotUrl = computed(() => `/shop/${storeSlug.value}/esqueci-senha`);
const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const shopUrl = computed(() => `/shop/${storeSlug.value}`);

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

const form = useForm({
    email: '',
});

watch(
    () => [props.contractor?.avatar_url, props.contractor?.logo_url],
    () => {
        storeLogoLoadFailed.value = false;
    },
);

const handleStoreLogoError = () => {
    storeLogoLoadFailed.value = true;
};

const submit = () => {
    form.post(forgotUrl.value, {
        preserveScroll: true,
    });
};
</script>

<template>
    <GuestLayout :show-aside="false">
        <Head :title="`Recuperar senha | ${storeName}`" />

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
                <span class="veshop-login-pill">Recuperação de acesso</span>
                <Link :href="loginUrl" class="veshop-login-link">Voltar para login</Link>
            </div>

            <h1 class="veshop-login-title">Esqueci minha senha</h1>
            <p class="veshop-login-subtitle">
                Informe o e-mail da sua conta e enviaremos um link para redefinir a senha.
            </p>

            <div v-if="props.status" class="alert alert-success mt-3 mb-0 py-2" role="alert">
                {{ props.status }}
            </div>

            <form class="mt-3" @submit.prevent="submit">
                <div class="mb-2">
                    <label for="shop-forgot-email" class="veshop-login-label">E-mail</label>
                    <input
                        id="shop-forgot-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        autofocus
                        class="form-control veshop-login-input"
                        placeholder="voce@exemplo.com"
                    />
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>

                <button
                    type="submit"
                    class="btn btn-primary veshop-login-submit shop-auth-submit mt-3 w-100"
                    :class="{ 'opacity-75': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    {{ form.processing ? 'Enviando...' : 'Enviar link de recuperação' }}
                </button>
            </form>
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
