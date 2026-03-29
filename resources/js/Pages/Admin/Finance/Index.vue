<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { useBranding } from '@/branding';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    CalendarClock,
    AlertTriangle,
    CheckCircle2,
    WalletCards,
    Banknote,
    Search,
    Plus,
    CreditCard,
    List,
    LayoutGrid,
    PlugZap,
    HandCoins,
    ShieldCheck,
    Pencil,
    Trash2,
    FileText,
} from 'lucide-vue-next';

const props = defineProps({
    initialTab: {
        type: String,
        default: 'payables',
    },
    paymentConfig: {
        type: Object,
        default: () => ({
            gateways: [],
            methods: [],
            provider_options: [],
            stats: {},
        }),
    },
    financeEntries: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },
    financeStats: {
        type: Object,
        default: () => ({
            payables: {},
            receivables: {},
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            status: '',
        }),
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const { normalizeHex, withAlpha, secondaryColor } = useBranding();
const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const tabAccentColor = computed(() =>
    normalizeHex(currentContractor.value?.brand_primary_color || '', secondaryColor.value),
);
const financeUiStyles = computed(() => ({
    '--finance-tab-active': tabAccentColor.value,
    '--finance-tab-active-soft': withAlpha(tabAccentColor.value, 0.12),
    '--finance-tab-active-border': withAlpha(tabAccentColor.value, 0.28),
    '--finance-toggle-color-soft': withAlpha(secondaryColor.value, 0.16),
}));

const allowedTabs = new Set(['payables', 'receivables']);
const activeTab = ref(allowedTabs.has(props.initialTab) ? props.initialTab : 'payables');

watch(
    () => props.initialTab,
    (tab) => {
        activeTab.value = allowedTabs.has(tab) ? tab : 'payables';
    },
);

const setActiveTab = (tab) => {
    if (!allowedTabs.has(tab)) return;
    if (activeTab.value === tab) return;

    activeTab.value = tab;

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        url.searchParams.delete('page');
        window.history.replaceState(window.history.state, '', url.toString());
    }

    syncFinanceWithServer({ preferCache: true });
};

const TABLE_VIEW_STORAGE_KEY = 'veshop:table-view-mode';
const allowedTableViewModes = new Set(['list', 'cards']);
const tableViewMode = ref('list');

const normalizeTableViewMode = (value) => {
    const normalized = String(value ?? '').trim().toLowerCase();
    return allowedTableViewModes.has(normalized) ? normalized : 'list';
};

const persistTableViewMode = (mode) => {
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('data-table-view-mode', mode);
    }

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(TABLE_VIEW_STORAGE_KEY, mode);
    }
};

const setTableViewMode = (mode) => {
    const normalized = normalizeTableViewMode(mode);
    tableViewMode.value = normalized;
    persistTableViewMode(normalized);
};

onMounted(() => {
    const fromDom =
        typeof document !== 'undefined'
            ? document.documentElement.getAttribute('data-table-view-mode')
            : '';
    const fromStorage =
        typeof window !== 'undefined'
            ? window.localStorage.getItem(TABLE_VIEW_STORAGE_KEY)
            : '';

    setTableViewMode(normalizeTableViewMode(fromDom || fromStorage));
});

const tabs = [
    {
        key: 'payables',
        label: 'Contas a pagar',
        icon: WalletCards,
    },
    {
        key: 'receivables',
        label: 'Contas a receber',
        icon: Banknote,
    },
];

const searchQuery = ref(String(props.filters?.search ?? ''));
const selectedStatus = ref(String(props.filters?.status ?? ''));
const localFinanceEntries = ref(normalizeFinanceEntries(props.financeEntries));
const localFinanceStats = ref(normalizeFinanceStats(props.financeStats));
const localStatusOptions = ref(Array.isArray(props.statusOptions) ? props.statusOptions : []);
const financeCache = new Map();
const suppressFinanceFiltersWatch = ref(false);

function normalizeFinanceEntries(value) {
    const safe = value && typeof value === 'object' ? value : {};
    return {
        ...safe,
        data: Array.isArray(safe?.data) ? safe.data : [],
        links: Array.isArray(safe?.links) ? safe.links : [],
    };
}

function normalizeFinanceStats(value) {
    const safe = value && typeof value === 'object' ? value : {};
    return {
        payables: safe?.payables && typeof safe.payables === 'object' ? safe.payables : {},
        receivables: safe?.receivables && typeof safe.receivables === 'object' ? safe.receivables : {},
    };
}

function cloneValue(value) {
    if (typeof structuredClone === 'function') {
        try {
            return structuredClone(value);
        } catch {
            // fallback below
        }
    }

    return JSON.parse(JSON.stringify(value ?? null));
}

const buildFinanceCacheKey = (
    tab = activeTab.value,
    search = searchQuery.value,
    status = selectedStatus.value,
) => {
    const safeTab = allowedTabs.has(String(tab)) ? String(tab) : 'payables';
    const safeSearch = String(search ?? '').trim().toLowerCase();
    const safeStatus = String(status ?? '').trim().toLowerCase();

    return `${safeTab}|${safeSearch}|${safeStatus}`;
};

const cacheFinanceState = ({
    tab = activeTab.value,
    search = searchQuery.value,
    status = selectedStatus.value,
    entries = localFinanceEntries.value,
    stats = localFinanceStats.value,
    statusOptions = localStatusOptions.value,
} = {}) => {
    financeCache.set(buildFinanceCacheKey(tab, search, status), {
        entries: cloneValue(normalizeFinanceEntries(entries)),
        stats: cloneValue(normalizeFinanceStats(stats)),
        statusOptions: cloneValue(Array.isArray(statusOptions) ? statusOptions : []),
    });
};

const applyCachedFinanceState = (key) => {
    const snapshot = financeCache.get(key);
    if (!snapshot) return false;

    localFinanceEntries.value = normalizeFinanceEntries(cloneValue(snapshot.entries));
    localFinanceStats.value = normalizeFinanceStats(cloneValue(snapshot.stats));
    localStatusOptions.value = Array.isArray(snapshot.statusOptions)
        ? cloneValue(snapshot.statusOptions)
        : [];

    return true;
};

const clearFinanceCache = () => {
    financeCache.clear();
};

const activeRows = computed(() => (
    Array.isArray(localFinanceEntries.value?.data)
        ? localFinanceEntries.value.data
        : []
));
const paginationLinks = computed(() => (
    Array.isArray(localFinanceEntries.value?.links)
        ? localFinanceEntries.value.links
        : []
));
const payablesStats = computed(() => localFinanceStats.value?.payables ?? {});
const receivablesStats = computed(() => localFinanceStats.value?.receivables ?? {});

