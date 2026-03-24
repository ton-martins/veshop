<script setup>
import Modal from '@/Components/Modal.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useBranding } from '@/branding';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Activity,
    AlertTriangle,
    Boxes,
    ChartNoAxesCombined,
    Clock4,
    CircleDollarSign,
    Download,
    Eye,
    FileCheck,
    Sparkles,
    ShoppingCart,
} from 'lucide-vue-next';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            revenue: 0,
            orders: 0,
            stock_turn: 0,
            margin: 0,
        }),
    },
    metricCards: {
        type: Array,
        default: () => [],
    },
    topItems: {
        type: Object,
        default: () => ({
            title: '',
            description: '',
            kind: '',
            items: [],
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            period: 'month',
            period_label: 'Este mês',
            start_date: '',
            end_date: '',
            timezone: 'UTC',
            period_options: [],
        }),
    },
    reportContext: {
        type: Object,
        default: () => ({
            niche: 'commercial',
            niche_label: 'Comércio',
            business_type: '',
            business_type_label: '',
            plan_name: '',
            timezone: 'UTC',
        }),
    },
    exportsHistory: {
        type: Array,
        default: () => [],
    },
    exportModules: {
        type: Array,
        default: () => [],
    },
    exportDefaults: {
        type: Object,
        default: () => ({
            format: 'pdf',
            include_details: true,
            module_codes: [],
            date_from: '',
            date_to: '',
        }),
    },
});

const page = usePage();
const { glassGradient } = useBranding();
const exportProcessing = ref(false);
const exportModalOpen = ref(false);
const previewModalOpen = ref(false);
const previewDocumentUrl = ref('');
const previewDocumentName = ref('');
const currentContractor = computed(() => page.props?.contractorContext?.current ?? null);
const contractorName = computed(() => (
    currentContractor.value?.brand_name
    || currentContractor.value?.name
    || 'Contratante'
));
const contractorLogo = computed(() => (
    currentContractor.value?.brand_logo_url
    || currentContractor.value?.brand_avatar_url
    || null
));

const getInitials = (value) => {
    const safe = String(value ?? '').trim();
    if (!safe) return 'CT';
    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
    const initials = `${first}${last}`.trim().toUpperCase();
    return initials || 'CT';
};

const contractorInitials = computed(() => getInitials(contractorName.value));

const iconByMetric = {
    commercial_revenue: CircleDollarSign,
    commercial_orders: ShoppingCart,
    commercial_average_ticket: ChartNoAxesCombined,
    commercial_active_products: Boxes,
    services_revenue: CircleDollarSign,
    services_completed_orders: ShoppingCart,
    services_appointments: ChartNoAxesCombined,
    services_active_catalog: Boxes,
};

const filterForm = ref({
    period: String(props.filters?.period ?? 'month'),
    start_date: String(props.filters?.start_date ?? ''),
    end_date: String(props.filters?.end_date ?? ''),
});

const exportForm = ref({
    format: String(props.exportDefaults?.format ?? 'pdf'),
    include_details: Boolean(props.exportDefaults?.include_details ?? true),
    date_from: String(props.exportDefaults?.date_from ?? props.filters?.start_date ?? ''),
    date_to: String(props.exportDefaults?.date_to ?? props.filters?.end_date ?? ''),
    module_codes: Array.isArray(props.exportDefaults?.module_codes)
        ? props.exportDefaults.module_codes.map((code) => String(code))
        : [],
});

watch(
    () => props.filters,
    (filters) => {
        filterForm.value = {
            period: String(filters?.period ?? 'month'),
            start_date: String(filters?.start_date ?? ''),
            end_date: String(filters?.end_date ?? ''),
        };
    },
);

watch(
    () => props.exportDefaults,
    (defaults) => {
        exportForm.value = {
            format: String(defaults?.format ?? 'pdf'),
            include_details: Boolean(defaults?.include_details ?? true),
            date_from: String(defaults?.date_from ?? props.filters?.start_date ?? ''),
            date_to: String(defaults?.date_to ?? props.filters?.end_date ?? ''),
            module_codes: Array.isArray(defaults?.module_codes)
                ? defaults.module_codes.map((code) => String(code))
                : [],
        };
    },
    { deep: true },
);

