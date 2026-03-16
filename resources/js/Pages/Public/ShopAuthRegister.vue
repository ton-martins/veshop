<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Home, UserPlus } from 'lucide-vue-next';
import { useBranding } from '@/branding';
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
const storeLogo = computed(() => props.contractor?.avatar_url || props.contractor?.logo_url || null);
const { normalizeHex, primaryColor, withAlpha, themeStyles } = useBranding();
const storePrimaryColor = computed(() => normalizeHex(props.contractor?.primary_color || '', primaryColor.value));

const stateOptions = computed(() => ([
    { value: '', label: 'Selecione a UF' },
    ...BRAZIL_STATES.map((state) => ({
        value: state.code,
        label: `${state.code} - ${state.name}`,
    })),
]));

const cepLookupLoading = ref(false);
const cepLookupError = ref('');

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
    };
});

const pageStyles = computed(() => {
    const c = storePrimaryColor.value;

    return {
        ...themeStyles.value,
        '--shop-primary': c,
        '--shop-primary-soft': withAlpha(c, 0.12),
        '--shop-primary-strong': withAlpha(c, 0.92),
        '--shop-gradient': `linear-gradient(145deg, ${withAlpha(c, 0.18)} 0%, rgba(255,255,255,0.96) 60%, ${withAlpha(c, 0.06)} 100%)`,
    };
});

const registerUrl = computed(() => `/shop/${storeSlug.value}/cadastro`);
const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const shopUrl = computed(() => `/shop/${storeSlug.value}`);

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
        cepLookupError.value = 'Não foi possível consultar o ViaCEP agora. Preencha manualmente.';
    } finally {
        cepLookupLoading.value = false;
    }
};

const submit = () => {
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

    <div class="min-h-screen bg-slate-100 px-4 py-8 text-slate-900 sm:px-6 lg:px-8" :style="pageStyles">
        <div class="mx-auto w-full max-w-3xl">
            <div class="mb-4 flex items-center justify-between gap-3">
                <Link :href="shopUrl" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    <Home class="h-3.5 w-3.5" />
                    Voltar para a loja
                </Link>
            </div>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="relative border-b border-slate-200 px-5 py-5 sm:px-6">
                    <div class="pointer-events-none absolute inset-0 opacity-90" style="background: var(--shop-gradient)"></div>
                    <div class="relative flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-slate-100" :style="storeIconStyle">
                            <img v-if="storeLogo" :src="storeLogo" :alt="storeName" class="h-full w-full object-cover" />
                            <span v-else class="text-xs font-semibold">{{ storeInitials }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ storeName }}</p>
                            <p class="text-xs text-slate-500">Crie sua conta para acompanhar pedidos.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome completo</label>
                            <input
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Seu nome"
                            >
                            <p v-if="form.errors.name" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.name }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                            <input
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="nome@exemplo.com"
                            >
                            <p v-if="form.errors.email" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.email }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                            <input
                                :value="form.phone"
                                type="text"
                                inputmode="numeric"
                                maxlength="15"
                                autocomplete="tel"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="(11) 99999-9999"
                                @input="onPhoneInput"
                            >
                            <p v-if="form.errors.phone" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.phone }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <div class="flex flex-wrap items-end gap-2">
                                <div class="min-w-[180px] flex-1">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">CEP (opcional)</label>
                                    <input
                                        :value="form.cep"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="9"
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
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
                            <p v-if="form.errors.cep" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.cep }}</p>
                            <p v-if="cepLookupError" class="mt-2 text-xs font-semibold text-amber-700">{{ cepLookupError }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rua</label>
                            <input v-model="form.street" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Logradouro">
                            <p v-if="form.errors.street" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.street }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Número</label>
                            <input v-model="form.number" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="123">
                            <p v-if="form.errors.number" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.number }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Complemento</label>
                            <input v-model="form.complement" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Apto, bloco, etc">
                            <p v-if="form.errors.complement" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.complement }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bairro</label>
                            <input v-model="form.neighborhood" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Bairro">
                            <p v-if="form.errors.neighborhood" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.neighborhood }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cidade</label>
                            <input v-model="form.city" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Cidade">
                            <p v-if="form.errors.city" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.city }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">UF</label>
                            <select v-model="form.state" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                <option v-for="option in stateOptions" :key="`state-register-${option.value || 'empty'}`" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.state" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.state }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Senha</label>
                            <input
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Crie uma senha"
                            >
                            <p v-if="form.errors.password" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.password }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Confirmar senha</label>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Repita a senha"
                            >
                            <p v-if="form.errors.password_confirmation" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.password_confirmation }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                                style="background: var(--shop-primary-strong)"
                                :disabled="form.processing"
                            >
                                <UserPlus class="h-4 w-4" />
                                {{ form.processing ? 'Criando conta...' : 'Criar conta' }}
                            </button>
                        </div>
                    </form>

                    <p class="text-center text-xs text-slate-600">
                        Já possui conta?
                        <Link :href="loginUrl" class="font-semibold text-slate-800 underline decoration-dotted underline-offset-2">
                            Entrar
                        </Link>
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>

