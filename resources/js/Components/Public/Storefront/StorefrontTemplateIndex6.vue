<script setup>
/* eslint-disable vue/prop-name-casing */
import InputError from '@/Components/InputError.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { BRAZIL_STATES, formatCepBR, formatPhoneBR, normalizeStateCode } from '@/utils/br';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';
import {
    ArrowLeft,
    Bell,
    Check,
    ChevronRight,
    Copy,
    Heart,
    Home,
    LayoutGrid,
    Loader2,
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
    shop_account: { type: Object, default: () => ({ orders: [], notifications: [], notifications_unread_count: 0 }) },
    bookings: { type: Array, default: () => [] },
});

const page = usePage();
let cartToastTimeout = null;
let cartAttentionTimeout = null;
const itemActionFeedbackTimers = new Map();
let alertToastTimeout = null;

const toInt = (value, fallback = 0) => {
    const parsed = Number.parseInt(String(value ?? ''), 10);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const toMoney = (value, fallback = 0) => {
    const parsed = Number.parseFloat(String(value ?? ''));
    return Number.isFinite(parsed) ? parsed : fallback;
};

const normalizeCityKey = (value) => {
    const base = String(value ?? '').trim().toLowerCase();
    if (base === '') return '';

    return base
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
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
const storeTheme = computed(() => {
    const theme = props.storefront?.theme ?? {};
    const menuButtonColor = normalizeHex(theme.menu_button_color || props.contractor?.primary_color, '#FF5C35');
    return {
        menuButtonColor,
        cartButtonColor: normalizeHex(theme.cart_button_color, '#F58D1D'),
        favoriteButtonColor: normalizeHex(theme.favorite_button_color, '#FF3B30'),
        addButtonColor: normalizeHex(theme.add_button_color, '#F59E0B'),
    };
});
const storeAccent = computed(() => storeTheme.value.menuButtonColor);
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
    '--idx-menu-button': storeTheme.value.menuButtonColor,
    '--idx-menu-button-soft': withAlpha(storeTheme.value.menuButtonColor, 0.14),
    '--idx-cart-button': storeTheme.value.cartButtonColor,
    '--idx-favorite-button': storeTheme.value.favoriteButtonColor,
    '--idx-add-button': storeTheme.value.addButtonColor,
}));

const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const registerUrl = computed(() => `/shop/${storeSlug.value}/cadastro`);
const verifyEmailUrl = computed(() => `/shop/${storeSlug.value}/verificar-email`);
const logoutUrl = computed(() => `/shop/${storeSlug.value}/sair`);
const checkoutUrl = computed(() => `/shop/${storeSlug.value}/checkout`);
const serviceBookUrl = computed(() => `/shop/${storeSlug.value}/servicos/agendar`);
const accountUpdateUrl = computed(() => `/shop/${storeSlug.value}/conta`);
const accountPasswordUpdateUrl = computed(() => `/shop/${storeSlug.value}/conta/senha`);
const accountNotificationsReadUrl = computed(() => `/shop/${storeSlug.value}/conta/notificacoes/ler`);
const favoriteUrl = (id) => `/shop/${storeSlug.value}/favoritos/${id}`;

const isAuthenticated = computed(() => Boolean(props.shop_auth?.authenticated));
const requiresEmailVerification = computed(() => Boolean(props.shop_auth?.requires_email_verification ?? true));
const isEmailVerified = computed(() => Boolean(props.shop_auth?.email_verified ?? false));
const hasVerifiedAccess = computed(() => !requiresEmailVerification.value || isEmailVerified.value);
const isAddressComplete = computed(() => Boolean(props.shop_auth?.address_complete ?? false));
const customer = computed(() => props.shop_auth?.customer ?? null);

const flashStatus = computed(() => String(page.props?.flash?.status ?? '').trim());
const checkoutPayment = computed(() => page.props?.flash?.checkout_payment ?? null);
const checkoutLivePayment = ref(null);
const checkoutManual = computed(() => page.props?.flash?.checkout_manual ?? null);
const bookingWhatsappUrl = computed(() => String(page.props?.flash?.service_booking_whatsapp_url ?? '').trim());
const checkoutPixRefreshLoading = ref(false);
const checkoutPixRefreshError = ref('');
const checkoutPixAutoRefreshAttempts = ref(0);
const checkoutPixAutoRefreshTimer = ref(null);
const checkoutEffectivePayment = computed(() => checkoutLivePayment.value ?? checkoutPayment.value);
const integratedPaymentCodes = ['pix', 'credit_card', 'debit_card', 'boleto'];
const actionableSaleStatuses = ['new', 'pending_confirmation', 'confirmed', 'awaiting_payment'];
const actionablePaymentStatuses = ['pending', 'authorized'];
const orderPaymentAutoRefreshTimer = ref(null);
const checkoutPixCodeCopied = ref(false);
const orderPaymentOverrides = ref({});
const orderPaymentRefreshLoadingById = ref({});
const orderPaymentRefreshErrorById = ref({});
const orderPaymentQrById = ref({});
const orderPixCodeCopiedById = ref({});

const parseDateTime = (value) => {
    const raw = String(value ?? '').trim();
    if (raw === '') return null;
    const parsed = new Date(raw);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const coalesceNonEmptyString = (...values) => {
    for (const value of values) {
        const safe = String(value ?? '').trim();
        if (safe !== '') return safe;
    }

    return '';
};

const paymentMethodLabelFromCode = (code, fallback = 'pagamento') => {
    const safeCode = String(code ?? '').trim().toLowerCase();
    if (safeCode === 'credit_card') return 'cartão de crédito';
    if (safeCode === 'debit_card') return 'cartão de débito';
    if (safeCode === 'boleto') return 'boleto';
    if (safeCode === 'pix') return 'Pix';
    return fallback;
};

const hasVisibleQrData = (payment) => (
    String(payment?.qr_code ?? '').trim() !== ''
    || String(payment?.qr_code_base64 ?? '').trim() !== ''
);

const isPixPayload = (payment) => {
    if (!payment || typeof payment !== 'object') return false;
    if (payment?.is_pix) return true;
    if (hasVisibleQrData(payment)) return true;

    const providerCode = String(payment?.provider ?? '').trim().toLowerCase();
    const methodCode = String(payment?.payment_method_code ?? payment?.method_code ?? '').trim().toLowerCase();
    return providerCode === 'mercado_pago' && methodCode.includes('pix');
};

const isIntegratedPayload = (payment) => {
    if (!payment || typeof payment !== 'object') return false;
    if (payment?.is_integrated) return true;

    const providerCode = String(payment?.provider ?? '').trim().toLowerCase();
    if (providerCode !== 'mercado_pago') return false;
    if (isPixPayload(payment)) return true;
    if (String(payment?.checkout_url ?? '').trim() !== '') return true;

    const transactionReference = String(payment?.transaction_reference ?? '').trim();
    const methodCode = String(payment?.payment_method_code ?? payment?.method_code ?? '').trim().toLowerCase();
    return transactionReference !== '' && integratedPaymentCodes.includes(methodCode);
};

const resolvePaymentExpirationDate = (payment) => (
    parseDateTime(payment?.reservation_expires_at ?? payment?.expires_at)
);

const mergePaymentPayload = (previousPayment, nextPayment) => {
    if (!nextPayment || typeof nextPayment !== 'object') {
        return previousPayment && typeof previousPayment === 'object'
            ? { ...previousPayment }
            : null;
    }

    const previous = previousPayment && typeof previousPayment === 'object'
        ? previousPayment
        : {};
    const merged = {
        ...previous,
        ...nextPayment,
    };

    merged.transaction_reference = coalesceNonEmptyString(
        nextPayment?.transaction_reference,
        previous?.transaction_reference,
    );
    merged.provider = coalesceNonEmptyString(
        nextPayment?.provider,
        previous?.provider,
    );
    merged.payment_method_code = coalesceNonEmptyString(
        nextPayment?.payment_method_code,
        previous?.payment_method_code,
    );
    merged.method_code = coalesceNonEmptyString(
        nextPayment?.method_code,
        previous?.method_code,
        merged.payment_method_code,
    );
    merged.payment_method_name = coalesceNonEmptyString(
        nextPayment?.payment_method_name,
        previous?.payment_method_name,
    );
    merged.method_name = coalesceNonEmptyString(
        nextPayment?.method_name,
        previous?.method_name,
        merged.payment_method_name,
    );
    merged.ticket_url = coalesceNonEmptyString(
        nextPayment?.ticket_url,
        previous?.ticket_url,
    );
    merged.checkout_url = coalesceNonEmptyString(
        nextPayment?.checkout_url,
        previous?.checkout_url,
    );
    merged.qr_code = coalesceNonEmptyString(
        nextPayment?.qr_code,
        previous?.qr_code,
    );
    merged.qr_code_base64 = coalesceNonEmptyString(
        nextPayment?.qr_code_base64,
        previous?.qr_code_base64,
    );
    merged.expires_at = coalesceNonEmptyString(
        nextPayment?.expires_at,
        previous?.expires_at,
    );
    merged.reservation_expires_at = coalesceNonEmptyString(
        nextPayment?.reservation_expires_at,
        previous?.reservation_expires_at,
    );
    merged.is_pix = nextPayment?.is_pix ?? previous?.is_pix ?? false;
    merged.is_integrated = nextPayment?.is_integrated ?? previous?.is_integrated ?? false;

    return merged;
};

const isActionableIntegratedPayment = (payment, saleStatusValue = '') => {
    if (!payment || typeof payment !== 'object') return false;

    const paymentStatus = String(payment?.status ?? payment?.payment_status ?? '').trim().toLowerCase();
    if (!actionablePaymentStatuses.includes(paymentStatus)) return false;

    const saleStatus = String(saleStatusValue ?? '').trim().toLowerCase();
    if (saleStatus !== '' && !actionableSaleStatuses.includes(saleStatus)) return false;

    const expiresAt = resolvePaymentExpirationDate(payment);
    if (expiresAt && expiresAt.getTime() <= Date.now()) return false;

    return true;
};

const resolveIntegratedActionUrlFromPayload = (payment) => {
    const checkoutUrlRaw = String(payment?.checkout_url ?? '').trim();
    if (checkoutUrlRaw !== '') return checkoutUrlRaw;
    if (isPixPayload(payment)) return String(payment?.ticket_url ?? '').trim();
    return '';
};

const normalizeQrImageBase64 = (value) => {
    const safe = String(value ?? '').trim();
    if (safe === '') return '';
    return safe.startsWith('data:') ? safe : `data:image/png;base64,${safe}`;
};

const checkoutMethodCode = computed(() => String(checkoutEffectivePayment.value?.payment_method_code ?? '').trim().toLowerCase());
const checkoutTicketUrl = computed(() => String(checkoutEffectivePayment.value?.ticket_url ?? '').trim());
const checkoutHasVisibleQrData = computed(() => hasVisibleQrData(checkoutEffectivePayment.value));
const checkoutHasPixPayload = computed(() => isPixPayload(checkoutEffectivePayment.value));
const checkoutIsIntegratedPayload = computed(() => isIntegratedPayload(checkoutEffectivePayment.value));
const checkoutIntegratedActionUrl = computed(() => resolveIntegratedActionUrlFromPayload(checkoutEffectivePayment.value));
const checkoutIntegratedMethodLabel = computed(() => {
    const raw = String(checkoutEffectivePayment.value?.payment_method_name ?? checkoutEffectivePayment.value?.method_name ?? '').trim();
    if (raw !== '') return raw;
    return paymentMethodLabelFromCode(checkoutMethodCode.value);
});
const checkoutPaymentStatusUrl = computed(() => {
    const saleId = toInt(checkoutEffectivePayment.value?.sale_id, 0);
    return saleId > 0 ? `/shop/${storeSlug.value}/checkout/pagamento/${saleId}` : null;
});
const checkoutHasVisiblePixData = computed(() => (
    checkoutHasVisibleQrData.value || checkoutTicketUrl.value !== ''
));
const checkoutPixCode = computed(() => String(checkoutEffectivePayment.value?.qr_code ?? '').trim());
const checkoutPixQrImageSrc = ref('');
const refreshCheckoutPixPayment = async () => {
    const url = String(checkoutPaymentStatusUrl.value ?? '').trim();
    if (url === '') {
        return;
    }

    checkoutPixRefreshLoading.value = true;
    checkoutPixRefreshError.value = '';

    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('not_found');
        }

        const data = await response.json();
        const payment = data?.payment;

        if (payment && typeof payment === 'object') {
            checkoutLivePayment.value = mergePaymentPayload(checkoutLivePayment.value, payment);
        }
    } catch {
        checkoutPixRefreshError.value = 'Não foi possível atualizar o status do pagamento agora.';
    } finally {
        checkoutPixRefreshLoading.value = false;
    }
};

