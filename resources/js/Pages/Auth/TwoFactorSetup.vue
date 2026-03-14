<script setup>
import InputError from '@/Components/InputError.vue';
import { useBranding } from '@/branding';
import { Head, router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { computed, ref, watch } from 'vue';

const { brandName, systemIconUrl } = useBranding();
const pageTitle = computed(() => `Autenticacao em dois fatores | ${brandName.value}`);

const props = defineProps({
    required: {
        type: Boolean,
        default: true,
    },
    enabled: {
        type: Boolean,
        default: false,
    },
    isEnabled: {
        type: Boolean,
        default: false,
    },
    secret: {
        type: String,
        default: '',
    },
    qrCode: {
        type: String,
        default: '',
    },
    otpauthUrl: {
        type: String,
        default: '',
    },
    issuer: {
        type: String,
        default: 'Veshop',
    },
    status: {
        type: String,
        default: '',
    },
    recoveryCodes: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    code: '',
});

const disableForm = useForm({});
const recoveryForm = useForm({});

const loginRoute = computed(() => {
    if (typeof route !== 'function') return '/login';
    try {
        return route('login');
    } catch {
        return '/login';
    }
});

const safeDashboard = computed(() => {
    if (typeof route !== 'function') return '/home';
    try {
        return route('home', {}, false);
    } catch {
        return '/home';
    }
});

const isTwoFactorEnabled = computed(() => Boolean(props.enabled || props.isEnabled));
const heading = computed(() =>
    isTwoFactorEnabled.value ? 'Autenticação em dois fatores ativa' : 'Ativar autenticação em dois fatores',
);

const hasRecoveryCodes = computed(() => Array.isArray(props.recoveryCodes) && props.recoveryCodes.length > 0);

const qrCodeData = ref(props.qrCode || '');
const qrCodeError = ref('');

const submit = () => {
    form.post(route('two-factor.confirm'), {
        preserveScroll: true,
        onFinish: () => form.reset('code'),
    });
};

const disable = () => {
    router.delete(route('two-factor.disable'), {
        preserveScroll: true,
    });
};

const regenerateSecret = () => {
    recoveryForm.post(route('two-factor.regenerate'), {
        preserveScroll: true,
    });
};

const returnToLogin = () => {
    router.post(
        route('logout'),
        {},
        {
            onFinish: () => {
                try {
                    localStorage.clear();
                    sessionStorage.clear();
                } catch {
                    // Ignore storage errors in restricted environments.
                }
                window.location.href = loginRoute.value;
            },
        },
    );
};

const renderQrFromOtp = async () => {
    if (props.qrCode) {
        qrCodeData.value = props.qrCode;
        qrCodeError.value = '';
        return;
    }

    if (!props.otpauthUrl) {
        qrCodeData.value = '';
        qrCodeError.value = 'QR Code indisponível no momento.';
        return;
    }

    try {
        qrCodeData.value = await QRCode.toDataURL(props.otpauthUrl, {
            width: 220,
            margin: 1,
            errorCorrectionLevel: 'M',
            color: {
                dark: '#073341',
                light: '#FFFFFFFF',
            },
        });
        qrCodeError.value = '';
    } catch {
        qrCodeData.value = '';
        qrCodeError.value = 'Não foi possível gerar o QR Code agora.';
    }
};

watch(
    () => [props.qrCode, props.otpauthUrl],
    () => {
        void renderQrFromOtp();
    },
    { immediate: true },
);
</script>

<template>
    <div class="veshop-twofa-page relative min-h-screen overflow-hidden text-white">
        <Head :title="pageTitle">
            <link
                head-key="public-sans-font"
                rel="stylesheet"
                href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap"
            />
            <link head-key="landing-remixicon" rel="stylesheet" href="/landing/css/remixicon.css" />
        </Head>

        <div class="absolute inset-0">
            <div class="veshop-twofa-bg-base absolute inset-0 z-[-2] h-full w-full" />
            <div class="veshop-twofa-bg-overlay absolute inset-0 z-[-1] h-full w-full opacity-75" />
            <div class="pointer-events-none absolute inset-0">
                <div class="veshop-twofa-orb veshop-twofa-orb-left" />
                <div class="veshop-twofa-orb veshop-twofa-orb-right" />
            </div>
        </div>

        <div class="relative z-10 flex min-h-screen items-center justify-center px-6 py-10">
            <div
                class="w-full max-w-[1080px] overflow-hidden rounded-3xl border border-white/10 bg-white/95 p-5 text-slate-900 shadow-2xl backdrop-blur lg:p-6"
            >
                <div class="grid gap-4 lg:grid-cols-[1.18fr_0.82fr]">
                    <section class="space-y-3 lg:pr-1">
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2.5">
                                <span class="veshop-twofa-brand-icon">
                                    <img
                                        :src="systemIconUrl"
                                        :alt="`${brandName} icone`"
                                        class="veshop-twofa-brand-icon-img"
                                    />
                                </span>
                                <p class="veshop-twofa-brand-name mb-0">{{ brandName }}</p>
                            </div>
                            <p class="veshop-login-pill mb-0">Segurança do acesso</p>
                            <h1 class="veshop-login-title mt-1">{{ heading }}</h1>
                        </div>

                        <p class="veshop-login-subtitle mt-0">
                            Use o app Authenticator para gerar códigos temporários e proteger sua conta.
                        </p>

                        <div
                            v-if="props.required"
                            class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800"
                        >
                            Obrigatório para este contratante.
                        </div>
                        <div
                            v-if="props.status"
                            class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-700"
                        >
                            {{ props.status }}
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-3.5">
                            <p class="veshop-login-label mb-0">Passo a passo</p>
                            <div class="mt-3 grid gap-2.5">
                                <div class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-[11px] font-semibold text-white">
                                        1
                                    </div>
                                    <div>
                                        <p class="veshop-twofa-step-title">Abra o Authenticator</p>
                                        <p class="veshop-twofa-step-text">Google, Microsoft ou outro app compatível.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-[11px] font-semibold text-white">
                                        2
                                    </div>
                                    <div>
                                        <p class="veshop-twofa-step-title">Escaneie o QR Code</p>
                                        <p class="veshop-twofa-step-text">Ou use o código manual exibido ao lado.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-[11px] font-semibold text-white">
                                        3
                                    </div>
                                    <div>
                                        <p class="veshop-twofa-step-title">Valide o código</p>
                                        <p class="veshop-twofa-step-text">Digite o token de 6 dígitos e finalize.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="attention-pulse overflow-hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700">
                            <strong>Atenção:</strong> se você perder os códigos de recuperação e o Authenticator, poderá ficar
                            sem acesso ao sistema.
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div v-if="isTwoFactorEnabled" class="flex flex-col space-y-4">
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="veshop-twofa-btn-base veshop-twofa-btn-primary"
                                    @click="router.visit(safeDashboard)"
                                >
                                    Acessar o sistema
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path
                                            d="M5 12h14m0 0-5-5m5 5-5 5"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800">
                                Seu dispositivo está configurado.
                            </div>

                            <div
                                v-if="hasRecoveryCodes"
                                class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-xs text-amber-800"
                            >
                                <p class="font-semibold">Códigos de recuperação</p>
                                <p class="mt-1 text-[11px] text-amber-800/80">
                                    Guarde estes códigos em local seguro. Cada código pode ser usado apenas uma vez.
                                </p>
                                <div class="mt-3 grid grid-cols-2 gap-2 text-[11px] font-semibold text-amber-900">
                                    <div v-for="code in props.recoveryCodes" :key="code" class="break-all rounded-lg bg-white px-2 py-1">
                                        {{ code }}
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <button
                                    type="button"
                                    class="veshop-twofa-btn-base veshop-twofa-btn-secondary w-full"
                                    @click="regenerateSecret"
                                    :disabled="recoveryForm.processing"
                                >
                                    Gerar nova chave
                                </button>
                            </div>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">QR Code</p>
                                <p class="mt-1 text-sm text-slate-700">Escaneie o QR Code abaixo com o Authenticator.</p>
                                <div class="mt-4 flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <img v-if="qrCodeData" :src="qrCodeData" alt="QR Code" class="h-40 w-40" />
                                    <p v-else class="text-xs text-slate-500">{{ qrCodeError }}</p>
                                </div>
                                <p class="mt-3 text-xs text-slate-500">
                                    Código manual: <strong class="text-slate-700">{{ props.secret }}</strong>
                                </p>
                            </div>

                            <form class="space-y-4" @submit.prevent="submit">
                                <div>
                                    <label class="veshop-login-label">Validação</label>
                                    <p class="veshop-twofa-helper-text">Digite o código de 6 dígitos.</p>
                                    <input
                                        v-model="form.code"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="6"
                                        class="form-control veshop-login-input veshop-twofa-code-input mt-2"
                                        placeholder="000000"
                                    />
                                    <InputError :message="form.errors.code" class="mt-1" />
                                    <InputError :message="form.errors.general" class="mt-1" />
                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-primary veshop-login-submit veshop-twofa-btn-base veshop-twofa-btn-primary w-full disabled:cursor-not-allowed disabled:opacity-70"
                                    :disabled="form.processing"
                                >
                                    {{ form.processing ? 'Ativando...' : 'Ativar 2FA' }}
                                </button>

                                <button
                                    type="button"
                                    class="veshop-twofa-btn-base veshop-twofa-btn-back mt-3 w-full"
                                    @click="returnToLogin"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path
                                            d="M19 12H5m0 0 5-5m-5 5 5 5"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    Voltar ao login
                                </button>
                            </form>
                        </div>

                        <div v-if="isTwoFactorEnabled && !props.required" class="mt-2">
                            <button
                                type="button"
                                class="veshop-twofa-btn-base veshop-twofa-btn-danger w-full"
                                @click="disable"
                                :disabled="disableForm.processing"
                            >
                                Desativar 2FA
                            </button>
                            <button
                                type="button"
                                class="veshop-twofa-btn-base veshop-twofa-btn-back mt-3 w-full"
                                @click="returnToLogin"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path
                                        d="M19 12H5m0 0 5-5m-5 5 5 5"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                                Voltar ao login
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.veshop-twofa-page {
    font-family: 'Public Sans', sans-serif;
    overflow-x: hidden;
}

.veshop-twofa-brand-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 1px solid rgba(7, 51, 65, 0.72);
    background: #073341;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 20px 35px -24px rgba(2, 17, 22, 0.85);
}

