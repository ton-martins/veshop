<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Store, Truck } from 'lucide-vue-next';

const props = defineProps({
    initialTab: { type: String, default: 'vitrine' },
    supportsShipping: { type: Boolean, default: true },
    contractor: { type: Object, default: () => ({}) },
    storefront: { type: Object, default: () => ({}) },
    shopShipping: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    shop_url: { type: String, default: '' },
});

const WEEK_DAYS = [
    { key: 'monday', label: 'Segunda' },
    { key: 'tuesday', label: 'Terça' },
    { key: 'wednesday', label: 'Quarta' },
    { key: 'thursday', label: 'Quinta' },
    { key: 'friday', label: 'Sexta' },
    { key: 'saturday', label: 'Sábado' },
    { key: 'sunday', label: 'Domingo' },
];

const emptyBusinessHours = () =>
    Object.fromEntries(
        WEEK_DAYS.map((day) => [day.key, {
            enabled: true,
            open: '00:00',
            close: '23:59',
        }]),
    );

const normalizeHour = (value, fallback = '00:00') => {
    const safe = String(value ?? '').trim();
    return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(safe) ? safe : fallback;
};

const normalizeHexColor = (value, fallback = '#073341') => {
    const safe = String(value ?? '').trim();
    if (/^#[0-9a-fA-F]{3}$/.test(safe) || /^#[0-9a-fA-F]{6}$/.test(safe)) return safe.toUpperCase();
    if (/^[0-9a-fA-F]{3}$/.test(safe) || /^[0-9a-fA-F]{6}$/.test(safe)) return `#${safe.toUpperCase()}`;
    return fallback.toUpperCase();
};

const MAX_BANNERS = 6;

const createEmptyBanner = () => ({
    title: '',
    subtitle: '',
    badge: '',
    existing_image_path: '',
    image_url: '',
    image_file: null,
    remove_image: false,
    cta_label: '',
    cta_url: '',
    background_color: normalizeHexColor(props.contractor?.primary_color || '#073341'),
    preview_url: '',
});

const normalizeBusinessHours = (value) => {
    const base = emptyBusinessHours();
    if (!value || typeof value !== 'object') {
        return base;
    }

    for (const day of WEEK_DAYS) {
        const row = value?.[day.key] ?? {};
        base[day.key] = {
            enabled: Boolean(row?.enabled ?? true),
            open: normalizeHour(row?.open, '00:00'),
            close: normalizeHour(row?.close, '23:59'),
        };
    }

    return base;
};

const allTabs = [
    { key: 'vitrine', label: 'Vitrine', icon: Store },
    { key: 'frete', label: 'Frete', icon: Truck },
];
const tabs = computed(() => allTabs.filter((tab) => props.supportsShipping || tab.key !== 'frete'));
const allowedTabs = computed(() => new Set(tabs.value.map((tab) => tab.key)));
const resolveTabKey = (tab) => (allowedTabs.value.has(tab) ? tab : 'vitrine');
const activeTab = ref(resolveTabKey(props.initialTab));

watch(
    () => [props.initialTab, props.supportsShipping],
    () => {
        activeTab.value = resolveTabKey(props.initialTab);
    },
);

const setActiveTab = (tab) => {
    if (!allowedTabs.value.has(tab)) return;
    activeTab.value = tab;

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState(window.history.state, '', url.toString());
    }
};

const storefrontForm = useForm({
    slug: '',
    store_online: true,
    offline_message: '',
    business_hours: emptyBusinessHours(),
    hero_enabled: true,
    hero_title: '',
    hero_subtitle: '',
    hero_cta_label: '',
    banners_enabled: true,
    banners: [createEmptyBanner()],
    promotions_enabled: true,
    promotions_title: '',
    promotions_subtitle: '',
    promotion_product_ids: [],
    promotion_service_ids: [],
    categories_enabled: true,
    catalog_enabled: true,
    catalog_title: '',
    catalog_subtitle: '',
});

const shippingForm = useForm({
    shipping_pickup_enabled: true,
    shipping_delivery_enabled: true,
    shipping_fixed_fee: 0,
    shipping_free_over: '',
    shipping_estimated_days: 2,
});