const ensureCheckoutPixPayload = async () => {
    if (!checkoutHasPixPayload.value || checkoutHasVisiblePixData.value) {
        return;
    }

    await refreshCheckoutPixPayment();
};

const stopCheckoutPixAutoRefresh = () => {
    if (checkoutPixAutoRefreshTimer.value) {
        clearTimeout(checkoutPixAutoRefreshTimer.value);
        checkoutPixAutoRefreshTimer.value = null;
    }
};

const scheduleCheckoutPixAutoRefresh = () => {
    if (!checkoutHasPixPayload.value || checkoutHasVisiblePixData.value || checkoutPixRefreshLoading.value) {
        return;
    }

    if (checkoutPixAutoRefreshAttempts.value >= 6) {
        return;
    }

    stopCheckoutPixAutoRefresh();
    checkoutPixAutoRefreshTimer.value = setTimeout(async () => {
        checkoutPixAutoRefreshAttempts.value += 1;
        await refreshCheckoutPixPayment();

        if (!checkoutHasVisiblePixData.value) {
            scheduleCheckoutPixAutoRefresh();
        }
    }, 3000);
};

const resolveCheckoutPixQrImage = async () => {
    const base64 = String(checkoutEffectivePayment.value?.qr_code_base64 ?? '').trim();
    if (base64 !== '') {
        checkoutPixQrImageSrc.value = normalizeQrImageBase64(base64);
        return;
    }

    const qrCode = String(checkoutEffectivePayment.value?.qr_code ?? '').trim();
    if (qrCode === '') {
        checkoutPixQrImageSrc.value = '';
        return;
    }

    try {
        checkoutPixQrImageSrc.value = await QRCode.toDataURL(qrCode, {
            width: 288,
            margin: 1,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });
    } catch {
        checkoutPixQrImageSrc.value = '';
    }
};

const copyTextToClipboard = async (value) => {
    const text = String(value ?? '').trim();
    if (text === '') return false;

    if (navigator?.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // fallback below
        }
    }

    try {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        return true;
    } catch {
        return false;
    }
};

const copyCheckoutPixCode = async () => {
    const copied = await copyTextToClipboard(checkoutPixCode.value);
    if (!copied) return;

    checkoutPixCodeCopied.value = true;
    setTimeout(() => {
        checkoutPixCodeCopied.value = false;
    }, 1800);
};

watch(checkoutPayment, () => {
    const flashPayment = checkoutPayment.value && typeof checkoutPayment.value === 'object'
        ? checkoutPayment.value
        : null;
    checkoutLivePayment.value = flashPayment
        ? mergePaymentPayload(checkoutLivePayment.value, flashPayment)
        : null;
    checkoutPixRefreshError.value = '';
    checkoutPixCodeCopied.value = false;
    checkoutPixAutoRefreshAttempts.value = 0;
    stopCheckoutPixAutoRefresh();
    void ensureCheckoutPixPayload();
    scheduleCheckoutPixAutoRefresh();
    void resolveCheckoutPixQrImage();
}, { immediate: true, deep: true });

watch(checkoutLivePayment, () => {
    if (checkoutHasVisiblePixData.value) {
        stopCheckoutPixAutoRefresh();
    } else {
        scheduleCheckoutPixAutoRefresh();
    }
    void resolveCheckoutPixQrImage();
}, { deep: true });

