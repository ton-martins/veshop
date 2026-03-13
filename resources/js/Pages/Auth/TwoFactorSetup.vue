<script setup>
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import BRANDING from '@/branding';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { ref, watch } from 'vue';

const props = defineProps({
    isEnabled: {
        type: Boolean,
        default: false,
    },
    secret: {
        type: String,
        default: '',
    },
    otpauthUrl: {
        type: String,
        default: '',
    },
    status: {
        type: String,
        default: null,
    },
});

const qrCodeDataUrl = ref('');
const qrCodeError = ref('');
const isGeneratingQr = ref(false);

const confirmForm = useForm({
    code: '',
});

const renderQrCode = async () => {
    if (!props.otpauthUrl) {
        qrCodeDataUrl.value = '';
        qrCodeError.value = 'URL do autenticador não disponível para gerar o QR code.';

        return;
    }

    isGeneratingQr.value = true;

    try {
        qrCodeDataUrl.value = await QRCode.toDataURL(props.otpauthUrl, {
            width: 240,
            margin: 1,
            errorCorrectionLevel: 'M',
            color: {
                dark: '#112240',
                light: '#FFFFFFFF',
            },
        });
        qrCodeError.value = '';
    } catch {
        qrCodeDataUrl.value = '';
        qrCodeError.value = 'Não foi possível gerar o QR code. Tente novamente em alguns segundos.';
    } finally {
        isGeneratingQr.value = false;
    }
};

watch(
    () => props.otpauthUrl,
    () => {
        void renderQrCode();
    },
    { immediate: true },
);

const confirmSetup = () => {
    confirmForm.post(route('two-factor.confirm'), {
        preserveScroll: true,
        onFinish: () => confirmForm.reset('code'),
    });
};

const regenerateSecret = () => {
    router.post(route('two-factor.regenerate'));
};

const disableTwoFactor = () => {
    router.delete(route('two-factor.disable'));
};
</script>

<template>
    <Head title="Configurar 2FA" />

    <div class="relative min-h-screen overflow-x-hidden">
        <div class="veshop-shell absolute inset-0 -z-20"></div>
        <div class="veshop-grid absolute inset-0 -z-10 opacity-80"></div>

        <div class="relative mx-auto flex min-h-screen w-full max-w-6xl items-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid w-full gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                <section class="veshop-card p-6 sm:p-8">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="veshop-kicker text-xs font-bold uppercase tracking-[0.2em]">Segurança da conta</p>
                            <h1 class="mt-2 font-display text-3xl font-extrabold text-slate-900 sm:text-4xl">
                                Ative a autenticação em dois fatores
                            </h1>
                        </div>
                        <Link href="/" class="veshop-btn-secondary rounded-xl px-4 py-2 text-sm font-semibold transition">
                            Voltar ao site
                        </Link>
                    </div>

                    <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base">
                        Use seu app autenticador (Google Authenticator, Microsoft Authenticator ou similar), escaneie o QR code e informe o código de 6 dígitos para concluir.
                    </p>
                    <p class="mt-2 text-xs font-medium text-slate-500">
                        Conta: {{ BRANDING.appName }}
                    </p>

                    <div
                        v-if="status"
                        class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                    >
                        {{ status }}
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <article class="veshop-chip rounded-2xl p-5">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">QR code</p>
                            <div class="mt-3 flex min-h-[248px] items-center justify-center rounded-xl border border-slate-200 bg-white p-4">
                                <img
                                    v-if="qrCodeDataUrl"
                                    :src="qrCodeDataUrl"
                                    alt="QR code para autenticação em dois fatores"
                                    class="h-56 w-56 rounded-lg"
                                />
                                <p v-else-if="isGeneratingQr" class="text-sm font-semibold text-slate-500">
                                    Gerando QR code...
                                </p>
                                <p v-else class="text-sm font-semibold text-rose-600">
                                    QR code indisponível
                                </p>
                            </div>
                            <p v-if="qrCodeError" class="mt-3 text-xs font-semibold text-rose-600">
                                {{ qrCodeError }}
                            </p>
                        </article>

                        <article class="veshop-chip rounded-2xl p-5">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Chave secreta</p>
                            <p class="mt-3 font-mono text-base font-semibold tracking-[0.1em] text-slate-900">
                                {{ secret }}
                            </p>
                        </article>

                        <article class="veshop-chip rounded-2xl p-5 md:col-span-2">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">URL otpauth</p>
                            <p class="mt-3 break-all text-xs font-medium text-slate-700">{{ otpauthUrl }}</p>
                        </article>
                    </div>

                    <form class="mt-7" @submit.prevent="confirmSetup">
                        <label for="code" class="text-sm font-semibold text-slate-700">Código do app autenticador</label>
                        <TextInput
                            id="code"
                            v-model="confirmForm.code"
                            type="text"
                            class="mt-2 block w-full !rounded-xl !border-slate-200 !bg-white/90 !px-3 !py-2 !text-sm !text-slate-900 focus:!border-sky-500 focus:!ring-sky-500/30"
                            inputmode="numeric"
                            maxlength="8"
                            placeholder="000 000"
                            required
                        />
                        <InputError class="mt-2" :message="confirmForm.errors.code" />

                        <button
                            type="submit"
                            class="veshop-btn-primary mt-5 inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-semibold transition"
                            :class="{ 'opacity-60': confirmForm.processing }"
                            :disabled="confirmForm.processing"
                        >
                            Confirmar autenticação em dois fatores
                        </button>
                    </form>
                </section>

                <section class="veshop-card p-6 sm:p-8">
                    <h2 class="font-display text-2xl font-extrabold text-slate-900">Gerenciar 2FA</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">
                        Se você trocar de celular ou app autenticador, gere uma nova chave e confirme novamente.
                    </p>

                    <div class="mt-6 space-y-3">
                        <button
                            type="button"
                            class="veshop-btn-secondary inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold transition"
                            @click="regenerateSecret"
                        >
                            Gerar nova chave
                        </button>

                        <button
                            v-if="props.isEnabled"
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                            @click="disableTwoFactor"
                        >
                            Desativar autenticação em dois fatores
                        </button>
                    </div>

                    <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Status atual</p>
                        <p class="mt-3 text-sm font-semibold text-slate-800">
                            {{ props.isEnabled ? '2FA ativo e confirmado.' : '2FA pendente de confirmação.' }}
                        </p>
                        <p class="mt-2 text-xs text-slate-600">
                            Após confirmar, todas as rotas protegidas exigirão segundo fator no login.
                        </p>
                    </div>

                    <div class="mt-6">
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="text-sm font-semibold text-slate-600 underline underline-offset-4 transition hover:text-slate-900"
                        >
                            Sair da conta
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
