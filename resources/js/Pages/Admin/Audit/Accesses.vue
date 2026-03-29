<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ShieldCheck, Monitor, Clock3, ShieldAlert, LogOut } from 'lucide-vue-next';

const props = defineProps({
    sessionDriver: {
        type: String,
        default: 'database',
    },
    activeSessions: {
        type: Array,
        default: () => [],
    },
    accessLogs: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
});

const disconnectForm = useForm({});

const rows = computed(() => (Array.isArray(props.activeSessions) ? props.activeSessions : []));
const logs = computed(() => (Array.isArray(props.accessLogs?.data) ? props.accessLogs.data : []));
const paginationLinks = computed(() => (Array.isArray(props.accessLogs?.links) ? props.accessLogs.links : []));

const statsCards = computed(() => [
    {
        key: 'active',
        label: 'Sessões ativas',
        value: String(rows.value.length),
        icon: Monitor,
        tone: 'bg-blue-100 text-blue-700',
    },
    {
        key: 'events',
        label: 'Eventos recentes',
        value: String(logs.value.length),
        icon: Clock3,
        tone: 'bg-slate-100 text-slate-700',
    },
]);

const severityClass = (severity) => {
    const safe = String(severity ?? '').trim().toLowerCase();

    if (safe === 'critical') return 'bg-rose-100 text-rose-700';
    if (safe === 'warning') return 'bg-amber-100 text-amber-700';

    return 'bg-emerald-100 text-emerald-700';
};

const disconnectAll = () => {
    if (!window.confirm('Deseja desconectar todos os dispositivos agora?')) {
        return;
    }

    disconnectForm.post(route('admin.audit.accesses.disconnect-all'));
};
</script>

<template>
    <Head title="Auditoria de acessos" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Auditoria de acessos" :show-table-view-toggle="false">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
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

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Sessões ativas por usuário</h2>
                        <p class="text-xs text-slate-500">Quando um novo login é realizado, a sessão anterior é encerrada automaticamente.</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="disconnectForm.processing"
                        @click="disconnectAll"
                    >
                        <LogOut class="h-3.5 w-3.5" />
                        {{ disconnectForm.processing ? 'Desconectando...' : 'Desconectar todos os dispositivos' }}
                    </button>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Dispositivo</th>
                                <th class="px-4 py-3">IP</th>
                                <th class="px-4 py-3">Última atividade</th>
                                <th class="px-4 py-3">Sessão</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!rows.length">
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                                    Nenhuma sessão ativa encontrada.
                                </td>
                            </tr>
                            <tr v-for="session in rows" :key="session.session_id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">
                                        {{ session.device_label }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ session.user_agent }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ session.ip_address || '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ session.last_activity || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                        :class="session.is_current ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ session.is_current ? 'Atual' : 'Encerrável' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="mb-3 flex items-center gap-2">
                    <ShieldAlert class="h-4 w-4 text-slate-600" />
                    <h2 class="text-sm font-semibold text-slate-900">Histórico de acessos</h2>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Evento</th>
                                <th class="px-4 py-3">Dispositivo</th>
                                <th class="px-4 py-3">IP</th>
                                <th class="px-4 py-3">Data/Hora</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!logs.length">
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                                    Nenhum evento de acesso registrado.
                                </td>
                            </tr>
                            <tr v-for="event in logs" :key="event.id">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="severityClass(event.severity)">
                                            {{ event.event_label }}
                                        </span>
                                    </div>
                                    <p v-if="event.terminated_sessions > 0" class="mt-1 text-xs text-slate-500">
                                        Sessões encerradas: {{ event.terminated_sessions }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    <p class="font-medium text-slate-900">{{ event.device_label }}</p>
                                    <p class="text-xs text-slate-500">{{ event.user_agent }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ event.ip_address || '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ event.occurred_at || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <PaginationLinks :links="paginationLinks" :min-links="4" />
            </section>
        </section>
    </AuthenticatedLayout>
</template>

