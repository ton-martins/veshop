<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Users2, UserPlus2, BadgeDollarSign, AlertCircle, Search, Filter, Plus } from 'lucide-vue-next';

const stats = [
    { key: 'active', label: 'Clientes ativos', value: '312', icon: Users2, tone: 'bg-slate-100 text-slate-700' },
    { key: 'new_month', label: 'Novos no mês', value: '24', icon: UserPlus2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'ticket', label: 'Ticket médio', value: 'R$ 186,00', icon: BadgeDollarSign, tone: 'bg-blue-100 text-blue-700' },
    { key: 'late', label: 'Inadimplentes', value: '8', icon: AlertCircle, tone: 'bg-amber-100 text-amber-700' },
];

const clients = [
    { name: 'Cristina Nascimento', segment: 'Pessoa Física', city: 'Salvador/BA', lastOrder: 'Há 2 dias', revenue: 'R$ 1.240,00', status: 'Ativo' },
    { name: 'Cakeflow Eventos', segment: 'Pessoa Jurídica', city: 'Lauro de Freitas/BA', lastOrder: 'Hoje', revenue: 'R$ 4.930,00', status: 'VIP' },
    { name: 'João Martins', segment: 'Pessoa Física', city: 'Camaçari/BA', lastOrder: 'Há 7 dias', revenue: 'R$ 690,00', status: 'Ativo' },
    { name: 'Buffet Estrela', segment: 'Pessoa Jurídica', city: 'Feira de Santana/BA', lastOrder: 'Há 15 dias', revenue: 'R$ 2.180,00', status: 'Atenção' },
];

const pipeline = [
    { stage: 'Leads', value: 38, tone: 'bg-slate-100 text-slate-700' },
    { stage: 'Negociação', value: 17, tone: 'bg-blue-100 text-blue-700' },
    { stage: 'Fechados', value: 9, tone: 'bg-emerald-100 text-emerald-700' },
];
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Clientes">
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
                        <input type="text" placeholder="Buscar cliente por nome, email ou cidade" class="w-full bg-transparent text-sm text-slate-700 outline-none" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Filter class="h-3.5 w-3.5" />
                            Segmentos
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            <Plus class="h-3.5 w-3.5" />
                            Novo cliente
                        </button>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Segmento</th>
                                    <th class="px-4 py-3">Último pedido</th>
                                    <th class="px-4 py-3">Faturamento</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="client in clients" :key="client.name">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ client.name }}</p>
                                        <p class="text-xs text-slate-500">{{ client.city }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ client.segment }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ client.lastOrder }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ client.revenue }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="client.status === 'VIP' ? 'bg-blue-100 text-blue-700' : client.status === 'Atenção' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'">
                                            {{ client.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <aside class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <h2 class="text-sm font-semibold text-slate-900">Funil comercial</h2>
                        <ul class="space-y-2">
                            <li v-for="item in pipeline" :key="item.stage" class="flex items-center justify-between rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
                                <span class="text-sm font-semibold text-slate-700">{{ item.stage }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="item.tone">{{ item.value }}</span>
                            </li>
                        </ul>
                    </aside>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