onBeforeUnmount(() => {
    stopCheckoutPixAutoRefresh();
    if (orderPaymentAutoRefreshTimer.value) {
        clearInterval(orderPaymentAutoRefreshTimer.value);
        orderPaymentAutoRefreshTimer.value = null;
    }
    if (cartToastTimeout) {
        clearTimeout(cartToastTimeout);
        cartToastTimeout = null;
    }
    if (cartAttentionTimeout) {
        clearTimeout(cartAttentionTimeout);
        cartAttentionTimeout = null;
    }
    if (alertToastTimeout) {
        clearTimeout(alertToastTimeout);
        alertToastTimeout = null;
    }
    itemActionFeedbackTimers.forEach((timers) => {
        if (!Array.isArray(timers)) return;
        timers.forEach((timer) => clearTimeout(timer));
    });
    itemActionFeedbackTimers.clear();
});

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
            .map((service) => {
                const primaryImage = String(service?.image_url || '').trim();
                const images = [primaryImage].filter((image) => image !== '');

                return {
                    id: toInt(service?.id, 0),
                    categoryId: toInt(service?.service_category_id, 0),
                    title: String(service?.name || 'Serviço'),
                    subtitle: String(service?.category_name || service?.code || 'Atendimento'),
                    description: String(service?.description || 'Sem descrição informada.'),
                    price: toMoney(service?.base_price, 0),
                    image: primaryImage || fallbackImage.value,
                    images: images.length ? images : [fallbackImage.value],
                    badge: String(service?.coupon_label || 'Destaque'),
                    rating: toMoney(service?.rating, 5),
                    reviews: 200 + (toInt(service?.id, 1) % 7) * 48,
                    durationLabel: String(service?.duration_label || '60 min'),
                    durationMinutes: Math.max(15, toInt(service?.duration_minutes, 60)),
                };
            })
            .filter((item) => item.id > 0);
    }

    const safe = Array.isArray(props.products) ? props.products : [];
    return safe
        .map((product) => {
            const id = toInt(product?.id, 0);
            const imageList = Array.isArray(product?.images)
                ? product.images
                    .map((row) => String(row?.image_url || '').trim())
                    .filter((image) => image !== '')
                : [];
            const firstImage = imageList[0] || String(product?.image_url || '').trim();

            return {
                id,
                categoryId: toInt(product?.category_id, toInt(product?.category_parent_id, 0)),
                title: String(product?.name || 'Produto'),
                subtitle: String(product?.category_name || product?.sku || 'Produto'),
                description: String(product?.description || 'Sem descrição informada.'),
                price: toMoney(product?.sale_price, 0),
                image: firstImage || fallbackImage.value,
                images: imageList.length ? imageList : [firstImage || fallbackImage.value],
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
const cartToast = ref({ visible: false, title: '', description: '' });
const cartAttention = ref(false);
const actionLoadingByItem = ref({});
const actionSuccessByItem = ref({});
const isDetailsOpen = ref(false);
const detailsImageIndex = ref(0);
const detailsVariationId = ref(null);
const detailsQuantity = ref(1);
const showNotificationsPanel = ref(false);
const alertToast = ref({ visible: false, message: '', tone: 'info' });

const showAlertToast = (message, tone = 'info', duration = 3200) => {
    const safeMessage = String(message ?? '').trim();
    if (safeMessage === '') return;

    alertToast.value = {
        visible: true,
        message: safeMessage,
        tone: ['success', 'warning', 'error', 'info'].includes(tone) ? tone : 'info',
    };

    if (alertToastTimeout) {
        clearTimeout(alertToastTimeout);
    }

    alertToastTimeout = setTimeout(() => {
        alertToast.value.visible = false;
        alertToastTimeout = null;
    }, Math.max(1400, toInt(duration, 3200)));
};

watch(flashStatus, (message) => {
    const safeMessage = String(message ?? '').trim();
    if (safeMessage === '') return;
    showAlertToast(safeMessage, 'success');
}, { immediate: true });

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

const selectedItemImages = computed(() => {
    if (!selectedItem.value) return [fallbackImage.value];
    const images = Array.isArray(selectedItem.value.images)
        ? selectedItem.value.images
            .map((image) => String(image || '').trim())
            .filter((image) => image !== '')
        : [];

    if (images.length) return images;
    return [String(selectedItem.value.image || '').trim() || fallbackImage.value];
});

const detailsVariationOptions = computed(() => {
    if (!selectedItem.value || !Array.isArray(selectedItem.value.variations)) return [];

    return selectedItem.value.variations
        .map((variation) => ({
            value: variation.id,
            label: `${variation.name} - ${formatMoney(variation.price)}`,
        }))
        .filter((variation) => toInt(variation.value, 0) > 0);
});

const detailsUnitPrice = computed(() => {
    if (!selectedItem.value) return 0;
    return resolveVariationPrice(selectedItem.value, toInt(detailsVariationId.value, 0) || null);
});

const detailsLineTotal = computed(() => Number((detailsUnitPrice.value * Math.max(1, toInt(detailsQuantity.value, 1))).toFixed(2)));
const detailsPrimaryActionLabel = computed(() => (
    isServicesMode.value ? 'Selecionar para agendar' : 'Adicionar ao carrinho'
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
            backgroundColor: normalizeHex(String(banner?.background_color || ''), storeAccent.value),
            useOriginalImageColors: Boolean(banner?.use_original_image_colors ?? false),
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
    backgroundColor: storeAccent.value,
    useOriginalImageColors: false,
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

const isItemActionLoading = (itemId) => Boolean(actionLoadingByItem.value[toInt(itemId, 0)]);
const isItemActionSuccess = (itemId) => Boolean(actionSuccessByItem.value[toInt(itemId, 0)]);

const triggerCartAttention = () => {
    cartAttention.value = true;
    if (cartAttentionTimeout) {
        clearTimeout(cartAttentionTimeout);
    }

    cartAttentionTimeout = setTimeout(() => {
        cartAttention.value = false;
        cartAttentionTimeout = null;
    }, 950);
};

const triggerAddToCartFeedback = (itemId) => {
    const id = toInt(itemId, 0);
    if (id <= 0) return;

    const currentTimers = itemActionFeedbackTimers.get(id);
    if (Array.isArray(currentTimers)) {
        currentTimers.forEach((timer) => clearTimeout(timer));
    }

    actionLoadingByItem.value = {
        ...actionLoadingByItem.value,
        [id]: true,
    };
    actionSuccessByItem.value = {
        ...actionSuccessByItem.value,
        [id]: false,
    };

    const loadingTimer = setTimeout(() => {
        actionLoadingByItem.value = {
            ...actionLoadingByItem.value,
            [id]: false,
        };
        actionSuccessByItem.value = {
            ...actionSuccessByItem.value,
            [id]: true,
        };
    }, 320);

    const successTimer = setTimeout(() => {
        actionSuccessByItem.value = {
            ...actionSuccessByItem.value,
            [id]: false,
        };
    }, 1900);

    itemActionFeedbackTimers.set(id, [loadingTimer, successTimer]);
};

const resolveItemMaxStock = (item, variationId = null) => {
    if (!item || isServicesMode.value) return 1;

    const safeVariationId = toInt(variationId, 0);
    if (safeVariationId > 0 && Array.isArray(item.variations) && item.variations.length) {
        const selectedVariation = item.variations.find((variation) => toInt(variation?.id, 0) === safeVariationId);
        return Math.max(0, toInt(selectedVariation?.stock, 0));
    }

    return Math.max(0, toInt(item.stock, 0));
};

const addToCart = (item, options = 1) => {
    if (!item) return;

    const id = toInt(item.id, 0);
    if (id <= 0) return;
    const payload = typeof options === 'object' && options !== null && !Array.isArray(options)
        ? options
        : { quantity: options };
    const quantity = Math.max(1, toInt(payload.quantity, 1));
    const explicitVariationId = payload.variationId !== undefined && payload.variationId !== null
        ? toInt(payload.variationId, 0)
        : null;

    if (isServicesMode.value) {
        triggerAddToCartFeedback(id);
        triggerCartAttention();
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
    const selectedVariationId = explicitVariationId && explicitVariationId > 0
        ? explicitVariationId
        : (variationId && variationId > 0 ? variationId : current.variation_id);
    const maxStock = resolveItemMaxStock(item, selectedVariationId);
    if (maxStock <= 0) {
        showAlertToast('Produto sem estoque disponível no momento.', 'warning');
        return;
    }

    const nextQuantity = Math.max(1, toInt(current.quantity, 0) + quantity);
    const safeQuantity = Math.min(maxStock, nextQuantity);
    if (safeQuantity <= toInt(current.quantity, 0)) {
        showAlertToast('Quantidade máxima disponível no estoque atingida.', 'warning');
        return;
    }

    triggerAddToCartFeedback(id);
    triggerCartAttention();

    cart.value = {
        ...cart.value,
        [id]: {
            quantity: safeQuantity,
            variation_id: selectedVariationId && selectedVariationId > 0 ? selectedVariationId : null,
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

    const item = normalizedCatalog.value.find((entry) => entry.id === id);
    if (!item) return;

    const selectedVariationId = toInt(cart.value[id]?.variation_id, 0);
    const maxStock = resolveItemMaxStock(item, selectedVariationId);
    const currentQty = Math.max(1, toInt(cart.value[id].quantity, 1));
    if (maxStock <= currentQty) {
        showAlertToast('Quantidade máxima disponível no estoque atingida.', 'warning');
        return;
    }

    cart.value = {
        ...cart.value,
        [id]: {
            ...cart.value[id],
            quantity: currentQty + 1,
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

        const variationId = row?.variation_id ? toInt(row.variation_id, 0) : null;
        const maxStock = resolveItemMaxStock(item, variationId);
        if (!isServicesMode.value && maxStock <= 0) return null;

        const quantityValue = isServicesMode.value
            ? 1
            : Math.max(1, Math.min(toInt(row?.quantity, 1), maxStock));
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
                code: String(method?.code || '').trim().toLowerCase(),
                checkoutMode: String(method?.checkout_mode || 'manual').trim().toLowerCase(),
                feeFixed: toMoney(method?.fee_fixed, 0),
                feePercent: toMoney(method?.fee_percent, 0),
            }))
            .filter((method) => method.id > 0)
        : []
));

const paymentMethodSelectOptions = computed(() =>
    paymentMethods.value.map((method) => {
        const feeText = method.feePercent > 0 || method.feeFixed > 0
            ? ` (+ ${method.feePercent.toFixed(2)}% / ${formatMoney(method.feeFixed)})`
            : '';
        const modeText = method.checkoutMode === 'integrated' ? ' - gateway' : ' - manual';

        return {
            value: method.id,
            label: `${method.name}${feeText}${modeText}`,
        };
    }));

const selectedPaymentMethod = computed(() => {
    const selectedId = toInt(checkoutForm.payment_method_id, 0);
    if (selectedId <= 0) return null;
    return paymentMethods.value.find((method) => method.id === selectedId) ?? null;
});

const shippingConfig = computed(() => {
    const stateRatesFromConfig = Array.isArray(props.shipping_config?.state_rates)
        ? props.shipping_config.state_rates
            .filter((row) => row && (row.active ?? false))
            .map((row) => ({
                state: normalizeStateCode(String(row.state ?? '')),
                fee: Math.max(0, toMoney(row.fee, 0)),
                freeOver: Math.max(0, toMoney(row.free_over, 0)),
                active: Boolean(row?.active ?? false),
            }))
            .filter((row) => row.state !== '')
        : [];

    if (!stateRatesFromConfig.length && Boolean(props.shipping_config?.statewide_enabled ?? false)) {
        const legacyState = normalizeStateCode(String(props.shipping_config?.statewide_state ?? ''));
        if (legacyState !== '') {
            stateRatesFromConfig.push({
                state: legacyState,
                fee: Math.max(0, toMoney(props.shipping_config?.statewide_fee, 0)),
                freeOver: Math.max(0, toMoney(props.shipping_config?.statewide_free_over, 0)),
                active: true,
            });
        }
    }

    return {
        deliveryEnabled: Boolean(props.shipping_config?.delivery_enabled ?? true),
        pickupEnabled: Boolean(props.shipping_config?.pickup_enabled ?? true),
        deliveryCoverageEnabled: Boolean(props.shipping_config?.delivery_coverage_enabled ?? false),
        nationwideEnabled: Boolean(props.shipping_config?.nationwide_enabled ?? false),
        nationwideFee: Math.max(0, toMoney(props.shipping_config?.nationwide_fee, 0)),
        nationwideFreeOver: Math.max(0, toMoney(props.shipping_config?.nationwide_free_over, 0)),
        estimatedDays: Math.max(1, toInt(props.shipping_config?.estimated_days, 2)),
        stateRates: stateRatesFromConfig,
        cityRates: Array.isArray(props.shipping_config?.city_rates)
            ? props.shipping_config.city_rates
                .filter((row) => row && (row.active ?? true))
                .map((row) => ({
                    city: String(row.city ?? '').trim(),
                    cityKey: normalizeCityKey(row.city_key ?? row.city ?? ''),
                    state: normalizeStateCode(String(row.state ?? '')),
                    fee: Math.max(0, toMoney(row.fee, 0)),
                    freeOver: Math.max(0, toMoney(row.free_over, 0)),
                    isFree: Boolean(row?.is_free ?? false),
                    estimatedDays: Math.max(1, toInt(row.estimated_days, 2)),
                }))
                .filter((row) => row.cityKey !== '')
            : [],
    };
});

const checkoutShippingCityKey = computed(() => normalizeCityKey(checkoutForm.shipping_city));
const checkoutShippingStateCode = computed(() => normalizeStateCode(checkoutForm.shipping_state));
const selectedShippingCityRate = computed(() => {
    const cityRates = shippingConfig.value.cityRates;
    if (!cityRates.length) return null;

    const cityKey = checkoutShippingCityKey.value;
    if (cityKey === '') return null;
    const state = checkoutShippingStateCode.value;

    return cityRates.find((row) => row.cityKey === cityKey && row.state !== '' && row.state === state) ?? null;
});

const selectedShippingStateRate = computed(() => {
    const state = checkoutShippingStateCode.value;
    if (state === '') return null;

    return shippingConfig.value.stateRates.find((row) => row.state === state && row.active) ?? null;
});

const deliveryRule = computed(() => {
    if (!shippingConfig.value.deliveryEnabled) return null;

    if (shippingConfig.value.nationwideEnabled) {
        return {
            fee: shippingConfig.value.nationwideFee,
            freeOver: shippingConfig.value.nationwideFreeOver,
            alwaysFree: shippingConfig.value.nationwideFreeOver <= 0,
            estimatedDays: shippingConfig.value.estimatedDays,
            coverageLabel: 'nacional',
        };
    }

    if (shippingConfig.value.stateRates.length > 0) {
        if (!selectedShippingStateRate.value) {
            return null;
        }

        return {
            fee: selectedShippingStateRate.value.fee,
            freeOver: selectedShippingStateRate.value.freeOver,
            alwaysFree: selectedShippingStateRate.value.freeOver <= 0,
            estimatedDays: shippingConfig.value.estimatedDays,
            coverageLabel: 'estado',
        };
    }

    if (!shippingConfig.value.cityRates.length) {
        return null;
    }

    if (!selectedShippingCityRate.value) {
        return null;
    }

    return {
        fee: selectedShippingCityRate.value.fee,
        freeOver: selectedShippingCityRate.value.freeOver,
        alwaysFree: Boolean(selectedShippingCityRate.value.isFree),
        estimatedDays: selectedShippingCityRate.value.estimatedDays,
        coverageLabel: 'cidade',
    };
});

const deliveryOptionBlockedByCity = computed(() =>
    shippingConfig.value.deliveryEnabled
    && !deliveryRule.value
    && (
        shippingConfig.value.nationwideEnabled
        || shippingConfig.value.stateRates.length > 0
        || shippingConfig.value.cityRates.length > 0
    ));

const isDeliveryOptionAvailable = computed(() => {
    if (!shippingConfig.value.deliveryEnabled) return false;
    return Boolean(deliveryRule.value);
});

const deliveryModeOptions = computed(() => {
    const options = [];
    if (shippingConfig.value.pickupEnabled) {
        options.push({ value: 'pickup', label: 'Retirar na loja' });
    }
    if (isDeliveryOptionAvailable.value) {
        options.push({ value: 'delivery', label: 'Entrega' });
    }
    return options;
});

const hasCheckoutDeliveryMode = computed(() => deliveryModeOptions.value.length > 0);
const isDeliveryModeSelected = computed(() => String(checkoutForm.delivery_mode ?? '').trim() === 'delivery');
const isCheckoutDeliveryModeValid = computed(() =>
    deliveryModeOptions.value.some((option) => option.value === checkoutForm.delivery_mode));

const deliveryBlockedMessage = computed(() => {
    if (!deliveryOptionBlockedByCity.value) return '';
    if (shippingConfig.value.stateRates.length > 0) {
        const activeStates = shippingConfig.value.stateRates.map((row) => row.state);
        const statesPreview = activeStates.length > 6
            ? `${activeStates.slice(0, 6).join(', ')} e mais ${activeStates.length - 6} UF(s)`
            : activeStates.join(', ');
        return `Entrega disponível apenas para: ${statesPreview}.`;
    }
    if (shippingConfig.value.pickupEnabled) {
        return 'Entrega indisponível para sua cidade. Selecione retirada na loja.';
    }
    return 'Entrega indisponível para sua cidade e a loja não possui retirada ativa.';
});

const deliveryFee = computed(() => {
    if (isServicesMode.value) return 0;
    if (!isDeliveryModeSelected.value) return 0;
    if (!isDeliveryOptionAvailable.value) return 0;

    const fee = Math.max(0, toMoney(deliveryRule.value?.fee, 0));
    const freeOver = Math.max(0, toMoney(deliveryRule.value?.freeOver, 0));
    const alwaysFree = Boolean(deliveryRule.value?.alwaysFree);
    if (alwaysFree) return 0;
    if (freeOver > 0 && subtotal.value >= freeOver) return 0;
    return fee;
});

const paymentFee = computed(() => {
    if (!selectedPaymentMethod.value) return 0;
    const fee = subtotal.value * (selectedPaymentMethod.value.feePercent / 100) + selectedPaymentMethod.value.feeFixed;
    return Number(fee.toFixed(2));
});

const total = computed(() => Number((subtotal.value + deliveryFee.value + paymentFee.value).toFixed(2)));
const checkoutSubmitDisabled = computed(() => {
    if (checkoutForm.processing || !cartEntries.value.length) return true;
    if (!hasCheckoutDeliveryMode.value) return true;
    if (!isCheckoutDeliveryModeValid.value) return true;
    if (isDeliveryModeSelected.value && !isDeliveryOptionAvailable.value) return true;
    if (isDeliveryModeSelected.value && !isAddressComplete.value) return true;
    return false;
});

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

watch(deliveryModeOptions, (options) => {
    if (!Array.isArray(options) || options.length === 0) {
        checkoutForm.delivery_mode = '';
        return;
    }

    if (!options.some((option) => option.value === checkoutForm.delivery_mode)) {
        checkoutForm.delivery_mode = String(options[0].value || '');
    }
}, { immediate: true });

watch(paymentMethods, (methods) => {
    const selectedId = toInt(checkoutForm.payment_method_id, 0);
    if (selectedId > 0 && methods.some((method) => method.id === selectedId)) {
        return;
    }

    checkoutForm.payment_method_id = methods[0]?.id ?? null;
}, { immediate: true, deep: true });

watch(() => checkoutForm.payment_method_id, (value) => {
    if (toInt(value, 0) > 0) {
        checkoutForm.clearErrors('payment_method_id');
    }
});

watch(() => checkoutForm.delivery_mode, (value) => {
    if (String(value ?? '').trim() !== '') {
        checkoutForm.clearErrors('delivery_mode');
    }
});

const submitQuickCheckout = () => {

    if (!isAuthenticated.value) {
        router.visit(loginUrl.value);
        return;
    }

    if (!hasVerifiedAccess.value) {
        router.visit(verifyEmailUrl.value);
        return;
    }

    if (!cartEntries.value.length) {
        showAlertToast('Adicione itens no carrinho.', 'warning');
        return;
    }

    if (!hasCheckoutDeliveryMode.value) {
        checkoutForm.setError('delivery_mode', 'A loja está sem checkout disponível no momento.');
        showAlertToast('A loja está sem checkout disponível no momento.', 'warning');
        return;
    }

    if (!isCheckoutDeliveryModeValid.value) {
        checkoutForm.setError('delivery_mode', 'Selecione como deseja receber o pedido.');
        showAlertToast('Selecione retirada na loja ou entrega para continuar.', 'warning');
        return;
    }

    if (isDeliveryModeSelected.value && !isDeliveryOptionAvailable.value) {
        checkoutForm.setError('delivery_mode', deliveryBlockedMessage.value || 'Entrega indisponível para sua cidade.');
        showAlertToast(deliveryBlockedMessage.value || 'Entrega indisponível para sua cidade.', 'warning');
        return;
    }

    if (isDeliveryModeSelected.value && !isAddressComplete.value) {
        showAlertToast('Complete seu endereço na aba Conta para finalizar o pedido.', 'warning');
        activeTab.value = 'account';
        isCartOpen.value = false;
        return;
    }

    if (!selectedPaymentMethod.value) {
        checkoutForm.setError('payment_method_id', 'Selecione uma forma de pagamento para finalizar o pedido.');
        showAlertToast('Escolha uma forma de pagamento para continuar.', 'warning');
        return;
    }

    checkoutForm.clearErrors();
    checkoutForm.customer_phone = formatPhoneBR(checkoutForm.customer_phone);
    checkoutForm.shipping_postal_code = formatCepBR(checkoutForm.shipping_postal_code);
    checkoutForm.shipping_state = normalizeStateCode(checkoutForm.shipping_state);
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
            activeTab.value = 'orders';
            isCartOpen.value = true;
        },
        onError: (errors) => {
            const orderError = String(errors?.order || checkoutForm.errors?.order || '').trim();
            const paymentError = String(errors?.payment_method_id || checkoutForm.errors?.payment_method_id || '').trim();
            if (orderError !== '') {
                showAlertToast(orderError, 'error');
            } else if (paymentError !== '') {
                showAlertToast(paymentError, 'error');
            } else {
                const firstBackendError = Object.values(errors ?? {})
                    .flatMap((value) => (Array.isArray(value) ? value : [value]))
                    .map((value) => String(value ?? '').trim())
                    .find((value) => value !== '');

                showAlertToast(firstBackendError || 'Não foi possível finalizar o pedido. Revise os dados e tente novamente.', 'error');
            }
            isCartOpen.value = true;
        },
    });
};
const bookingForm = useForm({
    service_catalog_id: null,
    scheduled_for: '',
    notes: '',
});

const hourToMinutes = (time) => {
    const safe = String(time ?? '').trim();
    const match = safe.match(/^([01]\d|2[0-3]):([0-5]\d)$/);
    if (!match) return -1;
    return (Number.parseInt(match[1], 10) * 60) + Number.parseInt(match[2], 10);
};

const activeBookingItem = computed(() => (cartEntries.value[0] ?? selectedItem.value ?? null));
const activeBookingService = computed(() => {
    const id = toInt(activeBookingItem.value?.id, 0);
    if (id <= 0) return null;
    return normalizedCatalog.value.find((item) => item.id === id) ?? activeBookingItem.value;
});
const activeBookingDurationMinutes = computed(() => Math.max(15, toInt(activeBookingService.value?.durationMinutes, 60)));

const bookingSlotGroups = computed(() => {
    const raw = Array.isArray(props.store_availability?.booking_slots) ? props.store_availability.booking_slots : [];
    const durationMinutes = activeBookingDurationMinutes.value;

    return raw
        .map((day) => {
            const close = String(day?.close ?? '').trim();
            const open = String(day?.open ?? '').trim();
            const closeMinutes = hourToMinutes(close);
            const slots = Array.isArray(day?.slots)
                ? day.slots
                    .map((slot) => {
                        const value = String(slot?.value ?? '').trim();
                        if (!value) return null;

                        const fallbackTime = value.includes('T') ? value.split('T')[1]?.slice(0, 5) : '';
                        const label = String(slot?.label ?? '').trim() || fallbackTime;
                        const startMinutes = hourToMinutes(label);

                        if (closeMinutes >= 0 && startMinutes >= 0 && (startMinutes + durationMinutes) > closeMinutes) {
                            return null;
                        }

                        return {
                            value,
                            label,
                        };
                    })
                    .filter(Boolean)
                : [];

            if (!slots.length) return null;

            const groupLabel = String(day?.label || '').trim()
                || `${String(day?.day_label || '').trim()} ${String(day?.date || '').trim()}`.trim()
                || 'Dia';

            return {
                label: groupLabel,
                date: String(day?.date || '').trim(),
                open,
                close,
                slots,
            };
        })
        .filter(Boolean);
});

const bookingMonthFormatter = new Intl.DateTimeFormat('pt-BR', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});
const selectedBookingMonth = ref('');
const selectedBookingDay = ref('');

const bookingMonthOptions = computed(() => {
    const map = new Map();

    bookingSlotGroups.value.forEach((group) => {
        const date = String(group?.date || '').trim();
        if (!date || date.length < 7) return;
        const monthKey = date.slice(0, 7);
        if (map.has(monthKey)) return;

        const parsedDate = new Date(`${monthKey}-01T00:00:00Z`);
        const monthLabel = Number.isNaN(parsedDate.getTime())
            ? monthKey
            : bookingMonthFormatter.format(parsedDate);
        const label = monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1);

        map.set(monthKey, {
            value: monthKey,
            label,
        });
    });

    return Array.from(map.values());
});

const bookingDayOptions = computed(() =>
    bookingSlotGroups.value
        .filter((group) => {
            const date = String(group?.date || '').trim();
            if (!date || date.length < 7) return false;
            if (!selectedBookingMonth.value) return true;
            return date.slice(0, 7) === selectedBookingMonth.value;
        })
        .map((group) => ({
            value: String(group?.date || '').trim(),
            label: `${group.label} (${group.open} - ${group.close})`,
        }))
        .filter((option) => option.value !== ''));

const bookingHourOptions = computed(() => {
    if (!selectedBookingDay.value) return [];

    const selectedGroup = bookingSlotGroups.value
        .find((group) => String(group?.date || '').trim() === selectedBookingDay.value);

    if (!selectedGroup || !Array.isArray(selectedGroup.slots)) return [];

    return selectedGroup.slots
        .map((slot) => ({
            value: String(slot?.value || '').trim(),
            label: String(slot?.label || '').trim(),
        }))
        .filter((slot) => slot.value !== '');
});

const hasAvailableBookingSlots = computed(() => bookingHourOptions.value.length > 0);
const isSelectedBookingSlotValid = computed(() =>
    bookingHourOptions.value.some((slot) => slot.value === bookingForm.scheduled_for));

watch(bookingMonthOptions, (months) => {
    if (!months.length) {
        selectedBookingMonth.value = '';
        return;
    }

    if (!months.some((month) => month.value === selectedBookingMonth.value)) {
        const now = new Date();
        const monthNumber = String(now.getMonth() + 1).padStart(2, '0');
        const currentMonthKey = `${now.getFullYear()}-${monthNumber}`;
        const currentMonth = months.find((month) => month.value === currentMonthKey);
        selectedBookingMonth.value = String((currentMonth ?? months[0]).value || '');
    }
}, { immediate: true });

watch(bookingDayOptions, (days) => {
    if (!days.length) {
        selectedBookingDay.value = '';
        return;
    }

    if (!days.some((day) => day.value === selectedBookingDay.value)) {
        selectedBookingDay.value = String(days[0].value || '');
    }
}, { immediate: true });

watch(bookingHourOptions, (hours) => {
    if (!hours.length) {
        bookingForm.scheduled_for = '';
        return;
    }

    const selected = String(bookingForm.scheduled_for ?? '').trim();
    if (!hours.some((hour) => hour.value === selected)) {
        bookingForm.scheduled_for = String(hours[0].value || '');
    }
}, { immediate: true });

watch(() => bookingForm.scheduled_for, (value) => {
    if (String(value ?? '').trim() !== '') {
        bookingForm.clearErrors('scheduled_for');
    }
});

const submitQuickBooking = () => {
    if (!isAuthenticated.value) {
        router.visit(loginUrl.value);
        return;
    }

    if (!hasVerifiedAccess.value) {
        router.visit(verifyEmailUrl.value);
        return;
    }

    const target = activeBookingService.value;
    if (!target) {
        showAlertToast('Selecione um serviço antes de agendar.', 'warning');
        return;
    }

    bookingForm.clearErrors();
    bookingForm.service_catalog_id = target.id;

    if (!isSelectedBookingSlotValid.value) {
        bookingForm.setError('scheduled_for', 'Selecione um horário disponível para o agendamento.');
        showAlertToast(hasAvailableBookingSlots.value
            ? 'Escolha um dia e horário para confirmar o agendamento.'
            : 'Não há horários disponíveis no momento.', 'warning');
        return;
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

const shouldSubmitPasswordUpdate = () => {
    return String(passwordForm.current_password || '').trim() !== ''
        || String(passwordForm.password || '').trim() !== ''
        || String(passwordForm.password_confirmation || '').trim() !== '';
};

const submitAccountChanges = () => {
    if (!isAuthenticated.value) {
        router.visit(loginUrl.value);
        return;
    }

    profileForm.phone = formatPhoneBR(profileForm.phone);
    profileForm.cep = formatCepBR(profileForm.cep);
    profileForm.state = normalizeStateCode(profileForm.state);

    profileForm.patch(accountUpdateUrl.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (!shouldSubmitPasswordUpdate()) {
                showAlertToast('Dados da conta atualizados.', 'success');
                return;
            }

            passwordForm.patch(accountPasswordUpdateUrl.value, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    passwordForm.reset();
                    passwordForm.clearErrors();
                    showAlertToast('Dados e senha atualizados.', 'success');
                },
            });
        },
    });
};

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const notifications = computed(() =>
    Array.isArray(props.shop_account?.notifications) ? props.shop_account.notifications : []);
