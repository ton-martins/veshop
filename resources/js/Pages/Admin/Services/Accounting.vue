<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { useBranding } from '@/branding';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { CircleDollarSign, CalendarClock, Eye, FileText, Plus, Pencil, Trash2, Upload } from 'lucide-vue-next';

const props = defineProps({
    clients: { type: Array, default: () => [] },
    fees: { type: Array, default: () => [] },
    obligations: { type: Array, default: () => [] },
    documents: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    moduleAccess: { type: Object, default: () => ({ finance: false, tasks: false, documents: false }) },
    feeStatusOptions: { type: Array, default: () => [] },
    obligationStatusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    documentStatusOptions: { type: Array, default: () => [] },
});

const page = usePage();
const { normalizeHex, withAlpha, secondaryColor } = useBranding();
const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const tabAccentColor = computed(() =>
    normalizeHex(currentContractor.value?.brand_primary_color || '', secondaryColor.value),
);
const accountingUiStyles = computed(() => ({
    '--accounting-tab-active': tabAccentColor.value,
    '--accounting-tab-active-soft': withAlpha(tabAccentColor.value, 0.12),
    '--accounting-tab-active-border': withAlpha(tabAccentColor.value, 0.28),
}));

const activeTab = ref('fees');
const tabs = [
    { key: 'fees', label: 'Honorários' },
    { key: 'obligations', label: 'Obrigações' },
    { key: 'documents', label: 'Documentos' },
];

const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const clientOptions = computed(() => ([
    { value: '', label: 'Sem cliente' },
    ...(props.clients ?? []).map((client) => ({ value: client.id, label: client.name })),
]));

const statsCards = computed(() => [
    { key: 'fees_pending', label: 'Honorários pendentes', value: asCurrency(props.stats?.fees_pending ?? 0), icon: CircleDollarSign, tone: 'text-slate-700' },
    { key: 'fees_received_month', label: 'Recebido no mês', value: asCurrency(props.stats?.fees_received_month ?? 0), icon: CircleDollarSign, tone: 'text-slate-700' },
    { key: 'obligations_due', label: 'Obrigações próximas', value: String(props.stats?.obligations_due ?? 0), icon: CalendarClock, tone: 'text-slate-700' },
    { key: 'documents_pending', label: 'Documentos pendentes', value: String(props.stats?.documents_pending ?? 0), icon: FileText, tone: 'text-slate-700' },
]);

const hasClients = computed(() => (props.clients ?? []).length > 0);
const sectionTitle = computed(() => {
    if (activeTab.value === 'obligations') return 'Controle de obrigações';
    if (activeTab.value === 'documents') return 'Solicitações de documentos';

    return 'Lançamentos de honorários';
});
const sectionSubtitle = computed(() => {
    if (activeTab.value === 'obligations') return 'Acompanhe entregas, prazos e prioridades por cliente.';
    if (activeTab.value === 'documents') return 'Controle pendências e recebimento de documentos dos clientes.';

    return 'Registre cobranças, vencimentos e liquidações mensais.';
});
const canCreateCurrentTab = computed(() => {
    if (activeTab.value === 'obligations') return Boolean(props.moduleAccess?.tasks);
    if (activeTab.value === 'documents') return Boolean(props.moduleAccess?.documents);

    return Boolean(props.moduleAccess?.finance);
});
const createButtonLabel = computed(() => {
    if (activeTab.value === 'obligations') return 'Nova obrigação';
    if (activeTab.value === 'documents') return 'Novo documento';

    return 'Novo honorário';
});
const openCreateByActiveTab = () => {
    if (!canCreateCurrentTab.value) return;

    if (activeTab.value === 'obligations') {
        openObligationCreate();
        return;
    }

    if (activeTab.value === 'documents') {
        openDocumentCreate();
        return;
    }

    openFeeCreate();
};

const feeModalOpen = ref(false);
const editingFee = ref(null);
const feeForm = useForm({
    client_id: '',
    reference_label: '',
    due_date: '',
    amount: '0.00',
    paid_amount: '0.00',
    status: props.feeStatusOptions?.[0]?.value ?? 'pending',
    paid_at: '',
    notes: '',
});

