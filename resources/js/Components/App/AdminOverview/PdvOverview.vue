<script setup>
import OrderDetailsModal from '@/Components/App/Orders/OrderDetailsModal.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    ChevronRight,
    ClipboardList,
    Clock3,
    CreditCard,
    DollarSign,
    Plus,
    QrCode,
    ShoppingCart,
    Wallet,
} from 'lucide-vue-next';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
});

const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const pdvStats = computed(() => [
    {
        key: 'sales_today',
        title: 'Vendas Hoje',
        value: asCurrency(props.stats?.sales_today ?? 0),
        subtitle: `${Number(props.stats?.sales_count ?? 0)} venda(s) concluída(s)`,
        icon: ShoppingCart,
        iconClass: 'bg-slate-100 text-slate-700',
    },
    {
        key: 'avg_ticket',
        title: 'Ticket Médio',
        value: asCurrency(props.stats?.avg_ticket ?? 0),
        subtitle: 'Com base nas vendas do dia',
        icon: DollarSign,
        iconClass: 'bg-emerald-100 text-emerald-600',
    },
    {
        key: 'cash_status',
        title: 'Status do Caixa',
        value: props.stats?.cash_open ? 'Aberto' : 'Fechado',
        subtitle: props.stats?.cash_open ? 'Pronto para operação' : 'Abra o caixa para iniciar',
        icon: Wallet,
        iconClass: 'bg-amber-100 text-amber-700',
    },
    {
        key: 'pending',
        title: 'Orçamentos PDV',
        value: String(props.stats?.pending_quotes ?? 0),
        subtitle: 'Aguardando finalização',
        icon: ClipboardList,
        iconClass: 'bg-blue-100 text-blue-700',
    },
]);

const pdvShortcuts = [
    {
        key: 'new_sale',
        title: 'Nova venda',
        description: 'Iniciar atendimento rápido',
        icon: Plus,
        href: () => route('admin.pdv.index'),
    },
    {
        key: 'read_code',
        title: 'Ler código',
        description: 'Adicionar item por leitura',
        icon: QrCode,
        href: () => route('admin.pdv.index'),
    },
    {
        key: 'cash_open',
        title: 'Abrir caixa',
        description: 'Definir troco inicial',
        icon: Wallet,
        href: () => route('admin.pdv.index', { action: 'open-cash' }),
    },
    {
        key: 'close_cash',
        title: 'Fechar caixa',
        description: 'Conferência e fechamento',
        icon: Clock3,
        href: () => route('admin.pdv.index', { action: 'close-cash' }),
    },
];

const pdvRecentSales = computed(() => props.stats?.recent_sales ?? []);
const cashOpen = computed(() => Boolean(props.stats?.cash_open));
const saleDetailsModalOpen = ref(false);
const selectedSale = ref(null);

const openSaleDetails = (sale) => {
    if (!sale?.id) return;
    selectedSale.value = sale;
    saleDetailsModalOpen.value = true;
};

const closeSaleDetails = () => {
    saleDetailsModalOpen.value = false;
    selectedSale.value = null;
};

const pdvPaymentSummary = computed(() => {
    const summary = props.stats?.payment_summary ?? {};
    return [
        { key: 'pix', label: 'Pix', value: asCurrency(summary.pix ?? 0), icon: QrCode },
        { key: 'credit', label: 'Cartão', value: asCurrency(summary.credit ?? 0), icon: CreditCard },
        { key: 'cash', label: 'Dinheiro', value: asCurrency(summary.cash ?? 0), icon: Wallet },
    ];
});
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="item in pdvStats" :key="item.key" class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-600">{{ item.title }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ item.value }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ item.subtitle }}</p>
                </div>
                <span class="veshop-stat-icon inline-flex h-9 w-9 items-center justify-center rounded-xl" :class="item.iconClass">
                    <component :is="item.icon" class="h-4 w-4" />
                </span>
            </div>
        </article>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">PDV Instantâneo</h2>
                <p class="mt-1 text-sm text-slate-500">Fluxo rápido de venda no balcão com atalhos de operação.</p>
            </div>
            <Link :href="route('admin.pdv.index')" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                <Plus class="h-4 w-4" />
                Nova venda
            </Link>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Link
                v-for="shortcut in pdvShortcuts"
                :key="shortcut.key"
                :href="shortcut.href()"
                class="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-left transition hover:bg-slate-100"
            >
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white text-slate-700 ring-1 ring-slate-200">
                    <component :is="shortcut.icon" class="h-4 w-4" />
                </span>
                <p class="mt-3 text-sm font-semibold text-slate-900">{{ shortcut.title }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ shortcut.description }}</p>
            </Link>
        </div>
    </section>

    <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
            <header class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Vendas Recentes do PDV</h2>
                <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700">
                    Ver histórico
                    <ChevronRight class="h-3.5 w-3.5" />
                </button>
            </header>

            <ul v-if="pdvRecentSales.length" class="space-y-3">
                <li
                    v-for="sale in pdvRecentSales"
                    :key="sale.id ?? sale.code"
                    class="flex cursor-pointer flex-col gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 transition hover:bg-slate-100 sm:flex-row sm:items-center sm:justify-between"
                    @click="openSaleDetails(sale)"
                >
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-slate-500">{{ sale.code || sale.id }}</p>
                        <p class="truncate text-sm font-semibold text-slate-900">{{ sale.customer }}</p>
                        <p class="truncate text-xs text-slate-500">{{ sale.payment_label || sale.payment }}</p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p class="text-sm font-semibold text-slate-900">{{ sale.amount }}</p>
                        <p class="text-[11px] text-slate-500">{{ sale.time || sale.created_at }}</p>
                    </div>
                </li>
            </ul>
            <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                Nenhuma venda registrada hoje.
            </div>
        </section>

        <section class="space-y-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <h3 class="text-sm font-semibold text-slate-900">Resumo de pagamentos</h3>
                <ul class="mt-4 space-y-3">
                    <li
                        v-for="payment in pdvPaymentSummary"
                        :key="payment.key"
                        class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2"
                    >
                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <component :is="payment.icon" class="h-3.5 w-3.5" />
                            {{ payment.label }}
                        </span>
                        <span class="text-sm font-semibold text-slate-900">{{ payment.value }}</span>
                    </li>
                </ul>
            </article>

            <article class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm md:p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Caixa</p>
                <p class="mt-2 text-lg font-semibold text-emerald-900">
                    {{ cashOpen ? 'Caixa aberto' : 'Pronto para iniciar' }}
                </p>
                <p class="mt-1 text-xs text-emerald-800/80">Abra o caixa para liberar vendas instantâneas no PDV.</p>
                <Link :href="route('admin.pdv.index', { action: cashOpen ? 'close-cash' : 'open-cash' })" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">
                    <Wallet class="h-3.5 w-3.5" />
                    {{ cashOpen ? 'Fechar caixa' : 'Abrir caixa' }}
                </Link>
            </article>
        </section>
    </div>

    <OrderDetailsModal
        :show="saleDetailsModalOpen"
        :order="selectedSale"
        :show-actions="false"
        @close="closeSaleDetails"
    />
</template>