const activeStats = computed(() => {
    if (activeTab.value === 'receivables') {
        return [
            { key: 'next_7', label: 'A receber (7 dias)', value: String(receivablesStats.value.next_7 ?? 'R$ 0,00'), icon: CalendarClock, tone: 'text-slate-700' },
            { key: 'late', label: 'Atrasado', value: String(receivablesStats.value.late ?? 'R$ 0,00'), icon: AlertTriangle, tone: 'text-slate-700' },
            { key: 'received', label: 'Recebido no mês', value: String(receivablesStats.value.received ?? 'R$ 0,00'), icon: CheckCircle2, tone: 'text-slate-700' },
            { key: 'default_rate', label: 'Inadimplência', value: String(receivablesStats.value.default_rate ?? '0,0%'), icon: Banknote, tone: 'text-slate-700' },
        ];
    }

    return [
        { key: 'next_7', label: 'A vencer (7 dias)', value: String(payablesStats.value.next_7 ?? 'R$ 0,00'), icon: CalendarClock, tone: 'text-slate-700' },
        { key: 'late', label: 'Vencido', value: String(payablesStats.value.late ?? 'R$ 0,00'), icon: AlertTriangle, tone: 'text-slate-700' },
        { key: 'paid', label: 'Pago no mês', value: String(payablesStats.value.paid ?? 'R$ 0,00'), icon: CheckCircle2, tone: 'text-slate-700' },
        { key: 'projection', label: 'Saída projetada', value: String(payablesStats.value.projection ?? 'R$ 0,00'), icon: WalletCards, tone: 'text-slate-700' },
    ];
});

const searchPlaceholder = computed(() =>
    activeTab.value === 'receivables'
        ? 'Buscar por cliente ou referência'
        : 'Buscar por fornecedor ou documento'
);

const statusFilterOptions = computed(() => [
    { value: '', label: 'Todos os status' },
    ...(localStatusOptions.value ?? []),
]);

const actionLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Novo recebível'
        : 'Lançar título'
);

const firstColumnLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Cliente'
        : 'Fornecedor / Descrição'
);

const secondColumnLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Referência'
        : 'Documento'
);

const emptyStateLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Nenhum título a receber cadastrado.'
        : 'Nenhum título a pagar cadastrado.'
);

const statusBadgeClass = (statusKey) => {
    const safe = String(statusKey ?? '').trim().toLowerCase();
    if (safe === 'paid') return 'bg-emerald-100 text-emerald-700';
    if (safe === 'overdue') return 'bg-rose-100 text-rose-700';
    if (safe === 'cancelled') return 'bg-slate-200 text-slate-700';
    return 'bg-amber-100 text-amber-700';
};

const clearSearch = () => {
    searchQuery.value = '';
};

let financeFilterDebounceTimer = null;

const syncFinanceWithServer = ({ preferCache = false, force = false } = {}) => {
    if (activeTab.value === 'payments') return;

    const tab = activeTab.value;
    const search = String(searchQuery.value ?? '').trim();
    const status = String(selectedStatus.value ?? '').trim();
    const cacheKey = buildFinanceCacheKey(tab, search, status);

    if (preferCache && !force && applyCachedFinanceState(cacheKey)) {
        return;
    }

    router.get(
        route('admin.finance.index'),
        {
            tab,
            search: search || undefined,
            status: status || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['initialTab', 'filters', 'statusOptions', 'financeEntries', 'financeStats', 'paymentConfig'],
        },
    );
};

const scheduleSyncFinanceWithServer = () => {
    if (activeTab.value === 'payments') return;

    if (financeFilterDebounceTimer) {
        clearTimeout(financeFilterDebounceTimer);
    }

    financeFilterDebounceTimer = setTimeout(() => {
        syncFinanceWithServer({ preferCache: true });
    }, 280);
};

watch([searchQuery, selectedStatus], () => {
    if (suppressFinanceFiltersWatch.value) return;
    scheduleSyncFinanceWithServer();
});

watch(
    () => props.filters,
    (filters) => {
        const nextSearch = String(filters?.search ?? '');
        const nextStatus = String(filters?.status ?? '');

        suppressFinanceFiltersWatch.value = true;

        try {
            if (searchQuery.value !== nextSearch) {
                searchQuery.value = nextSearch;
            }

            if (selectedStatus.value !== nextStatus) {
                selectedStatus.value = nextStatus;
            }
        } finally {
            suppressFinanceFiltersWatch.value = false;
        }

        cacheFinanceState({
            tab: props.initialTab,
            search: nextSearch,
            status: nextStatus,
            entries: props.financeEntries,
            stats: props.financeStats,
            statusOptions: props.statusOptions,
        });
    },
    { deep: true, immediate: true },
);

watch(
    () => props.financeEntries,
    (entries) => {
        localFinanceEntries.value = normalizeFinanceEntries(entries);
        cacheFinanceState({
            tab: props.initialTab,
            search: props.filters?.search ?? searchQuery.value,
            status: props.filters?.status ?? selectedStatus.value,
            entries,
            stats: localFinanceStats.value,
            statusOptions: localStatusOptions.value,
        });
    },
    { deep: true, immediate: true },
);

watch(
    () => props.financeStats,
    (stats) => {
        localFinanceStats.value = normalizeFinanceStats(stats);
        cacheFinanceState({
            tab: props.initialTab,
            search: props.filters?.search ?? searchQuery.value,
            status: props.filters?.status ?? selectedStatus.value,
            entries: localFinanceEntries.value,
            stats,
            statusOptions: localStatusOptions.value,
        });
    },
    { deep: true, immediate: true },
);

watch(
    () => props.statusOptions,
    (options) => {
        localStatusOptions.value = Array.isArray(options) ? options : [];
        cacheFinanceState({
            tab: props.initialTab,
            search: props.filters?.search ?? searchQuery.value,
            status: props.filters?.status ?? selectedStatus.value,
            entries: localFinanceEntries.value,
            stats: localFinanceStats.value,
            statusOptions: localStatusOptions.value,
        });
    },
    { deep: true, immediate: true },
);

onBeforeUnmount(() => {
    if (financeFilterDebounceTimer) {
        clearTimeout(financeFilterDebounceTimer);
        financeFilterDebounceTimer = null;
    }
});

const paymentStats = computed(() => {
    const stats = props.paymentConfig?.stats ?? {};

    return [
        {
            key: 'gateways_total',
            label: 'Gateways',
            value: String(stats.gateways_total ?? 0),
            icon: PlugZap,
            tone: 'text-slate-700',
        },
        {
            key: 'gateways_active',
            label: 'Gateways ativos',
            value: String(stats.gateways_active ?? 0),
            icon: ShieldCheck,
            tone: 'text-slate-700',
        },
        {
            key: 'methods_total',
            label: 'Formas cadastradas',
            value: String(stats.methods_total ?? 0),
            icon: HandCoins,
            tone: 'text-slate-700',
        },
        {
            key: 'methods_active',
            label: 'Formas ativas',
            value: String(stats.methods_active ?? 0),
            icon: CreditCard,
            tone: 'text-slate-700',
        },
    ];
});

const gateways = computed(() => props.paymentConfig?.gateways ?? []);
const methods = computed(() => props.paymentConfig?.methods ?? []);
const providerOptions = computed(() => props.paymentConfig?.provider_options ?? []);

