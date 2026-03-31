<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import OrderDetailsModal from '@/Components/App/Orders/OrderDetailsModal.vue';
import CatalogBanner from '@/Components/App/AdminOverview/CatalogBanner.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowUpRight, Clock3, CreditCard, FileText, QrCode, Store, Wallet } from 'lucide-vue-next';

const props = defineProps({
    overview: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();

const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const contractorName = computed(() => currentContractor.value?.brand_name || currentContractor.value?.name || 'Sua empresa');
const contractorNiche = computed(() => String(currentContractor.value?.business_niche ?? 'commercial').trim().toLowerCase());
const contractorBusinessType = computed(() => String(currentContractor.value?.business_type ?? '').trim().toLowerCase());

const enabledModules = computed(() => {
    const raw = currentContractor.value?.enabled_modules;
    if (!Array.isArray(raw) || raw.length === 0) {
        return contractorNiche.value === 'services' ? ['services'] : ['commercial'];
    }

    return raw
        .map((moduleCode) => String(moduleCode ?? '').trim().toLowerCase())
        .filter(Boolean);
});

const hasModule = (moduleCode) => enabledModules.value.includes(String(moduleCode ?? '').trim().toLowerCase());
const hasAnyModule = (moduleCodes) => Array.isArray(moduleCodes) && moduleCodes.some((moduleCode) => hasModule(moduleCode));

const isCommercialOverview = computed(() => contractorNiche.value !== 'services');

const overview = computed(() => props.overview ?? {});
const commercialOverview = computed(() => overview.value.commercial ?? {});
const servicesOverview = computed(() => overview.value.services ?? {});
const quickTotals = computed(() => overview.value.quick_totals ?? {});

const operationsStats = computed(() => commercialOverview.value.operations ?? {});
const pdvStats = computed(() => commercialOverview.value.pdv ?? {});
const serviceStats = computed(() => servicesOverview.value.stats ?? {});
const serviceQueue = computed(() => (Array.isArray(servicesOverview.value.queue) ? servicesOverview.value.queue : []));

const recentOrders = computed(() => (
    Array.isArray(operationsStats.value.recent_orders)
        ? operationsStats.value.recent_orders.slice(0, 8)
        : []
));

const recentDeliveries = computed(() => (
    Array.isArray(operationsStats.value.recent_deliveries)
        ? operationsStats.value.recent_deliveries.slice(0, 6)
        : []
));

const recentSales = computed(() => (
    Array.isArray(pdvStats.value.recent_sales)
        ? pdvStats.value.recent_sales.slice(0, 6)
        : []
));

const canViewPdv = computed(() => hasModule('pdv'));
const canViewReports = computed(() => hasModule('reports'));
const canViewServiceOrders = computed(() => hasModule('service_orders'));
const canViewServiceSchedule = computed(() => hasModule('schedule'));
const canViewServiceCatalog = computed(() => hasModule('services_catalog'));
const canViewServiceStorefront = computed(() => hasModule('services_storefront'));

const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const resolveSaleStatusLabel = (status) => String(status?.label ?? 'Sem status');
const resolveSaleStatusTone = (status) => String(status?.tone ?? 'bg-slate-100 text-slate-700');

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

const reportsHref = computed(() => route('admin.reports.index'));
const storefrontAdminHref = computed(() => route('admin.storefront.index'));
const serviceScheduleHref = computed(() => route('admin.services.schedule', { layout: 'day' }));
const buildServiceOrdersHref = (search = '') => route('admin.services.orders', { search: search || undefined });
const buildScheduleItemHref = (item) => route('admin.services.schedule', {
    layout: 'day',
    search: item?.code || item?.title || item?.customer || item?.service || undefined,
});

const hasPublicCatalog = computed(() => {
    if (!catalogUrl.value || catalogUrl.value === '/') return false;

    return isCommercialOverview.value
        ? hasAnyModule(['catalog', 'checkout'])
        : canViewServiceStorefront.value;
});

const recordDetailsOpen = ref(false);
const recordDetails = ref(null);
const appointmentDetailsOpen = ref(false);
const appointmentDetails = ref(null);

const openRecordDetails = (record) => {
    if (!record) return;
    recordDetails.value = record;
    recordDetailsOpen.value = true;
};

const closeRecordDetails = () => {
    recordDetailsOpen.value = false;
    recordDetails.value = null;
};

const openAppointmentDetails = (record) => {
    if (!record) return;
    appointmentDetails.value = record;
    appointmentDetailsOpen.value = true;
};

const closeAppointmentDetails = () => {
    appointmentDetailsOpen.value = false;
    appointmentDetails.value = null;
};

const appointmentWindowLabel = computed(() => {
    if (!appointmentDetails.value) return '--:--';

    return [appointmentDetails.value.starts_at, appointmentDetails.value.ends_at]
        .filter(Boolean)
        .join(' - ');
});

const paymentSummary = computed(() => {
    const summary = pdvStats.value.payment_summary ?? {};

    return [
        {
            key: 'pix',
            label: 'Pix',
            value: asCurrency(summary.pix ?? 0),
            icon: QrCode,
        },
        {
            key: 'card',
            label: 'Cartão',
            value: asCurrency(summary.credit ?? 0),
            icon: CreditCard,
        },
        {
            key: 'cash',
            label: 'Dinheiro',
            value: asCurrency(summary.cash ?? 0),
            icon: Wallet,
        },
    ];
});

const commercialCopy = computed(() => {
    if (contractorBusinessType.value === 'confectionery') {
        return {
            ordersTitle: 'Encomendas recentes',
            ordersDescription: 'Produção, retirada e entrega reunidas em uma fila comercial única.',
            executiveTitle: 'Radar da produção',
            executiveDescription: 'Volume do dia, ritmo da confeitaria e canais que mantêm a operação girando.',
            inventoryTitle: 'Base da confeitaria',
            inventoryDescription: 'Cardápio, clientes, fornecedores e rascunhos de venda em um único bloco.',
            emptyOrders: 'Nenhuma encomenda recente encontrada para este período.',
        };
    }

    return {
        ordersTitle: 'Pedidos recentes',
        ordersDescription: 'Últimos pedidos da operação, com status, canal e contexto de atendimento.',
        executiveTitle: 'Radar comercial',
        executiveDescription: 'Leitura executiva do dia para acompanhar receita, produção e pendências.',
        inventoryTitle: 'Base do negócio',
        inventoryDescription: 'Cadastros e sustentação da operação comercial sem repetir dados da fila.',
        emptyOrders: 'Nenhum pedido recente encontrado para este período.',
    };
});

const serviceCopy = computed(() => {
    switch (contractorBusinessType.value) {
        case 'accounting':
            return {
                queueTitle: 'Agenda do dia',
                queueDescription: 'Compromissos e rotinas previstos para hoje, ordenados pela execução do escritório.',
                queueEmpty: 'Nenhuma demanda agendada para hoje.',
                radarTitle: 'Radar do escritório',
                radarDescription: 'Leitura consolidada da agenda e das prioridades sem duplicar a fila completa.',
                portfolioTitle: 'Portfólio e canais',
                portfolioDescription: 'Serviços contábeis ativos, ticket médio e canal público quando disponível.',
            };
        case 'barbershop':
            return {
                queueTitle: 'Atendimentos da barbearia',
                queueDescription: 'A fila do dia aparece em ordem para facilitar a ocupação da equipe.',
                queueEmpty: 'Nenhum atendimento agendado para hoje.',
                radarTitle: 'Radar da barbearia',
                radarDescription: 'Resumo rápido do ritmo de atendimento e da agenda ativa.',
                portfolioTitle: 'Menu e canais',
                portfolioDescription: 'Serviços ativos, ticket médio e canal de captação do cliente final.',
            };
        case 'auto_electric':
            return {
                queueTitle: 'Atendimentos da autoelétrica',
                queueDescription: 'Os horários do dia ficam centralizados com foco em execução técnica.',
                queueEmpty: 'Nenhum atendimento agendado para hoje.',
                radarTitle: 'Radar técnico',
                radarDescription: 'Status operacional da agenda, da equipe e das ordens em aberto.',
                portfolioTitle: 'Portfólio e canais',
                portfolioDescription: 'Serviços técnicos, ticket médio e canal público de entrada.',
            };
        case 'mechanic':
            return {
                queueTitle: 'Atendimentos da oficina',
                queueDescription: 'A agenda do dia orienta a equipe por ordem de horário e prioridade.',
                queueEmpty: 'Nenhum atendimento agendado para hoje.',
                radarTitle: 'Radar da oficina',
                radarDescription: 'Panorama da agenda ativa com foco em execução, conclusão e ordens abertas.',
                portfolioTitle: 'Portfólio e canais',
                portfolioDescription: 'Serviços da oficina, ticket técnico e canais de captação do negócio.',
            };
        default:
            return {
                queueTitle: 'Atendimentos do dia',
                queueDescription: 'Compromissos do dia organizados em ordem para simplificar a operação.',
                queueEmpty: 'Nenhum atendimento agendado para hoje.',
                radarTitle: 'Radar da operação',
                radarDescription: 'Resumo da agenda do dia com foco no que exige leitura rápida.',
                portfolioTitle: 'Portfólio e canais',
                portfolioDescription: 'Serviços ativos, ticket médio e presença do canal público.',
            };
    }
});

const commercePendingTotal = computed(() =>
    Number(operationsStats.value.pending_quotes ?? 0) + Number(pdvStats.value.pending_quotes ?? 0),
);

const commercialExecutiveRows = computed(() => [
    {
        key: 'revenue',
        label: 'Faturamento do mês',
        value: asCurrency(operationsStats.value.monthly_revenue ?? 0),
    },
    {
        key: 'production',
        label: contractorBusinessType.value === 'confectionery' ? 'Em produção' : 'Pedidos em andamento',
        value: String(operationsStats.value.in_production ?? 0),
    },
    {
        key: 'deliveries',
        label: 'Entregas previstas hoje',
        value: String(operationsStats.value.deliveries_today ?? 0),
    },
    {
        key: 'quotes',
        label: 'Pendências comerciais',
        value: String(commercePendingTotal.value),
    },
]);

const commercialInventoryRows = computed(() => [
    {
        key: 'products',
        label: contractorBusinessType.value === 'confectionery' ? 'Itens do cardápio' : 'Produtos cadastrados',
        value: String(quickTotals.value.products ?? 0),
    },
    {
        key: 'clients',
        label: 'Clientes cadastrados',
        value: String(operationsStats.value.clients ?? 0),
    },
    {
        key: 'suppliers',
        label: 'Fornecedores',
        value: String(quickTotals.value.suppliers ?? 0),
    },
    {
        key: 'pdv_quotes',
        label: 'Rascunhos no PDV',
        value: String(pdvStats.value.pending_quotes ?? 0),
    },
]);

const serviceQueueStats = computed(() => {
    const items = serviceQueue.value ?? [];

    return {
        uniqueCustomers: new Set(
            items
                .map((item) => String(item.customer ?? '').trim())
                .filter(Boolean),
        ).size,
        pending: items.filter((item) => ['scheduled', 'confirmed'].includes(String(item.status_value ?? ''))).length,
        inService: items.filter((item) => String(item.status_value ?? '') === 'in_service').length,
        completed: items.filter((item) => String(item.status_value ?? '') === 'done').length,
        exceptions: items.filter((item) => ['cancelled', 'no_show'].includes(String(item.status_value ?? ''))).length,
    };
});

const nextServiceQueueItem = computed(() =>
    serviceQueue.value.find((item) => ['in_service', 'confirmed', 'scheduled'].includes(String(item.status_value ?? '')))
    || serviceQueue.value[0]
    || null,
);

const nextServiceWindowLabel = computed(() => {
    if (!nextServiceQueueItem.value) return 'Sem horários previstos';

    return [nextServiceQueueItem.value.starts_at, nextServiceQueueItem.value.ends_at]
        .filter(Boolean)
        .join(' - ');
});

const serviceSummaryRows = computed(() => [
    {
        key: 'window',
        label: contractorBusinessType.value === 'accounting' ? 'Próximo compromisso' : 'Próximo horário',
        value: nextServiceWindowLabel.value,
    },
    {
        key: 'customers',
        label: 'Clientes previstos',
        value: String(serviceQueueStats.value.uniqueCustomers),
    },
    {
        key: 'pending',
        label: 'Aguardando início',
        value: String(serviceQueueStats.value.pending),
    },
    {
        key: 'in_service',
        label: 'Em atendimento',
        value: String(serviceQueueStats.value.inService),
    },
    {
        key: 'completed',
        label: 'Concluídos hoje',
        value: String(serviceQueueStats.value.completed),
    },
    {
        key: 'open_orders',
        label: 'OS em aberto',
        value: String(serviceStats.value.open_orders ?? 0),
    },
]);

const servicePortfolioRows = computed(() => [
    {
        key: 'catalog_total',
        label: contractorBusinessType.value === 'barbershop' ? 'Serviços no menu' : 'Serviços cadastrados',
        value: String(serviceStats.value.catalog ?? 0),
    },
    {
        key: 'catalog_active',
        label: contractorBusinessType.value === 'accounting' ? 'Rotinas ativas' : 'Serviços ativos',
        value: String(serviceStats.value.active_services ?? 0),
    },
    {
        key: 'avg_price',
        label: ['mechanic', 'auto_electric'].includes(contractorBusinessType.value) ? 'Ticket técnico médio' : 'Preço médio',
        value: asCurrency(serviceStats.value.avg_price ?? 0),
    },
    {
        key: 'channel',
        label: 'Canal público',
        value: hasPublicCatalog.value ? 'Ativo' : 'Interno',
    },
]);

const serviceAttentionNote = computed(() => {
    if (serviceQueueStats.value.exceptions > 0) {
        return `${serviceQueueStats.value.exceptions} atendimento(s) com cancelamento ou não comparecimento hoje.`;
    }

    if (serviceQueue.value.length === 0) {
        return 'Sem atendimentos na fila hoje.';
    }

    return 'Sem exceções registradas na agenda de hoje.';
});
</script>

<template>
    <Head title="Visão Geral" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Visão Geral" :show-table-view-toggle="false">
        <template #header>
            <CatalogBanner
                v-if="hasPublicCatalog"
                :contractor-name="contractorName"
                :catalog-url="catalogUrl"
            />
        </template>

        <div class="space-y-8">
            <section
                v-if="isCommercialOverview"
                class="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]"
            >
                <section class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Operação comercial</p>
                            <h2 class="text-lg font-semibold text-slate-900">{{ commercialCopy.ordersTitle }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ commercialCopy.ordersDescription }}</p>
                        </div>
                        <Link
                            :href="route('admin.orders.index')"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Ver pedidos
                            <ArrowUpRight class="h-4 w-4" />
                        </Link>
                    </div>

                    <div v-if="recentOrders.length" class="mt-5 space-y-3">
                        <button
                            v-for="order in recentOrders"
                            :key="`recent-order-${order.id}`"
                            type="button"
                            class="group block w-full rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-left transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:shadow-md"
                            @click="openRecordDetails(order)"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                        {{ order.code }}
                                    </span>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                        :class="order.shipping_mode_tone || 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ order.shipping_mode_label || 'Retirada' }}
                                    </span>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                        :class="resolveSaleStatusTone(order.status)"
                                    >
                                        {{ resolveSaleStatusLabel(order.status) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <p class="text-sm font-semibold text-slate-900">{{ order.amount }}</p>
                                    <ArrowUpRight class="h-4 w-4 text-slate-400 transition group-hover:text-slate-700" />
                                </div>
                            </div>

                            <div class="mt-3 flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ order.customer }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ order.description }}</p>
                                    <p
                                        v-if="order.shipping_mode === 'delivery' && order.shipping_address_text"
                                        class="truncate text-xs text-slate-500"
                                    >
                                        {{ order.shipping_address_text }}
                                    </p>
                                </div>
                                <div class="text-left md:text-right">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Atualização</p>
                                    <p class="text-xs text-slate-500">{{ order.created_at || order.time }}</p>
                                </div>
                            </div>
                        </button>
                    </div>

                    <div
                        v-else
                        class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        {{ commercialCopy.emptyOrders }}
                    </div>
                </section>

                <div class="space-y-4">
                    <section class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Central comercial</p>
                                <h2 class="text-lg font-semibold text-slate-900">{{ commercialCopy.executiveTitle }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ commercialCopy.executiveDescription }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full px-3 py-1 text-[11px] font-semibold"
                                    :class="pdvStats.cash_open ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ pdvStats.cash_open ? 'Caixa aberto' : 'Caixa fechado' }}
                                </span>
                                <span
                                    class="rounded-full px-3 py-1 text-[11px] font-semibold"
                                    :class="hasPublicCatalog ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ hasPublicCatalog ? 'Loja pública ativa' : 'Loja pública inativa' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="item in commercialExecutiveRows"
                                :key="item.key"
                                class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3"
                            >
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ item.label }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ item.value }}</p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <a
                                v-if="hasPublicCatalog"
                                :href="catalogUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                            >
                                <Store class="h-4 w-4" />
                                Abrir loja pública
                            </a>
                            <Link
                                v-if="canViewReports"
                                :href="reportsHref"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                <FileText class="h-4 w-4" />
                                Abrir PDF
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">PDV</p>
                                <h2 class="text-lg font-semibold text-slate-900">Movimento do caixa</h2>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-[11px] font-semibold"
                                :class="pdvStats.cash_open ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ pdvStats.cash_open ? 'Caixa aberto' : 'Caixa fechado' }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 px-4 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Vendas hoje</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ asCurrency(pdvStats.sales_today ?? 0) }}</p>
                                <p class="text-xs text-slate-500">{{ Number(pdvStats.sales_count ?? 0) }} venda(s) concluída(s)</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Ticket médio</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ asCurrency(pdvStats.avg_ticket ?? 0) }}</p>
                                <p class="text-xs text-slate-500">Baseado nas vendas do dia</p>
                            </div>
                        </div>

                        <ul class="mt-4 space-y-3">
                            <li
                                v-for="payment in paymentSummary"
                                :key="payment.key"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3"
                            >
                                <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    <component :is="payment.icon" class="h-4 w-4 text-emerald-600" />
                                    {{ payment.label }}
                                </span>
                                <span class="text-sm font-semibold text-slate-900">{{ payment.value }}</span>
                            </li>
                        </ul>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <Link
                                v-if="canViewPdv"
                                :href="route('admin.pdv.index')"
                                class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800"
                            >
                                Abrir PDV
                            </Link>
                            <Link
                                v-if="canViewPdv"
                                :href="route('admin.pdv.index', { action: pdvStats.cash_open ? 'close-cash' : 'open-cash' })"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                {{ pdvStats.cash_open ? 'Fechar caixa' : 'Abrir caixa' }}
                            </Link>
                        </div>
                    </section>
                </div>
            </section>

            <section
                v-if="isCommercialOverview"
                class="grid gap-4 lg:grid-cols-3"
            >
                <section class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Logística</p>
                            <h2 class="text-lg font-semibold text-slate-900">Entregas recentes</h2>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">
                            Hoje: {{ Number(operationsStats.deliveries_today ?? 0) }}
                        </span>
                    </div>

                    <div v-if="recentDeliveries.length" class="mt-4 space-y-3">
                        <button
                            v-for="delivery in recentDeliveries"
                            :key="`recent-delivery-${delivery.id}`"
                            type="button"
                            class="group block w-full rounded-2xl border border-slate-200 bg-slate-50/70 p-4 text-left transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:shadow-md"
                            @click="openRecordDetails(delivery)"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600">{{ delivery.code }}</span>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="resolveSaleStatusTone(delivery.status)">
                                        {{ resolveSaleStatusLabel(delivery.status) }}
                                    </span>
                                </div>
                                <ArrowUpRight class="h-4 w-4 text-slate-400 transition group-hover:text-slate-700" />
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-900">{{ delivery.customer }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ delivery.shipping_address_text || 'Endereço não informado' }}</p>
                            <p class="mt-2 text-xs font-semibold text-slate-600">{{ delivery.amount }}</p>
                        </button>
                    </div>

                    <div
                        v-else
                        class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        Nenhuma entrega recente registrada.
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">PDV</p>
                            <h2 class="text-lg font-semibold text-slate-900">Vendas recentes</h2>
                        </div>
                        <Link
                            v-if="canViewPdv"
                            :href="route('admin.sales.index')"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Histórico
                        </Link>
                    </div>

                    <div v-if="recentSales.length" class="mt-4 space-y-3">
                        <button
                            v-for="sale in recentSales"
                            :key="`recent-sale-${sale.id}`"
                            type="button"
                            class="group block w-full rounded-2xl border border-slate-200 bg-slate-50/70 p-4 text-left transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:shadow-md"
                            @click="openRecordDetails(sale)"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600">{{ sale.code }}</span>
                                    <ArrowUpRight class="h-4 w-4 text-slate-400 transition group-hover:text-slate-700" />
                                </div>
                                <span class="text-sm font-semibold text-slate-900">{{ sale.amount }}</span>
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-900">{{ sale.customer }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ sale.payment_label || sale.payment }}</p>
                            <p class="mt-2 text-xs text-slate-500">{{ sale.time || sale.created_at }}</p>
                        </button>
                    </div>

                    <div
                        v-else
                        class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        Nenhuma venda registrada hoje.
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Base do negócio</p>
                            <h2 class="text-lg font-semibold text-slate-900">{{ commercialCopy.inventoryTitle }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ commercialCopy.inventoryDescription }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-600">
                            Consolidado
                        </span>
                    </div>

                    <div class="mt-4 grid gap-3">
                        <div
                            v-for="item in commercialInventoryRows"
                            :key="item.key"
                            class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3"
                        >
                            <span class="text-sm text-slate-600">{{ item.label }}</span>
                            <span class="text-sm font-semibold text-slate-900">{{ item.value }}</span>
                        </div>
                    </div>
                </section>
            </section>

            <section
                v-else
                class="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]"
            >
                <section class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Agenda integrada</p>
                            <h2 class="text-lg font-semibold text-slate-900">{{ serviceCopy.queueTitle }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ serviceCopy.queueDescription }}</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">
                            {{ serviceQueue.length }} atendimento(s) hoje
                        </span>
                    </div>

                    <div v-if="serviceQueue.length" class="mt-5 space-y-3">
                        <button
                            v-for="(item, index) in serviceQueue"
                            :key="`service-queue-${item.id}`"
                            type="button"
                            class="group block w-full rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-left transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:shadow-md"
                            @click="openAppointmentDetails(item)"
                        >
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-2xl bg-slate-900 text-white">
                                        <span class="text-sm font-semibold"># {{ index + 1 }}</span>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                            <span class="rounded-full bg-white px-2.5 py-1 font-semibold text-slate-600">
                                                {{ item.code ? `OS ${item.code}` : 'Sem OS' }}
                                            </span>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700">
                                                <Clock3 class="h-3.5 w-3.5" />
                                                {{ item.starts_at ?? '--:--' }}
                                                <template v-if="item.ends_at">
                                                    - {{ item.ends_at }}
                                                </template>
                                            </span>
                                            <span
                                                v-if="item.payment_status"
                                                class="rounded-full px-2.5 py-1 font-semibold"
                                                :class="item.payment_status_tone || 'bg-slate-100 text-slate-700'"
                                            >
                                                {{ item.payment_status }}
                                            </span>
                                        </div>

                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ item.service }}</p>
                                            <p class="text-sm text-slate-600">{{ item.customer }}</p>
                                        </div>

                                        <p v-if="item.title && item.title !== item.service" class="text-xs text-slate-500">
                                            {{ item.title }}
                                        </p>
                                        <p v-if="item.location" class="text-xs text-slate-500">
                                            Local: {{ item.location }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="item.status_tone">
                                        {{ item.status }}
                                    </span>
                                    <ArrowUpRight class="h-4 w-4 text-slate-400 transition group-hover:text-slate-700" />
                                </div>
                            </div>
                        </button>
                    </div>

                    <div
                        v-else
                        class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        {{ serviceCopy.queueEmpty }}
                    </div>
                </section>

                <div class="space-y-4">
                    <section class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Operação do dia</p>
                                <h2 class="text-lg font-semibold text-slate-900">{{ serviceCopy.radarTitle }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ serviceCopy.radarDescription }}</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-600">
                                {{ serviceQueue.length ? 'Fila ativa' : 'Sem fila no momento' }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="item in serviceSummaryRows"
                                :key="item.key"
                                class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3"
                            >
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ item.label }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ item.value }}</p>
                            </div>
                        </div>

                        <div
                            class="mt-4 rounded-2xl px-4 py-3 text-sm"
                            :class="serviceQueueStats.exceptions > 0 ? 'border border-rose-200 bg-rose-50 text-rose-700' : 'border border-emerald-100 bg-emerald-50/70 text-emerald-800'"
                        >
                            {{ serviceAttentionNote }}
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <Link
                                v-if="canViewServiceSchedule"
                                :href="serviceScheduleHref"
                                class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800"
                            >
                                Agenda do dia
                            </Link>
                            <Link
                                v-if="canViewServiceOrders"
                                :href="route('admin.services.orders')"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver OS
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Estrutura comercial</p>
                                <h2 class="text-lg font-semibold text-slate-900">{{ serviceCopy.portfolioTitle }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ serviceCopy.portfolioDescription }}</p>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-[11px] font-semibold"
                                :class="hasPublicCatalog ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ hasPublicCatalog ? 'Canal público ativo' : 'Operação interna' }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3">
                            <div
                                v-for="item in servicePortfolioRows"
                                :key="item.key"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3"
                            >
                                <span class="text-sm text-slate-600">{{ item.label }}</span>
                                <span class="text-sm font-semibold text-slate-900">{{ item.value }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <Link
                                v-if="canViewServiceCatalog"
                                :href="route('admin.services.catalog')"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Catálogo
                            </Link>
                            <Link
                                v-if="canViewServiceStorefront"
                                :href="storefrontAdminHref"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Gerenciar canal público
                            </Link>
                            <a
                                v-if="hasPublicCatalog"
                                :href="catalogUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                            >
                                <Store class="h-4 w-4" />
                                Abrir loja pública
                            </a>
                        </div>
                    </section>
                </div>
            </section>

            <OrderDetailsModal
                :show="recordDetailsOpen"
                :order="recordDetails"
                :show-actions="false"
                @close="closeRecordDetails"
            />

            <Modal :show="appointmentDetailsOpen" max-width="2xl" @close="closeAppointmentDetails">
                <div class="space-y-5 p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Atendimento do dia</p>
                            <h3 class="text-lg font-semibold text-slate-900">
                                {{ appointmentDetails?.service || appointmentDetails?.title || 'Compromisso' }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ appointmentDetails?.customer || 'Cliente não informado' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="appointmentDetails?.status"
                                class="rounded-full px-3 py-1 text-xs font-semibold"
                                :class="appointmentDetails?.status_tone || 'bg-slate-100 text-slate-700'"
                            >
                                {{ appointmentDetails?.status }}
                            </span>
                            <span
                                v-if="appointmentDetails?.payment_status"
                                class="rounded-full px-3 py-1 text-xs font-semibold"
                                :class="appointmentDetails?.payment_status_tone || 'bg-slate-100 text-slate-700'"
                            >
                                {{ appointmentDetails?.payment_status }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Horário</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ appointmentWindowLabel || '--:--' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Contato</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ appointmentDetails?.customer_contact || 'Não informado' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">OS vinculada</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ appointmentDetails?.code ? `OS ${appointmentDetails.code}` : 'Sem OS' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Responsável</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ appointmentDetails?.technician || 'Não definido' }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Local</p>
                            <p class="mt-1 text-sm text-slate-700">{{ appointmentDetails?.location || 'Não informado' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Título interno</p>
                            <p class="mt-1 text-sm text-slate-700">{{ appointmentDetails?.title || appointmentDetails?.service || 'Sem título' }}</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Observações</p>
                        <p class="mt-2 text-sm text-slate-700">
                            {{ appointmentDetails?.notes || 'Sem observações registradas para este atendimento.' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                v-if="appointmentDetails"
                                :href="buildScheduleItemHref(appointmentDetails)"
                                class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800"
                            >
                                Abrir na agenda
                            </Link>
                            <Link
                                v-if="appointmentDetails?.code && canViewServiceOrders"
                                :href="buildServiceOrdersHref(appointmentDetails.code)"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver OS
                            </Link>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="closeAppointmentDetails"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>
