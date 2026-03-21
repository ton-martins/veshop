<script setup>
import PdvLayout from '@/Layouts/PdvLayout.vue';
import Modal from '@/Components/Modal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    Search,
    Plus,
    Minus,
    Trash2,
    Lock,
    Unlock,
    ReceiptText,
    Star,
    UserPlus,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    ArrowUp,
    ArrowDown,
    Check,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    cashSession: { type: Object, default: null },
    cashSummary: { type: Object, default: () => ({ expected_balance: 0 }) },
    products: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    recentSales: { type: Array, default: () => [] },
    initialAction: { type: String, default: null },
    initialClientId: { type: Number, default: null },
});

const productSearch = ref('');
const productSearchInput = ref(null);

const clientSearch = ref('');
const clientPickerOpen = ref(false);
const clientPickerRoot = ref(null);
const clientPickerInput = ref(null);
const selectedClientId = ref('');
const selectedPaymentMethodId = ref('');
const installments = ref('');
const discountAmount = ref('');
const surchargeAmount = ref('');
const amountPaid = ref('');
const notes = ref('');
const cartItems = ref([]);

const openCashModalOpen = ref(false);
const closeCashModalOpen = ref(false);
const featuredProductsModalOpen = ref(false);
const featuredProductsSearch = ref('');
const featuredProductIds = ref([]);
const createClientModalOpen = ref(false);
const variationPickerModalOpen = ref(false);
const variationPickerProduct = ref(null);
const variationPickerVariationId = ref('');

const openCashForm = useForm({ opening_balance: '0.00', notes: '' });
const closeCashForm = useForm({ closing_balance: '', notes: '' });
const featuredProductsForm = useForm({ product_ids: [] });
const createClientForm = useForm({
    name: '',
    email: '',
    phone: '',
    document: '',
    city: '',
    state: '',
});
const saleForm = useForm({
    client_id: null,
    payment_method_id: null,
    installments: null,
    discount_amount: 0,
    surcharge_amount: 0,
    notes: '',
    items: [],
});

const hasOpenCashSession = computed(() => Boolean(props.cashSession?.id));
const productsSafe = computed(() => (Array.isArray(props.products) ? props.products : []));
const clientsSafe = computed(() => (Array.isArray(props.clients) ? props.clients : []));

const paymentMethodsSafe = computed(() =>
    (Array.isArray(props.paymentMethods) ? props.paymentMethods : []).map((method) => ({
        id: Number(method?.id ?? 0),
        name: String(method?.name ?? ''),
        code: String(method?.code ?? '').toLowerCase(),
        is_default: Boolean(method?.is_default ?? false),
        allows_installments: Boolean(method?.allows_installments ?? false),
        max_installments: Number(method?.max_installments ?? 0) || null,
        fee_fixed: normalizeMoneyInput(method?.fee_fixed ?? 0),
        fee_percent: normalizeMoneyInput(method?.fee_percent ?? 0),
    })).filter((method) => method.id > 0),
);

const paymentMethodOptions = computed(() =>
    paymentMethodsSafe.value.map((method) => ({
        value: String(method.id),
        label: (method.fee_fixed > 0 || method.fee_percent > 0)
            ? `${method.name} (+ taxa)`
            : method.name,
    })),
);

const selectedPaymentMethod = computed(() =>
    paymentMethodsSafe.value.find((method) => String(method.id) === String(selectedPaymentMethodId.value)) ?? null,
);

const installmentOptions = computed(() => {
    if (!selectedPaymentMethod.value?.allows_installments) return [];

    const max = Number(selectedPaymentMethod.value?.max_installments ?? 12) || 12;
    return Array.from({ length: Math.max(0, max - 1) }).map((_, index) => ({
        value: String(index + 2),
        label: `${index + 2}x`,
    }));
});

const activeCategoryKey = ref('all');
const categoryCarouselRef = ref(null);
const currentProductPage = ref(1);
const productsPerPage = 20;

const categoryBadges = computed(() => {
    const grouped = new Map();

    for (const product of productsSafe.value) {
        const hasCategory = Number.isInteger(Number(product.category_id)) && Number(product.category_id) > 0;
        const key = hasCategory ? `cat-${Number(product.category_id)}` : 'cat-uncategorized';
        const label = String(product.category_name ?? '').trim() || 'Sem categoria';

        if (!grouped.has(key)) {
            grouped.set(key, { key, label, count: 0 });
        }

        grouped.get(key).count += 1;
    }

    const sortedCategories = Array.from(grouped.values()).sort((a, b) =>
        String(a.label).localeCompare(String(b.label), 'pt-BR'),
    );

    return [
        {
            key: 'all',
            label: 'Todas categorias',
            count: productsSafe.value.length,
        },
        ...sortedCategories,
    ];
});

const activeCategoryBadge = computed(() =>
    categoryBadges.value.find((category) => category.key === activeCategoryKey.value) ?? categoryBadges.value[0] ?? null,
);

const filteredProducts = computed(() => {
    const categoryKey = String(activeCategoryKey.value ?? 'all');
    const query = String(productSearch.value ?? '').trim().toLowerCase();

    let scopedProducts = productsSafe.value;

    if (categoryKey.startsWith('cat-')) {
        const categoryId = Number(categoryKey.replace('cat-', ''));
        scopedProducts = scopedProducts.filter((product) => Number(product.category_id) === categoryId);
    } else if (categoryKey === 'cat-uncategorized') {
        scopedProducts = scopedProducts.filter((product) => !Number(product.category_id));
    }

    if (!query) return scopedProducts;

    return scopedProducts.filter((product) =>
        String(product.name ?? '').toLowerCase().includes(query)
        || String(product.sku ?? '').toLowerCase().includes(query),
    );
});

const totalProductPages = computed(() =>
    Math.max(1, Math.ceil(filteredProducts.value.length / productsPerPage)),
);

