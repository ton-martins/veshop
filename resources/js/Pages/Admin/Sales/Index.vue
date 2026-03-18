<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import Modal from '@/Components/Modal.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import OrderDetailsModal from '@/Components/App/Orders/OrderDetailsModal.vue';
import { useBranding } from '@/branding';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ShoppingBag, Search, ListFilter, Wallet, CheckCircle2, Ban, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    sales: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },
    saleStats: { type: Object, default: () => ({}) },
    pipeline: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: '', pipeline: 'all' }) },
});

const saleSearch = ref(String(props.filters?.search ?? ''));
const selectedStatus = ref(String(props.filters?.status ?? ''));
const selectedPipelineKey = ref(String(props.filters?.pipeline ?? 'all'));
const saleDetailsModalOpen = ref(false);
const saleDetails = ref(null);
const editModalOpen = ref(false);
const editSale = ref(null);
const editForm = useForm({
    client_id: '',
    customer_name: '',
    customer_contact: '',
    discount_amount: 0,
    surcharge_amount: 0,
    items: [],
    notes: '',
});

const page = usePage();
const { normalizeHex, withAlpha, secondaryColor } = useBranding();
const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const tabAccentColor = computed(() =>
    normalizeHex(currentContractor.value?.brand_primary_color || '', secondaryColor.value),
);
const salesUiStyles = computed(() => ({
    '--sales-pipeline-active': tabAccentColor.value,
    '--sales-pipeline-active-border': withAlpha(tabAccentColor.value, 0.28),
}));

const pipelineIconMap = {
    draft: ShoppingBag,
    open: Wallet,
    completed: CheckCircle2,
    cancelled: Ban,
};

const salesData = computed(() => (
    Array.isArray(props.sales?.data)
        ? props.sales.data
        : []
));

const paginationLinks = computed(() => (
    Array.isArray(props.sales?.links)
        ? props.sales.links
        : []
));

const clientLookup = computed(() => (
    new Map(
        (Array.isArray(props.clients) ? props.clients : []).map((client) => [Number(client.id), client]),
    )
));

const productLookup = computed(() => (
    new Map(
        (Array.isArray(props.products) ? props.products : []).map((product) => [Number(product.id), product]),
    )
));

const clientSelectOptions = computed(() => [
    { value: '', label: 'Consumidor final' },
    ...(Array.isArray(props.clients) ? props.clients : []).map((client) => ({
        value: Number(client.id),
        label: String(client.name ?? `Cliente #${client.id}`),
    })),
]);

const productSelectOptions = computed(() => (
    (Array.isArray(props.products) ? props.products : []).map((product) => ({
        value: Number(product.id),
        label: product?.sku
            ? `${String(product.name ?? '')} (${String(product.sku)})`
            : String(product.name ?? ''),
    }))
));

const pipelineTabs = computed(() => {
    const baseTabs = (props.pipeline ?? []).map((item) => ({
        key: String(item?.key ?? ''),
        label: String(item?.label ?? ''),
        qty: Number(item?.qty ?? 0),
        icon: pipelineIconMap[String(item?.key ?? '')] ?? ListFilter,
    }));

    return [
        {
            key: 'all',
            label: 'Todas',
            qty: Number(props.saleStats?.all ?? 0),
            icon: ListFilter,
        },
        ...baseTabs,
    ];
});

const asCurrency = (value) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const normalizeMoney = (value) => {
    const number = Number(value ?? 0);
    if (!Number.isFinite(number)) return 0;
    return Math.max(0, Math.round(number * 100) / 100);
};

const emptyItemLine = () => ({
    product_id: '',
    quantity: 1,
    discount_amount: 0,
});

const lineSubtotal = (line) => {
    const product = productLookup.value.get(Number(line?.product_id));
    const unitPrice = Number(product?.sale_price ?? 0);
    const quantity = Math.max(0, Number(line?.quantity ?? 0));
    return Math.round(unitPrice * quantity * 100) / 100;
};