const hydrateStorefront = () => {
    const storefront = props.storefront ?? {};
    const blocks = storefront.blocks ?? {};
    const hero = storefront.hero ?? {};
    const promotions = storefront.promotions ?? {};
    const catalog = storefront.catalog ?? {};
    storefrontForm.slug = String(props.contractor?.slug ?? '').trim();

    storefrontForm.store_online = storefront.store_online ?? true;
    storefrontForm.offline_message = storefront.offline_message ?? '';
    storefrontForm.business_hours = normalizeBusinessHours(storefront.business_hours ?? {});
    storefrontForm.hero_enabled = blocks.hero ?? true;
    storefrontForm.hero_title = hero.title ?? '';
    storefrontForm.hero_subtitle = hero.subtitle ?? '';
    storefrontForm.hero_cta_label = hero.cta_label ?? '';
    storefrontForm.banners_enabled = blocks.banners ?? true;
    storefrontForm.banners = Array.isArray(storefront.banners) && storefront.banners.length
        ? storefront.banners.map((banner) => ({
            title: String(banner?.title ?? ''),
            subtitle: String(banner?.subtitle ?? ''),
            badge: String(banner?.badge ?? ''),
            existing_image_path: String(banner?.image_path ?? ''),
            image_url: String(banner?.image_url ?? ''),
            image_file: null,
            remove_image: false,
            cta_label: String(banner?.cta_label ?? ''),
            cta_url: String(banner?.cta_url ?? ''),
            background_color: normalizeHexColor(banner?.background_color ?? props.contractor?.primary_color ?? '#073341'),
            preview_url: String(banner?.image_url ?? ''),
        }))
        : [createEmptyBanner(), createEmptyBanner()];
    storefrontForm.promotions_enabled = blocks.promotions ?? true;
    storefrontForm.promotions_title = promotions.title ?? '';
    storefrontForm.promotions_subtitle = promotions.subtitle ?? '';
    storefrontForm.promotion_product_ids = Array.isArray(promotions.product_ids)
        ? promotions.product_ids.map((id) => String(id))
        : [];
    storefrontForm.promotion_service_ids = Array.isArray(promotions.service_ids)
        ? promotions.service_ids.map((id) => String(id))
        : [];
    storefrontForm.categories_enabled = blocks.categories ?? true;
    storefrontForm.catalog_enabled = blocks.catalog ?? true;
    storefrontForm.catalog_title = catalog.title ?? '';
    storefrontForm.catalog_subtitle = catalog.subtitle ?? '';
};

const hydrateShipping = () => {
    shippingForm.shipping_pickup_enabled = props.shopShipping?.pickup_enabled ?? true;
    shippingForm.shipping_delivery_enabled = props.shopShipping?.delivery_enabled ?? true;
    shippingForm.shipping_fixed_fee = props.shopShipping?.fixed_fee ?? 0;
    shippingForm.shipping_free_over = props.shopShipping?.free_over ?? '';
    shippingForm.shipping_estimated_days = props.shopShipping?.estimated_days ?? 2;
};

watch(() => props.storefront, hydrateStorefront, { deep: true, immediate: true });
watch(() => props.shopShipping, hydrateShipping, { deep: true, immediate: true });
watch(
    () => props.contractor?.slug,
    (slug) => {
        storefrontForm.slug = String(slug ?? '').trim();
    },
);

const WEEKDAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
const DEFAULT_BUSINESS_OPEN = '08:00';
const DEFAULT_BUSINESS_CLOSE = '18:00';

const formatCurrency = (value) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const productOptions = computed(() =>
    (props.products ?? []).map((product) => ({
        value: String(product.id),
        title: String(product.name ?? 'Produto'),
        subtitle: formatCurrency(product.sale_price),
        meta: `Estoque: ${Number(product.stock_quantity ?? 0)}`,
        search: `${String(product.name ?? '')} ${String(product.id ?? '')}`.toLowerCase(),
    })),
);

const serviceOptions = computed(() =>
    (props.services ?? []).map((service) => ({
        value: String(service.id),
        title: String(service.name ?? 'Serviço'),
        subtitle: formatCurrency(service.base_price),
        meta: `${Number(service.duration_minutes ?? 0)} min`,
        search: `${String(service.name ?? '')} ${String(service.id ?? '')} ${Number(service.duration_minutes ?? 0)}`.toLowerCase(),
    })),
);

const currentTemplate = computed(() => String(props.storefront?.template ?? '').trim().toLowerCase());
const isServiceTemplate = computed(() => currentTemplate.value === 'servicos');
const currentTemplateMeta = computed(() =>
    (props.templates ?? []).find((item) => String(item?.value ?? '').trim().toLowerCase() === currentTemplate.value) ?? null,
);

