<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ChartNoAxesCombined, CircleDollarSign, Boxes, ShoppingCart, Download, FileText, BarChart3, PieChart } from 'lucide-vue-next';

const stats = [
    { key: 'revenue', label: 'Faturamento (mês)', value: 'R$ 86.430', icon: CircleDollarSign, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'orders', label: 'Pedidos faturados', value: '214', icon: ShoppingCart, tone: 'bg-blue-100 text-blue-700' },
    { key: 'stock_turn', label: 'Giro de estoque', value: '4,2x', icon: Boxes, tone: 'bg-amber-100 text-amber-700' },
    { key: 'margin', label: 'Margem bruta', value: '32,4%', icon: ChartNoAxesCombined, tone: 'bg-slate-100 text-slate-700' },
];

const reportCards = [
    { title: 'Vendas por período', description: 'Análise diária, semanal e mensal.', icon: BarChart3 },
    { title: 'Curva ABC de produtos', description: 'Participação no faturamento.', icon: PieChart },
    { title: 'DRE simplificado', description: 'Receita, custos e margem.', icon: FileText },
];

const exportsHistory = [
    { file: 'vendas-marco-2026.xlsx', by: 'Everton Martins', when: 'Hoje 10:12' },
    { file: 'dre-fevereiro-2026.pdf', by: 'Everton Martins', when: 'Ontem 17:30' },
    { file: 'estoque-critico.csv', by: 'Sistema', when: 'Ontem 08:05' },
];
</script>

<template>
    <Head title="Relatórios" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Relatórios">
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

            <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <header class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Painéis disponíveis</h2>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            <Download class="h-3.5 w-3.5" />
                            Exportar dados
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
                    <h2 class="text-sm font-semibold text-slate-900">Exportações recentes</h2>
                    <ul class="mt-4 space-y-2">
                        <li v-for="item in exportsHistory" :key="item.file" class="rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2">
                            <p class="text-sm font-semibold text-slate-800">{{ item.file }}</p>
                            <p class="text-xs text-slate-500">{{ item.by }} • {{ item.when }}</p>
                        </li>
                    </ul>
                </aside>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
