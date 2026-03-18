<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    ChevronLeft,
    ChevronRight,
    Heart,
    Home,
    LogIn,
    LogOut,
    Menu,
    Minus,
    Package,
    Plus,
    Save,
    Search,
    ShoppingCart,
    Tags,
    Trash2,
    UserCircle2,
    X,
} from 'lucide-vue-next';
import { useBranding } from '@/branding';
import { BRAZIL_STATES, formatCepBR, formatPhoneBR, normalizeStateCode, viaCepToAddress } from '@/utils/br';

const props = defineProps({
    contractor: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    storefront: { type: Object, default: () => ({}) },
    payment_methods: { type: Array, default: () => [] },
    shipping_config: { type: Object, default: () => ({}) },
    shop_auth: { type: Object, default: () => ({ authenticated: false, customer: null }) },
    shop_account: { type: Object, default: () => ({ orders: [] }) },
});

const page = usePage();
const { normalizeHex, primaryColor, publicFaviconHref, publicFaviconType, themeStyles, withAlpha } = useBranding();
const flashStatusMessage = computed(() => String(page.props?.flash?.status ?? '').trim());

const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const accountUrl = computed(() => `/shop/${storeSlug.value}/conta`);
const verifyEmailUrl = computed(() => `/shop/${storeSlug.value}/verificar-email`);
const isShopAuthenticated = computed(() => Boolean(props.shop_auth?.authenticated));
const requiresEmailVerification = computed(() => Boolean(props.shop_auth?.requires_email_verification ?? true));
const isShopEmailVerified = computed(() => Boolean(props.shop_auth?.email_verified ?? false));
const isShopAddressComplete = computed(() => Boolean(props.shop_auth?.address_complete ?? false));
const missingShopAddressFields = computed(() => {
    const raw = props.shop_auth?.missing_address_fields;
    const fieldLabelMap = {
        cep: 'CEP',
        street: 'logradouro',
        neighborhood: 'bairro',
        city: 'cidade',
        state: 'UF',
    };

    return Array.isArray(raw)
        ? raw
            .map((field) => String(field ?? '').trim().toLowerCase())
            .filter(Boolean)
            .map((field) => fieldLabelMap[field] ?? field)
        : [];
});
const shopCustomer = computed(() => props.shop_auth?.customer ?? null);
const shopAccountOrders = computed(() => (Array.isArray(props.shop_account?.orders) ? props.shop_account.orders : []));
const stateOptions = computed(() => ([
    { value: '', label: 'Selecione a UF' },
    ...BRAZIL_STATES.map((state) => ({
        value: state.code,
        label: `${state.code} - ${state.name}`,
    })),
]));
const normalizeBrandAsset = (value) => {
    const safe = String(value ?? '').trim();
    return safe !== '' ? safe : null;
};
const storeLogoLoadFailed = ref(false);
const rawStoreLogo = computed(() =>
    normalizeBrandAsset(props.contractor?.avatar_url) || normalizeBrandAsset(props.contractor?.logo_url),
);
const storeLogo = computed(() => (storeLogoLoadFailed.value ? null : rawStoreLogo.value));
const checkoutUrl = computed(() => `/shop/${storeSlug.value}/checkout`);
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
        '--catalog-primary': c,
        '--catalog-primary-soft': withAlpha(c, 0.14),
        '--catalog-primary-border': withAlpha(c, 0.28),
        '--catalog-primary-strong': withAlpha(c, 0.92),
        '--catalog-gradient': `linear-gradient(135deg, ${withAlpha(c, 0.16)} 0%, rgba(255,255,255,0.96) 54%, ${withAlpha(c, 0.05)} 100%)`,
    };
});

const storefront = computed(() => {
    const raw = props.storefront ?? {};
    const blocksRaw = raw.blocks ?? {};
    const heroRaw = raw.hero ?? {};
    const promotionsRaw = raw.promotions ?? {};
    const catalogRaw = raw.catalog ?? {};

    return {
        template: String(raw.template || 'comercio'),
        blocks: {
            hero: Boolean(blocksRaw.hero ?? true),
            promotions: Boolean(blocksRaw.promotions ?? true),
            categories: Boolean(blocksRaw.categories ?? true),
            catalog: Boolean(blocksRaw.catalog ?? true),
        },
        hero: {
            title: String(heroRaw.title || '').trim(),
            subtitle: String(heroRaw.subtitle || '').trim(),
            cta_label: String(heroRaw.cta_label || '').trim(),
        },
        promotions: {
            title: String(promotionsRaw.title || '').trim(),
            subtitle: String(promotionsRaw.subtitle || '').trim(),
            product_ids: Array.isArray(promotionsRaw.product_ids)
                ? Array.from(new Set(promotionsRaw.product_ids.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0)))
                : [],
        },
        catalog: {
            title: String(catalogRaw.title || '').trim(),
            subtitle: String(catalogRaw.subtitle || '').trim(),
        },
    };
});

const storefrontBlocks = computed(() => storefront.value.blocks);
const storefrontHeroTitle = computed(() => storefront.value.hero.title || `Compre em ${storeName.value}`);
const storefrontHeroSubtitle = computed(() => storefront.value.hero.subtitle || 'Confira os destaques e finalize seu pedido com rapidez.');
const storefrontPromotionsTitle = computed(() => storefront.value.promotions.title || 'Promoções da semana');
const storefrontPromotionsSubtitle = computed(() => storefront.value.promotions.subtitle || 'Itens selecionados para você.');
const storefrontCatalogTitle = computed(() => {
    if (storefront.value.catalog.title) return storefront.value.catalog.title;
    if (storefront.value.template === 'servicos') return 'Catálogo de serviços';
    if (storefront.value.template === 'hibrido') return 'Catálogo completo';
    return 'Catálogo de produtos';
});
const storefrontCatalogSubtitle = computed(() =>
    storefront.value.catalog.subtitle || 'Use os filtros para encontrar o que precisa.',
);
const searchPlaceholder = computed(() => (
    storefront.value.template === 'servicos'
        ? 'Buscar serviço, código ou categoria'
        : 'Buscar produto, SKU ou categoria'
));
const catalogItemLabel = computed(() => (storefront.value.template === 'servicos' ? 'serviço(s)' : 'produto(s)'));

const promotionProducts = computed(() => {
    const available = props.products.filter((product) => Number(product.stock_quantity || 0) > 0);
    if (!available.length) return [];

    const ids = storefront.value.promotions.product_ids;
    if (!ids.length) {
        return available.slice(0, 8);
    }

    const selected = available.filter((product) => ids.includes(Number(product.id)));
    return (selected.length ? selected : available).slice(0, 8);
});

const normalize = (value) =>
    String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

const search = ref('');
const selectedCategory = ref('all');
const sortMode = ref('featured');
const currentPage = ref(1);
const PAGE_SIZE = 20;
const cartOpen = ref(false);
const cartMobileStep = ref(1);
const leftMenuOpen = ref(false);
const favoritesOnly = ref(false);
const accountModalOpen = ref(false);
const favoritesModalOpen = ref(false);
const productModalOpen = ref(false);
const productModalQuantity = ref(1);
const productModalId = ref(null);
const addToCartProcessing = ref(false);
const profileCepLookupLoading = ref(false);
const profileCepLookupError = ref('');
const categorySectionRef = ref(null);
const uiFeedback = ref({
    visible: false,
    message: '',
    tone: 'success',
});
let uiFeedbackTimer = null;

const categoryList = computed(() => [
    { id: 'all', name: 'Todos', products_count: props.products.length },
    ...props.categories.map((c) => ({ id: Number(c.id), name: String(c.name), products_count: Number(c.products_count || 0) })),
]);

const filteredProducts = computed(() => {
    const term = normalize(search.value);
    let list = props.products.filter((p) => Number(p.stock_quantity || 0) > 0);

    if (favoritesOnly.value) {
        list = list.filter((p) => favoriteProductIdSet.value.has(Number(p.id)));
    }

    if (selectedCategory.value !== 'all') {
        list = list.filter((p) => Number(p.category_id) === Number(selectedCategory.value));
    }
    if (term) {
        list = list.filter((p) => normalize([p.name, p.sku, p.description, p.category_name].filter(Boolean).join(' ')).includes(term));
    }
    if (sortMode.value === 'name') return [...list].sort((a, b) => String(a.name).localeCompare(String(b.name), 'pt-BR'));
    if (sortMode.value === 'price_asc') return [...list].sort((a, b) => Number(a.sale_price || 0) - Number(b.sale_price || 0));
    if (sortMode.value === 'price_desc') return [...list].sort((a, b) => Number(b.sale_price || 0) - Number(a.sale_price || 0));
    return list;
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredProducts.value.length / PAGE_SIZE)));
const paginatedProducts = computed(() => filteredProducts.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE));
const rangeText = computed(() => {
    if (!filteredProducts.value.length) return '0-0 de 0';
    const start = (currentPage.value - 1) * PAGE_SIZE + 1;
    const end = Math.min(currentPage.value * PAGE_SIZE, filteredProducts.value.length);
    return `${start}-${end} de ${filteredProducts.value.length}`;
});