const promotionOptions = computed(() =>
    isServiceTemplate.value ? serviceOptions.value : productOptions.value,
);

const promotionFieldLabel = computed(() =>
    isServiceTemplate.value ? 'Serviços em destaque' : 'Produtos em destaque',
);

const promotionSearch = ref('');
const promotionSelectValue = ref('');

const selectedPromotionIds = computed({
    get: () => (isServiceTemplate.value
        ? storefrontForm.promotion_service_ids
        : storefrontForm.promotion_product_ids)
        .map((id) => String(id)),
    set: (values) => {
        const sanitized = Array.from(new Set(
            (values ?? [])
                .map((id) => String(id).trim())
                .filter((id) => id !== ''),
        ));

        if (isServiceTemplate.value) {
            storefrontForm.promotion_service_ids = sanitized;
            return;
        }

        storefrontForm.promotion_product_ids = sanitized;
    },
});

const selectedPromotionCount = computed(() => selectedPromotionIds.value.length);
const promotionSearchPlaceholder = computed(() =>
    isServiceTemplate.value
        ? 'Buscar serviços para destacar...'
        : 'Buscar produtos para destacar...',
);
const promotionSelectableOptions = computed(() => {
    const query = promotionSearch.value.trim().toLowerCase();
    const options = promotionOptions.value ?? [];
    const selectedSet = new Set(selectedPromotionIds.value);

    return options
        .filter((option) => {
            if (selectedSet.has(option.value)) return false;
            if (!query) return true;
            return option.search.includes(query);
        })
        .slice(0, 120);
});
const promotionSelectableSelectOptions = computed(() =>
    (promotionSelectableOptions.value ?? []).map((option) => ({
        value: option.value,
        label: `${option.title} - ${option.subtitle}`,
    })),
);
const selectedPromotionCards = computed(() => {
    const optionMap = new Map((promotionOptions.value ?? []).map((option) => [option.value, option]));

    return selectedPromotionIds.value
        .map((id) => optionMap.get(id))
        .filter(Boolean);
});

const promotionErrorMessage = computed(() =>
    storefrontForm.errors.promotion_service_ids || storefrontForm.errors.promotion_product_ids || '',
);

const bannerErrorMessage = computed(() => {
    if (storefrontForm.errors.banners) return storefrontForm.errors.banners;

    const dynamicErrorKey = Object.keys(storefrontForm.errors).find((key) => key.startsWith('banners.'));
    return dynamicErrorKey ? storefrontForm.errors[dynamicErrorKey] : '';
});

const previewBrandName = computed(() => props.contractor?.brand_name || props.contractor?.name || 'Loja');
const previewSlug = computed(() => String(storefrontForm.slug ?? props.contractor?.slug ?? '').trim().toLowerCase());
const previewShopPath = computed(() => `/shop/${previewSlug.value || String(props.contractor?.slug ?? '').trim()}`);
const previewShopUrl = computed(() => {
    const slug = previewSlug.value || String(props.contractor?.slug ?? '').trim();
    if (!slug) return '';

    const original = String(props.shop_url ?? '').trim();
    if (original.includes('/shop/')) {
        return `${original.split('/shop/')[0]}/shop/${slug}`;
    }

    if (typeof window !== 'undefined') {
        return `${window.location.origin}/shop/${slug}`;
    }

    return original || `/shop/${slug}`;
});
const activeBlocksCount = computed(() =>
    [
        storefrontForm.hero_enabled,
        storefrontForm.banners_enabled,
        storefrontForm.promotions_enabled,
        storefrontForm.categories_enabled,
        storefrontForm.catalog_enabled,
    ].filter(Boolean).length,
);

const canAddBanner = computed(() => storefrontForm.banners.length < MAX_BANNERS);

const revokePreview = (url) => {
    const safe = String(url ?? '').trim();
    if (!safe.startsWith('blob:')) return;
    URL.revokeObjectURL(safe);
};

const addBanner = () => {
    if (!canAddBanner.value) return;
    storefrontForm.banners = [...storefrontForm.banners, createEmptyBanner()];
};

const removeBanner = (index) => {
    const next = [...storefrontForm.banners];
    revokePreview(next[index]?.preview_url);
    next.splice(index, 1);
    storefrontForm.banners = next.length ? next : [createEmptyBanner()];
};