const obligationModalOpen = ref(false);
const editingObligation = ref(null);
const obligationForm = useForm({
    client_id: '',
    title: '',
    obligation_type: '',
    competence_date: '',
    due_date: '',
    status: props.obligationStatusOptions?.[0]?.value ?? 'pending',
    priority: props.priorityOptions?.[1]?.value ?? 'normal',
    completed_at: '',
    notes: '',
});

const documentModalOpen = ref(false);
const editingDocument = ref(null);
const documentFormFileInput = ref(null);
const documentForm = useForm({
    client_id: '',
    title: '',
    document_type: '',
    due_date: '',
    status: props.documentStatusOptions?.[0]?.value ?? 'pending',
    received_at: '',
    notes: '',
    version_file: null,
    version_notes: '',
});

const deleteModalOpen = ref(false);
const deleteTarget = ref({ type: '', id: null, label: '' });
const deleteForm = useForm({});
const documentUploadModalOpen = ref(false);
const documentUploadTarget = ref(null);
const documentUploadFileInput = ref(null);
const documentUploadForm = useForm({
    version_file: null,
    version_notes: '',
});
const documentPreviewModalOpen = ref(false);
const documentPreviewData = ref(null);

const feeFilters = ref({
    search: '',
    client_id: '',
    status: '',
    due_from: '',
    due_to: '',
});
const obligationFilters = ref({
    search: '',
    client_id: '',
    status: '',
    due_from: '',
    due_to: '',
});
const documentFilters = ref({
    search: '',
    client_id: '',
    status: '',
    due_from: '',
    due_to: '',
});

const normalizeFilterText = (value) =>
    String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

const normalizedDate = (value) => {
    const safe = String(value ?? '').trim();
    if (safe === '') return '';
    return safe.slice(0, 10);
};

const isDateWithinRange = (dateValue, fromDate, toDate) => {
    const target = normalizedDate(dateValue);
    if (target === '') return true;

    const from = normalizedDate(fromDate);
    if (from !== '' && target < from) return false;

    const to = normalizedDate(toDate);
    if (to !== '' && target > to) return false;

    return true;
};

const formatDateBR = (value) => {
    const safe = normalizedDate(value);
    if (safe === '') return '-';

    const [year, month, day] = safe.split('-');
    if (!year || !month || !day) return '-';
    return `${day}/${month}/${year}`;
};

const statusFilterOptions = (options) => ([
    { value: '', label: 'Todos' },
    ...(Array.isArray(options) ? options : []),
]);

const feeStatusFilterOptions = computed(() => statusFilterOptions(props.feeStatusOptions));
const obligationStatusFilterOptions = computed(() => statusFilterOptions(props.obligationStatusOptions));
const documentStatusFilterOptions = computed(() => statusFilterOptions(props.documentStatusOptions));

const filteredFees = computed(() => {
    const term = normalizeFilterText(feeFilters.value.search);

    return (props.fees ?? []).filter((entry) => {
        const clientMatch = feeFilters.value.client_id === ''
            || String(entry.client_id ?? '') === String(feeFilters.value.client_id);
        const statusMatch = feeFilters.value.status === ''
            || String(entry.status ?? '') === String(feeFilters.value.status);
        const dateMatch = isDateWithinRange(entry.due_date, feeFilters.value.due_from, feeFilters.value.due_to);

        if (!clientMatch || !statusMatch || !dateMatch) return false;
        if (term === '') return true;

        const haystack = normalizeFilterText([
            entry.client_name,
            entry.reference_label,
            entry.notes,
        ].join(' '));

        return haystack.includes(term);
    });
});

