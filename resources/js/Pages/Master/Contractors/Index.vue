<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Building2, CircleCheckBig, Store, Briefcase, Search, Filter, Plus, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    contractors: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, active: 0, commercial: 0, services: 0 }),
    },
    plans: {
        type: Array,
        default: () => [],
    },
    niches: {
        type: Array,
        default: () => [],
    },
    businessTypes: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const flashStatus = computed(() => page.props.flash?.status ?? null);

const filterForm = useForm({
    search: props.filters?.search ?? '',
    niche: props.filters?.niche ?? '',
    status: props.filters?.status ?? '',
    plan_id: props.filters?.plan_id ?? '',
});

watch(
    () => props.filters,
    (next) => {
        filterForm.search = next?.search ?? '';
        filterForm.niche = next?.niche ?? '';
        filterForm.status = next?.status ?? '';
        filterForm.plan_id = next?.plan_id ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('master.contractors.index'),
        {
            search: filterForm.search || undefined,
            niche: filterForm.niche || undefined,
            status: filterForm.status || undefined,
            plan_id: filterForm.plan_id || undefined,
        },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        },
    );
};

const clearSearch = () => {
    if (!String(filterForm.search ?? '').trim()) return;
    filterForm.search = '';
    applyFilters();
};

const clearFilters = () => {
    filterForm.search = '';
    filterForm.niche = '';
    filterForm.status = '';
    filterForm.plan_id = '';
    applyFilters();
};

const rows = computed(() => props.contractors?.data ?? []);
const paginationLinks = computed(() => props.contractors?.links ?? []);
const filteredPlans = computed(() => {
    const allPlans = props.plans ?? [];
    if (!filterForm.niche) return allPlans;

    return allPlans.filter((plan) => plan.niche === filterForm.niche);
});
const nicheFilterOptions = computed(() => [
    { value: '', label: 'Todos nichos' },
    ...(props.niches ?? []).map((niche) => ({
        value: niche.value,
        label: niche.label,
    })),
]);
const planFilterOptions = computed(() => [
    { value: '', label: 'Todos planos' },
    ...(filteredPlans.value ?? []).map((plan) => ({
        value: plan.id,
        label: filterForm.niche ? plan.name : `${plan.name} (${plan.niche_label})`,
    })),
]);
const statusFilterOptions = [
    { value: '', label: 'Todos status' },
    { value: 'active', label: 'Ativos' },
    { value: 'inactive', label: 'Inativos' },
];

