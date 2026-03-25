<script setup>
/* eslint-disable vue/prop-name-casing */
import InputError from '@/Components/InputError.vue';
import { BRAZIL_STATES, formatCepBR, formatPhoneBR, normalizeStateCode } from '@/utils/br';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    ArrowLeft,
    Bell,
    ChevronRight,
    Heart,
    Home,
    LayoutGrid,
    LogIn,
    LogOut,
    Minus,
    Package,
    Plus,
    Search,
    ShoppingCart,
    Star,
    Trash2,
    UserRound,
} from 'lucide-vue-next';

const props = defineProps({
    mode: { type: String, default: 'commerce' },
    contractor: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    storefront: { type: Object, default: () => ({}) },
    store_availability: { type: Object, default: () => ({}) },
    payment_methods: { type: Array, default: () => [] },
    shipping_config: { type: Object, default: () => ({}) },
    shop_auth: {
        type: Object,
        default: () => ({
            authenticated: false,
            customer: null,
            email_verified: false,
            requires_email_verification: true,
            favorite_product_ids: [],
        }),
    },
    shop_account: { type: Object, default: () => ({ orders: [] }) },
    bookings: { type: Array, default: () => [] },
});

const page = usePage();
let cartToastTimeout = null;

const toInt = (value, fallback = 0) => {
    const parsed = Number.parseInt(String(value ?? ''), 10);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const toMoney = (value, fallback = 0) => {
    const parsed = Number.parseFloat(String(value ?? ''));
    return Number.isFinite(parsed) ? parsed : fallback;
};

const normalizeHex = (value, fallback = '#FF5C35') => {
    const raw = String(value ?? '').trim();
    if (/^#[0-9a-fA-F]{6}$/.test(raw)) return raw;
    if (/^[0-9a-fA-F]{6}$/.test(raw)) return `#${raw}`;
    return fallback;
};

const hexToRgb = (hex) => {
    const safe = normalizeHex(hex).replace('#', '');
    const value = Number.parseInt(safe, 16);
    return {
        r: (value >> 16) & 255,
        g: (value >> 8) & 255,
        b: value & 255,
    };
};

const withAlpha = (hex, alpha = 1) => {
    const { r, g, b } = hexToRgb(hex);
    const safe = Math.max(0, Math.min(1, alpha));
    return `rgba(${r}, ${g}, ${b}, ${safe})`;
};

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const formatMoney = (value) => currency.format(toMoney(value));

const isServicesMode = computed(() => String(props.mode ?? '').toLowerCase() === 'services');
const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const storeAccent = computed(() => normalizeHex(props.contractor?.primary_color, '#FF5C35'));
const storeLogo = computed(() => {
    const safe = String(props.contractor?.avatar_url || props.contractor?.logo_url || '').trim();
    return safe !== '' ? safe : null;
});
const storeInitials = computed(() => {
    const safe = String(storeName.value || '').trim();
    if (!safe) return 'LJ';
    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) || '';
    const last = parts.length > 1 ? parts[parts.length - 1]?.charAt(0) : '';
    return `${first}${last}`.toUpperCase() || 'LJ';
});

const themeVars = computed(() => ({
    '--idx-primary': storeAccent.value,
    '--idx-primary-dark': withAlpha(storeAccent.value, 0.92),
    '--idx-primary-soft': withAlpha(storeAccent.value, 0.16),
    '--idx-primary-border': withAlpha(storeAccent.value, 0.4),
}));

const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const registerUrl = computed(() => `/shop/${storeSlug.value}/cadastro`);
const verifyEmailUrl = computed(() => `/shop/${storeSlug.value}/verificar-email`);
const logoutUrl = computed(() => `/shop/${storeSlug.value}/sair`);
const checkoutUrl = computed(() => `/shop/${storeSlug.value}/checkout`);
const serviceBookUrl = computed(() => `/shop/${storeSlug.value}/servicos/agendar`);
const accountUpdateUrl = computed(() => `/shop/${storeSlug.value}/conta`);
const favoriteUrl = (id) => `/shop/${storeSlug.value}/favoritos/${id}`;

const isAuthenticated = computed(() => Boolean(props.shop_auth?.authenticated));
const requiresEmailVerification = computed(() => Boolean(props.shop_auth?.requires_email_verification ?? true));
const isEmailVerified = computed(() => Boolean(props.shop_auth?.email_verified ?? false));
const hasVerifiedAccess = computed(() => !requiresEmailVerification.value || isEmailVerified.value);
const isAddressComplete = computed(() => Boolean(props.shop_auth?.address_complete ?? false));
const customer = computed(() => props.shop_auth?.customer ?? null);

const flashStatus = computed(() => String(page.props?.flash?.status ?? '').trim());
const checkoutPayment = computed(() => page.props?.flash?.checkout_payment ?? null);
const checkoutManual = computed(() => page.props?.flash?.checkout_manual ?? null);
const bookingWhatsappUrl = computed(() => String(page.props?.flash?.service_booking_whatsapp_url ?? '').trim());

const fallbackImage = computed(() => (
    storeLogo.value || 'https://placehold.co/800x600/e5e7eb/334155?text=Loja'
));

const storefrontBlocks = computed(() => {
    const blocks = props.storefront?.blocks ?? {};
    return {
        hero: blocks.hero !== false,
        banners: blocks.banners !== false,
        promotions: blocks.promotions !== false,
        categories: blocks.categories !== false,
        catalog: blocks.catalog !== false,
    };
});

const storefrontHero = computed(() => {
    const hero = props.storefront?.hero ?? {};
    return {
        title: String(hero.title || '').trim() || (isServicesMode.value ? `Agende em ${storeName.value}` : `Compre em ${storeName.value}`),
        subtitle: String(hero.subtitle || '').trim() || (isServicesMode.value
            ? 'Atendimento rápido e seguro para você.'
            : 'Ofertas e produtos para montar seu pedido.'),
        ctaLabel: String(hero.cta_label || '').trim() || (isServicesMode.value ? 'Ver serviços' : 'Ver catálogo'),
    };
});

const storefrontPromotions = computed(() => {
    const promotions = props.storefront?.promotions ?? {};
    return {
        title: String(promotions.title || '').trim() || (isServicesMode.value ? 'Destaques' : 'Promoções'),
        subtitle: String(promotions.subtitle || '').trim(),
    };
});

const normalizedCatalog = computed(() => {
    if (isServicesMode.value) {
        const safe = Array.isArray(props.services) ? props.services : [];
        return safe
            .map((service) => ({
                id: toInt(service?.id, 0),
                categoryId: toInt(service?.service_category_id, 0),
                title: String(service?.name || 'Serviço'),
                subtitle: String(service?.category_name || service?.code || 'Atendimento'),
                description: String(service?.description || 'Sem descrição informada.'),
                price: toMoney(service?.base_price, 0),
                image: String(service?.image_url || '').trim() || fallbackImage.value,
                badge: String(service?.coupon_label || 'Destaque'),
                rating: toMoney(service?.rating, 5),
                reviews: 200 + (toInt(service?.id, 1) % 7) * 48,
                durationLabel: String(service?.duration_label || '60 min'),
            }))
            .filter((item) => item.id > 0);
    }

    const safe = Array.isArray(props.products) ? props.products : [];
    return safe
        .map((product) => {
            const id = toInt(product?.id, 0);
            const firstImage = Array.isArray(product?.images)
                ? product.images.find((row) => String(row?.image_url || '').trim() !== '')
                : null;

            return {
                id,
                categoryId: toInt(product?.category_id, toInt(product?.category_parent_id, 0)),
                title: String(product?.name || 'Produto'),
                subtitle: String(product?.category_name || product?.sku || 'Produto'),
                description: String(product?.description || 'Sem descrição informada.'),
                price: toMoney(product?.sale_price, 0),
                image: String(firstImage?.image_url || product?.image_url || '').trim() || fallbackImage.value,
                badge: product?.has_variations ? 'Variações' : 'Destaque',
                rating: 4.5 + ((id % 4) * 0.1),
                reviews: 180 + (id % 9) * 62,
                stock: toInt(product?.stock_quantity, 0),
                variations: Array.isArray(product?.variations)
                    ? product.variations
                        .map((variation) => ({
                            id: toInt(variation?.id, 0),
                            name: String(variation?.name || 'Variação'),
                            price: toMoney(variation?.sale_price, toMoney(product?.sale_price, 0)),
                            stock: toInt(variation?.stock_quantity, 0),
                        }))
                        .filter((variation) => variation.id > 0 && variation.stock > 0)
                    : [],
            };
        })
        .filter((item) => item.id > 0 && item.stock > 0);
});

const categoryOptions = computed(() => {
    const safe = Array.isArray(props.categories) ? props.categories : [];
    const normalized = safe
        .map((category) => ({ id: toInt(category?.id, 0), label: String(category?.name || 'Categoria') }))
        .filter((category) => category.id > 0);

    return [{ id: 'all', label: 'Todas' }, ...normalized];
});

const activeTab = ref('home');
const search = ref('');
const activeCategory = ref('all');
const isCartOpen = ref(false);
const selectedId = ref(null);
const variationByProduct = ref({});
const uiMessage = ref('');
const cartToast = ref({ visible: false, title: '', description: '' });

watch(normalizedCatalog, (items) => {
    if (!items.length) {
        selectedId.value = null;
        return;
    }

    if (!items.some((item) => item.id === selectedId.value)) {
        selectedId.value = items[0].id;
    }
}, { immediate: true });

const selectedItem = computed(() => (
    normalizedCatalog.value.find((item) => item.id === selectedId.value) ?? null
));

const configuredBanners = computed(() => {
    if (!storefrontBlocks.value.banners) return [];

    const raw = Array.isArray(props.storefront?.banners) ? props.storefront.banners : [];
    return raw
        .map((banner, index) => ({
            id: `cfg-${index + 1}`,
            title: String(banner?.title || '').trim() || (isServicesMode.value ? 'Serviço em destaque' : 'Oferta especial'),
            subtitle: String(banner?.subtitle || '').trim(),
            badge: String(banner?.badge || '').trim(),
            image: String(banner?.image_url || '').trim() || fallbackImage.value,
            ctaLabel: String(banner?.cta_label || '').trim() || 'Ver mais',
            ctaUrl: String(banner?.cta_url || '').trim(),
            backgroundColor: normalizeHex(String(banner?.background_color || ''), storeAccent.value),
        }))
        .slice(0, 6);
});

const filteredCatalog = computed(() => {
    const term = String(search.value || '').trim().toLowerCase();

    return normalizedCatalog.value.filter((item) => {
        const categoryMatch = activeCategory.value === 'all' || toInt(activeCategory.value, 0) === item.categoryId;
        const searchPool = `${item.title} ${item.subtitle} ${item.description}`.toLowerCase();
        const searchMatch = term === '' || searchPool.includes(term);
        return categoryMatch && searchMatch;
    });
});

const featuredCatalog = computed(() => filteredCatalog.value.slice(0, 8));

const fallbackBanners = computed(() => featuredCatalog.value.slice(0, 6).map((item, index) => ({
    id: `fallback-${item.id}`,
    title: item.title,
    subtitle: item.subtitle,
    badge: index === 0 ? 'Destaque' : '',
    image: item.image,
    ctaLabel: isServicesMode.value ? 'Agendar' : 'Comprar',
    ctaUrl: '',
    backgroundColor: storeAccent.value,
})));

const promotionalBanners = computed(() => {
    if (!storefrontBlocks.value.banners) return [];
    if (configuredBanners.value.length > 0) return configuredBanners.value;
    return fallbackBanners.value;
});

const desktopBanners = computed(() => promotionalBanners.value.slice(0, 3));

const favoriteIds = ref([]);
const serviceFavoritesKey = computed(() => `veshop:index6:favorites:${storeSlug.value}`);

const syncFavorites = () => {
    if (!isServicesMode.value) {
        favoriteIds.value = Array.from(new Set(
            (Array.isArray(props.shop_auth?.favorite_product_ids) ? props.shop_auth.favorite_product_ids : [])
                .map((id) => toInt(id, 0))
                .filter((id) => id > 0),
        ));
        return;
    }

    try {
        const raw = localStorage.getItem(serviceFavoritesKey.value);
        const parsed = raw ? JSON.parse(raw) : [];
        favoriteIds.value = Array.isArray(parsed)
            ? parsed.map((id) => toInt(id, 0)).filter((id) => id > 0)
            : [];
    } catch {
        favoriteIds.value = [];
    }
};

onMounted(() => {
    syncFavorites();
});

watch(() => props.shop_auth?.favorite_product_ids, () => {
    if (!isServicesMode.value) syncFavorites();
}, { deep: true });

watch(favoriteIds, (ids) => {
    if (!isServicesMode.value) return;
    try {
        localStorage.setItem(serviceFavoritesKey.value, JSON.stringify(ids));
    } catch {
        // ignore
    }
}, { deep: true });

const isFavorite = (id) => favoriteIds.value.includes(id);
const toggleFavorite = (item) => {
    if (!item) return;
    const id = toInt(item.id, 0);
    if (id <= 0) return;

    const already = isFavorite(id);

    if (isServicesMode.value) {
        favoriteIds.value = already
            ? favoriteIds.value.filter((row) => row !== id)
            : [...favoriteIds.value, id];
        return;
    }

    if (!isAuthenticated.value) {
        router.visit(loginUrl.value);
        return;
    }

    if (already) {
        favoriteIds.value = favoriteIds.value.filter((row) => row !== id);
        router.delete(favoriteUrl(id), {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                favoriteIds.value = [...favoriteIds.value, id];
            },
        });
        return;
    }

    favoriteIds.value = [...favoriteIds.value, id];
    router.post(favoriteUrl(id), {}, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            favoriteIds.value = favoriteIds.value.filter((row) => row !== id);
        },
    });
};

