<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ClipboardList, Clock3, CircleCheckBig, Search, Filter, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const stats = [
    { key: 'open', label: 'OS em aberto', value: '0', icon: ClipboardList, tone: 'bg-amber-100 text-amber-700' },
    { key: 'running', label: 'Em execução', value: '0', icon: Clock3, tone: 'bg-blue-100 text-blue-700' },
    { key: 'done', label: 'Concluídas no mês', value: '0', icon: CircleCheckBig, tone: 'bg-emerald-100 text-emerald-700' },
];

const serviceOrders = [];
const serviceOrderSearch = ref('');

const filteredServiceOrders = computed(() => {
    const query = String(serviceOrderSearch.value ?? '').trim().toLowerCase();
    if (!query) return serviceOrders;

    return serviceOrders.filter((order) => {
        const code = String(order?.code ?? '').toLowerCase();
        const customer = String(order?.customer ?? '').toLowerCase();
        const service = String(order?.service ?? '').toLowerCase();
        return code.includes(query) || customer.includes(query) || service.includes(query);
    });
});

const clearSearch = () => {
    serviceOrderSearch.value = '';
};
</script>

<template>
    <Head title="Ordens de Serviço" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Ordens de Serviço">
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
                    <div class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input v-model="serviceOrderSearch" type="text" placeholder="Buscar por OS ou cliente" class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none" />
                        <button
                            v-if="serviceOrderSearch"
                            type="button"
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                            aria-label="Limpar pesquisa"
                            @click="clearSearch"
                        >
                            x
                        </button>
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
                                <th class="px-4 py-3">Serviço</th>
                                <th class="px-4 py-3">Técnico</th>
                                <th class="px-4 py-3">Prazo</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody v-if="filteredServiceOrders.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="order in filteredServiceOrders" :key="order.code">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ order.code }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ order.customer }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ order.service }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ order.technician }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ order.dueAt }}</td>
                                <td class="px-4 py-3">{{ order.status }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!filteredServiceOrders.length" class="px-4 py-8 text-center text-sm text-slate-500">
                        Nenhuma ordem de serviço cadastrada.
                    </div>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
