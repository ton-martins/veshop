<script setup>
import InputError from '@/Components/InputError.vue';
import AuthNativeShell from '@/Components/Public/Storefront/AuthNativeShell.vue';
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

const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const registerUrl = computed(() => `/shop/${storeSlug.value}/cadastro`);
const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const shopUrl = computed(() => `/shop/${storeSlug.value}`);

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
        cepLookupError.value = 'CEP invalido. Digite os 8 numeros.';
        return;
    }

    const cepDigits = form.cep.replace(/\D/g, '');
    cepLookupLoading.value = true;

    try {
        const response = await fetch(`https://viacep.com.br/ws/${cepDigits}/json/`);
        if (!response.ok) throw new Error('lookup_failed');

        const payload = await response.json();
        if (payload?.erro) {
            cepLookupError.value = 'CEP nao encontrado.';
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
        cepLookupError.value = 'Nao foi possivel consultar o CEP agora. Preencha manualmente.';
    } finally {
        cepLookupLoading.value = false;
    }
};

const goToStepTwo = () => {
    form.clearErrors(...requiredStepOneFields);

    let hasError = false;

    requiredStepOneFields.forEach((field) => {
        if (!String(form[field] ?? '').trim()) {
            form.setError(field, 'Campo obrigatorio.');
            hasError = true;
        }
    });

    if (form.password && form.password_confirmation && form.password !== form.password_confirmation) {
        form.setError('password_confirmation', 'A confirmacao de senha nao confere.');
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
            form.setError(field, 'Campo obrigatorio.');
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
    <Head :title="`Cadastro | ${storeName}`" />

    <AuthNativeShell
        :contractor="contractor"
        badge="Cadastro"
        title="Crie sua conta"
        subtitle="Fluxo em duas etapas para acelerar compras e pedidos."
        :back-href="shopUrl"
        back-label="Voltar para loja"
        hero-title="Cadastro no mesmo layout da loja"
        hero-description="Experiencia unica para nicho comercio e servicos, com identidade da marca do contratante."
    >
        <div class="mb-5 rounded-2xl border border-slate-200 bg-slate-50 p-3">
            <div class="flex items-center justify-between gap-3">
                <div class="inline-flex items-center gap-2">
                    <span
                        class="inline-flex h-7 w-7 items-center justify-center rounded-full border text-xs font-bold"
                        :class="isStepOne ? 'border-[var(--store-auth-primary-border)] bg-[var(--store-auth-primary-soft)] text-slate-800' : 'border-slate-200 bg-white text-slate-500'"
                    >
                        1
                    </span>
                    <span class="h-px w-6 bg-slate-300"></span>
                    <span
                        class="inline-flex h-7 w-7 items-center justify-center rounded-full border text-xs font-bold"
                        :class="isStepTwo ? 'border-[var(--store-auth-primary-border)] bg-[var(--store-auth-primary-soft)] text-slate-800' : 'border-slate-200 bg-white text-slate-500'"
                    >
                        2
                    </span>
                </div>
                <span class="text-xs font-semibold text-slate-500">{{ stepLabel }}</span>
            </div>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div v-if="isStepOne" class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Nome completo</label>
                    <input
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                        placeholder="Seu nome"
                    >
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                        placeholder="voce@exemplo.com"
                    >
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Telefone</label>
                    <input
                        :value="form.phone"
                        type="text"
                        inputmode="numeric"
                        maxlength="15"
                        autocomplete="tel"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                        placeholder="(11) 99999-9999"
                        @input="onPhoneInput"
                    >
                    <InputError :message="form.errors.phone" class="mt-1" />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Senha</label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                        placeholder="Crie uma senha"
                    >
                    <InputError :message="form.errors.password" class="mt-1" />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Confirmar senha</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                        placeholder="Repita a senha"
                    >
                    <InputError :message="form.errors.password_confirmation" class="mt-1" />
                </div>
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="min-w-[180px] flex-1">
                            <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">CEP</label>
                            <input
                                :value="form.cep"
                                type="text"
                                inputmode="numeric"
                                maxlength="9"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                                placeholder="00000-000"
                                @input="onCepInput"
                                @blur="lookupCep"
                            >
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="cepLookupLoading"
                            @click="lookupCep"
                        >
                            {{ cepLookupLoading ? 'Consultando...' : 'Consultar CEP' }}
                        </button>
                    </div>
                    <InputError :message="form.errors.cep" class="mt-1" />
                    <p v-if="cepLookupError" class="mt-1 text-xs font-semibold text-amber-700">{{ cepLookupError }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Logradouro</label>
                    <input
                        v-model="form.street"
                        type="text"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                        placeholder="Rua, avenida, etc"
                    >
                    <InputError :message="form.errors.street" class="mt-1" />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Numero</label>
                    <input
                        v-model="form.number"
                        type="text"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                        placeholder="123"
                    >
                    <InputError :message="form.errors.number" class="mt-1" />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Complemento</label>
                    <input
                        v-model="form.complement"
                        type="text"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                        placeholder="Apto, bloco, etc"
                    >
                    <InputError :message="form.errors.complement" class="mt-1" />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Bairro</label>
                    <input
                        v-model="form.neighborhood"
                        type="text"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                        placeholder="Bairro"
                    >
                    <InputError :message="form.errors.neighborhood" class="mt-1" />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Cidade</label>
                    <input
                        v-model="form.city"
                        type="text"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                        placeholder="Cidade"
                    >
                    <InputError :message="form.errors.city" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">UF</label>
                    <select
                        v-model="form.state"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-[var(--store-auth-primary-border)] focus:ring-2 focus:ring-[var(--store-auth-ring)]"
                    >
                        <option v-for="option in stateOptions" :key="`shop-register-state-${option.value || 'empty'}`" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.state" class="mt-1" />
                </div>
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <button
                    v-if="isStepTwo"
                    type="button"
                    class="inline-flex min-w-[120px] flex-1 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="backToStepOne"
                >
                    Voltar
                </button>

                <button
                    v-if="isStepOne"
                    type="button"
                    class="inline-flex min-w-[120px] flex-1 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm"
                    style="background: var(--store-auth-primary-strong)"
                    @click="goToStepTwo"
                >
                    Continuar
                </button>

                <button
                    v-else
                    type="submit"
                    class="inline-flex min-w-[120px] flex-1 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                    style="background: var(--store-auth-primary-strong)"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Criando conta...' : 'Criar conta' }}
                </button>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Ja tem conta?
            <Link :href="loginUrl" class="font-semibold text-[var(--store-auth-primary)]">Entrar</Link>
        </p>
    </AuthNativeShell>
</template>
