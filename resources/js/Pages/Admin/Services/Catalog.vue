<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Briefcase, Search, Filter, Clock3, CircleDollarSign } from 'lucide-vue-next';

const props = defineProps({
    services: {
        type: Object,
        default: () => ({ data: [] }),
    },
    categories: {
        type: Array,
        default: () => [],
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
const categoryId = ref(props.filters?.category_id ?? '');

watch(
    () => props.filters,
    (next) => {
        search.value = next?.search ?? '';
        status.value = next?.status ?? '';
        categoryId.value = next?.category_id ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('admin.services.catalog'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            category_id: categoryId.value || undefined,
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
    categoryId.value = '';
    applyFilters();
};

const rows = computed(() => props.services?.data ?? []);
const paginationLinks = computed(() => props.services?.links ?? []);
const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const formatDuration = (minutes) => `${Number(minutes ?? 0)} min`;

const statsCards = computed(() => [
    { key: 'total', label: 'Serviços cadastrados', value: String(props.stats?.total ?? 0), icon: Briefcase, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Serviços ativos', value: String(props.stats?.active ?? 0), icon: Clock3, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'avg_price', label: 'Preço médio', value: asCurrency(props.stats?.avg_price ?? 0), icon: CircleDollarSign, tone: 'bg-blue-100 text-blue-700' },
]);
</script>

<template>
    <Head title="Catálogo de Serviços" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Catálogo de Serviços">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
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
                            placeholder="Buscar serviço por código ou nome"
                            class="w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <select
                            v-model="categoryId"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                            @change="applyFilters"
                        >
                            <option value="">Todas categorias</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
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
                                <th class="px-4 py-3">Serviço</th>
                                <th class="px-4 py-3">Categoria</th>
                                <th class="px-4 py-3">Duração padrão</th>
                                <th class="px-4 py-3">Preço base</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody v-if="rows.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="service in rows" :key="service.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ service.name }}</p>
                                    <p class="text-xs text-slate-500">{{ service.code || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ service.category_name || '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ formatDuration(service.duration_minutes) }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ asCurrency(service.base_price) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="service.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ service.status_label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!rows.length" class="px-4 py-8 text-center text-sm text-slate-500">
                        Nenhum serviço cadastrado para este contratante.
                    </div>
                </div>

                <PaginationLinks :links="paginationLinks" :min-links="4" />
            </section>
        </section>
    </AuthenticatedLayout>
</template>