const providerLabelByValue = computed(() => {
    const map = new Map();
    for (const option of providerOptions.value) {
        map.set(String(option.value), String(option.label));
    }
    return map;
});

const providerLabel = (provider) => providerLabelByValue.value.get(String(provider)) ?? String(provider ?? '-');

const paymentMethodOptions = computed(() => [
    { value: '', label: 'Selecione uma forma' },
    ...methods.value.map((method) => ({
        value: method.id,
        label: method.name,
    })),
]);

const gatewaySelectOptions = computed(() => [
    { value: '', label: 'Sem gateway' },
    ...gateways.value.map((gateway) => ({
        value: gateway.id,
        label: gateway.name,
    })),
]);

const methodCodeOptions = [
    { value: 'pix', label: 'Pix' },
    { value: 'cash', label: 'Dinheiro' },
    { value: 'debit_card', label: 'Cartão de débito' },
    { value: 'credit_card', label: 'Cartão de crédito' },
    { value: 'installment', label: 'A prazo' },
];

const gatewayModalOpen = ref(false);
const editingGateway = ref(null);
const gatewayDeleteOpen = ref(false);
const gatewayToDelete = ref(null);

const gatewayForm = useForm({
    provider: 'manual',
    name: '',
    is_active: true,
    is_default: false,
    is_sandbox: true,
    mercado_pago_access_token: '',
    mercado_pago_webhook_secret: '',
});

const gatewayDeleteForm = useForm({});
const isEditingGateway = computed(() => Boolean(editingGateway.value?.id));
const gatewayConnectionLoading = ref(false);
const gatewayConnectionStatus = ref('idle');
const gatewayConnectionMessage = ref('');
const gatewayConnectionDetails = ref(null);

