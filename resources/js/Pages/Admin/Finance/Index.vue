<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    CalendarClock,
    AlertTriangle,
    CheckCircle2,
    WalletCards,
    Banknote,
    Search,
    Filter,
    Plus,
    CreditCard,
    PlugZap,
    HandCoins,
    ShieldCheck,
    Pencil,
    Trash2,
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
});

const allowedTabs = new Set(['payables', 'receivables', 'payments']);
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
        window.history.replaceState(window.history.state, '', url.toString());
    }
};

const tabs = [
    {
        key: 'payables',
        label: 'Contas a pagar',
        description: 'Despesas, fornecedores e saídas previstas.',
        icon: WalletCards,
    },
    {
        key: 'receivables',
        label: 'Contas a receber',
        description: 'Cobranças, clientes e entradas previstas.',
        icon: Banknote,
    },
    {
        key: 'payments',
        label: 'Pagamentos',
        description: 'Gateway e formas para o PDV.',
        icon: CreditCard,
    },
];

const payablesStats = [
    { key: 'next_7', label: 'A vencer (7 dias)', value: 'R$ 0,00', icon: CalendarClock, tone: 'bg-blue-100 text-blue-700' },
    { key: 'late', label: 'Vencido', value: 'R$ 0,00', icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
    { key: 'paid', label: 'Pago no mês', value: 'R$ 0,00', icon: CheckCircle2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'projection', label: 'Saída projetada', value: 'R$ 0,00', icon: WalletCards, tone: 'bg-slate-100 text-slate-700' },
];

const receivablesStats = [
    { key: 'next_7', label: 'A receber (7 dias)', value: 'R$ 0,00', icon: CalendarClock, tone: 'bg-blue-100 text-blue-700' },
    { key: 'late', label: 'Atrasado', value: 'R$ 0,00', icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
    { key: 'received', label: 'Recebido no mês', value: 'R$ 0,00', icon: CheckCircle2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'default', label: 'Inadimplência', value: '0%', icon: Banknote, tone: 'bg-slate-100 text-slate-700' },
];

const payables = [];
const receivables = [];
const searchQuery = ref('');

const activeStats = computed(() => (activeTab.value === 'receivables' ? receivablesStats : payablesStats));
const activeRows = computed(() => {
    const rows = activeTab.value === 'receivables' ? receivables : payables;
    const query = String(searchQuery.value ?? '').trim().toLowerCase();

    if (!query) return rows;

    return rows.filter((item) => {
        const primary = String(item?.primary ?? '').toLowerCase();
        const reference = String(item?.reference ?? '').toLowerCase();
        const status = String(item?.status ?? '').toLowerCase();
        return primary.includes(query) || reference.includes(query) || status.includes(query);
    });
});

const searchPlaceholder = computed(() =>
    activeTab.value === 'receivables'
        ? 'Buscar por cliente ou pedido'
        : 'Buscar por fornecedor ou documento'
);

const filterLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Cobrança'
        : 'Vencimento'
);

const actionLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Novo recebível'
        : 'Lançar título'
);

const firstColumnLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Cliente'
        : 'Fornecedor'
);

const secondColumnLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Pedido'
        : 'Documento'
);

const emptyStateLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Nenhum título a receber cadastrado.'
        : 'Nenhum título a pagar cadastrado.'
);

const clearSearch = () => {
    searchQuery.value = '';
};

const paymentStats = computed(() => {
    const stats = props.paymentConfig?.stats ?? {};

    return [
        {
            key: 'gateways_total',
            label: 'Gateways',
            value: String(stats.gateways_total ?? 0),
            icon: PlugZap,
            tone: 'bg-blue-100 text-blue-700',
        },
        {
            key: 'gateways_active',
            label: 'Gateways ativos',
            value: String(stats.gateways_active ?? 0),
            icon: ShieldCheck,
            tone: 'bg-emerald-100 text-emerald-700',
        },
        {
            key: 'methods_total',
            label: 'Formas cadastradas',
            value: String(stats.methods_total ?? 0),
            icon: HandCoins,
            tone: 'bg-amber-100 text-amber-700',
        },
        {
            key: 'methods_active',
            label: 'Formas ativas',
            value: String(stats.methods_active ?? 0),
            icon: CreditCard,
            tone: 'bg-slate-100 text-slate-700',
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
});

const gatewayDeleteForm = useForm({});
const isEditingGateway = computed(() => Boolean(editingGateway.value?.id));

const openCreateGateway = () => {
    editingGateway.value = null;
    gatewayForm.reset();
    gatewayForm.clearErrors();
    gatewayForm.provider = 'manual';
    gatewayForm.name = '';
    gatewayForm.is_active = true;
    gatewayForm.is_default = false;
    gatewayForm.is_sandbox = true;
    gatewayModalOpen.value = true;
};

const openEditGateway = (gateway) => {
    editingGateway.value = gateway;
    gatewayForm.provider = String(gateway.provider ?? 'manual');
    gatewayForm.name = String(gateway.name ?? '');
    gatewayForm.is_active = Boolean(gateway.is_active);
    gatewayForm.is_default = Boolean(gateway.is_default);
    gatewayForm.is_sandbox = Boolean(gateway.is_sandbox);
    gatewayForm.clearErrors();
    gatewayModalOpen.value = true;
};

const closeGatewayModal = () => {
    gatewayModalOpen.value = false;
    editingGateway.value = null;
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

const openCreateMethod = () => {
    editingMethod.value = null;
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
    methodForm.sort_order = (methods.value?.length ?? 0) + 10;
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
</script>

<template>
    <Head title="Contas" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Contas">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-2xl border bg-white p-4 text-left shadow-sm transition"
                    :class="activeTab === tab.key ? 'border-slate-900 ring-1 ring-slate-900/10' : 'border-slate-200 hover:border-slate-300'"
                    @click="setActiveTab(tab.key)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ tab.label }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ tab.description }}</p>
                        </div>
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                            <component :is="tab.icon" class="h-4 w-4" />
                        </span>
                    </div>
                </button>
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
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl" :class="stat.tone">
                                <component :is="stat.icon" class="h-4 w-4" />
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
                            <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                                <Filter class="h-3.5 w-3.5" />
                                {{ filterLabel }}
                            </button>
                            <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 sm:w-auto">
                                <Plus class="h-3.5 w-3.5" />
                                {{ actionLabel }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-white">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">{{ firstColumnLabel }}</th>
                                    <th class="px-4 py-3">{{ secondColumnLabel }}</th>
                                    <th class="px-4 py-3">Vencimento</th>
                                    <th class="px-4 py-3">Valor</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody v-if="activeRows.length" class="divide-y divide-slate-100 bg-white">
                                <tr v-for="item in activeRows" :key="item.id">
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ item.primary }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ item.reference }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ item.due }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ item.value }}</td>
                                    <td class="px-4 py-3">{{ item.status }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!activeRows.length" class="px-4 py-8 text-center text-sm text-slate-500">
                            {{ emptyStateLabel }}
                        </div>
                    </div>
                </section>
            </template>
        </section>

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
                        <input
                            v-model="methodForm.fee_fixed"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="0,00"
                        >
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