const lineDiscount = (line) => {
    const discount = normalizeMoney(line?.discount_amount ?? 0);
    return Math.min(discount, lineSubtotal(line));
};

const lineTotal = (line) => {
    return Math.max(0, Math.round((lineSubtotal(line) - lineDiscount(line)) * 100) / 100);
};

const formSubtotal = computed(() => (
    editForm.items.reduce((sum, line) => sum + lineSubtotal(line), 0)
));

const formItemsDiscount = computed(() => (
    editForm.items.reduce((sum, line) => sum + lineDiscount(line), 0)
));

const formGlobalDiscount = computed(() => normalizeMoney(editForm.discount_amount));
const formSurcharge = computed(() => normalizeMoney(editForm.surcharge_amount));

const formTotal = computed(() => {
    const total = formSubtotal.value - formItemsDiscount.value - formGlobalDiscount.value + formSurcharge.value;
    return Math.round(Math.max(0, total) * 100) / 100;
});

const filteredSales = computed(() => salesData.value);
let filterDebounceTimer = null;

const normalizePipeline = (value) => {
    const safe = String(value ?? '').trim();
    return ['draft', 'open', 'completed', 'cancelled'].includes(safe)
        ? safe
        : 'all';
};

const submitFilters = () => {
    router.get(
        route('admin.sales.index'),
        {
            search: String(saleSearch.value ?? '').trim() || undefined,
            status: String(selectedStatus.value ?? '').trim() || undefined,
            pipeline: normalizePipeline(selectedPipelineKey.value) !== 'all'
                ? normalizePipeline(selectedPipelineKey.value)
                : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['sales', 'saleStats', 'pipeline', 'filters'],
        },
    );
};

const scheduleSubmitFilters = () => {
    if (filterDebounceTimer) {
        clearTimeout(filterDebounceTimer);
    }

    filterDebounceTimer = setTimeout(() => {
        submitFilters();
    }, 280);
};

watch([saleSearch, selectedStatus, selectedPipelineKey], () => {
    scheduleSubmitFilters();
});

watch(
    () => props.filters,
    (filters) => {
        const nextSearch = String(filters?.search ?? '');
        const nextStatus = String(filters?.status ?? '');
        const nextPipeline = normalizePipeline(filters?.pipeline ?? 'all');

        if (saleSearch.value !== nextSearch) {
            saleSearch.value = nextSearch;
        }
        if (selectedStatus.value !== nextStatus) {
            selectedStatus.value = nextStatus;
        }
        if (selectedPipelineKey.value !== nextPipeline) {
            selectedPipelineKey.value = nextPipeline;
        }
    },
);

onBeforeUnmount(() => {
    if (filterDebounceTimer) {
        clearTimeout(filterDebounceTimer);
        filterDebounceTimer = null;
    }
});

const clearSearch = () => {
    saleSearch.value = '';
};

const openSaleDetails = (sale) => {
    if (!sale?.id) return;
    saleDetails.value = sale;
    saleDetailsModalOpen.value = true;
};

const closeSaleDetails = () => {
    saleDetailsModalOpen.value = false;
    saleDetails.value = null;
};

