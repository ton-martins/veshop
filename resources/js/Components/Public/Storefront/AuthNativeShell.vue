<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ShieldCheck, Sparkles } from 'lucide-vue-next';
import { useBranding } from '@/branding';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    contractor: { type: Object, required: true },
    badge: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    backHref: { type: String, default: '' },
    backLabel: { type: String, default: 'Voltar para loja' },
    heroTitle: { type: String, default: 'Checkout em estilo app nativo' },
    heroDescription: {
        type: String,
        default: 'Layout mobile-first com responsividade total para tablet e desktop.',
    },
});

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

const pageStyles = computed(() => {
    const c = storePrimaryColor.value;

    return {
        ...themeStyles.value,
        '--store-auth-primary': c,
        '--store-auth-primary-soft': withAlpha(c, 0.13),
        '--store-auth-primary-border': withAlpha(c, 0.34),
        '--store-auth-primary-strong': withAlpha(c, 0.92),
        '--store-auth-ring': withAlpha(c, 0.42),
    };
});

const storeIconStyle = computed(() => {
    if (storeLogo.value) return null;

    return {
        background: 'var(--store-auth-primary)',
        color: storeInitialsColor.value,
    };
});

const shopUrl = computed(() => `/shop/${storeSlug.value}`);
const backLinkHref = computed(() => String(props.backHref || '').trim() || shopUrl.value);

watch(
    () => [props.contractor?.avatar_url, props.contractor?.logo_url],
    () => {
        storeLogoLoadFailed.value = false;
    },
);

const handleStoreLogoError = () => {
    storeLogoLoadFailed.value = true;
};
</script>

<template>
    <div
        class="min-h-screen bg-[radial-gradient(circle_at_14%_14%,rgba(15,23,42,0.08),transparent_36%),radial-gradient(circle_at_88%_0%,rgba(15,23,42,0.08),transparent_34%),linear-gradient(170deg,#f8fafc_0%,#eef2f7_100%)] px-3 py-4 text-slate-900 sm:px-6 lg:px-8"
        :style="pageStyles"
    >
        <div class="mx-auto grid w-full max-w-6xl gap-4 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)] lg:gap-6">
            <aside class="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-900 p-5 text-white shadow-xl lg:sticky lg:top-6 lg:h-fit lg:p-6">
                <div class="absolute inset-0 opacity-90" :style="{ background: 'linear-gradient(145deg, var(--store-auth-primary-strong) 0%, rgba(15,23,42,0.92) 70%)' }" />
                <div class="relative">
                    <Link
                        :href="shopUrl"
                        class="inline-flex items-center gap-3 rounded-full border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/20"
                    >
                        <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-white/15" :style="storeIconStyle">
                            <img
                                v-if="storeLogo"
                                :src="storeLogo"
                                :alt="storeName"
                                class="h-full w-full object-cover"
                                @error="handleStoreLogoError"
                            >
                            <span v-else class="text-[10px] font-bold tracking-wide">{{ storeInitials }}</span>
                        </div>
                        <span class="max-w-[180px] truncate">{{ storeName }}</span>
                    </Link>

                    <div class="mt-8 space-y-4">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em]">
                            <Sparkles class="h-3.5 w-3.5" />
                            Native Commerce
                        </span>

                        <h2 class="font-['Outfit'] text-2xl font-extrabold leading-tight">{{ heroTitle }}</h2>
                        <p class="text-sm text-slate-100/90">{{ heroDescription }}</p>

                        <div class="rounded-2xl border border-white/25 bg-white/10 p-3">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/20">
                                    <ShieldCheck class="h-4 w-4" />
                                </span>
                                <div>
                                    <p class="text-sm font-semibold">Fluxo seguro e completo</p>
                                    <p class="mt-1 text-xs text-slate-100/85">Login, cadastro, recuperacao e verificacao no mesmo padrao visual da loja.</p>
                                </div>
                            </div>
                        </div>

                        <slot name="hero-footer" />
                    </div>
                </div>
            </aside>

            <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_28px_65px_-35px_rgba(15,23,42,0.42)]">
                <div class="p-5 sm:p-6 lg:p-8">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="inline-flex items-center rounded-full border border-[var(--store-auth-primary-border)] bg-[var(--store-auth-primary-soft)] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-700">
                            {{ badge || 'Acesso da conta' }}
                        </span>
                        <Link
                            :href="backLinkHref"
                            class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            <ArrowLeft class="h-3.5 w-3.5" />
                            {{ backLabel }}
                        </Link>
                    </div>

                    <h1 class="mt-5 font-['Outfit'] text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ title }}</h1>
                    <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>

                    <div class="mt-6">
                        <slot />
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
