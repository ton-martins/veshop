<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { Search, Plus, Minus, Trash2, Lock, Unlock, ReceiptText } from 'lucide-vue-next';

const props = defineProps({
    cashSession: { type: Object, default: null },
    cashSummary: { type: Object, default: () => ({ expected_balance: 0 }) },
    products: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    recentSales: { type: Array, default: () => [] },
    initialAction: { type: String, default: null },
});

const productSearch = ref('');
const selectedClientId = ref('');
const selectedPaymentMethodId = ref('');
const installments = ref('');
const discountAmount = ref('0');
const surchargeAmount = ref('0');
const notes = ref('');
const cartItems = ref([]);

const openCashModalOpen = ref(false);
const closeCashModalOpen = ref(false);

const openCashForm = useForm({ opening_balance: '0.00', notes: '' });
const closeCashForm = useForm({ closing_balance: '', notes: '' });
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

const clientsOptions = computed(() => [
    { value: '', label: 'Consumidor final' },
    ...(props.clients ?? []).map((client) => ({ value: String(client.id), label: client.name })),
]);

const paymentMethodOptions = computed(() =>
    (props.paymentMethods ?? []).map((method) => ({ value: String(method.id), label: method.name })),
);

const selectedPaymentMethod = computed(() =>
    (props.paymentMethods ?? []).find((method) => String(method.id) === String(selectedPaymentMethodId.value)) ?? null,
);

const installmentOptions = computed(() => {
    if (!selectedPaymentMethod.value?.allows_installments) return [];
    const max = Number(selectedPaymentMethod.value?.max_installments ?? 12) || 12;
    return Array.from({ length: Math.max(0, max - 1) }).map((_, i) => ({ value: String(i + 2), label: `${i + 2}x` }));
});

const filteredProducts = computed(() => {
    const query = String(productSearch.value ?? '').trim().toLowerCase();
    if (!query) return props.products ?? [];
    return (props.products ?? []).filter((product) =>
        String(product.name ?? '').toLowerCase().includes(query) ||
        String(product.sku ?? '').toLowerCase().includes(query),
    );
});

const subtotalAmount = computed(() => cartItems.value.reduce((sum, item) => sum + (Number(item.quantity) * Number(item.unit_price)), 0));
const discountValue = computed(() => normalizeMoneyInput(discountAmount.value));
const surchargeValue = computed(() => normalizeMoneyInput(surchargeAmount.value));
const totalAmount = computed(() => Math.max(0, subtotalAmount.value - discountValue.value + surchargeValue.value));
const canFinalizeSale = computed(() => hasOpenCashSession.value && cartItems.value.length > 0 && Boolean(selectedPaymentMethodId.value) && totalAmount.value > 0);

watch(
    () => props.paymentMethods,
    (methods) => {
        if (selectedPaymentMethodId.value && methods.some((method) => String(method.id) === String(selectedPaymentMethodId.value))) return;
        const defaultMethod = methods.find((method) => method.is_default) ?? methods[0] ?? null;
        selectedPaymentMethodId.value = defaultMethod ? String(defaultMethod.id) : '';
    },
    { immediate: true },
);

watch(selectedPaymentMethod, (method) => {
    if (!method?.allows_installments) {
        installments.value = '';
        return;
    }
    if (!installments.value) installments.value = '2';
}, { immediate: true });