const favoriteItems = computed(() => normalizedCatalog.value.filter((item) => isFavorite(item.id)));

const cartKey = computed(() => `veshop:index6:cart:${storeSlug.value}:${isServicesMode.value ? 'services' : 'commerce'}`);
const cart = ref({});

const hydrateCart = () => {
    try {
        const raw = localStorage.getItem(cartKey.value);
        const parsed = raw ? JSON.parse(raw) : {};

        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            cart.value = {};
            return;
        }

        const next = {};
        Object.entries(parsed).forEach(([rawId, row]) => {
            const id = toInt(rawId, 0);
            const qty = Math.max(1, toInt(row?.quantity, 1));
            const variationId = row?.variation_id ? toInt(row.variation_id, 0) : null;
            if (id > 0) {
                next[id] = {
                    quantity: isServicesMode.value ? 1 : qty,
                    variation_id: variationId && variationId > 0 ? variationId : null,
                };
            }
        });

        cart.value = next;
    } catch {
        cart.value = {};
    }
};

const persistCart = () => {
    try {
        localStorage.setItem(cartKey.value, JSON.stringify(cart.value));
    } catch {
        // ignore
    }
};

onMounted(() => {
    hydrateCart();
});

watch(cartKey, () => {
    hydrateCart();
});

watch(cart, () => {
    persistCart();
}, { deep: true });