const onBannerFileChange = (index, event) => {
    const file = event?.target?.files?.[0] ?? null;
    if (!file) return;

    revokePreview(storefrontForm.banners[index].preview_url);
    storefrontForm.banners[index].image_file = file;
    storefrontForm.banners[index].remove_image = false;
    storefrontForm.banners[index].preview_url = URL.createObjectURL(file);
};

const clearBannerImage = (index) => {
    revokePreview(storefrontForm.banners[index].preview_url);
    storefrontForm.banners[index].image_file = null;
    storefrontForm.banners[index].preview_url = '';
    storefrontForm.banners[index].image_url = '';
    storefrontForm.banners[index].remove_image = true;
};

const addPromotionFromSelect = () => {
    const value = String(promotionSelectValue.value || '').trim();
    if (!value) return;
    if (selectedPromotionIds.value.includes(value)) {
        promotionSelectValue.value = '';
        return;
    }

    selectedPromotionIds.value = [...selectedPromotionIds.value, value];
    promotionSelectValue.value = '';
};

const removePromotionSelection = (value) => {
    selectedPromotionIds.value = selectedPromotionIds.value.filter((id) => id !== String(value));
};

const clearPromotionSelection = () => {
    selectedPromotionIds.value = [];
};

watch(isServiceTemplate, () => {
    promotionSearch.value = '';
    promotionSelectValue.value = '';
});

const withBusinessHoursUpdate = (callback) => {
    const next = normalizeBusinessHours(storefrontForm.business_hours ?? {});
    callback(next);
    storefrontForm.business_hours = next;
};

const applyBusinessHoursPreset = (preset) => {
    withBusinessHoursUpdate((hours) => {
        for (const day of WEEK_DAYS) {
            const current = hours[day.key] ?? { enabled: true, open: '00:00', close: '23:59' };

            if (preset === 'business') {
                const isWeekday = WEEKDAY_KEYS.includes(day.key);
                hours[day.key] = {
                    enabled: isWeekday,
                    open: DEFAULT_BUSINESS_OPEN,
                    close: DEFAULT_BUSINESS_CLOSE,
                };
                continue;
            }

            if (preset === 'everyday') {
                hours[day.key] = {
                    enabled: true,
                    open: DEFAULT_BUSINESS_OPEN,
                    close: DEFAULT_BUSINESS_CLOSE,
                };
                continue;
            }

            if (preset === 'always') {
                hours[day.key] = {
                    enabled: true,
                    open: '00:00',
                    close: '23:59',
                };
                continue;
            }

            if (preset === 'close-weekend') {
                const isWeekend = day.key === 'saturday' || day.key === 'sunday';
                hours[day.key] = {
                    enabled: !isWeekend,
                    open: current.open || DEFAULT_BUSINESS_OPEN,
                    close: current.close || DEFAULT_BUSINESS_CLOSE,
                };
            }
        }
    });
};

const submitStorefront = () => {
    storefrontForm.transform((data) => ({
        ...data,
        _method: 'put',
        section: 'storefront',
        slug: String(data.slug || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9-]/g, '-')
            .replace(/-{2,}/g, '-')
            .replace(/^-+|-+$/g, ''),
        hero_cta_label: String(data.hero_cta_label || '').trim(),
        banners_enabled: Boolean(data.banners_enabled),
        banners: (data.banners ?? [])
            .map((banner) => ({
                title: String(banner?.title || '').trim(),
                subtitle: String(banner?.subtitle || '').trim(),
                badge: String(banner?.badge || '').trim(),
                existing_image_path: String(banner?.existing_image_path || '').trim(),
                image_file: banner?.image_file ?? null,
                remove_image: Boolean(banner?.remove_image),
                image_url: String(banner?.image_url || '').trim(),
                cta_label: String(banner?.cta_label || '').trim(),
                cta_url: String(banner?.cta_url || '').trim(),
                background_color: normalizeHexColor(
                    banner?.background_color || props.contractor?.primary_color || '#073341'
                ),
            }))
            .slice(0, MAX_BANNERS),
        promotion_product_ids: (data.promotion_product_ids ?? [])
            .map((id) => Number(id))
            .filter((id) => Number.isInteger(id) && id > 0),
        promotion_service_ids: (data.promotion_service_ids ?? [])
            .map((id) => Number(id))
            .filter((id) => Number.isInteger(id) && id > 0),
        business_hours: Object.fromEntries(
            WEEK_DAYS.map((day) => {
                const row = data.business_hours?.[day.key] ?? {};
                return [day.key, {
                    enabled: Boolean(row.enabled),
                    open: normalizeHour(row.open, '00:00'),
                    close: normalizeHour(row.close, '23:59'),
                }];
            }),
        ),
    })).post(route('admin.storefront.update'), {
        preserveScroll: true,
        forceFormData: true,
    });
};

