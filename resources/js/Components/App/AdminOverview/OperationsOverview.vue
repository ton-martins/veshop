<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    ClipboardList,
    DollarSign,
    Package,
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
const recentOrders = computed(() => props.stats?.recent_orders ?? []);
const deliveriesToday = computed(() => Number(props.stats?.deliveries_today ?? 0));
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
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl" :class="item.iconClass">
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
            <header class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Pedidos Recentes</h2>
                <Link :href="route('admin.orders.index')" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700">
                    Ver todos
                    <ChevronRight class="h-3.5 w-3.5" />
                </Link>
            </header>

            <ul v-if="recentOrders.length" class="space-y-3">
                <li
                    v-for="order in recentOrders"
                    :key="order.id"
                    class="flex flex-col gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-rose-500">{{ order.id }}</p>
                        <p class="truncate text-sm font-semibold text-slate-900">{{ order.customer }}</p>
                        <p class="truncate text-xs text-slate-500">{{ order.description }}</p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Orçamento</p>
                        <p class="text-sm font-semibold text-slate-900">{{ order.amount }}</p>
                    </div>
                </li>
            </ul>
            <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                Nenhum pedido registrado.
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
            <header class="mb-6 flex items-center gap-2">
                <ClipboardList class="h-4 w-4 text-rose-500" />
                <h2 class="text-lg font-semibold text-slate-900">Entregas Hoje</h2>
            </header>

            <div class="flex min-h-[220px] flex-col items-center justify-center rounded-xl border border-dashed border-emerald-200 bg-emerald-50/40 text-center">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <CheckCircle2 class="h-6 w-6" />
                </span>
                <p class="mt-4 text-sm font-semibold text-slate-700">
                    <template v-if="deliveriesToday > 0">
                        {{ deliveriesToday }} entrega(s) prevista(s) para hoje
                    </template>
                    <template v-else>
                        Nenhuma entrega para hoje
                    </template>
                </p>
            </div>
        </section>
    </div>
</template>
