<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { LifeBuoy, Clock3, CheckCircle2, Layers3 } from 'lucide-vue-next';

const stats = [
    { key: 'open', label: 'Chamados abertos', value: '23', icon: LifeBuoy, tone: 'bg-blue-100 text-blue-700' },
    { key: 'sla', label: 'SLA médio', value: '3h12', icon: Clock3, tone: 'bg-slate-100 text-slate-700' },
    { key: 'resolved', label: 'Resolvidos hoje', value: '12', icon: CheckCircle2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'backlog', label: 'Backlog', value: '31', icon: Layers3, tone: 'bg-amber-100 text-amber-700' },
];

const tickets = [
    { id: '#SUP-1284', contractor: 'Doce Encanto', subject: 'Erro ao emitir NFC-e', priority: 'Alta', sla: '1h' },
    { id: '#SUP-1281', contractor: 'Veshop Mix', subject: 'Ajuste de layout no PDV', priority: 'Média', sla: '4h' },
    { id: '#SUP-1279', contractor: 'Veshop Store', subject: 'Usuário sem permissão', priority: 'Baixa', sla: '8h' },
];

const team = [
    { name: 'Everton Martins', tickets: 9, avg: '2h40' },
    { name: 'Ana Suporte', tickets: 7, avg: '3h15' },
    { name: 'Lucas CS', tickets: 6, avg: '2h55' },
];
</script>

<template>
    <Head title="Suporte aos Contratantes" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Suporte aos Contratantes">
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
                    <h2 class="text-lg font-semibold text-slate-900">Fila de atendimento</h2>
                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Ticket</th>
                                    <th class="px-4 py-3">Contratante</th>
                                    <th class="px-4 py-3">Assunto</th>
                                    <th class="px-4 py-3">Prioridade</th>
                                    <th class="px-4 py-3">SLA</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="ticket in tickets" :key="ticket.id">
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ ticket.id }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ ticket.contractor }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ ticket.subject }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="ticket.priority === 'Alta' ? 'bg-amber-100 text-amber-700' : ticket.priority === 'Média' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700'">
                                            {{ ticket.priority }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ ticket.sla }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <h2 class="text-sm font-semibold text-slate-900">Desempenho do time</h2>
                    <ul class="mt-4 space-y-2">
                        <li v-for="member in team" :key="member.name" class="rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2">
                            <p class="text-sm font-semibold text-slate-800">{{ member.name }}</p>
                            <p class="text-xs text-slate-500">{{ member.tickets }} tickets • tempo médio {{ member.avg }}</p>
                        </li>
                    </ul>
                </aside>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
