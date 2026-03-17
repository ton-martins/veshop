<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ChartNoAxesCombined, CircleDollarSign, Boxes, ShoppingCart, Download, FileText, BarChart3, PieChart } from 'lucide-vue-next';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            revenue: 0,
            orders: 0,
            stock_turn: 0,
            margin: 0,
        }),
    },
    exportsHistory: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const exportProcessing = ref(false);

const statCards = computed(() => ([
    {
        key: 'revenue',
        label: 'Faturamento (mes)',
        value: formatCurrency(props.stats?.revenue),
        icon: CircleDollarSign,
        tone: 'bg-emerald-100 text-emerald-700',
    },
    {
        key: 'orders',
        label: 'Pedidos faturados',
        value: String(props.stats?.orders ?? 0),
        icon: ShoppingCart,
        tone: 'bg-blue-100 text-blue-700',
    },
    {
        key: 'stock_turn',
        label: 'Giro de estoque',
        value: `${Number(props.stats?.stock_turn ?? 0).toFixed(1)}x`,
        icon: Boxes,
        tone: 'bg-amber-100 text-amber-700',
    },
    {
        key: 'margin',
        label: 'Margem bruta',
        value: `${Number(props.stats?.margin ?? 0).toFixed(1)}%`,
        icon: ChartNoAxesCombined,
        tone: 'bg-slate-100 text-slate-700',
    },
]));

const reportCards = [
    { title: 'Vendas por periodo', description: 'Analise diaria, semanal e mensal.', icon: BarChart3 },
    { title: 'Curva ABC de produtos', description: 'Participacao no faturamento.', icon: PieChart },
    { title: 'DRE simplificado', description: 'Receita, custos e margem.', icon: FileText },
];

const flashStatus = computed(() => String(page.props?.flash?.status ?? '').trim());

const requestSalesExport = () => {
    if (exportProcessing.value) return;

    router.post(route('admin.reports.exports.sales'), {}, {
        preserveScroll: true,
        onStart: () => {
            exportProcessing.value = true;
        },
        onFinish: () => {
            exportProcessing.value = false;
        },
    });
};

const formatCurrency = (value) => {
    const parsed = Number(value ?? 0);

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number.isFinite(parsed) ? parsed : 0);
};
</script>

<template>
    <Head title="Relatorios" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Relatorios">
        <section class="space-y-4">
            <div
                v-if="flashStatus"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            >
                {{ flashStatus }}
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="stat in statCards" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">{{ stat.label }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ stat.value }}</p>
                        </div>
                        <span class="veshop-stat-icon inline-flex h-9 w-9 items-center justify-center rounded-xl" :class="stat.tone">
                            <component :is="stat.icon" class="h-4 w-4" />
                        </span>
                    </div>
                </article>
            </div>

            <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <header class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Paineis disponiveis</h2>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="exportProcessing"
                            @click="requestSalesExport"
                        >
                            <Download class="h-3.5 w-3.5" />
                            {{ exportProcessing ? 'Enfileirando...' : 'Exportar dados' }}
                        </button>
                    </header>

                    <div class="grid gap-3 md:grid-cols-3">
                        <article v-for="card in reportCards" :key="card.title" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white text-slate-700 ring-1 ring-slate-200">
                                <component :is="card.icon" class="h-4 w-4" />
                            </span>
                            <h3 class="mt-3 text-sm font-semibold text-slate-900">{{ card.title }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ card.description }}</p>
                        </article>
                    </div>
                </section>

                <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <h2 class="text-sm font-semibold text-slate-900">Exportacoes recentes</h2>
                    <ul v-if="props.exportsHistory.length" class="mt-4 space-y-2">
                        <li v-for="item in props.exportsHistory" :key="item.id" class="rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-800">{{ item.file }}</p>
                                    <p class="text-xs text-slate-500">{{ item.by }} • {{ item.when }}</p>
                                    <p v-if="item.rows !== null" class="text-xs text-slate-500">{{ item.rows }} linhas</p>
                                    <p v-if="item.error" class="mt-1 text-xs text-rose-600">{{ item.error }}</p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="item.status_tone">
                                        {{ item.status }}
                                    </span>
                                    <a
                                        v-if="item.download_url"
                                        :href="item.download_url"
                                        class="text-xs font-semibold text-slate-700 underline decoration-dotted underline-offset-2 hover:text-slate-900"
                                    >
                                        Baixar
                                    </a>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                        Nenhuma exportacao realizada.
                    </div>
                </aside>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