watch(
    () => [props.contractor?.avatar_url, props.contractor?.logo_url],
    () => {
        storeLogoLoadFailed.value = false;
    },
);

const handleStoreLogoError = () => {
    storeLogoLoadFailed.value = true;
};

watch([search, selectedCategory, sortMode], () => {
    currentPage.value = 1;
});
watch(totalPages, (v) => {
    if (currentPage.value > v) currentPage.value = v;
});
watch(
    () => storefrontBlocks.value.categories,
    (enabled) => {
        if (!enabled) {
            selectedCategory.value = 'all';
        }
    },
    { immediate: true },
);

const currency = (value) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const productImage = (product) => {
    const raw = String(product?.image_url || '').trim();
    return raw || `https://placehold.co/480x480/e2e8f0/475569?text=${encodeURIComponent(String(product?.name || 'Produto'))}`;
};

const productMap = computed(() => new Map(props.products.map((p) => [Number(p.id), p])));
const cartStorageKey = computed(() => `veshop:shop-cart:${storeSlug.value}`);
const favoritesStorageKey = computed(() => `veshop:shop-favorites:${storeSlug.value}`);
const cartItems = ref([]);
const favoriteProductIds = ref([]);
const favoriteProductIdSet = computed(() => new Set(favoriteProductIds.value));
const favoriteSyncingIds = ref([]);
const favoriteCount = computed(() => favoriteProductIds.value.length);

const normalizeFavoriteIds = (values) => {
    if (!Array.isArray(values)) return [];

    return Array.from(new Set(
        values
            .map((id) => Number(id))
            .filter((id) => Number.isFinite(id) && id > 0 && productMap.value.has(id)),
    ));
};

const serverFavoriteProductIds = computed(() =>
    normalizeFavoriteIds(props.shop_auth?.favorite_product_ids ?? []),
);

const loadFavoriteIdsFromStorage = () => {
    if (typeof window === 'undefined') return;

    try {
        const rawFavorites = window.localStorage.getItem(favoritesStorageKey.value);
        if (!rawFavorites) {
            favoriteProductIds.value = [];
            return;
        }

        favoriteProductIds.value = normalizeFavoriteIds(JSON.parse(rawFavorites));
    } catch {
        favoriteProductIds.value = [];
    }
};

const setFavoriteSyncing = (productId, syncing) => {
    const safeProductId = Number(productId);
    const next = new Set(favoriteSyncingIds.value);

    if (syncing) {
        next.add(safeProductId);
    } else {
        next.delete(safeProductId);
    }

    favoriteSyncingIds.value = Array.from(next);
};

const isFavoriteSyncing = (productId) => new Set(favoriteSyncingIds.value).has(Number(productId));

const persistFavoriteOnServer = async (productId, shouldFavorite) => {
    if (typeof window === 'undefined' || !window.axios) return false;

    const endpoint = `/shop/${storeSlug.value}/favoritos/${productId}`;

    try {
        const response = shouldFavorite
            ? await window.axios.post(endpoint)
            : await window.axios.delete(endpoint);

        return response?.data?.ok === true;
    } catch {
        return false;
    }
};

const normalizeProductModalId = (value) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed <= 0) return null;

    return productMap.value.has(parsed) ? parsed : null;
};

const syncModalQueryString = () => {
    if (typeof window === 'undefined') return;

    const url = new URL(window.location.href);
    const params = url.searchParams;
    params.delete('produto');
    params.delete('conta');
    params.delete('favoritos');

    if (productModalOpen.value && productModalId.value) {
        params.set('produto', String(productModalId.value));
    } else if (accountModalOpen.value) {
        params.set('conta', '1');
    } else if (favoritesModalOpen.value) {
        params.set('favoritos', '1');
    }

    const next = `${url.pathname}${params.toString() ? `?${params.toString()}` : ''}${url.hash || ''}`;
    window.history.replaceState({}, '', next);
};

const closeAllShopModals = () => {
    accountModalOpen.value = false;
    favoritesModalOpen.value = false;
    productModalOpen.value = false;
    productModalId.value = null;
    productModalQuantity.value = 1;
};

const openAccountModal = () => {
    if (!isShopAuthenticated.value) {
        if (typeof window !== 'undefined') window.location.href = loginUrl.value;
        return;
    }

    if (requiresEmailVerification.value && !isShopEmailVerified.value) {
        if (typeof window !== 'undefined') window.location.href = verifyEmailUrl.value;
        return;
    }

    closeAllShopModals();
    accountModalOpen.value = true;
    syncModalQueryString();
};

const openFavoritesModal = () => {
    closeAllShopModals();
    favoritesModalOpen.value = true;
    syncModalQueryString();
};

const openProductModal = (productId) => {
    const safeId = normalizeProductModalId(productId);
    if (!safeId) return;

    closeAllShopModals();
    productModalId.value = safeId;
    productModalQuantity.value = 1;
    productModalOpen.value = true;
    syncModalQueryString();
};

const closeAccountModal = () => {
    accountModalOpen.value = false;
    syncModalQueryString();
};

const closeFavoritesModal = () => {
    favoritesModalOpen.value = false;
    syncModalQueryString();
};

const closeProductModal = () => {
    productModalOpen.value = false;
    productModalId.value = null;
    productModalQuantity.value = 1;
    addToCartProcessing.value = false;
    syncModalQueryString();
};

onMounted(() => {
    if (typeof window === 'undefined') return;
    try {
        const raw = window.localStorage.getItem(cartStorageKey.value);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return;
        cartItems.value = parsed
            .map((i) => ({ product_id: Number(i.product_id), quantity: Math.max(1, Number(i.quantity || 1)) }))
            .filter((i) => productMap.value.has(i.product_id));
    } catch {
        cartItems.value = [];
    }

    if (isShopAuthenticated.value) {
        favoriteProductIds.value = serverFavoriteProductIds.value;
    } else {
        loadFavoriteIdsFromStorage();
    }

    const params = new URLSearchParams(window.location.search);
    const queryProductId = normalizeProductModalId(params.get('produto'));
    const queryAccount = params.get('conta');
    const queryFavorites = params.get('favoritos');

    if (queryProductId) {
        productModalId.value = queryProductId;
        productModalOpen.value = true;
    } else if (queryAccount === '1' || queryAccount === 'true') {
        if (isShopAuthenticated.value && (!requiresEmailVerification.value || isShopEmailVerified.value)) {
            accountModalOpen.value = true;
        }
    } else if (queryFavorites === '1' || queryFavorites === 'true') {
        favoritesModalOpen.value = true;
    }
});

watch(
    [isShopAuthenticated, serverFavoriteProductIds],
    ([authenticated]) => {
        if (authenticated) {
            favoriteProductIds.value = serverFavoriteProductIds.value;
            return;
        }

        loadFavoriteIdsFromStorage();
    },
    { immediate: true },
);

watch(
    shopCustomer,
    (customer) => {
        if (!customer) {
            profileForm.reset();
            return;
        }

        if (!String(checkoutForm.customer_name ?? '').trim()) {
            checkoutForm.customer_name = String(customer.name ?? '');
        }

        if (!String(checkoutForm.customer_phone ?? '').trim()) {
            checkoutForm.customer_phone = String(customer.phone ?? '');
        }

        if (!String(checkoutForm.customer_email ?? '').trim()) {
            checkoutForm.customer_email = String(customer.email ?? '');
        }

        profileForm.phone = String(customer.phone ?? '');
        profileForm.cep = String(customer.cep ?? '');
        profileForm.street = String(customer.street ?? '');
        profileForm.number = String(customer.number ?? '');
        profileForm.complement = String(customer.complement ?? '');
        profileForm.neighborhood = String(customer.neighborhood ?? '');
        profileForm.city = String(customer.city ?? '');
        profileForm.state = String(customer.state ?? '');
    },
    { immediate: true },
);

watch(
    cartItems,
    (value) => {
        if (typeof window === 'undefined') return;
        window.localStorage.setItem(cartStorageKey.value, JSON.stringify(value));
    },
    { deep: true },
);

watch(
    favoriteProductIds,
    (value) => {
        if (typeof window === 'undefined') return;
        if (isShopAuthenticated.value) return;
        window.localStorage.setItem(favoritesStorageKey.value, JSON.stringify(value));
    },
    { deep: true },
);

watch(
    [accountModalOpen, favoritesModalOpen, productModalOpen, productModalId],
    () => {
        syncModalQueryString();
    },
);

const cartDetailed = computed(() =>
    cartItems.value
        .map((i) => {
            const product = productMap.value.get(Number(i.product_id));
            if (!product) return null;
            return { ...i, product, line_total: Number(product.sale_price || 0) * Number(i.quantity || 1) };
        })
        .filter(Boolean),
);

const favoriteProducts = computed(() =>
    props.products
        .filter((product) => favoriteProductIdSet.value.has(Number(product.id)))
        .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'pt-BR')),
);

const accountOrders = computed(() =>
    Array.isArray(shopAccountOrders.value)
        ? [...shopAccountOrders.value].sort((a, b) => Number(b?.id || 0) - Number(a?.id || 0))
        : [],
);

const activeModalProduct = computed(() => {
    if (!productModalOpen.value || !productModalId.value) return null;
    return productMap.value.get(Number(productModalId.value)) ?? null;
});

