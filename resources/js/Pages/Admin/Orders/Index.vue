<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import Modal from '@/Components/Modal.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import OrderDetailsModal from '@/Components/App/Orders/OrderDetailsModal.vue';
import { useBranding } from '@/branding';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ShoppingBag, Search, CheckCircle2, XCircle, Ban, Wallet, ListFilter, Pencil, Plus, Trash2, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    orders: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },
    orderStats: { type: Object, default: () => ({}) },
    pipeline: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: '', pipeline: 'all' }) },
});

const orderSearch = ref(String(props.filters?.search ?? ''));
const selectedStatus = ref(String(props.filters?.status ?? ''));
const selectedPipelineKey = ref(String(props.filters?.pipeline ?? 'all'));
const orderDetailsModalOpen = ref(false);
const orderDetails = ref(null);
const actionConfirmModalOpen = ref(false);
const actionOrder = ref(null);
const actionType = ref('');
const rejectReason = ref('');
const editModalOpen = ref(false);
const editOrder = ref(null);
const clientInfoModalOpen = ref(false);
const clientInfo = ref(null);
const editSectionsDefaultState = () => ({
    customer: true,
    items: true,
    adjustments: false,
    notes: false,
});
const editSections = ref(editSectionsDefaultState());

const rejectForm = useForm({ reason: '' });
const actionForm = useForm({ notes: '' });
const editForm = useForm({
    client_id: '',
    customer_name: '',
    customer_contact: '',
    discount_amount: 0,
    shipping_mode: '',
    shipping_amount: 0,
    shipping_estimate_days: '',
    non_shipping_surcharge_amount: 0,
    items: [],
    notes: '',
});
const page = usePage();
const { normalizeHex, withAlpha, secondaryColor } = useBranding();
const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const tabAccentColor = computed(() =>
    normalizeHex(currentContractor.value?.brand_primary_color || '', secondaryColor.value),
);
const ordersUiStyles = computed(() => ({
    '--orders-pipeline-active': tabAccentColor.value,
    '--orders-pipeline-active-border': withAlpha(tabAccentColor.value, 0.28),
}));

const pipelineIconMap = {
    pending_confirmation: ShoppingBag,
    awaiting_payment: Wallet,
    paid: CheckCircle2,
    cancelled: XCircle,
};

const ordersData = computed(() => (
    Array.isArray(props.orders?.data)
        ? props.orders.data
        : []
));

const paginationLinks = computed(() => (
    Array.isArray(props.orders?.links)
        ? props.orders.links
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
            label: 'Todos',
            qty: Number(props.orderStats?.all ?? 0),
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
    variation_id: '',
    quantity: 1,
    discount_amount: 0,
});

const resolveProductForLine = (line) => (
    productLookup.value.get(Number(line?.product_id))
);

const resolveLineVariation = (line) => {
    const product = resolveProductForLine(line);
    if (!product) return null;

    const safeVariationId = Number(line?.variation_id ?? 0);
    if (safeVariationId <= 0) return null;

    const variations = Array.isArray(product.variations) ? product.variations : [];
    return variations.find((variation) => Number(variation?.id) === safeVariationId) ?? null;
};

const lineStockQuantity = (line) => {
    const variation = resolveLineVariation(line);
    if (variation) {
        return Number(variation?.stock_quantity ?? 0);
    }

    const product = resolveProductForLine(line);
    return Number(product?.stock_quantity ?? 0);
};

const productVariationOptions = (productId) => {
    const product = productLookup.value.get(Number(productId));
    if (!product) return [];

    const variations = Array.isArray(product.variations) ? product.variations : [];
    return variations.map((variation) => ({
        value: Number(variation.id),
        label: variation?.sku
            ? `${String(variation.name ?? '')} (${String(variation.sku)})`
            : String(variation.name ?? ''),
    }));
};

const variationSelectOptionsForLine = (line) => {
    const options = productVariationOptions(line?.product_id);
    if (!options.length) {
        return [{ value: '', label: 'Sem variação' }];
    }

    return [{ value: '', label: 'Selecione variação' }, ...options];
};