const statsCards = computed(() => [
    { key: 'total', label: 'Contratantes', value: String(props.stats?.total ?? 0), icon: Building2, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Ativos', value: String(props.stats?.active ?? 0), icon: CircleCheckBig, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'commercial', label: 'Nicho comércio', value: String(props.stats?.commercial ?? 0), icon: Store, tone: 'bg-blue-100 text-blue-700' },
    { key: 'services', label: 'Nicho serviços', value: String(props.stats?.services ?? 0), icon: Briefcase, tone: 'bg-amber-100 text-amber-700' },
]);

const showModal = ref(false);
const editingContractor = ref(null);
const showDeleteModal = ref(false);
const contractorToDelete = ref(null);
const contractorWizardSteps = ['Dados do contratante', 'Plano de assinatura'];
const contractorWizardStep = ref(1);
const contractorWizardValidationRequested = ref(false);

const contractorForm = useForm({
    name: '',
    email: '',
    phone: '',
    cnpj: '',
    slug: '',
    timezone: 'America/Sao_Paulo',
    brand_name: '',
    brand_primary_color: '#073341',
    contract_starts_at: '',
    contract_ends_at: '',
    business_niche: props.niches?.[0]?.value ?? 'commercial',
    business_type: '',
    plan_id: '',
    override_user_limit: '',
    override_storage_limit_gb: '',
    override_audit_log_retention_days: '',
    is_active: true,
});

const timezoneFormOptions = [
    { value: 'America/Noronha', label: 'Fernando de Noronha (UTC-02:00)' },
    { value: 'America/Sao_Paulo', label: 'São Paulo, Brasília (UTC-03:00)' },
    { value: 'America/Bahia', label: 'Bahia (UTC-03:00)' },
    { value: 'America/Fortaleza', label: 'Fortaleza (UTC-03:00)' },
    { value: 'America/Belem', label: 'Belém (UTC-03:00)' },
    { value: 'America/Manaus', label: 'Manaus (UTC-04:00)' },
    { value: 'America/Campo_Grande', label: 'Campo Grande (UTC-04:00)' },
    { value: 'America/Cuiaba', label: 'Cuiabá (UTC-04:00)' },
    { value: 'America/Porto_Velho', label: 'Porto Velho (UTC-04:00)' },
    { value: 'America/Boa_Vista', label: 'Boa Vista (UTC-04:00)' },
    { value: 'America/Rio_Branco', label: 'Rio Branco (UTC-05:00)' },
];

const digitsOnly = (value, maxLength = 99) => String(value ?? '').replace(/\D+/g, '').slice(0, maxLength);

const normalizeBrandColor = (value) => {
    const raw = String(value ?? '').trim();
    const match = raw.match(/^#?[0-9a-fA-F]{6}$/);
    if (!match) return '#073341';

    return `#${match[0].replace('#', '').toUpperCase()}`;
};

const formatPhone = (value) => {
    const digits = digitsOnly(value, 11);
    if (!digits) return '';
    if (digits.length <= 2) return `(${digits}`;
    if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
};

const formatCnpj = (value) => {
    const digits = digitsOnly(value, 14);
    if (!digits) return '';
    if (digits.length <= 2) return digits;
    if (digits.length <= 5) return `${digits.slice(0, 2)}.${digits.slice(2)}`;
    if (digits.length <= 8) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
    if (digits.length <= 12) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;

    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
};

const onPhoneInput = (event) => {
    contractorForm.phone = formatPhone(event?.target?.value ?? contractorForm.phone);
};

const onCnpjInput = (event) => {
    contractorForm.cnpj = formatCnpj(event?.target?.value ?? contractorForm.cnpj);
};
const deleteForm = useForm({});
const formPlans = computed(() => {
    return (props.plans ?? []).filter((plan) => plan.niche === contractorForm.business_niche);
});
const nicheFormOptions = computed(() =>
    (props.niches ?? []).map((niche) => ({
        value: niche.value,
        label: niche.label,
    })),
);
const businessTypeFormOptions = computed(() => {
    return (props.businessTypes ?? [])
        .filter((item) => item.niche === contractorForm.business_niche)
        .map((item) => ({
            value: item.value,
            label: item.label,
        }));
});
const planFormOptions = computed(() => [
    { value: '', label: 'Sem plano' },
    ...(formPlans.value ?? []).map((plan) => ({
        value: plan.id,
        label: plan.name,
    })),
]);
const isEditing = computed(() => Boolean(editingContractor.value?.id));

const isBasicEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value ?? '').trim());

const isContractorStepValid = (stepNumber) => {
    if (stepNumber === 1) {
        return String(contractorForm.name ?? '').trim() !== ''
            && String(contractorForm.email ?? '').trim() !== ''
            && isBasicEmail(contractorForm.email)
            && String(contractorForm.timezone ?? '').trim() !== ''
            && String(contractorForm.business_niche ?? '').trim() !== ''
            && String(contractorForm.business_type ?? '').trim() !== '';
    }

    if (stepNumber === 2) {
        const startsAt = String(contractorForm.contract_starts_at ?? '').trim();
        const endsAt = String(contractorForm.contract_ends_at ?? '').trim();

        if (endsAt && !startsAt) return false;
        if (startsAt && endsAt && endsAt < startsAt) return false;
    }

    return true;
};

const clearContractorStepLocalErrors = (stepNumber) => {
    if (stepNumber === 1) {
        contractorForm.clearErrors('name', 'email', 'timezone', 'business_niche', 'business_type');
        return;
    }

    if (stepNumber === 2) {
        contractorForm.clearErrors('contract_starts_at', 'contract_ends_at');
    }
};