const activeModalRelatedProducts = computed(() => {
    const active = activeModalProduct.value;
    if (!active) return [];

    const categoryId = Number(active.category_id || 0);
    const related = props.products.filter((product) => {
        if (Number(product.id) === Number(active.id)) return false;
        if (!Number.isFinite(categoryId) || categoryId <= 0) return true;
        return Number(product.category_id || 0) === categoryId;
    });

    return related.slice(0, 8);
});

const modalProductHasStock = computed(() => Number(activeModalProduct.value?.stock_quantity || 0) > 0);

const cartCount = computed(() => cartDetailed.value.reduce((acc, i) => acc + Number(i.quantity || 0), 0));
const cartSubtotal = computed(() => cartDetailed.value.reduce((acc, i) => acc + Number(i.line_total || 0), 0));
const shippingConfig = computed(() => {
    const raw = props.shipping_config ?? {};

    return {
        pickup_enabled: Boolean(raw.pickup_enabled ?? true),
        delivery_enabled: Boolean(raw.delivery_enabled ?? true),
        fixed_fee: Number(raw.fixed_fee ?? 0),
        free_over: Number(raw.free_over ?? 0),
        estimated_days: raw.estimated_days ? Number(raw.estimated_days) : null,
    };
});
const paymentMethodOptions = computed(() => (props.payment_methods ?? []).map((method) => ({
    value: String(method.id),
    label: method.name,
})));

const checkoutForm = useForm({
    customer_name: String(shopCustomer.value?.name ?? ''),
    customer_phone: String(shopCustomer.value?.phone ?? ''),
    customer_email: String(shopCustomer.value?.email ?? ''),
    idempotency_key: '',
    payment_method_id: '',
    delivery_mode: shippingConfig.value.delivery_enabled && !shippingConfig.value.pickup_enabled ? 'delivery' : 'pickup',
    shipping_postal_code: '',
    shipping_street: '',
    shipping_number: '',
    shipping_complement: '',
    shipping_district: '',
    shipping_city: '',
    shipping_state: '',
    notes: '',
    items: [],
});

const profileForm = useForm({
    phone: String(shopCustomer.value?.phone ?? ''),
    cep: String(shopCustomer.value?.cep ?? ''),
    street: String(shopCustomer.value?.street ?? ''),
    number: String(shopCustomer.value?.number ?? ''),
    complement: String(shopCustomer.value?.complement ?? ''),
    neighborhood: String(shopCustomer.value?.neighborhood ?? ''),
    city: String(shopCustomer.value?.city ?? ''),
    state: String(shopCustomer.value?.state ?? ''),
});

const logoutForm = useForm({});

const checkoutPaymentFromFlash = computed(() => page.props.flash?.checkout_payment ?? null);
const checkoutPayment = ref(null);
const checkoutPaymentPanelOpen = ref(false);
const checkoutPaymentPolling = ref(false);
const checkoutPaymentCopied = ref(false);
let checkoutPaymentPollTimer = null;

const showUiFeedback = (message, tone = 'success') => {
    if (uiFeedbackTimer) {
        clearTimeout(uiFeedbackTimer);
        uiFeedbackTimer = null;
    }

    uiFeedback.value = {
        visible: true,
        message: String(message ?? '').trim(),
        tone: tone === 'warning' ? 'warning' : 'success',
    };

    uiFeedbackTimer = setTimeout(() => {
        uiFeedback.value.visible = false;
        uiFeedbackTimer = null;
    }, 2200);
};

const normalizeCheckoutPaymentPayload = (payload) => {
    if (!payload || typeof payload !== 'object') return null;

    const saleId = Number(payload.sale_id ?? 0);
    if (!Number.isFinite(saleId) || saleId <= 0) return null;

    return {
        sale_id: saleId,
        sale_code: String(payload.sale_code ?? ''),
        sale_status: String(payload.sale_status ?? '').toLowerCase(),
        sale_status_label: String(payload.sale_status_label ?? 'Pedido recebido'),
        payment_id: Number(payload.payment_id ?? 0),
        payment_status: String(payload.payment_status ?? '').toLowerCase(),
        payment_status_label: String(payload.payment_status_label ?? 'Aguardando pagamento'),
        payment_method_code: String(payload.payment_method_code ?? '').toLowerCase(),
        payment_method_name: String(payload.payment_method_name ?? ''),
        provider: String(payload.provider ?? ''),
        transaction_reference: String(payload.transaction_reference ?? ''),
        amount: Number(payload.amount ?? 0),
        ticket_url: String(payload.ticket_url ?? ''),
        qr_code: String(payload.qr_code ?? ''),
        qr_code_base64: String(payload.qr_code_base64 ?? ''),
        expires_at: payload.expires_at ?? null,
    };
};

const isPixCheckoutPayment = computed(() =>
    String(checkoutPayment.value?.payment_method_code ?? '') === 'pix'
    && String(checkoutPayment.value?.qr_code ?? '').trim() !== '',
);
const checkoutPaymentQrImageSrc = computed(() => {
    const base64 = String(checkoutPayment.value?.qr_code_base64 ?? '').trim();
    if (!base64) return '';

    return `data:image/png;base64,${base64}`;
});

const checkoutPaymentStatusClass = computed(() => {
    const status = String(checkoutPayment.value?.payment_status ?? '');

    if (status === 'paid') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (status === 'failed' || status === 'cancelled') return 'border-rose-200 bg-rose-50 text-rose-700';
    if (status === 'refunded') return 'border-slate-300 bg-slate-100 text-slate-700';

    return 'border-amber-200 bg-amber-50 text-amber-700';
});

const checkoutPaymentStatusUrl = computed(() => {
    const saleId = Number(checkoutPayment.value?.sale_id ?? 0);
    if (!Number.isFinite(saleId) || saleId <= 0) return '';

    return `/shop/${storeSlug.value}/checkout/pagamento/${saleId}`;
});

const shouldPollCheckoutPayment = computed(() => {
    const status = String(checkoutPayment.value?.payment_status ?? '');

    return status === 'pending' || status === 'authorized';
});

const clearCheckoutPaymentPolling = () => {
    if (checkoutPaymentPollTimer) {
        clearInterval(checkoutPaymentPollTimer);
        checkoutPaymentPollTimer = null;
    }

    checkoutPaymentPolling.value = false;
};

