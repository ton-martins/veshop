<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Truck, PackageCheck, Timer, AlertTriangle, Search, Filter, Plus, Pencil, Trash2 } from 'lucide-vue-next';
import {
    BRAZIL_STATES,
    formatCepBR,
    detectDocumentTypeBR,
    formatDocumentByTypeBR,
    formatPhoneBR,
    isValidCepMaskBR,
    isValidDocumentByTypeBR,
    isValidPhoneMaskBR,
    normalizeStateCode,
    viaCepToAddress,
} from '@/utils/br';

const props = defineProps({
    suppliers: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const filterForm = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
});

const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'active', label: 'Ativos' },
    { value: 'inactive', label: 'Inativos' },
];

const documentTypeOptions = [
    { value: 'cpf', label: 'CPF' },
    { value: 'cnpj', label: 'CNPJ' },
];

const stateOptions = computed(() => ([
    { value: '', label: 'Selecione' },
    ...BRAZIL_STATES.map((state) => ({
        value: state.code,
        label: `${state.code} - ${state.name}`,
    })),
]));

const wizardSteps = ['Dados do fornecedor', 'Endereço'];
const currentStep = ref(1);
const cepLookupLoading = ref(false);
const cepLookupError = ref('');

watch(
    () => props.filters,
    (next) => {
        filterForm.search = next?.search ?? '';
        filterForm.status = next?.status ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('admin.suppliers.index'),
        {
            search: filterForm.search || undefined,
            status: filterForm.status || undefined,
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
    filterForm.status = '';
    applyFilters();
};

const rows = computed(() => props.suppliers?.data ?? []);
const paginationLinks = computed(() => props.suppliers?.links ?? []);

const statsCards = computed(() => [
    { key: 'total', label: 'Fornecedores cadastrados', value: String(props.stats?.total ?? 0), icon: Truck, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Fornecedores ativos', value: String(props.stats?.active ?? 0), icon: PackageCheck, tone: 'bg-blue-100 text-blue-700' },
    { key: 'lead', label: 'Lead time médio', value: `${Number(props.stats?.lead_time ?? 0).toLocaleString('pt-BR')} dia(s)`, icon: Timer, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'categories', label: 'Segmentos', value: String(props.stats?.categories ?? 0), icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
]);

const showModal = ref(false);
const editingSupplier = ref(null);
const showDeleteModal = ref(false);
const supplierToDelete = ref(null);

const buildSupplierDefaults = () => ({
    name: '',
    email: '',
    phone: '',
    document_type: 'cpf',
    document: '',
    cep: '',
    street: '',
    number: '',
    complement: '',
    neighborhood: '',
    city: '',
    state: '',
    category: '',
    lead_time_days: 0,
    is_active: true,
});

const supplierForm = useForm(buildSupplierDefaults());
const deleteForm = useForm({});

const isEditing = computed(() => Boolean(editingSupplier.value?.id));
const isFirstStep = computed(() => currentStep.value === 1);
const isLastStep = computed(() => currentStep.value === wizardSteps.length);
const canAdvance = computed(() => String(supplierForm.name ?? '').trim() !== '');

const resetWizard = () => {
    currentStep.value = 1;
    cepLookupError.value = '';
};

const openCreate = () => {
    editingSupplier.value = null;
    supplierForm.defaults(buildSupplierDefaults());
    supplierForm.reset();
    supplierForm.clearErrors();
    supplierForm.lead_time_days = 0;
    supplierForm.is_active = true;
    resetWizard();
    showModal.value = true;
};

const openEdit = (supplier) => {
    editingSupplier.value = supplier;
    supplierForm.document_type = detectDocumentTypeBR(supplier.document ?? '');
    supplierForm.name = supplier.name ?? '';
    supplierForm.email = supplier.email ?? '';
    supplierForm.phone = supplier.phone ?? '';
    supplierForm.document = formatDocumentByTypeBR(supplier.document ?? '', supplierForm.document_type);
    supplierForm.cep = supplier.cep ?? '';
    supplierForm.street = supplier.street ?? '';
    supplierForm.number = supplier.number ?? '';
    supplierForm.complement = supplier.complement ?? '';
    supplierForm.neighborhood = supplier.neighborhood ?? '';
    supplierForm.city = supplier.city ?? '';
    supplierForm.state = supplier.state ?? '';
    supplierForm.category = supplier.category ?? '';
    supplierForm.lead_time_days = Number.parseInt(String(supplier.lead_time_days ?? 0), 10) || 0;
    supplierForm.is_active = Boolean(supplier.is_active);
    supplierForm.clearErrors();
    resetWizard();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingSupplier.value = null;
    supplierForm.clearErrors();
    supplierForm.defaults(buildSupplierDefaults());
    supplierForm.reset();
    resetWizard();
};

const onPhoneInput = (event) => {
    supplierForm.phone = formatPhoneBR(event?.target?.value ?? supplierForm.phone);
    supplierForm.clearErrors('phone');
};

const onDocumentInput = (event) => {
    supplierForm.document = formatDocumentByTypeBR(event?.target?.value ?? supplierForm.document, supplierForm.document_type);
    supplierForm.clearErrors('document');
};

const onDocumentTypeChange = () => {
    supplierForm.document = formatDocumentByTypeBR(supplierForm.document, supplierForm.document_type);
    supplierForm.clearErrors('document');
};

const onCepInput = (event) => {
    supplierForm.cep = formatCepBR(event?.target?.value ?? supplierForm.cep);
    supplierForm.clearErrors('cep');
};

const lookupCep = async () => {
    cepLookupError.value = '';
    supplierForm.cep = formatCepBR(supplierForm.cep);

    if (!supplierForm.cep) return;
    if (supplierForm.cep.length !== 9) {
        cepLookupError.value = 'CEP inválido. Digite os 8 números.';
        return;
    }

    cepLookupLoading.value = true;
    const cepDigits = supplierForm.cep.replace(/\D/g, '');

    try {
        const response = await fetch(`https://viacep.com.br/ws/${cepDigits}/json/`);
        if (!response.ok) throw new Error('lookup_failed');

        const payload = await response.json();
        if (payload?.erro) {
            cepLookupError.value = 'CEP não encontrado.';
            return;
        }

        const parsed = viaCepToAddress(payload);
        supplierForm.cep = parsed.cep || supplierForm.cep;
        supplierForm.street = parsed.street || supplierForm.street;
        supplierForm.neighborhood = parsed.neighborhood || supplierForm.neighborhood;
        supplierForm.city = parsed.city || supplierForm.city;
        supplierForm.state = parsed.state || supplierForm.state;

        if (!String(supplierForm.complement ?? '').trim()) {
            supplierForm.complement = parsed.complement || '';
        }
    } catch {
        cepLookupError.value = 'Não foi possível consultar o ViaCEP agora. Preencha manualmente.';
    } finally {
        cepLookupLoading.value = false;
    }
};

const goNextStep = () => {
    if (!canAdvance.value) return;
    currentStep.value = Math.min(wizardSteps.length, currentStep.value + 1);
};

const goPreviousStep = () => {
    currentStep.value = Math.max(1, currentStep.value - 1);
};

const submitSupplier = () => {
    cepLookupError.value = '';
    supplierForm.phone = formatPhoneBR(supplierForm.phone);
    supplierForm.document = formatDocumentByTypeBR(supplierForm.document, supplierForm.document_type);
    supplierForm.cep = formatCepBR(supplierForm.cep);
    supplierForm.state = normalizeStateCode(supplierForm.state);

    if (supplierForm.phone && !isValidPhoneMaskBR(supplierForm.phone)) {
        supplierForm.setError('phone', 'Informe o telefone no formato (11) 99999-9999.');
        return;
    }

    if (supplierForm.document && !isValidDocumentByTypeBR(supplierForm.document, supplierForm.document_type)) {
        const example = supplierForm.document_type === 'cnpj'
            ? '00.000.000/0000-00'
            : '000.000.000.00';
        supplierForm.setError('document', `Informe o documento no formato ${example}.`);
        return;
    }

    if (supplierForm.cep && !isValidCepMaskBR(supplierForm.cep)) {
        supplierForm.setError('cep', 'Informe o CEP no formato 00000-000.');
        return;
    }

    if (isEditing.value) {
        supplierForm.put(route('admin.suppliers.update', editingSupplier.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    supplierForm.post(route('admin.suppliers.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const openDeleteModal = (supplier) => {
    supplierToDelete.value = supplier;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    supplierToDelete.value = null;
};

const removeSupplier = () => {
    if (!supplierToDelete.value?.id) return;

    deleteForm.delete(route('admin.suppliers.destroy', supplierToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};
</script>

<template>
    <Head title="Fornecedores" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Fornecedores" :show-table-view-toggle="false">
        <section class="space-y-4">
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
                            placeholder="Buscar fornecedor por nome, e-mail ou segmento"
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
                    <div class="veshop-toolbar-actions lg:justify-end">
                        <button
                            type="button"
                            class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto"
                            @click="applyFilters"
                        >
                            <Search class="h-3.5 w-3.5" />
                            Buscar
                        </button>
                        <UiSelect
                            v-model="filterForm.status"
                            :options="statusOptions"
                            button-class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto" @click="clearFilters">
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 sm:w-auto" @click="openCreate">
                            <Plus class="h-3.5 w-3.5" />
                            Novo fornecedor
                        </button>
                    </div>
                </div>

                                <div class="mt-3 flex justify-end">
                    <TableViewToggle />
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Fornecedor</th>
                                <th class="px-4 py-3">Contato</th>
                                <th class="px-4 py-3">Documento</th>
                                <th class="px-4 py-3">Segmento</th>
                                <th class="px-4 py-3">Lead time</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhum fornecedor cadastrado para este contratante.
                                </td>
                            </tr>
                            <tr v-for="supplier in rows" :key="supplier.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ supplier.name }}</p>
                                    <p class="text-xs text-slate-500">Criado em {{ supplier.created_at }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ supplier.email || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ supplier.phone || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ supplier.document || '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ supplier.category || '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ supplier.lead_time_days }} dia(s)</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="supplier.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ supplier.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="openEdit(supplier)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="openDeleteModal(supplier)"
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
                :title="isEditing ? 'Editar fornecedor' : 'Novo fornecedor'"
                description="Preencha os dados do fornecedor."
                :steps="wizardSteps"
                :current-step="currentStep"
                @close="closeModal"
            >
                <div v-if="currentStep === 1" class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input
                            v-model="supplierForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Distribuidora Central"
                        >
                        <p v-if="supplierForm.errors.name" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                        <input
                            v-model="supplierForm.email"
                            type="email"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="fornecedor@email.com"
                        >
                        <p v-if="supplierForm.errors.email" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                        <input
                            :value="supplierForm.phone"
                            type="text"
                            inputmode="numeric"
                            maxlength="15"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="(11) 99999-9999"
                            @input="onPhoneInput"
                        >
                        <p v-if="supplierForm.errors.phone" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.phone }}</p>
                    </div>

                    <div class="md:col-span-2 grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo de documento</label>
                            <UiSelect
                                v-model="supplierForm.document_type"
                                :options="documentTypeOptions"
                                button-class="mt-1 w-full text-sm"
                                @change="onDocumentTypeChange"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documento</label>
                            <input
                                :value="supplierForm.document"
                                type="text"
                                inputmode="numeric"
                                :maxlength="supplierForm.document_type === 'cnpj' ? 18 : 14"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                :placeholder="supplierForm.document_type === 'cnpj' ? '00.000.000/0000-00' : '000.000.000.00'"
                                @input="onDocumentInput"
                            >
                            <p v-if="supplierForm.errors.document" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.document }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Segmento</label>
                        <input
                            v-model="supplierForm.category"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Matéria-prima"
                        >
                        <p v-if="supplierForm.errors.category" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.category }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lead time (dias)</label>
                        <input
                            v-model="supplierForm.lead_time_days"
                            type="number"
                            min="0"
                            step="1"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="supplierForm.errors.lead_time_days" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.lead_time_days }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:col-span-2">
                        <input v-model="supplierForm.is_active" type="checkbox" class="rounded border-slate-300">
                        Fornecedor ativo
                    </label>
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="min-w-[180px] flex-1">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">CEP</label>
                                <input
                                    :value="supplierForm.cep"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="9"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="00000-000"
                                    @input="onCepInput"
                                    @blur="lookupCep"
                                >
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="cepLookupLoading"
                                @click="lookupCep"
                            >
                                {{ cepLookupLoading ? 'Consultando...' : 'Consultar CEP' }}
                            </button>
                        </div>
                        <p v-if="supplierForm.errors.cep" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.cep }}</p>
                        <p v-if="cepLookupError" class="mt-2 text-xs font-semibold text-amber-700">{{ cepLookupError }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rua</label>
                        <input v-model="supplierForm.street" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Logradouro">
                        <p v-if="supplierForm.errors.street" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.street }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Número</label>
                        <input v-model="supplierForm.number" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="123">
                        <p v-if="supplierForm.errors.number" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.number }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Complemento</label>
                        <input v-model="supplierForm.complement" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Apto, bloco, etc">
                        <p v-if="supplierForm.errors.complement" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.complement }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bairro</label>
                        <input v-model="supplierForm.neighborhood" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Bairro">
                        <p v-if="supplierForm.errors.neighborhood" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.neighborhood }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cidade</label>
                        <input v-model="supplierForm.city" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Cidade">
                        <p v-if="supplierForm.errors.city" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.city }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">UF</label>
                        <UiSelect v-model="supplierForm.state" :options="stateOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="supplierForm.errors.state" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.state }}</p>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-between gap-2">
                        <button
                            v-if="!isFirstStep"
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="goPreviousStep"
                        >
                            Voltar
                        </button>
                        <div v-else />

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                @click="closeModal"
                            >
                                Cancelar
                            </button>
                            <button
                                v-if="!isLastStep"
                                type="button"
                                class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!canAdvance"
                                @click="goNextStep"
                            >
                                Próximo
                            </button>
                            <button
                                v-else
                                type="button"
                                class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="supplierForm.processing"
                                @click="submitSupplier"
                            >
                                {{ supplierForm.processing ? 'Salvando...' : 'Salvar' }}
                            </button>
                        </div>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir fornecedor"
            message="Tem certeza que deseja excluir este fornecedor?"
            :item-label="supplierToDelete?.name ? `Fornecedor: ${supplierToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="removeSupplier"
        />
    </AuthenticatedLayout>
</template>