const productPageNumbers = computed(() => {
    const total = totalProductPages.value;
    const current = currentProductPage.value;

    if (total <= 5) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }

    let start = Math.max(1, current - 2);
    let end = Math.min(total, start + 4);

    if ((end - start) < 4) {
        start = Math.max(1, end - 4);
    }

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
});

const paginatedProducts = computed(() => {
    const start = (currentProductPage.value - 1) * productsPerPage;
    return filteredProducts.value.slice(start, start + productsPerPage);
});

const filteredClients = computed(() => {
    const query = String(clientSearch.value ?? '').trim().toLowerCase();
    if (!query) return clientsSafe.value;

    return clientsSafe.value.filter((client) => String(client.name ?? '').toLowerCase().includes(query));
});

const selectedClient = computed(() =>
    clientsSafe.value.find((client) => String(client.id) === String(selectedClientId.value)) ?? null,
);

const clientPickerLabel = computed(() => selectedClient.value?.name ?? 'Consumidor final');

const subtotalAmount = computed(() =>
    cartItems.value.reduce((sum, item) => sum + (Number(item.quantity) * Number(item.unit_price)), 0),
);

const discountValue = computed(() => normalizeMoneyInput(discountAmount.value));
const manualSurchargeValue = computed(() => normalizeMoneyInput(surchargeAmount.value));
const baseAmount = computed(() => Math.max(0, subtotalAmount.value - discountValue.value + manualSurchargeValue.value));
const paymentFeeAmount = computed(() => {
    const method = selectedPaymentMethod.value;
    if (!method) return 0;

    if (method.fee_fixed <= 0 && method.fee_percent <= 0) {
        return 0;
    }

    return normalizeMoneyInput(method.fee_fixed + (baseAmount.value * (method.fee_percent / 100)));
});
const totalAmount = computed(() => Math.max(0, baseAmount.value + paymentFeeAmount.value));
const paymentFeeHint = computed(() => {
    const method = selectedPaymentMethod.value;
    if (!method) return '';

    if (paymentFeeAmount.value > 0) {
        return `Taxa aplicada na venda: + ${asCurrency(paymentFeeAmount.value)}.`;
    }

    return '';
});
const isCashPayment = computed(() =>
    String(selectedPaymentMethod.value?.code ?? '').trim().toLowerCase() === 'cash',
);
const paidAmount = computed(() => normalizeMoneyInput(amountPaid.value));
const changeAmount = computed(() => {
    if (!isCashPayment.value) return 0;
    return Math.max(0, paidAmount.value - totalAmount.value);
});
const missingAmount = computed(() => {
    if (!isCashPayment.value) return 0;
    return Math.max(0, totalAmount.value - paidAmount.value);
});

const canFinalizeSale = computed(() =>
    hasOpenCashSession.value
    && cartItems.value.length > 0
    && Boolean(selectedPaymentMethodId.value)
    && (!isCashPayment.value || paidAmount.value >= totalAmount.value)
    && totalAmount.value > 0,
);

const featuredProductsMap = computed(() => {
    const map = new Map();
    for (const product of productsSafe.value) {
        map.set(Number(product.id), product);
    }
    return map;
});

const featuredProductsFiltered = computed(() => {
    const query = String(featuredProductsSearch.value ?? '').trim().toLowerCase();
    if (!query) return productsSafe.value;

    return productsSafe.value.filter((product) =>
        String(product.name ?? '').toLowerCase().includes(query)
        || String(product.sku ?? '').toLowerCase().includes(query),
    );
});

const selectedFeaturedProducts = computed(() =>
    featuredProductIds.value
        .map((id) => featuredProductsMap.value.get(Number(id)))
        .filter(Boolean),
);

const featuredLimitReached = computed(() => featuredProductIds.value.length >= 12);
const hasPendingCart = computed(() => cartItems.value.length > 0 && !saleForm.processing);

watch(
    () => props.paymentMethods,
    (methods) => {
        if (
            selectedPaymentMethodId.value
            && methods.some((method) => String(method.id) === String(selectedPaymentMethodId.value))
        ) {
            return;
        }

        const defaultMethod = methods.find((method) => method.is_default) ?? methods[0] ?? null;
        selectedPaymentMethodId.value = defaultMethod ? String(defaultMethod.id) : '';
    },
    { immediate: true },
);

watch(
    selectedPaymentMethod,
    (method) => {
        if (!method?.allows_installments) {
            installments.value = '';
        } else if (!installments.value) {
            installments.value = '2';
        }

        if (String(method?.code ?? '').toLowerCase() !== 'cash') {
            amountPaid.value = '';
        }
    },
    { immediate: true },
);

watch(
    () => props.initialClientId,
    (clientId) => {
        if (!clientId) return;
        selectedClientId.value = String(clientId);
    },
    { immediate: true },
);

watch([productSearch, activeCategoryKey], () => {
    currentProductPage.value = 1;
});

watch(
    totalProductPages,
    (totalPages) => {
        if (currentProductPage.value > totalPages) {
            currentProductPage.value = totalPages;
            return;
        }

        if (currentProductPage.value < 1) {
            currentProductPage.value = 1;
        }
    },
    { immediate: true },
);

watch(
    categoryBadges,
    (badges) => {
        if (!badges.some((badge) => badge.key === activeCategoryKey.value)) {
            activeCategoryKey.value = 'all';
        }
    },
    { immediate: true },
);

const focusProductSearch = () => {
    nextTick(() => {
        productSearchInput.value?.focus?.();
        productSearchInput.value?.select?.();
    });
};

const isTypingField = (target) => {
    if (!target || typeof target !== 'object') return false;

    const tagName = String(target.tagName ?? '').toUpperCase();
    if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') return true;

    return Boolean(target.isContentEditable);
};

const closeTopMostModal = () => {
    if (clientPickerOpen.value) {
        clientPickerOpen.value = false;
        return true;
    }

    if (createClientModalOpen.value) {
        createClientModalOpen.value = false;
        return true;
    }

    if (featuredProductsModalOpen.value) {
        featuredProductsModalOpen.value = false;
        return true;
    }

    if (closeCashModalOpen.value) {
        closeCashModalOpen.value = false;
        return true;
    }

    if (openCashModalOpen.value) {
        openCashModalOpen.value = false;
        return true;
    }

    return false;
};

