
<script setup>
/* eslint-disable vue/prop-name-casing */
import InputError from '@/Components/InputError.vue';
import { BRAZIL_STATES, formatCepBR, formatPhoneBR, normalizeStateCode } from '@/utils/br';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import {
    Bell,
    ChevronRight,
    CreditCard,
    Heart,
    Home,
    LayoutGrid,
    LogIn,
    LogOut,
    Menu,
    Minus,
    Package,
    Plus,
    Search,
    Settings,
    ShoppingCart,
    Star,
    UserRound,
    Wallet,
} from 'lucide-vue-next';

const props = defineProps({
    mode: {
        type: String,
        default: 'commerce',
    },
    contractor: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
    services: {
        type: Array,
        default: () => [],
    },
    storefront: {
        type: Object,
        default: () => ({}),
    },
    store_availability: {
        type: Object,
        default: () => ({}),
    },
    payment_methods: {
        type: Array,
        default: () => [],
    },
    shipping_config: {
        type: Object,
        default: () => ({}),
    },
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
    shop_account: {
        type: Object,
        default: () => ({
            orders: [],
        }),
    },
    bookings: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const toInt = (value, fallback = 0) => {
    const parsed = Number.parseInt(String(value ?? ''), 10);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const toMoney = (value, fallback = 0) => {
    const parsed = Number.parseFloat(String(value ?? ''));
    return Number.isFinite(parsed) ? parsed : fallback;
};

const normalizeHex = (value, fallback = '#ff4f1f') => {
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
    const rgb = hexToRgb(hex);
    const clamped = Math.max(0, Math.min(1, alpha));
    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${clamped})`;
};

const contrastColor = (hex) => {
    const { r, g, b } = hexToRgb(hex);
    const luminance = ((r * 299) + (g * 587) + (b * 114)) / 255000;
    return luminance > 0.63 ? '#0f172a' : '#ffffff';
};

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const formatMoney = (value) => currency.format(toMoney(value));

const isServicesMode = computed(() => String(props.mode ?? '').toLowerCase() === 'services');
const isCommerceMode = computed(() => !isServicesMode.value);

const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const storePhone = computed(() => String(props.contractor?.phone || '').trim());
const storePrimary = computed(() => normalizeHex(props.contractor?.primary_color, '#ff4f1f'));
const storeLogo = computed(() => {
    const safe = String(props.contractor?.avatar_url || props.contractor?.logo_url || '').trim();
    return safe !== '' ? safe : null;
});

const storeInitials = computed(() => {
    const safe = String(storeName.value ?? '').trim();
    if (!safe) return 'LJ';
    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) ?? '';
    const second = parts.length > 1 ? parts[parts.length - 1]?.charAt(0) ?? '' : '';
    return `${first}${second}`.toUpperCase() || 'LJ';
});

const pageQuery = computed(() => {
    const rawUrl = String(page.url ?? '');
    const questionIndex = rawUrl.indexOf('?');
    const queryString = questionIndex >= 0 ? rawUrl.slice(questionIndex + 1) : '';
    return new URLSearchParams(queryString);
});

const queryOpenAccount = computed(() => {
    const value = String(pageQuery.value.get('conta') ?? '').trim().toLowerCase();
    return value === '1' || value === 'true';
});

const queryProductId = computed(() => {
    const value = toInt(pageQuery.value.get('produto'), 0);
    return value > 0 ? value : null;
});

const flashStatus = computed(() => String(page.props?.flash?.status ?? '').trim());
const checkoutPayment = computed(() => page.props?.flash?.checkout_payment ?? null);
const checkoutManual = computed(() => page.props?.flash?.checkout_manual ?? null);
const bookingWhatsappUrl = computed(() => String(page.props?.flash?.service_booking_whatsapp_url ?? '').trim());
const bookingWhatsappMessage = computed(() => String(page.props?.flash?.service_booking_whatsapp_message ?? '').trim());

const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const registerUrl = computed(() => `/shop/${storeSlug.value}/cadastro`);
const verifyEmailUrl = computed(() => `/shop/${storeSlug.value}/verificar-email`);
const logoutUrl = computed(() => `/shop/${storeSlug.value}/sair`);
const checkoutUrl = computed(() => `/shop/${storeSlug.value}/checkout`);
const serviceBookUrl = computed(() => `/shop/${storeSlug.value}/servicos/agendar`);
const accountUpdateUrl = computed(() => `/shop/${storeSlug.value}/conta`);
const productUrl = (id) => `/shop/${storeSlug.value}/produto/${id}`;
const favoriteUrl = (id) => `/shop/${storeSlug.value}/favoritos/${id}`;

const isAuthenticated = computed(() => Boolean(props.shop_auth?.authenticated));
const requiresEmailVerification = computed(() => Boolean(props.shop_auth?.requires_email_verification ?? true));
const isEmailVerified = computed(() => Boolean(props.shop_auth?.email_verified ?? false));
const hasVerifiedAccess = computed(() => !requiresEmailVerification.value || isEmailVerified.value);
const isAddressComplete = computed(() => Boolean(props.shop_auth?.address_complete ?? false));
const missingAddressFields = computed(() => (
    Array.isArray(props.shop_auth?.missing_address_fields) ? props.shop_auth.missing_address_fields : []
));
const customer = computed(() => props.shop_auth?.customer ?? null);

const categoryOptions = computed(() => {
    const safe = Array.isArray(props.categories) ? props.categories : [];
    const normalized = safe
        .map((category) => ({
            id: toInt(category?.id, 0),
            label: String(category?.name || 'Categoria'),
        }))
        .filter((category) => category.id > 0);

    return [{ id: 'all', label: 'Todos' }, ...normalized];
});

const imageFallback = computed(() => {
    if (storeLogo.value) return storeLogo.value;
    return isServicesMode.value
        ? 'https://placehold.co/960x720/e2e8f0/0f172a?text=Servicos'
        : 'https://placehold.co/960x720/e2e8f0/0f172a?text=Produtos';
});

const normalizedProducts = computed(() => {
    const safe = Array.isArray(props.products) ? props.products : [];

    return safe
        .map((product) => {
            const basePrice = toMoney(product?.sale_price, 0);
            const stock = toInt(product?.stock_quantity, 0);
            const firstImage = Array.isArray(product?.images)
                ? product.images.find((row) => String(row?.image_url || '').trim() !== '')
                : null;
            const imageUrl = String(firstImage?.image_url || product?.image_url || '').trim() || imageFallback.value;
            const id = toInt(product?.id, 0);

            return {
                id,
                categoryId: toInt(product?.category_id, toInt(product?.category_parent_id, 0)),
                title: String(product?.name || 'Produto'),
                subtitle: String(product?.category_name || product?.sku || 'Produto'),
                description: String(product?.description || 'Sem descricao informada.'),
                price: basePrice,
                oldPrice: basePrice > 0 ? Number((basePrice * 1.14).toFixed(2)) : 0,
                rating: 4.6 + ((id % 4) * 0.1),
                reviews: 120 + ((id % 9) * 78),
                eta: '30-45 min',
                badge: product?.has_variations ? 'Variacoes' : 'Destaque',
                image: imageUrl,
                stock,
                hasVariations: Boolean(product?.has_variations),
                variations: Array.isArray(product?.variations)
                    ? product.variations
                        .map((variation) => ({
                            id: toInt(variation?.id, 0),
                            name: String(variation?.name || 'Variacao'),
                            sale_price: toMoney(variation?.sale_price, basePrice),
                            stock_quantity: toInt(variation?.stock_quantity, 0),
                        }))
                        .filter((variation) => variation.id > 0 && variation.stock_quantity > 0)
                    : [],
                raw: product,
            };
        })
        .filter((item) => item.id > 0 && item.stock > 0);
});
const normalizedServices = computed(() => {
    const safe = Array.isArray(props.services) ? props.services : [];

    return safe
        .map((service) => {
            const id = toInt(service?.id, 0);
            const basePrice = toMoney(service?.base_price, 0);
            const imageUrl = String(service?.image_url || '').trim() || imageFallback.value;
            const labelReviews = String(service?.reviews_label || '').trim();
            const parsedReviews = Number.parseInt(labelReviews.replace(/\D+/g, ''), 10);

            return {
                id,
                categoryId: toInt(service?.service_category_id, 0),
                title: String(service?.name || 'Servico'),
                subtitle: String(service?.category_name || service?.code || 'Atendimento'),
                description: String(service?.description || 'Sem descricao informada.'),
                price: basePrice,
                oldPrice: basePrice > 0 ? Number((basePrice * 1.12).toFixed(2)) : 0,
                rating: toMoney(service?.rating, 5),
                reviews: Number.isFinite(parsedReviews) ? parsedReviews : 240,
                eta: String(service?.duration_label || '60 min'),
                badge: String(service?.coupon_label || 'Atendimento premium'),
                image: imageUrl,
                stock: 1,
                hasVariations: false,
                variations: [],
                raw: service,
            };
        })
        .filter((item) => item.id > 0);
});

const catalogItems = computed(() => (
    isServicesMode.value ? normalizedServices.value : normalizedProducts.value
));

const featuredItems = computed(() => catalogItems.value.slice(0, 5));

const activeScreen = ref('home');
const searchTerm = ref('');
const activeCategory = ref('all');
const selectedItemId = ref(null);
const selectedVariationId = ref(null);
const detailQuantity = ref(1);
const interfaceMessage = ref('');

watch(categoryOptions, (options) => {
    const allowed = new Set(options.map((option) => String(option.id)));
    if (!allowed.has(String(activeCategory.value))) {
        activeCategory.value = 'all';
    }
}, { immediate: true });

watch(catalogItems, (items) => {
    if (!items.length) {
        selectedItemId.value = null;
        return;
    }

    if (queryProductId.value && items.some((item) => item.id === queryProductId.value)) {
        selectedItemId.value = queryProductId.value;
        activeScreen.value = 'detail';
        return;
    }

    if (!items.some((item) => item.id === selectedItemId.value)) {
        selectedItemId.value = items[0].id;
    }
}, { immediate: true });

watch(
    () => [queryOpenAccount.value, queryProductId.value],
    ([openAccount, productId]) => {
        if (openAccount) {
            activeScreen.value = 'account';
            return;
        }

        if (productId && catalogItems.value.some((item) => item.id === productId)) {
            selectedItemId.value = productId;
            activeScreen.value = 'detail';
        }
    },
    { immediate: true },
);

const selectedItem = computed(() => (
    catalogItems.value.find((item) => item.id === selectedItemId.value) ?? null
));

watch(selectedItem, (item) => {
    detailQuantity.value = 1;

    if (!item || !item.hasVariations || !item.variations.length) {
        selectedVariationId.value = null;
        return;
    }

    if (!item.variations.some((variation) => variation.id === selectedVariationId.value)) {
        selectedVariationId.value = item.variations[0].id;
    }
}, { immediate: true });

const selectedVariation = computed(() => {
    if (!selectedItem.value?.hasVariations) return null;
    return selectedItem.value.variations.find((variation) => variation.id === selectedVariationId.value) ?? null;
});

const normalizedSearch = computed(() => String(searchTerm.value ?? '').trim().toLowerCase());

const filteredItems = computed(() => catalogItems.value.filter((item) => {
    const categoryMatch = activeCategory.value === 'all' || toInt(activeCategory.value, 0) === item.categoryId;
    const searchSpace = `${item.title} ${item.subtitle} ${item.description}`.toLowerCase();
    const searchMatch = normalizedSearch.value === '' || searchSpace.includes(normalizedSearch.value);
    return categoryMatch && searchMatch;
}));

const localServiceFavoritesKey = computed(() => `veshop-native-service-favorites:${storeSlug.value}`);
const favoriteProductIds = ref([]);

const syncFavoriteIdsFromProps = () => {
    if (isCommerceMode.value) {
        favoriteProductIds.value = Array.from(new Set(
            (Array.isArray(props.shop_auth?.favorite_product_ids) ? props.shop_auth.favorite_product_ids : [])
                .map((id) => toInt(id, 0))
                .filter((id) => id > 0),
        ));
        return;
    }

    try {
        const serialized = localStorage.getItem(localServiceFavoritesKey.value);
        if (!serialized) {
            favoriteProductIds.value = [];
            return;
        }

        const parsed = JSON.parse(serialized);
        favoriteProductIds.value = Array.isArray(parsed)
            ? parsed.map((id) => toInt(id, 0)).filter((id) => id > 0)
            : [];
    } catch {
        favoriteProductIds.value = [];
    }
};

watch(
    () => [isCommerceMode.value, props.shop_auth?.favorite_product_ids],
    () => syncFavoriteIdsFromProps(),
    { immediate: true, deep: true },
);

watch(favoriteProductIds, (ids) => {
    if (isCommerceMode.value) return;
    try {
        localStorage.setItem(localServiceFavoritesKey.value, JSON.stringify(ids));
    } catch {
        // ignore storage failure
    }
}, { deep: true });

const favoritePendingProductId = ref(null);
const isFavorite = (id) => favoriteProductIds.value.includes(id);

const toggleFavorite = (item) => {
    if (!item) return;

    const productId = toInt(item.id, 0);
    if (productId <= 0) return;

    const alreadyFavorite = isFavorite(productId);

    if (!isCommerceMode.value) {
        if (alreadyFavorite) {
            favoriteProductIds.value = favoriteProductIds.value.filter((id) => id !== productId);
        } else {
            favoriteProductIds.value = [...favoriteProductIds.value, productId];
        }
        return;
    }

    if (!isAuthenticated.value) {
        interfaceMessage.value = 'Faça login para salvar favoritos.';
        router.visit(loginUrl.value);
        return;
    }

    if (favoritePendingProductId.value === productId) return;
    favoritePendingProductId.value = productId;

    if (alreadyFavorite) {
        favoriteProductIds.value = favoriteProductIds.value.filter((id) => id !== productId);
        router.delete(favoriteUrl(productId), {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                favoriteProductIds.value = [...favoriteProductIds.value, productId];
            },
            onFinish: () => {
                favoritePendingProductId.value = null;
            },
        });
        return;
    }

    favoriteProductIds.value = [...favoriteProductIds.value, productId];
    router.post(favoriteUrl(productId), {}, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            favoriteProductIds.value = favoriteProductIds.value.filter((id) => id !== productId);
        },
        onFinish: () => {
            favoritePendingProductId.value = null;
        },
    });
};

const favoriteItems = computed(() => catalogItems.value.filter((item) => isFavorite(item.id)));

const cartStorageKey = computed(() => `veshop-native-cart:${storeSlug.value}:${isServicesMode.value ? 'services' : 'commerce'}`);
const cart = ref({});

const saveCartToStorage = () => {
    try {
        localStorage.setItem(cartStorageKey.value, JSON.stringify(cart.value));
    } catch {
        // ignore storage failure
    }
};

const hydrateCartFromStorage = () => {
    try {
        const serialized = localStorage.getItem(cartStorageKey.value);
        if (!serialized) {
            cart.value = {};
            return;
        }

        const parsed = JSON.parse(serialized);
        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            cart.value = {};
            return;
        }

        const next = {};
        Object.entries(parsed).forEach(([rawId, row]) => {
            const id = toInt(rawId, 0);
            const quantity = toInt(row?.quantity, 0);
            const variationId = row?.variation_id ? toInt(row.variation_id, 0) : null;
            if (id > 0 && quantity > 0) {
                next[id] = {
                    quantity,
                    variation_id: variationId && variationId > 0 ? variationId : null,
                };
            }
        });
        cart.value = next;
    } catch {
        cart.value = {};
    }
};

onMounted(() => {
    hydrateCartFromStorage();
    syncFavoriteIdsFromProps();
});

watch([cartStorageKey, isServicesMode], () => {
    hydrateCartFromStorage();
}, { immediate: true });

watch(cart, () => {
    saveCartToStorage();
}, { deep: true });

const resolveItemPrice = (item, variationId = null) => {
    if (!item) return 0;
    if (!item.hasVariations || !variationId) return toMoney(item.price, 0);
    const variation = item.variations.find((row) => row.id === variationId);
    return variation ? toMoney(variation.sale_price, item.price) : toMoney(item.price, 0);
};

const addToCart = (item, quantity = 1) => {
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
        interfaceMessage.value = 'Servico selecionado para agendamento.';
        return;
    }

    const current = cart.value[id] ?? { quantity: 0, variation_id: null };
    const nextQuantity = Math.max(1, toInt(current.quantity, 0) + Math.max(1, toInt(quantity, 1)));
    const variationId = selectedVariation.value?.id ?? current.variation_id ?? null;

    cart.value = {
        ...cart.value,
        [id]: {
            quantity: nextQuantity,
            variation_id: variationId,
        },
    };
};
const increaseCartItem = (itemId) => {
    const id = toInt(itemId, 0);
    if (!id || !cart.value[id]) return;
    if (isServicesMode.value) return;

    cart.value = {
        ...cart.value,
        [id]: {
            ...cart.value[id],
            quantity: toInt(cart.value[id].quantity, 0) + 1,
        },
    };
};

const decreaseCartItem = (itemId) => {
    const id = toInt(itemId, 0);
    if (!id || !cart.value[id]) return;

    const current = toInt(cart.value[id].quantity, 0);
    if (current <= 1) {
        const next = { ...cart.value };
        delete next[id];
        cart.value = next;
        return;
    }

    cart.value = {
        ...cart.value,
        [id]: {
            ...cart.value[id],
            quantity: current - 1,
        },
    };
};

const removeCartItem = (itemId) => {
    const id = toInt(itemId, 0);
    if (!id) return;
    const next = { ...cart.value };
    delete next[id];
    cart.value = next;
};

const clearCart = () => {
    cart.value = {};
};

const cartEntries = computed(() => Object.entries(cart.value)
    .map(([rawId, row]) => {
        const itemId = toInt(rawId, 0);
        const item = catalogItems.value.find((entry) => entry.id === itemId);
        if (!item) return null;

        const quantity = Math.max(1, toInt(row?.quantity, 1));
        const variationId = row?.variation_id ? toInt(row.variation_id, 0) : null;
        const unitPrice = resolveItemPrice(item, variationId);
        return {
            ...item,
            quantity: isServicesMode.value ? 1 : quantity,
            variationId: variationId && variationId > 0 ? variationId : null,
            unitPrice,
            lineTotal: unitPrice * (isServicesMode.value ? 1 : quantity),
        };
    })
    .filter(Boolean));

const cartCount = computed(() => cartEntries.value.reduce((sum, entry) => sum + entry.quantity, 0));
const subtotal = computed(() => cartEntries.value.reduce((sum, entry) => sum + entry.lineTotal, 0));

const paymentMethods = computed(() => (
    Array.isArray(props.payment_methods)
        ? props.payment_methods.map((method) => ({
            id: toInt(method?.id, 0),
            name: String(method?.name || 'Pagamento'),
            code: String(method?.code || ''),
            fee_fixed: toMoney(method?.fee_fixed, 0),
            fee_percent: toMoney(method?.fee_percent, 0),
            checkout_mode: String(method?.checkout_mode || 'manual'),
        })).filter((method) => method.id > 0)
        : []
));

const shippingConfig = computed(() => ({
    pickup_enabled: Boolean(props.shipping_config?.pickup_enabled ?? true),
    delivery_enabled: Boolean(props.shipping_config?.delivery_enabled ?? true),
    fixed_fee: Math.max(0, toMoney(props.shipping_config?.fixed_fee, 0)),
    free_over: Math.max(0, toMoney(props.shipping_config?.free_over, 0)),
    estimated_days: toInt(props.shipping_config?.estimated_days, 0),
}));

const defaultDeliveryMode = computed(() => {
    if (shippingConfig.value.delivery_enabled) return 'delivery';
    return 'pickup';
});

const checkoutForm = useForm({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    notes: '',
    payment_method_id: null,
    delivery_mode: defaultDeliveryMode.value,
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

watch(defaultDeliveryMode, (mode) => {
    if (!['delivery', 'pickup'].includes(String(checkoutForm.delivery_mode))) {
        checkoutForm.delivery_mode = mode;
    }
}, { immediate: true });

watch(paymentMethods, (methods) => {
    if (!methods.length) {
        checkoutForm.payment_method_id = null;
        return;
    }

    if (!methods.some((method) => method.id === toInt(checkoutForm.payment_method_id, -1))) {
        checkoutForm.payment_method_id = methods[0].id;
    }
}, { immediate: true });

const syncCheckoutFromCustomer = () => {
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

watch(customer, () => syncCheckoutFromCustomer(), { immediate: true, deep: true });

const selectedPaymentMethod = computed(() => (
    paymentMethods.value.find((method) => method.id === toInt(checkoutForm.payment_method_id, -1)) ?? null
));

const paymentFeeAmount = computed(() => {
    const method = selectedPaymentMethod.value;
    if (!method) return 0;
    const percent = Math.max(0, method.fee_percent);
    const fixed = Math.max(0, method.fee_fixed);
    const fee = subtotal.value * (percent / 100) + fixed;
    return Number(fee.toFixed(2));
});

const deliveryFeeAmount = computed(() => {
    if (!isCommerceMode.value) return 0;
    if (checkoutForm.delivery_mode !== 'delivery') return 0;
    if (!shippingConfig.value.delivery_enabled) return 0;

    if (shippingConfig.value.free_over > 0 && subtotal.value >= shippingConfig.value.free_over) {
        return 0;
    }

    return Number(shippingConfig.value.fixed_fee.toFixed(2));
});

const orderTotal = computed(() => (
    Number((subtotal.value + paymentFeeAmount.value + deliveryFeeAmount.value).toFixed(2))
));

const commerceCheckoutBlockedReason = computed(() => {
    if (!isAuthenticated.value) return 'Faça login para finalizar o pedido.';
    if (!hasVerifiedAccess.value) return 'Confirme seu e-mail para finalizar o pedido.';
    if (!isAddressComplete.value) return 'Complete seu endereço para finalizar o pedido.';
    return '';
});

const submitCommerceCheckout = () => {
    interfaceMessage.value = '';

    if (!isAuthenticated.value) {
        router.visit(loginUrl.value);
        return;
    }

    if (!hasVerifiedAccess.value) {
        router.visit(verifyEmailUrl.value);
        return;
    }

    if (!cartEntries.value.length) {
        interfaceMessage.value = 'Adicione itens ao carrinho antes de finalizar.';
        return;
    }

    checkoutForm.clearErrors();
    checkoutForm.customer_phone = formatPhoneBR(checkoutForm.customer_phone);
    checkoutForm.shipping_postal_code = formatCepBR(checkoutForm.shipping_postal_code);
    checkoutForm.shipping_state = normalizeStateCode(checkoutForm.shipping_state);
    checkoutForm.idempotency_key = `native-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
    checkoutForm.items = cartEntries.value.map((entry) => ({
        product_id: entry.id,
        variation_id: entry.variationId,
        quantity: entry.quantity,
    }));

    checkoutForm.post(checkoutUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            clearCart();
            activeScreen.value = 'orders';
        },
    });
};

