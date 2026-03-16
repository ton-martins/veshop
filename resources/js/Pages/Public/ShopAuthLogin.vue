<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Home, LogIn } from 'lucide-vue-next';
import { useBranding } from '@/branding';

const props = defineProps({
    contractor: { type: Object, required: true },
});

const page = usePage();
const flashStatus = computed(() => String(page.props?.flash?.status ?? '').trim());

const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const storeLogo = computed(() => props.contractor?.avatar_url || props.contractor?.logo_url || null);
const { normalizeHex, primaryColor, withAlpha, themeStyles } = useBranding();
const storePrimaryColor = computed(() => normalizeHex(props.contractor?.primary_color || '', primaryColor.value));

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

const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const registerUrl = computed(() => `/shop/${storeSlug.value}/cadastro`);
const shopUrl = computed(() => `/shop/${storeSlug.value}`);

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post(loginUrl.value, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Entrar | ${storeName}`" />

    <div class="min-h-screen bg-slate-100 px-4 py-8 text-slate-900 sm:px-6 lg:px-8" :style="pageStyles">
        <div class="mx-auto w-full max-w-lg">
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
                            <p class="text-xs text-slate-500">Acesse sua conta para finalizar pedidos.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <p v-if="flashStatus" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                        {{ flashStatus }}
                    </p>

                    <form class="space-y-3" @submit.prevent="submit">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                            <input
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="voce@exemplo.com"
                            >
                            <p v-if="form.errors.email" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Senha</label>
                            <input
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Sua senha"
                            >
                            <p v-if="form.errors.password" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.password }}</p>
                        </div>

                        <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input v-model="form.remember" type="checkbox" class="rounded border-slate-300">
                            Manter conectado neste dispositivo
                        </label>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                            style="background: var(--shop-primary-strong)"
                            :disabled="form.processing"
                        >
                            <LogIn class="h-4 w-4" />
                            {{ form.processing ? 'Entrando...' : 'Entrar na minha conta' }}
                        </button>
                    </form>

                    <p class="text-center text-xs text-slate-600">
                        Ainda não tem conta?
                        <Link :href="registerUrl" class="font-semibold text-slate-800 underline decoration-dotted underline-offset-2">
                            Criar cadastro
                        </Link>
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