const closeEditModal = () => {
    editModalOpen.value = false;
    editSale.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const openEditModal = (sale) => {
    if (!sale?.id || !sale?.can_edit) return;

    const mappedItems = Array.isArray(sale.items)
        ? sale.items
            .filter((item) => Number(item?.product_id) > 0)
            .map((item) => ({
                product_id: Number(item.product_id),
                quantity: Math.max(1, Number(item.quantity ?? 1)),
                discount_amount: normalizeMoney(item.discount_amount ?? 0),
            }))
        : [];

    editSale.value = sale;
    editForm.defaults({
        client_id: sale.client_id ? Number(sale.client_id) : '',
        customer_name: String(sale.customer ?? ''),
        customer_contact: String(sale.customer_contact ?? ''),
        discount_amount: normalizeMoney(sale.global_discount_amount ?? 0),
        surcharge_amount: normalizeMoney(sale.surcharge_amount ?? 0),
        items: mappedItems.length ? mappedItems : [emptyItemLine()],
        notes: String(sale.notes ?? ''),
    });
    editForm.reset();
    editForm.clearErrors();
    editModalOpen.value = true;
};

const addItemLine = () => {
    editForm.items = [...editForm.items, emptyItemLine()];
};

const removeItemLine = (index) => {
    if (editForm.items.length <= 1) return;
    editForm.items = editForm.items.filter((_, idx) => idx !== index);
};

const setItemProduct = (index, productId) => {
    const nextItems = [...editForm.items];
    nextItems[index] = {
        ...nextItems[index],
        product_id: productId === '' ? '' : Number(productId),
    };
    editForm.items = nextItems;
};

const onClientChange = (clientId) => {
    const safeId = clientId === '' ? '' : Number(clientId);
    editForm.client_id = safeId;

    if (safeId === '') return;

    const selected = clientLookup.value.get(safeId);
    if (!selected) return;

    editForm.customer_name = String(selected.name ?? editForm.customer_name ?? '');
    editForm.customer_contact = String(selected.phone ?? selected.email ?? editForm.customer_contact ?? '');
};

const submitSaleEdit = () => {
    if (!editSale.value?.id || editForm.processing) return;

    const normalizedItems = (Array.isArray(editForm.items) ? editForm.items : [])
        .filter((line) => Number(line?.product_id) > 0 && Number(line?.quantity ?? 0) > 0)
        .map((line) => ({
            product_id: Number(line.product_id),
            quantity: Math.max(1, Number(line.quantity ?? 1)),
            discount_amount: normalizeMoney(line.discount_amount ?? 0),
        }));

    if (!normalizedItems.length) {
        editForm.setError('items', 'Adicione ao menos um produto na venda.');
        return;
    }

    editForm.transform((data) => ({
        client_id: data.client_id === '' ? null : Number(data.client_id),
        customer_name: String(data.customer_name ?? '').trim() || null,
        customer_contact: String(data.customer_contact ?? '').trim() || null,
        discount_amount: normalizeMoney(data.discount_amount ?? 0),
        surcharge_amount: normalizeMoney(data.surcharge_amount ?? 0),
        items: normalizedItems,
        notes: String(data.notes ?? '').trim() || null,
    })).put(route('admin.sales.update', editSale.value.id), {
        preserveScroll: true,
        onSuccess: closeEditModal,
    });
};

const handleSaleDetailsAction = (payload) => {
    const type = String(payload?.type ?? '').trim();
    const sale = payload?.order;
    if (!type || !sale?.id) return;

    closeSaleDetails();

    setTimeout(() => {
        if (type === 'edit') {
            openEditModal(sale);
        }
    }, 0);
};
</script>

<template>
    <Head title="Vendas" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Vendas" :show-table-view-toggle="false">
        <section class="space-y-4" :style="salesUiStyles">
            <div class="sales-pipeline-shell">
                <div class="sales-pipeline-track">
                    <button
                        v-for="tab in pipelineTabs"
                        :key="`sales-pipeline-tab-${tab.key}`"
                        type="button"
                        class="sales-pipeline-tab"
                        :class="selectedPipelineKey === tab.key ? 'is-active' : ''"
                        @click="selectedPipelineKey = tab.key"
                    >
                        <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                        <span class="truncate">{{ tab.label }}</span>
                        <span class="sales-pipeline-badge">
                            {{ tab.qty }}
                        </span>
                    </button>
                </div>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input
                            v-model="saleSearch"
                            type="text"
                            placeholder="Buscar venda por código, cliente ou contato"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                        >
                        <button
                            v-if="saleSearch"
                            type="button"
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                            aria-label="Limpar pesquisa"
                            @click="clearSearch"
                        >
                            x
                        </button>
                    </div>

                    <div class="flex w-full items-center gap-2 lg:w-auto">
                        <UiSelect
                            v-model="selectedStatus"
                            :options="statusOptions"
                            button-class="w-full lg:w-56"
                        />
                    </div>
                </div>

                <div class="mt-3 flex justify-end">
                    <TableViewToggle />
                </div>

                <div class="mt-4 space-y-3">
                    <div class="hidden overflow-hidden rounded-xl border border-slate-200 md:block">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Venda</th>
                                    <th class="px-4 py-3">Canal</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody v-if="filteredSales.length" class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="sale in filteredSales"
                                    :key="sale.id"
                                    class="cursor-pointer transition hover:bg-slate-50/70"
                                    @click="openSaleDetails(sale)"
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ sale.code }}</p>
                                        <p class="text-xs text-slate-500">{{ sale.customer }}</p>
                                        <p class="text-[11px] text-slate-400">{{ sale.customer_contact || 'Sem contato' }}</p>
                                        <p class="text-[11px] text-slate-400">{{ sale.created_at }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ sale.channel }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-800">{{ asCurrency(sale.total_amount) }}</p>
                                        <p class="text-[11px] text-slate-500">{{ sale.total_items }} item(ns)</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="sale.status.tone">{{ sale.status.label }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                v-if="sale.can_edit"
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100"
                                                title="Editar venda"
                                                aria-label="Editar venda"
                                                @click.stop="openEditModal(sale)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="space-y-3 md:hidden">
                        <article
                            v-for="sale in filteredSales"
                            :key="`mobile-sale-${sale.id}`"
                            class="cursor-pointer rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:bg-slate-50/70"
                            @click="openSaleDetails(sale)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ sale.code }}</p>
                                    <p class="text-xs text-slate-500">{{ sale.customer }}</p>
                                    <p class="text-[11px] text-slate-400">{{ sale.customer_contact || 'Sem contato' }}</p>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="sale.status.tone">{{ sale.status.label }}</span>
                            </div>

                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-600">
                                <p>Canal: <span class="font-semibold">{{ sale.channel }}</span></p>
                                <p>Itens: <span class="font-semibold">{{ sale.total_items }}</span></p>
                                <p>Total: <span class="font-semibold">{{ asCurrency(sale.total_amount) }}</span></p>
                                <p>Quando: <span class="font-semibold">{{ sale.created_at }}</span></p>
                            </div>

                            <div class="mt-3 flex items-center gap-1.5">
                                <button
                                    v-if="sale.can_edit"
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700"
                                    title="Editar venda"
                                    aria-label="Editar venda"
                                    @click.stop="openEditModal(sale)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                            </div>
                        </article>
                    </div>

                    <div v-if="!filteredSales.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Nenhuma venda registrada para este contratante.
                    </div>

                    <div v-if="paginationLinks.length" class="pt-2">
                        <PaginationLinks :links="paginationLinks" :min-links="4" />
                    </div>
                </div>
            </section>
        </section>

        <OrderDetailsModal
            :show="saleDetailsModalOpen"
            :order="saleDetails"
            :show-actions="true"
            @close="closeSaleDetails"
            @action="handleSaleDetailsAction"
        />

        <Modal :show="editModalOpen" max-width="2xl" @close="closeEditModal">
            <div class="space-y-4 p-5">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Editar venda</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Atualize os dados operacionais da venda
                        <span v-if="editSale?.code" class="font-semibold text-slate-700">
                            {{ editSale.code }}.
                        </span>
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</span>
                        <UiSelect
                            :model-value="editForm.client_id"
                            :options="clientSelectOptions"
                            button-class="w-full"
                            @update:model-value="onClientChange"
                        />
                    </label>
                    <label class="space-y-1 sm:col-span-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome do cliente</span>
                        <input
                            v-model="editForm.customer_name"
                            type="text"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Nome do cliente"
                        >
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contato</span>
                        <input
                            v-model="editForm.customer_contact"
                            type="text"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Telefone ou e-mail"
                        >
                    </label>
                </div>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contato</span>
                    <input
                        v-model="editForm.customer_contact"
                        type="text"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        placeholder="Telefone ou e-mail"
                    >
                </label>

                <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Itens da venda</p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                            @click="addItemLine"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Adicionar item
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="(line, index) in editForm.items"
                            :key="`sale-line-${index}`"
                            class="space-y-2 rounded-lg border border-slate-200 bg-white p-2.5"
                        >
                            <div class="grid gap-2 md:grid-cols-12">
                                <div class="md:col-span-6">
                                    <UiSelect
                                        :model-value="line.product_id"
                                        :options="productSelectOptions"
                                        button-class="w-full"
                                        @update:model-value="(value) => setItemProduct(index, value)"
                                    />
                                </div>
                                <label class="space-y-1 md:col-span-2">
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Qtd</span>
                                    <input
                                        v-model.number="line.quantity"
                                        type="number"
                                        min="1"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-slate-700"
                                    >
                                </label>
                                <label class="space-y-1 md:col-span-2">
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Desconto</span>
                                    <input
                                        v-model.number="line.discount_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-slate-700"
                                    >
                                </label>
                                <div class="flex items-end justify-end md:col-span-2">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="editForm.items.length <= 1"
                                        title="Remover item"
                                        @click="removeItemLine(index)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
                                <p v-if="productLookup.get(Number(line.product_id))">
                                    Estoque atual:
                                    <span class="font-semibold">
                                        {{ Number(productLookup.get(Number(line.product_id))?.stock_quantity ?? 0) }}
                                    </span>
                                </p>
                                <p>Subtotal: <span class="font-semibold">{{ asCurrency(lineSubtotal(line)) }}</span></p>
                                <p>Desconto: <span class="font-semibold">{{ asCurrency(lineDiscount(line)) }}</span></p>
                                <p>Total item: <span class="font-semibold">{{ asCurrency(lineTotal(line)) }}</span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Desconto geral</span>
                        <input
                            v-model.number="editForm.discount_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="0,00"
                        >
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Acréscimo</span>
                        <input
                            v-model.number="editForm.surcharge_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="0,00"
                        >
                    </label>
                </div>

                <div class="grid gap-2 text-xs sm:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-slate-500">Subtotal</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ asCurrency(formSubtotal) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-slate-500">Desc. itens</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ asCurrency(formItemsDiscount) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-slate-500">Desc. geral</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ asCurrency(formGlobalDiscount) }}</p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                        <p class="text-slate-600">Total final</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ asCurrency(formTotal) }}</p>
                    </div>
                </div>

                <label class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</span>
                    <textarea
                        v-model="editForm.notes"
                        rows="4"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        placeholder="Anotações da venda"
                    />
                </label>

                <p v-if="editForm.errors.sale" class="text-xs font-semibold text-rose-600">
                    {{ editForm.errors.sale }}
                </p>
                <p v-if="editForm.errors.items" class="text-xs font-semibold text-rose-600">
                    {{ editForm.errors.items }}
                </p>
                <div v-if="Object.keys(editForm.errors).some((key) => key !== 'sale')" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    Verifique os campos do formulário antes de salvar.
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="closeEditModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                        :disabled="editForm.processing || formTotal <= 0 || !editForm.items.length"
                        @click="submitSaleEdit"
                    >
                        {{ editForm.processing ? 'Salvando...' : 'Salvar alterações' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.sales-pipeline-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.sales-pipeline-shell::-webkit-scrollbar {
    height: 6px;
}

.sales-pipeline-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.sales-pipeline-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.sales-pipeline-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid transparent;
    border-radius: 0.72rem;
    min-height: 38px;
    padding: 0.52rem 0.8rem;
    color: #334155;
    font-size: 0.79rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.sales-pipeline-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.sales-pipeline-tab.is-active {
    border-color: var(--sales-pipeline-active-border);
    background: var(--sales-pipeline-active);
    color: #ffffff;
}

.sales-pipeline-badge {
    display: inline-flex;
    min-width: 20px;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: #f8fafc;
    padding: 0 0.38rem;
    font-size: 0.66rem;
    font-weight: 700;
    line-height: 1.3;
    color: #475569;
}

.sales-pipeline-tab.is-active .sales-pipeline-badge {
    border-color: rgba(255, 255, 255, 0.36);
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}
</style>