const lineSubtotal = (line) => {
    const variation = resolveLineVariation(line);
    const product = resolveProductForLine(line);
    const unitPrice = Number(variation?.sale_price ?? product?.sale_price ?? 0);
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
const formShippingAmount = computed(() => normalizeMoney(editForm.shipping_amount));
const formPaymentFee = computed(() => normalizeMoney(editForm.non_shipping_surcharge_amount));
const formTotal = computed(() => {
    const total = formSubtotal.value - formItemsDiscount.value - formGlobalDiscount.value + formShippingAmount.value + formPaymentFee.value;
    return Math.round(Math.max(0, total) * 100) / 100;
});

const filteredOrders = computed(() => ordersData.value);
let filterDebounceTimer = null;

const normalizePipeline = (value) => {
    const safe = String(value ?? '').trim();
    return ['pending_confirmation', 'awaiting_payment', 'paid', 'cancelled'].includes(safe)
        ? safe
        : 'all';
};

const submitFilters = () => {
    router.get(
        route('admin.orders.index'),
        {
            search: String(orderSearch.value ?? '').trim() || undefined,
            status: String(selectedStatus.value ?? '').trim() || undefined,
            pipeline: normalizePipeline(selectedPipelineKey.value) !== 'all'
                ? normalizePipeline(selectedPipelineKey.value)
                : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['orders', 'orderStats', 'pipeline', 'filters'],
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

watch([orderSearch, selectedStatus, selectedPipelineKey], () => {
    scheduleSubmitFilters();
});

watch(
    () => props.filters,
    (filters) => {
        const nextSearch = String(filters?.search ?? '');
        const nextStatus = String(filters?.status ?? '');
        const nextPipeline = normalizePipeline(filters?.pipeline ?? 'all');

        if (orderSearch.value !== nextSearch) {
            orderSearch.value = nextSearch;
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
    orderSearch.value = '';
};

const openOrderDetails = (order) => {
    if (!order?.id) return;
    orderDetails.value = order;
    orderDetailsModalOpen.value = true;
};

const closeOrderDetails = () => {
    orderDetailsModalOpen.value = false;
    orderDetails.value = null;
};

const resolveClientInfoFromOrder = (order) => {
    const safeOrder = order ?? {};
    const safeClientId = safeOrder.client_id ? Number(safeOrder.client_id) : null;
    const linkedClient = safeClientId ? clientLookup.value.get(safeClientId) : null;
    const customerName = String(safeOrder.customer ?? '').trim() || 'Consumidor final';
    const customerContact = String(safeOrder.customer_contact ?? '').trim();

    return {
        order_code: String(safeOrder.code ?? ''),
        order_date: String(safeOrder.created_at ?? ''),
        client_id: safeClientId,
        customer_name: customerName,
        customer_type: safeClientId ? 'Cliente cadastrado' : 'Consumidor final',
        contact: customerContact,
        email: String(linkedClient?.email ?? '').trim(),
        phone: String(linkedClient?.phone ?? '').trim(),
    };
};

const openClientInfoModal = (order) => {
    if (!order?.id) return;
    clientInfo.value = resolveClientInfoFromOrder(order);
    clientInfoModalOpen.value = true;
};

const closeClientInfoModal = () => {
    clientInfoModalOpen.value = false;
    clientInfo.value = null;
};

const actionMeta = computed(() => {
    const currentType = String(actionType.value ?? '');

    if (currentType === 'confirm') {
        return {
            title: 'Confirmar pedido',
            description: 'Este pedido será confirmado e seguirá para o próximo estágio.',
            confirmLabel: 'Confirmar pedido',
            confirmClass: 'bg-emerald-600 hover:bg-emerald-700',
        };
    }

    if (currentType === 'paid') {
        return {
            title: 'Marcar como pago',
            description: 'Confirme para marcar este pedido como pago.',
            confirmLabel: 'Marcar como pago',
            confirmClass: 'bg-blue-600 hover:bg-blue-700',
        };
    }

    if (currentType === 'reject') {
        return {
            title: 'Rejeitar pedido',
            description: 'Confirme para rejeitar este pedido.',
            confirmLabel: 'Confirmar rejeição',
            confirmClass: 'bg-amber-600 hover:bg-amber-700',
        };
    }

    return {
        title: 'Cancelar pedido',
        description: 'Confirme para cancelar este pedido.',
        confirmLabel: 'Confirmar cancelamento',
        confirmClass: 'bg-rose-600 hover:bg-rose-700',
    };
});

const closeActionConfirmModal = () => {
    actionConfirmModalOpen.value = false;
    actionOrder.value = null;
    actionType.value = '';
    rejectReason.value = '';
    actionForm.clearErrors();
    rejectForm.reset();
    rejectForm.clearErrors();
};

const closeEditModal = () => {
    editModalOpen.value = false;
    editOrder.value = null;
    editForm.reset();
    editForm.clearErrors();
    editSections.value = editSectionsDefaultState();
};

const openEditModal = (order) => {
    if (!order?.id || !order?.can_edit) return;

    const mappedItems = Array.isArray(order.items)
        ? order.items
            .filter((item) => Number(item?.product_id) > 0)
            .map((item) => ({
                product_id: Number(item.product_id),
                variation_id: Number(item?.variation_id ?? 0) > 0 ? Number(item.variation_id) : '',
                quantity: Math.max(1, Number(item.quantity ?? 1)),
                discount_amount: normalizeMoney(item.discount_amount ?? 0),
            }))
        : [];

    editOrder.value = order;
    editForm.defaults({
        client_id: order.client_id ? Number(order.client_id) : '',
        customer_name: String(order.customer ?? ''),
        customer_contact: String(order.customer_contact ?? ''),
        discount_amount: normalizeMoney(order.global_discount_amount ?? 0),
        shipping_mode: String(order.shipping_mode ?? ''),
        shipping_amount: normalizeMoney(order.shipping_amount ?? 0),
        shipping_estimate_days: order.shipping_estimate_days === null || order.shipping_estimate_days === undefined
            ? ''
            : String(order.shipping_estimate_days),
        non_shipping_surcharge_amount: normalizeMoney(order.non_shipping_surcharge_amount ?? order.payment_fee_amount ?? 0),
        items: mappedItems.length ? mappedItems : [emptyItemLine()],
        notes: String(order.notes ?? ''),
    });
    editForm.reset();
    editForm.clearErrors();
    editSections.value = editSectionsDefaultState();
    editModalOpen.value = true;
};

const isEditSectionOpen = (sectionKey) => Boolean(editSections.value?.[sectionKey]);
const toggleEditSection = (sectionKey) => {
    if (!Object.prototype.hasOwnProperty.call(editSections.value, sectionKey)) return;
    editSections.value = {
        ...editSections.value,
        [sectionKey]: !editSections.value[sectionKey],
    };
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
        variation_id: '',
    };
    editForm.items = nextItems;
};

const setItemVariation = (index, variationId) => {
    const nextItems = [...editForm.items];
    nextItems[index] = {
        ...nextItems[index],
        variation_id: variationId === '' ? '' : Number(variationId),
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

const submitOrderEdit = () => {
    if (!editOrder.value?.id || editForm.processing) return;

    const normalizedItems = (Array.isArray(editForm.items) ? editForm.items : [])
        .filter((line) => Number(line?.product_id) > 0 && Number(line?.quantity ?? 0) > 0)
        .map((line) => ({
            product_id: Number(line.product_id),
            variation_id: Number(line?.variation_id ?? 0) > 0 ? Number(line.variation_id) : null,
            quantity: Math.max(1, Number(line.quantity ?? 1)),
            discount_amount: normalizeMoney(line.discount_amount ?? 0),
        }));

    if (!normalizedItems.length) {
        editForm.setError('items', 'Adicione ao menos um produto no pedido.');
        return;
    }

    editForm.transform((data) => ({
        client_id: data.client_id === '' ? null : Number(data.client_id),
        customer_name: String(data.customer_name ?? '').trim() || null,
        customer_contact: String(data.customer_contact ?? '').trim() || null,
        discount_amount: normalizeMoney(data.discount_amount ?? 0),
        shipping_mode: String(data.shipping_mode ?? '').trim() || null,
        shipping_amount: normalizeMoney(data.shipping_amount ?? 0),
        shipping_estimate_days: String(data.shipping_estimate_days ?? '').trim() === '' ? null : Number(data.shipping_estimate_days),
        items: normalizedItems,
        notes: String(data.notes ?? '').trim() || null,
    })).put(route('admin.orders.update', editOrder.value.id), {
        preserveScroll: true,
        onSuccess: closeEditModal,
    });
};

const openActionConfirmModal = (type, order) => {
    if (!order?.id) return;

    actionType.value = String(type ?? '');
    actionOrder.value = order;
    rejectReason.value = 'Pedido rejeitado manualmente pelo operador.';
    actionForm.clearErrors();
    rejectForm.reset();
    rejectForm.clearErrors();
    actionConfirmModalOpen.value = true;
};

const submitActionConfirm = () => {
    if (!actionOrder.value?.id) return;
    if (actionForm.processing || rejectForm.processing) return;

    const orderId = actionOrder.value.id;
    const type = String(actionType.value ?? '');

    if (type === 'confirm') {
        actionForm.transform(() => ({ notes: '' })).post(route('admin.orders.confirm', orderId), {
            preserveScroll: true,
            onSuccess: closeActionConfirmModal,
        });
        return;
    }

    if (type === 'paid') {
        actionForm.transform(() => ({ notes: '' })).post(route('admin.orders.paid', orderId), {
            preserveScroll: true,
            onSuccess: closeActionConfirmModal,
        });
        return;
    }

    if (type === 'reject') {
        rejectForm.reason = String(rejectReason.value ?? '').trim() || 'Pedido rejeitado manualmente pelo operador.';
        rejectForm.post(route('admin.orders.reject', orderId), {
            preserveScroll: true,
            onSuccess: closeActionConfirmModal,
        });
        return;
    }

    actionForm.transform(() => ({ reason: '' })).post(route('admin.orders.cancel', orderId), {
        preserveScroll: true,
        onSuccess: closeActionConfirmModal,
    });
};

const handleOrderDetailsAction = (payload) => {
    const type = String(payload?.type ?? '').trim();
    const order = payload?.order;
    if (!type || !order?.id) return;

    closeOrderDetails();

    setTimeout(() => {
        if (type === 'edit') {
            openEditModal(order);
            return;
        }

        openActionConfirmModal(type, order);
    }, 0);
};
</script>

<template>
    <Head title="Pedidos" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Pedidos" :show-table-view-toggle="false">
        <section class="space-y-4" :style="ordersUiStyles">
            <div class="orders-pipeline-shell">
                <div class="orders-pipeline-track">
                    <button
                        v-for="tab in pipelineTabs"
                        :key="`pipeline-tab-${tab.key}`"
                        type="button"
                        class="orders-pipeline-tab"
                        :class="selectedPipelineKey === tab.key ? 'is-active' : ''"
                        @click="selectedPipelineKey = tab.key"
                    >
                        <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                        <span class="truncate">{{ tab.label }}</span>
                        <span class="orders-pipeline-badge">
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
                            v-model="orderSearch"
                            type="text"
                            placeholder="Buscar pedido por código, cliente ou contato"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                        >
                        <button
                            v-if="orderSearch"
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
                                        <th class="px-4 py-3">Pedido</th>
                                        <th class="px-4 py-3">Cliente</th>
                                        <th class="px-4 py-3">Canal</th>
                                        <th class="px-4 py-3">Total</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody v-if="filteredOrders.length" class="divide-y divide-slate-100 bg-white">
                                    <tr
                                        v-for="order in filteredOrders"
                                        :key="order.id"
                                        class="cursor-pointer transition hover:bg-slate-50/70"
                                        @click="openOrderDetails(order)"
                                    >
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-900">{{ order.code }}</p>
                                            <p class="text-[11px] text-slate-400">{{ order.created_at }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <button
                                                type="button"
                                                class="inline-flex max-w-[220px] items-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                                @click.stop="openClientInfoModal(order)"
                                            >
                                                <span class="truncate">{{ order.customer || 'Consumidor final' }}</span>
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ order.channel }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-800">{{ asCurrency(order.total_amount) }}</p>
                                            <p class="text-[11px] text-slate-500">{{ order.total_items }} item(ns)</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="order.status.tone">{{ order.status.label }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <button
                                                    v-if="order.can_edit"
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100"
                                                    title="Editar pedido"
                                                    aria-label="Editar pedido"
                                                    @click.stop="openEditModal(order)"
                                                >
                                                    <Pencil class="h-4 w-4" />
                                                </button>
                                                <button
                                                    v-if="order.can_confirm"
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
                                                    title="Confirmar pedido"
                                                    aria-label="Confirmar pedido"
                                                    @click.stop="openActionConfirmModal('confirm', order)"
                                                >
                                                    <CheckCircle2 class="h-4 w-4" />
                                                </button>
                                                <button
                                                    v-if="order.can_mark_paid"
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100"
                                                    title="Marcar como pago"
                                                    aria-label="Marcar como pago"
                                                    @click.stop="openActionConfirmModal('paid', order)"
                                                >
                                                    <Wallet class="h-4 w-4" />
                                                </button>
                                                <button
                                                    v-if="order.can_reject"
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100"
                                                    title="Rejeitar pedido"
                                                    aria-label="Rejeitar pedido"
                                                    @click.stop="openActionConfirmModal('reject', order)"
                                                >
                                                    <XCircle class="h-4 w-4" />
                                                </button>
                                                <button
                                                    v-if="order.can_cancel"
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100"
                                                    title="Cancelar pedido"
                                                    aria-label="Cancelar pedido"
                                                    @click.stop="openActionConfirmModal('cancel', order)"
                                                >
                                                    <Ban class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-3 md:hidden">
                            <article
                                v-for="order in filteredOrders"
                                :key="`mobile-order-${order.id}`"
                                class="cursor-pointer rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:bg-slate-50/70"
                                @click="openOrderDetails(order)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ order.code }}</p>
                                        <p class="text-[11px] text-slate-400">{{ order.created_at }}</p>
                                    </div>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="order.status.tone">{{ order.status.label }}</span>
                                </div>

                                <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-600">
                                    <p class="col-span-2">
                                        Cliente:
                                        <button
                                            type="button"
                                            class="ml-1 inline-flex max-w-[210px] items-center rounded-lg border border-slate-200 bg-slate-50 px-2 py-0.5 font-semibold text-slate-700 transition hover:bg-slate-100"
                                            @click.stop="openClientInfoModal(order)"
                                        >
                                            <span class="truncate">{{ order.customer || 'Consumidor final' }}</span>
                                        </button>
                                    </p>
                                    <p>Canal: <span class="font-semibold">{{ order.channel }}</span></p>
                                    <p>Itens: <span class="font-semibold">{{ order.total_items }}</span></p>
                                    <p>Total: <span class="font-semibold">{{ asCurrency(order.total_amount) }}</span></p>
                                    <p>Contato: <span class="font-semibold">{{ order.customer_contact || 'Não informado' }}</span></p>
                                </div>

                                <div class="mt-3 flex items-center gap-1.5">
                                    <button
                                        v-if="order.can_edit"
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700"
                                        title="Editar pedido"
                                        aria-label="Editar pedido"
                                        @click.stop="openEditModal(order)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="order.can_confirm"
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700"
                                        title="Confirmar pedido"
                                        aria-label="Confirmar pedido"
                                        @click.stop="openActionConfirmModal('confirm', order)"
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="order.can_mark_paid"
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700"
                                        title="Marcar como pago"
                                        aria-label="Marcar como pago"
                                        @click.stop="openActionConfirmModal('paid', order)"
                                    >
                                        <Wallet class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="order.can_reject"
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-700"
                                        title="Rejeitar pedido"
                                        aria-label="Rejeitar pedido"
                                        @click.stop="openActionConfirmModal('reject', order)"
                                    >
                                        <XCircle class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="order.can_cancel"
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700"
                                        title="Cancelar pedido"
                                        aria-label="Cancelar pedido"
                                        @click.stop="openActionConfirmModal('cancel', order)"
                                    >
                                        <Ban class="h-4 w-4" />
                                    </button>
                                </div>
                            </article>
                        </div>

                    <div v-if="!filteredOrders.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Nenhum pedido registrado para este contratante.
                    </div>

                    <div v-if="paginationLinks.length" class="pt-2">
                        <PaginationLinks :links="paginationLinks" :min-links="4" />
                    </div>
                </div>
            </section>
        </section>

        <OrderDetailsModal
            :show="orderDetailsModalOpen"
            :order="orderDetails"
            @close="closeOrderDetails"
            @action="handleOrderDetailsAction"
        />

        <Modal :show="clientInfoModalOpen" max-width="lg" @close="closeClientInfoModal">
            <div class="space-y-4 p-5">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Informações do cliente</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Dados gerais vinculados ao pedido
                        <span v-if="clientInfo?.order_code" class="font-semibold text-slate-700">
                            {{ clientInfo.order_code }}.
                        </span>
                    </p>
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Nome</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ clientInfo?.customer_name || 'Não informado' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tipo</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ clientInfo?.customer_type || 'Não informado' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Contato do pedido</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ clientInfo?.contact || 'Não informado' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">E-mail</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ clientInfo?.email || 'Não informado' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Telefone</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ clientInfo?.phone || 'Não informado' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Cliente ID</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ clientInfo?.client_id ?? 'Não vinculado' }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="closeClientInfoModal"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="editModalOpen" max-width="3xl" @close="closeEditModal">
            <div class="space-y-4 p-5">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Editar pedido</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Atualize os dados operacionais do pedido
                        <span v-if="editOrder?.code" class="font-semibold text-slate-700">
                            {{ editOrder.code }}.
                        </span>
                    </p>
                </div>

                <div class="space-y-3">
                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left"
                            @click="toggleEditSection('customer')"
                        >
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Cliente e contato</p>
                                <p class="text-xs text-slate-500">Dados de identificação do pedido.</p>
                            </div>
                            <component :is="isEditSectionOpen('customer') ? ChevronUp : ChevronDown" class="mt-0.5 h-4 w-4 text-slate-500" />
                        </button>
                        <div v-if="isEditSectionOpen('customer')" class="border-t border-slate-100 p-3">
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
                                <label class="space-y-1 sm:col-span-3">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contato</span>
                                    <input
                                        v-model="editForm.customer_contact"
                                        type="text"
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                        placeholder="Telefone ou e-mail"
                                    >
                                </label>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left"
                            @click="toggleEditSection('items')"
                        >
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Itens do pedido</p>
                                <p class="text-xs text-slate-500">Produtos, quantidades e descontos por item.</p>
                            </div>
                            <component :is="isEditSectionOpen('items') ? ChevronUp : ChevronDown" class="mt-0.5 h-4 w-4 text-slate-500" />
                        </button>
                        <div v-if="isEditSectionOpen('items')" class="space-y-2 border-t border-slate-100 p-3">
                            <div class="flex items-center justify-end">
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
                                    :key="`order-line-${index}`"
                                    class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-2.5"
                                >
                                    <div class="grid gap-2 md:grid-cols-12">
                                        <div class="md:col-span-4">
                                            <UiSelect
                                                :model-value="line.product_id"
                                                :options="productSelectOptions"
                                                button-class="w-full"
                                                @update:model-value="(value) => setItemProduct(index, value)"
                                            />
                                        </div>
                                        <div class="md:col-span-3">
                                            <UiSelect
                                                :model-value="line.variation_id"
                                                :options="variationSelectOptionsForLine(line)"
                                                button-class="w-full"
                                                @update:model-value="(value) => setItemVariation(index, value)"
                                            />
                                        </div>
                                        <label class="space-y-1 md:col-span-2">
                                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Quantidade</span>
                                            <input
                                                v-model.number="line.quantity"
                                                type="number"
                                                min="1"
                                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-slate-700"
                                            >
                                        </label>
                                        <label class="space-y-1 md:col-span-2">
                                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Desconto</span>
                                            <BrlMoneyInput
                                                v-model="line.discount_amount"
                                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-slate-700"
                                                placeholder="R$ 0,00"
                                            />
                                        </label>
                                        <div class="flex items-end justify-end md:col-span-1">
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

                                    <div class="grid gap-2 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4">
                                        <p v-if="productLookup.get(Number(line.product_id))">
                                            Estoque atual:
                                            <span class="font-semibold">
                                                {{ lineStockQuantity(line) }}
                                            </span>
                                        </p>
                                        <p>Subtotal: <span class="font-semibold">{{ asCurrency(lineSubtotal(line)) }}</span></p>
                                        <p>Desconto: <span class="font-semibold">{{ asCurrency(lineDiscount(line)) }}</span></p>
                                        <p>Total do item: <span class="font-semibold">{{ asCurrency(lineTotal(line)) }}</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left"
                            @click="toggleEditSection('adjustments')"
                        >
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Ajustes e resumo financeiro</p>
                                <p class="text-xs text-slate-500">Desconto, frete, taxa de pagamento e total final.</p>
                            </div>
                            <component :is="isEditSectionOpen('adjustments') ? ChevronUp : ChevronDown" class="mt-0.5 h-4 w-4 text-slate-500" />
                        </button>
                        <div v-if="isEditSectionOpen('adjustments')" class="space-y-3 border-t border-slate-100 p-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Desconto geral</span>
                                    <BrlMoneyInput
                                        v-model="editForm.discount_amount"
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                        placeholder="R$ 0,00"
                                    />
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Modo de entrega</span>
                                    <select
                                        v-model="editForm.shipping_mode"
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    >
                                        <option value="">Não informado</option>
                                        <option value="pickup">Retirada</option>
                                        <option value="delivery">Entrega</option>
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valor de entrega (R$)</span>
                                    <BrlMoneyInput
                                        v-model="editForm.shipping_amount"
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                        placeholder="R$ 0,00"
                                    />
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estimativa (dias)</span>
                                    <input
                                        v-model="editForm.shipping_estimate_days"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                        placeholder="Ex.: 2"
                                    >
                                </label>
                            </div>

                            <div class="grid gap-2 text-xs sm:grid-cols-3">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-slate-500">Subtotal</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ asCurrency(formSubtotal) }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-slate-500">Desconto dos itens</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ asCurrency(formItemsDiscount) }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-slate-500">Desconto geral</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ asCurrency(formGlobalDiscount) }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-slate-500">Entrega</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ asCurrency(formShippingAmount) }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-slate-500">Taxa de pagamento</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ asCurrency(formPaymentFee) }}</p>
                                </div>
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                                    <p class="text-slate-600">Total final</p>
                                    <p class="mt-1 text-sm font-bold text-slate-900">{{ asCurrency(formTotal) }}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left"
                            @click="toggleEditSection('notes')"
                        >
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Observações</p>
                                <p class="text-xs text-slate-500">Anotações internas da edição do pedido.</p>
                            </div>
                            <component :is="isEditSectionOpen('notes') ? ChevronUp : ChevronDown" class="mt-0.5 h-4 w-4 text-slate-500" />
                        </button>
                        <div v-if="isEditSectionOpen('notes')" class="border-t border-slate-100 p-3">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</span>
                                <textarea
                                    v-model="editForm.notes"
                                    rows="4"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Anotações do pedido"
                                />
                            </label>
                        </div>
                    </section>
                </div>

                <p v-if="editForm.errors.order" class="text-xs font-semibold text-rose-600">
                    {{ editForm.errors.order }}
                </p>
                <p v-if="editForm.errors.items" class="text-xs font-semibold text-rose-600">
                    {{ editForm.errors.items }}
                </p>
                <div v-if="Object.keys(editForm.errors).some((key) => key !== 'order')" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
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
                        @click="submitOrderEdit"
                    >
                        {{ editForm.processing ? 'Salvando...' : 'Salvar alterações' }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="actionConfirmModalOpen" max-width="lg" @close="closeActionConfirmModal">
            <div class="space-y-4 p-5">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">{{ actionMeta.title }}</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ actionMeta.description }}
                        <span v-if="actionOrder?.code" class="font-semibold text-slate-700">
                            Pedido {{ actionOrder.code }}.
                        </span>
                    </p>
                </div>

                <textarea
                    v-if="actionType === 'reject'"
                    :value="rejectReason"
                    rows="4"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                    placeholder="Motivo da rejeição"
                    @input="rejectReason = $event.target.value"
                />
                <p v-if="actionType === 'reject' && (rejectForm.errors.reason || rejectForm.errors.order)" class="text-xs font-semibold text-rose-600">
                    {{ rejectForm.errors.reason || rejectForm.errors.order }}
                </p>

                <p v-if="actionType !== 'reject' && actionForm.errors.order" class="text-xs font-semibold text-rose-600">
                    {{ actionForm.errors.order }}
                </p>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="closeActionConfirmModal"
                    >
                        Voltar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl px-3 py-2 text-xs font-semibold text-white transition disabled:opacity-60"
                        :class="actionMeta.confirmClass"
                        :disabled="actionForm.processing || rejectForm.processing"
                        @click="submitActionConfirm"
                    >
                        {{
                            actionForm.processing || rejectForm.processing
                                ? 'Processando...'
                                : actionMeta.confirmLabel
                        }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.orders-pipeline-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.orders-pipeline-shell::-webkit-scrollbar {
    height: 6px;
}

.orders-pipeline-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.orders-pipeline-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.orders-pipeline-tab {
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

.orders-pipeline-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.orders-pipeline-tab.is-active {
    border-color: var(--orders-pipeline-active-border);
    background: var(--orders-pipeline-active);
    color: #ffffff;
}

.orders-pipeline-badge {
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

.orders-pipeline-tab.is-active .orders-pipeline-badge {
    border-color: rgba(255, 255, 255, 0.36);
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}
</style>
