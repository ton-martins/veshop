<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import OrderDetailsModal from '@/Components/App/Orders/OrderDetailsModal.vue';
import { useBranding } from '@/branding';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ShoppingBag, Search, ListFilter, Wallet, CheckCircle2, Ban } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    sales: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },
    saleStats: { type: Object, default: () => ({}) },
    pipeline: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: '', pipeline: 'all' }) },
});

const saleSearch = ref(String(props.filters?.search ?? ''));
const selectedStatus = ref(String(props.filters?.status ?? ''));
const selectedPipelineKey = ref(String(props.filters?.pipeline ?? 'all'));
const saleDetailsModalOpen = ref(false);
const saleDetails = ref(null);

const page = usePage();
const { normalizeHex, withAlpha, secondaryColor } = useBranding();
const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const tabAccentColor = computed(() =>
    normalizeHex(currentContractor.value?.brand_primary_color || '', secondaryColor.value),
);
const salesUiStyles = computed(() => ({
    '--sales-pipeline-active': tabAccentColor.value,
    '--sales-pipeline-active-border': withAlpha(tabAccentColor.value, 0.28),
}));

const pipelineIconMap = {
    draft: ShoppingBag,
    open: Wallet,
    completed: CheckCircle2,
    cancelled: Ban,
};

const salesData = computed(() => (
    Array.isArray(props.sales?.data)
        ? props.sales.data
        : []
));

const paginationLinks = computed(() => (
    Array.isArray(props.sales?.links)
        ? props.sales.links
        : []
));

const pipelineTabs = computed(() => {
    const baseTabs = (props.pipeline ?? []).map((item) => ({
        key: String(item?.key ?? ''),
        label: String(item?.label ?? ''),
        qty: Number(item?.qty ?? 0),
        icon: pipelineIconMap[String(item?.key ?? '')] ?? ListFilter,
    }));

    return [
        {
            key: 'all',
            label: 'Todas',
            qty: Number(props.saleStats?.all ?? 0),
            icon: ListFilter,
        },
        ...baseTabs,
    ];
});

const asCurrency = (value) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const filteredSales = computed(() => salesData.value);
let filterDebounceTimer = null;

const normalizePipeline = (value) => {
    const safe = String(value ?? '').trim();
    return ['draft', 'open', 'completed', 'cancelled'].includes(safe)
        ? safe
        : 'all';
};

const submitFilters = () => {
    router.get(
        route('admin.sales.index'),
        {
            search: String(saleSearch.value ?? '').trim() || undefined,
            status: String(selectedStatus.value ?? '').trim() || undefined,
            pipeline: normalizePipeline(selectedPipelineKey.value) !== 'all'
                ? normalizePipeline(selectedPipelineKey.value)
                : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['sales', 'saleStats', 'pipeline', 'filters'],
        },
    );
};

const scheduleSubmitFilters = () => {
    if (filterDebounceTimer) {
        clearTimeout(filterDebounceTimer);
    }

    filterDebounceTimer = setTimeout(() => {
        submitFilters();
    }, 280);
};

watch([saleSearch, selectedStatus, selectedPipelineKey], () => {
    scheduleSubmitFilters();
});

watch(
    () => props.filters,
    (filters) => {
        const nextSearch = String(filters?.search ?? '');
        const nextStatus = String(filters?.status ?? '');
        const nextPipeline = normalizePipeline(filters?.pipeline ?? 'all');

        if (saleSearch.value !== nextSearch) {
            saleSearch.value = nextSearch;
        }
        if (selectedStatus.value !== nextStatus) {
            selectedStatus.value = nextStatus;
        }
        if (selectedPipelineKey.value !== nextPipeline) {
            selectedPipelineKey.value = nextPipeline;
        }
    },
);

onBeforeUnmount(() => {
    if (filterDebounceTimer) {
        clearTimeout(filterDebounceTimer);
        filterDebounceTimer = null;
    }
});

const clearSearch = () => {
    saleSearch.value = '';
};

const openSaleDetails = (sale) => {
    if (!sale?.id) return;
    saleDetails.value = sale;
    saleDetailsModalOpen.value = true;
};

const closeSaleDetails = () => {
    saleDetailsModalOpen.value = false;
    saleDetails.value = null;
};
</script>