const filteredObligations = computed(() => {
    const term = normalizeFilterText(obligationFilters.value.search);

    return (props.obligations ?? []).filter((entry) => {
        const clientMatch = obligationFilters.value.client_id === ''
            || String(entry.client_id ?? '') === String(obligationFilters.value.client_id);
        const statusMatch = obligationFilters.value.status === ''
            || String(entry.status ?? '') === String(obligationFilters.value.status);
        const dateMatch = isDateWithinRange(entry.due_date, obligationFilters.value.due_from, obligationFilters.value.due_to);

        if (!clientMatch || !statusMatch || !dateMatch) return false;
        if (term === '') return true;

        const haystack = normalizeFilterText([
            entry.client_name,
            entry.title,
            entry.obligation_type,
            entry.notes,
        ].join(' '));

        return haystack.includes(term);
    });
});

const filteredDocuments = computed(() => {
    const term = normalizeFilterText(documentFilters.value.search);

    return (props.documents ?? []).filter((entry) => {
        const clientMatch = documentFilters.value.client_id === ''
            || String(entry.client_id ?? '') === String(documentFilters.value.client_id);
        const statusMatch = documentFilters.value.status === ''
            || String(entry.status ?? '') === String(documentFilters.value.status);
        const dateMatch = isDateWithinRange(entry.due_date, documentFilters.value.due_from, documentFilters.value.due_to);

        if (!clientMatch || !statusMatch || !dateMatch) return false;
        if (term === '') return true;

        const haystack = normalizeFilterText([
            entry.client_name,
            entry.title,
            entry.document_type,
            entry.protocol_code,
            entry.notes,
        ].join(' '));

        return haystack.includes(term);
    });
});

const clearFeeFilters = () => {
    feeFilters.value = { search: '', client_id: '', status: '', due_from: '', due_to: '' };
};

const clearObligationFilters = () => {
    obligationFilters.value = { search: '', client_id: '', status: '', due_from: '', due_to: '' };
};

const clearDocumentFilters = () => {
    documentFilters.value = { search: '', client_id: '', status: '', due_from: '', due_to: '' };
};

const openDelete = (type, id, label) => {
    deleteTarget.value = { type, id, label };
    deleteModalOpen.value = true;
};

const closeDelete = () => {
    deleteModalOpen.value = false;
    deleteTarget.value = { type: '', id: null, label: '' };
};

const openFeeCreate = () => {
    editingFee.value = null;
    feeForm.reset();
    feeForm.clearErrors();
    feeForm.status = props.feeStatusOptions?.[0]?.value ?? 'pending';
    feeModalOpen.value = true;
};

const openFeeEdit = (entry) => {
    editingFee.value = entry;
    feeForm.client_id = entry.client_id ?? '';
    feeForm.reference_label = entry.reference_label ?? '';
    feeForm.due_date = entry.due_date ?? '';
    feeForm.amount = Number(entry.amount ?? 0).toFixed(2);
    feeForm.paid_amount = Number(entry.paid_amount ?? 0).toFixed(2);
    feeForm.status = entry.status ?? (props.feeStatusOptions?.[0]?.value ?? 'pending');
    feeForm.paid_at = entry.paid_at ?? '';
    feeForm.notes = entry.notes ?? '';
    feeForm.clearErrors();
    feeModalOpen.value = true;
};

const openObligationCreate = () => {
    editingObligation.value = null;
    obligationForm.reset();
    obligationForm.clearErrors();
    obligationForm.status = props.obligationStatusOptions?.[0]?.value ?? 'pending';
    obligationForm.priority = props.priorityOptions?.[1]?.value ?? 'normal';
    obligationModalOpen.value = true;
};

const openObligationEdit = (entry) => {
    editingObligation.value = entry;
    obligationForm.client_id = entry.client_id ?? '';
    obligationForm.title = entry.title ?? '';
    obligationForm.obligation_type = entry.obligation_type ?? '';
    obligationForm.competence_date = entry.competence_date ?? '';
    obligationForm.due_date = entry.due_date ?? '';
    obligationForm.status = entry.status ?? (props.obligationStatusOptions?.[0]?.value ?? 'pending');
    obligationForm.priority = entry.priority ?? (props.priorityOptions?.[1]?.value ?? 'normal');
    obligationForm.completed_at = entry.completed_at ?? '';
    obligationForm.notes = entry.notes ?? '';
    obligationForm.clearErrors();
    obligationModalOpen.value = true;
};