const pollCheckoutPaymentStatus = async () => {
    if (!checkoutPaymentStatusUrl.value || typeof window === 'undefined' || !window.axios) return;

    try {
        const response = await window.axios.get(checkoutPaymentStatusUrl.value, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (response?.data?.ok !== true) return;

        const normalized = normalizeCheckoutPaymentPayload(response.data.payment);
        if (!normalized) return;

        checkoutPayment.value = normalized;

        if (!shouldPollCheckoutPayment.value) {
            clearCheckoutPaymentPolling();
        }
    } catch {
        // mantém polling ativo; falhas temporárias de rede serão reavaliadas no próximo ciclo
    }
};

const startCheckoutPaymentPolling = () => {
    clearCheckoutPaymentPolling();

    if (!shouldPollCheckoutPayment.value) return;

    checkoutPaymentPolling.value = true;
    pollCheckoutPaymentStatus();
    checkoutPaymentPollTimer = setInterval(() => {
        pollCheckoutPaymentStatus();
    }, 7000);
};

const openCheckoutPaymentPanel = () => {
    if (!checkoutPayment.value) return;
    checkoutPaymentPanelOpen.value = true;
};

const closeCheckoutPaymentPanel = () => {
    checkoutPaymentPanelOpen.value = false;
};

const copyCheckoutPixCode = async () => {
    const pixCode = String(checkoutPayment.value?.qr_code ?? '').trim();
    if (!pixCode || typeof navigator === 'undefined' || !navigator.clipboard) return;

    try {
        await navigator.clipboard.writeText(pixCode);
        checkoutPaymentCopied.value = true;
        setTimeout(() => {
            checkoutPaymentCopied.value = false;
        }, 2200);
    } catch {
        checkoutPaymentCopied.value = false;
    }
};

watch(
    checkoutPaymentFromFlash,
    (payload) => {
        const normalized = normalizeCheckoutPaymentPayload(payload);
        if (!normalized) return;
        if (String(normalized.payment_method_code ?? '') !== 'pix') return;

        checkoutPayment.value = normalized;
        checkoutPaymentPanelOpen.value = true;
        checkoutPaymentCopied.value = false;
        startCheckoutPaymentPolling();
    },
    { immediate: true },
);

watch(
    shouldPollCheckoutPayment,
    (shouldPoll) => {
        if (shouldPoll) {
            startCheckoutPaymentPolling();
            return;
        }

        clearCheckoutPaymentPolling();
    },
);

onBeforeUnmount(() => {
    clearCheckoutPaymentPolling();

    if (uiFeedbackTimer) {
        clearTimeout(uiFeedbackTimer);
        uiFeedbackTimer = null;
    }
});

const shippingFee = computed(() => {
    if (checkoutForm.delivery_mode !== 'delivery') return 0;

    const freeOver = Number(shippingConfig.value.free_over || 0);
    if (freeOver > 0 && cartSubtotal.value >= freeOver) {
        return 0;
    }

    return Number(shippingConfig.value.fixed_fee || 0);
});

const orderTotal = computed(() => Number(cartSubtotal.value || 0) + Number(shippingFee.value || 0));
const checkoutErrorMessage = computed(() =>
    checkoutForm.errors.customer_name
    || checkoutForm.errors.customer_phone
    || checkoutForm.errors.customer_email
    || checkoutForm.errors.payment_method_id
    || checkoutForm.errors.delivery_mode
    || checkoutForm.errors.shipping_postal_code
    || checkoutForm.errors.shipping_street
    || checkoutForm.errors.shipping_number
    || checkoutForm.errors.shipping_district
    || checkoutForm.errors.shipping_city
    || checkoutForm.errors.shipping_state
    || checkoutForm.errors.items
    || checkoutForm.errors.order,
);

watch(
    shippingConfig,
    (config) => {
        if (checkoutForm.delivery_mode === 'delivery' && !config.delivery_enabled) {
            checkoutForm.delivery_mode = config.pickup_enabled ? 'pickup' : 'delivery';
        }

        if (checkoutForm.delivery_mode === 'pickup' && !config.pickup_enabled && config.delivery_enabled) {
            checkoutForm.delivery_mode = 'delivery';
        }
    },
    { immediate: true },
);

const canSubmitCheckout = computed(() => {
    if (!isShopAuthenticated.value) return false;
    if (requiresEmailVerification.value && !isShopEmailVerified.value) return false;
    if (!isShopAddressComplete.value) return false;

    const name = String(checkoutForm.customer_name ?? '').trim();
    const hasContact = Boolean(String(checkoutForm.customer_phone ?? '').trim() || String(checkoutForm.customer_email ?? '').trim());
    const needsAddress = checkoutForm.delivery_mode === 'delivery';
    const hasAddress = !needsAddress || (
        String(checkoutForm.shipping_postal_code ?? '').trim() !== ''
        && String(checkoutForm.shipping_street ?? '').trim() !== ''
        && String(checkoutForm.shipping_number ?? '').trim() !== ''
        && String(checkoutForm.shipping_district ?? '').trim() !== ''
        && String(checkoutForm.shipping_city ?? '').trim() !== ''
        && String(checkoutForm.shipping_state ?? '').trim().length === 2
    );

    return cartDetailed.value.length > 0 && name !== '' && hasContact && hasAddress;
});

const createCheckoutIdempotencyKey = () => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return `shop-${crypto.randomUUID()}`;
    }

    return `shop-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
};

const addProductToCart = (id, quantity = 1) => {
    const safeId = Number(id);
    const safeQty = Math.max(1, Number(quantity || 1));
    const product = productMap.value.get(safeId);
    if (!product) return;

    const stock = Math.max(0, Number(product.stock_quantity || 0));
    if (stock <= 0) {
        showUiFeedback('Produto sem estoque no momento.', 'warning');
        return;
    }

    const existing = cartItems.value.find((item) => Number(item.product_id) === safeId);
    if (!existing) {
        cartItems.value.push({
            product_id: safeId,
            quantity: Math.min(stock, safeQty),
        });
        showUiFeedback('Produto adicionado ao carrinho.');
        return;
    }

    existing.quantity = Math.min(stock, Math.max(1, Number(existing.quantity || 1)) + safeQty);
    showUiFeedback('Quantidade atualizada no carrinho.');
};

const increment = (id) => {
    const item = cartItems.value.find((i) => Number(i.product_id) === Number(id));
    const product = productMap.value.get(Number(id));
    if (!item || !product) return;
    if (item.quantity < Number(product.stock_quantity || 0)) item.quantity += 1;
};
const decrement = (id) => {
    const item = cartItems.value.find((i) => Number(i.product_id) === Number(id));
    if (!item) return;
    item.quantity -= 1;
    if (item.quantity <= 0) cartItems.value = cartItems.value.filter((i) => Number(i.product_id) !== Number(id));
};
const removeFromCart = (id) => {
    cartItems.value = cartItems.value.filter((i) => Number(i.product_id) !== Number(id));
};

const isFavorite = (productId) => favoriteProductIdSet.value.has(Number(productId));

const toggleFavorite = async (productId) => {
    const targetId = Number(productId);
    if (!targetId) return;
    if (isShopAuthenticated.value && isFavoriteSyncing(targetId)) return;

    const wasFavorite = isFavorite(targetId);
    const previous = [...favoriteProductIds.value];
    favoriteProductIds.value = wasFavorite
        ? favoriteProductIds.value.filter((id) => Number(id) !== targetId)
        : normalizeFavoriteIds([...favoriteProductIds.value, targetId]);
    showUiFeedback(wasFavorite ? 'Produto removido dos favoritos.' : 'Produto adicionado aos favoritos.');

    if (!isShopAuthenticated.value) return;

    setFavoriteSyncing(targetId, true);
    const saved = await persistFavoriteOnServer(targetId, !wasFavorite);
    setFavoriteSyncing(targetId, false);

    if (!saved) {
        favoriteProductIds.value = previous;
        showUiFeedback('Não foi possível atualizar favoritos agora.', 'warning');
        return;
    }
};

const incrementModalProductQty = () => {
    const stock = Math.max(1, Number(activeModalProduct.value?.stock_quantity || 1));
    productModalQuantity.value = Math.min(stock, Number(productModalQuantity.value || 1) + 1);
};

const decrementModalProductQty = () => {
    productModalQuantity.value = Math.max(1, Number(productModalQuantity.value || 1) - 1);
};

const addActiveModalProductToCart = () => {
    const product = activeModalProduct.value;
    if (!product) return;
    if (addToCartProcessing.value) return;

    addToCartProcessing.value = true;
    window.setTimeout(() => {
        addProductToCart(product.id, productModalQuantity.value);
        cartOpen.value = true;
        addToCartProcessing.value = false;
    }, 350);
};

const onProfilePhoneInput = (event) => {
    profileForm.phone = formatPhoneBR(event?.target?.value ?? profileForm.phone);
};

const onProfileCepInput = (event) => {
    profileForm.cep = formatCepBR(event?.target?.value ?? profileForm.cep);
};

const lookupProfileCep = async () => {
    profileCepLookupError.value = '';
    profileForm.cep = formatCepBR(profileForm.cep);

    if (!profileForm.cep) return;
    if (profileForm.cep.length !== 9) {
        profileCepLookupError.value = 'CEP inválido. Digite os 8 números.';
        return;
    }

    const cepDigits = profileForm.cep.replace(/\D/g, '');
    profileCepLookupLoading.value = true;

    try {
        const response = await fetch(`https://viacep.com.br/ws/${cepDigits}/json/`);
        if (!response.ok) throw new Error('lookup_failed');

        const payload = await response.json();
        if (payload?.erro) {
            profileCepLookupError.value = 'CEP não encontrado.';
            return;
        }

        const parsed = viaCepToAddress(payload);
        profileForm.cep = parsed.cep || profileForm.cep;
        profileForm.street = parsed.street || profileForm.street;
        profileForm.neighborhood = parsed.neighborhood || profileForm.neighborhood;
        profileForm.city = parsed.city || profileForm.city;
        profileForm.state = parsed.state || profileForm.state;

        if (!String(profileForm.complement ?? '').trim()) {
            profileForm.complement = parsed.complement || '';
        }
    } catch {
        profileCepLookupError.value = 'Não foi possível consultar o CEP agora. Preencha manualmente.';
    } finally {
        profileCepLookupLoading.value = false;
    }
};

const submitProfileUpdate = () => {
    profileCepLookupError.value = '';
    profileForm.phone = formatPhoneBR(profileForm.phone);
    profileForm.cep = formatCepBR(profileForm.cep);
    profileForm.state = normalizeStateCode(profileForm.state);

    profileForm.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post(accountUrl.value, {
        preserveScroll: true,
        onFinish: () => {
            profileForm.transform((data) => data);
        },
    });
};

const doShopLogout = () => {
    logoutForm.post(`/shop/${storeSlug.value}/sair`);
};

const checkout = () => {
    if (!canSubmitCheckout.value) return;

    checkoutForm.clearErrors();

    if (!String(checkoutForm.idempotency_key ?? '').trim()) {
        checkoutForm.idempotency_key = createCheckoutIdempotencyKey();
    }

    checkoutForm.items = cartDetailed.value.map((item) => ({
        product_id: Number(item.product.id),
        quantity: Number(item.quantity),
    }));

    checkoutForm.post(checkoutUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            cartItems.value = [];
            checkoutForm.reset('payment_method_id', 'notes', 'items', 'idempotency_key');
            cartOpen.value = false;
            cartMobileStep.value = 1;
            showUiFeedback('Pedido enviado com sucesso.');
        },
    });
};

