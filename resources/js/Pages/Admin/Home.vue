<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CatalogBanner from '@/Components/App/AdminOverview/CatalogBanner.vue';
import OperationsOverview from '@/Components/App/AdminOverview/OperationsOverview.vue';
import PdvOverview from '@/Components/App/AdminOverview/PdvOverview.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Briefcase, ClipboardList, Clock3, CircleDollarSign } from 'lucide-vue-next';

const props = defineProps({
    overview: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const contractorName = computed(() => currentContractor.value?.brand_name || currentContractor.value?.name || 'Sua empresa');
const currentNiche = computed(() => String(currentContractor.value?.business_niche ?? 'commercial').toLowerCase());

const catalogUrl = computed(() => {
    const slug = String(currentContractor.value?.slug ?? '').trim();
    if (!slug) return '/';

    if (typeof route === 'function') {
        try {
            return route('shop.show', { slug });
        } catch {
            return `/shop/${slug}`;
        }
    }

    return `/shop/${slug}`;
});

const activeTab = ref('operations');
const overviewTabs = [
    { key: 'operations', label: 'Operação' },
    { key: 'pdv', label: 'PDV' },
];

const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const serviceStats = computed(() => {
    const stats = props.overview?.services?.stats ?? {};
    return [
        { key: 'open_orders', label: 'OS em aberto', value: String(stats.open_orders ?? 0), icon: ClipboardList, tone: 'bg-amber-100 text-amber-700' },
        { key: 'today', label: 'Atendimentos hoje', value: String(stats.today ?? 0), icon: Clock3, tone: 'bg-blue-100 text-blue-700' },
        { key: 'catalog', label: 'Serviços ativos', value: String(stats.active_services ?? 0), icon: Briefcase, tone: 'bg-slate-100 text-slate-700' },
        { key: 'revenue', label: 'Receita de serviços', value: asCurrency(stats.revenue ?? 0), icon: CircleDollarSign, tone: 'bg-emerald-100 text-emerald-700' },
    ];
});

const serviceQueue = computed(() => props.overview?.services?.queue ?? []);
</script>

<template>
    <Head title="Visão Geral" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Visão Geral">
        <section class="space-y-4">
            <template v-if="currentNiche === 'commercial'">
                <CatalogBanner :contractor-name="contractorName" :catalog-url="catalogUrl" />

                <div class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                    <div class="grid grid-cols-2 gap-2 sm:inline-flex sm:items-center">
                        <button
                            v-for="tab in overviewTabs"
                            :key="tab.key"
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition"
                            :class="activeTab === tab.key ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <OperationsOverview
                        v-if="activeTab === 'operations'"
                        :stats="props.overview?.commercial?.operations ?? {}"
                    />
                    <PdvOverview
                        v-else
                        :stats="props.overview?.commercial?.pdv ?? {}"
                    />
                </div>
            </template>

            <template v-else>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="stat in serviceStats" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-slate-900">Fila de ordens de serviço</h2>
                        <div class="flex items-center gap-2">
                            <Link :href="route('admin.services.orders')" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Ver OS
                            </Link>
                            <Link :href="route('admin.services.schedule')" class="inline-flex items-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                Ver agenda
                            </Link>
                        </div>
                    </div>

                    <div v-if="serviceQueue.length" class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">OS</th>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Serviço</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="item in serviceQueue" :key="item.code">
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ item.code }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ item.customer }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ item.service }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                            :class="item.status === 'Em execução'
                                                ? 'bg-blue-100 text-blue-700'
                                                : item.status === 'Triagem'
                                                    ? 'bg-amber-100 text-amber-700'
                                                    : 'bg-slate-200 text-slate-700'"
                                        >
                                            {{ item.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Nenhuma ordem de serviço registrada.
                    </div>
                </section>
            </template>
        </section>
    </AuthenticatedLayout>
</template>
