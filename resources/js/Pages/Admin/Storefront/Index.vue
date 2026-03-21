<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Store, Truck } from 'lucide-vue-next';

const props = defineProps({
    initialTab: { type: String, default: 'vitrine' },
    contractor: { type: Object, default: () => ({}) },
    storefront: { type: Object, default: () => ({}) },
    shopShipping: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    shop_url: { type: String, default: '' },
});

const page = usePage();
const statusMessage = computed(() => page.props.flash?.status ?? null);

const tabs = [
    { key: 'vitrine', label: 'Vitrine', icon: Store },
    { key: 'frete', label: 'Frete', icon: Truck },
];
const allowedTabs = new Set(tabs.map((tab) => tab.key));
const activeTab = ref(allowedTabs.has(props.initialTab) ? props.initialTab : 'vitrine');

watch(() => props.initialTab, (tab) => {
    activeTab.value = allowedTabs.has(tab) ? tab : 'vitrine';
});

const setActiveTab = (tab) => {
    if (!allowedTabs.has(tab)) return;
    activeTab.value = tab;

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState(window.history.state, '', url.toString());
    }
};

const storefrontForm = useForm({
    hero_enabled: true,
    hero_title: '',
    hero_subtitle: '',
    promotions_enabled: true,
    promotions_title: '',
    promotions_subtitle: '',
    promotion_product_ids: [],
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

    storefrontForm.hero_enabled = blocks.hero ?? true;
    storefrontForm.hero_title = hero.title ?? '';
    storefrontForm.hero_subtitle = hero.subtitle ?? '';
    storefrontForm.promotions_enabled = blocks.promotions ?? true;
    storefrontForm.promotions_title = promotions.title ?? '';
    storefrontForm.promotions_subtitle = promotions.subtitle ?? '';
    storefrontForm.promotion_product_ids = Array.isArray(promotions.product_ids)
        ? promotions.product_ids.map((id) => String(id))
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

const productOptions = computed(() =>
    (props.products ?? []).map((product) => ({
        value: String(product.id),
        label: `${product.name} - ${Number(product.sale_price || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}`,
    })),
);

const selectedPromotionCount = computed(() => storefrontForm.promotion_product_ids.length);
const previewBrandName = computed(() => props.contractor?.brand_name || props.contractor?.name || 'Loja');
const activeBlocksCount = computed(() =>
    [storefrontForm.hero_enabled, storefrontForm.promotions_enabled, storefrontForm.categories_enabled, storefrontForm.catalog_enabled].filter(Boolean).length,
);

const currentTemplate = computed(() => String(props.storefront?.template ?? '').trim().toLowerCase());
const currentTemplateMeta = computed(() =>
    (props.templates ?? []).find((item) => String(item?.value ?? '').trim().toLowerCase() === currentTemplate.value) ?? null,
);

const submitStorefront = () => {
    storefrontForm.transform((data) => ({
        ...data,
        _method: 'put',
        section: 'storefront',
        banners_enabled: false,
        banners: [],
        promotion_product_ids: (data.promotion_product_ids ?? [])
            .map((id) => Number(id))
            .filter((id) => Number.isInteger(id) && id > 0),
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
</script>

<template>
    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Loja Virtual">
        <Head title="Loja Virtual" />

        <div v-if="statusMessage" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ statusMessage }}
        </div>

        <section class="space-y-4">
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Loja pública</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ previewBrandName }}</p>
                    <p class="mt-1 text-xs text-slate-500">/shop/{{ props.contractor?.slug }}</p>
                    <a
                        v-if="shop_url"
                        :href="shop_url"
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
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Promoções</p>
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

                <section class="grid gap-3 md:grid-cols-2">
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span>Hero principal</span>
                        <input v-model="storefrontForm.hero_enabled" type="checkbox" class="rounded border-slate-300">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span>Promoções</span>
                        <input v-model="storefrontForm.promotions_enabled" type="checkbox" class="rounded border-slate-300">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span>Categorias</span>
                        <input v-model="storefrontForm.categories_enabled" type="checkbox" class="rounded border-slate-300">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span>Catálogo</span>
                        <input v-model="storefrontForm.catalog_enabled" type="checkbox" class="rounded border-slate-300">
                    </label>
                </section>

                <div class="grid gap-3 md:grid-cols-2">
                    <input v-model="storefrontForm.hero_title" type="text" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Título da vitrine">
                    <input v-model="storefrontForm.promotions_title" type="text" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Título das promoções">
                    <textarea v-model="storefrontForm.hero_subtitle" rows="2" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Subtítulo da vitrine" />
                    <textarea v-model="storefrontForm.promotions_subtitle" rows="2" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Subtítulo das promoções" />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Itens em destaque ({{ selectedPromotionCount }})
                    </label>
                    <select v-model="storefrontForm.promotion_product_ids" multiple class="mt-1 h-40 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option v-for="option in productOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white" :disabled="storefrontForm.processing">
                        {{ storefrontForm.processing ? 'Salvando...' : 'Salvar vitrine' }}
                    </button>
                </div>
            </form>

            <form v-else class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="submitShipping">
                <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <span>Permitir retirada na loja</span>
                    <input v-model="shippingForm.shipping_pickup_enabled" type="checkbox" class="rounded border-slate-300">
                </label>
                <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <span>Permitir entrega</span>
                    <input v-model="shippingForm.shipping_delivery_enabled" type="checkbox" class="rounded border-slate-300">
                </label>

                <div class="grid gap-3 md:grid-cols-3">
                    <input v-model="shippingForm.shipping_fixed_fee" type="number" min="0" step="0.01" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Taxa fixa (R$)">
                    <input v-model="shippingForm.shipping_free_over" type="number" min="0" step="0.01" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Frete grátis acima (R$)">
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