const defaultBookingDatetime = () => {
    const now = new Date();
    now.setHours(now.getHours() + 2);
    now.setMinutes(0, 0, 0);

    const tzOffset = now.getTimezoneOffset() * 60000;
    return new Date(now.getTime() - tzOffset).toISOString().slice(0, 16);
};

const bookingForm = useForm({
    service_catalog_id: null,
    scheduled_for: defaultBookingDatetime(),
    notes: '',
});

const serviceBookingCandidate = computed(() => (
    cartEntries.value[0] ?? selectedItem.value ?? null
));

const submitServiceBooking = () => {
    interfaceMessage.value = '';

    if (!isAuthenticated.value) {
        router.visit(loginUrl.value);
        return;
    }

    if (!hasVerifiedAccess.value) {
        router.visit(verifyEmailUrl.value);
        return;
    }

    const target = serviceBookingCandidate.value;
    if (!target) {
        interfaceMessage.value = 'Selecione um servico antes de agendar.';
        return;
    }

    bookingForm.clearErrors();
    bookingForm.service_catalog_id = target.id;

    if (!bookingForm.scheduled_for) {
        bookingForm.scheduled_for = defaultBookingDatetime();
    }

    bookingForm.post(serviceBookUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            clearCart();
            activeScreen.value = 'orders';
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

const syncProfileFormFromCustomer = () => {
    profileForm.phone = formatPhoneBR(String(customer.value?.phone || ''));
    profileForm.cep = formatCepBR(String(customer.value?.cep || ''));
    profileForm.street = String(customer.value?.street || '');
    profileForm.number = String(customer.value?.number || '');
    profileForm.complement = String(customer.value?.complement || '');
    profileForm.neighborhood = String(customer.value?.neighborhood || '');
    profileForm.city = String(customer.value?.city || '');
    profileForm.state = normalizeStateCode(String(customer.value?.state || ''));
};

watch(customer, () => syncProfileFormFromCustomer(), { immediate: true, deep: true });

const onProfilePhoneInput = (event) => {
    profileForm.phone = formatPhoneBR(event?.target?.value ?? profileForm.phone);
};

const onProfileCepInput = (event) => {
    profileForm.cep = formatCepBR(event?.target?.value ?? profileForm.cep);
};

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
            interfaceMessage.value = 'Dados atualizados com sucesso.';
        },
    });
};

