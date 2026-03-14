<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Briefcase, ClipboardList, Clock3, CircleDollarSign, ChevronRight } from 'lucide-vue-next';

const stats = [
    { key: 'services', label: 'Serviços cadastrados', value: '0', icon: Briefcase, tone: 'bg-slate-100 text-slate-700' },
    { key: 'open', label: 'OS em aberto', value: '0', icon: ClipboardList, tone: 'bg-amber-100 text-amber-700' },
    { key: 'today', label: 'Atendimentos hoje', value: '0', icon: Clock3, tone: 'bg-blue-100 text-blue-700' },
    { key: 'revenue', label: 'Receita do mês', value: 'R$ 0,00', icon: CircleDollarSign, tone: 'bg-emerald-100 text-emerald-700' },
];

const pipelines = [
    { key: 'triagem', label: 'Triagem', qty: 0 },
    { key: 'execucao', label: 'Em execução', qty: 0 },
    { key: 'aguardo', label: 'Aguardando peça', qty: 0 },
    { key: 'finalizacao', label: 'Finalização', qty: 0 },
];

const todayAppointments = [];
</script>

<template>
    <Head title="Serviços" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Serviços">
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

            <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-slate-900">Pipeline de ordens</h2>
                        <Link :href="route('admin.services.orders')" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-900">
                            Ver OS
                            <ChevronRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <article v-for="pipeline in pipelines" :key="pipeline.key" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ pipeline.label }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ pipeline.qty }}</p>
                        </article>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-slate-900">Atalhos</h2>
                    </div>

                    <div class="mt-4 space-y-2">
                        <Link :href="route('admin.services.catalog')" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            Catálogo de serviços
                            <ChevronRight class="h-4 w-4" />
                        </Link>
                        <Link :href="route('admin.services.orders')" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            Ordens de serviço
                            <ChevronRight class="h-4 w-4" />
                        </Link>
                        <Link :href="route('admin.services.schedule')" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            Agenda técnica
                            <ChevronRight class="h-4 w-4" />
                        </Link>
                    </div>
                </section>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <h2 class="text-sm font-semibold text-slate-900">Atendimentos de hoje</h2>
                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">OS</th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Serviço</th>
                                <th class="px-4 py-3">Horário</th>
                                <th class="px-4 py-3">Técnico</th>
                            </tr>
                        </thead>
                        <tbody v-if="todayAppointments.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="appointment in todayAppointments" :key="appointment.id">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ appointment.id }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ appointment.customer }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ appointment.service }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ appointment.time }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ appointment.technician }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!todayAppointments.length" class="px-4 py-8 text-center text-sm text-slate-500">
                        Nenhum atendimento agendado para hoje.
                    </div>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