onMounted(() => {
    if (props.initialAction === 'open-cash' && !hasOpenCashSession.value) openCashModalOpen.value = true;
    if (props.initialAction === 'close-cash' && hasOpenCashSession.value) openCloseCashModal();
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
    if (item.quantity <= 1) { removeFromCart(item.product_id); return; }
    item.quantity -= 1;
}

function openCloseCashModal() {
    closeCashForm.clearErrors();
    closeCashForm.closing_balance = Number(props.cashSummary?.expected_balance ?? 0).toFixed(2);
    closeCashModalOpen.value = true;
}

function submitOpenCash() {
    openCashForm.post(route('admin.pdv.cash.open'), { preserveScroll: true, onSuccess: () => { openCashModalOpen.value = false; } });
}

function submitCloseCash() {
    closeCashForm.post(route('admin.pdv.cash.close'), { preserveScroll: true, onSuccess: () => { closeCashModalOpen.value = false; } });
}

function finalizeSale() {
    if (!canFinalizeSale.value) return;
    saleForm.client_id = selectedClientId.value ? Number(selectedClientId.value) : null;
    saleForm.payment_method_id = selectedPaymentMethodId.value ? Number(selectedPaymentMethodId.value) : null;
    saleForm.installments = selectedPaymentMethod.value?.allows_installments ? Number(installments.value || 0) || null : null;
    saleForm.discount_amount = discountValue.value;
    saleForm.surcharge_amount = surchargeValue.value;
    saleForm.notes = notes.value;
    saleForm.items = cartItems.value.map((item) => ({ product_id: item.product_id, quantity: item.quantity }));
    saleForm.post(route('admin.pdv.sales.store'), {
        preserveScroll: true,
        onSuccess: () => {
            cartItems.value = [];
            selectedClientId.value = '';
            discountAmount.value = '0';
            surchargeAmount.value = '0';
            notes.value = '';
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
                    <p class="text-sm text-slate-600">
                        {{ hasOpenCashSession ? `Caixa ${props.cashSession.code} aberto em ${props.cashSession.opened_at}` : 'Nenhum caixa aberto.' }}
                    </p>
                    <button
                        v-if="!hasOpenCashSession"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700"
                        @click="openCashModalOpen = true"
                    >
                        <Unlock class="h-3.5 w-3.5" /> Abrir caixa
                    </button>
                    <button
                        v-else
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700"
                        @click="openCloseCashModal"
                    >
                        <Lock class="h-3.5 w-3.5" /> Fechar caixa
                    </button>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[1.45fr_1fr]">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <div class="veshop-search-shell flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="h-4 w-4 text-slate-500" />
                        <input v-model="productSearch" type="text" placeholder="Buscar produto" class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none">
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <article v-for="product in filteredProducts" :key="product.id" class="rounded-xl border border-slate-200 bg-slate-50/80 p-3">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ product.name }}</p>
                            <p class="text-xs text-slate-500">Estoque: {{ product.stock_quantity }} • {{ asCurrency(product.sale_price) }}</p>
                            <button type="button" class="mt-2 inline-flex w-full items-center justify-center gap-1 rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" @click="addToCart(product)">
                                <Plus class="h-3.5 w-3.5" /> Adicionar
                            </button>
                        </article>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <h2 class="text-sm font-semibold text-slate-900">Carrinho</h2>
                    <div class="mt-3 space-y-2">
                        <article v-for="item in cartItems" :key="item.product_id" class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ item.name }}</p>
                                <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-200" @click="removeFromCart(item.product_id)"><Trash2 class="h-3.5 w-3.5" /></button>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1">
                                    <button type="button" class="rounded p-1 hover:bg-slate-100" @click="decrease(item)"><Minus class="h-3.5 w-3.5" /></button>
                                    <span class="min-w-[2rem] text-center text-xs font-semibold">{{ item.quantity }}</span>
                                    <button type="button" class="rounded p-1 hover:bg-slate-100" @click="increase(item)"><Plus class="h-3.5 w-3.5" /></button>
                                </div>
                                <p class="text-sm font-semibold text-slate-900">{{ asCurrency(item.quantity * item.unit_price) }}</p>
                            </div>
                        </article>
                    </div>

                    <div class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                        <UiSelect v-model="selectedClientId" :options="clientsOptions" />
                        <UiSelect v-model="selectedPaymentMethodId" :options="paymentMethodOptions" />
                        <UiSelect v-if="selectedPaymentMethod?.allows_installments" v-model="installments" :options="installmentOptions" />
                        <input v-model="discountAmount" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Desconto (R$)">
                        <input v-model="surchargeAmount" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Acréscimo (R$)">
                        <textarea v-model="notes" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Observações"></textarea>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-sm">
                            <div class="flex items-center justify-between"><span>Subtotal</span><span class="font-semibold">{{ asCurrency(subtotalAmount) }}</span></div>
                            <div class="mt-1 flex items-center justify-between"><span>Desconto</span><span class="font-semibold">- {{ asCurrency(discountValue) }}</span></div>
                            <div class="mt-1 flex items-center justify-between"><span>Acréscimo</span><span class="font-semibold">+ {{ asCurrency(surchargeValue) }}</span></div>
                            <div class="mt-2 flex items-center justify-between border-t border-slate-200 pt-2"><span class="font-semibold">Total</span><span class="text-base font-bold">{{ asCurrency(totalAmount) }}</span></div>
                        </div>

                        <p v-if="saleForm.errors.cash_session || saleForm.errors.items || saleForm.errors.payment_method_id" class="text-xs font-semibold text-rose-600">
                            {{ saleForm.errors.cash_session || saleForm.errors.items || saleForm.errors.payment_method_id }}
                        </p>
                        <button type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400" :disabled="saleForm.processing || !canFinalizeSale" @click="finalizeSale">
                            <ReceiptText class="h-4 w-4" /> {{ saleForm.processing ? 'Finalizando...' : 'Finalizar venda' }}
                        </button>
                    </div>
                </section>
            </div>
        </section>

        <Modal :show="openCashModalOpen" max-width="5xl" @close="openCashModalOpen = false">
            <WizardModalFrame title="Abrir caixa" description="Informe o saldo inicial." :steps="['Abertura']" :current-step="1" @close="openCashModalOpen = false">
                <div class="space-y-3">
                    <input v-model="openCashForm.opening_balance" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Saldo inicial (R$)">
                    <textarea v-model="openCashForm.notes" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Observações"></textarea>
                    <p v-if="openCashForm.errors.opening_balance || openCashForm.errors.cash_session" class="text-xs font-semibold text-rose-600">{{ openCashForm.errors.opening_balance || openCashForm.errors.cash_session }}</p>
                </div>
                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="openCashModalOpen = false">Cancelar</button>
                        <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60" :disabled="openCashForm.processing" @click="submitOpenCash">{{ openCashForm.processing ? 'Abrindo...' : 'Abrir caixa' }}</button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <Modal :show="closeCashModalOpen" max-width="5xl" @close="closeCashModalOpen = false">
            <WizardModalFrame title="Fechar caixa" description="Informe o saldo final apurado." :steps="['Fechamento']" :current-step="1" @close="closeCashModalOpen = false">
                <div class="space-y-3">
                    <p class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">Saldo esperado: <span class="font-semibold">{{ asCurrency(props.cashSummary?.expected_balance ?? 0) }}</span></p>
                    <input v-model="closeCashForm.closing_balance" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Saldo final (R$)">
                    <textarea v-model="closeCashForm.notes" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Observações"></textarea>
                    <p v-if="closeCashForm.errors.closing_balance || closeCashForm.errors.cash_session" class="text-xs font-semibold text-rose-600">{{ closeCashForm.errors.closing_balance || closeCashForm.errors.cash_session }}</p>
                </div>
                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeCashModalOpen = false">Cancelar</button>
                        <button type="button" class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-60" :disabled="closeCashForm.processing" @click="submitCloseCash">{{ closeCashForm.processing ? 'Fechando...' : 'Fechar caixa' }}</button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>
    </AuthenticatedLayout>
</template>

