<script setup>
import Modal from '@/Components/Modal.vue';
import { Ban, CheckCircle2, Pencil, Printer, ShoppingBag, Wallet, X, XCircle } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

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

const printPreviewOpen = ref(false);
const printPreviewTitle = ref('');
const printPreviewHtml = ref('');
const printPreviewIframe = ref(null);

const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const orderItems = computed(() => (
    Array.isArray(props.order?.items)
        ? props.order.items
        : []
));

const paymentMethods = computed(() => {
    if (Array.isArray(props.order?.payment_methods) && props.order.payment_methods.length > 0) {
        return props.order.payment_methods;
    }

    if (props.order?.payment_label) {
        return [{
            id: 'fallback',
            name: props.order.payment_label,
            status_label: '',
            amount_label: asCurrency(props.order?.total_amount ?? 0),
        }];
    }

    return [];
});

const shippingAddressText = computed(() => String(props.order?.shipping_address_text ?? '').trim());
const shippingModeLabel = computed(() => String(props.order?.shipping_mode_label ?? 'Retirada na loja').trim());
const shippingModeTone = computed(() => String(props.order?.shipping_mode_tone ?? 'bg-blue-100 text-blue-700').trim());
const isDelivery = computed(() => String(props.order?.shipping_mode ?? 'pickup') === 'delivery');
const deliveryStatus = computed(() => (
    props.order?.delivery_status && typeof props.order.delivery_status === 'object'
        ? props.order.delivery_status
        : null
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

const requestAction = (type, payload = {}) => {
    if (!props.order?.id) return;
    emit('action', { type, order: props.order, ...payload });
};

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');

const resolvePrintHtml = (format = 'pdf') => {
    const order = props.order ?? {};
    const isCoupon = format === 'coupon';
    const items = Array.isArray(order.items) ? order.items : [];
    const paymentRows = Array.isArray(order.payment_methods) ? order.payment_methods : [];

    const itemsRowsHtml = items.length
        ? items.map((item) => `
            <tr>
                <td>${escapeHtml(item.description || 'Item')}</td>
                <td style="text-align:center;">${escapeHtml(item.quantity ?? 0)}</td>
                <td style="text-align:right;">${escapeHtml(asCurrency(item.unit_price ?? 0))}</td>
                <td style="text-align:right;">${escapeHtml(asCurrency(item.total_amount ?? 0))}</td>
            </tr>
        `).join('')
        : `
            <tr>
                <td colspan="4" style="text-align:center;">Sem itens detalhados.</td>
            </tr>
        `;

    const paymentRowsHtml = paymentRows.length
        ? paymentRows.map((payment) => `
            <li>
                <strong>${escapeHtml(payment.name || 'Pagamento')}</strong>
                ${payment.status_label ? `<span> - ${escapeHtml(payment.status_label)}</span>` : ''}
                ${payment.amount_label ? `<span> (${escapeHtml(payment.amount_label)})</span>` : ''}
            </li>
        `).join('')
        : `<li>${escapeHtml(order.payment_label || 'Não informado')}</li>`;

    const pageSize = isCoupon ? '80mm auto' : 'A4';
    const containerWidth = isCoupon ? '74mm' : '100%';
    const containerPadding = isCoupon ? '4mm' : '10mm';
    const title = isCoupon ? 'Cupom não fiscal' : 'Pedido para impressão';

    return `<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>${escapeHtml(title)} - ${escapeHtml(order.code || order.id || '')}</title>
    <style>
        @page { size: ${pageSize}; margin: ${isCoupon ? '4mm' : '10mm'}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #0f172a; background: #fff; }
        .sheet { width: ${containerWidth}; margin: 0 auto; padding: ${containerPadding}; }
        h1 { margin: 0; font-size: ${isCoupon ? '14px' : '18px'}; }
        h2 { margin: 0 0 6px 0; font-size: ${isCoupon ? '12px' : '14px'}; }
        p { margin: 2px 0; font-size: ${isCoupon ? '11px' : '12px'}; }
        .muted { color: #475569; }
        .section { margin-top: 10px; padding-top: 8px; border-top: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { font-size: ${isCoupon ? '10px' : '12px'}; padding: 4px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { color: #475569; font-weight: 700; }
        ul { margin: 6px 0 0 16px; padding: 0; }
        .totals { margin-top: 8px; }
        .totals p { display: flex; justify-content: space-between; gap: 12px; }
        .strong { font-weight: 700; }
        .footer-note { margin-top: 14px; text-align: center; font-size: ${isCoupon ? '10px' : '11px'}; color: #64748b; }
    </style>
</head>
<body>
    <div class="sheet">
        <h1>${escapeHtml(title)}</h1>
        <p><strong>Pedido:</strong> ${escapeHtml(order.code || order.id || '-')}</p>
        <p><strong>Data:</strong> ${escapeHtml(order.created_at || '-')}</p>
        <p><strong>Status:</strong> ${escapeHtml(order?.status?.label || '-')}</p>
        <p><strong>Tipo:</strong> ${escapeHtml(order.shipping_mode_label || 'Retirada na loja')}</p>

        <div class="section">
            <h2>Cliente</h2>
            <p><strong>Nome:</strong> ${escapeHtml(order.customer || '-')}</p>
            <p><strong>Contato:</strong> ${escapeHtml(order.customer_contact || '-')}</p>
            ${order.customer_email ? `<p><strong>E-mail:</strong> ${escapeHtml(order.customer_email)}</p>` : ''}
            ${order.customer_document ? `<p><strong>Documento:</strong> ${escapeHtml(order.customer_document)}</p>` : ''}
        </div>

        ${order.shipping_mode === 'delivery' && order.shipping_address_text
            ? `<div class="section">
                <h2>Entrega</h2>
                <p>${escapeHtml(order.shipping_address_text)}</p>
            </div>`
            : ''}

        <div class="section">
            <h2>Pagamento</h2>
            <ul>${paymentRowsHtml}</ul>
        </div>

        <div class="section">
            <h2>Itens</h2>
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Qtd</th>
                        <th>Unitário</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>${itemsRowsHtml}</tbody>
            </table>
        </div>

        <div class="section totals">
            <p><span>Subtotal</span><span>${escapeHtml(asCurrency(itemsSubtotal.value))}</span></p>
            <p><span>Desconto</span><span>${escapeHtml(asCurrency(itemsDiscount.value))}</span></p>
            <p class="strong"><span>Total</span><span>${escapeHtml(asCurrency(order.total_amount ?? 0))}</span></p>
        </div>

        ${order.notes ? `<div class="section"><h2>Observações</h2><p class="muted">${escapeHtml(order.notes)}</p></div>` : ''}

        <p class="footer-note">Documento não fiscal para conferência interna.</p>
    </div>
</body>
</html>`;
};

const printOrder = (format = 'pdf') => {
    if (typeof window === 'undefined' || !props.order) return;

    printPreviewHtml.value = resolvePrintHtml(format);
    printPreviewTitle.value = format === 'coupon'
        ? 'Pré-visualização do cupom'
        : 'Pré-visualização do documento';
    printPreviewOpen.value = true;
};

const closePrintPreview = () => {
    printPreviewOpen.value = false;
};

const printFromPreview = async () => {
    await nextTick();
    const frameWindow = printPreviewIframe.value?.contentWindow;
    if (!frameWindow) {
        openPreviewInNewTab();
        return;
    }

    frameWindow.focus();
    frameWindow.print();
};

const openPreviewInNewTab = () => {
    if (typeof window === 'undefined') return;
    const html = String(printPreviewHtml.value ?? '').trim();
    if (html === '') return;

    const previewTab = window.open('', '_blank', 'noopener,noreferrer');
    if (!previewTab) return;

    previewTab.document.open();
    previewTab.document.write(html);
    previewTab.document.close();
    previewTab.focus();
};

watch(printPreviewOpen, (opened) => {
    if (opened) return;
    printPreviewHtml.value = '';
    printPreviewTitle.value = '';
});
</script>

<template>
    <Modal :show="show" max-width="3xl" @close="closeModal">
        <div class="flex max-h-[85vh] flex-col">
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-4 sm:px-5">
                <div class="min-w-0 space-y-1">
                    <p class="truncate text-base font-semibold text-slate-900">{{ order?.code || 'Detalhes do pedido' }}</p>
                    <p class="text-xs text-slate-500">{{ order?.channel || 'Pedido' }} • {{ order?.created_at || '-' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="order?.status"
                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="order.status.tone"
                    >
                        {{ order.status.label }}
                    </span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="shippingModeTone">
                        {{ shippingModeLabel }}
                    </span>
                    <span
                        v-if="isDelivery && deliveryStatus"
                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="deliveryStatus.tone || 'bg-slate-100 text-slate-700'"
                    >
                        {{ deliveryStatus.label || 'Em preparo' }}
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
                        <p v-if="order?.customer_email" class="mt-1 text-xs text-slate-500">{{ order.customer_email }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Canal</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ order?.channel || '-' }}</p>
                        <p v-if="order?.customer_document" class="mt-1 text-xs text-slate-500">Doc: {{ order.customer_document }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pagamento</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ order?.payment_label || 'Não informado' }}</p>
                    </div>
                </div>

                <section v-if="isDelivery && shippingAddressText" class="rounded-2xl border border-slate-200 bg-slate-50/50 p-3 sm:p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Endereço de entrega</p>
                    <p class="mt-2 text-sm text-slate-700">{{ shippingAddressText }}</p>
                </section>

                <section v-if="paymentMethods.length" class="rounded-2xl border border-slate-200 bg-slate-50/50 p-3 sm:p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Métodos de pagamento</p>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="payment in paymentMethods"
                            :key="`payment-detail-${payment.id}`"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-slate-900">{{ payment.name || 'Não informado' }}</p>
                                <p class="font-semibold text-slate-900">{{ payment.amount_label || asCurrency(payment.amount ?? 0) }}</p>
                            </div>
                            <p v-if="payment.status_label" class="mt-1 text-slate-500">Status: {{ payment.status_label }}</p>
                        </li>
                    </ul>
                </section>

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

                <section v-if="order?.notes" class="rounded-2xl border border-slate-200 bg-slate-50/50 p-3 sm:p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ order.notes }}</p>
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
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                        @click="printOrder('pdf')"
                    >
                        <Printer class="h-4 w-4" />
                        Imprimir PDF
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                        @click="printOrder('coupon')"
                    >
                        <Printer class="h-4 w-4" />
                        Imprimir cupom
                    </button>
                    <button
                        v-if="showActions && order?.can_edit"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                        @click="requestAction('edit')"
                    >
                        <Pencil class="h-4 w-4" />
                        Editar
                    </button>
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
                        v-if="showActions && order?.can_set_awaiting_payment"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                        @click="requestAction('awaiting_payment')"
                    >
                        <Wallet class="h-4 w-4" />
                        Aguardar pagamento
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
                        v-if="showActions && order?.can_update_delivery_status"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                        @click="requestAction('delivery_status', { delivery_status: 'preparing' })"
                    >
                        Em preparo
                    </button>
                    <button
                        v-if="showActions && order?.can_update_delivery_status"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                        @click="requestAction('delivery_status', { delivery_status: 'out_for_delivery' })"
                    >
                        Em entrega
                    </button>
                    <button
                        v-if="showActions && order?.can_update_delivery_status"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                        @click="requestAction('delivery_status', { delivery_status: 'delivered' })"
                    >
                        Entregue
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

    <Modal :show="printPreviewOpen" max-width="6xl" @close="closePrintPreview">
        <div class="flex w-[calc(100vw-1rem)] max-w-[96vw] flex-col gap-4 rounded-3xl bg-white p-3 sm:w-full sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">{{ printPreviewTitle || 'Pré-visualização' }}</h3>
                    <p class="text-xs text-slate-500">Confira o conteúdo antes de imprimir.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="openPreviewInNewTab"
                    >
                        Abrir em nova aba
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500"
                        @click="printFromPreview"
                    >
                        Imprimir
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                        @click="closePrintPreview"
                    >
                        Fechar
                    </button>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-2 sm:p-4">
                <iframe
                    v-if="printPreviewHtml"
                    ref="printPreviewIframe"
                    :srcdoc="printPreviewHtml"
                    class="h-[70vh] w-full rounded-xl border border-slate-200 bg-white"
                    title="Pré-visualização para impressão"
                />
                <div v-else class="flex h-[40vh] items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white text-sm text-slate-500">
                    Nenhum conteúdo disponível para visualização.
                </div>
            </div>
        </div>
    </Modal>
</template>