const submitShipping = () => {
    shippingForm.transform((data) => ({
        ...data,
        _method: 'put',
        section: 'shipping',
    })).post(route('admin.storefront.update'), {
        preserveScroll: true,
    });
};

onBeforeUnmount(() => {
    storefrontForm.banners.forEach((banner) => revokePreview(banner?.preview_url));
});
</script>

<template>
    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Loja Virtual">
        <Head title="Loja Virtual" />

        <section class="space-y-4">
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Loja pública</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ previewBrandName }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ previewShopPath }}</p>
                    <a
                        v-if="previewShopUrl"
                        :href="previewShopUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-3 inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Abrir loja virtual
                    </a>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Blocos ativos</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ activeBlocksCount }}</p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Destaques</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ selectedPromotionCount }}</p>
                </article>
            </section>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-xl border px-3 py-2 text-xs font-semibold"
                    :class="activeTab === tab.key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700'"
                    @click="setActiveTab(tab.key)"
                >
                    <component :is="tab.icon" class="h-3.5 w-3.5" />
                    {{ tab.label }}
                </button>
            </div>

            <form v-if="activeTab === 'vitrine'" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="submitStorefront">
                <section class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Modelo da loja</p>
                    <div class="mt-2 flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                        <p class="text-sm font-semibold text-slate-800">{{ currentTemplateMeta?.label || 'Modelo padrão' }}</p>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">Somente leitura</span>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug da loja virtual</label>
                    <div class="mt-2 flex items-center overflow-hidden rounded-lg border border-slate-200 bg-white">
                        <span class="border-r border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-500">/shop/</span>
                        <input
                            v-model="storefrontForm.slug"
                            type="text"
                            class="w-full border-0 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-0"
                            placeholder="minha-loja"
                            autocomplete="off"
                        >
                    </div>
                    <p class="mt-2 text-[11px] text-slate-500">Use apenas letras minúsculas, números e hífen.</p>
                    <p v-if="storefrontForm.errors.slug" class="mt-1 text-[11px] text-rose-600">{{ storefrontForm.errors.slug }}</p>
                </section>

                <section class="grid gap-3 md:grid-cols-2">
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span>Loja online ativa</span>
                        <input v-model="storefrontForm.store_online" type="checkbox" class="rounded border-slate-300">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span>Hero principal</span>
                        <input v-model="storefrontForm.hero_enabled" type="checkbox" class="rounded border-slate-300">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span>Banners</span>
                        <input v-model="storefrontForm.banners_enabled" type="checkbox" class="rounded border-slate-300">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span>Promoções</span>
                        <input v-model="storefrontForm.promotions_enabled" type="checkbox" class="rounded border-slate-300">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span>Categorias</span>
                        <input v-model="storefrontForm.categories_enabled" type="checkbox" class="rounded border-slate-300">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm md:col-span-2">
                        <span>Catálogo</span>
                        <input v-model="storefrontForm.catalog_enabled" type="checkbox" class="rounded border-slate-300">
                    </label>
                </section>

                <section class="space-y-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mensagem quando offline</label>
                    <textarea
                        v-model="storefrontForm.offline_message"
                        rows="2"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        placeholder="Ex.: Loja em manutenção. Tente novamente em alguns minutos."
                    />
                </section>

                <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Horário de funcionamento</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-emerald-200 hover:text-emerald-700" @click="applyBusinessHoursPreset('business')">
                                Seg-Sex
                            </button>
                            <button type="button" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-emerald-200 hover:text-emerald-700" @click="applyBusinessHoursPreset('everyday')">
                                Todos os dias
                            </button>
                            <button type="button" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-emerald-200 hover:text-emerald-700" @click="applyBusinessHoursPreset('always')">
                                24h
                            </button>
                            <button type="button" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-emerald-200 hover:text-emerald-700" @click="applyBusinessHoursPreset('close-weekend')">
                                Fechar fim de semana
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-2 md:grid-cols-2">
                        <article
                            v-for="day in WEEK_DAYS"
                            :key="`hour-${day.key}`"
                            class="cursor-pointer rounded-xl border border-slate-200 bg-white p-3 transition hover:border-emerald-200"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ day.label }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ storefrontForm.business_hours[day.key].enabled ? 'Dia com atendimento ativo' : 'Dia sem atendimento' }}
                                    </p>
                                </div>
                                <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600">
                                    <input v-model="storefrontForm.business_hours[day.key].enabled" type="checkbox" class="rounded border-slate-300">
                                    Aberto
                                </label>
                            </div>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <label class="space-y-1">
                                    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Abre</span>
                                    <input
                                        v-model="storefrontForm.business_hours[day.key].open"
                                        type="time"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                                        :class="storefrontForm.business_hours[day.key].enabled ? 'bg-white text-slate-700' : 'cursor-not-allowed bg-slate-100 text-slate-400'"
                                        :disabled="!storefrontForm.business_hours[day.key].enabled"
                                    >
                                </label>
                                <label class="space-y-1">
                                    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Fecha</span>
                                    <input
                                        v-model="storefrontForm.business_hours[day.key].close"
                                        type="time"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                                        :class="storefrontForm.business_hours[day.key].enabled ? 'bg-white text-slate-700' : 'cursor-not-allowed bg-slate-100 text-slate-400'"
                                        :disabled="!storefrontForm.business_hours[day.key].enabled"
                                    >
                                </label>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Textos principais da vitrine</p>
                    <div class="grid gap-3 md:grid-cols-2">
                        <input
                            v-model="storefrontForm.hero_title"
                            type="text"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            placeholder="Título da vitrine"
                        >
                        <input
                            v-model="storefrontForm.promotions_title"
                            type="text"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            placeholder="Título dos destaques"
                        >
                        <textarea
                            v-model="storefrontForm.hero_subtitle"
                            rows="2"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            placeholder="Subtítulo da vitrine"
                        ></textarea>
                        <textarea
                            v-model="storefrontForm.promotions_subtitle"
                            rows="2"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            placeholder="Subtítulo dos destaques"
                        ></textarea>
                        <input
                            v-model="storefrontForm.hero_cta_label"
                            type="text"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2"
                            placeholder="Texto do botão principal (ex.: Ver catálogo)"
                        >
                    </div>
                </section>

                <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Banners da loja</p>
                            <p class="mt-1 text-[11px] text-slate-500">
                                Use os mesmos banners em desktop e mobile. No app mobile, eles aparecem em rolagem horizontal.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canAddBanner"
                            @click="addBanner"
                        >
                            Adicionar banner
                        </button>
                    </div>

                    <p class="text-[11px] text-slate-500">
                        Banners configurados: {{ storefrontForm.banners.length }} de {{ MAX_BANNERS }}
                    </p>

                    <div class="space-y-3">
                        <article
                            v-for="(banner, index) in storefrontForm.banners"
                            :key="`banner-${index}`"
                            class="rounded-xl border border-slate-200 bg-white p-3"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Banner {{ index + 1 }}</p>
                                <button
                                    type="button"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="storefrontForm.banners.length <= 1"
                                    @click="removeBanner(index)"
                                >
                                    Remover
                                </button>
                            </div>

                            <div class="mt-3 grid gap-2 md:grid-cols-2">
                                <input
                                    v-model="banner.title"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                    placeholder="Título do banner"
                                >
                                <input
                                    v-model="banner.badge"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                    placeholder="Badge (ex.: Oferta)"
                                >
                                <textarea
                                    v-model="banner.subtitle"
                                    rows="2"
                                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2"
                                    placeholder="Subtítulo do banner"
                                ></textarea>
                                <input
                                    v-model="banner.cta_label"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                    placeholder="Texto do CTA"
                                >
                                <input
                                    v-model="banner.cta_url"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                    placeholder="URL do CTA (opcional)"
                                >
                            </div>

                            <div class="mt-2 grid gap-2 md:grid-cols-[1fr_180px_auto]">
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cor</span>
                                    <input v-model="banner.background_color" type="color" class="h-8 w-10 cursor-pointer rounded border border-slate-300 bg-white p-0.5">
                                    <input
                                        v-model="banner.background_color"
                                        type="text"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs"
                                        placeholder="#FF5C35"
                                    >
                                </label>

                                <label class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                    <span class="font-semibold uppercase tracking-wide text-slate-500">Imagem</span>
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/jpg,image/webp"
                                        class="mt-1 block w-full text-[11px]"
                                        @change="onBannerFileChange(index, $event)"
                                    >
                                </label>

                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                    @click="clearBannerImage(index)"
                                >
                                    Limpar imagem
                                </button>
                            </div>

                            <div v-if="banner.preview_url || banner.image_url" class="mt-3 overflow-hidden rounded-xl border border-slate-200">
                                <img :src="banner.preview_url || banner.image_url" :alt="banner.title || `Banner ${index + 1}`" class="h-32 w-full object-cover">
                            </div>
                        </article>
                    </div>
                </section>

                <p v-if="bannerErrorMessage" class="text-xs font-medium text-rose-600">{{ bannerErrorMessage }}</p>

                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ promotionFieldLabel }} ({{ selectedPromotionCount }})
                        </label>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-rose-200 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!selectedPromotionCount"
                            @click="clearPromotionSelection"
                        >
                            Limpar seleção
                        </button>
                    </div>

                    <section class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="grid gap-2 lg:grid-cols-[1fr,280px,auto]">
                            <input
                                v-model="promotionSearch"
                                type="text"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                :placeholder="promotionSearchPlaceholder"
                            >
                            <UiSelect
                                v-model="promotionSelectValue"
                                :options="promotionSelectableSelectOptions"
                                placeholder="Selecione um item"
                                button-class="w-full text-sm"
                            />
                            <button
                                type="button"
                                class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!promotionSelectValue"
                                @click="addPromotionFromSelect"
                            >
                                Adicionar
                            </button>
                        </div>

                        <p class="text-[11px] text-slate-500">
                            {{ promotionSelectableOptions.length }} opção(ões) disponível(is) para seleção.
                        </p>
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selecionados</p>
                        <div v-if="selectedPromotionCards.length" class="mt-2 grid gap-1.5 sm:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="option in selectedPromotionCards"
                                :key="`promotion-selected-${option.value}`"
                                class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-2"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p class="truncate pr-1 text-xs font-semibold text-slate-800">{{ option.title }}</p>
                                    <button
                                        type="button"
                                        class="shrink-0 rounded-md border border-emerald-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 hover:border-rose-200 hover:text-rose-600"
                                        @click="removePromotionSelection(option.value)"
                                    >
                                        Remover
                                    </button>
                                </div>
                                <p class="mt-0.5 text-[11px] font-semibold text-slate-600">{{ option.subtitle }}</p>
                                <p class="mt-0.5 truncate text-[10px] text-slate-500">{{ option.meta }}</p>
                            </article>
                        </div>
                        <p v-else class="mt-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-xs text-slate-500">
                            Selecione itens no campo acima para montar sua vitrine de destaque.
                        </p>
                    </section>

                    <p v-if="promotionErrorMessage" class="text-xs font-medium text-rose-600">{{ promotionErrorMessage }}</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white" :disabled="storefrontForm.processing">
                        {{ storefrontForm.processing ? 'Salvando...' : 'Salvar vitrine' }}
                    </button>
                </div>
            </form>

            <form v-else-if="activeTab === 'frete' && props.supportsShipping" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="submitShipping">
                <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <span>Permitir retirada na loja</span>
                    <input v-model="shippingForm.shipping_pickup_enabled" type="checkbox" class="rounded border-slate-300">
                </label>
                <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <span>Permitir entrega</span>
                    <input v-model="shippingForm.shipping_delivery_enabled" type="checkbox" class="rounded border-slate-300">
                </label>

                <div class="grid gap-3 md:grid-cols-3">
                    <BrlMoneyInput v-model="shippingForm.shipping_fixed_fee" :allow-empty="false" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Taxa fixa (R$)" />
                    <BrlMoneyInput v-model="shippingForm.shipping_free_over" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Frete grátis acima (R$)" />
                    <input v-model="shippingForm.shipping_estimated_days" type="number" min="1" max="60" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Prazo (dias)">
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white" :disabled="shippingForm.processing">
                        {{ shippingForm.processing ? 'Salvando...' : 'Salvar frete' }}
                    </button>
                </div>
            </form>
        </section>
    </AuthenticatedLayout>
</template>