const openDocumentCreate = () => {
    editingDocument.value = null;
    documentForm.reset();
    documentForm.clearErrors();
    documentForm.status = props.documentStatusOptions?.[0]?.value ?? 'pending';
    documentForm.version_file = null;
    documentForm.version_notes = '';
    if (documentFormFileInput.value) {
        documentFormFileInput.value.value = '';
    }
    documentModalOpen.value = true;
};

const openDocumentEdit = (entry) => {
    editingDocument.value = entry;
    documentForm.client_id = entry.client_id ?? '';
    documentForm.title = entry.title ?? '';
    documentForm.document_type = entry.document_type ?? '';
    documentForm.due_date = entry.due_date ?? '';
    documentForm.status = entry.status ?? (props.documentStatusOptions?.[0]?.value ?? 'pending');
    documentForm.received_at = entry.received_at ?? '';
    documentForm.notes = entry.notes ?? '';
    documentForm.version_file = null;
    documentForm.version_notes = '';
    if (documentFormFileInput.value) {
        documentFormFileInput.value.value = '';
    }
    documentForm.clearErrors();
    documentModalOpen.value = true;
};

const submitFee = () => {
    if (editingFee.value?.id) {
        feeForm.put(route('admin.services.accounting.fees.update', editingFee.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                feeModalOpen.value = false;
                editingFee.value = null;
                feeForm.reset();
            },
        });
        return;
    }

    feeForm.post(route('admin.services.accounting.fees.store'), {
        preserveScroll: true,
        onSuccess: () => {
            feeModalOpen.value = false;
            feeForm.reset();
        },
    });
};

const submitObligation = () => {
    if (editingObligation.value?.id) {
        obligationForm.put(route('admin.services.accounting.obligations.update', editingObligation.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                obligationModalOpen.value = false;
                editingObligation.value = null;
                obligationForm.reset();
            },
        });
        return;
    }

    obligationForm.post(route('admin.services.accounting.obligations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            obligationModalOpen.value = false;
            obligationForm.reset();
        },
    });
};

const submitDocument = () => {
    if (editingDocument.value?.id) {
        documentForm.put(route('admin.services.accounting.documents.update', editingDocument.value.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                documentModalOpen.value = false;
                editingDocument.value = null;
                documentForm.reset();
                documentForm.version_file = null;
                documentForm.version_notes = '';
                if (documentFormFileInput.value) {
                    documentFormFileInput.value.value = '';
                }
            },
        });
        return;
    }

    documentForm.post(route('admin.services.accounting.documents.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            documentModalOpen.value = false;
            documentForm.reset();
            documentForm.version_file = null;
            documentForm.version_notes = '';
            if (documentFormFileInput.value) {
                documentFormFileInput.value.value = '';
            }
        },
    });
};

const onDocumentFormFileChange = (event) => {
    documentForm.version_file = event?.target?.files?.[0] ?? null;
};

const latestDocumentVersion = (entry) => {
    const versions = Array.isArray(entry?.versions) ? entry.versions : [];
    return versions.find((version) => String(version?.file_url ?? '').trim() !== '') ?? null;
};

const openDocumentUpload = (entry) => {
    documentUploadTarget.value = entry ?? null;
    documentUploadForm.reset();
    documentUploadForm.clearErrors();
    if (documentUploadFileInput.value) {
        documentUploadFileInput.value.value = '';
    }
    documentUploadModalOpen.value = true;
};

const closeDocumentUpload = () => {
    documentUploadModalOpen.value = false;
    documentUploadTarget.value = null;
    documentUploadForm.reset();
    documentUploadForm.clearErrors();
    if (documentUploadFileInput.value) {
        documentUploadFileInput.value.value = '';
    }
};

const onDocumentUploadFileChange = (event) => {
    documentUploadForm.version_file = event?.target?.files?.[0] ?? null;
};

