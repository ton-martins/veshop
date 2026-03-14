<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Briefcase, Search, Filter, Plus, Clock3, CircleDollarSign } from 'lucide-vue-next';

const stats = [
    { key: 'total', label: 'Servicos cadastrados', value: '48', icon: Briefcase, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Servicos ativos', value: '44', icon: Clock3, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'avg', label: 'Ticket medio', value: 'R$ 280', icon: CircleDollarSign, tone: 'bg-blue-100 text-blue-700' },
];

const services = [
    { code: 'SER-001', name: 'Diagnostico eletrico', category: 'Auto eletrica', duration: '01h 30m', price: 'R$ 180,00', status: 'Ativo' },
    { code: 'SER-008', name: 'Instalacao de modulo', category: 'Auto eletrica', duration: '02h 00m', price: 'R$ 320,00', status: 'Ativo' },
    { code: 'SER-014', name: 'Manutencao preventiva', category: 'Industrial', duration: '03h 00m', price: 'R$ 540,00', status: 'Ativo' },
    { code: 'SER-022', name: 'Visita tecnica externa', category: 'Suporte tecnico', duration: '01h 00m', price: 'R$ 150,00', status: 'Inativo' },
];
</script>

<template>
    <Head title="Catalogo de Servicos" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Catalogo de Servicos">
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
                        <input type="text" placeholder="Buscar servico por codigo ou nome" class="w-full bg-transparent text-sm text-slate-700 outline-none" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Filter class="h-3.5 w-3.5" />
                            Categorias
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            <Plus class="h-3.5 w-3.5" />
                            Novo servico
                        </button>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Servico</th>
                                <th class="px-4 py-3">Categoria</th>
                                <th class="px-4 py-3">Duracao padrao</th>
                                <th class="px-4 py-3">Preco base</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="service in services" :key="service.code">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ service.name }}</p>
                                    <p class="text-xs text-slate-500">{{ service.code }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ service.category }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ service.duration }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ service.price }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="service.status === 'Ativo' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ service.status }}
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

