<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ShoppingBag, Boxes, CircleDollarSign, AlertTriangle, Search, Filter, CheckCircle2, XCircle, Ban, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    orders: { type: Array, default: () => [] },
    orderStats: { type: Object, default: () => ({}) },
    pipeline: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: '' }) },
});

const orderSearch = ref(String(props.filters?.search ?? ''));
const selectedStatus = ref(String(props.filters?.status ?? ''));
const rejectModalOpen = ref(false);
const orderToReject = ref(null);

const rejectForm = useForm({ reason: '' });
const actionForm = useForm({ notes: '' });

const stats = computed(() => [
    {
        key: 'pending_confirmation',
        label: 'Aguardando confirmação',
        value: String(props.orderStats?.pending_confirmation ?? 0),
        icon: ShoppingBag,
        tone: 'bg-slate-100 text-slate-700',
    },
    {
        key: 'awaiting_payment',
        label: 'Aguardando pagamento',
        value: String(props.orderStats?.awaiting_payment ?? 0),
        icon: Boxes,
        tone: 'bg-blue-100 text-blue-700',
    },
    {
        key: 'paid_today',
        label: 'Pagos hoje',
        value: String(props.orderStats?.paid_today ?? 0),
        icon: CircleDollarSign,
        tone: 'bg-emerald-100 text-emerald-700',
    },
    {
        key: 'cancelled',
        label: 'Rejeitados/cancelados',
        value: String(props.orderStats?.cancelled ?? 0),
        icon: AlertTriangle,
        tone: 'bg-amber-100 text-amber-700',
    },
]);

const asCurrency = (value) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const filteredOrders = computed(() => {
    const query = String(orderSearch.value ?? '').trim().toLowerCase();
    const status = String(selectedStatus.value ?? '').trim();

    return (props.orders ?? []).filter((order) => {
        if (status && String(order?.status?.value ?? '') !== status) {
            return false;
        }

        if (!query) return true;

        const code = String(order?.code ?? '').toLowerCase();
        const customer = String(order?.customer ?? '').toLowerCase();
        const contact = String(order?.customer_contact ?? '').toLowerCase();

        return code.includes(query) || customer.includes(query) || contact.includes(query);
    });
});

const clearSearch = () => {
    orderSearch.value = '';
};

const confirmOrder = (order) => {
    if (!order?.id || actionForm.processing) return;

    actionForm.transform(() => ({ notes: '' })).post(route('admin.orders.confirm', order.id), {
        preserveScroll: true,
    });
};

const markOrderPaid = (order) => {
    if (!order?.id || actionForm.processing) return;

    actionForm.transform(() => ({ notes: '' })).post(route('admin.orders.paid', order.id), {
        preserveScroll: true,
    });
};

const cancelOrder = (order) => {
    if (!order?.id || actionForm.processing) return;

    actionForm.transform(() => ({ reason: '' })).post(route('admin.orders.cancel', order.id), {
        preserveScroll: true,
    });
};

const openRejectModal = (order) => {
    if (!order?.id) return;

    orderToReject.value = order;
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectModalOpen.value = true;
};

