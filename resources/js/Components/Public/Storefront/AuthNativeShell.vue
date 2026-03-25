<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { useBranding } from '@/branding';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    contractor: { type: Object, required: true },
    badge: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    backHref: { type: String, default: '' },
    backLabel: { type: String, default: 'Voltar para loja' },
    heroTitle: { type: String, default: '' },
    heroDescription: { type: String, default: '' },
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
        '--store-auth-primary-soft': withAlpha(c, 0.12),
        '--store-auth-primary-border': withAlpha(c, 0.3),
        '--store-auth-primary-strong': withAlpha(c, 0.92),
        '--store-auth-ring': withAlpha(c, 0.34),
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
    <div class="min-h-screen bg-white text-slate-900" :style="pageStyles">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-6 py-8 md:justify-center">
            <div class="mb-4 flex items-center justify-between">
                <Link
                    :href="backLinkHref"
                    class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    {{ backLabel }}
                </Link>
                <Link :href="shopUrl" class="text-xs font-semibold text-slate-500 hover:text-slate-700">
                    {{ storeName }}
                </Link>
            </div>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mx-auto mb-5 flex h-24 w-24 items-center justify-center overflow-hidden rounded-3xl shadow-lg" :style="storeIconStyle">
                    <img
                        v-if="storeLogo"
                        :src="storeLogo"
                        :alt="storeName"
                        class="h-full w-full object-cover"
                        @error="handleStoreLogoError"
                    >
                    <span v-else class="text-2xl font-bold tracking-wide">{{ storeInitials }}</span>
                </div>

                <span
                    class="inline-flex items-center rounded-full border border-[var(--store-auth-primary-border)] bg-[var(--store-auth-primary-soft)] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-700"
                >
                    {{ badge || 'Acesso da conta' }}
                </span>

                <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-slate-900">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>

                <div class="mt-6">
                    <slot />
                </div>
            </section>

            <p v-if="heroTitle || heroDescription" class="mt-4 text-center text-xs text-slate-500">
                <strong v-if="heroTitle" class="font-semibold text-slate-700">{{ heroTitle }}</strong>
                <span v-if="heroTitle && heroDescription">. </span>
                <span v-if="heroDescription">{{ heroDescription }}</span>
            </p>

            <div class="mt-4">
                <slot name="hero-footer" />
            </div>
        </div>
    </div>
</template>