const logoutForm = useForm({});
const submitLogout = () => {
    logoutForm.post(logoutUrl.value);
};

const openDetail = (item) => {
    if (!item) return;
    selectedItemId.value = item.id;
    activeScreen.value = 'detail';
};

const setScreen = (screen) => {
    activeScreen.value = screen;
    if (screen === 'detail' && !selectedItem.value) {
        selectedItemId.value = catalogItems.value[0]?.id ?? null;
    }
};

const screenMeta = computed(() => ({
    home: {
        title: isServicesMode.value ? 'Servicos em destaque' : 'Produtos em destaque',
        subtitle: 'Layout nativo com foco mobile-first.',
    },
    listing: {
        title: isServicesMode.value ? 'Catalogo de servicos' : 'Catalogo de produtos',
        subtitle: 'Busca, filtros e cards no novo padrao visual.',
    },
    detail: {
        title: isServicesMode.value ? 'Detalhes do servico' : 'Detalhes do produto',
        subtitle: 'Descricao completa e acao principal fixa.',
    },
    favorites: {
        title: 'Favoritos',
        subtitle: 'Itens salvos para acesso rapido.',
    },
    cart: {
        title: isServicesMode.value ? 'Servico selecionado' : 'Seu carrinho',
        subtitle: isServicesMode.value ? 'Confirme para agendar.' : 'Resumo dos itens e valores.',
    },
    checkout: {
        title: isServicesMode.value ? 'Agendamento' : 'Checkout',
        subtitle: isServicesMode.value ? 'Escolha data e hora.' : 'Pagamento e entrega.',
    },
    orders: {
        title: isServicesMode.value ? 'Historico de agendamentos' : 'Historico de pedidos',
        subtitle: 'Acompanhe o andamento em tempo real.',
    },
    account: {
        title: 'Minha conta',
        subtitle: 'Perfil, endereco, seguranca e preferencias.',
    },
}));

const currentScreenMeta = computed(() => (
    screenMeta.value[activeScreen.value] ?? screenMeta.value.home
));

const navItems = [
    { key: 'home', label: 'Inicio', icon: Home },
    { key: 'listing', label: 'Catalogo', icon: LayoutGrid },
    { key: 'favorites', label: 'Favoritos', icon: Heart },
    { key: 'orders', label: 'Pedidos', icon: Package },
    { key: 'account', label: 'Conta', icon: UserRound },
];

const storeAvailability = computed(() => {
    const raw = props.store_availability ?? {};
    const storeOnline = Boolean(raw.store_online ?? true);
    const openNow = Boolean(raw.is_open_now ?? true);

    return {
        store_online: storeOnline,
        is_open_now: openNow,
        can_checkout: Boolean(raw.can_checkout ?? storeOnline),
        can_book: Boolean(raw.can_book ?? storeOnline),
        status_label: String(raw.status_label ?? '').trim(),
        message: String(raw.message ?? '').trim(),
        next_open_label: String(raw.next_open_label ?? '').trim(),
    };
});