const periodOptions = computed(() => {
    if (Array.isArray(props.filters?.period_options) && props.filters.period_options.length > 0) {
        return props.filters.period_options;
    }

    return [
        { value: 'today', label: 'Hoje' },
        { value: 'week', label: 'Esta semana' },
        { value: 'month', label: 'Este mês' },
        { value: 'quarter', label: 'Este trimestre' },
        { value: 'year', label: 'Este ano' },
        { value: 'custom', label: 'Período personalizado' },
    ];
});

const exportFormatOptions = [
    { value: 'pdf', label: 'PDF' },
    { value: 'excel', label: 'Excel (.xls)' },
    { value: 'csv', label: 'CSV' },
];

const isCustomPeriod = computed(() => filterForm.value.period === 'custom');
const flashStatus = computed(() => String(page.props?.flash?.status ?? '').trim());
const hasSelectedModules = computed(() => exportForm.value.module_codes.length > 0);
const hasExportModules = computed(() => Array.isArray(props.exportModules) && props.exportModules.length > 0);

const statCards = computed(() => {
    if (Array.isArray(props.metricCards) && props.metricCards.length > 0) {
        return props.metricCards.map((card) => ({
            key: card.key,
            label: card.label,
            description: card.description,
            value: resolveMetricValue(card),
            icon: iconByMetric[card.key] ?? ChartNoAxesCombined,
        }));
    }

    return [
        {
            key: 'revenue',
            label: 'Faturamento no período',
            description: 'Pedidos pagos e concluídos',
            value: formatCurrency(props.stats?.revenue),
            icon: CircleDollarSign,
        },
        {
            key: 'orders',
            label: 'Pedidos processados',
            description: 'Pedidos válidos no período',
            value: String(props.stats?.orders ?? 0),
            icon: ShoppingCart,
        },
    ];
});

const contextSummary = computed(() => {
    const niche = String(props.reportContext?.niche_label ?? '').trim();
    const business = String(props.reportContext?.business_type_label ?? '').trim();
    const plan = String(props.reportContext?.plan_name ?? '').trim();
    const blocks = [niche, business, plan].filter(Boolean);

    return blocks.length ? blocks.join(' - ') : 'Visão operacional por módulos';
});

const openExportModal = () => {
    exportModalOpen.value = true;
};

const closeExportModal = () => {
    exportModalOpen.value = false;
};

const openPdfPreview = (item) => {
    const previewUrl = String(item?.preview_url ?? '').trim();
    if (!previewUrl) return;

    previewDocumentUrl.value = previewUrl;
    previewDocumentName.value = String(item?.file ?? 'documento.pdf');
    previewModalOpen.value = true;
};

const closePdfPreview = () => {
    previewModalOpen.value = false;
    previewDocumentUrl.value = '';
    previewDocumentName.value = '';
};

const moduleIsSelected = (code) => exportForm.value.module_codes.includes(String(code));

const toggleModule = (code) => {
    const normalized = String(code);
    if (!normalized) return;

    if (moduleIsSelected(normalized)) {
        exportForm.value.module_codes = exportForm.value.module_codes.filter((item) => item !== normalized);
        return;
    }

    exportForm.value.module_codes = [...exportForm.value.module_codes, normalized];
};

const selectAllModules = () => {
    exportForm.value.module_codes = (props.exportModules ?? []).map((item) => String(item.code));
};

const clearModules = () => {
    exportForm.value.module_codes = [];
};

