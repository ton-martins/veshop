<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Truck, PackageCheck, Timer, AlertTriangle, Search, Filter, Plus } from 'lucide-vue-next';

const stats = [
    { key: 'suppliers', label: 'Fornecedores ativos', value: '64', icon: Truck, tone: 'bg-slate-100 text-slate-700' },
    { key: 'pending', label: 'Pedidos pendentes', value: '19', icon: PackageCheck, tone: 'bg-blue-100 text-blue-700' },
    { key: 'lead', label: 'Lead time médio', value: '5 dias', icon: Timer, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'risk', label: 'Risco de atraso', value: '3', icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
];

const suppliers = [
    { name: 'Distribuidora Bahia Sul', segment: 'Insumos', sla: '4 dias', nextDelivery: 'Amanhã', status: 'Regular' },
    { name: 'Embalagens Prime', segment: 'Embalagens', sla: '2 dias', nextDelivery: 'Hoje', status: 'Excelente' },
    { name: 'Laticínios Central', segment: 'Perecíveis', sla: '3 dias', nextDelivery: 'Sexta', status: 'Regular' },
    { name: 'Atacado Vitória', segment: 'Secos e molhados', sla: '7 dias', nextDelivery: 'Atrasado', status: 'Atenção' },
];

const plannedPurchases = [
    { item: 'Farinha especial', qty: '120 kg', eta: 'Quarta' },
    { item: 'Chocolate premium', qty: '60 kg', eta: 'Quinta' },
    { item: 'Embalagem box M', qty: '800 un', eta: 'Sexta' },
];
</script>

<template>
    <Head title="Fornecedores" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Fornecedores">
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
                    <div class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="h-4 w-4 text-slate-500" />
                        <input type="text" placeholder="Buscar fornecedor por nome ou segmento" class="w-full bg-transparent text-sm text-slate-700 outline-none" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Filter class="h-3.5 w-3.5" />
                            Filtros
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            <Plus class="h-3.5 w-3.5" />
                            Novo fornecedor
                        </button>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Fornecedor</th>
                                    <th class="px-4 py-3">Segmento</th>
                                    <th class="px-4 py-3">SLA</th>
                                    <th class="px-4 py-3">Próxima entrega</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="supplier in suppliers" :key="supplier.name">
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ supplier.name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ supplier.segment }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ supplier.sla }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ supplier.nextDelivery }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="supplier.status === 'Excelente' ? 'bg-blue-100 text-blue-700' : supplier.status === 'Atenção' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'">
                                            {{ supplier.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <aside class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <h2 class="text-sm font-semibold text-slate-900">Compras planejadas</h2>
                        <ul class="space-y-2">
                            <li v-for="purchase in plannedPurchases" :key="purchase.item" class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
                                <p class="text-sm font-semibold text-slate-800">{{ purchase.item }}</p>
                                <p class="text-xs text-slate-500">{{ purchase.qty }} • ETA {{ purchase.eta }}</p>
                            </li>
                        </ul>
                    </aside>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