const submitReject = () => {
    if (!orderToReject.value?.id || rejectForm.processing) return;

    rejectForm.post(route('admin.orders.reject', orderToReject.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            rejectModalOpen.value = false;
            orderToReject.value = null;
            rejectForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Pedidos" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Pedidos">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="stat in stats" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">{{ stat.label }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ stat.value }}</p>
                        </div>
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl" :class="stat.tone">
                            <component :is="stat.icon" class="h-4 w-4" />
                        </span>
                    </div>
                </article>
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
                        <div class="relative w-full lg:w-56">
                            <Filter class="pointer-events-none absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" />
                            <select v-model="selectedStatus" class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-8 pr-3 text-xs font-semibold text-slate-700">
                                <option
                                    v-for="option in statusOptions"
                                    :key="`status-${option.value || 'all'}`"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                    <div class="space-y-3">
                        <div class="hidden overflow-hidden rounded-xl border border-slate-200 md:block">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Pedido</th>
                                        <th class="px-4 py-3">Canal</th>
                                        <th class="px-4 py-3">Total</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody v-if="filteredOrders.length" class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="order in filteredOrders" :key="order.id">
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-900">{{ order.code }}</p>
                                            <p class="text-xs text-slate-500">{{ order.customer }}</p>
                                            <p class="text-[11px] text-slate-400">{{ order.customer_contact || 'Sem contato' }}</p>
                                            <p class="text-[11px] text-slate-400">{{ order.created_at }}</p>
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
                                                    v-if="order.can_confirm"
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100"
                                                    @click="confirmOrder(order)"
                                                >
                                                    <CheckCircle2 class="h-3.5 w-3.5" />
                                                    Confirmar
                                                </button>
                                                <button
                                                    v-if="order.can_mark_paid"
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700 hover:bg-blue-100"
                                                    @click="markOrderPaid(order)"
                                                >
                                                    <Wallet class="h-3.5 w-3.5" />
                                                    Marcar pago
                                                </button>
                                                <button
                                                    v-if="order.can_reject"
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700 hover:bg-amber-100"
                                                    @click="openRejectModal(order)"
                                                >
                                                    <XCircle class="h-3.5 w-3.5" />
                                                    Rejeitar
                                                </button>
                                                <button
                                                    v-if="order.can_cancel"
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-100"
                                                    @click="cancelOrder(order)"
                                                >
                                                    <Ban class="h-3.5 w-3.5" />
                                                    Cancelar
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
                                class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ order.code }}</p>
                                        <p class="text-xs text-slate-500">{{ order.customer }}</p>
                                        <p class="text-[11px] text-slate-400">{{ order.customer_contact || 'Sem contato' }}</p>
                                    </div>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="order.status.tone">{{ order.status.label }}</span>
                                </div>

                                <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-600">
                                    <p>Canal: <span class="font-semibold">{{ order.channel }}</span></p>
                                    <p>Itens: <span class="font-semibold">{{ order.total_items }}</span></p>
                                    <p>Total: <span class="font-semibold">{{ asCurrency(order.total_amount) }}</span></p>
                                    <p>Quando: <span class="font-semibold">{{ order.created_at }}</span></p>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <button
                                        v-if="order.can_confirm"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700"
                                        @click="confirmOrder(order)"
                                    >
                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                        Confirmar
                                    </button>
                                    <button
                                        v-if="order.can_mark_paid"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700"
                                        @click="markOrderPaid(order)"
                                    >
                                        <Wallet class="h-3.5 w-3.5" />
                                        Pago
                                    </button>
                                    <button
                                        v-if="order.can_reject"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700"
                                        @click="openRejectModal(order)"
                                    >
                                        <XCircle class="h-3.5 w-3.5" />
                                        Rejeitar
                                    </button>
                                    <button
                                        v-if="order.can_cancel"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-[11px] font-semibold text-rose-700"
                                        @click="cancelOrder(order)"
                                    >
                                        <Ban class="h-3.5 w-3.5" />
                                        Cancelar
                                    </button>
                                </div>
                            </article>
                        </div>

                        <div v-if="!filteredOrders.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            Nenhum pedido registrado para este contratante.
                        </div>
                    </div>

                    <aside class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <h2 class="text-sm font-semibold text-slate-900">Pipeline de pedidos</h2>
                        <ul class="space-y-2">
                            <li v-for="item in pipeline" :key="item.key" class="flex items-center justify-between rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
                                <span class="text-sm font-semibold text-slate-700">{{ item.label }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="item.tone">{{ item.qty }}</span>
                            </li>
                        </ul>
                    </aside>
                </div>
            </section>
        </section>

        <Modal :show="rejectModalOpen" max-width="lg" @close="rejectModalOpen = false">
            <div class="space-y-4 p-5">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Rejeitar pedido</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Informe o motivo da rejeição para o pedido
                        <span class="font-semibold text-slate-700">{{ orderToReject?.code }}</span>.
                    </p>
                </div>

                <textarea
                    v-model="rejectForm.reason"
                    rows="4"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                    placeholder="Motivo da rejeição"
                />
                <p v-if="rejectForm.errors.reason || rejectForm.errors.order" class="text-xs font-semibold text-rose-600">
                    {{ rejectForm.errors.reason || rejectForm.errors.order }}
                </p>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="rejectModalOpen = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-700 disabled:opacity-60"
                        :disabled="rejectForm.processing"
                        @click="submitReject"
                    >
                        {{ rejectForm.processing ? 'Rejeitando...' : 'Confirmar rejeição' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
