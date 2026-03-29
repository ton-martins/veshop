<script setup>
import OrderDetailsModal from '@/Components/App/Orders/OrderDetailsModal.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    AlertCircle,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    ClipboardList,
    DollarSign,
    Package,
    Search,
    Users2,
} from 'lucide-vue-next';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
});

const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const normalizeSearch = (value) => String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();

const currentMonthLabel = computed(() =>
    new Intl.DateTimeFormat('pt-BR', {
        month: 'long',
        year: 'numeric',
    }).format(new Date()),
);

const statCards = computed(() => [
    {
        key: 'orders_today',
        title: 'Pedidos Hoje',
        value: String(props.stats?.orders_today ?? 0),
        subtitle: 'Para entrega',
        icon: CalendarDays,
        iconClass: 'bg-rose-100 text-rose-600',
    },
    {
        key: 'in_production',
        title: 'Em Produção',
        value: String(props.stats?.in_production ?? 0),
        subtitle: 'Em andamento',
        icon: Package,
        iconClass: 'bg-amber-100 text-amber-600',
    },
    {
        key: 'monthly_revenue',
        title: 'Faturamento Mensal',
        value: asCurrency(props.stats?.monthly_revenue ?? 0),
        subtitle: currentMonthLabel.value,
        icon: DollarSign,
        iconClass: 'bg-emerald-100 text-emerald-600',
    },
    {
        key: 'clients',
        title: 'Clientes',
        value: String(props.stats?.clients ?? 0),
        subtitle: 'Cadastrados',
        icon: Users2,
        iconClass: 'bg-blue-100 text-blue-600',
    },
]);

const pendingQuotes = computed(() => Number(props.stats?.pending_quotes ?? 0));
const deliveriesToday = computed(() => Number(props.stats?.deliveries_today ?? 0));

const recentOrders = computed(() => (
    Array.isArray(props.stats?.recent_orders)
        ? props.stats.recent_orders.slice(0, 15)
        : []
));

const recentDeliveries = computed(() => {
    if (Array.isArray(props.stats?.recent_deliveries)) {
        return props.stats.recent_deliveries.slice(0, 15);
    }

    return recentOrders.value
        .filter((order) => String(order?.shipping_mode ?? '') === 'delivery')
        .slice(0, 15);
});

const orderModeFilters = [
    { value: 'all', label: 'Todos' },
    { value: 'pickup', label: 'Retirada na loja' },
    { value: 'delivery', label: 'Entrega' },
];

const selectedModeFilter = ref('all');
const recentOrdersSearch = ref('');
const orderDetailsModalOpen = ref(false);
const selectedOrder = ref(null);

const filteredRecentOrders = computed(() => {
    const mode = String(selectedModeFilter.value ?? 'all');
    const searchTerm = normalizeSearch(recentOrdersSearch.value);

    return recentOrders.value.filter((order) => {
        const shippingMode = String(order?.shipping_mode ?? '');
        if (mode !== 'all' && shippingMode !== mode) {
            return false;
        }

        if (searchTerm === '') {
            return true;
        }

        const searchableText = normalizeSearch([
            order?.code,
            order?.customer,
            order?.customer_contact,
            order?.customer_email,
            order?.payment_label,
            order?.description,
            order?.shipping_address_text,
        ].filter(Boolean).join(' '));

        return searchableText.includes(searchTerm);
    });
});

const openOrderDetails = (order) => {
    if (!order?.id) return;
    selectedOrder.value = order;
    orderDetailsModalOpen.value = true;
};

const closeOrderDetails = () => {
    orderDetailsModalOpen.value = false;
    selectedOrder.value = null;
};
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="item in statCards" :key="item.key" class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
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

    <div class="rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-3 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="inline-flex items-center gap-2 text-sm font-semibold text-amber-800">
                <AlertCircle class="h-4 w-4" />
                <template v-if="pendingQuotes > 0">
                    Você tem {{ pendingQuotes }} orçamento(s) aguardando aprovação
                </template>
                <template v-else>
                    Nenhum orçamento aguardando aprovação
                </template>
            </p>

            <Link :href="route('admin.orders.index')" class="inline-flex items-center gap-1 text-xs font-semibold text-amber-800 hover:text-amber-900">
                Ver pedidos
                <ChevronRight class="h-3.5 w-3.5" />
            </Link>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
            <header class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Pedidos Recentes</h2>
                <Link :href="route('admin.orders.index')" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700">
                    Ver todos
                    <ChevronRight class="h-3.5 w-3.5" />
                </Link>
            </header>

            <div class="mb-4 flex flex-wrap items-center gap-2">
                <button
                    v-for="filter in orderModeFilters"
                    :key="`order-mode-filter-${filter.value}`"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                    :class="selectedModeFilter === filter.value
                        ? 'border-slate-900 bg-slate-900 text-white'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                    @click="selectedModeFilter = filter.value"
                >
                    {{ filter.label }}
                </button>

                <div class="veshop-search-shell ml-auto flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 sm:w-80">
                    <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                    <input
                        v-model="recentOrdersSearch"
                        type="text"
                        class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                        placeholder="Buscar por cliente, código ou pagamento"
                    >
                </div>
            </div>

            <ul v-if="filteredRecentOrders.length" class="space-y-3">
                <li
                    v-for="order in filteredRecentOrders"
                    :key="`recent-order-${order.id ?? order.code}`"
                    class="flex cursor-pointer flex-col gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 transition hover:bg-slate-100"
                    @click="openOrderDetails(order)"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-semibold text-rose-500">{{ order.code || order.id }}</p>
                        <span
                            class="rounded-full px-2 py-1 text-[11px] font-semibold"
                            :class="order.shipping_mode_tone || 'bg-slate-100 text-slate-700'"
                        >
                            {{ order.shipping_mode_label || 'Retirada na loja' }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ order.customer }}</p>
                            <p class="truncate text-xs text-slate-500">{{ order.description }}</p>
                            <p v-if="order.shipping_mode === 'delivery' && order.shipping_address_text" class="truncate text-xs text-slate-500">
                                Entrega: {{ order.shipping_address_text }}
                            </p>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Total</p>
                            <p class="text-sm font-semibold text-slate-900">{{ order.amount }}</p>
                        </div>
                    </div>
                </li>
            </ul>
            <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                Nenhum pedido encontrado com os filtros aplicados.
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
            <header class="mb-4 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <ClipboardList class="h-4 w-4 text-emerald-600" />
                    <h2 class="text-lg font-semibold text-slate-900">Entregas Recentes</h2>
                </div>
                <span class="rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700">
                    Hoje: {{ deliveriesToday }}
                </span>
            </header>

            <ul v-if="recentDeliveries.length" class="space-y-3">
                <li
                    v-for="order in recentDeliveries"
                    :key="`recent-delivery-${order.id ?? order.code}`"
                    class="flex cursor-pointer flex-col gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 transition hover:bg-slate-100"
                    @click="openOrderDetails(order)"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-emerald-600">{{ order.code || order.id }}</p>
                        <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                    </div>
                    <p class="truncate text-sm font-semibold text-slate-900">{{ order.customer }}</p>
                    <p class="truncate text-xs text-slate-500">{{ order.shipping_address_text || 'Endereço não informado' }}</p>
                    <p class="text-xs font-semibold text-slate-700">{{ order.amount }}</p>
                </li>
            </ul>
            <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                Nenhuma entrega recente registrada.
            </div>
        </section>
    </div>

    <OrderDetailsModal
        :show="orderDetailsModalOpen"
        :order="selectedOrder"
        :show-actions="false"
        @close="closeOrderDetails"
    />
</template>