const normalizedCommerceOrders = computed(() => (
    Array.isArray(props.shop_account?.orders)
        ? props.shop_account.orders.map((order) => ({
            id: toInt(order?.id, 0),
            code: String(order?.code || ''),
            created_at: String(order?.created_at || ''),
            label: String(order?.status?.label || 'Pedido'),
            tone: String(order?.status?.tone || ''),
            total: toMoney(order?.total_amount, 0),
            payment_label: String(order?.payment_label || 'Nao informado'),
        }))
        : []
));

const normalizedServiceOrders = computed(() => (
    Array.isArray(props.bookings)
        ? props.bookings.map((booking) => ({
            id: toInt(booking?.id, 0),
            code: String(booking?.code || ''),
            created_at: String(booking?.scheduled_label || ''),
            label: String(booking?.status?.label || 'Agendado'),
            tone: String(booking?.status?.tone || ''),
            total: toMoney(booking?.final_amount || booking?.estimated_amount, 0),
            payment_label: String(booking?.service_name || 'Servico'),
        }))
        : []
));

const activeOrders = computed(() => (
    isServicesMode.value ? normalizedServiceOrders.value : normalizedCommerceOrders.value
));

const toneClass = (tone) => {
    const safe = String(tone || '').toLowerCase();
    if (safe.includes('emerald') || safe.includes('green')) return 'tone-success';
    if (safe.includes('amber') || safe.includes('orange')) return 'tone-warning';
    if (safe.includes('blue') || safe.includes('sky')) return 'tone-info';
    if (safe.includes('rose') || safe.includes('red')) return 'tone-danger';
    return 'tone-default';
};

const searchPlaceholder = computed(() => (
    isServicesMode.value
        ? 'Buscar profissional, servico ou categoria'
        : 'Buscar produto, codigo ou categoria'
));

const sectionTitle = computed(() => (
    isServicesMode.value ? 'Profissionais e servicos' : 'Lojas e produtos populares'
));

const callToActionLabel = computed(() => (
    isServicesMode.value ? 'Selecionar servico' : 'Adicionar'
));

const firstHeroImage = computed(() => {
    if (selectedItem.value?.image) return selectedItem.value.image;
    if (featuredItems.value[0]?.image) return featuredItems.value[0].image;
    return imageFallback.value;
});

const stateOptions = computed(() => ([
    { value: '', label: 'Selecione a UF' },
    ...BRAZIL_STATES.map((state) => ({ value: state.code, label: `${state.code} - ${state.name}` })),
]));

