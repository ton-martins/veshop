<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ClipboardList, Clock3, CircleCheckBig, Search, Filter, Plus } from 'lucide-vue-next';

const stats = [
    { key: 'open', label: 'OS em aberto', value: '19', icon: ClipboardList, tone: 'bg-amber-100 text-amber-700' },
    { key: 'running', label: 'Em execucao', value: '8', icon: Clock3, tone: 'bg-blue-100 text-blue-700' },
    { key: 'done', label: 'Concluidas no mes', value: '54', icon: CircleCheckBig, tone: 'bg-emerald-100 text-emerald-700' },
];

const serviceOrders = [
    { code: 'OS-1042', customer: 'Mercado Aurora', service: 'Manutencao de painel', technician: 'Carlos Lima', dueAt: '14/03 17:00', status: 'Em execucao' },
    { code: 'OS-1044', customer: 'Auto Eletrica Paulista', service: 'Diagnostico eletrico', technician: 'Bruna Castro', dueAt: '14/03 18:30', status: 'Triagem' },
    { code: 'OS-1039', customer: 'Bazar Bela Vista', service: 'Instalacao de sistema', technician: 'Joao Paulo', dueAt: '15/03 10:00', status: 'Aguardando peca' },
    { code: 'OS-1033', customer: 'Loja Central', service: 'Revisao de cablagem', technician: 'Renato Souza', dueAt: '13/03 16:30', status: 'Concluida' },
];
</script>

<template>
    <Head title="Ordens de Servico" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Ordens de Servico">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
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
                        <input type="text" placeholder="Buscar por OS ou cliente" class="w-full bg-transparent text-sm text-slate-700 outline-none" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Filter class="h-3.5 w-3.5" />
                            Status
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            <Plus class="h-3.5 w-3.5" />
                            Nova OS
                        </button>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">OS</th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Servico</th>
                                <th class="px-4 py-3">Tecnico</th>
                                <th class="px-4 py-3">Prazo</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="order in serviceOrders" :key="order.code">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ order.code }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ order.customer }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ order.service }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ order.technician }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ order.dueAt }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                        :class="order.status === 'Concluida'
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : order.status === 'Em execucao'
                                              ? 'bg-blue-100 text-blue-700'
                                              : order.status === 'Triagem'
                                                ? 'bg-amber-100 text-amber-700'
                                                : 'bg-slate-200 text-slate-700'"
                                    >
                                        {{ order.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>