const applyContractorStepLocalErrors = (stepNumber) => {
    if (stepNumber === 1) {
        const name = String(contractorForm.name ?? '').trim();
        const email = String(contractorForm.email ?? '').trim();
        const timezone = String(contractorForm.timezone ?? '').trim();
        const niche = String(contractorForm.business_niche ?? '').trim();
        const businessType = String(contractorForm.business_type ?? '').trim();

        if (name === '') contractorForm.setError('name', 'Informe o nome do contratante.');
        if (email === '') contractorForm.setError('email', 'Informe o e-mail do contratante.');
        if (email !== '' && !isBasicEmail(email)) contractorForm.setError('email', 'Informe um e-mail válido.');
        if (timezone === '') contractorForm.setError('timezone', 'Selecione o fuso horário.');
        if (niche === '') contractorForm.setError('business_niche', 'Selecione o nicho.');
        if (businessType === '') contractorForm.setError('business_type', 'Selecione o tipo de contratante.');
        return;
    }

    if (stepNumber === 2) {
        const startsAt = String(contractorForm.contract_starts_at ?? '').trim();
        const endsAt = String(contractorForm.contract_ends_at ?? '').trim();

        if (endsAt && !startsAt) {
            contractorForm.setError('contract_starts_at', 'Informe o início do contrato para usar término.');
        }
        if (startsAt && endsAt && endsAt < startsAt) {
            contractorForm.setError('contract_ends_at', 'O término deve ser igual ou posterior ao início.');
        }
    }
};

const validateCurrentContractorStepForCreate = () => {
    if (isEditing.value) return true;

    const step = contractorWizardStep.value;
    contractorWizardValidationRequested.value = true;
    clearContractorStepLocalErrors(step);

    if (isContractorStepValid(step)) return true;

    applyContractorStepLocalErrors(step);
    return false;
};

const contractorStepErrorKeyMap = {
    1: ['name', 'email', 'phone', 'cnpj', 'slug', 'timezone', 'brand_name', 'brand_primary_color', 'business_niche', 'business_type'],
    2: ['plan_id', 'contract_starts_at', 'contract_ends_at', 'override_user_limit', 'override_storage_limit_gb', 'override_audit_log_retention_days', 'is_active'],
};

const hasContractorFormErrorForStep = (stepNumber) => {
    const keys = Object.keys(contractorForm.errors ?? {});
    const prefixes = contractorStepErrorKeyMap[stepNumber] ?? [];

    return keys.some((key) => prefixes.some((prefix) => key === prefix || key.startsWith(`${prefix}.`)));
};

const shouldShowContractorStepErrors = computed(() =>
    isEditing.value
    || contractorWizardValidationRequested.value
    || Object.keys(contractorForm.errors ?? {}).length > 0,
);

const contractorStepErrors = computed(() =>
    contractorWizardSteps.map((_, index) => {
        const stepNumber = index + 1;
        if (!shouldShowContractorStepErrors.value) return false;

        const checkLocalValidation = isEditing.value || stepNumber <= contractorWizardStep.value;
        const hasLocalError = checkLocalValidation ? !isContractorStepValid(stepNumber) : false;

        return hasLocalError || hasContractorFormErrorForStep(stepNumber);
    }),
);

watch(
    () => filterForm.niche,
    () => {
        if (!filterForm.plan_id) return;
        const stillExists = filteredPlans.value.some((plan) => String(plan.id) === String(filterForm.plan_id));
        if (!stillExists) {
            filterForm.plan_id = '';
        }
    },
);

watch(
    () => contractorForm.business_niche,
    () => {
        const validBusinessType = businessTypeFormOptions.value.some((option) => option.value === contractorForm.business_type);
        if (!validBusinessType) {
            contractorForm.business_type = businessTypeFormOptions.value[0]?.value ?? '';
        }

        if (contractorForm.plan_id) {
            const stillExists = formPlans.value.some((plan) => String(plan.id) === String(contractorForm.plan_id));
            if (!stillExists) {
                contractorForm.plan_id = '';
            }
        }
    },
);