const resolveVariationPrice = (item, variationId) => {
    if (!variationId || !Array.isArray(item?.variations)) return toMoney(item?.price, 0);
    const variation = item.variations.find((row) => row.id === variationId);
    return variation ? toMoney(variation.price, toMoney(item?.price, 0)) : toMoney(item?.price, 0);
};

const addToCart = (item, qty = 1) => {
    if (!item) return;

    const id = toInt(item.id, 0);
    if (id <= 0) return;

    if (isServicesMode.value) {
        cart.value = {
            [id]: {
                quantity: 1,
                variation_id: null,
            },
        };
        cartToast.value = {
            visible: true,
            title: 'Serviço selecionado',
            description: `${item.title} pronto para agendamento.`,
        };
        if (cartToastTimeout) clearTimeout(cartToastTimeout);
        cartToastTimeout = setTimeout(() => {
            cartToast.value.visible = false;
        }, 2600);
        isCartOpen.value = true;
        return;
    }

    const variationId = variationByProduct.value[id] ? toInt(variationByProduct.value[id], 0) : null;
    const current = cart.value[id] ?? { quantity: 0, variation_id: null };

    cart.value = {
        ...cart.value,
        [id]: {
            quantity: Math.max(1, toInt(current.quantity, 0) + Math.max(1, toInt(qty, 1))),
            variation_id: variationId && variationId > 0 ? variationId : current.variation_id,
        },
    };

    cartToast.value = {
        visible: true,
        title: 'Produto adicionado',
        description: `${item.title} foi para o carrinho.`,
    };
    if (cartToastTimeout) clearTimeout(cartToastTimeout);
    cartToastTimeout = setTimeout(() => {
        cartToast.value.visible = false;
    }, 2600);
};

const increase = (id) => {
    if (isServicesMode.value) return;
    if (!cart.value[id]) return;

    cart.value = {
        ...cart.value,
        [id]: {
            ...cart.value[id],
            quantity: toInt(cart.value[id].quantity, 0) + 1,
        },
    };
};

const decrease = (id) => {
    if (!cart.value[id]) return;

    const qty = toInt(cart.value[id].quantity, 0);
    if (qty <= 1) {
        const next = { ...cart.value };
        delete next[id];
        cart.value = next;
        return;
    }

    cart.value = {
        ...cart.value,
        [id]: {
            ...cart.value[id],
            quantity: qty - 1,
        },
    };
};

const clearCart = () => {
    cart.value = {};
};

const cartEntries = computed(() => Object.entries(cart.value)
    .map(([rawId, row]) => {
        const id = toInt(rawId, 0);
        const item = normalizedCatalog.value.find((entry) => entry.id === id);
        if (!item) return null;

        const quantityValue = isServicesMode.value ? 1 : Math.max(1, toInt(row?.quantity, 1));
        const variationId = row?.variation_id ? toInt(row.variation_id, 0) : null;
        const unitPrice = resolveVariationPrice(item, variationId);

        return {
            ...item,
            quantity: quantityValue,
            variationId,
            unitPrice,
            lineTotal: unitPrice * quantityValue,
        };
    })
    .filter(Boolean));

const cartCount = computed(() => cartEntries.value.reduce((sum, entry) => sum + entry.quantity, 0));
const subtotal = computed(() => cartEntries.value.reduce((sum, entry) => sum + entry.lineTotal, 0));

const paymentMethods = computed(() => (
    Array.isArray(props.payment_methods)
        ? props.payment_methods
            .map((method) => ({
                id: toInt(method?.id, 0),
                name: String(method?.name || 'Pagamento'),
                feeFixed: toMoney(method?.fee_fixed, 0),
                feePercent: toMoney(method?.fee_percent, 0),
            }))
            .filter((method) => method.id > 0)
        : []
));

const firstPaymentMethod = computed(() => paymentMethods.value[0] ?? null);

const shippingConfig = computed(() => ({
    deliveryEnabled: Boolean(props.shipping_config?.delivery_enabled ?? true),
    pickupEnabled: Boolean(props.shipping_config?.pickup_enabled ?? true),
    fixedFee: Math.max(0, toMoney(props.shipping_config?.fixed_fee, 0)),
    freeOver: Math.max(0, toMoney(props.shipping_config?.free_over, 0)),
}));

const deliveryFee = computed(() => {
    if (isServicesMode.value) return 0;
    if (!shippingConfig.value.deliveryEnabled) return 0;
    if (shippingConfig.value.freeOver > 0 && subtotal.value >= shippingConfig.value.freeOver) return 0;
    return shippingConfig.value.fixedFee;
});