const handleHotkeys = (event) => {
    const key = String(event.key ?? '').toLowerCase();
    const typing = isTypingField(event.target);

    if ((event.ctrlKey || event.metaKey) && key === 'k') {
        event.preventDefault();
        focusProductSearch();
        return;
    }

    if ((event.ctrlKey || event.metaKey) && key === 'n') {
        event.preventDefault();
        openCreateClientModal();
        return;
    }

    if (key === 'f9') {
        event.preventDefault();
        finalizeSale();
        return;
    }

    if (key === 'f8') {
        event.preventDefault();
        if (hasOpenCashSession.value) {
            openCloseCashModal();
        } else {
            openCashModalOpen.value = true;
        }
        return;
    }

    if (key !== 'escape') return;
    event.preventDefault();

    if (closeTopMostModal()) return;

    if (typing) return;

    if (productSearch.value) {
        productSearch.value = '';
        focusProductSearch();
    }
};

const handleBeforeUnload = (event) => {
    if (!hasPendingCart.value) return;

    event.preventDefault();
    event.returnValue = '';
};

const handleClientPickerClickOutside = (event) => {
    if (!clientPickerOpen.value || !clientPickerRoot.value) return;
    if (clientPickerRoot.value.contains(event.target)) return;
    clientPickerOpen.value = false;
};

onMounted(() => {
    if (props.initialAction === 'open-cash' && !hasOpenCashSession.value) {
        openCashModalOpen.value = true;
    }

    if (props.initialAction === 'close-cash' && hasOpenCashSession.value) {
        openCloseCashModal();
    }

    window.addEventListener('beforeunload', handleBeforeUnload);
    document.addEventListener('keydown', handleHotkeys);
    document.addEventListener('mousedown', handleClientPickerClickOutside);
    document.addEventListener('touchstart', handleClientPickerClickOutside, { passive: true });

    focusProductSearch();
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    document.removeEventListener('keydown', handleHotkeys);
    document.removeEventListener('mousedown', handleClientPickerClickOutside);
    document.removeEventListener('touchstart', handleClientPickerClickOutside);
});

function normalizeMoneyInput(value) {
    const parsed = Number(String(value ?? '').replace(',', '.'));
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

function asCurrency(value) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function buildCartItemKey(productId, variationId = null) {
    const safeProductId = Number(productId) || 0;
    const safeVariationId = Number(variationId) || 0;
    return `${safeProductId}:${safeVariationId}`;
}

function resolveDefaultVariation(product) {
    const variations = Array.isArray(product?.variations) ? product.variations : [];
    return variations.find((variation) => Number(variation?.stock_quantity ?? 0) > 0) ?? null;
}

function closeVariationPickerModal() {
    variationPickerModalOpen.value = false;
    variationPickerProduct.value = null;
    variationPickerVariationId.value = '';
}

function openVariationPickerModal(product) {
    const variations = Array.isArray(product?.variations)
        ? product.variations.filter((variation) => Number(variation?.stock_quantity ?? 0) > 0)
        : [];

    if (!variations.length) return;

    variationPickerProduct.value = {
        ...product,
        variations,
    };
    variationPickerVariationId.value = String(variations[0].id);
    variationPickerModalOpen.value = true;
}

function addProductVariationToCart(product, variationId) {
    const variations = Array.isArray(product?.variations) ? product.variations : [];
    const selectedVariation = variations.find((variation) => Number(variation.id) === Number(variationId)) ?? null;
    if (!selectedVariation) return;

    const productId = Number(product.id);
    const safeVariationId = Number(selectedVariation.id);
    const lineKey = buildCartItemKey(productId, safeVariationId);
    const lineStock = Number(selectedVariation.stock_quantity ?? 0);
    const linePrice = Number(selectedVariation.sale_price ?? 0);
    const lineSku = selectedVariation.sku || product.sku || null;
    const lineName = `${product.name} • ${selectedVariation.name}`;

    const existing = cartItems.value.find((item) => String(item.key) === lineKey);

    if (existing) {
        if (existing.quantity < Number(existing.stock_quantity ?? 0)) existing.quantity += 1;
        return;
    }

    cartItems.value.push({
        key: lineKey,
        product_id: productId,
        variation_id: safeVariationId,
        variation_name: selectedVariation.name,
        name: lineName,
        sku: lineSku,
        image_url: product.image_url || '',
        quantity: 1,
        unit_price: linePrice,
        stock_quantity: lineStock,
    });
}

function addToCart(product) {
    const hasVariations = Boolean(product?.has_variations);
    const variations = Array.isArray(product?.variations)
        ? product.variations.filter((variation) => Number(variation?.stock_quantity ?? 0) > 0)
        : [];

    if (hasVariations && !variations.length) return;

    if (hasVariations && variations.length > 1) {
        openVariationPickerModal(product);
        return;
    }

    if (hasVariations) {
        addProductVariationToCart(product, variations[0]?.id);
        return;
    }

    const productId = Number(product.id);
    const lineKey = buildCartItemKey(productId, null);
    const lineStock = Number(product.stock_quantity ?? 0);
    const linePrice = Number(product.sale_price ?? 0);
    const lineSku = product.sku || null;
    const lineName = product.name;

    const existing = cartItems.value.find((item) => String(item.key) === lineKey);

    if (existing) {
        if (existing.quantity < Number(existing.stock_quantity ?? 0)) existing.quantity += 1;
        return;
    }

    cartItems.value.push({
        key: lineKey,
        product_id: productId,
        variation_id: variationId,
        variation_name: defaultVariation?.name ?? '',
        name: lineName,
        sku: lineSku,
        image_url: product.image_url || '',
        quantity: 1,
        unit_price: linePrice,
        stock_quantity: lineStock,
    });
}

function confirmVariationPickerSelection() {
    const product = variationPickerProduct.value;
    if (!product) return;

    addProductVariationToCart(product, variationPickerVariationId.value);
    closeVariationPickerModal();
}

function removeFromCart(productId, variationId = null) {
    const lineKey = buildCartItemKey(productId, variationId);
    cartItems.value = cartItems.value.filter((item) => String(item.key) !== lineKey);
}

function increase(item) {
    if (item.quantity < item.stock_quantity) item.quantity += 1;
}

function decrease(item) {
    if (item.quantity <= 1) {
        removeFromCart(item.product_id, item.variation_id);
        return;
    }

    item.quantity -= 1;
}

function openCloseCashModal() {
    closeCashForm.clearErrors();
    closeCashForm.closing_balance = Number(props.cashSummary?.expected_balance ?? 0).toFixed(2);
    closeCashModalOpen.value = true;
}

function submitOpenCash() {
    openCashForm.post(route('admin.pdv.cash.open'), {
        preserveScroll: true,
        onSuccess: () => {
            openCashModalOpen.value = false;
        },
    });
}

function submitCloseCash() {
    closeCashForm.post(route('admin.pdv.cash.close'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCashModalOpen.value = false;
        },
    });
}