const submitDocumentUpload = () => {
    if (!documentUploadTarget.value?.id) return;

    documentUploadForm.post(route('admin.services.accounting.documents.versions.store', documentUploadTarget.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeDocumentUpload,
    });
};

const openDocumentPreview = (entry) => {
    const latestVersion = latestDocumentVersion(entry);
    if (!latestVersion?.file_url) return;

    documentPreviewData.value = {
        title: String(entry?.title ?? 'Documento'),
        file_name: String(latestVersion.file_name ?? 'Documento'),
        file_url: String(latestVersion.file_url ?? ''),
        uploaded_at: String(latestVersion.uploaded_at ?? ''),
    };
    documentPreviewModalOpen.value = true;
};

const closeDocumentPreview = () => {
    documentPreviewModalOpen.value = false;
    documentPreviewData.value = null;
};

const previewExtension = computed(() => {
    const rawName = String(documentPreviewData.value?.file_name ?? documentPreviewData.value?.file_url ?? '').trim().toLowerCase();
    if (!rawName.includes('.')) return '';
    return rawName.split('.').pop() ?? '';
});

const previewType = computed(() => {
    const extension = previewExtension.value;
    if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'].includes(extension)) return 'image';
    if (extension === 'pdf') return 'pdf';
    return 'other';
});

const confirmDelete = () => {
    if (!deleteTarget.value?.id) return;

    let routeName = '';
    if (deleteTarget.value.type === 'fee') routeName = 'admin.services.accounting.fees.destroy';
    if (deleteTarget.value.type === 'obligation') routeName = 'admin.services.accounting.obligations.destroy';
    if (deleteTarget.value.type === 'document') routeName = 'admin.services.accounting.documents.destroy';
    if (!routeName) return;

    deleteForm.delete(route(routeName, deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: closeDelete,
    });
};

const statusLabel = (options, value) => {
    const found = (options ?? []).find((item) => item.value === value);
    return found?.label ?? value;
};
</script>

