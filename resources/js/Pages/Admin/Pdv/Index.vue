<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
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
const currentProductPage = ref(1);
const productsPerPage = 12;

const clientSearch = ref('');
const selectedClientId = ref('');
const selectedPaymentMethodId = ref('');
const installments = ref('');
const discountAmount = ref('');
const surchargeAmount = ref('');
const notes = ref('');
const cartItems = ref([]);

const openCashModalOpen = ref(false);
const closeCashModalOpen = ref(false);
const featuredProductsModalOpen = ref(false);
const featuredProductsSearch = ref('');
const featuredProductIds = ref([]);
const createClientModalOpen = ref(false);

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

const paymentMethodOptions = computed(() =>
    (props.paymentMethods ?? []).map((method) => ({
        value: String(method.id),
        label: method.name,
    })),
);

const selectedPaymentMethod = computed(() =>
    (props.paymentMethods ?? []).find((method) => String(method.id) === String(selectedPaymentMethodId.value)) ?? null,
);

const installmentOptions = computed(() => {
    if (!selectedPaymentMethod.value?.allows_installments) return [];

    const max = Number(selectedPaymentMethod.value?.max_installments ?? 12) || 12;
    return Array.from({ length: Math.max(0, max - 1) }).map((_, index) => ({
        value: String(index + 2),
        label: `${index + 2}x`,
    }));
});

const filteredProducts = computed(() => {
    const query = String(productSearch.value ?? '').trim().toLowerCase();
    if (!query) return productsSafe.value;

    return productsSafe.value.filter((product) =>
        String(product.name ?? '').toLowerCase().includes(query)
        || String(product.sku ?? '').toLowerCase().includes(query),
    );
});

const totalProductPages = computed(() => Math.max(1, Math.ceil(filteredProducts.value.length / productsPerPage)));

const paginatedProducts = computed(() => {
    const start = (currentProductPage.value - 1) * productsPerPage;
    return filteredProducts.value.slice(start, start + productsPerPage);
});

const productPageNumbers = computed(() => {
    const pages = totalProductPages.value;
    if (pages <= 7) return Array.from({ length: pages }).map((_, index) => index + 1);

    const current = currentProductPage.value;
    const numbers = new Set([1, pages, current - 1, current, current + 1]);
    return Array.from(numbers)
        .filter((page) => page >= 1 && page <= pages)
        .sort((a, b) => a - b);
});

const filteredClients = computed(() => {
    const query = String(clientSearch.value ?? '').trim().toLowerCase();
    if (!query) return clientsSafe.value;

    return clientsSafe.value.filter((client) => String(client.name ?? '').toLowerCase().includes(query));
});

const clientOptions = computed(() => [
    { value: '', label: 'Consumidor final' },
    ...filteredClients.value.map((client) => ({
        value: String(client.id),
        label: client.name,
    })),
]);

const subtotalAmount = computed(() =>
    cartItems.value.reduce((sum, item) => sum + (Number(item.quantity) * Number(item.unit_price)), 0),
);

const discountValue = computed(() => normalizeMoneyInput(discountAmount.value));
const surchargeValue = computed(() => normalizeMoneyInput(surchargeAmount.value));
const totalAmount = computed(() => Math.max(0, subtotalAmount.value - discountValue.value + surchargeValue.value));

const canFinalizeSale = computed(() =>
    hasOpenCashSession.value
    && cartItems.value.length > 0
    && Boolean(selectedPaymentMethodId.value)
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

watch(productSearch, () => {
    currentProductPage.value = 1;
});

watch(filteredProducts, () => {
    if (currentProductPage.value > totalProductPages.value) {
        currentProductPage.value = totalProductPages.value;
    }
});

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
            return;
        }

        if (!installments.value) installments.value = '2';
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

onMounted(() => {
    if (props.initialAction === 'open-cash' && !hasOpenCashSession.value) {
        openCashModalOpen.value = true;
    }

    if (props.initialAction === 'close-cash' && hasOpenCashSession.value) {
        openCloseCashModal();
    }
});