const notificationsUnreadCount = computed(() =>
    toInt(props.shop_account?.notifications_unread_count, notifications.value.filter((item) => !item?.is_read).length));

const markNotificationsForm = useForm({
    id: '',
});

const markNotificationsAsRead = (id = '') => {
    if (!isAuthenticated.value) {
        router.visit(loginUrl.value);
        return;
    }

    markNotificationsForm.transform(() => ({
        id: String(id || '').trim(),
    })).post(accountNotificationsReadUrl.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (String(id || '').trim() !== '') return;
            showNotificationsPanel.value = false;
        },
        onFinish: () => {
            markNotificationsForm.transform((data) => data);
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
            status_value: String(booking?.status?.value || '').trim().toLowerCase(),
            date: String(booking?.scheduled_label || ''),
            total: toMoney(booking?.final_amount || booking?.estimated_amount, 0),
            details: String(booking?.service_name || 'Serviço'),
            payment: null,
            payment_method_label: '',
            payment_is_pix: false,
            payment_is_integrated: false,
            payment_action_url: '',
            payment_pix_code: '',
        }));
    }

    return (Array.isArray(props.shop_account?.orders) ? props.shop_account.orders : []).map((order) => {
        const id = toInt(order?.id, 0);
        const overridePayment = orderPaymentOverrides.value[id];
        const payment = (overridePayment && typeof overridePayment === 'object')
            ? overridePayment
            : ((order?.payment && typeof order.payment === 'object') ? order.payment : null);
        const paymentMethodCode = String(payment?.method_code ?? payment?.payment_method_code ?? '').trim().toLowerCase();
        const paymentMethodName = String(payment?.method_name ?? payment?.payment_method_name ?? '').trim();
        const saleStatusValue = String(order?.status?.value || '').trim().toLowerCase();
        const paymentIsActionable = isActionableIntegratedPayment(payment, saleStatusValue);
        const paymentIsPix = paymentIsActionable && isPixPayload(payment);

        return {
            id,
            code: String(order?.code || ''),
            status: String(order?.status?.label || 'Pedido'),
            status_value: saleStatusValue,
            date: String(order?.created_at || ''),
            total: toMoney(order?.total_amount, 0),
            details: String(order?.payment_label || 'Pagamento'),
            payment,
            payment_method_label: paymentMethodName || paymentMethodLabelFromCode(paymentMethodCode, 'Pagamento'),
            payment_is_pix: paymentIsPix,
            payment_is_integrated: !paymentIsPix && paymentIsActionable && isIntegratedPayload(payment),
            payment_action_url: resolveIntegratedActionUrlFromPayload(payment),
            payment_pix_code: String(payment?.qr_code ?? '').trim(),
        };
    });
});