const themeStyles = computed(() => ({
    '--sf-accent': storePrimary.value,
    '--sf-accent-soft': withAlpha(storePrimary.value, 0.16),
    '--sf-accent-strong': withAlpha(storePrimary.value, 0.9),
    '--sf-accent-border': withAlpha(storePrimary.value, 0.35),
    '--sf-accent-contrast': contrastColor(storePrimary.value),
    '--sf-surface': '#f8fafc',
    '--sf-text': '#0f172a',
    '--sf-muted': '#64748b',
    '--sf-border': 'rgba(15,23,42,.12)',
}));
</script>
<template>
    <div class="sf-app" :style="themeStyles">
        <div class="sf-shell">
            <aside class="sf-left">
                <div class="sf-brand">
                    <div class="sf-brand__logo">
                        <img v-if="storeLogo" :src="storeLogo" :alt="storeName">
                        <span v-else>{{ storeInitials }}</span>
                    </div>
                    <div class="sf-brand__meta">
                        <strong>{{ storeName }}</strong>
                        <small>{{ isServicesMode ? 'Nicho servicos' : 'Nicho comercio' }}</small>
                    </div>
                </div>

                <nav class="sf-side-nav">
                    <button
                        v-for="item in navItems"
                        :key="item.key"
                        type="button"
                        :class="{ active: activeScreen === item.key }"
                        @click="setScreen(item.key)"
                    >
                        <component :is="item.icon" :size="17" />
                        {{ item.label }}
                    </button>
                </nav>

                <div class="sf-side-extra">
                    <Link v-if="!isAuthenticated" :href="loginUrl" class="sf-side-link">
                        <LogIn :size="16" />
                        Entrar
                    </Link>
                    <button
                        v-else
                        type="button"
                        class="sf-side-link"
                        :disabled="logoutForm.processing"
                        @click="submitLogout"
                    >
                        <LogOut :size="16" />
                        Sair
                    </button>
                </div>
            </aside>

            <main class="sf-main">
                <header class="sf-header">
                    <div class="sf-header__hero">
                        <img class="sf-header__bg" :src="firstHeroImage" alt="">
                        <div class="sf-header__overlay"></div>

                        <div class="sf-header__top">
                            <button type="button" class="icon-btn desktop-hidden">
                                <Menu :size="18" />
                            </button>
                            <div class="sf-header__top-right">
                                <button type="button" class="icon-btn">
                                    <Bell :size="17" />
                                </button>
                                <button type="button" class="icon-btn" @click="setScreen('cart')">
                                    <ShoppingCart :size="18" />
                                    <span v-if="cartCount > 0" class="badge-dot">{{ cartCount }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="sf-header__content">
                            <small>{{ storeName }}</small>
                            <h1>{{ currentScreenMeta.title }}</h1>
                            <p>{{ currentScreenMeta.subtitle }}</p>
                        </div>
                    </div>
                </header>

                <section class="sf-content">
                    <div v-if="flashStatus" class="sf-alert success">
                        {{ flashStatus }}
                    </div>

                    <div v-if="interfaceMessage" class="sf-alert info">
                        {{ interfaceMessage }}
                    </div>

                    <div v-if="checkoutManual?.whatsapp_url" class="sf-alert warning">
                        <p>Pagamento manual disponivel para o pedido {{ checkoutManual?.sale_code }}.</p>
                        <a :href="checkoutManual.whatsapp_url" target="_blank" rel="noopener noreferrer">
                            Enviar confirmacao no WhatsApp
                        </a>
                    </div>

                    <div v-if="checkoutPayment?.is_pix && checkoutPayment?.qr_code" class="sf-alert pix">
                        <p>Pix gerado para o pedido {{ checkoutPayment?.sale_code }}.</p>
                        <code>{{ checkoutPayment.qr_code }}</code>
                    </div>

                    <div v-if="bookingWhatsappUrl" class="sf-alert success">
                        <p>Agendamento criado. Deseja avisar a loja pelo WhatsApp?</p>
                        <a :href="bookingWhatsappUrl" target="_blank" rel="noopener noreferrer">
                            Abrir WhatsApp
                        </a>
                        <small v-if="bookingWhatsappMessage">{{ bookingWhatsappMessage }}</small>
                    </div>

                    <section v-if="activeScreen === 'home'" class="sf-screen">
                        <div class="sf-search">
                            <Search :size="17" />
                            <input v-model="searchTerm" type="text" :placeholder="searchPlaceholder">
                        </div>

                        <div class="sf-chip-row">
                            <button
                                v-for="category in categoryOptions"
                                :key="category.id"
                                type="button"
                                :class="{ active: String(activeCategory) === String(category.id) }"
                                @click="activeCategory = category.id"
                            >
                                {{ category.label }}
                            </button>
                        </div>

                        <h3 class="sf-title">Destaques</h3>
                        <div class="sf-highlight-scroll">
                            <article
                                v-for="item in featuredItems"
                                :key="`feature-${item.id}`"
                                class="sf-highlight-card"
                                @click="openDetail(item)"
                            >
                                <img :src="item.image" :alt="item.title">
                                <span class="tag">{{ item.badge }}</span>
                                <div class="overlay">
                                    <strong>{{ item.title }}</strong>
                                    <small>{{ item.subtitle }}</small>
                                    <div class="meta">
                                        <span>{{ formatMoney(item.price) }}</span>
                                        <span>{{ item.eta }}</span>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <h3 class="sf-title">{{ sectionTitle }}</h3>
                        <div class="sf-list">
                            <article
                                v-for="item in filteredItems.slice(0, 6)"
                                :key="`home-${item.id}`"
                                class="sf-list-item"
                            >
                                <img :src="item.image" :alt="item.title" @click="openDetail(item)">
                                <div class="info" @click="openDetail(item)">
                                    <strong>{{ item.title }}</strong>
                                    <p>{{ item.subtitle }}</p>
                                    <div class="rating">
                                        <Star :size="13" />
                                        {{ item.rating.toFixed(1) }}
                                        <small>({{ item.reviews }})</small>
                                    </div>
                                </div>
                                <div class="actions">
                                    <span class="price">{{ formatMoney(item.price) }}</span>
                                    <button type="button" class="icon-btn" @click="toggleFavorite(item)">
                                        <Heart :size="16" :class="{ fill: isFavorite(item.id) }" />
                                    </button>
                                </div>
                            </article>
                        </div>
                    </section>
                    <section v-else-if="activeScreen === 'listing'" class="sf-screen">
                        <div class="sf-search">
                            <Search :size="17" />
                            <input v-model="searchTerm" type="text" :placeholder="searchPlaceholder">
                        </div>

                        <div class="sf-chip-row">
                            <button
                                v-for="category in categoryOptions"
                                :key="`list-${category.id}`"
                                type="button"
                                :class="{ active: String(activeCategory) === String(category.id) }"
                                @click="activeCategory = category.id"
                            >
                                {{ category.label }}
                            </button>
                        </div>

                        <div class="sf-catalog">
                            <article v-for="item in filteredItems" :key="`catalog-${item.id}`" class="sf-catalog-item">
                                <img :src="item.image" :alt="item.title" @click="openDetail(item)">
                                <div class="content">
                                    <strong>{{ item.title }}</strong>
                                    <p>{{ item.description }}</p>
                                    <div class="price-row">
                                        <span class="new">{{ formatMoney(item.price) }}</span>
                                        <span class="old">{{ formatMoney(item.oldPrice) }}</span>
                                    </div>
                                </div>
                                <div class="side">
                                    <button type="button" class="icon-btn" @click="toggleFavorite(item)">
                                        <Heart :size="16" :class="{ fill: isFavorite(item.id) }" />
                                    </button>
                                    <button type="button" class="sf-mini-btn" @click="addToCart(item)">
                                        <Plus :size="14" />
                                        {{ callToActionLabel }}
                                    </button>
                                    <Link
                                        v-if="isCommerceMode"
                                        :href="productUrl(item.id)"
                                        class="sf-mini-link"
                                    >
                                        Abrir
                                    </Link>
                                </div>
                            </article>
                        </div>

                        <div v-if="!filteredItems.length" class="sf-empty">
                            Nenhum item encontrado com os filtros atuais.
                        </div>
                    </section>

                    <section v-else-if="activeScreen === 'detail'" class="sf-screen">
                        <article v-if="selectedItem" class="sf-detail">
                            <img :src="selectedItem.image" :alt="selectedItem.title">
                            <div class="body">
                                <div class="top">
                                    <div>
                                        <strong>{{ selectedItem.title }}</strong>
                                        <p>{{ selectedItem.subtitle }}</p>
                                    </div>
                                    <button type="button" class="icon-btn" @click="toggleFavorite(selectedItem)">
                                        <Heart :size="16" :class="{ fill: isFavorite(selectedItem.id) }" />
                                    </button>
                                </div>

                                <p class="description">{{ selectedItem.description }}</p>

                                <div class="meta">
                                    <span>{{ formatMoney(resolveItemPrice(selectedItem, selectedVariation?.id)) }}</span>
                                    <small>de {{ formatMoney(selectedItem.oldPrice) }}</small>
                                    <small>{{ selectedItem.eta }}</small>
                                </div>

                                <label v-if="selectedItem.hasVariations" class="field">
                                    <span>Variacao</span>
                                    <select v-model="selectedVariationId">
                                        <option
                                            v-for="variation in selectedItem.variations"
                                            :key="variation.id"
                                            :value="variation.id"
                                        >
                                            {{ variation.name }} - {{ formatMoney(variation.sale_price) }}
                                        </option>
                                    </select>
                                </label>

                                <div v-if="isCommerceMode" class="stepper">
                                    <button type="button" @click="detailQuantity = Math.max(1, detailQuantity - 1)">
                                        <Minus :size="14" />
                                    </button>
                                    <strong>{{ detailQuantity }}</strong>
                                    <button type="button" @click="detailQuantity = detailQuantity + 1">
                                        <Plus :size="14" />
                                    </button>
                                </div>

                                <button type="button" class="sf-primary-btn" @click="addToCart(selectedItem, detailQuantity)">
                                    {{ callToActionLabel }}
                                    <span v-if="isCommerceMode">
                                        - {{ formatMoney(resolveItemPrice(selectedItem, selectedVariation?.id) * detailQuantity) }}
                                    </span>
                                </button>
                            </div>
                        </article>
                    </section>

                    <section v-else-if="activeScreen === 'favorites'" class="sf-screen">
                        <div v-if="favoriteItems.length" class="sf-list">
                            <article v-for="item in favoriteItems" :key="`fav-${item.id}`" class="sf-list-item">
                                <img :src="item.image" :alt="item.title" @click="openDetail(item)">
                                <div class="info" @click="openDetail(item)">
                                    <strong>{{ item.title }}</strong>
                                    <p>{{ item.subtitle }}</p>
                                    <div class="rating">
                                        <Star :size="13" />
                                        {{ item.rating.toFixed(1) }}
                                        <small>({{ item.reviews }})</small>
                                    </div>
                                </div>
                                <div class="actions">
                                    <button type="button" class="icon-btn" @click="toggleFavorite(item)">
                                        <Heart :size="16" class="fill" />
                                    </button>
                                    <button type="button" class="sf-mini-btn" @click="addToCart(item)">
                                        <Plus :size="14" />
                                        {{ callToActionLabel }}
                                    </button>
                                </div>
                            </article>
                        </div>
                        <div v-else class="sf-empty">
                            Nenhum favorito salvo.
                        </div>
                    </section>

                    <section v-else-if="activeScreen === 'cart'" class="sf-screen">
                        <div v-if="cartEntries.length" class="sf-cart">
                            <article v-for="entry in cartEntries" :key="`cart-${entry.id}`" class="sf-cart-item">
                                <img :src="entry.image" :alt="entry.title">
                                <div class="content">
                                    <strong>{{ entry.title }}</strong>
                                    <p>{{ formatMoney(entry.unitPrice) }}</p>
                                    <small v-if="entry.variationId && entry.variations.length">
                                        {{
                                            entry.variations.find((variation) => variation.id === entry.variationId)?.name
                                            || 'Variacao'
                                        }}
                                    </small>
                                    <div v-if="isCommerceMode" class="qty">
                                        <button type="button" @click="decreaseCartItem(entry.id)">
                                            <Minus :size="13" />
                                        </button>
                                        <span>{{ entry.quantity }}</span>
                                        <button type="button" @click="increaseCartItem(entry.id)">
                                            <Plus :size="13" />
                                        </button>
                                    </div>
                                </div>
                                <div class="end">
                                    <strong>{{ formatMoney(entry.lineTotal) }}</strong>
                                    <button type="button" class="remove-btn" @click="removeCartItem(entry.id)">
                                        Remover
                                    </button>
                                </div>
                            </article>

                            <article class="sf-bill">
                                <div><span>Subtotal</span><strong>{{ formatMoney(subtotal) }}</strong></div>
                                <div v-if="isCommerceMode"><span>Entrega</span><strong>{{ formatMoney(deliveryFeeAmount) }}</strong></div>
                                <div v-if="isCommerceMode"><span>Taxa pagamento</span><strong>{{ formatMoney(paymentFeeAmount) }}</strong></div>
                                <div class="total"><span>Total</span><strong>{{ formatMoney(orderTotal) }}</strong></div>
                            </article>

                            <button type="button" class="sf-primary-btn" @click="setScreen('checkout')">
                                {{ isServicesMode ? 'Continuar agendamento' : 'Ir para checkout' }}
                            </button>
                        </div>
                        <div v-else class="sf-empty">
                            {{ isServicesMode ? 'Nenhum servico selecionado.' : 'Carrinho vazio no momento.' }}
                        </div>
                    </section>
                    <section v-else-if="activeScreen === 'checkout'" class="sf-screen">
                        <template v-if="isCommerceMode">
                            <div v-if="commerceCheckoutBlockedReason" class="sf-auth-box">
                                <p>{{ commerceCheckoutBlockedReason }}</p>
                                <div class="actions">
                                    <Link v-if="!isAuthenticated" :href="loginUrl">Entrar</Link>
                                    <Link v-if="!isAuthenticated" :href="registerUrl">Criar conta</Link>
                                    <Link v-if="isAuthenticated && !hasVerifiedAccess" :href="verifyEmailUrl">
                                        Verificar e-mail
                                    </Link>
                                    <button type="button" @click="setScreen('account')">Atualizar endereco</button>
                                </div>
                                <small v-if="missingAddressFields.length">
                                    Campos pendentes: {{ missingAddressFields.join(', ') }}
                                </small>
                            </div>

                            <form v-else class="sf-form" @submit.prevent="submitCommerceCheckout">
                                <article class="sf-card">
                                    <h4>Contato</h4>
                                    <label class="field">
                                        <span>Nome</span>
                                        <input v-model="checkoutForm.customer_name" type="text" required>
                                    </label>
                                    <label class="field">
                                        <span>Telefone</span>
                                        <input
                                            :value="checkoutForm.customer_phone"
                                            type="text"
                                            inputmode="numeric"
                                            maxlength="15"
                                            @input="checkoutForm.customer_phone = formatPhoneBR($event.target.value)"
                                        >
                                    </label>
                                    <label class="field">
                                        <span>Email</span>
                                        <input v-model="checkoutForm.customer_email" type="email">
                                    </label>
                                    <InputError :message="checkoutForm.errors.customer_name" />
                                    <InputError :message="checkoutForm.errors.customer_phone" />
                                    <InputError :message="checkoutForm.errors.customer_email" />
                                </article>

                                <article class="sf-card">
                                    <h4>Entrega</h4>
                                    <div class="pill-grid">
                                        <button
                                            type="button"
                                            :disabled="!shippingConfig.pickup_enabled"
                                            :class="{ active: checkoutForm.delivery_mode === 'pickup' }"
                                            @click="checkoutForm.delivery_mode = 'pickup'"
                                        >
                                            Retirada
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="!shippingConfig.delivery_enabled"
                                            :class="{ active: checkoutForm.delivery_mode === 'delivery' }"
                                            @click="checkoutForm.delivery_mode = 'delivery'"
                                        >
                                            Entrega
                                        </button>
                                    </div>

                                    <template v-if="checkoutForm.delivery_mode === 'delivery'">
                                        <label class="field">
                                            <span>CEP</span>
                                            <input
                                                :value="checkoutForm.shipping_postal_code"
                                                type="text"
                                                inputmode="numeric"
                                                maxlength="9"
                                                @input="checkoutForm.shipping_postal_code = formatCepBR($event.target.value)"
                                            >
                                        </label>
                                        <label class="field">
                                            <span>Logradouro</span>
                                            <input v-model="checkoutForm.shipping_street" type="text">
                                        </label>
                                        <div class="field-grid">
                                            <label class="field">
                                                <span>Numero</span>
                                                <input v-model="checkoutForm.shipping_number" type="text">
                                            </label>
                                            <label class="field">
                                                <span>Complemento</span>
                                                <input v-model="checkoutForm.shipping_complement" type="text">
                                            </label>
                                        </div>
                                        <label class="field">
                                            <span>Bairro</span>
                                            <input v-model="checkoutForm.shipping_district" type="text">
                                        </label>
                                        <div class="field-grid">
                                            <label class="field">
                                                <span>Cidade</span>
                                                <input v-model="checkoutForm.shipping_city" type="text">
                                            </label>
                                            <label class="field">
                                                <span>UF</span>
                                                <input
                                                    v-model="checkoutForm.shipping_state"
                                                    type="text"
                                                    maxlength="2"
                                                >
                                            </label>
                                        </div>
                                    </template>
                                    <InputError :message="checkoutForm.errors.delivery_mode" />
                                    <InputError :message="checkoutForm.errors.shipping_postal_code" />
                                    <InputError :message="checkoutForm.errors.shipping_street" />
                                    <InputError :message="checkoutForm.errors.shipping_number" />
                                    <InputError :message="checkoutForm.errors.shipping_district" />
                                    <InputError :message="checkoutForm.errors.shipping_city" />
                                    <InputError :message="checkoutForm.errors.shipping_state" />
                                </article>

                                <article class="sf-card">
                                    <h4>Pagamento</h4>
                                    <div class="payment-grid">
                                        <button
                                            v-for="method in paymentMethods"
                                            :key="method.id"
                                            type="button"
                                            :class="{ active: checkoutForm.payment_method_id === method.id }"
                                            @click="checkoutForm.payment_method_id = method.id"
                                        >
                                            <Wallet :size="15" />
                                            {{ method.name }}
                                        </button>
                                    </div>
                                    <label class="field">
                                        <span>Observacoes</span>
                                        <textarea v-model="checkoutForm.notes" rows="3"></textarea>
                                    </label>
                                    <InputError :message="checkoutForm.errors.payment_method_id" />
                                    <InputError :message="checkoutForm.errors.notes" />
                                    <InputError :message="checkoutForm.errors.order" />
                                </article>

                                <article class="sf-bill">
                                    <div><span>Subtotal</span><strong>{{ formatMoney(subtotal) }}</strong></div>
                                    <div><span>Entrega</span><strong>{{ formatMoney(deliveryFeeAmount) }}</strong></div>
                                    <div><span>Taxa pagamento</span><strong>{{ formatMoney(paymentFeeAmount) }}</strong></div>
                                    <div class="total"><span>Total</span><strong>{{ formatMoney(orderTotal) }}</strong></div>
                                </article>

                                <button type="submit" class="sf-primary-btn" :disabled="checkoutForm.processing">
                                    {{ checkoutForm.processing ? 'Enviando pedido...' : 'Finalizar pedido' }}
                                </button>
                            </form>
                        </template>

                        <template v-else>
                            <form class="sf-form" @submit.prevent="submitServiceBooking">
                                <article class="sf-card">
                                    <h4>Servico selecionado</h4>
                                    <p v-if="serviceBookingCandidate">
                                        {{ serviceBookingCandidate.title }} - {{ formatMoney(serviceBookingCandidate.price) }}
                                    </p>
                                    <p v-else>Nenhum servico selecionado.</p>
                                </article>

                                <article class="sf-card">
                                    <h4>Agendamento</h4>
                                    <label class="field">
                                        <span>Data e horario</span>
                                        <input v-model="bookingForm.scheduled_for" type="datetime-local" required>
                                    </label>
                                    <label class="field">
                                        <span>Observacoes</span>
                                        <textarea v-model="bookingForm.notes" rows="4"></textarea>
                                    </label>
                                    <InputError :message="bookingForm.errors.service_catalog_id" />
                                    <InputError :message="bookingForm.errors.scheduled_for" />
                                    <InputError :message="bookingForm.errors.notes" />
                                    <InputError :message="bookingForm.errors.booking" />
                                </article>

                                <article class="sf-bill">
                                    <div><span>Valor estimado</span><strong>{{ formatMoney(serviceBookingCandidate?.price || 0) }}</strong></div>
                                    <div class="total"><span>Total</span><strong>{{ formatMoney(serviceBookingCandidate?.price || 0) }}</strong></div>
                                </article>

                                <button type="submit" class="sf-primary-btn" :disabled="bookingForm.processing">
                                    {{ bookingForm.processing ? 'Enviando agendamento...' : 'Confirmar agendamento' }}
                                </button>
                            </form>
                        </template>
                    </section>

                    <section v-else-if="activeScreen === 'orders'" class="sf-screen">
                        <div v-if="activeOrders.length" class="sf-orders">
                            <article v-for="order in activeOrders" :key="`${order.code}-${order.id}`" class="sf-order-card">
                                <div class="top">
                                    <strong>{{ order.code || `#${order.id}` }}</strong>
                                    <span class="tone" :class="toneClass(order.tone)">{{ order.label }}</span>
                                </div>
                                <p>{{ order.created_at }}</p>
                                <small>{{ order.payment_label }}</small>
                                <div class="bottom">
                                    <strong>{{ formatMoney(order.total) }}</strong>
                                    <button type="button">
                                        Detalhes
                                        <ChevronRight :size="14" />
                                    </button>
                                </div>
                            </article>
                        </div>
                        <div v-else class="sf-empty">
                            {{ isServicesMode ? 'Sem agendamentos ainda.' : 'Sem pedidos registrados.' }}
                        </div>
                    </section>

                    <section v-else-if="activeScreen === 'account'" class="sf-screen">
                        <template v-if="isAuthenticated">
                            <article class="sf-profile">
                                <div class="avatar">
                                    <img v-if="storeLogo" :src="storeLogo" :alt="storeName">
                                    <span v-else>{{ storeInitials }}</span>
                                </div>
                                <div>
                                    <strong>{{ customer?.name || 'Cliente' }}</strong>
                                    <p>{{ customer?.email || 'sem email' }}</p>
                                    <small>{{ hasVerifiedAccess ? 'Conta verificada' : 'Pendente de verificacao' }}</small>
                                </div>
                            </article>

                            <form class="sf-form" @submit.prevent="submitProfile">
                                <article class="sf-card">
                                    <h4>Dados de contato</h4>
                                    <label class="field">
                                        <span>Telefone</span>
                                        <input
                                            :value="profileForm.phone"
                                            type="text"
                                            inputmode="numeric"
                                            maxlength="15"
                                            @input="onProfilePhoneInput"
                                        >
                                    </label>
                                    <InputError :message="profileForm.errors.phone" />
                                </article>

                                <article class="sf-card">
                                    <h4>Endereco</h4>
                                    <label class="field">
                                        <span>CEP</span>
                                        <input
                                            :value="profileForm.cep"
                                            type="text"
                                            inputmode="numeric"
                                            maxlength="9"
                                            @input="onProfileCepInput"
                                        >
                                    </label>
                                    <div class="field-grid">
                                        <label class="field">
                                            <span>Logradouro</span>
                                            <input v-model="profileForm.street" type="text">
                                        </label>
                                        <label class="field">
                                            <span>Numero</span>
                                            <input v-model="profileForm.number" type="text">
                                        </label>
                                    </div>
                                    <label class="field">
                                        <span>Complemento</span>
                                        <input v-model="profileForm.complement" type="text">
                                    </label>
                                    <label class="field">
                                        <span>Bairro</span>
                                        <input v-model="profileForm.neighborhood" type="text">
                                    </label>
                                    <div class="field-grid">
                                        <label class="field">
                                            <span>Cidade</span>
                                            <input v-model="profileForm.city" type="text">
                                        </label>
                                        <label class="field">
                                            <span>UF</span>
                                            <select v-model="profileForm.state">
                                                <option
                                                    v-for="option in stateOptions"
                                                    :key="option.value || 'empty'"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </label>
                                    </div>
                                    <InputError :message="profileForm.errors.cep" />
                                    <InputError :message="profileForm.errors.street" />
                                    <InputError :message="profileForm.errors.number" />
                                    <InputError :message="profileForm.errors.neighborhood" />
                                    <InputError :message="profileForm.errors.city" />
                                    <InputError :message="profileForm.errors.state" />
                                </article>

                                <div class="sf-inline-actions">
                                    <button type="submit" class="sf-primary-btn" :disabled="profileForm.processing">
                                        {{ profileForm.processing ? 'Salvando...' : 'Salvar dados' }}
                                    </button>
                                    <button type="button" class="sf-secondary-btn" :disabled="logoutForm.processing" @click="submitLogout">
                                        <LogOut :size="16" />
                                        Sair da conta
                                    </button>
                                </div>
                            </form>
                        </template>

                        <div v-else class="sf-auth-box">
                            <p>Entre na sua conta para gerenciar perfil, endereco e pedidos.</p>
                            <div class="actions">
                                <Link :href="loginUrl">Entrar</Link>
                                <Link :href="registerUrl">Criar conta</Link>
                            </div>
                        </div>
                    </section>
                </section>

                <nav class="sf-bottom-nav">
                    <button
                        v-for="item in navItems"
                        :key="`bottom-${item.key}`"
                        type="button"
                        :class="{ active: activeScreen === item.key }"
                        @click="setScreen(item.key)"
                    >
                        <component :is="item.icon" :size="18" />
                        <span>{{ item.label }}</span>
                    </button>
                </nav>

                <button type="button" class="sf-fab" @click="setScreen('cart')">
                    <ShoppingCart :size="18" />
                    <span>{{ cartCount }}</span>
                </button>
            </main>

            <aside class="sf-right">
                <article class="sf-side-card">
                    <h4>Status da loja</h4>
                    <p>{{ storeAvailability.status_label || (storeAvailability.is_open_now ? 'Aberta agora' : 'Fechada') }}</p>
                    <small v-if="storeAvailability.message">{{ storeAvailability.message }}</small>
                    <small v-else-if="storeAvailability.next_open_label">{{ storeAvailability.next_open_label }}</small>
                </article>

                <article class="sf-side-card">
                    <h4>Resumo rapido</h4>
                    <p>{{ cartCount }} item(ns) no carrinho</p>
                    <strong>{{ formatMoney(orderTotal) }}</strong>
                    <button type="button" class="sf-side-btn" @click="setScreen('cart')">
                        <ShoppingCart :size="15" />
                        Abrir carrinho
                    </button>
                </article>

                <article class="sf-side-card">
                    <h4>Atalhos</h4>
                    <button type="button" class="sf-side-btn" @click="setScreen('checkout')">
                        <CreditCard :size="15" />
                        Checkout
                    </button>
                    <button type="button" class="sf-side-btn" @click="setScreen('orders')">
                        <Package :size="15" />
                        Pedidos
                    </button>
                    <button type="button" class="sf-side-btn" @click="setScreen('account')">
                        <Settings :size="15" />
                        Minha conta
                    </button>
                </article>

                <article class="sf-side-card">
                    <h4>Contato da loja</h4>
                    <p v-if="storePhone">{{ storePhone }}</p>
                    <small>Fluxo padronizado no layout mobile-first validado.</small>
                    <button
                        v-if="isAuthenticated"
                        type="button"
                        class="sf-side-btn"
                        :disabled="logoutForm.processing"
                        @click="submitLogout"
                    >
                        <LogOut :size="15" />
                        Sair
                    </button>
                    <Link v-else :href="loginUrl" class="sf-side-btn as-link">
                        <LogIn :size="15" />
                        Entrar
                    </Link>
                </article>
            </aside>
        </div>
    </div>
</template>
<style scoped>
.sf-app {
    min-height: 100vh;
    background:
        radial-gradient(circle at 8% 10%, var(--sf-accent-soft) 0%, transparent 38%),
        radial-gradient(circle at 92% 22%, rgba(255, 255, 255, 0.8) 0%, transparent 42%),
        #f1f5f9;
    color: var(--sf-text);
    font-family: 'Manrope', 'Segoe UI', sans-serif;
}

.sf-shell {
    min-height: 100vh;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
}

.sf-left,
.sf-right {
    display: none;
}

.sf-main {
    min-width: 0;
    position: relative;
    padding-bottom: 84px;
}

.sf-header__hero {
    position: relative;
    min-height: 232px;
    padding: 16px;
    overflow: hidden;
}

.sf-header__bg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sf-header__overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.22), rgba(15, 23, 42, 0.82));
}

