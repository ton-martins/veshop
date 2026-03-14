<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { CalendarClock, Clock3, MapPin, UserRound, Filter } from 'lucide-vue-next';

const stats = [
    { key: 'today', label: 'Visitas hoje', value: '11', icon: CalendarClock, tone: 'bg-blue-100 text-blue-700' },
    { key: 'next', label: 'Proximas 24h', value: '7', icon: Clock3, tone: 'bg-slate-100 text-slate-700' },
    { key: 'teams', label: 'Tecnicos alocados', value: '6', icon: UserRound, tone: 'bg-emerald-100 text-emerald-700' },
];

const timeline = [
    { id: 'AG-201', time: '08:30', customer: 'Auto Eletrica Paulista', service: 'Diagnostico eletrico', address: 'Av. Brasil, 442', technician: 'Bruna Castro', status: 'Confirmado' },
    { id: 'AG-204', time: '10:00', customer: 'Mercado Aurora', service: 'Troca de painel', address: 'Rua das Flores, 90', technician: 'Carlos Lima', status: 'Em rota' },
    { id: 'AG-209', time: '14:20', customer: 'Bazar Bela Vista', service: 'Instalacao de sistema', address: 'Rua Central, 112', technician: 'Joao Paulo', status: 'Confirmado' },
    { id: 'AG-212', time: '16:00', customer: 'Loja Centro Sul', service: 'Revisao tecnica', address: 'Rua Sao Jose, 19', technician: 'Renato Souza', status: 'Pendente' },
];
</script>

<template>
    <Head title="Agenda Tecnica" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Agenda Tecnica">
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
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-slate-900">Roteiro do dia</h2>
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        <Filter class="h-3.5 w-3.5" />
                        Filtrar tecnico
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <article
                        v-for="item in timeline"
                        :key="item.id"
                        class="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ item.time }} - {{ item.customer }}</p>
                                <p class="text-xs text-slate-600">{{ item.service }}</p>
                            </div>
                            <span
                                class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                :class="item.status === 'Confirmado'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : item.status === 'Em rota'
                                      ? 'bg-blue-100 text-blue-700'
                                      : 'bg-amber-100 text-amber-700'"
                            >
                                {{ item.status }}
                            </span>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                            <span class="inline-flex items-center gap-1">
                                <MapPin class="h-3.5 w-3.5" />
                                {{ item.address }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <UserRound class="h-3.5 w-3.5" />
                                {{ item.technician }}
                            </span>
                        </div>
                    </article>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>