function normalizeMoneyInput(value) {
    const parsed = Number(String(value ?? '').replace(',', '.'));
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

function asCurrency(value) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function addToCart(product) {
    const existing = cartItems.value.find((item) => Number(item.product_id) === Number(product.id));

    if (existing) {
        if (existing.quantity < Number(existing.stock_quantity ?? 0)) existing.quantity += 1;
        return;
    }

    cartItems.value.push({
        product_id: Number(product.id),
        name: product.name,
        quantity: 1,
        unit_price: Number(product.sale_price ?? 0),
        stock_quantity: Number(product.stock_quantity ?? 0),
    });
}

function removeFromCart(productId) {
    cartItems.value = cartItems.value.filter((item) => Number(item.product_id) !== Number(productId));
}

function increase(item) {
    if (item.quantity < item.stock_quantity) item.quantity += 1;
}

function decrease(item) {
    if (item.quantity <= 1) {
        removeFromCart(item.product_id);
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
    saleForm.surcharge_amount = surchargeValue.value;
    saleForm.notes = notes.value;
    saleForm.items = cartItems.value.map((item) => ({
        product_id: item.product_id,
        quantity: item.quantity,
    }));

    saleForm.post(route('admin.pdv.sales.store'), {
        preserveScroll: true,
        onSuccess: () => {
            cartItems.value = [];
            selectedClientId.value = '';
            discountAmount.value = '';
            surchargeAmount.value = '';
            notes.value = '';
        },
    });
}

function setProductPage(page) {
    if (page < 1 || page > totalProductPages.value) return;
    currentProductPage.value = page;
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

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="PDV">
        <section class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="hasOpenCashSession"
                            class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                        >
                            {{ props.cashSession.code }}
                        </span>
                        <p class="text-sm text-slate-600">
                            {{ hasOpenCashSession ? `Aberto em ${props.cashSession.opened_at}` : 'Nenhum caixa aberto no momento.' }}
                        </p>
                    </div>

                    <button
                        v-if="!hasOpenCashSession"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                        @click="openCashModalOpen = true"
                    >
                        <Unlock class="h-3.5 w-3.5" />
                        Abrir caixa
                    </button>
                    <button
                        v-else
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-700"
                        @click="openCloseCashModal"
                    >
                        <Lock class="h-3.5 w-3.5" />
                        Fechar caixa
                    </button>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[1.45fr_1fr]">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-sm font-semibold text-slate-900">Produtos</h2>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="openFeaturedProductsModal"
                        >
                            <Star class="h-3.5 w-3.5" />
                            Top 12 do PDV
                        </button>
                    </div>

                    <div class="veshop-search-shell mt-3 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="h-4 w-4 text-slate-500" />
                        <input
                            v-model="productSearch"
                            type="text"
                            placeholder="Buscar produto por nome ou SKU"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                        >
                    </div>

                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span>{{ filteredProducts.length }} produto(s)</span>
                        <span>Página {{ currentProductPage }} de {{ totalProductPages }}</span>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="product in paginatedProducts"
                            :key="product.id"
                            class="rounded-xl border border-slate-200 bg-slate-50/80 p-3"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ product.name }}</p>
                                <span
                                    v-if="product.is_pdv_featured"
                                    class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                                >
                                    Top {{ product.pdv_featured_order }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">
                                Estoque: {{ product.stock_quantity }} • {{ asCurrency(product.sale_price) }}
                            </p>
                            <button
                                type="button"
                                class="mt-2 inline-flex w-full items-center justify-center gap-1 rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800"
                                @click="addToCart(product)"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                Adicionar
                            </button>
                        </article>
                    </div>

                    <div v-if="!paginatedProducts.length" class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                        Nenhum produto encontrado para o filtro aplicado.
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                            :disabled="currentProductPage <= 1"
                            @click="setProductPage(currentProductPage - 1)"
                        >
                            <ChevronLeft class="h-3.5 w-3.5" />
                            Anterior
                        </button>

                        <button
                            v-for="page in productPageNumbers"
                            :key="`pdv-page-${page}`"
                            type="button"
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs font-semibold transition"
                            :class="page === currentProductPage ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            @click="setProductPage(page)"
                        >
                            {{ page }}
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                            :disabled="currentProductPage >= totalProductPages"
                            @click="setProductPage(currentProductPage + 1)"
                        >
                            Próximo
                            <ChevronRight class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <h2 class="text-sm font-semibold text-slate-900">Carrinho</h2>

                    <div class="mt-3 space-y-2">
                        <article
                            v-for="item in cartItems"
                            :key="item.product_id"
                            class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ item.name }}</p>
                                <button type="button" class="rounded p-1 text-slate-500 transition hover:bg-slate-200" @click="removeFromCart(item.product_id)">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1">
                                    <button type="button" class="rounded p-1 transition hover:bg-slate-100" @click="decrease(item)">
                                        <Minus class="h-3.5 w-3.5" />
                                    </button>
                                    <span class="min-w-[2rem] text-center text-xs font-semibold">{{ item.quantity }}</span>
                                    <button type="button" class="rounded p-1 transition hover:bg-slate-100" @click="increase(item)">
                                        <Plus class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <p class="text-sm font-semibold text-slate-900">{{ asCurrency(item.quantity * item.unit_price) }}</p>
                            </div>
                        </article>
                    </div>

                    <div v-if="!cartItems.length" class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-center text-xs text-slate-500">
                        Adicione produtos para iniciar a venda.
                    </div>

                    <div class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                        <div class="veshop-search-shell flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <Search class="h-4 w-4 text-slate-500" />
                            <input
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

                        <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                            <UiSelect v-model="selectedClientId" :options="clientOptions" />
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                @click="openCreateClientModal"
                            >
                                <UserPlus class="h-3.5 w-3.5" />
                                Novo cliente
                            </button>
                        </div>

                        <UiSelect v-model="selectedPaymentMethodId" :options="paymentMethodOptions" placeholder="Forma de pagamento" />
                        <UiSelect
                            v-if="selectedPaymentMethod?.allows_installments"
                            v-model="installments"
                            :options="installmentOptions"
                            placeholder="Parcelamento"
                        />

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
                                <span class="font-semibold">+ {{ asCurrency(surchargeValue) }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between border-t border-slate-200 pt-2">
                                <span class="font-semibold">Total</span>
                                <span class="text-base font-bold">{{ asCurrency(totalAmount) }}</span>
                            </div>
                        </div>

                        <p
                            v-if="saleForm.errors.cash_session || saleForm.errors.items || saleForm.errors.payment_method_id"
                            class="text-xs font-semibold text-rose-600"
                        >
                            {{ saleForm.errors.cash_session || saleForm.errors.items || saleForm.errors.payment_method_id }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400"
                            :disabled="saleForm.processing || !canFinalizeSale"
                            @click="finalizeSale"
                        >
                            <ReceiptText class="h-4 w-4" />
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
                                    <p class="text-xs text-slate-500">{{ asCurrency(product.sale_price) }} • Estoque {{ product.stock_quantity }}</p>
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
                                        <p class="truncate text-xs font-semibold text-slate-900">#{{ index + 1 }} • {{ product.name }}</p>
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
    </AuthenticatedLayout>
</template>
