
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import {
    Activity,
    AlertTriangle,
    ArrowUpRight,
    BarChart3,
    CheckCircle,
    Clock4,
    Download,
    DownloadCloud,
    FileCheck,
    Filter,
    ListChecks,
    Plus,
    ShieldCheck,
    Sparkles,
    Users2,
    XCircleIcon,
} from 'lucide-vue-next';
import { useBranding } from '@/branding';

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({}),
    },
    pendingSummary: {
        type: Object,
        default: () => ({}),
    },
    slaSummary: {
        type: Object,
        default: () => ({}),
    },
    approvalSummary: {
        type: Object,
        default: () => ({}),
    },
    statusSeries: {
        type: Array,
        default: () => [],
    },
    recentProcessings: {
        type: Array,
        default: () => [],
    },
    processTypeRanking: {
        type: Array,
        default: () => [],
    },
    slaQueue: {
        type: Array,
        default: () => [],
    },
    activity: {
        type: Array,
        default: () => [],
    },
    pendingByStep: {
        type: Array,
        default: () => [],
    },
    processTypes: {
        type: Array,
        default: () => [],
    },
    planUsage: {
        type: Object,
        default: () => ({}),
    },
    planUsageDaily: {
        type: Array,
        default: () => [],
    },
    planUsageByUser: {
        type: Array,
        default: () => [],
    },
    storageUsage: {
        type: Object,
        default: () => ({}),
    },
    operationTeams: {
        type: Array,
        default: () => [],
    },
    responsibleUsers: {
        type: Array,
        default: () => [],
    },
    userTeamIds: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const { glassGradient, contractorActiveGradient } = useBranding();
const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const isMaster = computed(() => user.value?.role === 'master');
const isAdmin = computed(() => user.value?.role === 'admin');
const isAdminOrMaster = computed(() => isMaster.value || isAdmin.value);
const currentUserId = computed(() => user.value?.id ?? null);
const userFirstName = computed(() => String(user.value?.name ?? '').trim().split(' ')[0] || 'analista');
const contractor = computed(() => page.props.contractor?.currentContractor ?? null);
const contractorName = computed(() => contractor.value?.brand_name ?? contractor.value?.name ?? 'Contratante');
const contractorLogo = computed(() => contractor.value?.brand_logo_url ?? contractor.value?.brand_avatar_url ?? null);
const getContractorInitials = (value) => {
    const safe = String(value ?? '').trim();
    if (!safe) return 'CT';
    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
    const initials = `${first}${last}`.trim();
    return initials ? initials.toUpperCase() : 'CT';
};
const contractorInitials = computed(() => getContractorInitials(contractorName.value));
const processTypes = computed(() => props.processTypes ?? []);
const operationTeams = computed(() => (props.operationTeams ?? []).filter((team) => team.is_active));
const responsibleUsers = computed(() => (props.responsibleUsers ?? []).filter((item) => item.is_active));
const userTeamIds = computed(() => (props.userTeamIds ?? []).map((value) => Number(value)));

const availableResponsibleUsers = computed(() => {
    if (isAdminOrMaster.value) return responsibleUsers.value;
    if (!currentUserId.value) return [];
    return responsibleUsers.value.filter((item) => Number(item.id) === Number(currentUserId.value));
});

const availableTeams = computed(() => {
    if (isAdminOrMaster.value) return operationTeams.value;
    if (!userTeamIds.value.length) return [];
    return operationTeams.value.filter((team) => userTeamIds.value.includes(Number(team.id)));
});

const formatUserName = (item) => {
    const name = item?.name ?? '';
    if (!name) return '';
    if (currentUserId.value && Number(item.id) === Number(currentUserId.value)) {
        return `${name} (você)`;
    }
    return name;
};

const filterForm = reactive({
    status: props.filters?.status ?? 'all',
    process_definition_group_id: props.filters?.process_definition_group_id ?? 'all',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
    assigned_user_id: props.filters?.assigned_user_id ?? 'all',
    assigned_team_id: props.filters?.assigned_team_id ?? 'all',
});

const filtersActive = computed(() => {
    if (filterForm.status && filterForm.status !== 'all') return true;
    if (filterForm.process_definition_group_id && filterForm.process_definition_group_id !== 'all') return true;
    if (filterForm.date_from) return true;
    if (filterForm.date_to) return true;
    if (filterForm.assigned_user_id && filterForm.assigned_user_id !== 'all') return true;
    if (filterForm.assigned_team_id && filterForm.assigned_team_id !== 'all') return true;
    return false;
});
const showProcessTypeBadge = computed(() =>
    !filterForm.process_definition_group_id || filterForm.process_definition_group_id === 'all'
);
const exportModalOpen = ref(false);
const planExportOpen = ref(false);
const planUsageDetailsOpen = ref(false);
const indicatorsModalOpen = ref(false);

const buildFilterPayload = () => {
    const payload = {};
    if (filterForm.status && filterForm.status !== 'all') payload.status = filterForm.status;
    if (filterForm.process_definition_group_id && filterForm.process_definition_group_id !== 'all') {
        payload.process_definition_group_id = filterForm.process_definition_group_id;
    }
    if (filterForm.date_from) payload.date_from = filterForm.date_from;
    if (filterForm.date_to) payload.date_to = filterForm.date_to;
    if (filterForm.assigned_user_id && filterForm.assigned_user_id !== 'all') {
        payload.assigned_user_id = filterForm.assigned_user_id;
    }
    if (filterForm.assigned_team_id && filterForm.assigned_team_id !== 'all') {
        payload.assigned_team_id = filterForm.assigned_team_id;
    }
    return payload;
};
const buildActiveFilterPayload = () => {
    const payload = {};
    const activeFilters = props.filters ?? {};
    if (activeFilters.status && activeFilters.status !== 'all') payload.status = activeFilters.status;
    if (activeFilters.process_definition_group_id && activeFilters.process_definition_group_id !== 'all') {
        payload.process_definition_group_id = activeFilters.process_definition_group_id;
    }
    if (activeFilters.date_from) payload.date_from = activeFilters.date_from;
    if (activeFilters.date_to) payload.date_to = activeFilters.date_to;
    if (activeFilters.assigned_user_id && activeFilters.assigned_user_id !== 'all') {
        payload.assigned_user_id = activeFilters.assigned_user_id;
    }
    if (activeFilters.assigned_team_id && activeFilters.assigned_team_id !== 'all') {
        payload.assigned_team_id = activeFilters.assigned_team_id;
    }
    return payload;
};

