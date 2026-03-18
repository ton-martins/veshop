<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import CatalogBanner from '@/Components/App/AdminOverview/CatalogBanner.vue';
import OperationsOverview from '@/Components/App/AdminOverview/OperationsOverview.vue';
import PdvOverview from '@/Components/App/AdminOverview/PdvOverview.vue';
import { useBranding } from '@/branding';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Briefcase, ClipboardList, Clock3, CircleDollarSign, ShoppingBag, Wallet } from 'lucide-vue-next';

const props = defineProps({
    overview: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const { normalizeHex, secondaryColor, withAlpha } = useBranding();
const tabAccentColor = computed(() =>
    normalizeHex(currentContractor.value?.brand_primary_color || '', secondaryColor.value),
);
const overviewUiStyles = computed(() => ({
    '--overview-tab-active': tabAccentColor.value,
    '--overview-tab-active-border': withAlpha(tabAccentColor.value, 0.28),
}));
const contractorName = computed(() => currentContractor.value?.brand_name || currentContractor.value?.name || 'Sua empresa');
const contractorNiche = computed(() => String(currentContractor.value?.business_niche ?? 'commercial').toLowerCase());
const contractorBusinessType = computed(() => String(currentContractor.value?.business_type ?? '').trim().toLowerCase());

const enabledModules = computed(() => {
    const raw = currentContractor.value?.enabled_modules;
    if (!Array.isArray(raw) || raw.length === 0) {
        return contractorNiche.value === 'services' ? ['services'] : ['commercial'];
    }

    return raw
        .map((module) => String(module ?? '').trim().toLowerCase())
        .filter(Boolean);
});

const hasModule = (moduleCode) => enabledModules.value.includes(String(moduleCode ?? '').trim().toLowerCase());
const hasAnyModule = (moduleCodes) => Array.isArray(moduleCodes) && moduleCodes.some((moduleCode) => hasModule(moduleCode));

const dashboardProfileByType = {
    store: 'commercial',
    confectionery: 'commercial',
    barbershop: 'services',
    auto_electric: 'services',
    mechanic: 'services',
    accounting: 'services',
    general_services: 'services',
};

const dashboardProfile = computed(() => {
    const byType = dashboardProfileByType[contractorBusinessType.value];
    if (byType) return byType;

    return contractorNiche.value === 'services' ? 'services' : 'commercial';
});

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

const showCatalogBanner = computed(() => hasAnyModule(['catalog', 'checkout']));

const commercialTabs = computed(() => {
    const tabs = [];

    if (hasAnyModule(['catalog', 'inventory', 'orders', 'crm'])) {
        tabs.push({ key: 'operations', label: 'Loja virtual', icon: ShoppingBag });
    }

    if (hasModule('pdv')) {
        tabs.push({ key: 'pdv', label: 'PDV', icon: Wallet });
    }

    return tabs;
});

const activeTab = ref('operations');

watch(
    commercialTabs,
    (tabs) => {
        if (!tabs.length) {
            activeTab.value = 'operations';
            return;
        }

        const hasCurrent = tabs.some((tab) => tab.key === activeTab.value);
        if (!hasCurrent) {
            activeTab.value = tabs[0].key;
        }
    },
    { immediate: true },
);

const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const canViewServiceOrders = computed(() => hasModule('service_orders'));
const canViewServiceSchedule = computed(() => hasModule('schedule'));
const canViewServiceCatalog = computed(() => hasModule('services_catalog'));

const serviceStats = computed(() => {
    const stats = props.overview?.services?.stats ?? {};
    const cards = [];

    if (canViewServiceOrders.value) {
        cards.push({
            key: 'open_orders',
            label: 'OS em aberto',
            value: String(stats.open_orders ?? 0),
            icon: ClipboardList,
            tone: 'bg-amber-100 text-amber-700',
        });
    }

    if (canViewServiceSchedule.value) {
        cards.push({
            key: 'today',
            label: 'Atendimentos hoje',
            value: String(stats.today ?? 0),
            icon: Clock3,
            tone: 'bg-blue-100 text-blue-700',
        });
    }

    if (canViewServiceCatalog.value) {
        cards.push({
            key: 'catalog',
            label: 'Serviços ativos',
            value: String(stats.active_services ?? 0),
            icon: Briefcase,
            tone: 'bg-slate-100 text-slate-700',
        });
    }

    if (hasAnyModule(['service_orders', 'finance'])) {
        cards.push({
            key: 'revenue',
            label: 'Receita de serviços',
            value: asCurrency(stats.revenue ?? 0),
            icon: CircleDollarSign,
            tone: 'bg-emerald-100 text-emerald-700',
        });
    }

    return cards;
});

const serviceQueue = computed(() => props.overview?.services?.queue ?? []);
</script>

<template>
    <Head title="Visão Geral" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Visão Geral" :show-table-view-toggle="false">
        <section class="space-y-4" :style="overviewUiStyles">
            <template v-if="dashboardProfile === 'commercial'">
                <CatalogBanner
                    v-if="showCatalogBanner"
                    :contractor-name="contractorName"
                    :catalog-url="catalogUrl"
                />

                <div v-if="commercialTabs.length" class="space-y-4">
                    <div v-if="commercialTabs.length > 1" class="overview-tabs-shell">
                        <div class="overview-tabs-track">
                            <button
                                v-for="tab in commercialTabs"
                                :key="tab.key"
                                type="button"
                                class="overview-tab"
                                :class="activeTab === tab.key ? 'is-active' : ''"
                                @click="activeTab = tab.key"
                            >
                                <component :is="tab.icon" class="h-4 w-4" />
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
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                >
                    Nenhum módulo comercial habilitado para este contratante.
                </div>
            </template>

            <template v-else>
                <div v-if="serviceStats.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="stat in serviceStats" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                >
                    Nenhum módulo de serviços habilitado para este contratante.
                </div>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-slate-900">Fila de ordens de serviço</h2>
                        <div class="flex items-center gap-2">
                            <Link
                                v-if="canViewServiceOrders"
                                :href="route('admin.services.orders')"
                                class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Ver OS
                            </Link>
                            <Link
                                v-if="canViewServiceSchedule"
                                :href="route('admin.services.schedule')"
                                class="inline-flex items-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            >
                                Ver agenda
                            </Link>
                        </div>
                    </div>

                    <div v-if="canViewServiceOrders" class="mt-3 flex justify-end">
                        <TableViewToggle />
                    </div>

                    <div
                        v-if="!canViewServiceOrders"
                        class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        O módulo de ordens de serviço não está habilitado para este contratante.
                    </div>

                    <div v-else-if="serviceQueue.length" class="mt-4 overflow-hidden rounded-xl border border-slate-200">
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

                    <div
                        v-else
                        class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        Nenhuma ordem de serviço registrada.
                    </div>
                </section>
            </template>
        </section>
    </AuthenticatedLayout>
</template>

<style scoped>
.overview-tabs-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.overview-tabs-shell::-webkit-scrollbar {
    height: 6px;
}

.overview-tabs-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.overview-tabs-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.overview-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: 1px solid transparent;
    border-radius: 0.72rem;
    min-height: 38px;
    padding: 0.6rem 0.95rem;
    color: #334155;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.overview-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.overview-tab.is-active {
    border-color: var(--overview-tab-active-border);
    background: var(--overview-tab-active);
    color: #ffffff;
}
</style>

