<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Users2, UserPlus2, MapPin, AlertCircle, Search, Filter } from 'lucide-vue-next';

const props = defineProps({
    clients: {
        type: Object,
        default: () => ({ data: [] }),
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');

watch(
    () => props.filters,
    (next) => {
        search.value = next?.search ?? '';
        status.value = next?.status ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('admin.clients.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        },
    );
};

const clearFilters = () => {
    search.value = '';
    status.value = '';
    applyFilters();
};

const rows = computed(() => props.clients?.data ?? []);

const statsCards = computed(() => [
    { key: 'total', label: 'Clientes cadastrados', value: String(props.stats?.total ?? 0), icon: Users2, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Clientes ativos', value: String(props.stats?.active ?? 0), icon: UserPlus2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'new_month', label: 'Novos no mês', value: String(props.stats?.new_month ?? 0), icon: AlertCircle, tone: 'bg-blue-100 text-blue-700' },
    { key: 'cities', label: 'Cidades atendidas', value: String(props.stats?.cities ?? 0), icon: MapPin, tone: 'bg-amber-100 text-amber-700' },
]);
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Clientes">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="stat in statsCards" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar cliente por nome, email, telefone ou cidade"
                            class="w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <select
                            v-model="status"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                            @change="applyFilters"
                        >
                            <option value="">Todos</option>
                            <option value="active">Ativos</option>
                            <option value="inactive">Inativos</option>
                        </select>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="clearFilters">
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Contato</th>
                                <th class="px-4 py-3">Documento</th>
                                <th class="px-4 py-3">Cidade</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody v-if="rows.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="client in rows" :key="client.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ client.name }}</p>
                                    <p class="text-xs text-slate-500">Criado em {{ client.created_at }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ client.email || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ client.phone || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ client.document || '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ client.city || '-' }}<span v-if="client.state">/{{ client.state }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="client.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ client.status_label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!rows.length" class="px-4 py-8 text-center text-sm text-slate-500">
                        Nenhum cliente cadastrado para este contratante.
                    </div>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