const applyFilters = () => {
    const payload = {
        period: String(filterForm.value.period || 'month'),
    };

    if (payload.period === 'custom') {
        payload.start_date = String(filterForm.value.start_date || '').trim();
        payload.end_date = String(filterForm.value.end_date || '').trim();
    }

    router.get(route('admin.reports.index'), payload, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const submitExport = () => {
    if (exportProcessing.value || !hasSelectedModules.value) return;

    const payload = {
        format: String(exportForm.value.format || 'pdf'),
        module_codes: exportForm.value.module_codes,
        include_details: Boolean(exportForm.value.include_details),
        date_from: String(exportForm.value.date_from || '').trim() || null,
        date_to: String(exportForm.value.date_to || '').trim() || null,
    };

    router.post(route('admin.reports.exports'), payload, {
        preserveScroll: true,
        onStart: () => {
            exportProcessing.value = true;
        },
        onSuccess: () => {
            closeExportModal();
        },
        onFinish: () => {
            exportProcessing.value = false;
        },
    });
};

const formatCurrency = (value) => {
    const parsed = Number(value ?? 0);

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number.isFinite(parsed) ? parsed : 0);
};

const resolveMetricValue = (card) => {
    const format = String(card?.format ?? 'integer');
    const rawValue = Number(card?.value ?? 0);

    if (format === 'currency') {
        return formatCurrency(rawValue);
    }

    if (format === 'percent') {
        return `${rawValue.toFixed(1)}%`;
    }

    return new Intl.NumberFormat('pt-BR').format(Number.isFinite(rawValue) ? rawValue : 0);
};
</script>

<template>
    <Head title="Relatórios" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Relatórios">
        <section class="space-y-4">
            <div
                v-if="flashStatus"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            >
                {{ flashStatus }}
            </div>

            <section class="relative overflow-hidden rounded-3xl border border-white/70 px-5 py-6 shadow-sm md:px-7 md:py-7">
                <div class="pointer-events-none absolute inset-0" :style="{ background: glassGradient }" />

                <div class="relative grid gap-5 xl:grid-cols-[1.5fr_1fr]">
                    <div class="space-y-4">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/95 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200/70">
                            <Sparkles class="h-3.5 w-3.5" />
                            Relatórios operacionais
                        </span>

                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/95 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200/80"
                            >
                                <img
                                    v-if="contractorLogo"
                                    :src="contractorLogo"
                                    :alt="contractorName"
                                    class="h-9 w-9 rounded-xl object-contain"
                                >
                                <span v-else>{{ contractorInitials }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ contractorName }}</p>
                                <p class="truncate text-xs text-slate-600">{{ contextSummary }}</p>
                            </div>
                        </div>

                        <h2 class="text-xl font-semibold tracking-tight text-slate-900 md:text-2xl">
                            Visão consolidada dos módulos habilitados
                        </h2>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200/80">
                                <Activity class="h-3.5 w-3.5 text-emerald-600" />
                                Nicho: {{ reportContext.niche_label }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200/80">
                                <AlertTriangle class="h-3.5 w-3.5 text-amber-500" />
                                Plano: {{ reportContext.plan_name }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200/80">
                                <Clock4 class="h-3.5 w-3.5 text-sky-600" />
                                Período: {{ filters.period_label }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-emerald-100/70 bg-gradient-to-br from-white via-white to-emerald-50/60 p-4 shadow-lg backdrop-blur">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-slate-900 text-white">
                                <FileCheck class="h-4 w-4" />
                            </span>
                            <div>
                                <p class="text-xs font-semibold text-slate-900">Ações de relatório</p>
                                <p class="text-[11px] text-slate-500">Filtre e exporte com precisão</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <UiSelect
                                v-model="filterForm.period"
                                :options="periodOptions"
                                placeholder="Período"
                                button-class="w-full text-sm"
                                @change="applyFilters"
                            />

                            <div v-if="isCustomPeriod" class="grid gap-2 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600">
                                    Início
                                    <input
                                        v-model="filterForm.start_date"
                                        type="date"
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700"
                                        @change="applyFilters"
                                    >
                                </label>
                                <label class="text-xs font-medium text-slate-600">
                                    Fim
                                    <input
                                        v-model="filterForm.end_date"
                                        type="date"
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700"
                                        @change="applyFilters"
                                    >
                                </label>
                            </div>

                            <button
                                type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800"
                                @click="openExportModal"
                            >
                                <Download class="h-3.5 w-3.5" />
                                Exportar dados
                            </button>

                            <p class="text-[11px] text-slate-500">
                                {{ exportsHistory.length }} exportação(ões) registrada(s).
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="stat in statCards"
                    :key="stat.key"
                    class="group rounded-2xl border border-emerald-100 bg-gradient-to-br from-white via-white to-emerald-50/60 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">{{ stat.label }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ stat.value }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ stat.description }}</p>
                        </div>
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500">
                            <component :is="stat.icon" class="h-4 w-4" />
                        </span>
                    </div>
                </article>
            </div>

            <div class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
                <div class="space-y-4">
                    <section class="rounded-3xl border border-emerald-100 bg-gradient-to-br from-white via-white to-slate-50/80 p-4 shadow-sm backdrop-blur md:p-5">
                        <header class="mb-2">
                            <h3 class="text-sm font-semibold text-slate-900">{{ topItems.title || 'Itens em destaque' }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ topItems.description }}</p>
                        </header>

                        <ul v-if="Array.isArray(topItems.items) && topItems.items.length" class="space-y-2">
                            <li
                                v-for="(item, index) in topItems.items"
                                :key="item.id"
                                class="rounded-xl border border-slate-200 bg-white/90 px-3 py-2"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-600">
                                            {{ index + 1 }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-800">{{ item.label }}</p>
                                            <p class="text-xs text-slate-500">{{ item.volume }} ocorrência(s)</p>
                                        </div>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-900">{{ formatCurrency(item.total) }}</p>
                                </div>
                            </li>
                        </ul>
                        <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                            Nenhum item elegível para o período.
                        </div>
                    </section>

                    <section class="rounded-3xl border border-emerald-100 bg-gradient-to-br from-white via-white to-emerald-50/50 p-4 shadow-sm backdrop-blur md:p-5">
                        <header class="mb-3 flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-slate-900">Módulos disponíveis para exportação</h3>
                            <span class="text-xs font-medium text-slate-500">{{ exportModules.length }} módulo(s)</span>
                        </header>

                        <div v-if="hasExportModules" class="grid gap-2 md:grid-cols-2">
                            <article
                                v-for="module in exportModules"
                                :key="module.code"
                                class="rounded-xl border border-slate-200 bg-white/85 px-3 py-2 shadow-sm"
                            >
                                <p class="text-sm font-semibold text-slate-800">{{ module.label }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ module.description }}</p>
                            </article>
                        </div>
                        <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                            Nenhum módulo elegível para exportação neste contratante.
                        </div>
                    </section>
                </div>

                <aside class="space-y-4">
                    <section class="rounded-3xl border border-emerald-100 bg-gradient-to-br from-white via-white to-slate-50/80 p-4 shadow-sm backdrop-blur md:p-5">
                        <h3 class="text-sm font-semibold text-slate-900">Exportações recentes</h3>
                        <ul v-if="props.exportsHistory.length" class="mt-4 space-y-2">
                            <li v-for="item in props.exportsHistory" :key="item.id" class="rounded-lg border border-slate-200 bg-white/90 px-3 py-2 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800">{{ item.file }}</p>
                                        <p class="text-xs text-slate-500">{{ item.by }} - {{ item.when }}</p>
                                        <p v-if="item.rows !== null" class="text-xs text-slate-500">{{ item.rows }} linhas</p>
                                        <p v-if="item.error" class="mt-1 text-xs text-rose-600">{{ item.error }}</p>
                                    </div>
                                    <div class="flex shrink-0 flex-col items-end gap-2">
                                        <span class="rounded-full border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold uppercase text-slate-600">
                                            {{ item.format }}
                                        </span>
                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="item.status_tone">
                                            {{ item.status }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <button
                                                v-if="item.is_pdf && item.preview_url"
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                                                title="Visualizar PDF"
                                                aria-label="Visualizar PDF"
                                                @click="openPdfPreview(item)"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </button>
                                            <a
                                                v-if="item.download_url"
                                                :href="item.download_url"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                                                title="Baixar arquivo"
                                                aria-label="Baixar arquivo"
                                            >
                                                <Download class="h-4 w-4" />
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div v-else class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                            Nenhuma exportação realizada.
                        </div>
                    </section>
                </aside>
            </div>
        </section>

        <Modal :show="exportModalOpen" max-width="4xl" @close="closeExportModal">
            <div class="space-y-4 p-5">
                <header>
                    <h3 class="text-base font-semibold text-slate-900">Exportar dados</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Escolha os módulos, formato e período para montar a exportação.
                    </p>
                </header>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="text-xs font-medium text-slate-600">
                        Formato
                        <UiSelect
                            v-model="exportForm.format"
                            :options="exportFormatOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                    </label>
                    <label class="text-xs font-medium text-slate-600">
                        Nível de detalhe
                        <span class="mt-1 flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                            <input
                                v-model="exportForm.include_details"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-emerald-500 focus:ring-emerald-500"
                            >
                            Incluir dados detalhados
                        </span>
                    </label>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="text-xs font-medium text-slate-600">
                        Data inicial
                        <input
                            v-model="exportForm.date_from"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                    </label>
                    <label class="text-xs font-medium text-slate-600">
                        Data final
                        <input
                            v-model="exportForm.date_to"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                    </label>
                </div>

                <section class="rounded-2xl border border-slate-200 p-3">
                    <header class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-900">Módulos para exportar</p>
                        <div class="flex items-center gap-2 text-xs">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 font-semibold text-slate-700 hover:bg-slate-50"
                                @click="selectAllModules"
                            >
                                Selecionar todos
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 font-semibold text-slate-700 hover:bg-slate-50"
                                @click="clearModules"
                            >
                                Limpar
                            </button>
                        </div>
                    </header>

                    <div v-if="hasExportModules" class="grid gap-2 md:grid-cols-2">
                        <button
                            v-for="module in exportModules"
                            :key="module.code"
                            type="button"
                            class="rounded-xl border px-3 py-2 text-left transition"
                            :class="moduleIsSelected(module.code)
                                ? 'border-emerald-300 bg-emerald-50'
                                : 'border-slate-200 bg-white hover:bg-slate-50'"
                            @click="toggleModule(module.code)"
                        >
                            <span class="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    :checked="moduleIsSelected(module.code)"
                                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-500 focus:ring-emerald-500"
                                    @change.prevent
                                >
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-800">{{ module.label }}</span>
                                    <span class="block text-xs text-slate-500">{{ module.description }}</span>
                                </span>
                            </span>
                        </button>
                    </div>
                    <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-5 text-center text-sm text-slate-500">
                        Nenhum módulo disponível para exportação.
                    </div>

                    <p v-if="!hasSelectedModules" class="mt-2 text-xs font-medium text-rose-600">
                        Selecione ao menos um módulo para continuar.
                    </p>
                </section>

                <footer class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeExportModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="exportProcessing || !hasSelectedModules"
                        @click="submitExport"
                    >
                        <Download class="h-3.5 w-3.5" />
                        {{ exportProcessing ? 'Enfileirando...' : 'Exportar agora' }}
                    </button>
                </footer>
            </div>
        </Modal>

        <Modal :show="previewModalOpen" max-width="6xl" @close="closePdfPreview">
            <div class="space-y-3 p-4">
                <header class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-slate-900">Pré-visualização do PDF</h3>
                        <p class="truncate text-xs text-slate-500">{{ previewDocumentName }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closePdfPreview"
                    >
                        Fechar
                    </button>
                </header>

                <div class="h-[72vh] overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                    <iframe
                        v-if="previewDocumentUrl"
                        :src="previewDocumentUrl"
                        class="h-full w-full"
                        title="Pré-visualização da exportação em PDF"
                    />
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