const gatewayConnectionToneClass = computed(() => {
    if (gatewayConnectionStatus.value === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (gatewayConnectionStatus.value === 'error') return 'border-rose-200 bg-rose-50 text-rose-700';

    return 'border-slate-200 bg-slate-50 text-slate-600';
});

const gatewayConnectionDetailsText = computed(() => {
    const details = gatewayConnectionDetails.value;
    if (!details || typeof details !== 'object') return '';

    if (String(details.provider ?? '') === 'mercado_pago') {
        const nickname = String(details.nickname ?? '').trim();
        const email = String(details.email ?? '').trim();
        const accountId = String(details.account_id ?? '').trim();
        const chunks = [nickname, email, accountId !== '' ? `ID ${accountId}` : ''].filter(Boolean);
        return chunks.join(' · ');
    }

    return '';
});

const resetGatewayConnectionFeedback = () => {
    gatewayConnectionStatus.value = 'idle';
    gatewayConnectionMessage.value = '';
    gatewayConnectionDetails.value = null;
};

const runGatewayConnectionTest = async () => {
    gatewayConnectionLoading.value = true;
    gatewayConnectionStatus.value = 'idle';
    gatewayConnectionMessage.value = '';
    gatewayConnectionDetails.value = null;

    try {
        const payload = {
            provider: gatewayForm.provider,
            is_sandbox: Boolean(gatewayForm.is_sandbox),
            gateway_id: editingGateway.value?.id ?? null,
            mercado_pago_access_token: gatewayForm.mercado_pago_access_token,
            mercado_pago_webhook_secret: gatewayForm.mercado_pago_webhook_secret,
        };

        let data = null;

        if (typeof window !== 'undefined' && window.axios) {
            const response = await window.axios.post(route('admin.finance.gateways.test'), payload, {
                headers: {
                    Accept: 'application/json',
                },
            });
            data = response?.data ?? null;
        } else {
            const csrf = typeof document !== 'undefined'
                ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
                : '';
            const response = await fetch(route('admin.finance.gateways.test'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
            });
            data = response.ok ? await response.json() : null;
        }

        if (!data || data.ok !== true) {
            gatewayConnectionStatus.value = 'error';
            gatewayConnectionMessage.value = String(data?.message ?? 'Não foi possível validar a conexão.');
            return;
        }

        gatewayConnectionStatus.value = 'success';
        gatewayConnectionMessage.value = String(data.message ?? 'Conexão validada com sucesso.');
        gatewayConnectionDetails.value = data.details ?? null;
    } catch (error) {
        const fallbackMessage = 'Não foi possível validar a conexão com o gateway.';
        const responseMessage = String(error?.response?.data?.message ?? '').trim();
        const message = responseMessage !== '' ? responseMessage : fallbackMessage;

        gatewayConnectionStatus.value = 'error';
        gatewayConnectionMessage.value = message;
    } finally {
        gatewayConnectionLoading.value = false;
    }
};

const openCreateGateway = () => {
    resetGatewayForm();
    editingGateway.value = null;
    resetGatewayConnectionFeedback();
    gatewayModalOpen.value = true;
};

const openEditGateway = (gateway) => {
    editingGateway.value = gateway;
    gatewayForm.provider = String(gateway.provider ?? 'manual');
    gatewayForm.name = String(gateway.name ?? '');
    gatewayForm.is_active = Boolean(gateway.is_active);
    gatewayForm.is_default = Boolean(gateway.is_default);
    gatewayForm.is_sandbox = Boolean(gateway.is_sandbox);
    gatewayForm.mercado_pago_access_token = '';
    gatewayForm.mercado_pago_webhook_secret = '';
    gatewayForm.clearErrors();
    resetGatewayConnectionFeedback();
    gatewayModalOpen.value = true;
};

const closeGatewayModal = () => {
    gatewayModalOpen.value = false;
    editingGateway.value = null;
    resetGatewayForm();
    resetGatewayConnectionFeedback();
};

const submitGateway = () => {
    if (isEditingGateway.value) {
        gatewayForm.put(route('admin.finance.gateways.update', editingGateway.value.id), {
            preserveScroll: true,
            onSuccess: closeGatewayModal,
        });
        return;
    }

    gatewayForm.post(route('admin.finance.gateways.store'), {
        preserveScroll: true,
        onSuccess: closeGatewayModal,
    });
};

const openDeleteGateway = (gateway) => {
    gatewayToDelete.value = gateway;
    gatewayDeleteOpen.value = true;
};

const closeDeleteGateway = () => {
    gatewayToDelete.value = null;
    gatewayDeleteOpen.value = false;
};

const removeGateway = () => {
    if (!gatewayToDelete.value?.id) return;

    gatewayDeleteForm.delete(route('admin.finance.gateways.destroy', gatewayToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteGateway,
    });
};

const methodModalOpen = ref(false);
const editingMethod = ref(null);
const methodDeleteOpen = ref(false);
const methodToDelete = ref(null);

const methodForm = useForm({
    payment_gateway_id: '',
    code: 'pix',
    name: '',
    is_active: true,
    is_default: false,
    allows_installments: false,
    max_installments: '',
    fee_fixed: '',
    fee_percent: '',
    sort_order: 0,
});

const methodDeleteForm = useForm({});
const isEditingMethod = computed(() => Boolean(editingMethod.value?.id));

const resetGatewayForm = () => {
    gatewayForm.transform((data) => data);
    gatewayForm.reset();
    gatewayForm.clearErrors();
    gatewayForm.provider = 'manual';
    gatewayForm.name = '';
    gatewayForm.is_active = true;
    gatewayForm.is_default = false;
    gatewayForm.is_sandbox = true;
    gatewayForm.mercado_pago_access_token = '';
    gatewayForm.mercado_pago_webhook_secret = '';
};

const resetMethodForm = (sortOrder = 0) => {
    methodForm.transform((data) => data);
    methodForm.reset();
    methodForm.clearErrors();
    methodForm.payment_gateway_id = '';
    methodForm.code = 'pix';
    methodForm.name = '';
    methodForm.is_active = true;
    methodForm.is_default = false;
    methodForm.allows_installments = false;
    methodForm.max_installments = '';
    methodForm.fee_fixed = '';
    methodForm.fee_percent = '';
    methodForm.sort_order = Number(sortOrder || 0);
};

const openCreateMethod = () => {
    editingMethod.value = null;
    resetMethodForm((methods.value?.length ?? 0) + 10);
    methodModalOpen.value = true;
};

const openEditMethod = (method) => {
    editingMethod.value = method;
    methodForm.payment_gateway_id = method.payment_gateway_id ?? '';
    methodForm.code = String(method.code ?? 'pix');
    methodForm.name = String(method.name ?? '');
    methodForm.is_active = Boolean(method.is_active);
    methodForm.is_default = Boolean(method.is_default);
    methodForm.allows_installments = Boolean(method.allows_installments);
    methodForm.max_installments = method.max_installments ?? '';
    methodForm.fee_fixed = method.fee_fixed ?? '';
    methodForm.fee_percent = method.fee_percent ?? '';
    methodForm.sort_order = Number(method.sort_order ?? 0);
    methodForm.clearErrors();
    methodModalOpen.value = true;
};

const closeMethodModal = () => {
    methodModalOpen.value = false;
    editingMethod.value = null;
    resetMethodForm();
};

const submitMethod = () => {
    const payload = {
        payment_gateway_id: methodForm.payment_gateway_id === '' ? null : Number(methodForm.payment_gateway_id),
        code: methodForm.code,
        name: methodForm.name,
        is_active: Boolean(methodForm.is_active),
        is_default: Boolean(methodForm.is_default),
        allows_installments: Boolean(methodForm.allows_installments),
        max_installments: methodForm.allows_installments && methodForm.max_installments !== ''
            ? Number(methodForm.max_installments)
            : null,
        fee_fixed: methodForm.fee_fixed === '' ? null : Number(methodForm.fee_fixed),
        fee_percent: methodForm.fee_percent === '' ? null : Number(methodForm.fee_percent),
        sort_order: Number(methodForm.sort_order || 0),
    };

    if (isEditingMethod.value) {
        methodForm.transform(() => payload).put(route('admin.finance.methods.update', editingMethod.value.id), {
            preserveScroll: true,
            onSuccess: closeMethodModal,
        });
        return;
    }

    methodForm.transform(() => payload).post(route('admin.finance.methods.store'), {
        preserveScroll: true,
        onSuccess: closeMethodModal,
    });
};

const openDeleteMethod = (method) => {
    methodToDelete.value = method;
    methodDeleteOpen.value = true;
};

const closeDeleteMethod = () => {
    methodToDelete.value = null;
    methodDeleteOpen.value = false;
};

const removeMethod = () => {
    if (!methodToDelete.value?.id) return;

    methodDeleteForm.delete(route('admin.finance.methods.destroy', methodToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteMethod,
    });
};

const asCurrency = (value) => {
    const parsed = Number(value ?? 0);
    return parsed.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};

const methodCodeLabel = (code) => {
    const found = methodCodeOptions.find((item) => item.value === String(code ?? ''));
    return found?.label ?? String(code ?? '-');
};

const isMercadoPagoGateway = computed(() => gatewayForm.provider === 'mercado_pago');

watch(
    () => [gatewayForm.provider, gatewayForm.is_sandbox],
    () => {
        resetGatewayConnectionFeedback();
    },
);

const entryModalOpen = ref(false);
const editingEntry = ref(null);
const entryDeleteOpen = ref(false);
const entryToDelete = ref(null);

const entryStatusFormOptions = [
    { value: 'pending', label: 'Em aberto' },
    { value: 'paid', label: 'Liquidado' },
    { value: 'cancelled', label: 'Cancelado' },
];

const entryForm = useForm({
    type: 'payable',
    counterparty_name: '',
    reference: '',
    amount: '',
    issue_date: '',
    due_date: '',
    status: 'pending',
    payment_method_id: '',
    paid_at: '',
    notes: '',
    document: null,
    remove_document: false,
});

const entryDeleteForm = useForm({});
const isEditingEntry = computed(() => Boolean(editingEntry.value?.id));
const entryTypeByActiveTab = computed(() => (
    activeTab.value === 'receivables' ? 'receivable' : 'payable'
));
const entryModalTitle = computed(() => {
    if (isEditingEntry.value) {
        return activeTab.value === 'receivables'
            ? 'Editar conta a receber'
            : 'Editar conta a pagar';
    }

    return activeTab.value === 'receivables'
        ? 'Nova conta a receber'
        : 'Nova conta a pagar';
});
const entryModalDescription = computed(() => (
    activeTab.value === 'receivables'
        ? 'Cadastre o recebimento com vencimento, status e documento.'
        : 'Cadastre o pagamento com vencimento, status e documento.'
));
const entryCounterpartyLabel = computed(() => (
    activeTab.value === 'receivables'
        ? 'Cliente'
        : 'Fornecedor / Descrição'
));
const entryCounterpartyPlaceholder = computed(() => (
    activeTab.value === 'receivables'
        ? 'Ex.: Cliente Silva'
        : 'Ex.: Aluguel da sede'
));
const entryReferenceLabel = computed(() => (
    activeTab.value === 'receivables'
        ? 'Referência'
        : 'Referência / Documento'
));

const resetEntryForm = () => {
    entryForm.transform((data) => data);
    entryForm.reset();
    entryForm.clearErrors();
    entryForm.type = entryTypeByActiveTab.value;
    entryForm.counterparty_name = '';
    entryForm.reference = '';
    entryForm.amount = '';
    entryForm.issue_date = '';
    entryForm.due_date = '';
    entryForm.status = 'pending';
    entryForm.payment_method_id = '';
    entryForm.paid_at = '';
    entryForm.notes = '';
    entryForm.document = null;
    entryForm.remove_document = false;
};

const openCreateEntry = () => {
    editingEntry.value = null;
    resetEntryForm();
    entryModalOpen.value = true;
};

const openEditEntry = (entry) => {
    editingEntry.value = entry;
    entryForm.type = entryTypeByActiveTab.value;
    entryForm.counterparty_name = String(entry.counterparty_name ?? entry.primary ?? '');
    entryForm.reference = String(entry.reference ?? '');
    entryForm.amount = Number(entry.amount_raw ?? 0).toFixed(2);
    entryForm.issue_date = String(entry.issue_date_raw ?? '');
    entryForm.due_date = String(entry.due_date_raw ?? '');
    entryForm.status = String(entry.status_key ?? 'pending') === 'overdue' ? 'pending' : String(entry.status_key ?? 'pending');
    entryForm.payment_method_id = entry.payment_method_id ?? '';
    entryForm.paid_at = String(entry.paid_at_raw ?? '').slice(0, 10);
    entryForm.notes = String(entry.notes ?? '');
    entryForm.remove_document = false;
    entryForm.document = null;
    entryForm.clearErrors();
    entryModalOpen.value = true;
};

const closeEntryModal = () => {
    entryModalOpen.value = false;
    editingEntry.value = null;
    resetEntryForm();
};

const onEntryDocumentChange = (event) => {
    const [file] = Array.from(event?.target?.files ?? []);
    entryForm.document = file ?? null;
    if (file) {
        entryForm.remove_document = false;
    }
};

const removeEntryDocument = () => {
    entryForm.document = null;
    entryForm.remove_document = true;
};

const submitEntry = () => {
    const payload = {
        type: entryTypeByActiveTab.value,
        counterparty_name: entryForm.counterparty_name,
        reference: String(entryForm.reference ?? '').trim() || null,
        amount: entryForm.amount === '' ? null : Number(entryForm.amount),
        issue_date: String(entryForm.issue_date ?? '').trim() || null,
        due_date: String(entryForm.due_date ?? '').trim() || null,
        status: entryForm.status,
        payment_method_id: entryForm.status === 'paid' && entryForm.payment_method_id !== ''
            ? Number(entryForm.payment_method_id)
            : null,
        paid_at: entryForm.status === 'paid'
            ? (String(entryForm.paid_at ?? '').trim() || null)
            : null,
        notes: String(entryForm.notes ?? '').trim() || null,
        remove_document: Boolean(entryForm.remove_document),
        document: entryForm.document ?? null,
    };

    if (isEditingEntry.value) {
        entryForm.transform(() => payload).post(route('admin.finance.entries.update', editingEntry.value.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                clearFinanceCache();
                closeEntryModal();
            },
        });
        return;
    }

    entryForm.transform(() => payload).post(route('admin.finance.entries.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            clearFinanceCache();
            closeEntryModal();
        },
    });
};