function finalizeSale() {
    if (!canFinalizeSale.value) return;

    saleForm.client_id = selectedClientId.value ? Number(selectedClientId.value) : null;
    saleForm.payment_method_id = selectedPaymentMethodId.value ? Number(selectedPaymentMethodId.value) : null;
    saleForm.installments = selectedPaymentMethod.value?.allows_installments
        ? Number(installments.value || 0) || null
        : null;
    saleForm.discount_amount = discountValue.value;
    saleForm.surcharge_amount = manualSurchargeValue.value;
    saleForm.notes = notes.value;
    saleForm.items = cartItems.value.map((item) => ({
        product_id: item.product_id,
        variation_id: item.variation_id || null,
        quantity: item.quantity,
    }));

    saleForm.post(route('admin.pdv.sales.store'), {
        preserveScroll: true,
        onSuccess: () => {
            cartItems.value = [];
            selectedClientId.value = '';
            discountAmount.value = '';
            surchargeAmount.value = '';
            amountPaid.value = '';
            notes.value = '';
            focusProductSearch();
        },
    });
}

function setActiveCategory(categoryKey) {
    activeCategoryKey.value = String(categoryKey ?? 'all');
}

function scrollCategoryBadges(direction = 1) {
    const element = categoryCarouselRef.value;
    if (!element) return;

    const scrollDistance = Math.max(180, Math.floor(Number(element.clientWidth ?? 0) * 0.75));
    element.scrollBy({
        left: direction * scrollDistance,
        behavior: 'smooth',
    });
}

function goToProductPage(page) {
    const target = Number(page) || 1;
    currentProductPage.value = Math.min(totalProductPages.value, Math.max(1, target));
}

function openClientPicker() {
    clientPickerOpen.value = true;
    nextTick(() => {
        clientPickerInput.value?.focus?.();
    });
}

function toggleClientPicker() {
    if (clientPickerOpen.value) {
        clientPickerOpen.value = false;
        return;
    }

    openClientPicker();
}

function clearClientSelection() {
    selectedClientId.value = '';
    clientSearch.value = '';
}

function selectClientFromPicker(clientId = '') {
    selectedClientId.value = clientId ? String(clientId) : '';
    clientSearch.value = '';
    clientPickerOpen.value = false;
}

function openFeaturedProductsModal() {
    featuredProductsSearch.value = '';
    featuredProductIds.value = productsSafe.value
        .filter((product) => Boolean(product.is_pdv_featured))
        .sort((a, b) => Number(a.pdv_featured_order ?? 99) - Number(b.pdv_featured_order ?? 99))
        .map((product) => Number(product.id));
    featuredProductsModalOpen.value = true;
}

function isFeatured(productId) {
    return featuredProductIds.value.includes(Number(productId));
}

function toggleFeaturedProduct(productId) {
    const id = Number(productId);
    const index = featuredProductIds.value.indexOf(id);

    if (index >= 0) {
        featuredProductIds.value.splice(index, 1);
        return;
    }

    if (featuredLimitReached.value) return;
    featuredProductIds.value.push(id);
}

function moveFeaturedProduct(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= featuredProductIds.value.length) return;

    const reordered = [...featuredProductIds.value];
    const [moved] = reordered.splice(index, 1);
    reordered.splice(target, 0, moved);
    featuredProductIds.value = reordered;
}

function saveFeaturedProducts() {
    featuredProductsForm.product_ids = featuredProductIds.value.map((id) => Number(id));
    featuredProductsForm.put(route('admin.pdv.products.featured.update'), {
        preserveScroll: true,
        onSuccess: () => {
            featuredProductsModalOpen.value = false;
        },
    });
}

function openCreateClientModal() {
    clientPickerOpen.value = false;
    createClientForm.reset();
    createClientForm.clearErrors();
    createClientModalOpen.value = true;
}

function submitCreateClient() {
    createClientForm.post(route('admin.pdv.clients.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createClientModalOpen.value = false;
            clientSearch.value = '';
        },
    });
}
</script>