.veshop-twofa-brand-icon-img {
    width: 23px;
    height: 23px;
    object-fit: contain;
}

.veshop-twofa-brand-name {
    margin: 0;
    color: #073341;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.veshop-twofa-bg-base {
    background:
        radial-gradient(circle at top, rgba(129, 216, 111, 0.24) 0%, transparent 60%),
        radial-gradient(120% 120% at 85% 10%, rgba(129, 216, 111, 0.2) 0%, transparent 65%),
        linear-gradient(180deg, #f8fef9 0%, #eef9f1 55%, #e7f5eb 100%);
}

.veshop-twofa-bg-overlay {
    background:
        radial-gradient(120% 120% at 90% 12%, rgba(129, 216, 111, 0.18), transparent),
        radial-gradient(90% 95% at 18% 78%, rgba(7, 51, 65, 0.07), transparent 70%);
}

.veshop-twofa-orb {
    position: absolute;
    border-radius: 999px;
    filter: blur(58px);
}

.veshop-twofa-orb-left {
    left: -120px;
    top: -54px;
    width: 360px;
    height: 360px;
    background: rgba(129, 216, 111, 0.22);
}

.veshop-twofa-orb-right {
    right: -120px;
    bottom: -130px;
    width: 420px;
    height: 420px;
    background: rgba(129, 216, 111, 0.18);
}

.veshop-twofa-step-title {
    margin: 0;
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.3;
}

.veshop-twofa-step-text,
.veshop-twofa-helper-text {
    margin: 0;
    color: #5f7388;
    font-size: 12px;
    line-height: 1.35;
}

.veshop-twofa-code-input {
    letter-spacing: 0.32em;
    text-align: center;
    font-size: 14px;
}

.veshop-twofa-btn-base {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 10px;
    padding: 9px 14px;
    font-size: 13px;
    font-weight: 600;
    transition: 0.2s ease;
    border: 1px solid transparent;
}

.veshop-twofa-btn-primary,
.veshop-twofa-btn-secondary,
.veshop-twofa-btn-danger {
    background-color: #073341;
    border-color: #073341;
    color: #ffffff;
}

.veshop-twofa-btn-primary:hover,
.veshop-twofa-btn-secondary:hover,
.veshop-twofa-btn-danger:hover {
    background-color: #0a4255;
    border-color: #0a4255;
    color: #ffffff;
}

.veshop-twofa-btn-back {
    background-color: transparent;
    border-color: rgba(7, 51, 65, 0.28);
    color: #073341;
}

.veshop-twofa-btn-back:hover {
    background-color: rgba(7, 51, 65, 0.05);
    border-color: rgba(7, 51, 65, 0.5);
    color: #073341;
}

.attention-pulse {
    animation: attentionPulse 2.6s ease-in-out infinite;
}

@keyframes attentionPulse {
    0%,
    100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(244, 63, 94, 0);
    }

    50% {
        transform: scale(1.015);
        box-shadow: 0 0 0 6px rgba(244, 63, 94, 0.18);
    }
}
</style>