const resetContractorForm = () => {
    contractorForm.reset();
    contractorForm.clearErrors();
    contractorForm.name = '';
    contractorForm.email = '';
    contractorForm.phone = '';
    contractorForm.cnpj = '';
    contractorForm.slug = '';
    contractorForm.timezone = 'America/Sao_Paulo';
    contractorForm.brand_name = '';
    contractorForm.brand_primary_color = normalizeBrandColor('#073341');
    contractorForm.contract_starts_at = '';
    contractorForm.contract_ends_at = '';
    contractorForm.business_niche = props.niches?.[0]?.value ?? 'commercial';
    contractorForm.business_type = businessTypeFormOptions.value[0]?.value ?? '';
    contractorForm.plan_id = '';
    contractorForm.override_user_limit = '';
    contractorForm.override_storage_limit_gb = '';
    contractorForm.override_audit_log_retention_days = '';
    contractorForm.is_active = true;
};

const openCreate = () => {
    editingContractor.value = null;
    contractorWizardStep.value = 1;
    contractorWizardValidationRequested.value = false;
    resetContractorForm();
    showModal.value = true;
};

const openEdit = (contractor) => {
    editingContractor.value = contractor;
    contractorWizardStep.value = 1;
    contractorWizardValidationRequested.value = false;
    contractorForm.name = contractor.name ?? '';
    contractorForm.email = contractor.email ?? '';
    contractorForm.phone = formatPhone(contractor.phone ?? '');
    contractorForm.cnpj = formatCnpj(contractor.cnpj ?? '');
    contractorForm.slug = contractor.slug ?? '';
    contractorForm.timezone = contractor.timezone ?? 'America/Sao_Paulo';
    contractorForm.brand_name = contractor.brand_name ?? '';
    contractorForm.brand_primary_color = normalizeBrandColor(contractor.brand_primary_color ?? '#073341');
    contractorForm.contract_starts_at = contractor.contract_starts_at ?? '';
    contractorForm.contract_ends_at = contractor.contract_ends_at ?? '';
    contractorForm.business_niche = contractor.business_niche ?? 'commercial';
    contractorForm.business_type = contractor.business_type ?? (businessTypeFormOptions.value[0]?.value ?? '');
    contractorForm.plan_id = contractor.plan_id ?? '';
    contractorForm.override_user_limit = contractor.override_user_limit ?? '';
    contractorForm.override_storage_limit_gb = contractor.override_storage_limit_gb ?? '';
    contractorForm.override_audit_log_retention_days = contractor.override_audit_log_retention_days ?? '';
    contractorForm.is_active = Boolean(contractor.is_active);
    contractorForm.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingContractor.value = null;
    contractorWizardStep.value = 1;
    contractorWizardValidationRequested.value = false;
    resetContractorForm();
};

const goToWizardStep = (step) => {
    contractorWizardStep.value = Math.min(Math.max(Number(step) || 1, 1), contractorWizardSteps.length);
};

const nextWizardStep = () => {
    if (!validateCurrentContractorStepForCreate()) return;
    goToWizardStep(contractorWizardStep.value + 1);
};

const previousWizardStep = () => {
    goToWizardStep(contractorWizardStep.value - 1);
};

