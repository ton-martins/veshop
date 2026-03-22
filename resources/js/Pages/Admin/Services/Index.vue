<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Briefcase, ClipboardList, Clock3, CircleDollarSign, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
    pipeline: {
        type: Array,
        default: () => [],
    },
    todayAppointments: {
        type: Array,
        default: () => [],
    },
});

const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const statsCards = computed(() => [
    {
        key: 'services',
        label: 'Serviços cadastrados',
        value: String(props.stats?.services ?? 0),
        icon: Briefcase,
        tone: 'text-slate-700',
    },
    {
        key: 'open',
        label: 'OS em aberto',
        value: String(props.stats?.open ?? 0),
        icon: ClipboardList,
        tone: 'text-slate-700',
    },
    {
        key: 'today',
        label: 'Atendimentos hoje',
        value: String(props.stats?.today ?? 0),
        icon: Clock3,
        tone: 'text-slate-700',
    },
    {
        key: 'revenue',
        label: 'Receita do mês',
        value: asCurrency(props.stats?.revenue ?? 0),
        icon: CircleDollarSign,
        tone: 'text-slate-700',
    },
]);

const pipelines = computed(() => {
    if (!Array.isArray(props.pipeline)) {
        return [];
    }

    return props.pipeline;
});

const todayAppointments = computed(() => (Array.isArray(props.todayAppointments) ? props.todayAppointments : []));
</script>

<template>
    <Head title="Serviços" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Serviços" :show-table-view-toggle="false">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="stat in statsCards" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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

            <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-slate-900">Pipeline de ordens</h2>
                        <Link :href="route('admin.services.orders')" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-900">
                            Ver OS
                            <ChevronRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div v-if="pipelines.length" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <article v-for="pipelineItem in pipelines" :key="pipelineItem.key" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ pipelineItem.label }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ pipelineItem.qty }}</p>
                        </article>
                    </div>
                    <div v-else class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Nenhuma ordem de serviço em andamento.
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
                        <Link :href="route('admin.services.schedule', { layout: 'month' })" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            Agenda de serviços
                            <ChevronRight class="h-4 w-4" />
                        </Link>
                        <Link :href="route('admin.services.accounting')" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            Gestão contábil
                            <ChevronRight class="h-4 w-4" />
                        </Link>
                    </div>
                </section>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <h2 class="text-sm font-semibold text-slate-900">Atendimentos de hoje</h2>
                <div class="mt-3 flex justify-end">
                    <TableViewToggle />
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">OS</th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Serviço</th>
                                <th class="px-4 py-3">Horário</th>
                                <th class="px-4 py-3">Responsável</th>
                            </tr>
                        </thead>
                        <tbody v-if="todayAppointments.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="appointment in todayAppointments" :key="appointment.id">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ appointment.service_order_code }}</td>
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
