<script setup>
import InputError from '@/Components/InputError.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useBranding } from '@/branding';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    BRAZIL_STATES,
    formatCepBR,
    formatPhoneBR,
    normalizeStateCode,
    viaCepToAddress,
} from '@/utils/br';

const props = defineProps({
    contractor: { type: Object, required: true },
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

const registerUrl = computed(() => `/shop/${storeSlug.value}/cadastro`);
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

const stateOptions = computed(() => ([
    { value: '', label: 'Selecione a UF' },
    ...BRAZIL_STATES.map((state) => ({
        value: state.code,
        label: `${state.code} - ${state.name}`,
    })),
]));

const wizardStep = ref(1);
const cepLookupLoading = ref(false);
const cepLookupError = ref('');

const form = useForm({
    name: '',
    email: '',
    phone: '',
    cep: '',
    street: '',
    number: '',
    complement: '',
    neighborhood: '',
    city: '',
    state: '',
    password: '',
    password_confirmation: '',
});

const requiredStepOneFields = ['name', 'email', 'password', 'password_confirmation'];
const requiredStepTwoFields = ['cep', 'street', 'neighborhood', 'city', 'state'];

const stepLabel = computed(() => `Etapa ${wizardStep.value} de 2`);
const isStepOne = computed(() => wizardStep.value === 1);
const isStepTwo = computed(() => wizardStep.value === 2);

watch(
    () => form.errors,
    (errors) => {
        if (requiredStepOneFields.some((field) => Boolean(errors?.[field]))) {
            wizardStep.value = 1;
        }
    },
    { deep: true },
);

watch(
    () => [props.contractor?.avatar_url, props.contractor?.logo_url],
    () => {
        storeLogoLoadFailed.value = false;
    },
);

const handleStoreLogoError = () => {
    storeLogoLoadFailed.value = true;
};

const onPhoneInput = (event) => {
    form.phone = formatPhoneBR(event?.target?.value ?? form.phone);
};

const onCepInput = (event) => {
    form.cep = formatCepBR(event?.target?.value ?? form.cep);
};

const lookupCep = async () => {
    cepLookupError.value = '';
    form.cep = formatCepBR(form.cep);

    if (!form.cep) return;
    if (form.cep.length !== 9) {
        cepLookupError.value = 'CEP inválido. Digite os 8 números.';
        return;
    }

    const cepDigits = form.cep.replace(/\D/g, '');
    cepLookupLoading.value = true;

    try {
        const response = await fetch(`https://viacep.com.br/ws/${cepDigits}/json/`);
        if (!response.ok) throw new Error('lookup_failed');

        const payload = await response.json();
        if (payload?.erro) {
            cepLookupError.value = 'CEP não encontrado.';
            return;
        }

        const parsed = viaCepToAddress(payload);
        form.cep = parsed.cep || form.cep;
        form.street = parsed.street || form.street;
        form.neighborhood = parsed.neighborhood || form.neighborhood;
        form.city = parsed.city || form.city;
        form.state = parsed.state || form.state;

        if (!String(form.complement ?? '').trim()) {
            form.complement = parsed.complement || '';
        }
    } catch {
        cepLookupError.value = 'Não foi possível consultar o CEP agora. Preencha manualmente.';
    } finally {
        cepLookupLoading.value = false;
    }
};

const goToStepTwo = () => {
    form.clearErrors(...requiredStepOneFields);

    let hasError = false;

    requiredStepOneFields.forEach((field) => {
        if (!String(form[field] ?? '').trim()) {
            form.setError(field, 'Este campo é obrigatório.');
            hasError = true;
        }
    });

    if (form.password && form.password_confirmation && form.password !== form.password_confirmation) {
        form.setError('password_confirmation', 'A confirmação de senha não confere.');
        hasError = true;
    }

    if (hasError) return;

    wizardStep.value = 2;
};

const backToStepOne = () => {
    wizardStep.value = 1;
};

const submit = () => {
    if (!isStepTwo.value) {
        goToStepTwo();
        return;
    }

    form.clearErrors(...requiredStepTwoFields);
    let hasError = false;

    requiredStepTwoFields.forEach((field) => {
        if (!String(form[field] ?? '').trim()) {
            form.setError(field, 'Este campo é obrigatório.');
            hasError = true;
        }
    });

    if (hasError) return;

    form.phone = formatPhoneBR(form.phone);
    form.cep = formatCepBR(form.cep);
    form.state = normalizeStateCode(form.state);

    form.post(registerUrl.value, {
        preserveScroll: true,
    });
};
</script>

<template>
    <GuestLayout :show-aside="false">
        <Head :title="`Cadastro | ${storeName}`" />

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
                <span class="veshop-login-pill">Cadastro da loja virtual</span>
                <Link :href="shopUrl" class="veshop-login-link">Voltar para a loja</Link>
            </div>

            <h1 class="veshop-login-title">Criar conta em {{ storeName }}</h1>
            <p class="veshop-login-subtitle">
                Faça seu cadastro em duas etapas para comprar e acompanhar seus pedidos.
            </p>

            <div class="shop-wizard-header mt-3">
                <div class="shop-wizard-steps">
                    <span class="shop-wizard-badge" :class="{ 'is-active': isStepOne }">1</span>
                    <span class="shop-wizard-divider"></span>
                    <span class="shop-wizard-badge" :class="{ 'is-active': isStepTwo }">2</span>
                </div>
                <span class="shop-wizard-label">{{ stepLabel }}</span>
            </div>

            <form class="mt-3" @submit.prevent="submit">
                <div v-if="isStepOne" class="row g-2">
                    <div class="col-12">
                        <label for="shop-register-name" class="veshop-login-label">Nome completo</label>
                        <input
                            id="shop-register-name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            class="form-control veshop-login-input"
                            placeholder="Seu nome"
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div class="col-12">
                        <label for="shop-register-email" class="veshop-login-label">E-mail</label>
                        <input
                            id="shop-register-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            class="form-control veshop-login-input"
                            placeholder="voce@exemplo.com"
                        />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>

                    <div class="col-12">
                        <label for="shop-register-phone" class="veshop-login-label">Telefone</label>
                        <input
                            id="shop-register-phone"
                            :value="form.phone"
                            type="text"
                            inputmode="numeric"
                            maxlength="15"
                            autocomplete="tel"
                            class="form-control veshop-login-input"
                            placeholder="(11) 99999-9999"
                            @input="onPhoneInput"
                        />
                        <InputError :message="form.errors.phone" class="mt-1" />
                    </div>

                    <div class="col-sm-6">
                        <label for="shop-register-password" class="veshop-login-label">Senha</label>
                        <input
                            id="shop-register-password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            class="form-control veshop-login-input"
                            placeholder="Crie uma senha"
                        />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>

                    <div class="col-sm-6">
                        <label for="shop-register-password-confirmation" class="veshop-login-label">Confirmar senha</label>
                        <input
                            id="shop-register-password-confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="form-control veshop-login-input"
                            placeholder="Repita a senha"
                        />
                        <InputError :message="form.errors.password_confirmation" class="mt-1" />
                    </div>
                </div>

                <div v-else class="row g-2">
                    <div class="col-12">
                        <div class="d-flex flex-wrap align-items-end gap-2">
                            <div class="flex-grow-1">
                                <label for="shop-register-cep" class="veshop-login-label">CEP</label>
                                <input
                                    id="shop-register-cep"
                                    :value="form.cep"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="9"
                                    class="form-control veshop-login-input"
                                    placeholder="00000-000"
                                    @input="onCepInput"
                                    @blur="lookupCep"
                                />
                            </div>
                            <button
                                type="button"
                                class="btn btn-outline-secondary h-100"
                                :disabled="cepLookupLoading"
                                @click="lookupCep"
                            >
                                {{ cepLookupLoading ? 'Consultando...' : 'Consultar CEP' }}
                            </button>
                        </div>
                        <InputError :message="form.errors.cep" class="mt-1" />
                        <p v-if="cepLookupError" class="mt-1 text-xs font-semibold text-amber-700">{{ cepLookupError }}</p>
                    </div>

                    <div class="col-12">
                        <label for="shop-register-street" class="veshop-login-label">Logradouro</label>
                        <input
                            id="shop-register-street"
                            v-model="form.street"
                            type="text"
                            class="form-control veshop-login-input"
                            placeholder="Rua, avenida, etc."
                        />
                        <InputError :message="form.errors.street" class="mt-1" />
                    </div>

                    <div class="col-sm-6">
                        <label for="shop-register-number" class="veshop-login-label">Número</label>
                        <input
                            id="shop-register-number"
                            v-model="form.number"
                            type="text"
                            class="form-control veshop-login-input"
                            placeholder="123"
                        />
                        <InputError :message="form.errors.number" class="mt-1" />
                    </div>

                    <div class="col-sm-6">
                        <label for="shop-register-complement" class="veshop-login-label">Complemento</label>
                        <input
                            id="shop-register-complement"
                            v-model="form.complement"
                            type="text"
                            class="form-control veshop-login-input"
                            placeholder="Apto, bloco, etc."
                        />
                        <InputError :message="form.errors.complement" class="mt-1" />
                    </div>

                    <div class="col-sm-6">
                        <label for="shop-register-neighborhood" class="veshop-login-label">Bairro</label>
                        <input
                            id="shop-register-neighborhood"
                            v-model="form.neighborhood"
                            type="text"
                            class="form-control veshop-login-input"
                            placeholder="Bairro"
                        />
                        <InputError :message="form.errors.neighborhood" class="mt-1" />
                    </div>

                    <div class="col-sm-6">
                        <label for="shop-register-city" class="veshop-login-label">Cidade</label>
                        <input
                            id="shop-register-city"
                            v-model="form.city"
                            type="text"
                            class="form-control veshop-login-input"
                            placeholder="Cidade"
                        />
                        <InputError :message="form.errors.city" class="mt-1" />
                    </div>

                    <div class="col-12">
                        <label for="shop-register-state" class="veshop-login-label">UF</label>
                        <select
                            id="shop-register-state"
                            v-model="form.state"
                            class="form-select veshop-login-input"
                        >
                            <option v-for="option in stateOptions" :key="`shop-register-state-${option.value || 'empty'}`" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.state" class="mt-1" />
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button
                        v-if="isStepTwo"
                        type="button"
                        class="btn btn-outline-secondary veshop-login-submit w-100"
                        @click="backToStepOne"
                    >
                        Voltar
                    </button>
                    <button
                        v-if="isStepOne"
                        type="button"
                        class="btn btn-primary veshop-login-submit shop-auth-submit w-100"
                        @click="goToStepTwo"
                    >
                        Continuar
                    </button>
                    <button
                        v-else
                        type="submit"
                        class="btn btn-primary veshop-login-submit shop-auth-submit w-100"
                        :class="{ 'opacity-75': form.processing }"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        {{ form.processing ? 'Criando conta...' : 'Criar conta' }}
                    </button>
                </div>
            </form>

            <p class="veshop-login-note mt-3 mb-0">
                Já possui conta?
                <Link :href="loginUrl" class="veshop-login-link">Entrar</Link>
            </p>
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

.shop-wizard-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.shop-wizard-steps {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.shop-wizard-badge {
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 999px;
    border: 1px solid rgba(100, 116, 139, 0.4);
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
}

.shop-wizard-badge.is-active {
    border-color: var(--shop-auth-primary);
    color: #ffffff;
    background: var(--shop-auth-primary);
}

.shop-wizard-divider {
    width: 1.5rem;
    height: 1px;
    background: rgba(100, 116, 139, 0.45);
}

.shop-wizard-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
}
</style>