const applyFilters = () => {
    router.get(route('dashboard'), buildFilterPayload(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.status = 'all';
    filterForm.process_definition_group_id = 'all';
    filterForm.date_from = '';
    filterForm.date_to = '';
    filterForm.assigned_user_id = 'all';
    filterForm.assigned_team_id = 'all';
    applyFilters();
};
const exportReport = (format) => {
    const normalized = String(format ?? '').toLowerCase();
    if (!['pdf', 'excel', 'xlsx'].includes(normalized)) {
        return;
    }
    exportModalOpen.value = false;
    const payload = {
        ...buildFilterPayload(),
        format: normalized,
    };
    window.open(route('dashboard.export', payload), '_blank');
};
const applyDashboardQuickFilter = (options = {}) => {
    if (Object.prototype.hasOwnProperty.call(options, 'status')) {
        filterForm.status = options.status ?? 'all';
    }
    if (Object.prototype.hasOwnProperty.call(options, 'process_definition_group_id')) {
        filterForm.process_definition_group_id = options.process_definition_group_id ?? 'all';
    }
    applyFilters();
};
const openProcessing = (processing) => {
    const target = processing?.uuid ?? processing?.id;
    if (!target) return;
    router.visit(route('process-instances.show', target));
};
const openProcessFromQueue = (item) => {
    const target = item?.process_instance_uuid ?? item?.process_instance_id ?? null;
    if (!target) return;
    router.visit(route('process-instances.show', target));
};
const openProcessFromActivity = (event) => {
    const target = event?.process?.uuid ?? event?.process?.id ?? null;
    if (!target) return;
    router.visit(route('process-instances.show', target));
};
const openProcessIndexWithStatus = (status) => {
    const payload = buildActiveFilterPayload();
    if (status) {
        payload.status = status;
    }
    router.visit(route('process-instances.index', payload));
};

const metrics = computed(() => props.metrics ?? {});
const pendingSummary = computed(() => props.pendingSummary ?? {});
const slaSummary = computed(() => props.slaSummary ?? {});
const approvalSummary = computed(() => props.approvalSummary ?? {});
const pendingByStep = computed(() => props.pendingByStep ?? []);
const statusSeries = computed(() => props.statusSeries ?? []);
const recentProcessings = computed(() => props.recentProcessings ?? []);
const processTypeRanking = computed(() => props.processTypeRanking ?? []);
const slaQueue = computed(() => props.slaQueue ?? []);
const activity = computed(() => props.activity ?? []);
const planUsage = computed(() => props.planUsage ?? null);
const planUsageDaily = computed(() => props.planUsageDaily ?? []);
const planUsageByUser = computed(() => props.planUsageByUser ?? []);
const storageUsage = computed(() => props.storageUsage ?? null);

const throughput = computed(() => props.metrics?.throughput ?? []);
const clientMetrics = computed(() => props.metrics?.clients ?? { total: 0, series: [] });
const filesMetrics = computed(() => props.metrics?.files ?? { total: 0 });
const providerDistribution = computed(() => props.metrics?.providerDistribution ?? []);
const tokensByProcessing = computed(() => props.metrics?.tokens?.byProcessing ?? []);
const tokensByProvider = computed(() => props.metrics?.tokens?.byProvider ?? []);

const resolvedMetrics = computed(() => ({
    total: Number(metrics.value.total ?? 0),
    in_progress: Number(metrics.value.in_progress ?? 0),
    pending: Number(metrics.value.pending ?? 0),
    paused: Number(metrics.value.paused ?? 0),
    overdue: Number(metrics.value.overdue ?? 0),
    completed: Number(metrics.value.completed ?? 0),
    cancelled: Number(metrics.value.cancelled ?? 0),
    rejected: Number(metrics.value.rejected ?? 0),
    avg_duration_hours: metrics.value.avg_duration_hours ?? null,
    avg_step_resolution_hours: metrics.value.avg_step_resolution_hours ?? null,
}));
const resolvedPending = computed(() => ({
    open: Number(pendingSummary.value.open ?? 0),
}));
const resolvedSla = computed(() => ({
    at_risk: Number(slaSummary.value.at_risk ?? 0),
}));
const resolvedApproval = computed(() => ({
    total: Number(approvalSummary.value.total ?? 0),
    approved: Number(approvalSummary.value.approved ?? 0),
    rejected: Number(approvalSummary.value.rejected ?? 0),
    approval_rate: approvalSummary.value.approval_rate ?? null,
}));
const resolvedSeries = computed(() => statusSeries.value ?? []);
const resolvedRecent = computed(() => recentProcessings.value ?? []);
const resolvedRanking = computed(() => processTypeRanking.value ?? []);
const resolvedQueue = computed(() => slaQueue.value ?? []);
const resolvedActivity = computed(() => activity.value ?? []);
const resolvedPendingByStep = computed(() => pendingByStep.value ?? []);
const resolvedThroughput = computed(() => {
    if (Array.isArray(throughput.value) && throughput.value.length) return throughput.value;

    const series = resolvedSeries.value ?? [];
    if (Array.isArray(series) && series.length) {
        return series.slice(-7).map((item) => ({
            day: item.date ? item.date.slice(5).replace('-', '/') : '',
            total: Number(item.completed ?? item.total ?? 0),
        }));
    }

    return [];
});
const resolvedClients = computed(() => {
    const data = clientMetrics.value ?? {};
    return {
        total: Number(data.total ?? 0),
        series: Array.isArray(data.series) ? data.series : [],
    };
});
const resolvedFiles = computed(() => ({
    total: Number(filesMetrics.value?.total ?? 0),
}));
const resolvedProviderDistribution = computed(() =>
    Array.isArray(providerDistribution.value) ? providerDistribution.value : []
);
const resolvedTokensByProcessing = computed(() =>
    Array.isArray(tokensByProcessing.value) ? tokensByProcessing.value : []
);
const resolvedTokensByProvider = computed(() =>
    Array.isArray(tokensByProvider.value) ? tokensByProvider.value : []
);

const formatNumber = (value) => Number(value ?? 0).toLocaleString('pt-BR');
const formatDateTime = (value) => (value ? value.substring(0, 16).replace('T', ' ') : '');
const formatDate = (value) => {
    if (!value) return '';
    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('pt-BR');
};
const formatMonthLabel = (value) => {
    if (!value) return '';
    const parsed = new Date(`${value}-01T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
};
const formatPercent = (value) => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '0%';
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '0%';
    return `${numeric.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%`;
};
const resolvePercent = (used, limit) => {
    const usedValue = Number(used ?? 0);
    const limitValue = Number(limit ?? 0);
    if (!Number.isFinite(usedValue) || !Number.isFinite(limitValue) || limitValue <= 0) return null;
    return (usedValue / limitValue) * 100;
};
const formatBytes = (value) => {
    const bytes = Number(value ?? 0);
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 MB';
    const mb = bytes / (1024 * 1024);
    if (mb < 1024) {
        return `${mb.toFixed(1)} MB`;
    }
    return `${(mb / 1024).toFixed(1)} GB`;
};
const formatBytesPrecise = (value) => {
    const bytes = Number(value ?? 0);
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 MB';
    const mb = bytes / (1024 * 1024);
    if (mb < 1024) {
        return `${mb.toFixed(2)} MB`;
    }
    return `${(mb / 1024).toFixed(2)} GB`;
};
const formatHours = (value) => {
    const numeric = Number(value ?? 0);
    if (!Number.isFinite(numeric) || numeric === 0) return '';
    const normalized = Math.abs(numeric);
    return `${normalized.toFixed(1)}h`;
};
const safeDate = (value) => (value ? value.slice(0, 10) : '');
const barWidth = (n) => `${Math.min((Number(n) || 0) * 8, 100)}%`;
const formatTokens = (value) => Number(value ?? 0).toLocaleString('pt-BR');

const storageLimitLabel = computed(() => {
    const limit = Number(storageUsage.value?.limit_gb ?? 0);
    return limit > 0 ? `${limit} GB` : 'Ilimitado';
});

const buildSparkline = (series) => {
    if (!Array.isArray(series) || series.length === 0) {
        return '';
    }

    const totals = series.map((item) => Number(item.total) || 0);
    const max = Math.max(...totals, 1);
    const lastIndex = series.length - 1;

    return series
        .map((item, index) => {
            const x = lastIndex === 0 ? 0 : Math.round((index / lastIndex) * 100);
            const value = Number(item.total) || 0;
            const y = 40 - Math.round((value / max) * 36);
            return `${x},${Math.max(Math.min(y, 40), 4)}`;
        })
        .join(' ');
};

const clientsSparkline = computed(() => buildSparkline(resolvedClients.value.series ?? []));
const throughputSparkline = computed(() => buildSparkline(resolvedThroughput.value ?? []));

const clientsDelta = computed(() => {
    const series = resolvedClients.value.series ?? [];
    if (series.length < 2) {
        return 0;
    }
    const first = Number(series[0].total) || 0;
    const last = Number(series[series.length - 1].total) || 0;
    return last - first;
});
const throughputTotal = computed(() => (resolvedThroughput.value ?? []).reduce((sum, item) => sum + (Number(item.total) || 0), 0));
const clientTotal = computed(() => Number(resolvedClients.value.total ?? 0));
const filesTotal = computed(() => Number(resolvedFiles.value.total ?? 0));
const formattedClientsDelta = computed(() => {
    const delta = clientsDelta.value;
    if (!delta) return '0';
    const sign = delta > 0 ? '+' : '';
    return `${sign}${delta}`;
});

const planAlertDismissed = ref(false);
const showPlanAlert = computed(() => {
    const percent = Number(planUsage.value?.percent ?? 0);
    return !planAlertDismissed.value && Number.isFinite(percent) && percent >= 90;
});

const monthOptions = computed(() => {
    const months = new Map();
    planUsageDaily.value.forEach((item) => {
        const key = item?.day ? String(item.day).slice(0, 7) : '';
        if (key) {
            months.set(key, { value: key, label: formatMonthLabel(key) });
        }
    });
    if (!months.size && planUsage.value?.period_start) {
        const key = String(planUsage.value.period_start).slice(0, 7);
        if (key) {
            months.set(key, { value: key, label: formatMonthLabel(key) });
        }
    }
    return Array.from(months.values()).sort((a, b) => b.value.localeCompare(a.value));
});

const selectedMonth = ref('');

const allowPlanUsageRefresh = computed(() => {
    const latest = monthOptions.value[0]?.value ?? '';
    return !selectedMonth.value || selectedMonth.value === latest;
});

const dailyForMonth = computed(() => {
    const selected = selectedMonth.value;
    const days = Array.isArray(planUsageDaily.value) ? planUsageDaily.value : [];
    const filtered = selected ? days.filter((item) => String(item.day ?? '').startsWith(selected)) : days;
    return filtered.slice().sort((a, b) => String(a.day ?? '').localeCompare(String(b.day ?? '')));
});

const weeklyBuckets = computed(() => {
    const days = dailyForMonth.value;
    if (!days.length) return [];
    const buckets = [];
    for (let i = 0; i < days.length; i += 7) {
        buckets.push(days.slice(i, i + 7));
    }
    return buckets;
});

const weekIndex = ref(0);

const weekDays = computed(() => {
    const bucket = weeklyBuckets.value[weekIndex.value] ?? [];
    return bucket.map((item) => ({
        day: item.day,
        label: item.day ? String(item.day).slice(8) : '',
        total: Number(item.total ?? 0),
        enabled: Number(item.total ?? 0) > 0,
    }));
});

const canShiftPrevWeek = computed(() => weekIndex.value > 0);
const canShiftNextWeek = computed(() => weekIndex.value < weeklyBuckets.value.length - 1);

const shiftWeek = (direction) => {
    if (direction < 0 && !canShiftPrevWeek.value) return;
    if (direction > 0 && !canShiftNextWeek.value) return;
    weekIndex.value = Math.min(
        Math.max(weekIndex.value + direction, 0),
        Math.max(weeklyBuckets.value.length - 1, 0)
    );
};

const weekRangeLabel = computed(() => {
    const bucket = weeklyBuckets.value[weekIndex.value] ?? [];
    if (!bucket.length) return '';
    const start = bucket[0]?.day;
    const end = bucket[bucket.length - 1]?.day;
    if (!start || !end) return '';
    return `${formatDate(start)} - ${formatDate(end)}`;
});

const rangeTotalProcesses = computed(() =>
    weekDays.value.reduce((sum, day) => sum + (Number(day.total) || 0), 0)
);

const rangePercent = computed(() =>
    resolvePercent(rangeTotalProcesses.value, planUsage.value?.process_limit)
);

const periodUsed = computed(() => {
    const raw = planUsage.value?.processes_used;
    if (raw === null || raw === undefined) return rangeTotalProcesses.value;
    const numeric = Number(raw);
    return Number.isFinite(numeric) ? numeric : rangeTotalProcesses.value;
});

const periodPercent = computed(() => {
    const exact = resolvePercent(periodUsed.value, planUsage.value?.process_limit);
    if (exact === null || exact === undefined) return rangePercent.value;
    return exact;
});

const storageUsagePercent = computed(() =>
    resolvePercent(storageUsage.value?.used_bytes, storageUsage.value?.limit_bytes)
);

const planUsageTooltip = computed(() => {
    const limit = Number(planUsage.value?.process_limit ?? 0);
    if (!Number.isFinite(limit) || limit <= 0) return '';
    const used = Number(periodUsed.value ?? 0);
    const percent = resolvePercent(used, limit);
    return `Usado: ${formatNumber(used)} / ${formatNumber(limit)} processos (${formatPercent(percent)})`;
});

const storageUsageTooltip = computed(() => {
    const limitBytes = Number(storageUsage.value?.limit_bytes ?? 0);
    if (!Number.isFinite(limitBytes) || limitBytes <= 0) return '';
    const usedBytes = Number(storageUsage.value?.used_bytes ?? 0);
    const percent = resolvePercent(usedBytes, limitBytes);
    return `Usado: ${formatBytesPrecise(usedBytes)} / ${formatBytesPrecise(limitBytes)} (${formatPercent(percent)})`;
});

const barHeight = (value) => {
    const total = Number(value ?? 0);
    const max = Math.max(...weekDays.value.map((day) => Number(day.total ?? 0)), 0) || 1;
    return `${Math.max(8, Math.round((total / max) * 100))}%`;
};

const exportPlanUsagePdf = () => exportReport('pdf');
const exportPlanUsageExcel = () => exportReport('excel');

const approvalRate = computed(() => {
    const rate = resolvedApproval.value.approval_rate;
    if (rate === null || rate === undefined || isNaN(rate)) return null;
    return Math.round(Number(rate));
});

const autoRefreshEnabled = ref(true);
const refreshTimer = ref(null);
const isLoading = ref(false);
const prefersReducedMotion = typeof window !== 'undefined'
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : { matches: false };
const refreshIntervalMs = computed(() => (prefersReducedMotion?.matches ? 30000 : 15000));

const doReload = () => {
    if (document.hidden || !autoRefreshEnabled.value) return;
    isLoading.value = true;
    const reloadKeys = [
        'metrics',
        'pendingSummary',
        'slaSummary',
        'approvalSummary',
        'statusSeries',
        'recentProcessings',
        'processTypeRanking',
        'slaQueue',
        'activity',
        'pendingByStep',
    ];
    if (allowPlanUsageRefresh.value) {
        reloadKeys.push('planUsage', 'planUsageDaily', 'planUsageByUser');
    }
    router.reload({
        only: reloadKeys,
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isLoading.value = false;
        },
    });
};

const startDashboardAutoRefresh = () => {
    if (refreshTimer.value) {
        window.clearInterval(refreshTimer.value);
    }
    refreshTimer.value = window.setInterval(doReload, refreshIntervalMs.value);
};

const stopDashboardAutoRefresh = () => {
    if (refreshTimer.value) {
        window.clearInterval(refreshTimer.value);
        refreshTimer.value = null;
    }
};

const toggleAutoRefresh = () => {
    autoRefreshEnabled.value = !autoRefreshEnabled.value;
    if (autoRefreshEnabled.value) {
        startDashboardAutoRefresh();
    } else {
        stopDashboardAutoRefresh();
    }
};

onMounted(() => {
    startDashboardAutoRefresh();
    if (!selectedMonth.value && monthOptions.value.length) {
        selectedMonth.value = monthOptions.value[0].value;
    }
    if (weeklyBuckets.value.length) {
        weekIndex.value = Math.max(weeklyBuckets.value.length - 1, 0);
    }
});

onBeforeUnmount(() => {
    stopDashboardAutoRefresh();
});

const syncMonthSelection = () => {
    if (!monthOptions.value.length) {
        selectedMonth.value = '';
        weekIndex.value = 0;
        return;
    }
    const exists = monthOptions.value.some((option) => option.value === selectedMonth.value);
    if (!exists) {
        selectedMonth.value = monthOptions.value[0].value;
    }
    if (weeklyBuckets.value.length) {
        weekIndex.value = Math.max(weeklyBuckets.value.length - 1, 0);
    }
};

watch(monthOptions, syncMonthSelection, { immediate: true });
watch(planUsageDaily, () => {
    if (weeklyBuckets.value.length && weekIndex.value > weeklyBuckets.value.length - 1) {
        weekIndex.value = Math.max(weeklyBuckets.value.length - 1, 0);
    }
});

const statusTone = (status) => {
    switch (String(status ?? '').toLowerCase()) {
        case 'completed':
            return 'bg-emerald-100 text-emerald-700';
        case 'in_progress':
            return 'bg-sky-100 text-sky-700';
        case 'pending':
            return 'bg-amber-100 text-amber-700';
        case 'paused':
            return 'bg-orange-100 text-orange-700';
        case 'overdue':
        case 'rejected':
            return 'bg-rose-100 text-rose-700';
        case 'cancelled':
            return 'bg-slate-100 text-slate-600';
        default:
            return 'bg-slate-100 text-slate-600';
    }
};
const seriesMax = computed(() => {
    const max = Math.max(...resolvedSeries.value.map((item) => item.total ?? 0), 0);
    return max || 1;
});

const statusSlices = computed(() => {
    const metrics = resolvedMetrics.value;
    const total = Math.max(metrics.total || 0, 1);
    const slices = [
        { label: 'Em andamento', value: metrics.in_progress, color: '#0ea5e9' },
        { label: 'Pendente', value: metrics.pending, color: '#f59e0b' },
        { label: 'Pausado', value: metrics.paused, color: '#fb923c' },
        { label: 'Atrasado', value: metrics.overdue, color: '#f97316' },
        { label: 'Concluído', value: metrics.completed, color: '#10b981' },
        { label: 'Cancelado', value: metrics.cancelled, color: '#94a3b8' },
        { label: 'Rejeitado', value: metrics.rejected, color: '#f43f5e' },
    ];
    const knownTotal = slices.reduce((acc, item) => acc + (item.value || 0), 0);
    if (total > knownTotal) {
        slices.push({ label: 'Outros', value: Math.max(total - knownTotal, 0), color: '#cbd5f5' });
    }
    return slices;
});

const statusGradient = computed(() => {
    const slices = statusSlices.value;
    const total = slices.reduce((acc, item) => acc + (item.value || 0), 0) || 1;
    let cursor = 0;
    const segments = slices.map((slice) => {
        const size = (slice.value / total) * 100;
        const start = cursor;
        cursor += size;
        return `${slice.color} ${start}% ${cursor}%`;
    });
    return segments.join(', ');
});

const totalRanking = computed(() =>
    resolvedRanking.value.reduce((acc, item) => acc + (item.total ?? 0), 0) || 1
);

const formatMinutes = (minutes) => {
    if (minutes === null || minutes === undefined || isNaN(minutes)) return '';

    const totalMinutes = Math.max(0, Math.floor(Math.abs(Number(minutes))));
    const isOverdue = Number(minutes) < 0;

    let label = `${totalMinutes} min`;
    if (totalMinutes >= 60) {
        const hours = Math.floor(totalMinutes / 60);
        const remaining = totalMinutes % 60;
        label = `${hours}h ${remaining}m`;
    }

    return isOverdue ? `${label} atrasado` : label;
};

const slaTone = (minutes) => {
    if (minutes === null || minutes === undefined) return 'bg-slate-100 text-slate-600';
    if (minutes < 0) return 'bg-rose-100 text-rose-700';
    if (minutes <= 120) return 'bg-amber-100 text-amber-700';
    return 'bg-emerald-100 text-emerald-700';
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <section class="relative overflow-hidden rounded-3xl px-6 py-8 md:px-10 md:py-10">
                <div class="pointer-events-none absolute inset-0" :style="{ background: glassGradient }" />
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-3xl space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/95 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200/70">
                                Monitoramento em tempo real
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/90 text-lg font-semibold text-slate-700 shadow-sm"
                                :style="{ backgroundImage: contractorActiveGradient }"
                            >
                                <img v-if="contractorLogo" :src="contractorLogo" :alt="contractorName" class="h-9 w-9 rounded-xl object-contain" />
                                <span v-else class="text-white">{{ contractorInitials }}</span>
                
                            </div>
                            <div>
                                <h1 class="text-xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                                    Olá {{ userFirstName }}, acompanhe os insights
                                </h1>
                            </div>
                        </div>
                        
                        <p class="text-sm md:text-[0.85rem] text-slate-600">
                            Panorama completo de volume, desempenho, pendências e cumprimento de SLA do workflow.
                        </p>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-emerald-700 ring-1 ring-emerald-200">
                                <Activity class="h-4 w-4" />
                                {{ formatNumber(resolvedMetrics.total) }} processos monitorados
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-emerald-700 ring-1 ring-emerald-200">
                                <AlertTriangle class="h-4 w-4" />
                                {{ formatNumber(resolvedPending.open) }} pendências abertas
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-emerald-700 ring-1 ring-emerald-200">
                                <Clock4 class="h-4 w-4" />
                                SLA em risco: {{ formatNumber(resolvedSla.at_risk) }}
                            </span>
                        </div>
                    </div>

                    <div class="relative w-full rounded-2xl border border-white/70 bg-white/90 p-4 md:p-5 shadow-lg backdrop-blur sm:w-auto">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-sky-500 text-white shadow-lg">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none">
                                    <path d="M5 13.5l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-emerald-600">Saúde operacional</p>
                                <p class="text-sm font-semibold text-slate-900">Orquestração ativa</p>
                                <p class="text-xs text-slate-500">Workflows, filas e análses sob monitoramento.</p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                @click="doReload"
                                class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-300"
                                :aria-busy="isLoading"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M4 10a6 6 0 1012 0h-2a4 4 0 11-8 0H4z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                                Atualizar
                            </button>
                            <button
                                type="button"
                                @click="toggleAutoRefresh"
                                class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-300"
                                :aria-pressed="autoRefreshEnabled"
                            >
                                <span class="inline-flex h-2 w-2 rounded-full" :class="autoRefreshEnabled ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                {{ autoRefreshEnabled ? 'Auto (on)' : 'Auto (off)' }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <div class="space-y-8">
            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
                <!--
                <div class="relative overflow-hidden rounded-3xl border border-white/70 p-5 shadow-sm backdrop-blur" :style="{ background: glassGradient }">
                    <div class="pointer-events-none absolute inset-0 tenant-glass" />
                    <div class="relative">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600">Volume diário</p>
                                <h3 class="text-lg font-semibold text-slate-900">Fluxo de entrada (14 dias)</h3>
                            </div>
                            <span class="inline-flex items-center z-10 gap-2 rounded-full bg-white/85 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-200/70">
                                <BarChart3 class="h-4 w-4" />
                                Consolidado diário
                            </span>
                        </div>

                        <div v-if="resolvedSeries.length" class="mt-4">
                            <div class="flex h-32 items-end gap-2">
                                <div
                                    v-for="item in resolvedSeries"
                                    :key="item.date"
                                    class="group flex flex-1 flex-col items-center gap-2"
                                >
                                    <div class="relative flex h-28 w-full items-end">
                                        <div
                                            class="w-full rounded-t-xl bg-emerald-100/70"
                                            :style="{ height: `${Math.max(12, Math.round((item.total / seriesMax) * 100))}%` }"
                                        ></div>
                                        <div
                                            class="absolute bottom-0 left-0 right-0 rounded-t-xl bg-emerald-500/80"
                                            :style="{ height: `${Math.max(8, Math.round((item.completed / seriesMax) * 100))}%` }"
                                        ></div>
                                    </div>
                                    <span class="text-[10px] font-semibold text-slate-500">{{ item.date.slice(5) }}</span>
                                    <span class="text-[10px] text-slate-400">{{ item.total }}</span>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-3 text-[11px] text-slate-500">
                                <span class="inline-flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500/80"></span>
                                    Concluídos
                                </span>
                                <span class="inline-flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-emerald-100"></span>
                                    Novos processos
                                </span>
                            </div>
                        </div>

                        <div v-else class="mt-4 rounded-2xl border border-dashed border-emerald-200 bg-white/80 p-5 text-center text-sm text-emerald-700">
                            Nenhum dado de volume disponível para o período.
                        </div>
                    </div>
                </div>
                -->
                <div class="relative overflow-hidden rounded-3xl border border-white/70 p-5 shadow-sm backdrop-blur" :style="{ background: glassGradient }">
                    <div class="pointer-events-none absolute inset-0 tenant-glass" />
                    <div class="relative">
                        <div
                            v-if="showPlanAlert"
                            class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 shadow-sm"
                        >
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold">Consumo do plano próximo do limite</p>
                                    <p class="text-xs text-amber-800">
                                        Você atingiu {{ formatPercent(planUsage?.percent) }} do limite contratado. Considere o upgrade ou acompanhe a migração automática.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 shadow-sm transition hover:bg-amber-100"
                                    @click="planAlertDismissed = true"
                                >
                                    Fechar
                                </button>
                            </div>
                        </div>

                        <article class="relative overflow-hidden rounded-3xl border-none bg-transparent px-5 py-5 shadow-sm">
                            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(140%_100%_at_top,_rgba(240,253,250,0.85)_0%,_transparent_65%)]" />
                            <div class="relative flex flex-col gap-6 lg:flex-row border-none">
                                <div class="lg:w-1/3">
                                    <div class="flex flex-col gap-1">
                                        <h3 class="bg-white/55 w-fit px-2 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wide text-emerald-900/70">Consumo semanal (7 dias)</h3>
                                        <span class="text-[11px] font-semibold text-emerald-700">
                                            {{ formatNumber(rangeTotalProcesses) }} processos
                                        </span>
                                    </div>
                                    <div class="mt-3 flex items-center justify-between text-[11px] text-slate-600">
                                        <button
                                            type="button"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="!canShiftPrevWeek"
                                            aria-label="Semana anterior"
                                            @click="shiftWeek(-1)"
                                        >
                                            &lt;
                                        </button>
                                        <span class="text-[11px] font-semibold text-slate-600">{{ weekRangeLabel }}</span>
                                        <button
                                            type="button"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="!canShiftNextWeek"
                                            aria-label="Proxima semana"
                                            @click="shiftWeek(1)"
                                        >
                                            &gt;
                                        </button>
                                    </div>
                                    <div v-if="weekDays.length" class="mt-4">
                                        <div class="grid grid-cols-7 items-end gap-2">
                                            <div
                                                v-for="day in weekDays"
                                                :key="day.day"
                                                class="flex flex-col items-center gap-2"
                                                :title="day.enabled ? `${formatDate(day.day)}: ${formatNumber(day.total)} processos` : ''"
                                            >
                                                <div class="relative h-28 w-full rounded-full" :class="day.enabled ? 'bg-emerald-200/80' : 'bg-slate-100'">
                                                    <div
                                                        class="absolute bottom-0 w-full rounded-full"
                                                        :class="day.enabled ? 'bg-emerald-600' : 'bg-slate-300'"
                                                        :style="{ height: barHeight(day.total) }"
                                                    />
                                                </div>
                                                <span class="text-[10px] font-semibold text-slate-500">{{ day.label }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-else class="mt-8 text-xs text-slate-500">Sem consumo registrado no período.</p>
                                </div>

                                <div class="flex-1 space-y-4">
                                    <div class="flex flex-col gap-3">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <div class="flex flex-col space-y-1">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-900/70">Consumo do plano</p>
                                                <h3 class="text-lg font-semibold text-slate-900">
                                                    {{ planUsage?.plan?.name ?? 'Sem plano ativo' }}
                                                </h3>
                                                <p class="text-xs text-slate-500">
                                                    Período {{ formatDate(planUsage?.period_start) }} até {{ formatDate(planUsage?.period_end) }}
                                                </p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-600 shadow-sm">
                                                    <span class="text-[10px] uppercase tracking-wide text-slate-400">Mês</span>
                                                    <select
                                                        v-model="selectedMonth"
                                                        class="ml-2 cursor-pointer border-none bg-transparent p-0 pr-6 text-[11px] font-semibold text-slate-700 focus:outline-none focus:ring-1 focus:ring-emerald-400"
                                                    >
                                                        <option v-for="option in monthOptions" :key="option.value" :value="option.value">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
                                                    title="Exportar"
                                                    @click="planExportOpen = true"
                                                >
                                                    <DownloadCloud class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="rounded-2xl border border-emerald-100 bg-white/80 p-4">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Utilizado no período</p>
                                            </div>
                                            <div class="flex items-center justify-between gap-2">
                                                <p
                                                    class="w-fit px-2 py-0.5 bg-slate-200 rounded-full text-[11px] font-semibold text-slate-600"
                                                    :title="planUsageTooltip"
                                                >
                                                    {{ formatNumber(periodUsed) }} processos
                                                </p>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
                                                    title="Ver detalhes"
                                                    @click="planUsageDetailsOpen = true"
                                                >
                                                    <ListChecks class="h-4 w-4" />
                                                </button>
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500">
                                                Limite mensal: {{ planUsage?.process_limit ? formatNumber(planUsage.process_limit) : 'Ilimitado' }}
                                            </p>
                                            <div v-if="planUsage?.process_limit" class="mt-3">
                                                <div
                                                    class="flex items-center justify-between text-[11px] font-semibold text-emerald-700"
                                                    :title="planUsageTooltip"
                                                >
                                                    <span>Percentual</span>
                                                    <span>{{ formatPercent(periodPercent) }}</span>
                                                </div>
                                                <div class="mt-2 h-2 w-full rounded-full bg-emerald-100">
                                                    <div
                                                        class="h-full rounded-full transition-[width] duration-700"
                                                        :class="(periodPercent ?? 0) >= 90 ? 'bg-amber-500' : 'bg-emerald-500'"
                                                        :style="{ width: `${Math.min(periodPercent ?? 0, 100)}%` }"
                                                    />
                                                </div>
                                            </div>
                                            <p v-else class="mt-3 text-xs text-slate-500">Plano sem limite definido.</p>
                                        </div>
                                        <div class="rounded-2xl border border-emerald-100 bg-white/80 p-4">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Armazenamento</p>
                                            </div>
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="w-fit px-2 py-0.5 bg-slate-200 rounded-full text-[11px] font-semibold text-slate-600">
                                                    {{ storageLimitLabel }}
                                                </p>
                                                <span
                                                    class="inline-flex h-8 items-center rounded-full border border-slate-200 bg-white px-3 text-[11px] font-semibold text-slate-600 shadow-sm"
                                                    :title="storageUsageTooltip"
                                                >
                                                    {{ formatBytes(storageUsage?.used_bytes) }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500">
                                                Limite do plano: {{ storageLimitLabel }}
                                            </p>
                                            <div v-if="storageUsage?.limit_bytes" class="mt-3">
                                                <div
                                                    class="flex items-center justify-between text-[11px] font-semibold text-emerald-700"
                                                    :title="storageUsageTooltip"
                                                >
                                                    <span>Percentual</span>
                                                    <span>{{ formatPercent(storageUsagePercent) }}</span>
                                                </div>
                                                <div class="mt-2 h-2 w-full rounded-full bg-emerald-100">
                                                    <div
                                                        class="h-full rounded-full transition-[width] duration-700"
                                                        :class="(storageUsagePercent ?? 0) >= 90 ? 'bg-amber-500' : 'bg-emerald-500'"
                                                        :style="{ width: `${Math.min(storageUsagePercent ?? 0, 100)}%` }"
                                                    />
                                                </div>
                                            </div>
                                            <p v-else class="mt-3 text-xs text-slate-500">Plano sem limite definido.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
                <section class="h-full rounded-3xl border border-white/70 bg-white/90 p-4 text-slate-900 backdrop-blur">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/90 text-sm font-semibold text-slate-700 shadow-sm"
                                :style="{ backgroundImage: contractorActiveGradient }"
                            >
                                <img v-if="contractorLogo" :src="contractorLogo" :alt="contractorName" class="h-8 w-8 rounded-xl object-contain" />
                                <span v-else class="text-white">{{ contractorInitials }}</span>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600">Resumo executivo</p>
                                <p class="text-[13px] font-semibold text-slate-900">{{ contractorName }}</p>
                                <p class="text-[11px] text-slate-500">Consolidado das últimas 2 semanas</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                :href="route('process-instances.index')"
                                class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-2.5 py-1 text-[10px] font-semibold text-white shadow-sm transition hover:bg-slate-800"
                            >
                                Abrir processos
                                <ArrowUpRight class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        <div class="rounded-2xl border border-emerald-100/70 bg-white px-3 py-2.5 cursor-pointer transition hover:border-emerald-200 hover:bg-emerald-50/40" role="button" tabindex="0" @click="openProcessIndexWithStatus('in_progress')" @keydown.enter.prevent="openProcessIndexWithStatus('in_progress')">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Em execução</p>
                            <p class="text-base font-semibold text-slate-900">{{ formatNumber(resolvedMetrics.in_progress) }}</p>
                            <p class="text-[11px] text-slate-500">Processos ativos</p>
                        </div>
                        <div class="rounded-2xl border border-amber-100/70 bg-amber-50/50 px-3 py-2.5 cursor-pointer transition hover:border-amber-200 hover:bg-amber-50/80" role="button" tabindex="0" @click="openProcessIndexWithStatus('pending')" @keydown.enter.prevent="openProcessIndexWithStatus('pending')">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Pendentes</p>
                            <p class="text-base font-semibold text-amber-700">{{ formatNumber(resolvedMetrics.pending) }}</p>
                            <p class="text-[11px] text-slate-500">Pendências e bloqueios</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-100/70 bg-emerald-50/40 px-3 py-2.5 cursor-pointer transition hover:border-emerald-200 hover:bg-emerald-50/70" role="button" tabindex="0" @click="openProcessIndexWithStatus('completed')" @keydown.enter.prevent="openProcessIndexWithStatus('completed')">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Concluídos</p>
                            <p class="text-base font-semibold text-emerald-700">{{ formatNumber(resolvedMetrics.completed) }}</p>
                            <p class="text-[11px] text-slate-500">Finalizados no período</p>
                        </div>
                    </div>
                </section>
            </section>

            <section class="rounded-3xl border border-emerald-100 bg-white/95 p-6 shadow-sm backdrop-blur">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                            <Filter class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Filtros da dashboard</h3>
                            <p class="text-xs text-slate-500">Refine o período, status e tipo de processo para analisar os dados.</p>
                        </div>
                    </div>
                    <SecondaryButton type="button" :icon="Download" class="rounded-full px-4 py-2" @click="exportModalOpen = true">
                        Exportar relatório
                    </SecondaryButton>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <div class="space-y-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Início</label>
                        <input
                            v-model="filterForm.date_from"
                            type="date"
                            class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Fim</label>
                        <input
                            v-model="filterForm.date_to"
                            type="date"
                            class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Status</label>
                            <select
                                v-model="filterForm.status"
                                class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option value="all">Todos</option>
                                <option value="in_progress">Em andamento</option>
                                <option value="pending">Pendente</option>
                                <option value="paused">Pausado</option>
                                <option value="overdue">Atrasado</option>
                                <option value="completed">Concluído</option>
                                <option value="cancelled">Cancelado</option>
                                <option value="rejected">Rejeitado</option>
                            </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tipo de processo</label>
                        <select
                            v-model="filterForm.process_definition_group_id"
                            class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="all">Todos</option>
                            <option
                                v-for="type in processTypes"
                                :key="type.uuid ?? type.id"
                                :value="type.uuid ?? type.id"
                            >
                                {{ type.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-emerald-100/70 bg-emerald-50/40 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-emerald-700 ring-1 ring-emerald-100">
                            <Users2 class="h-5 w-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-slate-900">Responsáveis e times</h4>
                            <p class="text-xs text-slate-600">
                                Admins podem filtrar por qualquer responsável. Operadores veem seu usuário e seus times.
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Responsável</label>
                            <select
                                v-model="filterForm.assigned_user_id"
                                class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option value="all">Todos</option>
                                <option value="unassigned">Sem responsável</option>
                                <option
                                    v-for="item in availableResponsibleUsers"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ formatUserName(item) || item.name }} • {{ item.role }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Time</label>
                            <select
                                v-model="filterForm.assigned_team_id"
                                class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                <option value="all">Todos</option>
                                <option value="unassigned">Sem time</option>
                                <option
                                    v-for="team in availableTeams"
                                    :key="team.id"
                                    :value="team.id"
                                >
                                    {{ team.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-[11px] text-slate-500">
                        {{ filtersActive ? 'Filtros ativos. Dados atualizados conforme seleção.' : 'Sem filtros ativos. Exibindo visão geral.' }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <SecondaryButton type="button" class="rounded-full px-4 py-2" @click="resetFilters">
                            Limpar filtros
                        </SecondaryButton>
                        <PrimaryButton type="button" :icon="Filter" class="rounded-full px-4 py-2" @click="applyFilters">
                            Aplicar filtros
                        </PrimaryButton>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:gap-5 md:gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3 auto-rows-fr">

                <div class="group h-full flex flex-col overflow-hidden rounded-2xl border border-emerald-100 bg-white/95 p-5 sm:p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-emerald-900/70">Tempo médio de resolução</h3>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">SLA</span>
                    </div>
                    <p class="mt-3 sm:mt-4 text-2xl sm:text-3xl font-semibold text-slate-900">
                        {{ formatHours(resolvedMetrics.avg_step_resolution_hours) }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">Entre início e finalização da etapa.</p>
                </div>

                <div class="group h-full flex flex-col overflow-hidden rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50/70 via-white to-amber-50/40 p-5 sm:p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-amber-900/70">Pendências por etapa (30 dias)</h3>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Alertas</span>
                    </div>
                    <ul class="mt-3 sm:mt-4 space-y-2 text-sm text-amber-900/80 flex-1">
                        <li v-if="!resolvedPendingByStep.length" class="flex items-center gap-2 text-amber-600">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <circle cx="10" cy="10" r="6" opacity=".35" />
                                <path d="M10 6v4m0 3h.01" stroke="currentColor" stroke-linecap="round" stroke-width="1.6" />
                            </svg>
                            Nenhuma pendência registrada.
                        </li>
                        <li
                            v-for="item in resolvedPendingByStep"
                            :key="`${item.step}-${item.process_definition_group_uuid ?? item.process_definition_group_id ?? item.process_type}`"
                            class="flex items-center justify-between rounded-lg bg-white px-3 py-2 ring-1 ring-amber-100 cursor-pointer transition hover:bg-amber-50/60"
                            role="button"
                            tabindex="0"
                            @click="applyDashboardQuickFilter({ status: 'pending', process_definition_group_id: item.process_definition_group_uuid ?? item.process_definition_group_id ?? 'all' })"
                            @keydown.enter.prevent="applyDashboardQuickFilter({ status: 'pending', process_definition_group_id: item.process_definition_group_uuid ?? item.process_definition_group_id ?? 'all' })"
                        >
                            <div class="flex items-center gap-2 truncate">
                                <span class="text-sm font-medium truncate max-w-[60%]">{{ item.step ?? 'Etapa' }}</span>
                                <span
                                    v-if="showProcessTypeBadge"
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                                >
                                    {{ item.process_type ?? 'Tipo' }}
                                </span>
                            </div>
                            <span class="text-sm font-semibold text-amber-700">{{ item.total }}</span>
                        </li>
                    </ul>
                </div>

                <div class="group h-full flex flex-col overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/80 via-white to-emerald-100/60 p-5 sm:p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-emerald-900/70">Taxa de aprovação</h3>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Qualidade</span>
                    </div>
                    <div class="mt-3 sm:mt-4 flex items-center justify-between">
                        <div class="space-y-2" aria-live="polite">
                            <p class="text-2xl sm:text-3xl font-semibold text-emerald-900">
                            {{ approvalRate !== null ? approvalRate + '%' : '' }}
                            </p>
                            <p class="text-xs text-emerald-700/80">Decisões aprovadas</p>
                        </div>

                        <div
                            class="flex items-center gap-4 rounded-2xl border border-emerald-200 bg-white/90 px-3 sm:px-4 py-2 sm:py-3 text-sm font-semibold shadow-sm"
                        >
                            <!-- Aprovações -->
                            <div class="flex items-center gap-1 text-emerald-600">
                                <div class="relative">
                                    <CheckCircle class="h-4 w-4 cursor-help peer" />

                                    <span
                                    class="pointer-events-none absolute left-1/2 top-full z-10 mt-2
                                            -translate-x-1/2 whitespace-nowrap rounded-md
                                            bg-slate-900 px-2 py-1 text-[10px] font-medium text-white
                                            opacity-0 transition peer-hover:opacity-100"
                                    role="tooltip"
                                    >
                                    Aprovações
                                    </span>
                                </div>

                                <span class="leading-none">
                                    {{ formatNumber(resolvedApproval.approved) }}
                                </span>
                            </div>

                            <!-- Rejeições -->
                            <div class="flex items-center gap-1 text-rose-600">
                                <div class="relative">
                                    <XCircleIcon class="h-4 w-4 cursor-help peer" />

                                    <span
                                    class="pointer-events-none absolute left-1/2 top-full z-10 mt-2
                                            -translate-x-1/2 whitespace-nowrap rounded-md
                                            bg-slate-900 px-2 py-1 text-[10px] font-medium text-white
                                            opacity-0 transition peer-hover:opacity-100"
                                    role="tooltip"
                                    >
                                    Rejeições
                                    </span>
                                </div>

                                <span class="leading-none">
                                    {{ formatNumber(resolvedApproval.rejected) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 sm:mt-4 text-xs text-slate-500">
                        {{ resolvedApproval.total ? formatNumber(resolvedApproval.total) + ' decisões registradas.' : 'Sem decisões registradas.' }}
                    </p>
                    <div class="mt-auto"></div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm backdrop-blur">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Top workflows</p>
                            <h3 class="text-lg font-semibold text-slate-900">Tipos mais executados</h3>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-2 text-slate-700">
                            <ListChecks class="h-5 w-5" />
                        </div>
                    </div>

                    <div v-if="resolvedRanking.length" class="mt-5 space-y-4">
                        <div
                            v-for="item in resolvedRanking"
                            :key="item.id"
                            class="rounded-2xl border border-slate-200 bg-white px-4 py-4 cursor-pointer transition hover:bg-slate-50"
                            role="button"
                            tabindex="0"
                            @click="applyDashboardQuickFilter({ process_definition_group_id: item.uuid ?? item.id })"
                            @keydown.enter.prevent="applyDashboardQuickFilter({ process_definition_group_id: item.uuid ?? item.id })"
                        >
                            <div class="flex justify-end">
                                <span class="flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                    execuções: {{ formatNumber(item.total) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-900">{{ item.name }}</p>
                                
                            </div>
                            <div class="mt-3 h-2 w-full rounded-full bg-slate-100">
                                <div
                                    class="h-2 rounded-full bg-emerald-500"
                                    :style="{ width: `${Math.min(100, Math.round((item.total / totalRanking) * 100))}%` }"
                                ></div>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-500">
                                Concluídos: {{ formatNumber(item.completed) }}
                            </p>
                        </div>
                    </div>

                    <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                        Nenhum tipo de processo executado até o momento.
                    </div>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm backdrop-blur">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Atividade recente</p>
                            <h3 class="text-lg font-semibold text-slate-900">Movimentações do time</h3>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-2 text-slate-700">
                            <FileCheck class="h-5 w-5" />
                        </div>
                    </div>

                    <div v-if="resolvedActivity.length" class="mt-5 space-y-4">
                        <div
                            v-for="event in resolvedActivity"
                            :key="event.id"
                            class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 cursor-pointer transition hover:bg-slate-50"
                            role="button"
                            tabindex="0"
                            @click="openProcessFromActivity(event)"
                            @keydown.enter.prevent="openProcessFromActivity(event)"
                        >
                            <div class="mt-1 h-2 w-2 rounded-full bg-emerald-500"></div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-slate-900">{{ event.title }}</p>
                                <p class="text-[11px] text-slate-500">
                                    {{ event.actor ?? 'Sistema' }} - {{ event.process?.type ?? 'Processo' }}
                                </p>
                                <p class="text-[11px] text-slate-400">
                                    {{ event.process?.reference ?? '' }}  {{ formatDateTime(event.created_at) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                        Nenhuma atividade registrada recentemente.
                    </div>
                </div>
                <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-100/70 via-white to-emerald-100/50 p-6 shadow-sm backdrop-blur">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Distribuição</p>
                            <h3 class="text-lg font-semibold text-slate-900">Status dos processos</h3>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-2 text-slate-700">
                            <Sparkles class="h-5 w-5" />
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4">
                        <div class="flex items-center gap-4">
                            <div class="relative h-24 w-24">
                                <div
                                    class="h-24 w-24 rounded-full"
                                    :style="{ background: `conic-gradient(${statusGradient})` }"
                                ></div>
                                <div class="absolute inset-3 rounded-full bg-white/95"></div>
                                <div class="absolute inset-0 flex flex-col items-center justify-center text-xs font-semibold text-slate-700">
                                    <span>{{ formatNumber(resolvedMetrics.total) }}</span>
                                    <span class="text-[10px] text-slate-400">total</span>
                                </div>
                            </div>
                            <div class="flex-1 space-y-2 text-xs">
                                <div
                                    v-for="slice in statusSlices"
                                    :key="slice.label"
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span class="inline-flex items-center gap-2 text-slate-600">
                                        <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: slice.color }"></span>
                                        {{ slice.label }}
                                    </span>
                                    <span class="font-semibold text-slate-700">{{ formatNumber(slice.value) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 px-4 py-3 text-xs text-emerald-900">
                            <p class="font-semibold">Indicador de risco</p>
                            <p class="mt-1">SLA em risco: {{ formatNumber(resolvedSla.at_risk) }} etapa(s) próximas do vencimento.</p>
                        </div>
                    </div>
                </div>
            </section>
            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <div class="rounded-3xl border border-emerald-100 bg-white/95 p-6 shadow-sm backdrop-blur">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Processos recentes</p>
                            <h3 class="text-lg font-semibold text-slate-900">Últimas aberturas</h3>
                        </div>
                        <Link
                            :href="route('process-instances.index')"
                            class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50"
                        >
                            Ver todos
                            <ArrowUpRight class="h-4 w-4" />
                        </Link>
                    </div>

                    <div v-if="resolvedRecent.length" class="mt-5 space-y-3 md:hidden">
                        <article
                            v-for="processing in resolvedRecent"
                            :key="processing.id"
                            class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm"
                            role="button"
                            tabindex="0"
                            @click="openProcessing(processing)"
                            @keydown.enter.prevent="openProcessing(processing)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-emerald-950/90">
                                        {{ processing.reference ?? `#${(processing.uuid ?? '').slice(0, 8)}` }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ processing.process_type?.name ?? 'Tipo não informado' }}
                                    </p>
                                </div>
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold"
                                    :class="statusTone(processing.status)"
                                >
                                    {{ processing.status_label ?? processing.status }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                                <span>Etapa: {{ processing.current_step?.name ?? '—' }}</span>
                                <span>{{ formatDateTime(processing.created_at) }}</span>
                            </div>
                        </article>
                    </div>

                    <div v-if="resolvedRecent.length" class="mt-5 hidden rounded-2xl border border-emerald-100 md:block">
                        <div class="w-full overflow-x-auto [-webkit-overflow-scrolling:touch]">
                            <table class="w-max min-w-full divide-y divide-emerald-100 text-sm">
                                <thead class="bg-emerald-50/70 text-left text-[11px] font-semibold uppercase tracking-wide text-emerald-800/80">
                                    <tr>
                                    <th class="px-4 py-3 whitespace-nowrap">Referência</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Tipo</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Etapa atual</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Status</th>
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Criado em</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-emerald-50">
                                    <tr
                                        v-for="processing in resolvedRecent"
                                        :key="processing.id"
                                        class="cursor-pointer hover:bg-emerald-50/40"
                                        role="button"
                                        tabindex="0"
                                        @click="openProcessing(processing)"
                                        @keydown.enter.prevent="openProcessing(processing)"
                                    >
                                    <td class="px-4 py-3 font-semibold text-emerald-950/90 whitespace-nowrap">
                                        {{ processing.reference ?? `#${(processing.uuid ?? '').slice(0, 8)}` }}
                                    </td>

                                    <td class="px-4 py-3 text-emerald-950/90 whitespace-nowrap">
                                        {{ processing.process_type?.name ?? '' }}
                                    </td>

                                    <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                                        {{ processing.current_step?.name ?? '' }}
                                    </td>

                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold whitespace-nowrap"
                                        :class="statusTone(processing.status)"
                                        >
                                        {{ processing.status_label ?? processing.status }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-right text-xs text-slate-500 whitespace-nowrap">
                                        {{ formatDateTime(processing.created_at) }}
                                    </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="mt-5 rounded-2xl border border-dashed border-emerald-100 bg-emerald-50/60 p-6 text-center text-sm text-emerald-700">
                        Nenhum processo registrado até o momento.
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="rounded-3xl border border-amber-100 bg-white/95 p-6 shadow-sm backdrop-blur">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">SLA em risco</p>
                                <h3 class="text-lg font-semibold text-slate-900">Etapas próximas do vencimento</h3>
                            </div>
                            <div class="rounded-2xl bg-amber-50 p-2 text-amber-700">
                                <Clock4 class="h-5 w-5" />
                            </div>
                        </div>

                        <div v-if="resolvedQueue.length" class="mt-4 space-y-3">
                            <div
                                v-for="item in resolvedQueue"
                                :key="item.id"
                                class="rounded-2xl border border-amber-100/70 bg-amber-50/50 px-4 py-3 text-xs text-amber-900 cursor-pointer transition hover:bg-amber-100/60"
                                role="button"
                                tabindex="0"
                                @click="openProcessFromQueue(item)"
                                @keydown.enter.prevent="openProcessFromQueue(item)"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-slate-900">{{ item.reference ?? 'Processo' }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="slaTone(item.minutes_left)">
                                        {{ formatMinutes(item.minutes_left) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-500">{{ item.process_type ?? 'Tipo não informado' }}</p>
                                <p class="mt-1 text-[11px] text-amber-700">Etapa: {{ item.step ?? '' }}</p>
                            </div>
                        </div>
                        <div v-else class="mt-4 rounded-2xl border border-dashed border-amber-200 bg-amber-50/70 p-4 text-center text-xs text-amber-800">
                            Nenhuma etapa com SLA em risco no momento.
                        </div>
                    </div>

                    <div class="rounded-3xl border border-emerald-100 bg-white/95 p-6 shadow-sm backdrop-blur">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Qualidade</p>
                                <h3 class="text-lg font-semibold text-slate-900">Indicadores de entrega</h3>
                            </div>
                            <div class="rounded-2xl bg-emerald-50 p-2 text-emerald-700">
                                <ShieldCheck class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-3 text-xs text-slate-600">
                            <div class="flex items-center justify-between rounded-2xl border border-emerald-100 bg-emerald-50/60 px-4 py-3">
                                <span>Processos concluídos</span>
                                <span class="font-semibold text-emerald-800">{{ formatNumber(resolvedMetrics.completed) }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <span>Processos rejeitados</span>
                                <span class="font-semibold text-slate-800">{{ formatNumber(resolvedMetrics.rejected) }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl border border-sky-100 bg-sky-50/60 px-4 py-3">
                                <span>Tempo médio por processo</span>
                                <span class="font-semibold text-sky-800">{{ formatHours(resolvedMetrics.avg_duration_hours) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <section class="rounded-3xl border border-emerald-100 bg-white/95 p-6 shadow-sm backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Insights</p>
                        <h3 class="text-lg font-semibold text-slate-900">Oportunidades imediatas</h3>
                    </div>
                    <PrimaryButton :icon="Sparkles" class="rounded-full px-4 py-2" @click="indicatorsModalOpen = true">
                        Acompanhar indicadores
                    </PrimaryButton>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <div
                        class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-900 cursor-pointer transition hover:bg-emerald-50/80"
                        role="button"
                        tabindex="0"
                        @click="applyDashboardQuickFilter({ status: 'pending' })"
                        @keydown.enter.prevent="applyDashboardQuickFilter({ status: 'pending' })"
                    >
                        <p class="font-semibold">Pêndencias ativas</p>
                        <p class="mt-2 text-xs text-emerald-800">
                            {{ formatNumber(resolvedPending.open) }} pendências exigem retorno antes da próxima etapa.
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-sky-100 bg-sky-50/60 p-4 text-sm text-sky-900 cursor-pointer transition hover:bg-sky-50/80"
                        role="button"
                        tabindex="0"
                        @click="applyDashboardQuickFilter({ status: 'in_progress' })"
                        @keydown.enter.prevent="applyDashboardQuickFilter({ status: 'in_progress' })"
                    >
                        <p class="font-semibold">Fluxos ativos</p>
                        <p class="mt-2 text-xs text-sky-800">
                            {{ formatNumber(resolvedMetrics.in_progress) }} processos em execução. Priorize os que possuem SLA curto.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700">
                        <p class="font-semibold">Histórico recente</p>
                        <p class="mt-2 text-xs text-slate-500">
                            {{ formatNumber(resolvedMetrics.completed) }} processos concluídos com eficiência média de {{ formatHours(resolvedMetrics.avg_duration_hours) }}.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <Modal :show="indicatorsModalOpen" max-width="6xl" @close="indicatorsModalOpen = false">
            <div class="w-full rounded-3xl bg-white p-6 text-slate-900 shadow-2xl">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Indicadores do período</h3>
                        <p class="text-xs text-slate-500">
                            Período {{ formatDate(planUsage?.period_start) || '—' }} até {{ formatDate(planUsage?.period_end) || '—' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <SecondaryButton class="rounded-full px-4 py-2" @click="indicatorsModalOpen = false">
                            Fechar
                        </SecondaryButton>
                        <PrimaryButton class="rounded-full px-4 py-2" @click="exportModalOpen = true">
                            Exportar relatório
                        </PrimaryButton>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Processos no período</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ formatNumber(periodUsed) }}</p>
                        <p class="text-xs text-slate-500">
                            Limite: {{ planUsage?.process_limit ? formatNumber(planUsage.process_limit) : 'Ilimitado' }}
                            <span v-if="planUsage?.process_limit">· {{ formatPercent(periodPercent) }}</span>
                        </p>
                    </div>
                    <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">SLA em risco</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ formatNumber(resolvedSla.at_risk) }}</p>
                        <p class="text-xs text-slate-500">Etapas próximas do vencimento</p>
                    </div>
                    <div class="rounded-2xl border border-sky-100 bg-sky-50/60 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-700">Pendências abertas</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ formatNumber(resolvedPending.open) }}</p>
                        <p class="text-xs text-slate-500">Aguardando retorno</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tempo médio</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ formatHours(resolvedMetrics.avg_duration_hours) || '—' }}</p>
                        <p class="text-xs text-slate-500">Por processo concluído</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-100 bg-white p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Consumo do plano</p>
                                <p class="text-sm font-semibold text-slate-900">{{ planUsage?.plan?.name ?? 'Sem plano ativo' }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ formatNumber(periodUsed) }} de {{ planUsage?.process_limit ? formatNumber(planUsage.process_limit) : 'Ilimitado' }} processos
                                </p>
                            </div>
                            <span class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700">
                                {{ formatPercent(periodPercent) }}
                            </span>
                        </div>
                        <div v-if="planUsage?.process_limit" class="mt-4">
                            <div class="h-2 w-full rounded-full bg-emerald-100">
                                <div
                                    class="h-full rounded-full transition-[width] duration-700"
                                    :class="(periodPercent ?? 0) >= 90 ? 'bg-amber-500' : 'bg-emerald-500'"
                                    :style="{ width: `${Math.min(periodPercent ?? 0, 100)}%` }"
                                />
                            </div>
                        </div>
                        <p v-else class="mt-3 text-xs text-slate-500">Plano sem limite definido.</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Armazenamento</p>
                                <p class="text-sm font-semibold text-slate-900">{{ formatBytes(storageUsage?.used_bytes) }}</p>
                                <p class="text-xs text-slate-500">Limite: {{ storageLimitLabel }}</p>
                            </div>
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold text-slate-600">
                                {{ formatPercent(storageUsagePercent) }}
                            </span>
                        </div>
                        <div v-if="storageUsage?.limit_bytes" class="mt-4">
                            <div class="h-2 w-full rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full transition-[width] duration-700"
                                    :class="(storageUsagePercent ?? 0) >= 90 ? 'bg-amber-500' : 'bg-emerald-500'"
                                    :style="{ width: `${Math.min(storageUsagePercent ?? 0, 100)}%` }"
                                />
                            </div>
                        </div>
                        <p v-else class="mt-3 text-xs text-slate-500">Plano sem limite definido.</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <SecondaryButton class="rounded-full px-4 py-2" @click="openProcessIndexWithStatus('pending')">
                        Ver pendências
                    </SecondaryButton>
                    <SecondaryButton class="rounded-full px-4 py-2" @click="openProcessIndexWithStatus('in_progress')">
                        Ver processos ativos
                    </SecondaryButton>
                    <SecondaryButton class="rounded-full px-4 py-2" @click="openProcessIndexWithStatus('overdue')">
                        Ver atrasados
                    </SecondaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="planUsageDetailsOpen" max-width="lg" @close="planUsageDetailsOpen = false">
            <div class="w-full rounded-3xl bg-white p-6 text-slate-900 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Detalhes de utilização</h3>
                        <p class="text-xs text-slate-500">
                            Período {{ formatDate(planUsage?.period_start) }} até {{ formatDate(planUsage?.period_end) }}
                        </p>
                    </div>
                    <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="planUsageDetailsOpen = false">
                        Fechar
                    </button>
                </div>
                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-600">
                    <div class="flex items-center justify-between">
                        <span>Total utilizado no período</span>
                        <span class="font-semibold text-slate-900">{{ formatNumber(periodUsed) }} processos</span>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        <span>Usuário</span>
                        <span>Processos</span>
                    </div>
                    <ul v-if="planUsageByUser.length" class="mt-3 space-y-2 text-sm text-slate-700">
                        <li
                            v-for="item in planUsageByUser"
                            :key="item.user_id ?? item.name"
                            class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                        >
                            <span class="max-w-[70%] truncate">{{ item.name }}</span>
                            <span class="text-xs font-semibold text-slate-600">{{ formatNumber(item.total) }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-xs text-slate-500">Sem consumo registrado neste período.</p>
                </div>
            </div>
        </Modal>

        <Modal :show="planExportOpen" max-width="sm" @close="planExportOpen = false">
            <div class="w-full rounded-3xl bg-white p-6 text-slate-900 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Exportar consumo do plano</h3>
                        <p class="text-xs text-slate-500">Escolha o formato para exportar o período selecionado.</p>
                    </div>
                    <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="planExportOpen = false">
                        Fechar
                    </button>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-600"
                        @click="planExportOpen = false; exportPlanUsagePdf()"
                    >
                        <DownloadCloud class="h-4 w-4" />
                        Exportar PDF
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100"
                        @click="planExportOpen = false; exportPlanUsageExcel()"
                    >
                        <DownloadCloud class="h-4 w-4" />
                        Exportar Excel
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="exportModalOpen" max-width="md" @close="exportModalOpen = false">
            <div class="w-full rounded-3xl bg-white p-6 text-slate-900 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Exportar relatório</h3>
                        <p class="text-xs text-slate-500">Escolha o formato para baixar os dados atuais da dashboard.</p>
                    </div>
                    <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="exportModalOpen = false">
                        Fechar
                    </button>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <button 
                        type="button" 
                        :icon="DownloadCloud"
                        class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-600" 
                        @click="exportReport('pdf')">
                        Exportar PDF
                    </button>
                    <button 
                        type="button" 
                        :icon="DownloadCloud"
                        class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100" 
                        @click="exportReport('excel')">
                        Exportar Excel
                    </button>
                </div>
            </div>
        </Modal>
    
        <transition name="fade" appear>
            <div v-if="isLoading" class="pointer-events-none fixed inset-0 z-[5] bg-transparent">
                <div class="absolute right-20 bottom-5 sm:right-24 sm:bottom-5">
                    <div class="flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200 shadow">
                        <span class="inline-flex h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                        Atualizando métricas...
                    </div>
                </div>
            </div>
        </transition>
</AuthenticatedLayout>
</template>



<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 240ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