const paymentFee = computed(() => {
    if (!firstPaymentMethod.value) return 0;
    const fee = subtotal.value * (firstPaymentMethod.value.feePercent / 100) + firstPaymentMethod.value.feeFixed;
    return Number(fee.toFixed(2));
});

const total = computed(() => Number((subtotal.value + deliveryFee.value + paymentFee.value).toFixed(2)));

const checkoutForm = useForm({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    notes: '',
    payment_method_id: null,
    delivery_mode: 'delivery',
    shipping_postal_code: '',
    shipping_street: '',
    shipping_number: '',
    shipping_complement: '',
    shipping_district: '',
    shipping_city: '',
    shipping_state: '',
    items: [],
    idempotency_key: '',
});

const syncCheckoutData = () => {
    checkoutForm.customer_name = String(customer.value?.name || '');
    checkoutForm.customer_phone = formatPhoneBR(String(customer.value?.phone || ''));
    checkoutForm.customer_email = String(customer.value?.email || '');
    checkoutForm.shipping_postal_code = formatCepBR(String(customer.value?.cep || ''));
    checkoutForm.shipping_street = String(customer.value?.street || '');
    checkoutForm.shipping_number = String(customer.value?.number || '');
    checkoutForm.shipping_complement = String(customer.value?.complement || '');
    checkoutForm.shipping_district = String(customer.value?.neighborhood || '');
    checkoutForm.shipping_city = String(customer.value?.city || '');
    checkoutForm.shipping_state = normalizeStateCode(String(customer.value?.state || ''));
};

watch(customer, () => syncCheckoutData(), { immediate: true, deep: true });

watch(firstPaymentMethod, (method) => {
    checkoutForm.payment_method_id = method?.id ?? null;
}, { immediate: true });