const openDeleteEntry = (entry) => {
    entryToDelete.value = entry;
    entryDeleteOpen.value = true;
};

const closeDeleteEntry = () => {
    entryToDelete.value = null;
    entryDeleteOpen.value = false;
};

const removeEntry = () => {
    if (!entryToDelete.value?.id) return;

    entryDeleteForm.delete(route('admin.finance.entries.destroy', entryToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            clearFinanceCache();
            closeDeleteEntry();
        },
    });
};

const filePreview = ref({
    open: false,
    file: null,
});

const pdfZoom = ref('page-fit');
const previewPaneClasses = 'h-full w-full rounded-xl border border-slate-200 bg-white';

const openFilePreview = (file) => {
    const publicUrl = String(file?.public_url ?? '').trim();
    if (publicUrl === '') return;

    filePreview.value = {
        open: true,
        file: {
            original_name: String(file?.original_name ?? file?.name ?? 'Documento'),
            public_url: publicUrl,
            mime_type: String(file?.mime_type ?? ''),
        },
    };

    pdfZoom.value = 'page-fit';
};

const closeFilePreview = () => {
    filePreview.value = {
        open: false,
        file: null,
    };
};

const previewKind = (file) => {
    const mimeType = String(file?.mime_type ?? '').toLowerCase();
    const originalName = String(file?.original_name ?? '').toLowerCase();
    const publicUrl = String(file?.public_url ?? '').toLowerCase();
    const target = `${originalName} ${publicUrl}`;

    if (mimeType.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|svg)(\?|#|$)/i.test(target)) {
        return 'image';
    }

    if (mimeType.includes('pdf') || /\.pdf(\?|#|$)/i.test(target)) {
        return 'pdf';
    }

    return 'other';
};

const setPdfZoom = (zoom) => {
    if (!['page-fit', 'page-width', '125', '200'].includes(String(zoom))) return;
    pdfZoom.value = String(zoom);
};

const pdfSrcFor = (file) => {
    const src = String(file?.public_url ?? '').trim();
    if (src === '') return '';

    const fragment = {
        'page-fit': 'view=Fit',
        'page-width': 'view=FitH',
        '125': 'zoom=125',
        '200': 'zoom=200',
    }[pdfZoom.value] || 'view=Fit';

    return `${src}#${fragment}`;
};

const pdfIframeKey = (file) => `${String(file?.public_url ?? '')}-${pdfZoom.value}`;
</script>

<template>
    <Head title="Contas" />

    <AuthenticatedLayout
        area="admin"
        header-variant="compact"
        header-title="Contas"
        :show-table-view-toggle="false"
    >
        <section class="space-y-4" :style="financeUiStyles">
            <div class="finance-tabs-shell">
                <div class="finance-tabs-track">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="finance-tab"
                        :class="activeTab === tab.key ? 'is-active' : ''"
                        @click="setActiveTab(tab.key)"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        <span class="truncate">{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <template v-if="activeTab === 'payments'">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="stat in paymentStats"
                        :key="`payment-stat-${stat.key}`"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-slate-500">{{ stat.label }}</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ stat.value }}</p>
                            </div>
                            <span class="veshop-stat-icon finance-stat-icon inline-flex h-10 w-10 items-center justify-center rounded-xl" :class="stat.tone">
                                <component :is="stat.icon" class="h-5 w-5" />
                            </span>
                        </div>
                    </article>
                </div>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Configurações de pagamento</h2>
                            <p class="text-xs text-slate-500">Defina gateway e formas aceitas no PDV.</p>
                        </div>
                        <div class="veshop-toolbar-actions lg:justify-end">
                            <button
                                type="button"
                                class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto"
                                @click="openCreateGateway"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                Novo gateway
                            </button>
                            <button
                                type="button"
                                class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 sm:w-auto"
                                @click="openCreateMethod"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                Nova forma
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 2xl:grid-cols-2">
                        <article class="rounded-xl border border-slate-200 bg-white">
                            <div class="border-b border-slate-200 px-4 py-3">
                                <h3 class="text-sm font-semibold text-slate-900">Gateways</h3>
                            </div>
                            <div class="p-4">
                                <div
                                    v-if="!gateways.length"
                                    class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                                >
                                    Nenhum gateway cadastrado.
                                </div>

                                <div v-else class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                    <article
                                        v-for="gateway in gateways"
                                        :key="`gateway-${gateway.id}`"
                                        class="min-w-0 rounded-2xl border border-slate-200 bg-slate-50/70 p-3 shadow-sm"
                                    >
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="truncate text-base font-semibold text-slate-900">{{ gateway.name }}</p>
                                                <p class="text-sm text-slate-600">{{ providerLabel(gateway.provider) }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span
                                                    class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                                    :class="gateway.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                                >
                                                    {{ gateway.is_active ? 'Ativo' : 'Inativo' }}
                                                </span>
                                                <span v-if="gateway.is_default" class="rounded-full bg-blue-100 px-2 py-1 text-[11px] font-semibold text-blue-700">
                                                    Padrão
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mt-3 grid gap-2 text-sm">
                                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">
                                                {{ gateway.is_sandbox ? 'Sandbox' : 'Produção' }}
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">
                                                {{ gateway.methods_count || 0 }} forma(s)
                                            </div>
                                            <div
                                                v-if="gateway.provider === 'mercado_pago'"
                                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                                            >
                                                Token: {{ gateway.credentials_status?.access_token_configured ? 'configurado' : 'pendente' }}
                                                · Webhook: {{ gateway.credentials_status?.webhook_secret_configured ? 'configurado' : 'pendente' }}
                                            </div>
                                            <div
                                                v-if="gateway.last_health_check_at"
                                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                                            >
                                                Último teste: {{ gateway.last_health_check_at }}
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:flex-none"
                                                @click="openEditGateway(gateway)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-white px-2 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 sm:flex-none"
                                                @click="openDeleteGateway(gateway)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                Excluir
                                            </button>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white">
                            <div class="border-b border-slate-200 px-4 py-3">
                                <h3 class="text-sm font-semibold text-slate-900">Formas de pagamento</h3>
                            </div>
                            <div class="p-4">
                                <div
                                    v-if="!methods.length"
                                    class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                                >
                                    Nenhuma forma de pagamento cadastrada.
                                </div>

                                <div v-else class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                    <article
                                        v-for="method in methods"
                                        :key="`method-${method.id}`"
                                        class="min-w-0 rounded-2xl border border-slate-200 bg-slate-50/70 p-3 shadow-sm"
                                    >
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="truncate text-base font-semibold text-slate-900">{{ method.name }}</p>
                                                <p class="text-sm text-slate-600">{{ methodCodeLabel(method.code) }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span
                                                    class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                                    :class="method.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                                >
                                                    {{ method.is_active ? 'Ativa' : 'Inativa' }}
                                                </span>
                                                <span v-if="method.is_default" class="rounded-full bg-blue-100 px-2 py-1 text-[11px] font-semibold text-blue-700">
                                                    Padrão
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mt-3 grid gap-2 text-sm">
                                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">
                                                {{ method.payment_gateway_name || 'Sem gateway' }}
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">
                                                {{ method.fee_percent !== null ? `${method.fee_percent}%` : '-' }} / {{ method.fee_fixed !== null ? asCurrency(method.fee_fixed) : '-' }}
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">
                                                {{ method.allows_installments ? `Até ${method.max_installments || '-'}x` : '-' }}
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:flex-none"
                                                @click="openEditMethod(method)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-white px-2 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 sm:flex-none"
                                                @click="openDeleteMethod(method)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                Excluir
                                            </button>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </template>

            <template v-else>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="stat in activeStats"
                        :key="`${activeTab}-${stat.key}`"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-slate-500">{{ stat.label }}</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ stat.value }}</p>
                            </div>
                            <span class="veshop-stat-icon finance-stat-icon inline-flex h-10 w-10 items-center justify-center rounded-xl" :class="stat.tone">
                                <component :is="stat.icon" class="h-5 w-5" />
                            </span>
                        </div>
                    </article>
                </div>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="searchPlaceholder"
                                class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                                aria-label="Limpar pesquisa"
                                @click="clearSearch"
                            >
                                x
                            </button>
                        </div>

                        <div class="veshop-toolbar-actions lg:justify-end">
                            <UiSelect
                                v-model="selectedStatus"
                                :options="statusFilterOptions"
                                button-class="min-w-[190px]"
                            />
                            <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 sm:w-auto" @click="openCreateEntry">
                                <Plus class="h-3.5 w-3.5" />
                                {{ actionLabel }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 flex justify-end">
                        <div class="veshop-table-view-toggle finance-table-view-toggle">
                            <button
                                type="button"
                                class="veshop-table-view-btn inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                :class="tableViewMode === 'list' ? 'is-active' : ''"
                                @click="setTableViewMode('list')"
                            >
                                <List class="h-3.5 w-3.5" />
                                Lista
                            </button>
                            <button
                                type="button"
                                class="veshop-table-view-btn inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                :class="tableViewMode === 'cards' ? 'is-active' : ''"
                                @click="setTableViewMode('cards')"
                            >
                                <LayoutGrid class="h-3.5 w-3.5" />
                                Cards
                            </button>
                        </div>
                    </div>

                    <div v-if="tableViewMode === 'list'" class="mt-4 rounded-xl border border-slate-200 bg-white">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">{{ firstColumnLabel }}</th>
                                    <th class="px-4 py-3">{{ secondColumnLabel }}</th>
                                    <th class="px-4 py-3">Vencimento</th>
                                    <th class="px-4 py-3">Valor</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody v-if="activeRows.length" class="divide-y divide-slate-100 bg-white">
                                <tr v-for="item in activeRows" :key="item.id">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ item.primary }}</p>
                                        <p class="text-[11px] text-slate-400">{{ item.reference || '' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        <div class="flex flex-col items-start gap-1">
                                            <button
                                                v-if="item.document_url"
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100"
                                                @click="openFilePreview({
                                                    original_name: item.document_name || 'Documento',
                                                    public_url: item.document_url,
                                                })"
                                            >
                                                <FileText class="h-3.5 w-3.5" />
                                                {{ item.document_name || 'Documento' }}
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ item.due }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ item.value }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusBadgeClass(item.status_key)">
                                            {{ item.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50"
                                                title="Editar"
                                                @click="openEditEntry(item)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-rose-200 p-2 text-rose-700 hover:bg-rose-50"
                                                title="Excluir"
                                                :disabled="!item.can_delete"
                                                @click="openDeleteEntry(item)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!activeRows.length" class="px-4 py-8 text-center text-sm text-slate-500">
                            {{ emptyStateLabel }}
                        </div>
                    </div>

                    <div v-else class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="item in activeRows"
                            :key="`finance-card-${item.id}`"
                            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ item.primary }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ item.reference || 'Sem referência' }}</p>
                                </div>
                                <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="statusBadgeClass(item.status_key)">
                                    {{ item.status }}
                                </span>
                            </div>

                            <div class="mt-3 grid gap-2 text-sm">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">
                                    Vencimento: {{ item.due }}
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-900 font-semibold">
                                    {{ item.value }}
                                </div>
                                <button
                                    v-if="item.document_url"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                    @click="openFilePreview({
                                        original_name: item.document_name || 'Documento',
                                        public_url: item.document_url,
                                    })"
                                >
                                    <FileText class="h-3.5 w-3.5" />
                                    {{ item.document_name || 'Documento' }}
                                </button>
                            </div>

                            <div class="mt-3 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="openEditEntry(item)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                    :disabled="!item.can_delete"
                                    @click="openDeleteEntry(item)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Excluir
                                </button>
                            </div>
                        </article>

                        <div v-if="!activeRows.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">
                            {{ emptyStateLabel }}
                        </div>
                    </div>

                    <PaginationLinks :links="paginationLinks" :min-links="4" />
                </section>
            </template>
        </section>

        <Modal :show="entryModalOpen" max-width="5xl" @close="closeEntryModal">
            <WizardModalFrame
                :title="entryModalTitle"
                :description="entryModalDescription"
                :steps="['Dados do lançamento']"
                :current-step="1"
                @close="closeEntryModal"
            >
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ entryCounterpartyLabel }}</label>
                        <input
                            v-model="entryForm.counterparty_name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            :placeholder="entryCounterpartyPlaceholder"
                        >
                        <p v-if="entryForm.errors.counterparty_name" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.counterparty_name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                        <UiSelect
                            v-model="entryForm.status"
                            :options="entryStatusFormOptions"
                            button-class="mt-1"
                        />
                        <p v-if="entryForm.errors.status" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.status }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ entryReferenceLabel }}</label>
                        <input
                            v-model="entryForm.reference"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: NF 1234"
                        >
                        <p v-if="entryForm.errors.reference" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.reference }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valor (R$)</label>
                        <BrlMoneyInput
                            v-model="entryForm.amount"
                            :allow-empty="false"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="R$ 0,00"
                        />
                        <p v-if="entryForm.errors.amount" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.amount }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data de emissão</label>
                        <input
                            v-model="entryForm.issue_date"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="entryForm.errors.issue_date" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.issue_date }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data de vencimento</label>
                        <input
                            v-model="entryForm.due_date"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="entryForm.errors.due_date" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.due_date }}</p>
                    </div>

                    <div v-if="entryForm.status === 'paid'">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data de baixa</label>
                        <input
                            v-model="entryForm.paid_at"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="entryForm.errors.paid_at" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.paid_at }}</p>
                    </div>

                    <div v-if="entryForm.status === 'paid'">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Forma de pagamento</label>
                        <UiSelect
                            v-model="entryForm.payment_method_id"
                            :options="paymentMethodOptions"
                            button-class="mt-1"
                        />
                        <p v-if="entryForm.errors.payment_method_id" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.payment_method_id }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</label>
                        <textarea
                            v-model="entryForm.notes"
                            rows="3"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Informações adicionais"
                        />
                        <p v-if="entryForm.errors.notes" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.notes }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documento (PDF/JPG/PNG/WEBP)</label>
                        <input
                            type="file"
                            accept=".pdf,.png,.jpg,.jpeg,.webp"
                            class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border file:border-slate-200 file:bg-slate-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700"
                            @change="onEntryDocumentChange"
                        >
                        <p v-if="entryForm.errors.document" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.document }}</p>

                        <div v-if="isEditingEntry && editingEntry?.document_url" class="mt-2 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                @click="openFilePreview({
                                    original_name: editingEntry.document_name || 'Documento atual',
                                    public_url: editingEntry.document_url,
                                })"
                            >
                                <FileText class="h-3.5 w-3.5" />
                                {{ editingEntry.document_name || 'Documento atual' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                @click="removeEntryDocument"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                Remover documento atual
                            </button>
                        </div>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeEntryModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="entryForm.processing"
                            @click="submitEntry"
                        >
                            {{ entryForm.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <Modal :show="gatewayModalOpen" max-width="5xl" @close="closeGatewayModal">
            <WizardModalFrame
                :title="isEditingGateway ? 'Editar gateway' : 'Novo gateway'"
                description="Configure a conexão do gateway para operações do PDV."
                :steps="['Dados do gateway']"
                :current-step="1"
                @close="closeGatewayModal"
            >
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Provedor</label>
                        <UiSelect
                            v-model="gatewayForm.provider"
                            :options="providerOptions"
                            button-class="mt-1"
                        />
                        <p v-if="gatewayForm.errors.provider" class="mt-1 text-xs text-rose-600">{{ gatewayForm.errors.provider }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input
                            v-model="gatewayForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Mercado Pago principal"
                        >
                        <p v-if="gatewayForm.errors.name" class="mt-1 text-xs text-rose-600">{{ gatewayForm.errors.name }}</p>
                    </div>
                </div>

                <div v-if="isMercadoPagoGateway" class="mt-3 grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Access token Mercado Pago</label>
                        <input
                            v-model="gatewayForm.mercado_pago_access_token"
                            type="password"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            :placeholder="isEditingGateway ? 'Deixe em branco para manter o token atual' : 'APP_USR-...'"
                            autocomplete="off"
                        >
                        <p v-if="gatewayForm.errors.mercado_pago_access_token" class="mt-1 text-xs text-rose-600">{{ gatewayForm.errors.mercado_pago_access_token }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Token do webhook</label>
                        <input
                            v-model="gatewayForm.mercado_pago_webhook_secret"
                            type="password"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            :placeholder="isEditingGateway ? 'Deixe em branco para manter o token atual' : 'Crie um token forte para validar notificações'"
                            autocomplete="off"
                        >
                        <p v-if="gatewayForm.errors.mercado_pago_webhook_secret" class="mt-1 text-xs text-rose-600">{{ gatewayForm.errors.mercado_pago_webhook_secret }}</p>
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 md:w-fit"
                            :disabled="gatewayConnectionLoading"
                            @click="runGatewayConnectionTest"
                        >
                            {{ gatewayConnectionLoading ? 'Testando conexão...' : 'Testar conexão Mercado Pago' }}
                        </button>
                        <div
                            v-if="gatewayConnectionMessage"
                            class="rounded-xl border px-3 py-2 text-xs font-semibold"
                            :class="gatewayConnectionToneClass"
                        >
                            <p>{{ gatewayConnectionMessage }}</p>
                            <p v-if="gatewayConnectionDetailsText" class="mt-1 text-[11px] font-medium opacity-90">
                                {{ gatewayConnectionDetailsText }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="gatewayForm.is_active" type="checkbox" class="rounded border-slate-300">
                        Ativo
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="gatewayForm.is_default" type="checkbox" class="rounded border-slate-300">
                        Padrão
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="gatewayForm.is_sandbox" type="checkbox" class="rounded border-slate-300">
                        Sandbox
                    </label>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeGatewayModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="gatewayForm.processing"
                            @click="submitGateway"
                        >
                            {{ gatewayForm.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <Modal :show="methodModalOpen" max-width="5xl" @close="closeMethodModal">
            <WizardModalFrame
                :title="isEditingMethod ? 'Editar forma de pagamento' : 'Nova forma de pagamento'"
                description="Defina comportamento da forma no PDV."
                :steps="['Dados da forma']"
                :current-step="1"
                @close="closeMethodModal"
            >
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gateway (opcional)</label>
                        <UiSelect
                            v-model="methodForm.payment_gateway_id"
                            :options="gatewaySelectOptions"
                            button-class="mt-1"
                        />
                        <p v-if="methodForm.errors.payment_gateway_id" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.payment_gateway_id }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Código</label>
                        <UiSelect
                            v-model="methodForm.code"
                            :options="methodCodeOptions"
                            button-class="mt-1"
                        />
                        <p v-if="methodForm.errors.code" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.code }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome de exibição</label>
                        <input
                            v-model="methodForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Cartão da loja"
                        >
                        <p v-if="methodForm.errors.name" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ordem</label>
                        <input
                            v-model="methodForm.sort_order"
                            type="number"
                            min="0"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="methodForm.errors.sort_order" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.sort_order }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Taxa fixa (R$)</label>
                        <BrlMoneyInput
                            v-model="methodForm.fee_fixed"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="R$ 0,00"
                        />
                        <p v-if="methodForm.errors.fee_fixed" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.fee_fixed }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Taxa percentual (%)</label>
                        <input
                            v-model="methodForm.fee_percent"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="0,00"
                        >
                        <p v-if="methodForm.errors.fee_percent" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.fee_percent }}</p>
                    </div>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="methodForm.is_active" type="checkbox" class="rounded border-slate-300">
                        Ativa
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="methodForm.is_default" type="checkbox" class="rounded border-slate-300">
                        Padrão
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="methodForm.allows_installments" type="checkbox" class="rounded border-slate-300">
                        Permite parcelamento
                    </label>
                </div>

                <div v-if="methodForm.allows_installments" class="mt-3">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Máximo de parcelas</label>
                    <input
                        v-model="methodForm.max_installments"
                        type="number"
                        min="2"
                        max="24"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                    >
                    <p v-if="methodForm.errors.max_installments" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.max_installments }}</p>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeMethodModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="methodForm.processing"
                            @click="submitMethod"
                        >
                            {{ methodForm.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <Modal :show="filePreview.open" max-width="6xl" @close="closeFilePreview">
            <div class="flex w-[calc(100vw-1rem)] max-w-[96vw] flex-col gap-4 rounded-3xl bg-white p-3 sm:w-full sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ filePreview.file?.original_name }}</h3>
                        <p class="text-xs text-slate-500">Pré-visualização rápida do arquivo ingerido.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="previewKind(filePreview.file) === 'pdf'">
                            <div class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 ring-1 ring-slate-200">
                                <button type="button" class="rounded-full px-2 py-1 text-xs font-semibold" :class="pdfZoom === 'page-fit' ? 'bg-slate-200 text-slate-900' : 'text-slate-700 hover:bg-slate-200'" @click="setPdfZoom('page-fit')">Página</button>
                                <button type="button" class="rounded-full px-2 py-1 text-xs font-semibold" :class="pdfZoom === 'page-width' ? 'bg-slate-200 text-slate-900' : 'text-slate-700 hover:bg-slate-200'" @click="setPdfZoom('page-width')">Largura</button>
                                <button type="button" class="rounded-full px-2 py-1 text-xs font-semibold" :class="pdfZoom === '125' ? 'bg-slate-200 text-slate-900' : 'text-slate-700 hover:bg-slate-200'" @click="setPdfZoom('125')">125%</button>
                                <button type="button" class="rounded-full px-2 py-1 text-xs font-semibold" :class="pdfZoom === '200' ? 'bg-slate-200 text-slate-900' : 'text-slate-700 hover:bg-slate-200'" @click="setPdfZoom('200')">200%</button>
                            </div>
                        </template>

                        <a
                            v-if="filePreview.file?.public_url"
                            :href="filePreview.file?.public_url"
                            target="_blank"
                            rel="noopener"
                            class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500"
                        >Abrir em nova aba</a>

                        <button
                            type="button"
                            class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                            @click="closeFilePreview"
                        >Fechar</button>
                    </div>
                </div>

                <div class="scroll-area file-preview-scroll rounded-2xl border border-slate-200 bg-slate-50/60 p-2 sm:p-4">
                    <template v-if="previewKind(filePreview.file) === 'image'">
                        <img :src="filePreview.file?.public_url" alt="Pré-visualização do arquivo" :class="previewPaneClasses + ' block object-contain'" />
                    </template>

                    <template v-else-if="previewKind(filePreview.file) === 'pdf'">
                        <iframe :key="pdfIframeKey(filePreview.file)" :src="pdfSrcFor(filePreview.file)" :class="previewPaneClasses" title="Pré-visualização do PDF" />
                    </template>

                    <template v-else>
                        <div class="space-y-3 text-sm text-slate-600">
                            <p>Não foi possível renderizar este formato aqui.</p>
                            <a
                                v-if="filePreview.file?.public_url"
                                :href="filePreview.file?.public_url"
                                class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-500"
                                target="_blank"
                                rel="noopener"
                            >Baixar arquivo</a>
                        </div>
                    </template>
                </div>
            </div>
        </Modal>

        <DeleteConfirmModal
            :show="entryDeleteOpen"
            title="Excluir lançamento"
            message="Tem certeza que deseja excluir este lançamento financeiro?"
            :item-label="entryToDelete?.primary ? `Lançamento: ${entryToDelete.primary}` : ''"
            :processing="entryDeleteForm.processing"
            @close="closeDeleteEntry"
            @confirm="removeEntry"
        />

        <DeleteConfirmModal
            :show="gatewayDeleteOpen"
            title="Excluir gateway"
            message="Tem certeza que deseja excluir este gateway?"
            :item-label="gatewayToDelete?.name ? `Gateway: ${gatewayToDelete.name}` : ''"
            :processing="gatewayDeleteForm.processing"
            @close="closeDeleteGateway"
            @confirm="removeGateway"
        />

        <DeleteConfirmModal
            :show="methodDeleteOpen"
            title="Excluir forma de pagamento"
            message="Tem certeza que deseja excluir esta forma de pagamento?"
            :item-label="methodToDelete?.name ? `Forma: ${methodToDelete.name}` : ''"
            :processing="methodDeleteForm.processing"
            @close="closeDeleteMethod"
            @confirm="removeMethod"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.finance-tabs-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.finance-tabs-shell::-webkit-scrollbar {
    height: 6px;
}

.finance-tabs-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.finance-tabs-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.finance-tab {
    display: inline-flex;
    align-items: center;
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

.finance-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.finance-tab.is-active {
    border-color: var(--finance-tab-active-border);
    background: var(--finance-tab-active);
    color: #ffffff;
}

.finance-table-view-toggle {
    border-color: var(--finance-toggle-color-soft);
    background: #ffffff;
}

.finance-stat-icon {
    background: #f1f5f9;
}

.scroll-area {
    overflow: auto;
}

.file-preview-scroll {
    min-height: 18rem;
    height: min(72vh, calc(100dvh - 220px));
}

.scroll-area::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.scroll-area::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

@media (min-width: 1024px) {
    .file-preview-scroll {
        height: min(78vh, calc(100dvh - 180px));
    }
}
</style>
