<script setup>
import Modal from '@/Components/Modal.vue';
import { Ban, CheckCircle2, ShoppingBag, Wallet, X, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    order: {
        type: Object,
        default: null,
    },
    showActions: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close', 'action']);

const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const orderItems = computed(() => (
    Array.isArray(props.order?.items)
        ? props.order.items
        : []
));

const itemsSubtotal = computed(() => (
    orderItems.value.reduce(
        (sum, item) => sum + (Number(item?.unit_price ?? 0) * Number(item?.quantity ?? 0)),
        0,
    )
));

const itemsDiscount = computed(() => (
    orderItems.value.reduce(
        (sum, item) => sum + Number(item?.discount_amount ?? 0),
        0,
    )
));

const closeModal = () => emit('close');

const requestAction = (type) => {
    if (!props.order?.id) return;
    emit('action', { type, order: props.order });
};
</script>

<template>
    <Modal :show="show" max-width="3xl" @close="closeModal">
        <div class="flex max-h-[85vh] flex-col">
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-4 sm:px-5">
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-slate-900">{{ order?.code || 'Detalhes do pedido' }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ order?.channel || 'Pedido' }} • {{ order?.created_at || '-' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="order?.status"
                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="order.status.tone"
                    >
                        {{ order.status.label }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                        aria-label="Fechar detalhes"
                        @click="closeModal"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </header>

            <div class="flex-1 space-y-4 overflow-y-auto p-4 sm:p-5">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Cliente</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ order?.customer || '-' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ order?.customer_contact || 'Sem contato' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Canal</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ order?.channel || '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pagamento</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ order?.payment_label || 'Não informado' }}</p>
                    </div>
                </div>

                <section class="rounded-2xl border border-slate-200 bg-slate-50/50 p-3 sm:p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Itens do pedido</p>
                        <span class="text-[11px] font-semibold text-slate-500">{{ orderItems.length }} item(ns)</span>
                    </div>

                    <div v-if="orderItems.length" class="mt-3 space-y-2">
                        <article
                            v-for="(item, idx) in orderItems"
                            :key="`order-item-${order?.id ?? 'x'}-${idx}`"
                            class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm"
                        >
                            <div class="flex items-start gap-3">
                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                    <img
                                        v-if="item.image_url"
                                        :src="item.image_url"
                                        :alt="item.description || `Produto ${idx + 1}`"
                                        class="h-full w-full object-cover"
                                    >
                                    <span v-else class="inline-flex h-full w-full items-center justify-center text-slate-500">
                                        <ShoppingBag class="h-4 w-4" />
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">
                                        {{ item.description || `Produto ${idx + 1}` }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        <span>Qtd: {{ item.quantity }}</span>
                                        <span v-if="item.sku"> • SKU: {{ item.sku }}</span>
                                    </p>
                                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                                        <span>Unitário: {{ asCurrency(item.unit_price ?? 0) }}</span>
                                        <span v-if="Number(item.discount_amount ?? 0) > 0">Desconto: {{ asCurrency(item.discount_amount) }}</span>
                                    </div>
                                </div>
                                <p class="shrink-0 text-sm font-bold text-slate-900">{{ asCurrency(item.total_amount ?? 0) }}</p>
                            </div>
                        </article>
                    </div>
                    <p v-else class="mt-3 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-500">
                        Nenhum item detalhado neste pedido.
                    </p>
                </section>
            </div>

            <footer class="space-y-3 border-t border-slate-200 p-4 sm:p-5">
                <div class="grid gap-2 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Subtotal itens</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ asCurrency(itemsSubtotal) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Desconto itens</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ asCurrency(itemsDiscount) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total pedido</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ asCurrency(order?.total_amount ?? 0) }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        v-if="showActions && order?.can_confirm"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                        @click="requestAction('confirm')"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        Confirmar
                    </button>
                    <button
                        v-if="showActions && order?.can_mark_paid"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                        @click="requestAction('paid')"
                    >
                        <Wallet class="h-4 w-4" />
                        Marcar pago
                    </button>
                    <button
                        v-if="showActions && order?.can_reject"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                        @click="requestAction('reject')"
                    >
                        <XCircle class="h-4 w-4" />
                        Rejeitar
                    </button>
                    <button
                        v-if="showActions && order?.can_cancel"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                        @click="requestAction('cancel')"
                    >
                        <Ban class="h-4 w-4" />
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Fechar
                    </button>
                </div>
            </footer>
        </div>
    </Modal>
</template>