const scrollTop = () => {
    if (typeof window === 'undefined') return;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
const scrollCategories = () => {
    categorySectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const openCartFromMenu = () => {
    leftMenuOpen.value = false;
    cartMobileStep.value = 1;
    cartOpen.value = true;
};

const goToCartCheckoutStep = () => {
    if (!cartDetailed.value.length) return;
    cartMobileStep.value = 2;
};

const goToCartItemsStep = () => {
    cartMobileStep.value = 1;
};

watch(cartOpen, (isOpen) => {
    if (isOpen) {
        cartMobileStep.value = 1;
    }
});
</script>

<template>
    <Head :title="`${storeName}`">
        <link v-if="publicFaviconHref" rel="icon" :href="publicFaviconHref" :type="publicFaviconType" />
    </Head>

    <div class="min-h-screen bg-slate-100 text-slate-900" :style="pageStyles">
        <transition name="cart-overlay">
            <div
                v-if="uiFeedback.visible && uiFeedback.message"
                class="fixed inset-x-0 top-20 z-[95] flex justify-center px-4"
            >
                <div
                    class="inline-flex max-w-md items-center rounded-xl border px-3 py-2 text-xs font-semibold shadow-lg"
                    :class="uiFeedback.tone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
                >
                    {{ uiFeedback.message }}
                </div>
            </div>
        </transition>
        <header class="sticky top-0 z-40 border-b border-white/70 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="hidden h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 sm:inline-flex"
                        @click="leftMenuOpen = true"
                    >
                        <Menu class="h-4 w-4" />
                    </button>
                    <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-slate-100" :style="storeIconStyle">
                        <img
                            v-if="storeLogo"
                            :src="storeLogo"
                            :alt="storeName"
                            class="h-full w-full object-cover"
                            @error="handleStoreLogoError"
                        />
                        <span v-else class="text-xs font-semibold">{{ storeInitials }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ storeName }}</p>
                        <p class="truncate text-xs text-slate-500">Loja pública</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="hidden items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition sm:inline-flex"
                        :class="favoritesModalOpen ? 'border-transparent text-white shadow-inner shadow-black/10' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                        :style="favoritesModalOpen ? { background: 'var(--catalog-primary-strong)' } : null"
                        @click="openFavoritesModal"
                    >
                        <Heart class="h-3.5 w-3.5" />
                        {{ `Favoritos (${favoriteCount})` }}
                    </button>
                    <button type="button" class="hidden items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:inline-flex" @click="openAccountModal">
                        <LogIn class="h-3.5 w-3.5" />
                        {{ isShopAuthenticated ? 'Minha conta' : 'Entrar' }}
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl px-4 pb-24 pt-5 sm:px-6 lg:px-8 lg:pb-10">
            <section
                v-if="isPixCheckoutPayment"
                class="mb-5 rounded-2xl border px-4 py-3 shadow-sm"
                :class="checkoutPaymentStatusClass"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold">Pagamento Pix do pedido {{ checkoutPayment.sale_code || `#${checkoutPayment.sale_id}` }}</p>
                        <p class="text-xs">
                            Status: <span class="font-semibold">{{ checkoutPayment.payment_status_label }}</span>
                            <span v-if="checkoutPaymentPolling" class="ml-1">• atualizando...</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-current/30 bg-white/80 px-3 py-2 text-xs font-semibold transition hover:bg-white"
                            @click="openCheckoutPaymentPanel"
                        >
                            Ver cobrança Pix
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-current/30 bg-white/80 px-3 py-2 text-xs font-semibold transition hover:bg-white"
                            @click="openAccountModal"
                        >
                            Meus pedidos
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="storefrontBlocks.hero" class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="pointer-events-none absolute inset-0 opacity-90" style="background: var(--catalog-gradient)"></div>
                <div class="relative grid gap-3 md:grid-cols-[1fr,220px] md:items-center">
                    <div class="space-y-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ storefrontHeroTitle }}</h1>
                        <p class="max-w-2xl text-sm text-slate-700">{{ storefrontHeroSubtitle }}</p>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <label class="veshop-search-shell flex min-w-0 flex-1 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                                <Search class="veshop-search-icon h-4 w-4 text-slate-400" />
                                <input v-model="search" type="search" class="veshop-search-input text-sm text-slate-700" :placeholder="searchPlaceholder" />
                            </label>
                            <select v-model="sortMode" class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm sm:w-[210px]">
                                <option value="featured">Relevância</option>
                                <option value="name">Nome (A-Z)</option>
                                <option value="price_asc">Menor preço</option>
                                <option value="price_desc">Maior preço</option>
                            </select>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">No carrinho</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ cartCount }} item(ns)</p>
                        <button type="button" class="mt-2 inline-flex w-full items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold text-white shadow-sm" style="background: var(--catalog-primary-strong)" @click="cartOpen = true">Ver carrinho</button>
                    </div>
                </div>
            </section>

            <section v-if="storefrontBlocks.catalog && !storefrontBlocks.hero" class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="grid gap-3 md:grid-cols-[1fr,220px] md:items-center">
                    <div class="space-y-2">
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ storefrontCatalogTitle }}</h1>
                        <p class="text-sm text-slate-600">{{ storefrontCatalogSubtitle }}</p>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <label class="veshop-search-shell flex min-w-0 flex-1 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                                <Search class="veshop-search-icon h-4 w-4 text-slate-400" />
                                <input v-model="search" type="search" class="veshop-search-input text-sm text-slate-700" :placeholder="searchPlaceholder" />
                            </label>
                            <select v-model="sortMode" class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm sm:w-[210px]">
                                <option value="featured">Relevância</option>
                                <option value="name">Nome (A-Z)</option>
                                <option value="price_asc">Menor preço</option>
                                <option value="price_desc">Maior preço</option>
                            </select>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">No carrinho</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ cartCount }} item(ns)</p>
                        <button type="button" class="mt-2 inline-flex w-full items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold text-white shadow-sm" style="background: var(--catalog-primary-strong)" @click="cartOpen = true">Ver carrinho</button>
                    </div>
                </div>
            </section>

            <section v-if="storefrontBlocks.promotions && promotionProducts.length" class="mt-6 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ storefrontPromotionsTitle }}</h2>
                        <p class="text-xs text-slate-500">{{ storefrontPromotionsSubtitle }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <div
                        v-for="product in promotionProducts"
                        :key="`promo-${product.id}`"
                        class="group block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                        @click="openProductModal(product.id)"
                    >
                        <div class="relative aspect-square overflow-hidden bg-slate-100">
                            <img :src="productImage(product)" :alt="product.name" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]" />
                            <span class="absolute left-2 top-2 inline-flex rounded-full bg-white/90 px-2 py-1 text-[10px] font-semibold text-slate-700">
                                Destaque
                            </span>
                        </div>
                        <div class="space-y-1.5 p-3">
                            <h3 class="min-h-[2.25rem] text-sm font-semibold leading-tight text-slate-900">{{ product.name }}</h3>
                            <p class="text-sm font-bold text-slate-900">{{ currency(product.sale_price) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="storefrontBlocks.categories" ref="categorySectionRef" class="mt-6 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-slate-900">Categorias</h2>
                    <p class="text-xs text-slate-500">{{ filteredProducts.length }} {{ catalogItemLabel }}</p>
                </div>
                <div class="catalog-chip-scroll flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="category in categoryList"
                        :key="`cat-${category.id}`"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition"
                        :class="String(selectedCategory) === String(category.id) ? 'text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        :style="String(selectedCategory) === String(category.id) ? { background: 'var(--catalog-primary-strong)', borderColor: 'var(--catalog-primary-strong)' } : null"
                        @click="selectedCategory = category.id"
                    >
                        <Tags class="h-3.5 w-3.5" />
                        {{ category.name }}
                        <span class="inline-flex min-w-[20px] items-center justify-center rounded-full border px-1.5 py-0.5 text-[10px] font-bold" :class="String(selectedCategory) === String(category.id) ? 'border-white/35 bg-white/20 text-white' : 'border-slate-200 bg-slate-100 text-slate-600'">{{ category.products_count }}</span>
                    </button>
                </div>
            </section>

            <section v-if="storefrontBlocks.catalog" class="mt-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ storefrontCatalogTitle }}</h2>
                        <p class="text-xs text-slate-500">{{ storefrontCatalogSubtitle }}</p>
                    </div>
                    <p class="text-xs text-slate-500">{{ filteredProducts.length }} {{ catalogItemLabel }}</p>
                </div>
                <div v-if="paginatedProducts.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <div
                        v-for="product in paginatedProducts"
                        :key="`prod-${product.id}`"
                        class="group block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                        @click="openProductModal(product.id)"
                    >
                        <div class="relative aspect-square overflow-hidden bg-slate-100">
                            <img :src="productImage(product)" :alt="product.name" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]" />
                            <button
                                type="button"
                                class="absolute right-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-full border shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60"
                                :class="isFavorite(product.id) ? 'border-rose-200 bg-rose-50 text-rose-600' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                                :disabled="isFavoriteSyncing(product.id)"
                                @click.stop.prevent="toggleFavorite(product.id)"
                            >
                                <Heart class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="space-y-2 p-3">
                            <h3 class="min-h-[2.5rem] text-sm font-semibold leading-tight text-slate-900">{{ product.name }}</h3>
                            <div>
                                <p class="text-[11px] text-slate-500">Preço</p>
                                <p class="text-base font-bold text-slate-900">{{ currency(product.sale_price) }}</p>
                            </div>
                            <p class="text-[11px] text-slate-500">Toque para ver detalhes</p>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">Nenhum item encontrado para os filtros informados.</div>

                <div class="mt-6 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">Mostrando {{ rangeText }}</p>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage <= 1" @click="currentPage = Math.max(1, currentPage - 1)">
                            <ChevronLeft class="h-3.5 w-3.5" />
                            Anterior
                        </button>
                        <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">{{ currentPage }} / {{ totalPages }}</span>
                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage >= totalPages" @click="currentPage = Math.min(totalPages, currentPage + 1)">
                            Próximo
                            <ChevronRight class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </section>

            <section v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                Catálogo indisponível no momento.
            </section>
        </main>

                <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 pb-[max(env(safe-area-inset-bottom),0.45rem)] pt-2 shadow-[0_-10px_30px_-20px_rgba(15,23,42,0.22)] backdrop-blur sm:hidden">
            <div class="mx-auto flex max-w-md items-end gap-1">
                <button type="button" class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold text-slate-600 hover:bg-slate-100" @click="leftMenuOpen = true"><Menu class="h-4 w-4" />Menu</button>
                <button type="button" class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold text-slate-600 hover:bg-slate-100" @click="scrollTop"><Home class="h-4 w-4" />Início</button>
                <button
                    type="button"
                    class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold transition"
                    :class="favoritesModalOpen ? 'text-white shadow-inner shadow-black/10' : 'text-slate-600 hover:bg-slate-100'"
                    :style="favoritesModalOpen ? { background: 'var(--catalog-primary-strong)' } : null"
                    @click="openFavoritesModal"
                >
                    <Heart class="h-4 w-4" />
                    Favoritos
                </button>
                <button
                    type="button"
                    class="relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold transition"
                    :class="cartOpen ? 'text-white shadow-inner shadow-black/10' : 'text-slate-600 hover:bg-slate-100'"
                    :style="cartOpen ? { background: 'var(--catalog-primary-strong)' } : null"
                    @click="cartOpen = true"
                >
                    <ShoppingCart class="h-4 w-4" />Carrinho
                    <span v-if="cartCount > 0" class="absolute right-2 top-1 inline-flex min-w-[16px] items-center justify-center rounded-full bg-white px-1 py-0.5 text-[9px] font-bold text-slate-900">{{ cartCount }}</span>
                </button>
                <button type="button" class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold text-slate-600 hover:bg-slate-100" @click="openAccountModal"><UserCircle2 class="h-4 w-4" />Conta</button>
            </div>
        </nav>
        <transition name="menu-overlay">
            <div v-if="leftMenuOpen" class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[1px]" @click="leftMenuOpen = false"></div>
                <aside class="absolute left-0 top-0 flex h-full w-full max-w-sm flex-col border-r border-slate-200 bg-white shadow-2xl">
                    <header class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Menu</p>
                            <p class="text-xs text-slate-500">{{ storeName }}</p>
                        </div>
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" @click="leftMenuOpen = false">
                            <X class="h-4 w-4" />
                        </button>
                    </header>

                    <div class="flex-1 space-y-4 overflow-y-auto p-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-sm font-semibold text-slate-900">{{ storeName }}</p>
                            <p class="mt-1 text-xs text-slate-500">Selecione uma seção do catálogo.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="leftMenuOpen = false; scrollTop()">
                                <Home class="h-3.5 w-3.5" />
                                Início
                            </button>
                            <button v-if="storefrontBlocks.categories" type="button" class="inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="leftMenuOpen = false; scrollCategories()">
                                <Tags class="h-3.5 w-3.5" />
                                Categorias
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-1 rounded-xl border px-3 py-2 text-xs font-semibold transition"
                                :class="cartOpen ? 'border-transparent text-white shadow-inner shadow-black/10' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                :style="cartOpen ? { background: 'var(--catalog-primary-strong)' } : null"
                                @click="openCartFromMenu"
                            >
                                <ShoppingCart class="h-3.5 w-3.5" />
                                Carrinho
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-1 rounded-xl border px-3 py-2 text-xs font-semibold transition"
                                :class="favoritesModalOpen ? 'border-transparent text-white shadow-inner shadow-black/10' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                :style="favoritesModalOpen ? { background: 'var(--catalog-primary-strong)' } : null"
                                @click="openFavoritesModal(); leftMenuOpen = false"
                            >
                                <Heart class="h-3.5 w-3.5" />
                                Favoritos
                            </button>
                            <button type="button" class="inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="openAccountModal(); leftMenuOpen = false">
                                <UserCircle2 class="h-3.5 w-3.5" />
                                Conta
                            </button>
                        </div>

                        <div v-if="storefrontBlocks.categories" class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Categorias</p>
                            <div class="grid gap-2">
                                <button
                                    v-for="category in categoryList"
                                    :key="`menu-cat-${category.id}`"
                                    type="button"
                                    class="inline-flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="selectedCategory = category.id; leftMenuOpen = false; scrollCategories()"
                                >
                                    <span class="truncate">{{ category.name }}</span>
                                    <span class="ml-2 inline-flex min-w-[20px] items-center justify-center rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">
                                        {{ category.products_count }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </transition>
        <transition name="cart-overlay">
            <div v-if="cartOpen" class="fixed inset-0 z-[60]">
                <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[1px]" @click="cartOpen = false"></div>
                <aside class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl">
                    <header class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
                        <div><p class="text-sm font-semibold text-slate-900">Carrinho</p><p class="text-xs text-slate-500">{{ cartCount }} item(ns)</p></div>
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" @click="cartOpen = false"><X class="h-4 w-4" /></button>
                    </header>
                    <div class="border-b border-slate-200 px-4 py-2 md:hidden">
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                                :class="cartMobileStep === 1 ? 'border-transparent text-white shadow-inner shadow-black/10' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                :style="cartMobileStep === 1 ? { background: 'var(--catalog-primary-strong)' } : null"
                                @click="goToCartItemsStep"
                            >
                                1. Itens
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                                :class="cartMobileStep === 2 ? 'border-transparent text-white shadow-inner shadow-black/10' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                :style="cartMobileStep === 2 ? { background: 'var(--catalog-primary-strong)' } : null"
                                :disabled="!cartDetailed.length"
                                @click="goToCartCheckoutStep"
                            >
                                2. Finalizar
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 space-y-3 overflow-y-auto p-4" :class="cartMobileStep === 2 ? 'hidden md:block' : 'block'">
                        <div v-if="!cartDetailed.length" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">Seu carrinho está vazio.</div>
                        <article v-for="item in cartDetailed" :key="`cart-${item.product.id}`" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                            <div class="flex items-start gap-3">
                                <img :src="productImage(item.product)" :alt="item.product.name" class="h-16 w-16 rounded-xl object-cover" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ item.product.name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ currency(item.product.sale_price) }} cada</p>
                                    <div class="mt-3 flex items-center justify-between gap-2">
                                        <div class="inline-flex items-center rounded-lg border border-slate-200 bg-white">
                                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center text-slate-600 hover:bg-slate-50" @click="decrement(item.product.id)"><Minus class="h-3.5 w-3.5" /></button>
                                            <span class="min-w-[34px] text-center text-sm font-semibold text-slate-800">{{ item.quantity }}</span>
                                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center text-slate-600 hover:bg-slate-50" @click="increment(item.product.id)"><Plus class="h-3.5 w-3.5" /></button>
                                        </div>
                                        <p class="text-sm font-bold text-slate-900">{{ currency(item.line_total) }}</p>
                                    </div>
                                </div>
                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100" @click="removeFromCart(item.product.id)"><Trash2 class="h-3.5 w-3.5" /></button>
                            </div>
                        </article>
                    </div>
                    <footer class="space-y-3 border-t border-slate-200 p-4">
                        <div v-if="cartMobileStep === 1" class="md:hidden">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Subtotal</span>
                                <span class="text-base font-bold text-slate-900">{{ currency(cartSubtotal) }}</span>
                            </div>
                            <button
                                type="button"
                                class="mt-3 inline-flex w-full items-center justify-center rounded-xl px-3 py-2 text-xs font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-40"
                                style="background: var(--catalog-primary-strong)"
                                :disabled="!cartDetailed.length"
                                @click="goToCartCheckoutStep"
                            >
                                Ir para finalizar
                            </button>
                        </div>

                        <div :class="cartMobileStep === 1 ? 'hidden md:block' : 'block'">
                        <div
                            v-if="!isShopAuthenticated"
                            class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700"
                        >
                            Faça login para finalizar seu pedido.
                            <Link :href="loginUrl" class="ml-1 underline decoration-dotted underline-offset-2">Entrar agora</Link>
                        </div>
                        <div
                            v-else-if="requiresEmailVerification && !isShopEmailVerified"
                            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700"
                        >
                            Confirme seu e-mail para finalizar pedidos.
                            <Link :href="verifyEmailUrl" class="ml-1 underline decoration-dotted underline-offset-2">Verificar e-mail</Link>
                        </div>
                        <div
                            v-else-if="!isShopAddressComplete"
                            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700"
                        >
                            Complete seu endereço em Minha conta para finalizar pedidos.
                            <button type="button" class="ml-1 underline decoration-dotted underline-offset-2" @click="openAccountModal">Atualizar endereço</button>
                            <span v-if="missingShopAddressFields.length" class="ml-1">
                                (faltando: {{ missingShopAddressFields.join(', ') }})
                            </span>
                        </div>

                        <div class="grid gap-2">
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                                    :class="checkoutForm.delivery_mode === 'pickup' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                    :disabled="!shippingConfig.pickup_enabled"
                                    @click="checkoutForm.delivery_mode = 'pickup'"
                                >
                                    Retirada
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
                                    :class="checkoutForm.delivery_mode === 'delivery' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                    :disabled="!shippingConfig.delivery_enabled"
                                    @click="checkoutForm.delivery_mode = 'delivery'"
                                >
                                    Entrega
                                </button>
                            </div>

                            <input
                                v-model="checkoutForm.customer_name"
                                type="text"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Seu nome"
                            >
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <input
                                    v-model="checkoutForm.customer_phone"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="WhatsApp"
                                >
                                <input
                                    v-model="checkoutForm.customer_email"
                                    type="email"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="E-mail"
                                >
                            </div>

                            <div v-if="checkoutForm.delivery_mode === 'delivery'" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <input
                                    v-model="checkoutForm.shipping_postal_code"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="CEP"
                                >
                                <input
                                    v-model="checkoutForm.shipping_state"
                                    type="text"
                                    maxlength="2"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm uppercase text-slate-700"
                                    placeholder="UF"
                                >
                                <input
                                    v-model="checkoutForm.shipping_street"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 sm:col-span-2"
                                    placeholder="Rua"
                                >
                                <input
                                    v-model="checkoutForm.shipping_number"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Número"
                                >
                                <input
                                    v-model="checkoutForm.shipping_complement"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Complemento (opcional)"
                                >
                                <input
                                    v-model="checkoutForm.shipping_district"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Bairro"
                                >
                                <input
                                    v-model="checkoutForm.shipping_city"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Cidade"
                                >
                            </div>

                            <select
                                v-model="checkoutForm.payment_method_id"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            >
                                <option value="">Forma de pagamento (opcional)</option>
                                <option
                                    v-for="method in paymentMethodOptions"
                                    :key="`checkout-payment-${method.value}`"
                                    :value="method.value"
                                >
                                    {{ method.label }}
                                </option>
                            </select>
                            <textarea
                                v-model="checkoutForm.notes"
                                rows="2"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Observações (opcional)"
                            />
                            <p v-if="checkoutErrorMessage" class="text-xs font-semibold text-rose-600">{{ checkoutErrorMessage }}</p>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Subtotal</span>
                            <span class="text-base font-bold text-slate-900">{{ currency(cartSubtotal) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Frete</span>
                            <span class="text-base font-semibold text-slate-900">{{ currency(shippingFee) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Total</span>
                            <span class="text-base font-bold text-slate-900">{{ currency(orderTotal) }}</span>
                        </div>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl px-3 py-2 text-xs font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-40"
                            style="background: var(--catalog-primary-strong)"
                            :disabled="checkoutForm.processing || !canSubmitCheckout"
                            @click="checkout"
                        >
                            <span v-if="checkoutForm.processing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            {{ checkoutForm.processing ? 'Enviando pedido...' : 'Finalizar pedido' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 md:hidden"
                            @click="goToCartItemsStep"
                        >
                            Voltar para itens
                        </button>
                        </div>
                    </footer>
                </aside>
            </div>
        </transition>
        <transition name="cart-overlay">
            <div v-if="productModalOpen && activeModalProduct" class="fixed inset-0 z-[72]">
                <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[1px]" @click="closeProductModal"></div>
                <section class="absolute inset-0 flex items-center justify-center p-3 sm:p-5">
                    <div class="relative flex w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
                        <header class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ activeModalProduct.name }}</p>
                                <p class="text-xs text-slate-500">{{ activeModalProduct.category_name || 'Produto' }}</p>
                            </div>
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" @click="closeProductModal">
                                <X class="h-4 w-4" />
                            </button>
                        </header>

                        <div class="max-h-[88vh] overflow-y-auto p-4 sm:p-6">
                            <div class="mx-auto grid w-full max-w-5xl gap-5 lg:grid-cols-[minmax(0,1fr),minmax(0,1fr)]">
                                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                                    <img :src="productImage(activeModalProduct)" :alt="activeModalProduct.name" class="aspect-[4/3] h-full w-full object-cover sm:aspect-square" />
                                </div>

                                <div class="space-y-4">
                                    <div class="space-y-1">
                                        <h2 class="text-2xl font-bold tracking-tight text-slate-900">{{ activeModalProduct.name }}</h2>
                                        <p class="text-xs text-slate-500">{{ activeModalProduct.sku || 'Sem SKU' }}</p>
                                    </div>

                                    <p v-if="activeModalProduct.description" class="text-sm leading-relaxed text-slate-600">{{ activeModalProduct.description }}</p>

                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Preço</p>
                                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ currency(activeModalProduct.sale_price) }}</p>
                                        <p class="mt-2 text-xs font-semibold" :class="modalProductHasStock ? 'text-emerald-700' : 'text-rose-600'">
                                            {{ modalProductHasStock ? `${activeModalProduct.stock_quantity} ${activeModalProduct.unit || 'un'} em estoque` : 'Indisponível no momento' }}
                                        </p>
                                    </div>

                                    <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                                        <div class="inline-flex items-center rounded-xl border border-slate-200 bg-white">
                                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center text-slate-600 hover:bg-slate-50" @click="decrementModalProductQty"><Minus class="h-4 w-4" /></button>
                                            <span class="min-w-[42px] text-center text-sm font-semibold text-slate-800">{{ productModalQuantity }}</span>
                                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center text-slate-600 hover:bg-slate-50" @click="incrementModalProductQty"><Plus class="h-4 w-4" /></button>
                                        </div>

                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl px-3 py-2 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-40"
                                                style="background: var(--catalog-primary-strong)"
                                                :disabled="!modalProductHasStock || addToCartProcessing"
                                                @click="addActiveModalProductToCart"
                                            >
                                                <span v-if="addToCartProcessing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                                {{ addToCartProcessing ? 'Adicionando...' : 'Adicionar ao carrinho' }}
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                                :disabled="isFavoriteSyncing(activeModalProduct.id)"
                                                @click="toggleFavorite(activeModalProduct.id)"
                                            >
                                                {{ isFavorite(activeModalProduct.id) ? 'Remover dos favoritos' : 'Adicionar aos favoritos' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="activeModalRelatedProducts.length" class="mx-auto mt-6 w-full max-w-5xl">
                                <p class="mb-3 text-sm font-semibold text-slate-900">Produtos relacionados</p>
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                    <div
                                        v-for="related in activeModalRelatedProducts"
                                        :key="`related-modal-${related.id}`"
                                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                                    >
                                        <button type="button" class="w-full text-left" @click="openProductModal(related.id)">
                                            <div class="aspect-square overflow-hidden bg-slate-100">
                                                <img :src="productImage(related)" :alt="related.name" class="h-full w-full object-cover" />
                                            </div>
                                            <div class="space-y-1 p-3">
                                                <p class="line-clamp-2 text-sm font-semibold text-slate-900">{{ related.name }}</p>
                                                <p class="text-sm font-bold text-slate-900">{{ currency(related.sale_price) }}</p>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </transition>
        <transition name="cart-overlay">
            <div v-if="favoritesModalOpen" class="fixed inset-0 z-[60]">
                <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[1px]" @click="closeFavoritesModal"></div>
                <aside class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl">
                    <header class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Favoritos</p>
                            <p class="text-xs text-slate-500">{{ favoriteProducts.length }} item(ns)</p>
                        </div>
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" @click="closeFavoritesModal">
                            <X class="h-4 w-4" />
                        </button>
                    </header>

                    <div class="flex-1 space-y-3 overflow-y-auto p-4">
                        <div v-if="!favoriteProducts.length" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                            Nenhum favorito salvo nesta loja.
                        </div>
                        <article v-for="product in favoriteProducts" :key="`fav-modal-${product.id}`" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                            <div class="flex items-start gap-3">
                                <button type="button" class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100" @click="openProductModal(product.id)">
                                    <img :src="productImage(product)" :alt="product.name" class="h-16 w-16 object-cover" />
                                </button>
                                <div class="min-w-0 flex-1">
                                    <button type="button" class="truncate text-left text-sm font-semibold text-slate-900 hover:text-slate-700" @click="openProductModal(product.id)">
                                        {{ product.name }}
                                    </button>
                                    <p class="mt-1 text-xs text-slate-500">{{ product.category_name || 'Produto' }}</p>
                                    <p class="mt-2 text-sm font-bold text-slate-900">{{ currency(product.sale_price) }}</p>
                                    <button
                                        type="button"
                                        class="mt-2 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        @click="openProductModal(product.id)"
                                    >
                                        Ver produto
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="isFavoriteSyncing(product.id)"
                                    @click="toggleFavorite(product.id)"
                                >
                                    <Heart class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </article>
                    </div>
                    <footer class="space-y-3 border-t border-slate-200 p-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Total de favoritos</span>
                            <span class="text-base font-bold text-slate-900">{{ favoriteProducts.length }}</span>
                        </div>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeFavoritesModal"
                        >
                            Continuar comprando
                        </button>
                    </footer>
                </aside>
            </div>
        </transition>
        <transition name="cart-overlay">
            <div v-if="accountModalOpen" class="fixed inset-0 z-[60]">
                <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[1px]" @click="closeAccountModal"></div>
                <aside class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl">
                    <header class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Minha conta</p>
                            <p class="text-xs text-slate-500">{{ shopCustomer?.name || 'Cliente' }}</p>
                        </div>
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" @click="closeAccountModal">
                            <X class="h-4 w-4" />
                        </button>
                    </header>

                    <div class="flex-1 overflow-y-auto p-4">
                        <div class="w-full space-y-5">
                            <div v-if="flashStatusMessage" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                                {{ flashStatusMessage }}
                            </div>

                            <section v-if="!isShopAuthenticated" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">
                                Faça login para acessar os dados da sua conta.
                                <a :href="loginUrl" class="ml-1 underline decoration-dotted underline-offset-2">Entrar agora</a>
                            </section>

                            <section v-else-if="requiresEmailVerification && !isShopEmailVerified" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                                Confirme seu e-mail para liberar a conta e finalizar pedidos.
                                <a :href="verifyEmailUrl" class="ml-1 underline decoration-dotted underline-offset-2">Verificar e-mail</a>
                            </section>

                            <template v-else>
                                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <div class="mb-4 flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-900">Dados cadastrais</p>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            :disabled="logoutForm.processing"
                                            @click="doShopLogout"
                                        >
                                            <LogOut class="h-3.5 w-3.5" />
                                            Sair
                                        </button>
                                    </div>

                                    <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submitProfileUpdate">
                                        <div class="md:col-span-2">
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                                            <input
                                                :value="profileForm.phone"
                                                type="text"
                                                inputmode="numeric"
                                                maxlength="15"
                                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                                placeholder="(11) 99999-9999"
                                                @input="onProfilePhoneInput"
                                            >
                                            <p v-if="profileForm.errors.phone" class="mt-1 text-xs font-semibold text-rose-600">{{ profileForm.errors.phone }}</p>
                                        </div>

                                        <div class="md:col-span-2">
                                            <div class="flex flex-wrap items-end gap-2">
                                                <div class="min-w-[180px] flex-1">
                                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">CEP</label>
                                                    <input
                                                        :value="profileForm.cep"
                                                        type="text"
                                                        inputmode="numeric"
                                                        maxlength="9"
                                                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                                        placeholder="00000-000"
                                                        @input="onProfileCepInput"
                                                        @blur="lookupProfileCep"
                                                    >
                                                </div>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                                    :disabled="profileCepLookupLoading"
                                                    @click="lookupProfileCep"
                                                >
                                                    {{ profileCepLookupLoading ? 'Consultando...' : 'Consultar CEP' }}
                                                </button>
                                            </div>
                                            <p v-if="profileForm.errors.cep" class="mt-1 text-xs font-semibold text-rose-600">{{ profileForm.errors.cep }}</p>
                                            <p v-if="profileCepLookupError" class="mt-2 text-xs font-semibold text-amber-700">{{ profileCepLookupError }}</p>
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Logradouro</label>
                                            <input v-model="profileForm.street" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Rua, avenida, etc">
                                            <p v-if="profileForm.errors.street" class="mt-1 text-xs font-semibold text-rose-600">{{ profileForm.errors.street }}</p>
                                        </div>

                                        <div>
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Número</label>
                                            <input v-model="profileForm.number" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="123">
                                            <p v-if="profileForm.errors.number" class="mt-1 text-xs font-semibold text-rose-600">{{ profileForm.errors.number }}</p>
                                        </div>

                                        <div>
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Complemento</label>
                                            <input v-model="profileForm.complement" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Apto, bloco, etc.">
                                            <p v-if="profileForm.errors.complement" class="mt-1 text-xs font-semibold text-rose-600">{{ profileForm.errors.complement }}</p>
                                        </div>

                                        <div>
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bairro</label>
                                            <input v-model="profileForm.neighborhood" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Bairro">
                                            <p v-if="profileForm.errors.neighborhood" class="mt-1 text-xs font-semibold text-rose-600">{{ profileForm.errors.neighborhood }}</p>
                                        </div>

                                        <div>
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cidade</label>
                                            <input v-model="profileForm.city" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Cidade">
                                            <p v-if="profileForm.errors.city" class="mt-1 text-xs font-semibold text-rose-600">{{ profileForm.errors.city }}</p>
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">UF</label>
                                            <select v-model="profileForm.state" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                                <option v-for="option in stateOptions" :key="`state-shop-account-${option.value || 'empty'}`" :value="option.value">
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                            <p v-if="profileForm.errors.state" class="mt-1 text-xs font-semibold text-rose-600">{{ profileForm.errors.state }}</p>
                                        </div>

                                        <div class="md:col-span-2">
                                            <button
                                                type="submit"
                                                class="inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                                                style="background: var(--catalog-primary-strong)"
                                                :disabled="profileForm.processing"
                                            >
                                                <Save class="h-4 w-4" />
                                                {{ profileForm.processing ? 'Salvando...' : 'Salvar dados' }}
                                            </button>
                                        </div>
                                    </form>
                                </section>

                                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <div class="mb-4 flex items-center justify-between gap-3">
                                        <div class="inline-flex items-center gap-2">
                                            <Package class="h-4 w-4 text-slate-500" />
                                            <p class="text-sm font-semibold text-slate-900">Meus pedidos</p>
                                        </div>
                                        <span class="text-xs font-semibold text-slate-500">{{ accountOrders.length }} pedido(s)</span>
                                    </div>

                                    <div v-if="accountOrders.length" class="space-y-3">
                                        <article
                                            v-for="order in accountOrders"
                                            :key="`account-order-${order.id}`"
                                            class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3"
                                        >
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900">{{ order.code }}</p>
                                                    <p class="text-xs text-slate-500">{{ order.created_at }}</p>
                                                </div>
                                                <span class="inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-semibold" :class="order.status?.tone || 'bg-slate-100 text-slate-700'">
                                                    {{ order.status?.label || 'Pedido' }}
                                                </span>
                                            </div>
                                            <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
                                                <p>Total: <span class="font-semibold">{{ currency(order.total_amount) }}</span></p>
                                                <p>Pagamento: <span class="font-semibold">{{ order.payment_label || 'Não informado' }}</span></p>
                                                <p>Itens: <span class="font-semibold">{{ Array.isArray(order.items) ? order.items.length : 0 }}</span></p>
                                            </div>
                                        </article>
                                    </div>
                                    <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                        Você ainda não possui pedidos.
                                    </div>
                                </section>
                            </template>
                        </div>
                    </div>
                    <footer class="border-t border-slate-200 p-4">
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeAccountModal"
                        >
                            Fechar
                        </button>
                    </footer>
                </aside>
            </div>
        </transition>
        <transition name="cart-overlay">
            <div v-if="checkoutPaymentPanelOpen && isPixCheckoutPayment" class="fixed inset-0 z-[70]">
                <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[1px]" @click="closeCheckoutPaymentPanel"></div>
                <aside class="absolute inset-x-3 top-6 mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-2xl sm:inset-x-auto sm:right-6 sm:left-auto">
                    <header class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Pagamento Pix</p>
                            <p class="text-xs text-slate-500">Pedido {{ checkoutPayment.sale_code || `#${checkoutPayment.sale_id}` }}</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50"
                            @click="closeCheckoutPaymentPanel"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </header>

                    <div class="space-y-3 p-4">
                        <div class="rounded-xl border px-3 py-2 text-xs font-semibold" :class="checkoutPaymentStatusClass">
                            Status: {{ checkoutPayment.payment_status_label }}
                            <span v-if="checkoutPaymentPolling"> • atualizando automaticamente</span>
                        </div>

                        <div v-if="checkoutPaymentQrImageSrc" class="flex justify-center rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <img :src="checkoutPaymentQrImageSrc" alt="QR Code Pix" class="h-52 w-52 rounded-lg bg-white p-2" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-semibold text-slate-600">Código copia e cola</label>
                            <textarea
                                :value="checkoutPayment.qr_code"
                                rows="4"
                                readonly
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700"
                            />
                            <button
                                type="button"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                @click="copyCheckoutPixCode"
                            >
                                {{ checkoutPaymentCopied ? 'Código Pix copiado' : 'Copiar código Pix' }}
                            </button>
                        </div>

                        <a
                            v-if="checkoutPayment.ticket_url"
                            :href="checkoutPayment.ticket_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Abrir link de pagamento
                        </a>
                    </div>
                </aside>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.catalog-chip-scroll {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.catalog-chip-scroll::-webkit-scrollbar {
    display: none;
}
.menu-overlay-enter-active,
.menu-overlay-leave-active {
    transition: opacity 160ms ease;
}
.menu-overlay-enter-from,
.menu-overlay-leave-to {
    opacity: 0;
}
.cart-overlay-enter-active,
.cart-overlay-leave-active {
    transition: opacity 160ms ease;
}
.cart-overlay-enter-from,
.cart-overlay-leave-to {
    opacity: 0;
}
</style>