<template>
    <Head title="Gestão Contábil" />

    <AuthenticatedLayout
        area="admin"
        header-variant="compact"
        header-title="Gestão Contábil"
        :show-table-view-toggle="false"
    >
        <section class="space-y-4" :style="accountingUiStyles">
            <div class="accounting-tabs-shell">
                <div class="accounting-tabs-track">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="accounting-tab"
                        :class="{ 'is-active': activeTab === tab.key }"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="stat in statsCards" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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
                v-if="!hasClients"
                class="flex flex-col gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="font-medium">
                    Nenhum cliente cadastrado. Cadastre clientes para vincular honorários, obrigações e documentos.
                </p>
                <Link
                    :href="route('admin.clients.index')"
                    class="inline-flex w-fit items-center rounded-xl border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-100"
                >
                    Ir para clientes
                </Link>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ sectionTitle }}</p>
                        <p class="text-xs text-slate-500">{{ sectionSubtitle }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                        :disabled="!canCreateCurrentTab"
                        @click="openCreateByActiveTab"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        {{ createButtonLabel }}
                    </button>
                </div>

                <div v-if="activeTab === 'fees'" class="mt-4 space-y-3">
                    <div v-if="!moduleAccess.finance" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-700">
                        O módulo Financeiro não está habilitado para este contratante.
                    </div>
                    <div class="grid gap-2 md:grid-cols-5">
                        <input
                            v-model="feeFilters.search"
                            type="text"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 md:col-span-2"
                            placeholder="Buscar por cliente ou referência"
                        >
                        <UiSelect v-model="feeFilters.client_id" :options="clientOptions" button-class="w-full text-sm" />
                        <UiSelect v-model="feeFilters.status" :options="feeStatusFilterOptions" button-class="w-full text-sm" />
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="clearFeeFilters"
                        >
                            Limpar filtros
                        </button>
                    </div>
                    <div class="grid gap-2 md:grid-cols-4">
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Vencimento de</label>
                            <input v-model="feeFilters.due_from" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Vencimento até</label>
                            <input v-model="feeFilters.due_to" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <TableViewToggle />
                    </div>
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Referência</th>
                                    <th class="px-4 py-3">Vencimento</th>
                                    <th class="px-4 py-3">Valor</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-if="!filteredFees.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Nenhum honorário cadastrado.</td>
                                </tr>
                                <tr v-for="entry in filteredFees" :key="entry.id">
                                    <td class="px-4 py-3 text-slate-700">{{ entry.client_name }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ entry.reference_label }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ formatDateBR(entry.due_date) }}</td>
                                    <td class="px-4 py-3 text-slate-700">
                                        <p>{{ asCurrency(entry.amount) }}</p>
                                        <p class="text-xs text-slate-500">Pago: {{ asCurrency(entry.paid_amount) }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                            {{ statusLabel(feeStatusOptions, entry.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <button
                                                v-if="moduleAccess.finance"
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                @click="openFeeEdit(entry)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                Editar
                                            </button>
                                            <button
                                                v-if="moduleAccess.finance"
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                                @click="openDelete('fee', entry.id, entry.reference_label)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                Excluir
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="activeTab === 'obligations'" class="mt-4 space-y-3">
                    <div v-if="!moduleAccess.tasks" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-700">
                        O módulo Tarefas recorrentes não está habilitado para este contratante.
                    </div>
                    <div class="grid gap-2 md:grid-cols-5">
                        <input
                            v-model="obligationFilters.search"
                            type="text"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 md:col-span-2"
                            placeholder="Buscar por cliente, obrigação ou tipo"
                        >
                        <UiSelect v-model="obligationFilters.client_id" :options="clientOptions" button-class="w-full text-sm" />
                        <UiSelect v-model="obligationFilters.status" :options="obligationStatusFilterOptions" button-class="w-full text-sm" />
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="clearObligationFilters"
                        >
                            Limpar filtros
                        </button>
                    </div>
                    <div class="grid gap-2 md:grid-cols-4">
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Vencimento de</label>
                            <input v-model="obligationFilters.due_from" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Vencimento até</label>
                            <input v-model="obligationFilters.due_to" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <TableViewToggle />
                    </div>
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Obrigação</th>
                                    <th class="px-4 py-3">Vencimento</th>
                                    <th class="px-4 py-3">Prioridade</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-if="!filteredObligations.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Nenhuma obrigação cadastrada.</td>
                                </tr>
                                <tr v-for="entry in filteredObligations" :key="entry.id">
                                    <td class="px-4 py-3 text-slate-700">{{ entry.client_name }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ entry.title }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ formatDateBR(entry.due_date) }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ statusLabel(priorityOptions, entry.priority) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                            {{ statusLabel(obligationStatusOptions, entry.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <button
                                                v-if="moduleAccess.tasks"
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                @click="openObligationEdit(entry)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                Editar
                                            </button>
                                            <button
                                                v-if="moduleAccess.tasks"
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                                @click="openDelete('obligation', entry.id, entry.title)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                Excluir
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="activeTab === 'documents'" class="mt-4 space-y-3">
                    <div v-if="!moduleAccess.documents" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-700">
                        O módulo Documentos não está habilitado para este contratante.
                    </div>
                    <div class="grid gap-2 md:grid-cols-5">
                        <input
                            v-model="documentFilters.search"
                            type="text"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 md:col-span-2"
                            placeholder="Buscar por cliente, documento, tipo ou protocolo"
                        >
                        <UiSelect v-model="documentFilters.client_id" :options="clientOptions" button-class="w-full text-sm" />
                        <UiSelect v-model="documentFilters.status" :options="documentStatusFilterOptions" button-class="w-full text-sm" />
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="clearDocumentFilters"
                        >
                            Limpar filtros
                        </button>
                    </div>
                    <div class="grid gap-2 md:grid-cols-4">
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Prazo de</label>
                            <input v-model="documentFilters.due_from" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Prazo até</label>
                            <input v-model="documentFilters.due_to" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <TableViewToggle />
                    </div>
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Documento</th>
                                    <th class="px-4 py-3">Tipo</th>
                                    <th class="px-4 py-3">Prazo</th>
                                    <th class="px-4 py-3">Arquivo</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-if="!filteredDocuments.length">
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">Nenhuma solicitação de documento cadastrada.</td>
                                </tr>
                                <tr v-for="entry in filteredDocuments" :key="entry.id">
                                    <td class="px-4 py-3 text-slate-700">{{ entry.client_name }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ entry.title }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ entry.document_type || '-' }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ formatDateBR(entry.due_date) }}</td>
                                    <td class="px-4 py-3 text-slate-700">
                                        <template v-if="latestDocumentVersion(entry)">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                @click="openDocumentPreview(entry)"
                                            >
                                                <Eye class="h-3.5 w-3.5" />
                                                {{ latestDocumentVersion(entry).file_name || `Versão ${latestDocumentVersion(entry).version_number}` }}
                                            </button>
                                            <p class="mt-1 text-[11px] text-slate-500">
                                                Enviado em {{ latestDocumentVersion(entry).uploaded_at || '-' }}
                                            </p>
                                        </template>
                                        <span v-else class="text-xs text-slate-400">Sem arquivo</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                            {{ statusLabel(documentStatusOptions, entry.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <button
                                                v-if="moduleAccess.documents"
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                @click="openDocumentEdit(entry)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                Editar
                                            </button>
                                            <button
                                                v-if="moduleAccess.documents"
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                @click="openDocumentUpload(entry)"
                                            >
                                                <Upload class="h-3.5 w-3.5" />
                                                Upload
                                            </button>
                                            <button
                                                v-if="moduleAccess.documents"
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                                @click="openDelete('document', entry.id, entry.title)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                Excluir
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </section>

        <Modal :show="feeModalOpen" max-width="4xl" @close="feeModalOpen = false">
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <h3 class="text-lg font-semibold text-slate-900">{{ editingFee ? 'Editar honorário' : 'Novo honorário' }}</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</label>
                        <UiSelect v-model="feeForm.client_id" :options="clientOptions" button-class="mt-1 w-full text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Referência</label>
                        <input v-model="feeForm.reference_label" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="03/2026">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vencimento</label>
                        <input v-model="feeForm.due_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                        <UiSelect v-model="feeForm.status" :options="feeStatusOptions" button-class="mt-1 w-full text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valor</label>
                        <BrlMoneyInput v-model="feeForm.amount" :allow-empty="false" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="R$ 0,00" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valor pago</label>
                        <BrlMoneyInput v-model="feeForm.paid_amount" :allow-empty="false" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="R$ 0,00" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="feeModalOpen = false">Cancelar</button>
                    <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800" @click="submitFee">Salvar</button>
                </div>
            </div>
        </Modal>

        <Modal :show="obligationModalOpen" max-width="4xl" @close="obligationModalOpen = false">
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <h3 class="text-lg font-semibold text-slate-900">{{ editingObligation ? 'Editar obrigação' : 'Nova obrigação' }}</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</label>
                        <UiSelect v-model="obligationForm.client_id" :options="clientOptions" button-class="mt-1 w-full text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Título</label>
                        <input v-model="obligationForm.title" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</label>
                        <input v-model="obligationForm.obligation_type" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vencimento</label>
                        <input v-model="obligationForm.due_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                        <UiSelect v-model="obligationForm.status" :options="obligationStatusOptions" button-class="mt-1 w-full text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prioridade</label>
                        <UiSelect v-model="obligationForm.priority" :options="priorityOptions" button-class="mt-1 w-full text-sm" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="obligationModalOpen = false">Cancelar</button>
                    <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800" @click="submitObligation">Salvar</button>
                </div>
            </div>
        </Modal>

        <Modal :show="documentModalOpen" max-width="4xl" @close="documentModalOpen = false">
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <h3 class="text-lg font-semibold text-slate-900">{{ editingDocument ? 'Editar documento' : 'Novo documento' }}</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</label>
                        <UiSelect v-model="documentForm.client_id" :options="clientOptions" button-class="mt-1 w-full text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Título</label>
                        <input v-model="documentForm.title" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</label>
                        <input v-model="documentForm.document_type" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prazo</label>
                        <input v-model="documentForm.due_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                        <UiSelect v-model="documentForm.status" :options="documentStatusOptions" button-class="mt-1 w-full text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Arquivo do documento</label>
                        <input
                            ref="documentFormFileInput"
                            type="file"
                            class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-slate-800"
                            @change="onDocumentFormFileChange"
                        >
                        <p class="mt-1 text-[11px] text-slate-500">
                            Opcional. Envie PDF ou imagem para já anexar a primeira versão.
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações da versão</label>
                        <textarea
                            v-model="documentForm.version_notes"
                            rows="2"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Documento enviado pelo cliente em 22/03/2026"
                        />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="documentModalOpen = false">Cancelar</button>
                    <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800" @click="submitDocument">Salvar</button>
                </div>
            </div>
        </Modal>

        <Modal :show="documentUploadModalOpen" max-width="2xl" @close="closeDocumentUpload">
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <h3 class="text-lg font-semibold text-slate-900">Upload de documento</h3>
                <p class="text-sm text-slate-600">
                    {{ documentUploadTarget?.title || 'Documento' }}
                </p>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Arquivo</label>
                    <input
                        ref="documentUploadFileInput"
                        type="file"
                        class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-slate-800"
                        @change="onDocumentUploadFileChange"
                    >
                    <p class="mt-1 text-[11px] text-slate-500">Aceita PDF e imagens.</p>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</label>
                    <textarea
                        v-model="documentUploadForm.version_notes"
                        rows="3"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        placeholder="Inclua detalhes sobre esta versão"
                    />
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeDocumentUpload"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="documentUploadForm.processing"
                        @click="submitDocumentUpload"
                    >
                        {{ documentUploadForm.processing ? 'Enviando...' : 'Enviar versão' }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="documentPreviewModalOpen" max-width="5xl" @close="closeDocumentPreview">
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ documentPreviewData?.title || 'Documento' }}</h3>
                        <p class="text-sm text-slate-600">{{ documentPreviewData?.file_name || '-' }}</p>
                        <p class="text-[11px] text-slate-500">
                            Enviado em: {{ documentPreviewData?.uploaded_at || '-' }}
                        </p>
                    </div>
                    <a
                        v-if="documentPreviewData?.file_url"
                        :href="documentPreviewData.file_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Abrir em nova aba
                    </a>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <img
                        v-if="previewType === 'image' && documentPreviewData?.file_url"
                        :src="documentPreviewData.file_url"
                        :alt="documentPreviewData?.file_name || 'Documento'"
                        class="mx-auto max-h-[70vh] w-auto rounded-lg object-contain"
                    >
                    <iframe
                        v-else-if="previewType === 'pdf' && documentPreviewData?.file_url"
                        :src="documentPreviewData.file_url"
                        class="h-[70vh] w-full rounded-lg border border-slate-200 bg-white"
                    />
                    <div v-else class="space-y-2 px-2 py-6 text-center">
                        <p class="text-sm text-slate-600">Pré-visualização indisponível para este formato.</p>
                        <a
                            v-if="documentPreviewData?.file_url"
                            :href="documentPreviewData.file_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                        >
                            Baixar arquivo
                        </a>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeDocumentPreview"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </Modal>

        <DeleteConfirmModal
            :show="deleteModalOpen"
            title="Excluir registro"
            message="Tem certeza que deseja excluir este registro?"
            :item-label="deleteTarget.label ? `Item: ${deleteTarget.label}` : ''"
            :processing="deleteForm.processing"
            @close="closeDelete"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.accounting-tabs-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.accounting-tabs-shell::-webkit-scrollbar {
    height: 6px;
}

.accounting-tabs-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.accounting-tabs-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.accounting-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
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

.accounting-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.accounting-tab.is-active {
    border-color: var(--accounting-tab-active-border);
    background: var(--accounting-tab-active);
    color: #ffffff;
}
</style>