const submitQuickCheckout = () => {
    uiMessage.value = '';

    if (!isAuthenticated.value) {
        router.visit(loginUrl.value);
        return;
    }

    if (!hasVerifiedAccess.value) {
        router.visit(verifyEmailUrl.value);
        return;
    }

    if (!isAddressComplete.value) {
        uiMessage.value = 'Complete seu endereço na aba Conta para finalizar o pedido.';
        activeTab.value = 'account';
        isCartOpen.value = false;
        return;
    }

    if (!cartEntries.value.length) {
        uiMessage.value = 'Adicione itens no carrinho.';
        return;
    }

    checkoutForm.clearErrors();
    checkoutForm.customer_phone = formatPhoneBR(checkoutForm.customer_phone);
    checkoutForm.shipping_postal_code = formatCepBR(checkoutForm.shipping_postal_code);
    checkoutForm.shipping_state = normalizeStateCode(checkoutForm.shipping_state);
    checkoutForm.delivery_mode = shippingConfig.value.deliveryEnabled ? 'delivery' : 'pickup';
    checkoutForm.idempotency_key = `idx6-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    checkoutForm.items = cartEntries.value.map((entry) => ({
        product_id: entry.id,
        variation_id: entry.variationId,
        quantity: entry.quantity,
    }));

    checkoutForm.post(checkoutUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            clearCart();
            isCartOpen.value = false;
            activeTab.value = 'orders';
        },
    });
};
const bookingForm = useForm({
    service_catalog_id: null,
    scheduled_for: '',
    notes: '',
});

const nextTwoHours = () => {
    const now = new Date();
    now.setHours(now.getHours() + 2);
    now.setMinutes(0, 0, 0);
    const tzOffset = now.getTimezoneOffset() * 60000;
    return new Date(now.getTime() - tzOffset).toISOString().slice(0, 16);
};

bookingForm.scheduled_for = nextTwoHours();

const submitQuickBooking = () => {
    uiMessage.value = '';

    if (!isAuthenticated.value) {
        router.visit(loginUrl.value);
        return;
    }

    if (!hasVerifiedAccess.value) {
        router.visit(verifyEmailUrl.value);
        return;
    }

    const target = cartEntries.value[0] ?? selectedItem.value;
    if (!target) {
        uiMessage.value = 'Selecione um serviço antes de agendar.';
        return;
    }

    bookingForm.clearErrors();
    bookingForm.service_catalog_id = target.id;

    if (!bookingForm.scheduled_for) {
        bookingForm.scheduled_for = nextTwoHours();
    }

    bookingForm.post(serviceBookUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            clearCart();
            isCartOpen.value = false;
            activeTab.value = 'orders';
        },
    });
};

const profileForm = useForm({
    phone: '',
    cep: '',
    street: '',
    number: '',
    complement: '',
    neighborhood: '',
    city: '',
    state: '',
});

const syncProfile = () => {
    profileForm.phone = formatPhoneBR(String(customer.value?.phone || ''));
    profileForm.cep = formatCepBR(String(customer.value?.cep || ''));
    profileForm.street = String(customer.value?.street || '');
    profileForm.number = String(customer.value?.number || '');
    profileForm.complement = String(customer.value?.complement || '');
    profileForm.neighborhood = String(customer.value?.neighborhood || '');
    profileForm.city = String(customer.value?.city || '');
    profileForm.state = normalizeStateCode(String(customer.value?.state || ''));
};

watch(customer, () => syncProfile(), { immediate: true, deep: true });

const submitProfile = () => {
    if (!isAuthenticated.value) {
        router.visit(loginUrl.value);
        return;
    }

    profileForm.phone = formatPhoneBR(profileForm.phone);
    profileForm.cep = formatCepBR(profileForm.cep);
    profileForm.state = normalizeStateCode(profileForm.state);

    profileForm.patch(accountUpdateUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            uiMessage.value = 'Dados da conta atualizados.';
        },
    });
};

const logoutForm = useForm({});
const logout = () => {
    logoutForm.post(logoutUrl.value);
};

const orders = computed(() => {
    if (isServicesMode.value) {
        return (Array.isArray(props.bookings) ? props.bookings : []).map((booking) => ({
            id: toInt(booking?.id, 0),
            code: String(booking?.code || ''),
            status: String(booking?.status?.label || 'Agendado'),
            date: String(booking?.scheduled_label || ''),
            total: toMoney(booking?.final_amount || booking?.estimated_amount, 0),
            details: String(booking?.service_name || 'Serviço'),
        }));
    }

    return (Array.isArray(props.shop_account?.orders) ? props.shop_account.orders : []).map((order) => ({
        id: toInt(order?.id, 0),
        code: String(order?.code || ''),
        status: String(order?.status?.label || 'Pedido'),
        date: String(order?.created_at || ''),
        total: toMoney(order?.total_amount, 0),
        details: String(order?.payment_label || 'Pagamento'),
    }));
});

const stateOptions = computed(() => ([
    { value: '', label: 'Selecione a UF' },
    ...BRAZIL_STATES.map((state) => ({ value: state.code, label: `${state.code} - ${state.name}` })),
]));

const storeAvailability = computed(() => {
    const raw = props.store_availability ?? {};
    return {
        status: String(raw.status_label ?? '').trim(),
        message: String(raw.message ?? '').trim(),
        open: Boolean(raw.is_open_now ?? true),
    };
});

const openDetails = (item) => {
    if (!item) return;
    selectedId.value = item.id;
};

const applyHeroCta = () => {
    search.value = '';
    activeCategory.value = 'all';
    const first = filteredCatalog.value[0] ?? null;
    if (first) selectedId.value = first.id;
};

const resolveInitialScreen = () => {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    const tab = String(params.get('tab') || '').trim().toLowerCase();
    const allowedTabs = new Set(['home', 'favorites', 'orders', 'account']);

    if (allowedTabs.has(tab)) {
        activeTab.value = tab;
    } else if (params.has('conta') || params.has('account')) {
        activeTab.value = 'account';
    } else if (params.has('favoritos') || params.has('favorites')) {
        activeTab.value = 'favorites';
    } else if (params.has('pedidos') || params.has('orders')) {
        activeTab.value = 'orders';
    }

    const productId = toInt(params.get('produto'), 0);
    if (productId > 0 && normalizedCatalog.value.some((item) => item.id === productId)) {
        selectedId.value = productId;
    }
};

onMounted(() => {
    resolveInitialScreen();
});

onBeforeUnmount(() => {
    if (cartToastTimeout) {
        clearTimeout(cartToastTimeout);
        cartToastTimeout = null;
    }
});

</script>
<template>
    <div class="idx-root" :style="themeVars">
        <div class="h-screen w-full relative flex overflow-hidden">
            <div class="w-full h-full flex">
                <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col h-full z-10">
                    <div class="p-6 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center">
                            <img v-if="storeLogo" :src="storeLogo" :alt="storeName" class="w-full h-full object-cover">
                            <span v-else class="text-xs font-bold text-gray-700">{{ storeInitials }}</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm">{{ isAuthenticated ? (customer?.name || storeName) : storeName }}</h3>
                            <p class="text-xs text-gray-400">{{ storeAvailability.status || (storeAvailability.open ? 'Loja aberta' : 'Loja fechada') }}</p>
                        </div>
                    </div>

                    <nav class="flex-1 px-4 space-y-2 overflow-y-auto">
                        <button
                            type="button"
                            class="w-full flex items-center justify-between px-4 py-3 rounded-xl shadow-sm transition"
                            :class="activeTab === 'home' ? 'bg-[var(--idx-primary)] text-white' : 'text-gray-500 hover:bg-gray-50'"
                            @click="activeTab = 'home'"
                        >
                            <span class="flex items-center gap-3"><LayoutGrid :size="16" /> Catálogo</span>
                            <ChevronRight :size="14" />
                        </button>
                        <button
                            type="button"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition"
                            :class="activeTab === 'favorites' ? 'bg-[var(--idx-primary)]/10 text-[var(--idx-primary)]' : 'text-gray-500 hover:bg-gray-50'"
                            @click="activeTab = 'favorites'"
                        >
                            <Heart :size="16" /> Favoritos
                        </button>
                        <button
                            type="button"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition"
                            :class="activeTab === 'orders' ? 'bg-[var(--idx-primary)]/10 text-[var(--idx-primary)]' : 'text-gray-500 hover:bg-gray-50'"
                            @click="activeTab = 'orders'"
                        >
                            <Package :size="16" /> {{ isServicesMode ? 'Agendamentos' : 'Pedidos' }}
                        </button>
                        <button
                            type="button"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition"
                            :class="activeTab === 'account' ? 'bg-[var(--idx-primary)]/10 text-[var(--idx-primary)]' : 'text-gray-500 hover:bg-gray-50'"
                            @click="activeTab = 'account'"
                        >
                            <UserRound :size="16" /> Conta
                        </button>
                    </nav>

                    <div class="p-4 border-t border-gray-100">
                        <Link v-if="!isAuthenticated" :href="loginUrl" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                            <LogIn :size="16" /> Entrar
                        </Link>
                        <button v-else type="button" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50" @click="logout">
                            <LogOut :size="16" /> Sair
                        </button>
                    </div>
                </aside>

                <main class="flex-1 flex flex-col h-full bg-[#F8F9FA] overflow-hidden">
                    <header class="bg-white px-4 md:px-6 py-4 flex items-center justify-between border-b border-gray-100 z-10">
                        <div class="flex items-center gap-3 w-full max-w-xl">
                            <button v-if="activeTab !== 'home'" type="button" class="text-gray-400 md:hidden" @click="activeTab = 'home'">
                                <ArrowLeft :size="18" />
                            </button>
                            <h2 class="text-lg font-bold md:hidden">{{ activeTab === 'home' ? 'Catálogo' : activeTab === 'favorites' ? 'Favoritos' : activeTab === 'orders' ? (isServicesMode ? 'Agendamentos' : 'Pedidos') : 'Conta' }}</h2>
                            <div class="hidden md:flex items-center bg-gray-100 rounded-full px-4 py-2 w-full">
                                <Search :size="15" class="text-gray-400 mr-2" />
                                <input v-model="search" type="text" placeholder="Buscar" class="bg-transparent border-none focus:outline-none w-full text-sm">
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" class="p-2 text-gray-500 hover:text-[var(--idx-primary)] transition-colors">
                                <Bell :size="19" />
                            </button>
                            <button type="button" class="relative p-2 text-gray-600 hover:text-[var(--idx-primary)] transition-colors" @click="isCartOpen = true">
                                <ShoppingCart :size="20" />
                                <span class="absolute top-0 right-0 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center">{{ cartCount }}</span>
                            </button>
                        </div>
                    </header>

                    <div class="flex-1 overflow-y-auto p-4 md:p-6 pb-24 md:pb-6">
                        <div v-if="flashStatus" class="mb-4 rounded-xl border border-green-200 bg-green-50 text-green-700 text-sm px-4 py-2.5">
                            {{ flashStatus }}
                        </div>
                        <div v-if="uiMessage" class="mb-4 rounded-xl border border-sky-200 bg-sky-50 text-sky-700 text-sm px-4 py-2.5">
                            {{ uiMessage }}
                        </div>
                        <div v-if="checkoutManual?.whatsapp_url" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 text-sm px-4 py-2.5">
                            Pagamento manual disponível. <a :href="checkoutManual.whatsapp_url" target="_blank" class="font-semibold underline">Abrir WhatsApp</a>
                        </div>
                        <div v-if="checkoutPayment?.is_pix && checkoutPayment?.qr_code" class="mb-4 rounded-xl border border-orange-200 bg-orange-50 text-orange-800 text-sm px-4 py-2.5">
                            Pix gerado: <span class="font-semibold break-all">{{ checkoutPayment.qr_code }}</span>
                        </div>
                        <div v-if="bookingWhatsappUrl" class="mb-4 rounded-xl border border-green-200 bg-green-50 text-green-700 text-sm px-4 py-2.5">
                            Agendamento criado. <a :href="bookingWhatsappUrl" target="_blank" class="font-semibold underline">Abrir WhatsApp</a>
                        </div>

                        <div v-if="activeTab === 'home' && storefrontBlocks.hero" class="mb-4">
                            <h2 class="text-2xl font-black text-slate-900">{{ storefrontHero.title }}</h2>
                            <p v-if="storefrontHero.subtitle" class="mt-1 text-sm text-slate-500">{{ storefrontHero.subtitle }}</p>
                            <button
                                v-if="storefrontHero.ctaLabel"
                                type="button"
                                class="mt-3 inline-flex items-center rounded-full bg-[var(--idx-primary)] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:opacity-95"
                                @click="applyHeroCta"
                            >
                                {{ storefrontHero.ctaLabel }}
                            </button>
                        </div>

                        <template v-if="activeTab === 'home' && promotionalBanners.length">
                            <div class="md:hidden -mx-4 px-4 mb-6 overflow-x-auto">
                                <div class="flex w-max gap-3">
                                    <article
                                        v-for="banner in promotionalBanners"
                                        :key="`mobile-banner-${banner.id}`"
                                        class="relative h-40 w-[280px] overflow-hidden rounded-2xl border border-white/20 shadow-sm"
                                    >
                                        <img :src="banner.image" :alt="banner.title" class="h-full w-full object-cover">
                                        <div class="absolute inset-0 bg-gradient-to-tr from-black/60 via-black/25 to-black/15 p-4 text-white">
                                            <span v-if="banner.badge" class="inline-flex rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
                                                {{ banner.badge }}
                                            </span>
                                            <h3 class="mt-2 text-base font-extrabold leading-tight">{{ banner.title }}</h3>
                                            <p v-if="banner.subtitle" class="mt-1 line-clamp-2 text-xs text-white/90">{{ banner.subtitle }}</p>
                                            <a
                                                v-if="banner.ctaUrl"
                                                :href="banner.ctaUrl"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mt-3 inline-flex rounded-full bg-white/20 px-3 py-1 text-[11px] font-semibold text-white"
                                            >
                                                {{ banner.ctaLabel || 'Ver mais' }}
                                            </a>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <div class="hidden md:grid grid-cols-3 gap-6 mb-8">
                                <article
                                    v-for="(banner, index) in desktopBanners"
                                    :key="`desktop-banner-${banner.id}`"
                                    class="relative overflow-hidden rounded-2xl shadow-sm"
                                    :class="index === 0 ? 'col-span-2 h-48' : 'h-48'"
                                >
                                    <img :src="banner.image" :alt="banner.title" class="h-full w-full object-cover">
                                    <div class="absolute inset-0 p-5 text-white" :style="{ background: `linear-gradient(130deg, ${withAlpha(banner.backgroundColor, 0.84)} 0%, rgba(15, 23, 42, 0.58) 65%)` }">
                                        <p class="text-xs uppercase tracking-[0.16em]">{{ storeName }}</p>
                                        <h3 class="mt-2 text-2xl font-black leading-tight">{{ banner.title }}</h3>
                                        <p v-if="banner.subtitle" class="mt-1 max-w-md text-sm text-white/90">{{ banner.subtitle }}</p>
                                        <a
                                            v-if="banner.ctaUrl"
                                            :href="banner.ctaUrl"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-4 inline-flex rounded-full bg-white/20 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur"
                                        >
                                            {{ banner.ctaLabel || 'Ver mais' }}
                                        </a>
                                    </div>
                                </article>
                            </div>
                        </template>

                        <template v-if="activeTab === 'home' || activeTab === 'favorites'">
                            <div class="flex items-center justify-between mb-4 mt-2">
                                <div>
                                    <h3 class="text-lg font-bold">{{ activeTab === 'favorites' ? 'Favoritos' : (storefrontPromotions.title || 'Categorias') }}</h3>
                                    <p v-if="activeTab === 'home' && storefrontPromotions.subtitle" class="text-xs text-slate-500">{{ storefrontPromotions.subtitle }}</p>
                                </div>
                                <button class="bg-orange-100 text-[var(--idx-primary)] px-3 py-1 rounded-lg text-sm font-medium" @click="activeCategory = 'all'">
                                    Limpar filtro
                                </button>
                            </div>

                            <div class="flex items-center gap-2 mb-4 overflow-x-auto">
                                <button
                                    v-for="category in categoryOptions"
                                    :key="category.id"
                                    type="button"
                                    class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap border"
                                    :class="String(activeCategory) === String(category.id)
                                        ? 'bg-[var(--idx-primary)] text-white border-[var(--idx-primary)]'
                                        : 'bg-white text-gray-500 border-gray-200'"
                                    @click="activeCategory = category.id"
                                >
                                    {{ category.label }}
                                </button>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
                                <div
                                    v-for="item in (activeTab === 'favorites' ? favoriteItems : featuredCatalog)"
                                    :key="`card-${item.id}`"
                                    class="bg-white rounded-2xl overflow-hidden shadow-sm relative group"
                                >
                                    <button
                                        type="button"
                                        class="absolute top-2 right-2 bg-red-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold z-10"
                                        @click="toggleFavorite(item)"
                                    >
                                        <Heart :size="12" :class="{ 'fill-white': isFavorite(item.id) }" />
                                    </button>
                                    <img :src="item.image" :alt="item.title" class="w-full h-32 md:h-40 object-cover group-hover:scale-105 transition-transform duration-300" @click="openDetails(item)">
                                    <div class="p-4">
                                        <h4 class="font-semibold text-sm md:text-base mb-1 truncate">{{ item.title }}</h4>
                                        <p class="text-xs text-gray-400 truncate">{{ item.subtitle }}</p>
                                        <div class="flex items-center gap-1 mt-1 text-xs text-amber-500">
                                            <Star :size="12" />
                                            <span class="font-semibold">{{ item.rating.toFixed(1) }}</span>
                                            <span class="text-gray-400">({{ item.reviews }})</span>
                                        </div>
                                        <div class="flex justify-between items-center mt-2">
                                            <span class="text-gray-700 text-xs font-semibold">{{ formatMoney(item.price) }}</span>
                                            <button class="bg-[#F59E0B] text-white text-xs px-3 py-1 rounded-full font-medium" @click="addToCart(item)">
                                                {{ isServicesMode ? 'Agendar' : 'Adicionar' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <template v-if="activeTab === 'home'">
                                <h3 class="text-lg font-bold mt-8 mb-4">Favoritos</h3>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
                                    <div
                                        v-for="item in favoriteItems.slice(0, 4)"
                                        :key="`fav-home-${item.id}`"
                                        class="bg-white rounded-2xl overflow-hidden shadow-sm relative group"
                                    >
                                        <img :src="item.image" :alt="item.title" class="w-full h-32 md:h-40 object-cover group-hover:scale-105 transition-transform duration-300" @click="openDetails(item)">
                                        <div class="p-4">
                                            <h4 class="font-semibold text-sm md:text-base mb-1 truncate">{{ item.title }}</h4>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>

                        <template v-else-if="activeTab === 'orders'">
                            <h3 class="text-lg font-bold mb-4">{{ isServicesMode ? 'Meus agendamentos' : 'Meus pedidos' }}</h3>
                            <div class="space-y-3">
                                <article v-for="order in orders" :key="`order-${order.code}-${order.id}`" class="bg-white rounded-2xl border border-gray-100 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <strong class="text-sm text-gray-800">{{ order.code || `#${order.id}` }}</strong>
                                        <span class="text-xs px-2.5 py-1 rounded-full bg-orange-100 text-orange-700 font-semibold">{{ order.status }}</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">{{ order.date }}</p>
                                    <div class="mt-2 flex items-center justify-between text-sm">
                                        <span class="text-gray-500">{{ order.details }}</span>
                                        <strong class="text-gray-800">{{ formatMoney(order.total) }}</strong>
                                    </div>
                                </article>
                            </div>
                            <div v-if="!orders.length" class="bg-white rounded-2xl border border-dashed border-gray-300 text-gray-500 text-sm p-6 text-center">
                                Nenhum {{ isServicesMode ? 'agendamento' : 'pedido' }} encontrado.
                            </div>
                        </template>

                        <template v-else>
                            <h3 class="text-lg font-bold mb-4">Minha conta</h3>
                            <div v-if="!isAuthenticated" class="bg-white rounded-2xl border border-gray-200 p-5 text-sm text-gray-600 space-y-3">
                                <p>Faça login para gerenciar seus dados e finalizar pedidos.</p>
                                <div class="flex gap-2">
                                    <Link :href="loginUrl" class="px-4 py-2 rounded-xl bg-[var(--idx-primary)] text-white font-semibold">Entrar</Link>
                                    <Link :href="registerUrl" class="px-4 py-2 rounded-xl border border-gray-200 font-semibold text-gray-700">Cadastrar</Link>
                                </div>
                            </div>

                            <form v-else class="space-y-4" @submit.prevent="submitProfile">
                                <div class="bg-white rounded-2xl border border-gray-100 p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                        Telefone
                                        <input :value="profileForm.phone" type="text" maxlength="15" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @input="profileForm.phone = formatPhoneBR($event.target.value)">
                                    </label>
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                        CEP
                                        <input :value="profileForm.cep" type="text" maxlength="9" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @input="profileForm.cep = formatCepBR($event.target.value)">
                                    </label>
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide md:col-span-2">
                                        Logradouro
                                        <input v-model="profileForm.street" type="text" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                        Número
                                        <input v-model="profileForm.number" type="text" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                        Complemento
                                        <input v-model="profileForm.complement" type="text" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                        Bairro
                                        <input v-model="profileForm.neighborhood" type="text" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                        Cidade
                                        <input v-model="profileForm.city" type="text" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                        UF
                                        <select v-model="profileForm.state" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm bg-white">
                                            <option v-for="option in stateOptions" :key="option.value || 'empty'" :value="option.value">{{ option.label }}</option>
                                        </select>
                                    </label>
                                </div>

                                <InputError :message="profileForm.errors.phone" />
                                <InputError :message="profileForm.errors.cep" />
                                <InputError :message="profileForm.errors.street" />
                                <InputError :message="profileForm.errors.number" />
                                <InputError :message="profileForm.errors.neighborhood" />
                                <InputError :message="profileForm.errors.city" />
                                <InputError :message="profileForm.errors.state" />

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-[var(--idx-primary)] text-white font-semibold text-sm" :disabled="profileForm.processing">
                                        {{ profileForm.processing ? 'Salvando...' : 'Salvar dados' }}
                                    </button>
                                    <button type="button" class="px-4 py-2.5 rounded-xl border border-gray-200 font-semibold text-sm text-gray-600" @click="logout">
                                        Sair da conta
                                    </button>
                                </div>
                            </form>
                        </template>
                    </div>
                </main>

                <nav class="md:hidden fixed bottom-0 w-full bg-white border-t border-gray-200 px-6 py-2 flex justify-between items-center z-50">
                    <button class="flex flex-col items-center p-2" :class="activeTab === 'home' ? 'text-[var(--idx-primary)]' : 'text-gray-400'" @click="activeTab = 'home'">
                        <Home :size="16" class="mb-1" />
                        <span class="text-[10px] font-medium">Início</span>
                    </button>
                    <button class="flex flex-col items-center p-2" :class="activeTab === 'orders' ? 'text-[var(--idx-primary)]' : 'text-gray-400'" @click="activeTab = 'orders'">
                        <Package :size="16" class="mb-1" />
                        <span class="text-[10px] font-medium">{{ isServicesMode ? 'Agenda' : 'Pedidos' }}</span>
                    </button>

                    <div class="relative -top-5">
                        <button class="bg-[#FF3B30] text-white w-14 h-14 rounded-full shadow-lg flex items-center justify-center" @click="activeTab = 'favorites'">
                            <Heart :size="20" :class="{ 'fill-white': activeTab === 'favorites' }" />
                        </button>
                        <span class="text-[10px] font-medium text-gray-400 absolute -bottom-4 left-1/2 transform -translate-x-1/2">Favoritos</span>
                    </div>

                    <button class="flex flex-col items-center p-2" :class="activeTab === 'account' ? 'text-[var(--idx-primary)]' : 'text-gray-400'" @click="activeTab = 'account'">
                        <UserRound :size="16" class="mb-1" />
                        <span class="text-[10px] font-medium">Conta</span>
                    </button>
                    <button class="flex flex-col items-center p-2 text-gray-400" @click="isCartOpen = true">
                        <ShoppingCart :size="16" class="mb-1" />
                        <span class="text-[10px] font-medium">Carrinho</span>
                    </button>
                </nav>
            </div>

            <transition
                enter-active-class="transition duration-250 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-2 opacity-0"
            >
                <div
                    v-if="cartToast.visible"
                    class="pointer-events-none absolute left-1/2 top-4 z-[70] w-[92%] max-w-sm -translate-x-1/2 rounded-2xl border border-emerald-200 bg-white/95 px-4 py-3 shadow-xl backdrop-blur"
                >
                    <p class="text-sm font-semibold text-emerald-700">{{ cartToast.title }}</p>
                    <p class="mt-0.5 text-xs text-slate-600">{{ cartToast.description }}</p>
                </div>
            </transition>

            <div v-if="isCartOpen" class="idx-cart-backdrop" @click="isCartOpen = false"></div>

            <div class="idx-cart-panel" :class="{ open: isCartOpen }">
                <header class="flex justify-between items-center p-4 border-b border-gray-100">
                    <button type="button" class="text-gray-600 p-2" @click="isCartOpen = false"><ArrowLeft :size="20" /></button>
                    <h1 class="text-lg font-bold">{{ isServicesMode ? 'Agendamento' : 'Carrinho' }}</h1>
                    <button type="button" class="text-gray-600 p-2" @click="clearCart"><Trash2 :size="20" /></button>
                </header>

                <div class="p-4">
                    <div class="bg-[#F8F9FA] rounded-2xl p-4 flex justify-between items-center">
                        <h2 class="text-xl font-medium">{{ isServicesMode ? 'Serviço selecionado' : 'Resumo do pedido' }}</h2>
                        <span class="text-gray-500 text-sm">{{ cartCount }} item(ns)</span>
                    </div>

                    <div v-if="selectedItem && selectedItem.variations?.length && !isServicesMode" class="mt-3 flex items-center gap-2">
                        <select v-model="variationByProduct[selectedItem.id]" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm bg-white">
                            <option :value="null">Preço base</option>
                            <option v-for="variation in selectedItem.variations" :key="variation.id" :value="variation.id">
                                {{ variation.name }} - {{ formatMoney(variation.price) }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-4 pb-32 border-t border-gray-100">
                    <div v-for="entry in cartEntries" :key="`entry-${entry.id}`" class="flex items-center py-4 border-b border-gray-100">
                        <img :src="entry.image" :alt="entry.title" class="w-16 h-16 rounded-xl object-cover mr-4">
                        <div class="flex-1">
                            <h3 class="font-medium text-gray-800">{{ entry.title }}</h3>
                            <p class="text-xs text-gray-400">{{ entry.subtitle }}</p>
                            <p class="font-bold text-gray-800 mt-1">{{ formatMoney(entry.unitPrice) }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <div v-if="!isServicesMode" class="flex items-center gap-2">
                                <button type="button" class="w-6 h-6 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center" @click="decrease(entry.id)"><Minus :size="12" /></button>
                                <span class="text-xs font-semibold">{{ entry.quantity }}</span>
                                <button type="button" class="w-6 h-6 rounded-full bg-[var(--idx-primary)] text-white flex items-center justify-center" @click="increase(entry.id)"><Plus :size="12" /></button>
                            </div>
                            <span class="text-gray-500 text-sm">{{ formatMoney(entry.lineTotal) }}</span>
                        </div>
                    </div>

                    <div v-if="!cartEntries.length" class="py-10 text-center text-sm text-gray-500">
                        {{ isServicesMode ? 'Nenhum serviço selecionado.' : 'Seu carrinho está vazio.' }}
                    </div>
                </div>

                <div class="absolute bottom-0 w-full bg-white border-t border-gray-100 p-6 idx-glass z-10">
                    <div class="flex justify-between items-center mb-2 text-sm">
                        <span class="font-medium text-gray-700">Subtotal</span>
                        <span class="font-semibold text-gray-800">{{ formatMoney(subtotal) }}</span>
                    </div>
                    <div v-if="!isServicesMode" class="flex justify-between items-center mb-2 text-sm">
                        <span class="font-medium text-gray-700">Entrega + taxa</span>
                        <span class="font-semibold text-gray-800">{{ formatMoney(deliveryFee + paymentFee) }}</span>
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <span class="font-medium text-gray-800">Total</span>
                        <span class="font-bold text-green-600">{{ formatMoney(total) }}</span>
                    </div>
                    <button
                        type="button"
                        class="w-full bg-[#F58D1D] hover:bg-orange-500 transition-colors text-white font-medium py-4 rounded-full shadow-lg disabled:opacity-60 disabled:cursor-not-allowed"
                        :disabled="isServicesMode ? bookingForm.processing || !cartEntries.length : checkoutForm.processing || !cartEntries.length"
                        @click="isServicesMode ? submitQuickBooking() : submitQuickCheckout()"
                    >
                        {{
                            isServicesMode
                                ? (bookingForm.processing ? 'Enviando agendamento...' : 'Confirmar agendamento')
                                : (checkoutForm.processing ? 'Enviando pedido...' : 'Finalizar pedido')
                        }}
                    </button>
                    <InputError :message="checkoutForm.errors.order" class="mt-2" />
                    <InputError :message="bookingForm.errors.booking" class="mt-2" />
                </div>
            </div>
        </div>
    </div>
</template>
<style scoped>
.idx-root {
    --idx-secondary: #f8f9fa;
    --idx-dark: #1e1e1e;
}

.idx-root :deep(::-webkit-scrollbar) {
    width: 6px;
    height: 6px;
}

.idx-root :deep(::-webkit-scrollbar-track) {
    background: #f1f1f1;
}

.idx-root :deep(::-webkit-scrollbar-thumb) {
    background: #cbd5e1;
    border-radius: 10px;
}

.idx-root :deep(::-webkit-scrollbar-thumb:hover) {
    background: #94a3b8;
}

.idx-cart-backdrop {
    position: absolute;
    inset: 0;
    z-index: 40;
    background: rgba(15, 23, 42, 0.46);
}

.idx-cart-panel {
    position: absolute;
    top: 0;
    right: 0;
    width: 100%;
    max-width: 420px;
    height: 100%;
    background: #fff;
    display: flex;
    flex-direction: column;
    z-index: 50;
    transform: translateX(100%);
    transition: transform 0.3s ease;
    border-left: 1px solid rgba(15, 23, 42, 0.12);
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28);
}

.idx-cart-panel.open {
    transform: translateX(0);
}

.idx-glass {
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(10px);
}

@media (max-width: 767px) {
    .idx-cart-panel {
        max-width: none;
    }
}

@media (min-width: 768px) {
    .idx-root {
        background: #f8fafc;
    }
}
</style>

