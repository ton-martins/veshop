<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Building2, CircleCheckBig, Clock3, Ban, Search, Plus, Filter } from 'lucide-vue-next';

const stats = [
    { key: 'total', label: 'Contratantes', value: '42', icon: Building2, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Ativos', value: '37', icon: CircleCheckBig, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'trial', label: 'Em trial', value: '3', icon: Clock3, tone: 'bg-blue-100 text-blue-700' },
    { key: 'blocked', label: 'Bloqueados', value: '2', icon: Ban, tone: 'bg-amber-100 text-amber-700' },
];

const contractors = [
    { name: 'Veshop Mix', plan: 'Pro', admins: 4, monthly: 'R$ 399,00', status: 'Ativo' },
    { name: 'Veshop Store', plan: 'Business', admins: 7, monthly: 'R$ 799,00', status: 'Ativo' },
    { name: 'Doce Encanto', plan: 'Start', admins: 2, monthly: 'R$ 199,00', status: 'Trial' },
    { name: 'Atacado Litoral', plan: 'Pro', admins: 3, monthly: 'R$ 399,00', status: 'Bloqueado' },
];
</script>

<template>
    <Head title="Contratantes" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Contratantes">
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
                        <input type="text" placeholder="Buscar contratante por nome" class="w-full bg-transparent text-sm text-slate-700 outline-none" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Filter class="h-3.5 w-3.5" />
                            Plano
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            <Plus class="h-3.5 w-3.5" />
                            Novo contratante
                        </button>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Contratante</th>
                                <th class="px-4 py-3">Plano</th>
                                <th class="px-4 py-3">Admins</th>
                                <th class="px-4 py-3">Mensalidade</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="contractor in contractors" :key="contractor.name">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ contractor.name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ contractor.plan }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ contractor.admins }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ contractor.monthly }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="contractor.status === 'Ativo' ? 'bg-emerald-100 text-emerald-700' : contractor.status === 'Trial' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'">
                                        {{ contractor.status }}
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