.sf-header__top {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: space-between;
}

.sf-header__top-right {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.icon-btn {
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.95);
    color: #111827;
    display: grid;
    place-items: center;
    cursor: pointer;
    position: relative;
}

.desktop-hidden {
    display: grid;
}

.badge-dot {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 999px;
    background: var(--sf-accent);
    color: var(--sf-accent-contrast);
    font-size: 0.62rem;
    font-weight: 700;
    display: grid;
    place-items: center;
}

.sf-header__content {
    position: relative;
    z-index: 2;
    margin-top: 68px;
}

.sf-header__content small {
    color: rgba(255, 255, 255, 0.86);
    font-size: 0.78rem;
}

.sf-header__content h1 {
    margin: 8px 0 6px;
    color: #fff;
    font-size: 1.38rem;
    line-height: 1.15;
}

.sf-header__content p {
    margin: 0;
    color: rgba(255, 255, 255, 0.88);
    max-width: 420px;
    line-height: 1.35;
    font-size: 0.87rem;
}

.sf-content {
    margin-top: -18px;
    padding: 18px;
    border-radius: 22px 22px 0 0;
    background: var(--sf-surface);
    border-top: 1px solid var(--sf-border);
    min-height: calc(100vh - 220px);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.sf-alert {
    border-radius: 14px;
    border: 1px solid var(--sf-border);
    background: #fff;
    padding: 11px 12px;
    font-size: 0.83rem;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sf-alert p,
.sf-alert small {
    margin: 0;
}

.sf-alert.success {
    border-color: rgba(22, 163, 74, 0.32);
    background: rgba(236, 253, 245, 0.9);
    color: #166534;
}

.sf-alert.info {
    border-color: rgba(14, 165, 233, 0.3);
    background: rgba(240, 249, 255, 0.9);
    color: #075985;
}

.sf-alert.warning {
    border-color: rgba(245, 158, 11, 0.32);
    background: rgba(255, 251, 235, 0.94);
    color: #92400e;
}

.sf-alert.pix {
    border-color: var(--sf-accent-border);
    background: #fff;
}

.sf-alert a {
    color: inherit;
    font-weight: 700;
    text-decoration: none;
}

.sf-alert code {
    display: block;
    background: #f8fafc;
    border: 1px dashed var(--sf-border);
    border-radius: 10px;
    padding: 8px;
    font-size: 0.74rem;
    overflow-wrap: anywhere;
}

.sf-screen {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.sf-search {
    border: 1px solid var(--sf-border);
    border-radius: 14px;
    background: #fff;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
}

.sf-search input {
    border: 0;
    background: transparent;
    width: 100%;
    outline: none;
    color: var(--sf-text);
    font-size: 0.92rem;
}

.sf-chip-row {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 2px;
}

.sf-chip-row button {
    border: 1px solid var(--sf-border);
    border-radius: 999px;
    background: #fff;
    color: var(--sf-muted);
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
    padding: 8px 12px;
    cursor: pointer;
}

.sf-chip-row button.active {
    border-color: var(--sf-accent);
    color: var(--sf-accent);
    background: var(--sf-accent-soft);
}

.sf-title {
    margin: 2px 0 0;
    font-size: 1.02rem;
}

.sf-highlight-scroll {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(210px, 1fr);
    gap: 10px;
    overflow-x: auto;
}

.sf-highlight-card {
    position: relative;
    border-radius: 18px;
    overflow: hidden;
    min-height: 172px;
    border: 1px solid var(--sf-border);
    cursor: pointer;
}

.sf-highlight-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sf-highlight-card .tag {
    position: absolute;
    top: 10px;
    left: 10px;
    border-radius: 999px;
    padding: 4px 8px;
    font-size: 0.67rem;
    font-weight: 800;
    background: rgba(255, 255, 255, 0.95);
}

.sf-highlight-card .overlay {
    position: absolute;
    inset: auto 0 0;
    padding: 12px;
    background: linear-gradient(180deg, transparent, rgba(15, 23, 42, 0.86));
    color: #fff;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sf-highlight-card .overlay small {
    color: rgba(255, 255, 255, 0.86);
}

.sf-highlight-card .meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.72rem;
}

.sf-list,
.sf-catalog,
.sf-cart,
.sf-orders {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sf-list-item,
.sf-catalog-item,
.sf-cart-item {
    border: 1px solid var(--sf-border);
    border-radius: 16px;
    background: #fff;
    padding: 9px;
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr) auto;
    gap: 10px;
}

.sf-list-item img,
.sf-catalog-item img,
.sf-cart-item img {
    width: 76px;
    height: 76px;
    border-radius: 12px;
    object-fit: cover;
}

.sf-list-item .info,
.sf-catalog-item .content,
.sf-cart-item .content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 4px;
    min-width: 0;
}

.sf-list-item strong,
.sf-catalog-item strong,
.sf-cart-item strong {
    font-size: 0.95rem;
}

.sf-list-item p,
.sf-catalog-item p,
.sf-cart-item p {
    margin: 0;
    color: var(--sf-muted);
    font-size: 0.79rem;
    line-height: 1.35;
}

.sf-list-item .actions,
.sf-catalog-item .side,
.sf-cart-item .end {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: flex-end;
    gap: 6px;
}

.sf-list-item .price {
    font-size: 0.84rem;
    font-weight: 800;
}

.rating {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    color: #f59e0b;
    font-size: 0.77rem;
    font-weight: 700;
}

.rating small {
    color: var(--sf-muted);
    font-size: 0.72rem;
    font-weight: 500;
}

.sf-catalog-item {
    grid-template-columns: 90px minmax(0, 1fr) auto;
}

.sf-catalog-item img {
    width: 90px;
    height: 90px;
}

.price-row {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.price-row .new {
    font-weight: 800;
}

.price-row .old {
    color: var(--sf-muted);
    font-size: 0.77rem;
    text-decoration: line-through;
}

.sf-mini-btn,
.sf-mini-link {
    border: 1px solid var(--sf-accent);
    border-radius: 10px;
    background: #fff;
    color: var(--sf-accent);
    text-decoration: none;
    padding: 7px 10px;
    font-size: 0.74rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
}

.sf-empty {
    border: 1px dashed var(--sf-border);
    border-radius: 16px;
    background: #fff;
    text-align: center;
    color: var(--sf-muted);
    padding: 18px;
}

.sf-detail {
    border: 1px solid var(--sf-border);
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
}

.sf-detail img {
    width: 100%;
    height: 224px;
    object-fit: cover;
}

.sf-detail .body {
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 11px;
}

.sf-detail .top {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.sf-detail .top p {
    margin: 4px 0 0;
    color: var(--sf-muted);
}

.sf-detail .description {
    margin: 0;
    color: var(--sf-muted);
    line-height: 1.45;
}

.sf-detail .meta {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
}

.sf-detail .meta span {
    font-size: 1.18rem;
    font-weight: 800;
}

.sf-detail .meta small {
    color: var(--sf-muted);
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field span {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sf-muted);
    font-weight: 700;
}

.field input,
.field select,
.field textarea {
    border: 1px solid var(--sf-border);
    border-radius: 12px;
    background: #fff;
    color: var(--sf-text);
    font-size: 0.9rem;
    padding: 10px 11px;
    outline: none;
    width: 100%;
}

.field textarea {
    resize: vertical;
}

.stepper {
    border: 1px solid var(--sf-border);
    border-radius: 999px;
    width: fit-content;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 5px;
}

.stepper button {
    width: 28px;
    height: 28px;
    border: 0;
    border-radius: 999px;
    background: var(--sf-accent-soft);
    color: var(--sf-accent);
    display: grid;
    place-items: center;
    cursor: pointer;
}

.sf-primary-btn,
.sf-secondary-btn {
    border: 0;
    border-radius: 14px;
    padding: 12px 14px;
    cursor: pointer;
    font-weight: 800;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.sf-primary-btn {
    background: var(--sf-accent);
    color: var(--sf-accent-contrast);
}

.sf-secondary-btn {
    border: 1px solid var(--sf-border);
    background: #fff;
    color: var(--sf-text);
}

.sf-cart-item .qty {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.sf-cart-item .qty button {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    border: 1px solid var(--sf-border);
    background: #fff;
    display: grid;
    place-items: center;
    cursor: pointer;
}

.sf-cart-item .remove-btn {
    border: 0;
    background: transparent;
    color: #dc2626;
    font-size: 0.73rem;
    font-weight: 700;
    cursor: pointer;
}

.sf-bill {
    border: 1px solid var(--sf-border);
    border-radius: 16px;
    background: #fff;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sf-bill > div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
}

.sf-bill .total {
    border-top: 1px solid var(--sf-border);
    padding-top: 8px;
    margin-top: 4px;
    font-size: 0.94rem;
}

.sf-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.sf-card {
    border: 1px solid var(--sf-border);
    border-radius: 16px;
    background: #fff;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.sf-card h4 {
    margin: 0;
    font-size: 0.95rem;
}

.field-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.pill-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.pill-grid button,
.payment-grid button {
    border: 1px solid var(--sf-border);
    border-radius: 12px;
    background: #fff;
    color: var(--sf-muted);
    padding: 10px 8px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.pill-grid button.active,
.payment-grid button.active {
    border-color: var(--sf-accent);
    color: var(--sf-accent);
    background: var(--sf-accent-soft);
}

.pill-grid button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.payment-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.sf-order-card {
    border: 1px solid var(--sf-border);
    border-radius: 16px;
    background: #fff;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sf-order-card .top,
.sf-order-card .bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.sf-order-card p,
.sf-order-card small {
    margin: 0;
    color: var(--sf-muted);
}

.sf-order-card .bottom button {
    border: 0;
    border-radius: 999px;
    background: var(--sf-accent-soft);
    color: var(--sf-accent);
    padding: 5px 10px;
    font-size: 0.74rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.tone {
    border-radius: 999px;
    padding: 4px 8px;
    font-size: 0.7rem;
    font-weight: 800;
}

.tone-success {
    background: rgba(34, 197, 94, 0.14);
    color: #15803d;
}

.tone-warning {
    background: rgba(245, 158, 11, 0.16);
    color: #b45309;
}

.tone-info {
    background: rgba(14, 165, 233, 0.16);
    color: #0369a1;
}

.tone-danger {
    background: rgba(244, 63, 94, 0.14);
    color: #be123c;
}

.tone-default {
    background: rgba(100, 116, 139, 0.13);
    color: #475569;
}

.sf-profile {
    border: 1px solid var(--sf-border);
    border-radius: 16px;
    background: #fff;
    padding: 13px;
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr);
    gap: 10px;
    align-items: center;
}

.sf-profile .avatar {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    overflow: hidden;
    background: var(--sf-accent-soft);
    color: var(--sf-accent);
    display: grid;
    place-items: center;
    font-weight: 800;
}

.sf-profile .avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sf-profile p,
.sf-profile small {
    margin: 4px 0 0;
    color: var(--sf-muted);
}

.sf-inline-actions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.sf-auth-box {
    border: 1px solid var(--sf-border);
    border-radius: 16px;
    background: #fff;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    color: var(--sf-muted);
}

.sf-auth-box p,
.sf-auth-box small {
    margin: 0;
}

.sf-auth-box .actions {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 8px;
}

.sf-auth-box .actions a,
.sf-auth-box .actions button {
    border: 1px solid var(--sf-border);
    border-radius: 999px;
    background: #fff;
    color: var(--sf-text);
    text-decoration: none;
    font-size: 0.76rem;
    font-weight: 700;
    padding: 7px 12px;
    cursor: pointer;
}

.sf-bottom-nav {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 50;
    border-top: 1px solid var(--sf-border);
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(8px);
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    padding: 8px 8px calc(8px + env(safe-area-inset-bottom, 0px));
}

.sf-bottom-nav button {
    border: 0;
    background: transparent;
    display: grid;
    place-items: center;
    gap: 4px;
    color: var(--sf-muted);
    font-size: 0.67rem;
    cursor: pointer;
}

.sf-bottom-nav button.active {
    color: var(--sf-accent);
    font-weight: 800;
}

.sf-fab {
    position: fixed;
    right: 16px;
    bottom: calc(74px + env(safe-area-inset-bottom, 0px));
    z-index: 55;
    border: 0;
    border-radius: 999px;
    background: var(--sf-accent);
    color: var(--sf-accent-contrast);
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.24);
    cursor: pointer;
}

.sf-right {
    border-left: 1px solid var(--sf-border);
    background: #f8fafc;
    padding: 16px;
    flex-direction: column;
    gap: 12px;
}

.sf-side-card {
    border: 1px solid var(--sf-border);
    border-radius: 16px;
    background: #fff;
    padding: 13px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sf-side-card h4 {
    margin: 0;
}

.sf-side-card p,
.sf-side-card small {
    margin: 0;
    color: var(--sf-muted);
}

.sf-side-card strong {
    font-size: 1.1rem;
}

.sf-side-btn {
    border: 1px solid var(--sf-border);
    border-radius: 10px;
    background: #fff;
    color: var(--sf-muted);
    font-size: 0.82rem;
    font-weight: 700;
    padding: 9px 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    text-decoration: none;
}

.sf-side-btn.as-link {
    color: var(--sf-muted);
}

.sf-left {
    border-right: 1px solid var(--sf-border);
    background: #fff;
    padding: 16px;
    flex-direction: column;
    gap: 16px;
}

.sf-brand {
    display: grid;
    grid-template-columns: 46px minmax(0, 1fr);
    gap: 10px;
    align-items: center;
}

.sf-brand__logo {
    width: 46px;
    height: 46px;
    border-radius: 13px;
    background: var(--sf-accent-soft);
    color: var(--sf-accent);
    overflow: hidden;
    display: grid;
    place-items: center;
    font-weight: 800;
}

.sf-brand__logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sf-brand__meta strong {
    display: block;
    font-size: 0.9rem;
}

.sf-brand__meta small {
    color: var(--sf-muted);
    font-size: 0.72rem;
}

.sf-side-nav {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sf-side-nav button {
    border: 1px solid transparent;
    border-radius: 12px;
    background: transparent;
    color: var(--sf-muted);
    text-align: left;
    font-weight: 700;
    padding: 10px 11px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.sf-side-nav button.active {
    border-color: var(--sf-accent);
    color: var(--sf-accent);
    background: var(--sf-accent-soft);
}

.sf-side-extra {
    margin-top: auto;
}

.sf-side-link {
    width: 100%;
    border: 1px solid var(--sf-border);
    border-radius: 12px;
    background: #fff;
    color: var(--sf-muted);
    text-decoration: none;
    font-weight: 700;
    padding: 10px 11px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    cursor: pointer;
}

.fill {
    fill: currentColor;
    color: var(--sf-accent);
}

@media (min-width: 1024px) {
    .sf-shell {
        grid-template-columns: 240px minmax(0, 1fr) 300px;
    }

    .sf-left,
    .sf-right {
        display: flex;
    }

    .sf-main {
        padding-bottom: 0;
        min-height: 100vh;
    }

    .desktop-hidden {
        display: none;
    }

    .sf-content {
        margin-top: -14px;
        min-height: calc(100vh - 236px);
        border-radius: 18px 18px 0 0;
    }

    .sf-bottom-nav,
    .sf-fab {
        display: none;
    }

    .sf-inline-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1400px) {
    .sf-shell {
        grid-template-columns: 260px minmax(0, 1fr) 320px;
    }
}

@media (max-width: 460px) {
    .sf-content {
        padding: 14px;
    }

    .sf-header__content h1 {
        font-size: 1.28rem;
    }

    .field-grid,
    .payment-grid {
        grid-template-columns: 1fr;
    }
}
</style>