<template>
    <Head title="Vendas" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Vendas" :show-table-view-toggle="false">
        <section class="space-y-4" :style="salesUiStyles">
            <div class="sales-pipeline-shell">
                <div class="sales-pipeline-track">
                    <button
                        v-for="tab in pipelineTabs"
                        :key="`sales-pipeline-tab-${tab.key}`"
                        type="button"
                        class="sales-pipeline-tab"
                        :class="selectedPipelineKey === tab.key ? 'is-active' : ''"
                        @click="selectedPipelineKey = tab.key"
                    >
                        <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                        <span class="truncate">{{ tab.label }}</span>
                        <span class="sales-pipeline-badge">
                            {{ tab.qty }}
                        </span>
                    </button>
                </div>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input
                            v-model="saleSearch"
                            type="text"
                            placeholder="Buscar venda por código, cliente ou contato"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                        >
                        <button
                            v-if="saleSearch"
                            type="button"
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                            aria-label="Limpar pesquisa"
                            @click="clearSearch"
                        >
                            x
                        </button>
                    </div>

                    <div class="flex w-full items-center gap-2 lg:w-auto">
                        <UiSelect
                            v-model="selectedStatus"
                            :options="statusOptions"
                            button-class="w-full lg:w-56"
                        />
                    </div>
                </div>

                <div class="mt-3 flex justify-end">
                    <TableViewToggle />
                </div>

                <div class="mt-4 space-y-3">
                    <div class="hidden overflow-hidden rounded-xl border border-slate-200 md:block">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Venda</th>
                                    <th class="px-4 py-3">Canal</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody v-if="filteredSales.length" class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="sale in filteredSales"
                                    :key="sale.id"
                                    class="cursor-pointer transition hover:bg-slate-50/70"
                                    @click="openSaleDetails(sale)"
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ sale.code }}</p>
                                        <p class="text-xs text-slate-500">{{ sale.customer }}</p>
                                        <p class="text-[11px] text-slate-400">{{ sale.customer_contact || 'Sem contato' }}</p>
                                        <p class="text-[11px] text-slate-400">{{ sale.created_at }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ sale.channel }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-800">{{ asCurrency(sale.total_amount) }}</p>
                                        <p class="text-[11px] text-slate-500">{{ sale.total_items }} item(ns)</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="sale.status.tone">{{ sale.status.label }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="space-y-3 md:hidden">
                        <article
                            v-for="sale in filteredSales"
                            :key="`mobile-sale-${sale.id}`"
                            class="cursor-pointer rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:bg-slate-50/70"
                            @click="openSaleDetails(sale)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ sale.code }}</p>
                                    <p class="text-xs text-slate-500">{{ sale.customer }}</p>
                                    <p class="text-[11px] text-slate-400">{{ sale.customer_contact || 'Sem contato' }}</p>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="sale.status.tone">{{ sale.status.label }}</span>
                            </div>

                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-600">
                                <p>Canal: <span class="font-semibold">{{ sale.channel }}</span></p>
                                <p>Itens: <span class="font-semibold">{{ sale.total_items }}</span></p>
                                <p>Total: <span class="font-semibold">{{ asCurrency(sale.total_amount) }}</span></p>
                                <p>Quando: <span class="font-semibold">{{ sale.created_at }}</span></p>
                            </div>
                        </article>
                    </div>

                    <div v-if="!filteredSales.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Nenhuma venda registrada para este contratante.
                    </div>

                    <div v-if="paginationLinks.length" class="pt-2">
                        <PaginationLinks :links="paginationLinks" :min-links="4" />
                    </div>
                </div>
            </section>
        </section>

        <OrderDetailsModal
            :show="saleDetailsModalOpen"
            :order="saleDetails"
            :show-actions="false"
            @close="closeSaleDetails"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.sales-pipeline-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.sales-pipeline-shell::-webkit-scrollbar {
    height: 6px;
}

.sales-pipeline-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.sales-pipeline-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.sales-pipeline-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid transparent;
    border-radius: 0.72rem;
    min-height: 38px;
    padding: 0.52rem 0.8rem;
    color: #334155;
    font-size: 0.79rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.sales-pipeline-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.sales-pipeline-tab.is-active {
    border-color: var(--sales-pipeline-active-border);
    background: var(--sales-pipeline-active);
    color: #ffffff;
}

.sales-pipeline-badge {
    display: inline-flex;
    min-width: 20px;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: #f8fafc;
    padding: 0 0.38rem;
    font-size: 0.66rem;
    font-weight: 700;
    line-height: 1.3;
    color: #475569;
}

.sales-pipeline-tab.is-active .sales-pipeline-badge {
    border-color: rgba(255, 255, 255, 0.36);
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}
</style>