<template>
    <Head title="PDV" />

    <PdvLayout title="PDV" subtitle="Operação de frente de caixa" :confirm-leave="hasPendingCart">
        <template #status>
            <div class="flex w-full flex-wrap items-center gap-2 md:justify-center">
                <span
                    v-if="hasOpenCashSession"
                    class="inline-flex max-w-full items-center truncate rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                >
                    {{ props.cashSession.code }}
                </span>
                <span class="text-xs font-medium text-slate-600 md:text-sm">
                    {{ hasOpenCashSession ? `Aberto em ${props.cashSession.opened_at}` : 'Nenhum caixa aberto no momento' }}
                </span>
                <span class="hidden rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600 lg:inline-flex">
                    Ctrl+K buscar
                </span>
                <span class="hidden rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600 lg:inline-flex">
                    F9 finalizar
                </span>
            </div>
        </template>

        <template #actions>
            <button
                v-if="!hasOpenCashSession"
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
                @click="openCashModalOpen = true"
            >
                <Unlock class="h-4 w-4" />
                <span class="sm:hidden">Abrir</span>
                <span class="hidden sm:inline">Abrir caixa</span>
            </button>
            <button
                v-else
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700"
                @click="openCloseCashModal"
            >
                <Lock class="h-4 w-4" />
                <span class="sm:hidden">Fechar</span>
                <span class="hidden sm:inline">Fechar caixa</span>
            </button>
        </template>

        <section class="pdv-touch-mode min-w-0 space-y-4 overflow-x-hidden md:flex md:h-full md:min-h-0 md:flex-col">

            <div class="grid min-w-0 gap-4 md:h-full md:min-h-0 lg:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)]">
                <section class="min-w-0 rounded-2xl border border-slate-300 bg-white p-3 shadow-sm md:flex md:h-full md:min-h-0 md:flex-col md:p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-sm font-semibold text-slate-900">Produtos</h2>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="openFeaturedProductsModal"
                        >
                            <Star class="h-4 w-4" />
                            Top 12 do PDV
                        </button>
                    </div>

                    <div class="veshop-search-shell mt-3 flex items-center gap-2 rounded-xl border border-slate-300 bg-slate-50 px-3 py-2">
                        <Search class="h-4 w-4 text-slate-500" />
                        <input
                            ref="productSearchInput"
                            v-model="productSearch"
                            type="text"
                            placeholder="Buscar produto por nome ou SKU"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                        >
                        <button
                            v-if="productSearch"
                            type="button"
                            class="rounded p-1 text-slate-500 transition hover:bg-slate-200"
                            @click="productSearch = ''"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50/70 p-2">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 transition hover:bg-slate-100"
                                @click="scrollCategoryBadges(-1)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </button>
                            <div ref="categoryCarouselRef" class="no-scrollbar flex min-w-0 flex-1 gap-2 overflow-x-auto py-1">
                                <button
                                    v-for="category in categoryBadges"
                                    :key="'pdv-category-' + category.key"
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                    :class="activeCategoryKey === category.key
                                        ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                                        : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'"
                                    @click="setActiveCategory(category.key)"
                                >
                                    <span class="truncate">{{ category.label }}</span>
                                    <span
                                        class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold"
                                        :class="activeCategoryKey === category.key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ category.count }}
                                    </span>
                                </button>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 transition hover:bg-slate-100"
                                @click="scrollCategoryBadges(1)"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span>{{ filteredProducts.length }} produto(s)</span>
                        <span>{{ activeCategoryBadge?.label || 'Todas categorias' }}</span>
                    </div>
                    <div v-if="!filteredProducts.length" class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                        Nenhum produto encontrado para o filtro aplicado.
                    </div>
                    <div v-else class="mt-4 md:min-h-0 md:flex-1 md:overflow-y-auto md:pr-1">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <button
                                v-for="product in paginatedProducts"
                                :key="'paged-product-' + product.id"
                                type="button"
                                class="group relative flex min-w-0 flex-col overflow-hidden rounded-xl border border-slate-300 bg-white p-2 text-left shadow-sm transition hover:border-slate-400 hover:shadow-md"
                                @click="addToCart(product)"
                            >
                                <div class="h-20 overflow-hidden rounded-lg border border-slate-200 bg-slate-50 md:h-24">
                                    <img
                                        v-if="product.image_url"
                                        :src="product.image_url"
                                        :alt="product.name"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    >
                                    <div v-else class="flex h-full w-full items-center justify-center bg-slate-100 text-[10px] font-semibold uppercase text-slate-500">
                                        sem foto
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="line-clamp-2 text-xs font-semibold text-slate-900">{{ product.name }}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        {{ asCurrency(product.sale_price) }} - Estoque {{ product.stock_quantity }}
                                    </p>
                                </div>
                                <div
                                    class="pointer-events-none absolute inset-0 rounded-xl bg-slate-900/0 transition duration-200 group-hover:bg-slate-900/45 group-active:bg-slate-900/45"
                                />
                                <div
                                    class="pointer-events-none absolute inset-0 flex items-center justify-center opacity-0 transition duration-200 group-hover:opacity-100 group-active:opacity-100"
                                >
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-[11px] font-semibold text-slate-900 shadow">
                                        <Plus class="h-3.5 w-3.5" />
                                        Adicionar
                                    </span>
                                </div>
                            </button>
                        </div>
                        <div
                            v-if="totalProductPages > 1"
                            class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3"
                        >
                            <p class="text-xs text-slate-500">
                                Página {{ currentProductPage }} de {{ totalProductPages }}
                            </p>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="currentProductPage <= 1"
                                    @click="goToProductPage(currentProductPage - 1)"
                                >
                                    <ChevronLeft class="h-3.5 w-3.5" />
                                    <span class="hidden sm:inline">Anterior</span>
                                </button>
                                <button
                                    v-for="page in productPageNumbers"
                                    :key="'pdv-page-' + page"
                                    type="button"
                                    class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border px-2 text-xs font-semibold transition"
                                    :class="page === currentProductPage
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'"
                                    @click="goToProductPage(page)"
                                >
                                    {{ page }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="currentProductPage >= totalProductPages"
                                    @click="goToProductPage(currentProductPage + 1)"
                                >
                                    <span class="hidden sm:inline">Próxima</span>
                                    <ChevronRight class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="min-w-0 rounded-2xl border border-slate-300 bg-white p-3 shadow-sm md:flex md:h-full md:min-h-0 md:flex-col md:p-4">
                    <h2 class="text-sm font-semibold text-slate-900">Carrinho</h2>

                    <div class="mt-3 space-y-2 md:max-h-44 md:overflow-y-auto md:pr-1">
                        <article
                            v-for="item in cartItems"
                            :key="item.key"
                            class="rounded-xl border border-slate-300 bg-slate-50/80 px-3 py-2"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <div class="h-11 w-11 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-white">
                                        <img
                                            v-if="item.image_url"
                                            :src="item.image_url"
                                            :alt="item.name"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >
                                        <div v-else class="flex h-full w-full items-center justify-center bg-slate-100 text-[10px] font-semibold uppercase text-slate-500">
                                            sem
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ item.name }}</p>
                                        <p v-if="item.sku" class="truncate text-[11px] text-slate-500">{{ item.sku }}</p>
                                    </div>
                                </div>
                                <button type="button" class="rounded p-1 text-slate-500 transition hover:bg-slate-200" @click="removeFromCart(item.product_id, item.variation_id)">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1">
                                    <button type="button" class="rounded p-1.5 transition hover:bg-slate-100" @click="decrease(item)">
                                        <Minus class="h-4 w-4" />
                                    </button>
                                    <span class="min-w-[2rem] text-center text-xs font-semibold">{{ item.quantity }}</span>
                                    <button type="button" class="rounded p-1.5 transition hover:bg-slate-100" @click="increase(item)">
                                        <Plus class="h-4 w-4" />
                                    </button>
                                </div>
                                <p class="text-sm font-semibold text-slate-900">{{ asCurrency(item.quantity * item.unit_price) }}</p>
                            </div>
                        </article>
                    </div>

                    <div v-if="!cartItems.length" class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-center text-xs text-slate-500">
                        Adicione produtos para iniciar a venda.
                    </div>

                    <div class="mt-4 space-y-3 border-t border-slate-200 pt-4 md:min-h-0 md:flex-1 md:overflow-y-auto md:pr-1">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto]">
                            <div ref="clientPickerRoot" class="relative min-w-0">
                                <button
                                    type="button"
                                    class="inline-flex w-full items-center justify-between gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                    @click="toggleClientPicker"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <Search class="h-4 w-4 shrink-0 text-slate-500" />
                                        <span class="truncate">{{ clientPickerLabel }}</span>
                                    </span>
                                    <ChevronDown class="h-4 w-4 shrink-0 text-slate-500 transition-transform" :class="clientPickerOpen ? 'rotate-180' : ''" />
                                </button>

                                <Transition
                                    enter-active-class="transition ease-out duration-150"
                                    enter-from-class="opacity-0 -translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-100"
                                    leave-from-class="opacity-100 translate-y-0"
                                    leave-to-class="opacity-0 -translate-y-1"
                                >
                                    <div
                                        v-if="clientPickerOpen"
                                        class="absolute left-0 top-[calc(100%+0.35rem)] z-[90] w-full min-w-0 sm:min-w-[15rem] rounded-xl border border-slate-200 bg-white p-2 shadow-[0_22px_50px_-32px_rgba(15,23,42,0.95)]"
                                    >
                                        <div class="veshop-search-shell flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2">
                                            <Search class="h-4 w-4 text-slate-500" />
                                            <input
                                                ref="clientPickerInput"
                                                v-model="clientSearch"
                                                type="text"
                                                placeholder="Pesquisar cliente"
                                                class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                                            >
                                            <button
                                                v-if="clientSearch"
                                                type="button"
                                                class="rounded p-1 text-slate-500 transition hover:bg-slate-200"
                                                @click="clientSearch = ''"
                                            >
                                                <X class="h-3.5 w-3.5" />
                                            </button>
                                        </div>

                                        <div class="mt-2 max-h-48 space-y-1 overflow-y-auto pr-1">
                                            <button
                                                type="button"
                                                class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition hover:bg-slate-50"
                                                :class="!selectedClientId ? 'bg-slate-100 font-semibold text-slate-900' : 'text-slate-700'"
                                                @click="selectClientFromPicker('')"
                                            >
                                                <span class="truncate">Consumidor final</span>
                                                <Check v-if="!selectedClientId" class="h-4 w-4 shrink-0 text-slate-600" />
                                            </button>

                                            <button
                                                v-for="client in filteredClients"
                                                :key="`pdv-client-${client.id}`"
                                                type="button"
                                                class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition hover:bg-slate-50"
                                                :class="String(selectedClientId) === String(client.id) ? 'bg-slate-100 font-semibold text-slate-900' : 'text-slate-700'"
                                                @click="selectClientFromPicker(client.id)"
                                            >
                                                <span class="truncate">{{ client.name }}</span>
                                                <Check v-if="String(selectedClientId) === String(client.id)" class="h-4 w-4 shrink-0 text-slate-600" />
                                            </button>

                                            <div v-if="!filteredClients.length" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-500">
                                                Nenhum cliente encontrado.
                                            </div>
                                        </div>

                                        <div class="mt-2 flex items-center justify-between border-t border-slate-100 pt-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-100"
                                                @click="clearClientSelection"
                                            >
                                                <X class="h-3.5 w-3.5" />
                                                Limpar
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-100"
                                                @click="clientPickerOpen = false"
                                            >
                                                Fechar
                                            </button>
                                        </div>
                                    </div>
                                </Transition>
                            </div>

                            <button
                                type="button"
                                class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto"
                                @click="openCreateClientModal"
                            >
                                <UserPlus class="h-4 w-4" />
                                Novo cliente
                            </button>
                        </div>

                        <UiSelect v-model="selectedPaymentMethodId" :options="paymentMethodOptions" placeholder="Forma de pagamento" />
                        <p v-if="paymentFeeHint" class="text-xs font-medium text-slate-500">
                            {{ paymentFeeHint }}
                        </p>
                        <UiSelect
                            v-if="selectedPaymentMethod?.allows_installments"
                            v-model="installments"
                            :options="installmentOptions"
                            placeholder="Parcelamento"
                        />

                        <div class="grid gap-2 sm:grid-cols-2">
                            <input
                                v-model="discountAmount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Desconto (R$)"
                            >
                            <input
                                v-model="surchargeAmount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Acréscimo (R$)"
                            >
                        </div>

                        <div v-if="isCashPayment" class="grid gap-2 sm:grid-cols-2">
                            <input
                                v-model="amountPaid"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Valor pago (R$)"
                            >
                            <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Troco</p>
                                <p class="mt-1 text-base font-bold text-emerald-700">{{ asCurrency(changeAmount) }}</p>
                            </div>
                        </div>
                        <textarea
                            v-model="notes"
                            rows="2"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Observações"
                        />

                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span>Subtotal</span>
                                <span class="font-semibold">{{ asCurrency(subtotalAmount) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between">
                                <span>Desconto</span>
                                <span class="font-semibold">- {{ asCurrency(discountValue) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between">
                                <span>Acréscimo</span>
                                <span class="font-semibold">+ {{ asCurrency(manualSurchargeValue) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between">
                                <span>Taxa de pagamento</span>
                                <span class="font-semibold">+ {{ asCurrency(paymentFeeAmount) }}</span>
                            </div>
                            <div v-if="isCashPayment" class="mt-1 flex items-center justify-between">
                                <span>Valor pago</span>
                                <span class="font-semibold">{{ asCurrency(paidAmount) }}</span>
                            </div>
                            <div v-if="isCashPayment" class="mt-1 flex items-center justify-between">
                                <span>Troco</span>
                                <span class="font-semibold text-emerald-700">{{ asCurrency(changeAmount) }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between border-t border-slate-200 pt-2">
                                <span class="font-semibold">Total</span>
                                <span class="text-base font-bold">{{ asCurrency(totalAmount) }}</span>
                            </div>
                        </div>

                        <p v-if="isCashPayment && missingAmount > 0" class="text-xs font-semibold text-rose-600">
                            Faltam {{ asCurrency(missingAmount) }} para concluir no dinheiro.
                        </p>

                        <p
                            v-if="saleForm.errors.cash_session || saleForm.errors.items || saleForm.errors.payment_method_id"
                            class="text-xs font-semibold text-rose-600"
                        >
                            {{ saleForm.errors.cash_session || saleForm.errors.items || saleForm.errors.payment_method_id }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-base font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400"
                            :disabled="saleForm.processing || !canFinalizeSale"
                            @click="finalizeSale"
                        >
                            <ReceiptText class="h-5 w-5" />
                            {{ saleForm.processing ? 'Finalizando...' : 'Finalizar venda' }}
                        </button>
                    </div>
                </section>
            </div>
        </section>

        <Modal :show="openCashModalOpen" max-width="5xl" @close="openCashModalOpen = false">
            <WizardModalFrame title="Abrir caixa" description="Informe o saldo inicial." :steps="['Abertura']" :current-step="1" @close="openCashModalOpen = false">
                <div class="space-y-3">
                    <input
                        v-model="openCashForm.opening_balance"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        placeholder="Saldo inicial (R$)"
                    >
                    <textarea
                        v-model="openCashForm.notes"
                        rows="3"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        placeholder="Observações"
                    />
                    <p v-if="openCashForm.errors.opening_balance || openCashForm.errors.cash_session" class="text-xs font-semibold text-rose-600">
                        {{ openCashForm.errors.opening_balance || openCashForm.errors.cash_session }}
                    </p>
                </div>
                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" @click="openCashModalOpen = false">
                            Cancelar
                        </button>
                        <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60" :disabled="openCashForm.processing" @click="submitOpenCash">
                            {{ openCashForm.processing ? 'Abrindo...' : 'Abrir caixa' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <Modal :show="closeCashModalOpen" max-width="5xl" @close="closeCashModalOpen = false">
            <WizardModalFrame title="Fechar caixa" description="Informe o saldo final apurado." :steps="['Fechamento']" :current-step="1" @close="closeCashModalOpen = false">
                <div class="space-y-3">
                    <p class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        Saldo esperado:
                        <span class="font-semibold">{{ asCurrency(props.cashSummary?.expected_balance ?? 0) }}</span>
                    </p>
                    <input
                        v-model="closeCashForm.closing_balance"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        placeholder="Saldo final (R$)"
                    >
                    <textarea
                        v-model="closeCashForm.notes"
                        rows="3"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        placeholder="Observações"
                    />
                    <p v-if="closeCashForm.errors.closing_balance || closeCashForm.errors.cash_session" class="text-xs font-semibold text-rose-600">
                        {{ closeCashForm.errors.closing_balance || closeCashForm.errors.cash_session }}
                    </p>
                </div>
                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" @click="closeCashModalOpen = false">
                            Cancelar
                        </button>
                        <button type="button" class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-700 disabled:opacity-60" :disabled="closeCashForm.processing" @click="submitCloseCash">
                            {{ closeCashForm.processing ? 'Fechando...' : 'Fechar caixa' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <Modal :show="variationPickerModalOpen" max-width="5xl" @close="closeVariationPickerModal">
            <WizardModalFrame
                title="Selecionar variação"
                description="Escolha a variação para adicionar ao carrinho."
                :steps="['Variação']"
                :current-step="1"
                @close="closeVariationPickerModal"
            >
                <div class="space-y-3">
                    <p class="text-sm font-semibold text-slate-900">
                        {{ variationPickerProduct?.name || 'Produto' }}
                    </p>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Variação disponível</label>
                        <select
                            v-model="variationPickerVariationId"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                            <option
                                v-for="variation in variationPickerProduct?.variations || []"
                                :key="`pdv-variation-picker-${variation.id}`"
                                :value="String(variation.id)"
                            >
                                {{ variation.name }} - {{ asCurrency(variation.sale_price) }} - estoque {{ variation.stock_quantity }}
                            </option>
                        </select>
                    </div>
                </div>
                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="closeVariationPickerModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800"
                            @click="confirmVariationPickerSelection"
                        >
                            Adicionar
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <Modal :show="featuredProductsModalOpen" max-width="5xl" @close="featuredProductsModalOpen = false">
            <WizardModalFrame
                title="Top 12 produtos do PDV"
                description="Defina a ordem dos primeiros produtos exibidos no PDV."
                :steps="['Produtos em destaque']"
                :current-step="1"
                @close="featuredProductsModalOpen = false"
            >
                <div class="grid gap-4 lg:grid-cols-[1.3fr_1fr]">
                    <div class="space-y-3">
                        <div class="veshop-search-shell flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <Search class="h-4 w-4 text-slate-500" />
                            <input
                                v-model="featuredProductsSearch"
                                type="text"
                                placeholder="Buscar produto"
                                class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                            >
                        </div>

                        <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                            <button
                                v-for="product in featuredProductsFiltered"
                                :key="`featured-product-${product.id}`"
                                type="button"
                                class="flex w-full items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left transition"
                                :class="isFeatured(product.id) ? 'border-emerald-300 bg-emerald-50/70' : 'border-slate-200 bg-white hover:bg-slate-50'"
                                @click="toggleFeaturedProduct(product.id)"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ product.name }}</p>
                                    <p class="text-xs text-slate-500">{{ asCurrency(product.sale_price) }} - Estoque {{ product.stock_quantity }}</p>
                                </div>
                                <span
                                    v-if="isFeatured(product.id)"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-white"
                                >
                                    <Check class="h-3.5 w-3.5" />
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-900">Selecionados</p>
                            <span class="text-xs font-semibold text-slate-500">{{ featuredProductIds.length }}/12</span>
                        </div>

                        <div class="mt-3 space-y-2">
                            <div v-if="!selectedFeaturedProducts.length" class="rounded-lg border border-dashed border-slate-200 bg-white px-3 py-3 text-xs text-slate-500">
                                Nenhum produto selecionado.
                            </div>
                            <div
                                v-for="(product, index) in selectedFeaturedProducts"
                                :key="`selected-featured-${product.id}`"
                                class="rounded-lg border border-slate-200 bg-white px-2.5 py-2"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-slate-900">#{{ index + 1 }} - {{ product.name }}</p>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="rounded p-1 text-slate-500 transition hover:bg-slate-100"
                                            :disabled="index === 0"
                                            @click="moveFeaturedProduct(index, -1)"
                                        >
                                            <ArrowUp class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded p-1 text-slate-500 transition hover:bg-slate-100"
                                            :disabled="index === selectedFeaturedProducts.length - 1"
                                            @click="moveFeaturedProduct(index, 1)"
                                        >
                                            <ArrowDown class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-if="featuredLimitReached" class="mt-3 text-xs font-semibold text-amber-700">
                            Limite de 12 produtos atingido.
                        </p>
                        <p v-if="featuredProductsForm.errors.product_ids" class="mt-2 text-xs font-semibold text-rose-600">
                            {{ featuredProductsForm.errors.product_ids }}
                        </p>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" @click="featuredProductsModalOpen = false">
                            Cancelar
                        </button>
                        <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60" :disabled="featuredProductsForm.processing" @click="saveFeaturedProducts">
                            {{ featuredProductsForm.processing ? 'Salvando...' : 'Salvar top 12' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <Modal :show="createClientModalOpen" max-width="5xl" @close="createClientModalOpen = false">
            <WizardModalFrame
                title="Cadastrar cliente no PDV"
                description="Preencha os dados essenciais para usar o cliente na venda."
                :steps="['Cliente']"
                :current-step="1"
                @close="createClientModalOpen = false"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Nome</label>
                        <input v-model="createClientForm.name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Nome do cliente">
                        <p v-if="createClientForm.errors.name" class="mt-1 text-xs font-semibold text-rose-600">{{ createClientForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">E-mail</label>
                        <input v-model="createClientForm.email" type="email" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="cliente@email.com">
                        <p v-if="createClientForm.errors.email" class="mt-1 text-xs font-semibold text-rose-600">{{ createClientForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Telefone</label>
                        <input v-model="createClientForm.phone" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="(00) 00000-0000">
                        <p v-if="createClientForm.errors.phone" class="mt-1 text-xs font-semibold text-rose-600">{{ createClientForm.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Documento</label>
                        <input v-model="createClientForm.document" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="CPF/CNPJ">
                        <p v-if="createClientForm.errors.document" class="mt-1 text-xs font-semibold text-rose-600">{{ createClientForm.errors.document }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Cidade</label>
                        <input v-model="createClientForm.city" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Cidade">
                        <p v-if="createClientForm.errors.city" class="mt-1 text-xs font-semibold text-rose-600">{{ createClientForm.errors.city }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">UF</label>
                        <input v-model="createClientForm.state" type="text" maxlength="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm uppercase text-slate-700" placeholder="BA">
                        <p v-if="createClientForm.errors.state" class="mt-1 text-xs font-semibold text-rose-600">{{ createClientForm.errors.state }}</p>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" @click="createClientModalOpen = false">
                            Cancelar
                        </button>
                        <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60" :disabled="createClientForm.processing" @click="submitCreateClient">
                            {{ createClientForm.processing ? 'Salvando...' : 'Salvar cliente' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>
    </PdvLayout>
</template>

<style scoped>
.pdv-touch-mode {
    overflow-x: clip;
}

@media (min-width: 1024px) {
    .pdv-touch-mode :deep(button) {
        min-height: 48px;
    }

    .pdv-touch-mode :deep(input),
    .pdv-touch-mode :deep(select),
    .pdv-touch-mode :deep(textarea) {
        min-height: 46px;
    }

    .pdv-touch-mode :deep(textarea) {
        min-height: 84px;
    }
}

.pdv-touch-mode :deep(.no-scrollbar) {
    scrollbar-width: none;
}

.pdv-touch-mode :deep(.no-scrollbar::-webkit-scrollbar) {
    display: none;
}
</style>
