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
import { Users2, UserPlus2, MapPin, AlertCircle, Search, Filter, Plus, Pencil, Trash2 } from 'lucide-vue-next';
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
    clients: {
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

const wizardSteps = ['Dados do cliente', 'Endereço'];
const currentStep = ref(1);
const cepLookupLoading = ref(false);
const cepLookupError = ref('');
const wizardValidationRequested = ref(false);

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
        route('admin.clients.index'),
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

const rows = computed(() => props.clients?.data ?? []);
const paginationLinks = computed(() => props.clients?.links ?? []);

const statsCards = computed(() => [
    { key: 'total', label: 'Clientes cadastrados', value: String(props.stats?.total ?? 0), icon: Users2, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Clientes ativos', value: String(props.stats?.active ?? 0), icon: UserPlus2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'new_month', label: 'Novos no mês', value: String(props.stats?.new_month ?? 0), icon: AlertCircle, tone: 'bg-blue-100 text-blue-700' },
    { key: 'cities', label: 'Cidades atendidas', value: String(props.stats?.cities ?? 0), icon: MapPin, tone: 'bg-amber-100 text-amber-700' },
]);

const showModal = ref(false);
const editingClient = ref(null);
const showDeleteModal = ref(false);
const clientToDelete = ref(null);

const buildClientDefaults = () => ({
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
    is_active: true,
});

const clientForm = useForm(buildClientDefaults());
const deleteForm = useForm({});

const isEditing = computed(() => Boolean(editingClient.value?.id));
const isFirstStep = computed(() => currentStep.value === 1);
const isLastStep = computed(() => currentStep.value === wizardSteps.length);

const isWizardStepValid = (stepNumber) => {
    if (stepNumber === 1) {
        return String(clientForm.name ?? '').trim() !== '';
    }

    return true;
};

const clearStepLocalErrors = (stepNumber) => {
    if (stepNumber === 1) {
        clientForm.clearErrors('name');
    }
};

const applyStepLocalErrors = (stepNumber) => {
    if (stepNumber === 1 && String(clientForm.name ?? '').trim() === '') {
        clientForm.setError('name', 'Informe o nome do cliente.');
    }
};

const validateCurrentStepForCreate = () => {
    if (isEditing.value) return true;

    const step = currentStep.value;
    wizardValidationRequested.value = true;
    clearStepLocalErrors(step);

    if (isWizardStepValid(step)) return true;

    applyStepLocalErrors(step);
    return false;
};

const stepErrorKeyMap = {
    1: ['name', 'email', 'phone', 'document_type', 'document', 'is_active'],
    2: ['cep', 'street', 'number', 'complement', 'neighborhood', 'city', 'state'],
};

const hasFormErrorForStep = (stepNumber) => {
    const keys = Object.keys(clientForm.errors ?? {});
    const prefixes = stepErrorKeyMap[stepNumber] ?? [];

    return keys.some((key) => prefixes.some((prefix) => key === prefix || key.startsWith(`${prefix}.`)));
};

const shouldShowStepErrors = computed(() =>
    isEditing.value
    || wizardValidationRequested.value
    || Object.keys(clientForm.errors ?? {}).length > 0,
);

const wizardStepErrors = computed(() =>
    wizardSteps.map((_, index) => {
        const stepNumber = index + 1;
        if (!shouldShowStepErrors.value) return false;

        const checkLocalValidation = isEditing.value || stepNumber <= currentStep.value;
        const hasLocalError = checkLocalValidation ? !isWizardStepValid(stepNumber) : false;

        return hasLocalError || hasFormErrorForStep(stepNumber);
    }),
);

const resetWizard = () => {
    currentStep.value = 1;
    cepLookupError.value = '';
};

const openCreate = () => {
    editingClient.value = null;
    clientForm.defaults(buildClientDefaults());
    clientForm.reset();
    clientForm.clearErrors();
    clientForm.is_active = true;
    wizardValidationRequested.value = false;
    resetWizard();
    showModal.value = true;
};

const openEdit = (client) => {
    editingClient.value = client;
    clientForm.document_type = detectDocumentTypeBR(client.document ?? '');
    clientForm.name = client.name ?? '';
    clientForm.email = client.email ?? '';
    clientForm.phone = client.phone ?? '';
    clientForm.document = formatDocumentByTypeBR(client.document ?? '', clientForm.document_type);
    clientForm.cep = client.cep ?? '';
    clientForm.street = client.street ?? '';
    clientForm.number = client.number ?? '';
    clientForm.complement = client.complement ?? '';
    clientForm.neighborhood = client.neighborhood ?? '';
    clientForm.city = client.city ?? '';
    clientForm.state = client.state ?? '';
    clientForm.is_active = Boolean(client.is_active);
    clientForm.clearErrors();
    wizardValidationRequested.value = false;
    resetWizard();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingClient.value = null;
    clientForm.clearErrors();
    clientForm.defaults(buildClientDefaults());
    clientForm.reset();
    wizardValidationRequested.value = false;
    resetWizard();
};

const onPhoneInput = (event) => {
    clientForm.phone = formatPhoneBR(event?.target?.value ?? clientForm.phone);
    clientForm.clearErrors('phone');
};

const onDocumentInput = (event) => {
    clientForm.document = formatDocumentByTypeBR(event?.target?.value ?? clientForm.document, clientForm.document_type);
    clientForm.clearErrors('document');
};

const onDocumentTypeChange = () => {
    clientForm.document = formatDocumentByTypeBR(clientForm.document, clientForm.document_type);
    clientForm.clearErrors('document');
};

const onCepInput = (event) => {
    clientForm.cep = formatCepBR(event?.target?.value ?? clientForm.cep);
    clientForm.clearErrors('cep');
};

const lookupCep = async () => {
    cepLookupError.value = '';
    clientForm.cep = formatCepBR(clientForm.cep);

    if (!clientForm.cep) return;
    if (clientForm.cep.length !== 9) {
        cepLookupError.value = 'CEP inválido. Digite os 8 números.';
        return;
    }

    cepLookupLoading.value = true;
    const cepDigits = clientForm.cep.replace(/\D/g, '');

    try {
        const response = await fetch(`https://viacep.com.br/ws/${cepDigits}/json/`);
        if (!response.ok) throw new Error('lookup_failed');

        const payload = await response.json();
        if (payload?.erro) {
            cepLookupError.value = 'CEP não encontrado.';
            return;
        }

        const parsed = viaCepToAddress(payload);
        clientForm.cep = parsed.cep || clientForm.cep;
        clientForm.street = parsed.street || clientForm.street;
        clientForm.neighborhood = parsed.neighborhood || clientForm.neighborhood;
        clientForm.city = parsed.city || clientForm.city;
        clientForm.state = parsed.state || clientForm.state;

        if (!String(clientForm.complement ?? '').trim()) {
            clientForm.complement = parsed.complement || '';
        }
    } catch {
        cepLookupError.value = 'Não foi possível consultar o ViaCEP agora. Preencha manualmente.';
    } finally {
        cepLookupLoading.value = false;
    }
};

const goNextStep = () => {
    if (!validateCurrentStepForCreate()) return;
    currentStep.value = Math.min(wizardSteps.length, currentStep.value + 1);
};

const goPreviousStep = () => {
    currentStep.value = Math.max(1, currentStep.value - 1);
};

const setWizardStep = (step) => {
    if (!isEditing.value) return;

    const parsedStep = Number(step);
    if (!Number.isFinite(parsedStep)) return;

    currentStep.value = Math.min(wizardSteps.length, Math.max(1, Math.floor(parsedStep)));
};

const submitClient = () => {
    cepLookupError.value = '';
    clientForm.phone = formatPhoneBR(clientForm.phone);
    clientForm.document = formatDocumentByTypeBR(clientForm.document, clientForm.document_type);
    clientForm.cep = formatCepBR(clientForm.cep);
    clientForm.state = normalizeStateCode(clientForm.state);

    if (clientForm.phone && !isValidPhoneMaskBR(clientForm.phone)) {
        clientForm.setError('phone', 'Informe o telefone no formato (11) 99999-9999.');
        return;
    }

    if (clientForm.document && !isValidDocumentByTypeBR(clientForm.document, clientForm.document_type)) {
        const example = clientForm.document_type === 'cnpj'
            ? '00.000.000/0000-00'
            : '000.000.000.00';
        clientForm.setError('document', `Informe o documento no formato ${example}.`);
        return;
    }

    if (clientForm.cep && !isValidCepMaskBR(clientForm.cep)) {
        clientForm.setError('cep', 'Informe o CEP no formato 00000-000.');
        return;
    }

    if (isEditing.value) {
        clientForm.put(route('admin.clients.update', editingClient.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    clientForm.post(route('admin.clients.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const openDeleteModal = (client) => {
    clientToDelete.value = client;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    clientToDelete.value = null;
};

const removeClient = () => {
    if (!clientToDelete.value?.id) return;

    deleteForm.delete(route('admin.clients.destroy', clientToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Clientes" :show-table-view-toggle="false">
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
                            placeholder="Buscar cliente por nome, e-mail, telefone ou cidade"
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
                            Novo cliente
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
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Contato</th>
                                <th class="px-4 py-3">Documento</th>
                                <th class="px-4 py-3">Cidade</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!rows.length">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhum cliente cadastrado para este contratante.
                                </td>
                            </tr>
                            <tr v-for="client in rows" :key="client.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ client.name }}</p>
                                    <p class="text-xs text-slate-500">Criado em {{ client.created_at }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ client.email || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ client.phone || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ client.document || '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ client.city || '-' }}<span v-if="client.state">/{{ client.state }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="client.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ client.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="openEdit(client)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="openDeleteModal(client)"
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
                :title="isEditing ? 'Editar cliente' : 'Novo cliente'"
                description="Preencha os dados do cliente."
                :steps="wizardSteps"
                :current-step="currentStep"
                :steps-clickable="isEditing"
                :step-errors="wizardStepErrors"
                @step-change="setWizardStep"
                @close="closeModal"
            >
                <div v-if="currentStep === 1" class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input
                            v-model="clientForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Maria Souza"
                        >
                        <p v-if="clientForm.errors.name" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                        <input
                            v-model="clientForm.email"
                            type="email"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="cliente@email.com"
                        >
                        <p v-if="clientForm.errors.email" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                        <input
                            :value="clientForm.phone"
                            type="text"
                            inputmode="numeric"
                            maxlength="15"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="(11) 99999-9999"
                            @input="onPhoneInput"
                        >
                        <p v-if="clientForm.errors.phone" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.phone }}</p>
                    </div>

                    <div class="md:col-span-2 grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo de documento</label>
                            <UiSelect
                                v-model="clientForm.document_type"
                                :options="documentTypeOptions"
                                button-class="mt-1 w-full text-sm"
                                @change="onDocumentTypeChange"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documento</label>
                            <input
                                :value="clientForm.document"
                                type="text"
                                inputmode="numeric"
                                :maxlength="clientForm.document_type === 'cnpj' ? 18 : 14"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                :placeholder="clientForm.document_type === 'cnpj' ? '00.000.000/0000-00' : '000.000.000.00'"
                                @input="onDocumentInput"
                            >
                            <p v-if="clientForm.errors.document" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.document }}</p>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:col-span-2">
                        <input v-model="clientForm.is_active" type="checkbox" class="rounded border-slate-300">
                        Cliente ativo
                    </label>
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="min-w-[180px] flex-1">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">CEP</label>
                                <input
                                    :value="clientForm.cep"
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
                        <p v-if="clientForm.errors.cep" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.cep }}</p>
                        <p v-if="cepLookupError" class="mt-2 text-xs font-semibold text-amber-700">{{ cepLookupError }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rua</label>
                        <input v-model="clientForm.street" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Logradouro">
                        <p v-if="clientForm.errors.street" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.street }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Número</label>
                        <input v-model="clientForm.number" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="123">
                        <p v-if="clientForm.errors.number" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.number }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Complemento</label>
                        <input v-model="clientForm.complement" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Apto, bloco, etc">
                        <p v-if="clientForm.errors.complement" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.complement }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bairro</label>
                        <input v-model="clientForm.neighborhood" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Bairro">
                        <p v-if="clientForm.errors.neighborhood" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.neighborhood }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cidade</label>
                        <input v-model="clientForm.city" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Cidade">
                        <p v-if="clientForm.errors.city" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.city }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">UF</label>
                        <UiSelect v-model="clientForm.state" :options="stateOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="clientForm.errors.state" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.state }}</p>
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
                                @click="goNextStep"
                            >
                                Próximo
                            </button>
                            <button
                                v-else
                                type="button"
                                class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="clientForm.processing"
                                @click="submitClient"
                            >
                                {{ clientForm.processing ? 'Salvando...' : 'Salvar' }}
                            </button>
                        </div>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir cliente"
            message="Tem certeza que deseja excluir este cliente?"
            :item-label="clientToDelete?.name ? `Cliente: ${clientToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="removeClient"
        />
    </AuthenticatedLayout>
</template>