const submitContractor = () => {
    contractorWizardValidationRequested.value = true;
    clearContractorStepLocalErrors(2);
    if (!isContractorStepValid(2)) {
        applyContractorStepLocalErrors(2);
        return;
    }

    contractorForm.clearErrors('phone', 'cnpj', 'brand_primary_color');

    const phoneRegex = /^\(\d{2}\)\s\d{5}-\d{4}$/;
    const cnpjRegex = /^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/;

    if (contractorForm.phone && !phoneRegex.test(contractorForm.phone)) {
        contractorForm.setError('phone', 'Informe no formato (00) 00000-0000.');
        return;
    }

    if (contractorForm.cnpj && !cnpjRegex.test(contractorForm.cnpj)) {
        contractorForm.setError('cnpj', 'Informe no formato 00.000.000/0000-00.');
        return;
    }

    contractorForm.brand_primary_color = normalizeBrandColor(contractorForm.brand_primary_color);

    if (isEditing.value) {
        contractorForm.put(route('master.contractors.update', editingContractor.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    contractorForm.post(route('master.contractors.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const openDeleteModal = (contractor) => {
    contractorToDelete.value = contractor;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    contractorToDelete.value = null;
};

const removeContractor = () => {
    if (!contractorToDelete.value?.id) return;

    deleteForm.delete(route('master.contractors.destroy', contractorToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};

const formatMoney = (value) => {
    if (value === null || value === undefined) return 'Sob consulta';

    const parsed = Number(value);
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number.isFinite(parsed) ? parsed : 0);
};
</script>

<template>
    <Head title="Contratantes" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Contratantes" :show-table-view-toggle="false">
        <section class="space-y-4">
            <div v-if="flashStatus" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ flashStatus }}
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

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Buscar contratante por nome, email, slug ou CNPJ"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keydown.enter.prevent="applyFilters"
                        />
                        <button
                            v-if="filterForm.search"
                            type="button"
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                            aria-label="Limpar pesquisa"
                            @click="clearSearch"
                        >
                            x
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="applyFilters"
                        >
                            <Search class="h-3.5 w-3.5" />
                            Buscar
                        </button>
                        <UiSelect
                            v-model="filterForm.niche"
                            :options="nicheFilterOptions"
                            button-class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <UiSelect
                            v-model="filterForm.plan_id"
                            :options="planFilterOptions"
                            button-class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <UiSelect
                            v-model="filterForm.status"
                            :options="statusFilterOptions"
                            button-class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="clearFilters"
                        >
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            @click="openCreate"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Novo contratante
                        </button>
                    </div>
                </div>

                                <div class="mt-3 flex justify-end">
                    <TableViewToggle />
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Contratante</th>
                                <th class="px-4 py-3">Plano</th>
                                <th class="px-4 py-3">Nicho</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Admins</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhum contratante encontrado.
                                </td>
                            </tr>
                            <tr v-for="contractor in rows" :key="contractor.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ contractor.name }}</p>
                                    <p class="text-xs text-slate-500">{{ contractor.email }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p class="font-semibold text-slate-800">{{ contractor.plan_name || 'Sem plano' }}</p>
                                    <p class="text-xs text-slate-500">{{ formatMoney(contractor.monthly_price) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ contractor.business_niche_label }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ contractor.business_type_label || '--' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ contractor.admins_count }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                        :class="contractor.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                                    >
                                        {{ contractor.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="openEdit(contractor)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="openDeleteModal(contractor)"
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

                <PaginationLinks :links="paginationLinks" :min-links="4" />
            </section>
        </section>
        <Modal :show="showModal" max-width="5xl" @close="closeModal">
            <WizardModalFrame
                :title="isEditing ? 'Editar contratante' : 'Novo contratante'"
                description="Preencha os dados do contratante."
                :steps="contractorWizardSteps"
                :current-step="contractorWizardStep"
                :steps-clickable="isEditing"
                :max-clickable-step="contractorWizardSteps.length"
                :step-errors="contractorStepErrors"
                @step-change="goToWizardStep"
                @close="closeModal"
            >
                <div v-if="contractorWizardStep === 1" class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input
                            v-model="contractorForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Veshop Market"
                        >
                        <p v-if="contractorForm.errors.name" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                        <input
                            v-model="contractorForm.email"
                            type="email"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="contato@empresa.com.br"
                        >
                        <p v-if="contractorForm.errors.email" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                        <input
                            v-model="contractorForm.phone"
                            type="text"
                            inputmode="tel"
                            maxlength="15"
                            pattern="^\(\d{2}\)\s\d{5}-\d{4}$"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="(00) 00000-0000"
                            @input="onPhoneInput"
                        >
                        <p v-if="contractorForm.errors.phone" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">CNPJ</label>
                        <input
                            v-model="contractorForm.cnpj"
                            type="text"
                            inputmode="numeric"
                            maxlength="18"
                            pattern="^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="00.000.000/0000-00"
                            @input="onCnpjInput"
                        >
                        <p v-if="contractorForm.errors.cnpj" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.cnpj }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug (opcional)</label>
                        <input
                            v-model="contractorForm.slug"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="veshop-market"
                        >
                        <p v-if="contractorForm.errors.slug" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.slug }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fuso horário</label>
                        <UiSelect
                            v-model="contractorForm.timezone"
                            :options="timezoneFormOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="contractorForm.errors.timezone" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.timezone }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome da marca</label>
                        <input
                            v-model="contractorForm.brand_name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Nome exibido no sistema"
                        >
                        <p v-if="contractorForm.errors.brand_name" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.brand_name }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cor principal</label>
                        <div class="mt-1 flex items-center gap-3">
                            <input
                                v-model="contractorForm.brand_primary_color"
                                type="color"
                                class="h-10 w-14 cursor-pointer rounded-lg border border-slate-200 bg-white p-1"
                                aria-label="Selecionar cor principal"
                            >
                            <span class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-600">
                                {{ contractorForm.brand_primary_color }}
                            </span>
                        </div>
                        <p v-if="contractorForm.errors.brand_primary_color" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.brand_primary_color }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nicho</label>
                        <UiSelect
                            v-model="contractorForm.business_niche"
                            :options="nicheFormOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="contractorForm.errors.business_niche" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.business_niche }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo de contratante</label>
                        <UiSelect
                            v-model="contractorForm.business_type"
                            :options="businessTypeFormOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="contractorForm.errors.business_type" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.business_type }}</p>
                    </div>
                </div>
                <div v-else class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Plano ativo</label>
                        <UiSelect
                            v-model="contractorForm.plan_id"
                            :options="planFormOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            Módulos e permissões são definidos no plano selecionado.
                        </p>
                        <p v-if="contractorForm.errors.plan_id" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.plan_id }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Início do contrato</label>
                        <input
                            v-model="contractorForm.contract_starts_at"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="contractorForm.errors.contract_starts_at" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.contract_starts_at }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Término do contrato</label>
                        <input
                            v-model="contractorForm.contract_ends_at"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="contractorForm.errors.contract_ends_at" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.contract_ends_at }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Overrides de limite (opcional)</p>
                            <p class="mt-1 text-xs text-slate-600">
                                Quando preenchido, o valor sobrescreve o limite padrão do plano para este contratante.
                            </p>
                            <div class="mt-3 grid gap-3 md:grid-cols-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Limite de usuários</label>
                                    <input
                                        v-model="contractorForm.override_user_limit"
                                        type="number"
                                        min="1"
                                        step="1"
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                        placeholder="Padrão do plano"
                                    >
                                    <p v-if="contractorForm.errors.override_user_limit" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.override_user_limit }}</p>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Storage (GB)</label>
                                    <input
                                        v-model="contractorForm.override_storage_limit_gb"
                                        type="number"
                                        min="1"
                                        step="1"
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                        placeholder="Padrão do plano"
                                    >
                                    <p v-if="contractorForm.errors.override_storage_limit_gb" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.override_storage_limit_gb }}</p>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Auditoria (dias)</label>
                                    <input
                                        v-model="contractorForm.override_audit_log_retention_days"
                                        type="number"
                                        min="1"
                                        step="1"
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                        placeholder="Padrão do plano"
                                    >
                                    <p v-if="contractorForm.errors.override_audit_log_retention_days" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.override_audit_log_retention_days }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="contractorForm.is_active" type="checkbox" class="rounded border-slate-300">
                            Contratante ativo
                        </label>
                    </div>
                </div>
                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeModal"
                        >
                            Cancelar
                        </button>
                        <button
                            v-if="contractorWizardStep > 1"
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="previousWizardStep"
                        >
                            Voltar
                        </button>
                        <button
                            v-if="contractorWizardStep < contractorWizardSteps.length"
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            @click="nextWizardStep"
                        >
                            Próxima etapa
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="contractorForm.processing"
                            @click="submitContractor"
                        >
                            {{ contractorForm.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir contratante"
            message="Tem certeza que deseja excluir este contratante?"
            :item-label="contractorToDelete?.name ? `Contratante: ${contractorToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="removeContractor"
        />
    </AuthenticatedLayout>
</template>