const resolvePaymentQrImageSrc = async (payment) => {
    const base64 = String(payment?.qr_code_base64 ?? '').trim();
    if (base64 !== '') {
        return normalizeQrImageBase64(base64);
    }

    const qrCode = String(payment?.qr_code ?? '').trim();
    if (qrCode === '') {
        return '';
    }

    try {
        return await QRCode.toDataURL(qrCode, {
            width: 240,
            margin: 1,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });
    } catch {
        return '';
    }
};

const copyOrderPixCode = async (orderId, pixCode) => {
    const id = toInt(orderId, 0);
    if (id <= 0) return;

    const copied = await copyTextToClipboard(pixCode);
    if (!copied) return;

    orderPixCodeCopiedById.value = {
        ...orderPixCodeCopiedById.value,
        [id]: true,
    };
    setTimeout(() => {
        orderPixCodeCopiedById.value = {
            ...orderPixCodeCopiedById.value,
            [id]: false,
        };
    }, 1800);
};

const refreshOrderPayment = async (orderId) => {
    const id = toInt(orderId, 0);
    if (id <= 0) return;
    if (orderPaymentRefreshLoadingById.value[id]) return;

    orderPaymentRefreshLoadingById.value = {
        ...orderPaymentRefreshLoadingById.value,
        [id]: true,
    };
    orderPaymentRefreshErrorById.value = {
        ...orderPaymentRefreshErrorById.value,
        [id]: '',
    };

    try {
        const response = await fetch(`/shop/${storeSlug.value}/checkout/pagamento/${id}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('not_found');
        }

        const data = await response.json();
        const payment = data?.payment;
        if (payment && typeof payment === 'object') {
            const currentPayment = orderPaymentOverrides.value[id] && typeof orderPaymentOverrides.value[id] === 'object'
                ? orderPaymentOverrides.value[id]
                : null;
            const mergedPayment = mergePaymentPayload(currentPayment, payment);
            orderPaymentOverrides.value = {
                ...orderPaymentOverrides.value,
                [id]: mergedPayment,
            };
            const qrImage = await resolvePaymentQrImageSrc(mergedPayment);
            orderPaymentQrById.value = {
                ...orderPaymentQrById.value,
                [id]: qrImage,
            };
        }
    } catch {
        orderPaymentRefreshErrorById.value = {
            ...orderPaymentRefreshErrorById.value,
            [id]: 'Não foi possível atualizar a cobrança agora.',
        };
    } finally {
        orderPaymentRefreshLoadingById.value = {
            ...orderPaymentRefreshLoadingById.value,
            [id]: false,
        };
    }
};

const syncOrderPaymentAutoRefresh = () => {
    if (orderPaymentAutoRefreshTimer.value) {
        clearInterval(orderPaymentAutoRefreshTimer.value);
        orderPaymentAutoRefreshTimer.value = null;
    }

    const targetOrderIds = orders.value
        .filter((order) => order?.payment_is_pix || order?.payment_is_integrated)
        .map((order) => toInt(order?.id, 0))
        .filter((id) => id > 0);

    if (!targetOrderIds.length) {
        return;
    }

    orderPaymentAutoRefreshTimer.value = setInterval(() => {
        targetOrderIds.forEach((id) => {
            void refreshOrderPayment(id);
        });
    }, 15000);
};

watch(orders, async (list) => {
    const nextQr = { ...orderPaymentQrById.value };
    const validIds = new Set();

    for (const order of list) {
        const id = toInt(order?.id, 0);
        if (id <= 0) continue;
        validIds.add(id);

        if (!order.payment_is_pix) {
            delete nextQr[id];
            continue;
        }

        nextQr[id] = await resolvePaymentQrImageSrc(order.payment);
    }

    Object.keys(nextQr).forEach((rawId) => {
        const id = toInt(rawId, 0);
        if (!validIds.has(id)) {
            delete nextQr[id];
        }
    });

    orderPaymentQrById.value = nextQr;
    syncOrderPaymentAutoRefresh();
}, { immediate: true, deep: true });

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
const storeAvailabilityLabel = computed(() =>
    String(storeAvailability.value.status || '').trim()
    || (storeAvailability.value.open ? 'Loja aberta' : 'Loja fechada'));
const storeAvailabilityChipClass = computed(() =>
    storeAvailability.value.open
        ? 'bg-emerald-100 text-emerald-700'
        : 'bg-rose-100 text-rose-700');

const openDetails = (item) => {
    if (!item) return;

    selectedId.value = item.id;
    detailsImageIndex.value = 0;
    detailsQuantity.value = 1;
    detailsVariationId.value = item.variations?.[0]?.id ?? null;
    isDetailsOpen.value = true;
};

const closeDetails = () => {
    isDetailsOpen.value = false;
};

const changeDetailsImage = (nextIndex) => {
    const images = selectedItemImages.value;
    if (!images.length) {
        detailsImageIndex.value = 0;
        return;
    }

    const safeIndex = Math.max(0, Math.min(images.length - 1, toInt(nextIndex, 0)));
    detailsImageIndex.value = safeIndex;
};

const submitDetailsPrimaryAction = () => {
    if (!selectedItem.value) return;

    if (isServicesMode.value) {
        if (!isSelectedBookingSlotValid.value) {
            bookingForm.setError('scheduled_for', 'Selecione um horário disponível.');
            showAlertToast(hasAvailableBookingSlots.value
                ? 'Escolha um horário para continuar com o agendamento.'
                : 'Não há horários disponíveis no momento.', 'warning');
            return;
        }

        addToCart(selectedItem.value, { quantity: 1 });
        isCartOpen.value = true;
        isDetailsOpen.value = false;
        return;
    }

    addToCart(selectedItem.value, {
        quantity: Math.max(1, toInt(detailsQuantity.value, 1)),
        variationId: toInt(detailsVariationId.value, 0) || null,
    });
    isDetailsOpen.value = false;
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
    if (cartAttentionTimeout) {
        clearTimeout(cartAttentionTimeout);
        cartAttentionTimeout = null;
    }
    if (alertToastTimeout) {
        clearTimeout(alertToastTimeout);
        alertToastTimeout = null;
    }
});

</script>
<template>
    <div class="idx-root" :style="themeVars">
        <div class="h-screen w-full relative flex overflow-hidden">
            <div class="w-full h-full flex">
                <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col h-full z-10">
                    <div class="p-4 border-b border-gray-100">
                        <div class="rounded-xl px-3 py-2">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg"
                                    :style="storeLogo ? null : { background: 'var(--idx-primary)' }"
                                >
                                    <img v-if="storeLogo" :src="storeLogo" :alt="storeName" class="h-full w-full rounded-lg object-cover">
                                    <span v-else class="text-xs font-semibold text-white">{{ storeInitials }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-semibold text-slate-900">{{ storeName }}</p>
                                    <span class="mt-1 inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="storeAvailabilityChipClass">
                                        {{ storeAvailabilityLabel }}
                                    </span>
                                </div>
                            </div>
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
                            <div v-if="activeTab === 'home'" class="md:hidden min-w-0 flex-1">
                                <div class="flex min-w-0 items-center gap-3 rounded-xl px-2.5 py-2">
                                    <div
                                        class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg"
                                        :style="storeLogo ? null : { background: 'var(--idx-primary)' }"
                                    >
                                        <img v-if="storeLogo" :src="storeLogo" :alt="storeName" class="h-full w-full rounded-lg object-cover">
                                        <span v-else class="text-[10px] font-semibold text-white">{{ storeInitials }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1 leading-tight">
                                        <p class="truncate text-xs font-semibold text-slate-900">{{ storeName }}</p>
                                        <span class="mt-1 inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="storeAvailabilityChipClass">
                                            {{ storeAvailabilityLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <h2 v-else class="text-lg font-bold md:hidden">{{ activeTab === 'favorites' ? 'Favoritos' : activeTab === 'orders' ? (isServicesMode ? 'Agendamentos' : 'Pedidos') : 'Conta' }}</h2>
                            <div class="hidden md:flex items-center bg-gray-100 rounded-full px-4 py-2 w-full">
                                <Search :size="15" class="text-gray-400 mr-2" />
                                <input v-model="search" type="text" placeholder="Buscar" class="bg-transparent border-none focus:outline-none w-full text-sm">
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="relative p-2 text-gray-500 hover:text-[var(--idx-primary)] transition-colors"
                                :disabled="markNotificationsForm.processing"
                                @click="showNotificationsPanel = !showNotificationsPanel"
                            >
                                <Bell :size="19" />
                                <span
                                    v-if="notificationsUnreadCount > 0"
                                    class="absolute -right-0.5 -top-0.5 inline-flex min-h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                                >
                                    {{ notificationsUnreadCount > 9 ? '9+' : notificationsUnreadCount }}
                                </span>
                            </button>
                            <button
                                type="button"
                                class="relative rounded-full p-2 text-gray-600 transition-all duration-200 hover:text-[var(--idx-primary)]"
                                :class="cartAttention ? 'scale-110 bg-emerald-50 text-emerald-700 shadow-sm ring-2 ring-emerald-200' : ''"
                                @click="isCartOpen = true"
                            >
                                <ShoppingCart :size="20" />
                                <span class="absolute top-0 right-0 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center">{{ cartCount }}</span>
                                <span
                                    v-if="cartAttention"
                                    class="pointer-events-none absolute -right-2 -top-2 rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white shadow-sm"
                                >
                                    +1
                                </span>
                            </button>
                        </div>
                    </header>

                    <div
                        v-if="showNotificationsPanel"
                        class="absolute inset-0 z-30"
                        @click="showNotificationsPanel = false"
                    ></div>

                    <transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 translate-y-1"
                    >
                        <section
                            v-if="showNotificationsPanel"
                            class="absolute right-3 top-16 z-40 w-[min(22rem,calc(100%-1.5rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                        >
                            <header class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">Notificações</h3>
                                    <p class="text-xs text-slate-500">Status de pedidos e agendamentos</p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                    :disabled="markNotificationsForm.processing || notificationsUnreadCount <= 0"
                                    @click="markNotificationsAsRead()"
                                >
                                    Marcar todas
                                </button>
                            </header>
                            <div class="max-h-80 overflow-y-auto p-3">
                                <article
                                    v-for="item in notifications"
                                    :key="`notif-${item.id}`"
                                    class="rounded-xl border border-slate-200 bg-slate-50/60 p-3"
                                    :class="item.is_read ? '' : 'border-[var(--idx-primary-border)] bg-[var(--idx-primary-soft)]'"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
                                            <p class="mt-1 text-xs text-slate-600">{{ item.message }}</p>
                                            <p v-if="item.created_at_label" class="mt-1 text-[11px] text-slate-500">{{ item.created_at_label }}</p>
                                        </div>
                                        <button
                                            v-if="!item.is_read"
                                            type="button"
                                            class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100"
                                            :disabled="markNotificationsForm.processing"
                                            @click="markNotificationsAsRead(item.id)"
                                        >
                                            Lida
                                        </button>
                                    </div>
                                </article>
                                <p v-if="!notifications.length" class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-500">
                                    Nenhuma notificação de status no momento.
                                </p>
                            </div>
                        </section>
                    </transition>

                    <div class="flex-1 overflow-y-auto p-4 md:p-6 pb-24 md:pb-6">
                        <div v-if="checkoutManual?.whatsapp_url" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 text-sm px-4 py-2.5">
                            Pagamento manual disponível. <a :href="checkoutManual.whatsapp_url" target="_blank" class="font-semibold underline">Abrir WhatsApp</a>
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
                                        class="relative h-40 w-[280px] overflow-hidden rounded-2xl border border-white/20"
                                    >
                                        <img :src="banner.image" :alt="banner.title" class="h-full w-full object-cover">
                                        <div
                                            class="absolute inset-0 p-4 text-white"
                                            :class="banner.useOriginalImageColors ? 'flex flex-col justify-end' : 'bg-gradient-to-tr from-black/60 via-black/25 to-black/15'"
                                            :style="banner.useOriginalImageColors ? { textShadow: '0 1px 3px rgba(0, 0, 0, 0.65)' } : null"
                                        >
                                            <span
                                                v-if="banner.badge"
                                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                                :class="banner.useOriginalImageColors ? 'bg-slate-900/55' : 'bg-white/20'"
                                            >
                                                {{ banner.badge }}
                                            </span>
                                            <h3 class="mt-2 text-base font-extrabold leading-tight">{{ banner.title }}</h3>
                                            <p v-if="banner.subtitle" class="mt-1 line-clamp-2 text-xs text-white/90">{{ banner.subtitle }}</p>
                                            <button
                                                v-if="banner.ctaLabel"
                                                type="button"
                                                class="mt-3 inline-flex rounded-full px-3 py-1 text-[11px] font-semibold"
                                                :class="banner.useOriginalImageColors ? 'bg-slate-900/55 text-white' : 'bg-white/20 text-white'"
                                                @click="applyHeroCta"
                                            >
                                                {{ banner.ctaLabel || 'Ver mais' }}
                                            </button>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <div class="hidden md:grid grid-cols-3 gap-6 mb-8">
                                <article
                                    v-for="(banner, index) in desktopBanners"
                                    :key="`desktop-banner-${banner.id}`"
                                    class="relative overflow-hidden rounded-2xl"
                                    :class="index === 0 ? 'col-span-2 h-48' : 'h-48'"
                                >
                                    <img :src="banner.image" :alt="banner.title" class="h-full w-full object-cover">
                                    <div
                                        class="absolute inset-0 p-5 text-white"
                                        :style="banner.useOriginalImageColors
                                            ? { textShadow: '0 1px 3px rgba(0, 0, 0, 0.65)' }
                                            : { background: `linear-gradient(130deg, ${withAlpha(banner.backgroundColor, 0.84)} 0%, rgba(15, 23, 42, 0.58) 65%)` }"
                                    >
                                        <p class="text-xs uppercase tracking-[0.16em]">{{ storeName }}</p>
                                        <h3 class="mt-2 text-2xl font-black leading-tight">{{ banner.title }}</h3>
                                        <p v-if="banner.subtitle" class="mt-1 max-w-md text-sm text-white/90">{{ banner.subtitle }}</p>
                                        <button
                                            v-if="banner.ctaLabel"
                                            type="button"
                                            class="mt-4 inline-flex rounded-full px-3 py-1.5 text-xs font-semibold backdrop-blur"
                                            :class="banner.useOriginalImageColors ? 'bg-slate-900/55 text-white' : 'bg-white/20 text-white'"
                                            @click="applyHeroCta"
                                        >
                                            {{ banner.ctaLabel || 'Ver mais' }}
                                        </button>
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
                                    class="bg-white rounded-2xl overflow-hidden shadow-sm relative group transition-all duration-200 md:cursor-pointer md:hover:-translate-y-0.5 md:hover:shadow-md md:hover:ring-1 md:hover:ring-slate-200"
                                    :class="[
                                        isItemActionLoading(item.id) ? 'ring-2 ring-[var(--idx-primary)] shadow-xl md:-translate-y-1' : '',
                                        isItemActionSuccess(item.id) ? 'ring-2 ring-emerald-300 shadow-lg' : '',
                                    ]"
                                    @click="openDetails(item)"
                                >
                                    <button
                                        type="button"
                                        class="absolute top-2 right-2 bg-red-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold z-10"
                                        @click.stop="toggleFavorite(item)"
                                    >
                                        <Heart :size="12" :class="{ 'fill-white': isFavorite(item.id) }" />
                                    </button>
                                    <div
                                        v-if="isItemActionLoading(item.id) || isItemActionSuccess(item.id)"
                                        class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center bg-slate-900/25 backdrop-blur-[1px]"
                                    >
                                        <div class="inline-flex items-center gap-2 rounded-full bg-white/95 px-3 py-1.5 text-[11px] font-semibold text-slate-700 shadow-sm">
                                            <Loader2 v-if="isItemActionLoading(item.id)" :size="13" class="animate-spin text-[var(--idx-primary)]" />
                                            <Check v-else :size="13" class="text-emerald-600" />
                                            <span>
                                                {{
                                                    isItemActionLoading(item.id)
                                                        ? (isServicesMode ? 'Carregando agendamento...' : 'Adicionando ao carrinho...')
                                                        : (isServicesMode ? 'Serviço carregado' : 'Produto carregado')
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <img :src="item.image" :alt="item.title" class="w-full h-32 md:h-40 object-cover group-hover:scale-105 transition-transform duration-300">
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
                                            <button
                                                class="inline-flex items-center gap-1 text-white text-xs px-3 py-1 rounded-full font-medium disabled:opacity-70"
                                                :style="{ backgroundColor: 'var(--idx-add-button)' }"
                                                :disabled="isItemActionLoading(item.id)"
                                                @click.stop="addToCart(item)"
                                            >
                                                <Loader2 v-if="isItemActionLoading(item.id)" :size="12" class="animate-spin" />
                                                <Check v-else-if="isItemActionSuccess(item.id)" :size="12" />
                                                <span>
                                                    {{
                                                        isItemActionLoading(item.id)
                                                            ? (isServicesMode ? 'Carregando...' : 'Adicionando...')
                                                            : (
                                                        isItemActionSuccess(item.id)
                                                            ? 'Adicionado'
                                                            : (isServicesMode ? 'Agendar' : 'Adicionar')
                                                            )
                                                    }}
                                                </span>
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
                                        class="bg-white rounded-2xl overflow-hidden shadow-sm relative group transition-all duration-200 md:cursor-pointer md:hover:-translate-y-0.5 md:hover:shadow-md"
                                        @click="openDetails(item)"
                                    >
                                        <img :src="item.image" :alt="item.title" class="w-full h-32 md:h-40 object-cover group-hover:scale-105 transition-transform duration-300">
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

                                    <div v-if="!isServicesMode && order.payment_is_pix" class="mt-3 rounded-xl border border-orange-200 bg-orange-50 px-3 py-2.5 text-orange-900">
                                        <p class="text-sm font-semibold">Cobrança Pix</p>
                                        <p v-if="order.payment_pix_code" class="mt-1 break-all text-xs">
                                            Código Pix: <span class="font-semibold">{{ order.payment_pix_code }}</span>
                                        </p>
                                        <div v-if="orderPaymentQrById[order.id]" class="mt-2 inline-flex rounded-lg border border-orange-200 bg-white p-2">
                                            <img :src="orderPaymentQrById[order.id]" alt="QR Code Pix" class="h-28 w-28 rounded-md">
                                        </div>
                                        <p v-if="order.payment?.transaction_reference" class="mt-1 text-xs">
                                            Referência: <span class="font-semibold">{{ order.payment.transaction_reference }}</span>
                                        </p>
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-orange-200 bg-white px-2.5 py-1 text-xs font-semibold text-orange-800 hover:bg-orange-100 disabled:opacity-60"
                                                :disabled="!order.payment_pix_code"
                                                @click="copyOrderPixCode(order.id, order.payment_pix_code)"
                                            >
                                                <Copy :size="12" />
                                                {{ orderPixCodeCopiedById[order.id] ? 'Código copiado' : 'Copiar código Pix' }}
                                            </button>
                                            <a
                                                v-if="order.payment_action_url"
                                                :href="order.payment_action_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center rounded-lg border border-orange-200 bg-white px-2.5 py-1 text-xs font-semibold text-orange-800 hover:bg-orange-100"
                                            >
                                                Abrir cobrança Pix
                                            </a>
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-lg border border-orange-200 bg-white px-2.5 py-1 text-xs font-semibold text-orange-800 hover:bg-orange-100 disabled:opacity-60"
                                                :disabled="orderPaymentRefreshLoadingById[order.id]"
                                                @click="refreshOrderPayment(order.id)"
                                            >
                                                {{ orderPaymentRefreshLoadingById[order.id] ? 'Atualizando...' : 'Atualizar cobrança Pix' }}
                                            </button>
                                        </div>
                                        <p v-if="orderPaymentRefreshErrorById[order.id]" class="mt-1 text-xs text-orange-700">
                                            {{ orderPaymentRefreshErrorById[order.id] }}
                                        </p>
                                    </div>

                                    <div v-else-if="!isServicesMode && order.payment_is_integrated" class="mt-3 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2.5 text-indigo-900">
                                        <p class="text-sm font-semibold">Pagamento {{ order.payment_method_label }} pendente</p>
                                        <p class="mt-1 text-xs">Finalize no ambiente seguro do Mercado Pago.</p>
                                        <p v-if="order.payment?.transaction_reference" class="mt-1 text-xs break-all">
                                            Referência: <span class="font-semibold">{{ order.payment.transaction_reference }}</span>
                                        </p>
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <a
                                                v-if="order.payment_action_url"
                                                :href="order.payment_action_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center rounded-lg border border-indigo-200 bg-white px-2.5 py-1 text-xs font-semibold text-indigo-800 hover:bg-indigo-100"
                                            >
                                                Finalizar pagamento
                                            </a>
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-lg border border-indigo-200 bg-white px-2.5 py-1 text-xs font-semibold text-indigo-800 hover:bg-indigo-100 disabled:opacity-60"
                                                :disabled="orderPaymentRefreshLoadingById[order.id]"
                                                @click="refreshOrderPayment(order.id)"
                                            >
                                                {{ orderPaymentRefreshLoadingById[order.id] ? 'Atualizando...' : 'Atualizar status' }}
                                            </button>
                                        </div>
                                        <p v-if="orderPaymentRefreshErrorById[order.id]" class="mt-1 text-xs text-indigo-700">
                                            {{ orderPaymentRefreshErrorById[order.id] }}
                                        </p>
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

                            <div v-else class="space-y-4">
                                <div class="bg-white rounded-2xl border border-gray-100 p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide md:col-span-2">
                                        E-mail
                                        <input :value="customer?.email || ''" type="email" readonly class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    </label>
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
                                        <UiSelect
                                            v-model="profileForm.state"
                                            :options="stateOptions"
                                            button-class="mt-1"
                                        />
                                    </label>
                                </div>

                                <InputError :message="profileForm.errors.phone" />
                                <InputError :message="profileForm.errors.cep" />
                                <InputError :message="profileForm.errors.street" />
                                <InputError :message="profileForm.errors.number" />
                                <InputError :message="profileForm.errors.neighborhood" />
                                <InputError :message="profileForm.errors.city" />
                                <InputError :message="profileForm.errors.state" />

                            <div v-if="isAuthenticated" class="space-y-3">
                                <div class="bg-white rounded-2xl border border-gray-100 p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                        Senha atual
                                        <input v-model="passwordForm.current_password" type="password" autocomplete="current-password" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                        Nova senha
                                        <input v-model="passwordForm.password" type="password" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs font-semibold uppercase text-gray-500 tracking-wide">
                                        Confirmar senha
                                        <input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    </label>
                                </div>
                                <InputError :message="passwordForm.errors.current_password" />
                                <InputError :message="passwordForm.errors.password" />
                                <InputError :message="passwordForm.errors.password_confirmation" />
                            </div>

                            <div v-if="isAuthenticated" class="flex flex-wrap justify-end gap-2">
                                <button
                                    type="button"
                                    class="px-4 py-2.5 rounded-xl bg-[var(--idx-primary)] text-white font-semibold text-sm disabled:opacity-60"
                                    :disabled="profileForm.processing || passwordForm.processing"
                                    @click="submitAccountChanges"
                                >
                                    {{ profileForm.processing || passwordForm.processing ? 'Salvando...' : 'Salvar dados' }}
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2.5 rounded-xl border border-rose-200 bg-rose-50 font-semibold text-sm text-rose-700 hover:bg-rose-100 disabled:opacity-60"
                                    :disabled="logoutForm.processing || profileForm.processing || passwordForm.processing"
                                    @click="logout"
                                >
                                    Sair da conta
                                </button>
                            </div>
                            </div>
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
                        <button class="text-white w-14 h-14 rounded-full shadow-lg flex items-center justify-center" :style="{ backgroundColor: 'var(--idx-favorite-button)' }" @click="activeTab = 'favorites'">
                            <Heart :size="20" :class="{ 'fill-white': activeTab === 'favorites' }" />
                        </button>
                        <span class="text-[10px] font-medium text-gray-400 absolute -bottom-4 left-1/2 transform -translate-x-1/2">Favoritos</span>
                    </div>

                    <button class="flex flex-col items-center p-2" :class="activeTab === 'account' ? 'text-[var(--idx-primary)]' : 'text-gray-400'" @click="activeTab = 'account'">
                        <UserRound :size="16" class="mb-1" />
                        <span class="text-[10px] font-medium">Conta</span>
                    </button>
                    <button
                        class="relative flex flex-col items-center p-2 text-gray-400 transition-all duration-200"
                        :class="cartAttention ? 'scale-110 text-emerald-700' : ''"
                        @click="isCartOpen = true"
                    >
                        <ShoppingCart :size="16" class="mb-1" />
                        <span class="text-[10px] font-medium">Carrinho</span>
                        <span
                            v-if="cartAttention"
                            class="pointer-events-none absolute -right-1 top-0 rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white shadow-sm"
                        >
                            +1
                        </span>
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
                    class="pointer-events-none absolute bottom-20 left-1/2 z-[70] w-[92%] max-w-sm -translate-x-1/2 rounded-2xl border border-emerald-200 bg-white/95 px-4 py-3 shadow-xl backdrop-blur md:bottom-6"
                >
                    <p class="text-sm font-semibold text-emerald-700">{{ cartToast.title }}</p>
                    <p class="mt-0.5 text-xs text-slate-600">{{ cartToast.description }}</p>
                </div>
            </transition>
            <transition
                enter-active-class="transition duration-250 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-180 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-2 opacity-0"
            >
                <div
                    v-if="alertToast.visible"
                    class="pointer-events-none absolute bottom-36 left-1/2 z-[71] w-[94%] max-w-md -translate-x-1/2 rounded-2xl border px-4 py-3 shadow-xl backdrop-blur md:bottom-20"
                    :class="{
                        'border-slate-200 bg-white/95 text-slate-800': alertToast.tone === 'info',
                        'border-emerald-200 bg-emerald-50/95 text-emerald-800': alertToast.tone === 'success',
                        'border-amber-200 bg-amber-50/95 text-amber-900': alertToast.tone === 'warning',
                        'border-rose-200 bg-rose-50/95 text-rose-800': alertToast.tone === 'error',
                    }"
                >
                    <p class="text-sm font-semibold">{{ alertToast.message }}</p>
                </div>
            </transition>

            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="isDetailsOpen"
                    class="absolute inset-0 z-[72] bg-slate-900/55"
                    @click="closeDetails"
                />
            </transition>
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 translate-y-2 scale-[0.99]"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-2 scale-[0.99]"
            >
                <section
                    v-if="isDetailsOpen && selectedItem"
                    class="absolute left-1/2 top-1/2 z-[73] w-[calc(100%-1.5rem)] max-w-3xl -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl"
                >
                    <header class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-slate-900">{{ selectedItem.title }}</h3>
                            <p class="truncate text-xs text-slate-500">{{ selectedItem.subtitle }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeDetails"
                        >
                            Fechar
                        </button>
                    </header>

                    <div class="grid max-h-[78vh] gap-4 overflow-y-auto p-4 md:grid-cols-[1.1fr_1fr]">
                        <div class="space-y-2">
                            <img
                                :src="selectedItemImages[detailsImageIndex] || selectedItemImages[0]"
                                :alt="selectedItem.title"
                                class="h-56 w-full rounded-2xl border border-slate-200 object-cover md:h-72"
                            >
                            <div v-if="selectedItemImages.length > 1" class="grid grid-cols-4 gap-2">
                                <button
                                    v-for="(image, imageIndex) in selectedItemImages"
                                    :key="`details-thumb-${imageIndex}`"
                                    type="button"
                                    class="overflow-hidden rounded-xl border"
                                    :class="detailsImageIndex === imageIndex ? 'border-[var(--idx-primary)]' : 'border-slate-200'"
                                    @click="changeDetailsImage(imageIndex)"
                                >
                                    <img :src="image" :alt="`${selectedItem.title} ${imageIndex + 1}`" class="h-14 w-full object-cover">
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <p class="text-sm text-slate-600">{{ selectedItem.description }}</p>
                            <p class="text-xl font-bold text-emerald-600">{{ formatMoney(detailsUnitPrice) }}</p>

                            <div v-if="!isServicesMode && detailsVariationOptions.length" class="space-y-1">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Variação</label>
                                <UiSelect
                                    v-model="detailsVariationId"
                                    :options="detailsVariationOptions"
                                    button-class="w-full"
                                />
                            </div>

                            <div v-if="!isServicesMode" class="space-y-1">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quantidade</label>
                                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-2 py-1">
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-700"
                                        @click="detailsQuantity = Math.max(1, toInt(detailsQuantity, 1) - 1)"
                                    >
                                        <Minus :size="13" />
                                    </button>
                                    <span class="min-w-7 text-center text-sm font-semibold text-slate-800">{{ Math.max(1, toInt(detailsQuantity, 1)) }}</span>
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-white"
                                        @click="detailsQuantity = Math.max(1, toInt(detailsQuantity, 1) + 1)"
                                    >
                                        <Plus :size="13" />
                                    </button>
                                </div>
                                <p class="text-xs text-slate-500">Subtotal: {{ formatMoney(detailsLineTotal) }}</p>
                            </div>

                            <div v-if="isServicesMode" class="space-y-2">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Mês
                                    <UiSelect
                                        v-model="selectedBookingMonth"
                                        :options="bookingMonthOptions"
                                        button-class="mt-1"
                                        :disabled="bookingForm.processing || !bookingMonthOptions.length"
                                    />
                                </label>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Dia
                                    <UiSelect
                                        v-model="selectedBookingDay"
                                        :options="bookingDayOptions"
                                        button-class="mt-1"
                                        :disabled="bookingForm.processing || !bookingDayOptions.length"
                                    />
                                </label>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Hora
                                    <UiSelect
                                        v-model="bookingForm.scheduled_for"
                                        :options="bookingHourOptions"
                                        button-class="mt-1"
                                        :disabled="bookingForm.processing || !bookingHourOptions.length"
                                    />
                                </label>
                                <InputError :message="bookingForm.errors.scheduled_for" />
                            </div>

                            <button
                                type="button"
                                class="mt-2 inline-flex w-full items-center justify-center rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-sm"
                                :style="{ backgroundColor: 'var(--idx-cart-button)' }"
                                @click="submitDetailsPrimaryAction"
                            >
                                {{ detailsPrimaryActionLabel }}
                            </button>
                        </div>
                    </div>
                </section>
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
                        <UiSelect
                            v-model="variationByProduct[selectedItem.id]"
                            :options="[{ value: null, label: 'Preço base' }, ...detailsVariationOptions]"
                            button-class="w-full"
                        />
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
                                <button type="button" class="w-6 h-6 rounded-full text-white flex items-center justify-center" :style="{ backgroundColor: 'var(--idx-add-button)' }" @click="increase(entry.id)"><Plus :size="12" /></button>
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
                    <div v-if="!isServicesMode" class="mb-4">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Como deseja receber
                            <UiSelect
                                v-model="checkoutForm.delivery_mode"
                                :options="deliveryModeOptions"
                                button-class="mt-1"
                                :disabled="!hasCheckoutDeliveryMode"
                            />
                        </label>
                        <p v-if="!hasCheckoutDeliveryMode" class="mt-1 text-xs text-rose-700">
                            A loja está sem checkout disponível no momento.
                        </p>
                        <p v-else-if="deliveryBlockedMessage" class="mt-1 text-xs text-amber-700">
                            {{ deliveryBlockedMessage }}
                        </p>
                        <InputError :message="checkoutForm.errors.delivery_mode" />
                    </div>
                    <div v-if="!isServicesMode" class="mb-4">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Forma de pagamento
                            <UiSelect
                                v-model="checkoutForm.payment_method_id"
                                :options="paymentMethodSelectOptions"
                                button-class="mt-1"
                            />
                        </label>
                        <InputError :message="checkoutForm.errors.payment_method_id" />
                    </div>
                    <div v-if="!isServicesMode && checkoutHasPixPayload" class="mb-4 rounded-xl border border-orange-200 bg-orange-50 px-3 py-2.5 text-orange-900">
                        <p class="text-sm font-semibold">Pagamento Pix gerado</p>
                        <p v-if="checkoutPixCode" class="mt-1 break-all text-xs">
                            Código Pix: <span class="font-semibold">{{ checkoutPixCode }}</span>
                        </p>
                        <div v-if="checkoutPixQrImageSrc" class="mt-2 inline-flex rounded-lg border border-orange-200 bg-white p-2">
                            <img :src="checkoutPixQrImageSrc" alt="QR Code Pix" class="h-28 w-28 rounded-md">
                        </div>
                        <p v-if="!checkoutHasVisiblePixData" class="mt-1 text-xs">
                            Estamos buscando o QR Code da cobrança. Atualize em alguns segundos.
                        </p>
                        <p v-if="checkoutEffectivePayment?.transaction_reference" class="mt-1 break-all text-xs">
                            Referência: <span class="font-semibold">{{ checkoutEffectivePayment.transaction_reference }}</span>
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-orange-200 bg-white px-2.5 py-1 text-xs font-semibold text-orange-800 hover:bg-orange-100 disabled:opacity-60"
                                :disabled="!checkoutPixCode"
                                @click="copyCheckoutPixCode()"
                            >
                                <Copy :size="12" />
                                {{ checkoutPixCodeCopied ? 'Código copiado' : 'Copiar código Pix' }}
                            </button>
                            <a
                                v-if="checkoutIntegratedActionUrl"
                                :href="checkoutIntegratedActionUrl"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center rounded-lg border border-orange-200 bg-white px-2.5 py-1 text-xs font-semibold text-orange-800 hover:bg-orange-100"
                            >
                                Abrir cobrança Pix
                            </a>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border border-orange-200 bg-white px-2.5 py-1 text-xs font-semibold text-orange-800 hover:bg-orange-100 disabled:opacity-60"
                                :disabled="checkoutPixRefreshLoading"
                                @click="refreshCheckoutPixPayment()"
                            >
                                {{ checkoutPixRefreshLoading ? 'Atualizando...' : 'Atualizar cobrança Pix' }}
                            </button>
                        </div>
                        <p v-if="checkoutPixRefreshError" class="mt-1 text-xs text-orange-700">
                            {{ checkoutPixRefreshError }}
                        </p>
                    </div>
                    <div v-else-if="!isServicesMode && checkoutIsIntegratedPayload" class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2.5 text-indigo-900">
                        <p class="text-sm font-semibold">Pagamento {{ checkoutIntegratedMethodLabel }} pendente</p>
                        <p class="mt-1 text-xs">Finalize no ambiente seguro do Mercado Pago.</p>
                        <p v-if="checkoutEffectivePayment?.transaction_reference" class="mt-1 break-all text-xs">
                            Referência: <span class="font-semibold">{{ checkoutEffectivePayment.transaction_reference }}</span>
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <a
                                v-if="checkoutIntegratedActionUrl"
                                :href="checkoutIntegratedActionUrl"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center rounded-lg border border-indigo-200 bg-white px-2.5 py-1 text-xs font-semibold text-indigo-800 hover:bg-indigo-100"
                            >
                                Finalizar pagamento
                            </a>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border border-indigo-200 bg-white px-2.5 py-1 text-xs font-semibold text-indigo-800 hover:bg-indigo-100 disabled:opacity-60"
                                :disabled="checkoutPixRefreshLoading"
                                @click="refreshCheckoutPixPayment()"
                            >
                                {{ checkoutPixRefreshLoading ? 'Atualizando...' : 'Atualizar status' }}
                            </button>
                        </div>
                        <p v-if="checkoutPixRefreshError" class="mt-1 text-xs text-indigo-700">
                            {{ checkoutPixRefreshError }}
                        </p>
                    </div>
                    <div v-if="isServicesMode" class="mb-4 space-y-2">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Mês
                            <UiSelect
                                v-model="selectedBookingMonth"
                                :options="bookingMonthOptions"
                                button-class="mt-1"
                                :disabled="bookingForm.processing || !bookingMonthOptions.length"
                            />
                        </label>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Dia
                            <UiSelect
                                v-model="selectedBookingDay"
                                :options="bookingDayOptions"
                                button-class="mt-1"
                                :disabled="bookingForm.processing || !bookingDayOptions.length"
                            />
                        </label>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Horário
                            <UiSelect
                                v-model="bookingForm.scheduled_for"
                                :options="bookingHourOptions"
                                button-class="mt-1"
                                :disabled="bookingForm.processing || !bookingHourOptions.length"
                            />
                        </label>
                        <p v-if="!hasAvailableBookingSlots" class="text-xs text-amber-700">
                            Não há horários disponíveis para agendamento.
                        </p>
                        <InputError :message="bookingForm.errors.scheduled_for" />
                    </div>
                    <button
                        type="button"
                        class="w-full transition-colors text-white font-medium py-4 rounded-full shadow-lg disabled:opacity-60 disabled:cursor-not-allowed"
                        :style="{ backgroundColor: 'var(--idx-cart-button)' }"
                        :disabled="isServicesMode
                            ? bookingForm.processing || !cartEntries.length || !isSelectedBookingSlotValid
                            : checkoutSubmitDisabled"
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

