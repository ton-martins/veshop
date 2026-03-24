<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PaginationControls from '@/Components/Ui/PaginationControls.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { AlertTriangle, LayoutGrid, Plus, Table } from 'lucide-vue-next';
import { useBranding } from '@/branding';
import { emitActionBlocked } from '@/action-blocker';

const props = defineProps({
    processings: {
        type: Object,
        default: () => ({ data: [] }),
    },
    processTypes: {
        type: Array,
        default: () => [],
    },
    clients: {
        type: Array,
        default: () => [],
    },
    suppliers: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
    contractors: {
        type: Array,
        default: () => [],
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
    currentContractorId: {
        type: Number,
        default: null,
    },
});

const { glassGradient } = useBranding();
const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? null);
const currentUserId = computed(() => currentUser.value?.id ?? null);
const currentContractor = computed(() => page.props.contractor?.currentContractor ?? null);
const planAccess = computed(() => page.props.contractor?.planAccess ?? null);
const contractExpired = computed(() => planAccess.value?.contract_expired ?? false);
const contractEndsAt = computed(() => planAccess.value?.contract_ends_at ?? null);
const isMaster = computed(() => page.props.auth?.user?.role === 'master');
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const isAdminOrMaster = computed(() => isMaster.value || isAdmin.value);
const canOperate = computed(() => page.props.auth?.can?.operateProcesses ?? false);
const contractorLogo = computed(() => currentContractor.value?.brand_logo_url ?? currentContractor.value?.brand_avatar_url ?? null);
const contractorDisplayName = computed(() => currentContractor.value?.brand_name ?? currentContractor.value?.name ?? 'Contratante');
const getContractorInitials = (value) => {
    const safe = String(value ?? '').trim();
    if (!safe) return 'CT';
    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
    const initials = `${first}${last}`.trim();
    return initials ? initials.toUpperCase() : 'CT';
};
const contractorInitials = computed(() => getContractorInitials(contractorDisplayName.value));
const summaryScopeLabel = computed(() =>
    isMaster.value
        ? 'Indicadores da operação.'
        : `Indicadores do contratante`
);
const todayDate = computed(() => {
    const now = new Date();
    const offsetMs = now.getTimezoneOffset() * 60000;
    return new Date(now.getTime() - offsetMs).toISOString().slice(0, 10);
});
const selectedDate = ref(new Date());
const onlyDigits = (value) => String(value ?? '').replace(/\D+/g, '');

const formatCpf = (value) => {
    const digits = onlyDigits(value).slice(0, 11);
    return digits
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
};

const formatCnpj = (value) => {
    const digits = onlyDigits(value).slice(0, 14);
    return digits
        .replace(/(\d{2})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1/$2')
        .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
};

const formatCurrencyBRL = (value) => {
    const digits = onlyDigits(value);
    if (!digits) return '';
    const number = parseInt(digits, 10);
    const [integers, cents] = (number / 100).toFixed(2).split('.');
    const formattedIntegers = integers.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `R$ ${formattedIntegers},${cents}`;
};

const normalizeCurrencyBRL = (value) => {
    const digits = onlyDigits(value);
    if (!digits) return '';
    const number = parseInt(digits, 10);
    return (number / 100).toFixed(2);
};

const defaultPlaceholderByType = (type) => {
    switch (type) {
        case 'cpf':
            return '000.000.000-00';
        case 'cnpj':
            return '00.000.000/0000-00';
        case 'monetary':
            return 'R$ 0,00';
        case 'decimal':
            return '0,00';
        case 'number':
            return '0';
        case 'email':
            return 'ex.: nome@dominio.com';
        case 'date':
            return 'Selecione a data';
        case 'client_selector':
            return 'Selecione o cliente';
        case 'supplier_selector':
            return 'Selecione o fornecedor';
        case 'textarea':
            return 'Descreva aqui';
        case 'text':
        default:
            return 'Digite aqui';
    }
};

const resolveFieldPlaceholder = (field) => field?.placeholder || defaultPlaceholderByType(field?.type ?? 'text');

const applyFieldMask = (field) => {
    if (!field?.key) return;
    const type = field.type;
    if (type === 'cpf') {
        form.fields[field.key] = formatCpf(form.fields[field.key]);
    } else if (type === 'cnpj') {
        form.fields[field.key] = formatCnpj(form.fields[field.key]);
    } else if (type === 'monetary') {
        form.fields[field.key] = formatCurrencyBRL(form.fields[field.key]);
    }
};

// Lista de processos (instancias abertas) para o grid.
const processingsData = computed(() => props.processings?.data ?? []);

const statusMetrics = computed(() => {
    return processingsData.value.reduce(
        (acc, processing) => {
            const status = processing.status;
            if (status === 'in_progress') acc.running += 1;
            if (status === 'completed') acc.completed += 1;
            return acc;
        },
        { running: 0, completed: 0 }
    );
});

const totalProcessings = computed(() => props.processings?.total ?? processingsData.value.length);
const runningCount = computed(() => statusMetrics.value.running);
const completedCount = computed(() => statusMetrics.value.completed);

const providersList = computed(() => {
    const set = new Set();
    processingsData.value.forEach((processing) => {
        if (processing.current_step?.name) set.add(processing.current_step.name);
    });
    return Array.from(set);
});

const VIEW_MODE_KEY = 'flowap.processes.viewMode';
const viewMode = ref('table');
const isCardView = computed(() => viewMode.value === 'cards');
const cardListClass = computed(() => {
    if (isCardView.value) {
        return 'grid gap-3 px-4 py-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';
    }
    return 'space-y-3 px-4 py-4 md:hidden';
});

const setViewMode = (mode) => {
    if (!['table', 'cards'].includes(mode)) return;
    viewMode.value = mode;
};

const operationTeams = computed(() => (props.operationTeams ?? []).filter((team) => team.is_active));
const responsibleUsers = computed(() => (props.responsibleUsers ?? []).filter((user) => user.is_active));
const userTeamIds = computed(() => (props.userTeamIds ?? []).map((value) => Number(value)));

const availableResponsibleUsers = computed(() => {
    if (isAdminOrMaster.value) return responsibleUsers.value;
    if (!currentUserId.value) return [];
    return responsibleUsers.value.filter((user) => Number(user.id) === Number(currentUserId.value));
});

const availableTeams = computed(() => {
    if (isAdminOrMaster.value) return operationTeams.value;
    if (!userTeamIds.value.length) return [];
    return operationTeams.value.filter((team) => userTeamIds.value.includes(Number(team.id)));
});

const filters = reactive({
    search: '',
    status: props.filters?.status ?? 'all',
    provider: 'all',
    responsible: 'all',
    team: 'all',
});

const matchesStatus = (processingStatus, filterStatus) => {
    return processingStatus === filterStatus;
};

const isAssignedToMe = (processing) => (
    currentUserId.value && Number(processing.assigned_to_user_id) === Number(currentUserId.value)
);

const formatUserName = (user) => {
    const name = user?.name ?? '';
    if (!name) return '';
    if (currentUserId.value && Number(user.id) === Number(currentUserId.value)) {
        return `${name} (você)`;
    }
    return name;
};

const isAssignedToMyTeam = (processing) => (
    processing.assigned_team_id && userTeamIds.value.includes(Number(processing.assigned_team_id))
);

const resolveResponsibleLabel = (processing) => {
    if (processing.assigned_user?.name) {
        return formatUserName(processing.assigned_user) || processing.assigned_user.name;
    }
    if (processing.assigned_team?.name) {
        return `Time: ${processing.assigned_team.name}`;
    }
    return 'Sem responsável';
};

const assignmentHighlightClass = (processing) => {
    if (isAssignedToMe(processing)) {
        return 'bg-emerald-50/70';
    }
    if (isAssignedToMyTeam(processing)) {
        return 'bg-sky-50/70';
    }
    return '';
};

const filteredProcessings = computed(() => {
    const searchTerm = filters.search.trim().toLowerCase();

    return processingsData.value.filter((processing) => {
        const matchesSearch =
            !searchTerm ||
            (processing.reference && processing.reference.toLowerCase().includes(searchTerm)) ||
            (processing.process_type?.name && processing.process_type.name.toLowerCase().includes(searchTerm));

        const matchesStatusFilter =
            filters.status === 'all' || matchesStatus(processing.status, filters.status);

        const matchesProviderFilter =
            filters.provider === 'all' || processing.current_step?.name === filters.provider;

        const assignedUserId = processing.assigned_to_user_id ?? null;
        const assignedTeamId = processing.assigned_team_id ?? null;

        const matchesResponsibleFilter = (() => {
            if (filters.responsible === 'all') return true;
            if (filters.responsible === 'unassigned') return !assignedUserId;
            return Number(assignedUserId) === Number(filters.responsible);
        })();

        const matchesTeamFilter = (() => {
            if (filters.team === 'all') return true;
            if (filters.team === 'unassigned') return !assignedTeamId;
            return Number(assignedTeamId) === Number(filters.team);
        })();

        return matchesSearch
            && matchesStatusFilter
            && matchesProviderFilter
            && matchesResponsibleFilter
            && matchesTeamFilter;
    });
});

const filteredCount = computed(() => filteredProcessings.value.length);

const currentPage = ref(1);
const pageSize = ref(12);
const totalPages = computed(() => Math.max(1, Math.ceil(filteredProcessings.value.length / pageSize.value)));
const paginatedProcessings = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return filteredProcessings.value.slice(start, start + pageSize.value);
});

watch(
    () => [filters.search, filters.status, filters.provider, filters.responsible, filters.team],
    () => {
        currentPage.value = 1;
    }
);

onMounted(() => {
    try {
        const stored = window.localStorage.getItem(VIEW_MODE_KEY);
        if (stored === 'table' || stored === 'cards') {
            viewMode.value = stored;
        }
    } catch {
        // ignore
    }
});

watch(viewMode, (value) => {
    try {
        window.localStorage.setItem(VIEW_MODE_KEY, value);
    } catch {
        // ignore
    }
});

watch(totalPages, (value) => {
    if (currentPage.value > value) {
        currentPage.value = value;
    }
});

const applySummaryFilter = (status) => {
    filters.status = status;
    filters.search = '';
    filters.provider = 'all';
    filters.responsible = 'all';
    filters.team = 'all';
};

const processTypes = computed(() => props.processTypes ?? []);
const rawClients = computed(() => props.clients ?? []);
const mapProduct = (product) => ({
    id: product.id,
    label: product.name ?? `Produto #${product.id}`,
    sku: product.sku ?? '',
    category: product.category ?? '',
    price: product.price ?? null,
    image_url: product.image_url ?? null,
    pivot: product.pivot ? {
        quantity: product.pivot.quantity ?? null,
        quantity_in_use: product.pivot.quantity_in_use ?? 0,
        quantity_used: product.pivot.quantity_used ?? 0,
    } : null,
});
const clientOptions = computed(() => rawClients.value.map((client) => ({
    id: client.id,
    label: client.brand_name ?? client.name ?? `Cliente #${client.id}`,
})));
const clientProductsMap = computed(() => {
    const map = {};
    rawClients.value.forEach((client) => {
        map[String(client.id)] = Array.isArray(client.products) ? client.products.map(mapProduct) : [];
    });
    return map;
});
const supplierOptions = computed(() => (props.suppliers ?? []).map((supplier) => ({
    id: supplier.id,
    label: supplier.brand_name ?? supplier.name ?? `Fornecedor #${supplier.id}`,
})));
const productOptions = computed(() => (props.products ?? []).map(mapProduct));
const productLookup = computed(() => {
    const map = {};
    productOptions.value.forEach((product) => {
        map[String(product.id)] = product;
    });
    return map;
});

// Formulario de abertura de processo (campos + documentos).
const form = useForm({
    process_type_id: '',
    reference: '',
    fields: {},
    documents: {},
    product_ids: [],
    product_client_id: '',
});

const selectedProcessType = computed(() => {
    const id = form.process_type_id;
    return processTypes.value.find((type) => String(type.uuid ?? type.id) === String(id));
});

const processTypePreviewDescription = computed(() => {
    const description = selectedProcessType.value?.description ?? '';
    const normalized = String(description).replace(/\s+/g, ' ').trim();
    if (!normalized) return '';
    if (normalized.length <= 220) return normalized;
    return `${normalized.slice(0, 220)}`;
});

const processTypeDetailUrl = computed(() => {
    const type = selectedProcessType.value;
    if (!type || typeof route !== 'function') return null;
    return route('process-definitions.show', type.uuid ?? type.id);
});

const formSchema = computed(() => selectedProcessType.value?.form?.schema ?? {});
const schemaFields = computed(() => formSchema.value?.fields ?? []);
const schemaWizardSteps = computed(() => formSchema.value?.wizard?.steps ?? []);

const productSelectorField = computed(() => (
    schemaFields.value.find((field) => field?.type === 'product_selector') ?? null
));
const clientProductSelectorField = computed(() => (
    schemaFields.value.find((field) => field?.type === 'client_product_selector') ?? null
));
const activeProductField = computed(() => clientProductSelectorField.value || productSelectorField.value);
const hasProductSelector = computed(() => Boolean(activeProductField.value));
const productMultiple = computed(() => Boolean(activeProductField.value?.multiple));
const productRequired = computed(() => Boolean(activeProductField.value?.required));
const productRequiresClient = computed(() => Boolean(clientProductSelectorField.value));
const selectedProductIds = computed(() => (
    Array.isArray(form.product_ids) ? form.product_ids.filter(Boolean) : []
));
const selectedProductItems = computed(() => selectedProductIds.value
    .map((id) => productLookup.value[String(id)])
    .filter(Boolean)
);
const singleProductId = computed({
    get: () => selectedProductIds.value[0] ?? '',
    set: (value) => {
        form.product_ids = value ? [value] : [];
    },
});
const hasSelectedProducts = computed(() => selectedProductIds.value.length > 0);
const clientProductOptions = computed(() => {
    if (!form.product_client_id) return [];
    return clientProductsMap.value[String(form.product_client_id)] ?? [];
});
const resolveAvailableQuantity = (product) => {
    const pivot = product?.pivot ?? null;
    const totalRaw = pivot?.quantity ?? null;
    if (totalRaw === null || totalRaw === undefined || totalRaw === '') return 0;
    const total = Number(totalRaw);
    if (Number.isNaN(total)) return 0;
    const inUse = Number(pivot?.quantity_in_use ?? 0) || 0;
    const used = Number(pivot?.quantity_used ?? 0) || 0;
    return Math.max(total - inUse - used, 0);
};
const isUnavailableProduct = (product) => {
    const available = resolveAvailableQuantity(product);
    return available !== null && available <= 0;
};
const productSelectionValid = computed(() => {
    if (!hasProductSelector.value) return true;
    if (productRequired.value && !hasSelectedProducts.value) return false;
    if (!productMultiple.value && selectedProductIds.value.length > 1) return false;
    if (productRequiresClient.value && (productRequired.value || hasSelectedProducts.value) && !form.product_client_id) return false;
    return true;
});
const productClientLabel = computed(() => {
    if (!form.product_client_id) return '';
    const match = clientOptions.value.find((client) => String(client.id) === String(form.product_client_id));
    return match?.label ?? '';
});

const normalizedWizardSteps = computed(() => {
    if (schemaWizardSteps.value.length) {
        return schemaWizardSteps.value.map((step, index) => ({
            key: String(step?.key ?? `etapa_${index + 1}`),
            label: String(step?.label ?? `Etapa ${index + 1}`),
            description: step?.description ?? '',
            index: index + 1,
        }));
    }

    return [
        {
            key: 'etapa_1',
            label: 'Etapa 1',
            description: '',
            index: 1,
        },
    ];
});

const groupedFields = computed(() => {
    const grouped = {};
    normalizedWizardSteps.value.forEach((step) => {
        grouped[step.key] = [];
    });

    schemaFields.value.forEach((field) => {
        const target = field.wizard_step_key || normalizedWizardSteps.value[0]?.key || 'default';
        if (!grouped[target]) grouped[target] = [];
        grouped[target].push(field);
    });

    return grouped;
});

const formWizardIndex = ref(0);
const formWizardTotal = computed(() => normalizedWizardSteps.value.length || 1);
const currentFormWizardStep = computed(() => normalizedWizardSteps.value[formWizardIndex.value] ?? null);
const currentFormWizardKey = computed(
    () => currentFormWizardStep.value?.key ?? normalizedWizardSteps.value[0]?.key ?? null
);
const currentFormWizardFields = computed(() => {
    if (!currentFormWizardKey.value) return [];
    return groupedFields.value[currentFormWizardKey.value] ?? [];
});

const headerWizardSteps = computed(() => {
    if (selectedProcessType.value && normalizedWizardSteps.value.length) {
        return normalizedWizardSteps.value.map((step) => ({
            key: step.index,
            title: step.label,
            description: step.description,
        }));
    }

    return wizardSteps;
});

const headerWizardActive = computed(() => {
    if (selectedProcessType.value && normalizedWizardSteps.value.length) {
        if (wizardStep.value === 2) {
            return headerWizardSteps.value[formWizardIndex.value]?.key ?? 1;
        }
        return headerWizardSteps.value[0]?.key ?? 1;
    }

    return wizardStep.value;
});

const extractFormFieldKey = (errorKey) => {
    const match = String(errorKey ?? '').match(/^(fields|documents)\.(.+)$/);
    return match ? match[2] : null;
};

const collectFormFieldErrors = (errors) => {
    const fieldKeys = new Set();
    Object.keys(errors ?? {}).forEach((key) => {
        const fieldKey = extractFormFieldKey(key);
        if (fieldKey) fieldKeys.add(fieldKey);
    });
    return fieldKeys;
};

const resolveWizardStepArrayIndex = (field) => {
    const fallbackKey = normalizedWizardSteps.value[0]?.key ?? null;
    const wizardKey = field?.wizard_step_key ?? fallbackKey;
    const index = normalizedWizardSteps.value.findIndex((step) => String(step.key) === String(wizardKey));
    return index === -1 ? 0 : index;
};

const resolveWizardStepHeaderKey = (field) => {
    const index = resolveWizardStepArrayIndex(field);
    return normalizedWizardSteps.value[index]?.index ?? 1;
};

const productWizardStepKey = computed(() => {
    if (!activeProductField.value) {
        return normalizedWizardSteps.value[0]?.index ?? 1;
    }
    return resolveWizardStepHeaderKey(activeProductField.value);
});
const hasProductErrors = computed(() => (
    Object.keys(form.errors ?? {}).some((key) => key === 'product_client_id' || key.startsWith('product_ids'))
));

const formWizardErrors = computed(() => {
    const map = {};
    normalizedWizardSteps.value.forEach((step) => {
        map[step.index] = false;
    });
    const errorFields = collectFormFieldErrors(form.errors);
    const baseError = Object.keys(form.errors ?? {}).some((key) => (
        ['process_type_id', 'reference', 'form'].includes(key)
    ));
    if (baseError) {
        const firstKey = normalizedWizardSteps.value[0]?.index ?? 1;
        map[firstKey] = true;
    }
    if (hasProductErrors.value) {
        const targetKey = hasProductSelector.value ? productWizardStepKey.value : (normalizedWizardSteps.value[0]?.index ?? 1);
        map[targetKey] = true;
    }
    if (!errorFields.size) return map;
    schemaFields.value.forEach((field) => {
        if (!field?.key) return;
        if (errorFields.has(field.key)) {
            const stepKey = resolveWizardStepHeaderKey(field);
            map[stepKey] = true;
        }
    });
    return map;
});

const baseWizardErrors = computed(() => {
    const errors = form.errors ?? {};
    const keys = Object.keys(errors);
    const productError = keys.some((key) => key.startsWith('product_ids') || key === 'product_client_id');
    const productStep = hasProductSelector.value ? 2 : 1;
    return {
        1: ['process_type_id', 'reference', 'form']
            .some((key) => Object.prototype.hasOwnProperty.call(errors, key))
            || (productError && productStep === 1),
        2: keys.some((key) => key.startsWith('fields.') || key.startsWith('documents.'))
            || (productError && productStep === 2),
        3: false,
    };
});

const headerWizardErrors = computed(() => {
    if (selectedProcessType.value && normalizedWizardSteps.value.length) {
        return formWizardErrors.value;
    }
    return baseWizardErrors.value;
});

const canJumpFormWizard = computed(() => wizardStep.value === 2 && formWizardTotal.value > 1);

// Permite navegar diretamente entre as etapas do wizard do formulario.
const jumpToFormWizardStep = (stepKey) => {
    if (!canJumpFormWizard.value) return;
    const nextIndex = normalizedWizardSteps.value.findIndex((step) => step.index === stepKey);
    if (nextIndex === -1) return;
    formWizardIndex.value = nextIndex;
};

const resetFormFields = () => {
    if (!selectedProcessType.value) {
        form.fields = {};
        form.documents = {};
        formWizardIndex.value = 0;
        form.product_ids = [];
        form.product_client_id = '';
        return;
    }

    const nextFields = {};
    schemaFields.value.forEach((field) => {
        if (!field?.key) return;
        if (['product_selector', 'client_product_selector'].includes(field.type)) return;
        nextFields[field.key] = field.type === 'checkbox' ? false : '';
    });

    form.fields = nextFields;
    form.documents = {};
    formWizardIndex.value = 0;
    form.product_ids = [];
    form.product_client_id = '';
};

watch(
    () => form.process_type_id,
    () => {
        resetFormFields();
        form.clearErrors();
    }
);

watch(hasProductSelector, (enabled) => {
    if (enabled) return;
    form.product_ids = [];
    form.product_client_id = '';
});

watch(
    () => form.product_ids,
    (value) => {
        if (!hasProductSelector.value || productMultiple.value) return;
        if (Array.isArray(value) && value.length > 1) {
            form.product_ids = value.slice(0, 1);
        }
    },
    { deep: true }
);

watch(
    () => form.product_client_id,
    (value, previous) => {
        if (String(value ?? '') !== String(previous ?? '')) {
            form.product_ids = [];
        }
        if (!productRequiresClient.value && value) {
            form.product_client_id = '';
        }
    }
);

watch(
    normalizedWizardSteps,
    (steps) => {
        if (formWizardIndex.value > steps.length - 1) {
            formWizardIndex.value = 0;
        }
    },
    { immediate: true }
);

const wizardSteps = [
    {
        key: 1,
        title: 'Selecionar workflow',
        description: 'Escolha o tipo de processo e personalize a identificação interna.',
    },
    {
        key: 2,
        title: 'Enviar & classificar',
        description: 'Envie os arquivos, valide a classificação e ajuste metadados.',
    },
    {
        key: 3,
        title: 'Revisar & iniciar',
        description: 'Confirme os dados finais e crie o processo.',
    },
];

const illustrations = [
    {
        title: 'Workflows inteligentes',
        description: 'Criação e orquestração de fluxos automatizados com regras dinâmicas, decisões condicionais e execução contínua.',
        icon: (
            '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" class="h-9 w-9 text-emerald-700"><rect x="12" y="12" width="40" height="40" rx="8"/><path d="M20 32h24M20 23h24M20 41h14"/></svg>'
        ),
    },
    {
        title: 'Gestão de etapas',
        description: 'Cada etapa do processo é monitorada com status, tempos de execução e métricas de desempenho.',
        icon: (
            '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" class="h-9 w-9 text-emerald-700"><path d="M20 18h24l8 14-8 14H20l-8-14 8-14Z"/><path d="M32 24v16M26 32h12"/></svg>'
        ),
    },
    {
        title: 'Monitoramento em tempo real',
        description: 'Visibilidade completa dos processos em execução, gargalhos e violações de SLA.',
        icon: (
            '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" class="h-9 w-9 text-emerald-700"><circle cx="32" cy="32" r="20"/><path d="M32 20v14l8 8"/></svg>'
        ),
    },
];

const isCreateOpen = ref(false);
const planLimitModalType = ref('process');
const wizardStep = ref(1);
const totalSteps = wizardSteps.length;

const planLimitCopy = computed(() => {
    const access = planAccess.value ?? {};
    const type = planLimitModalType.value;
    const planName = access.plan_name ?? 'plano atual';
    const reason = type === 'process' ? access.process_reason : access.user_reason;
    const limit = type === 'process' ? access.process_limit : access.user_limit;
    const used = type === 'process' ? access.processes_used : access.active_users;
    const label = type === 'process' ? 'processo' : 'usuário';
    const labelPlural = type === 'process' ? 'processos' : 'usuários';

    if (reason === 'limit_reached') {
        return {
            title: 'Limite do plano atingido',
            message: `O limite de ${labelPlural} do ${planName} foi atingido (${used}/${limit}). Você não conseguirá criar ${labelPlural} até a renovação do período ou upgrade do plano.`,
        };
    }

    if (reason === 'expired') {
        return {
            title: 'Plano expirado',
            message: `O plano do contratante expirou. Você não conseguirá criar ${labelPlural} até a renovação.`,
        };
    }

    if (reason === 'inactive' || reason === 'no_plan') {
        return {
            title: 'Sem plano ativo',
            message: `Este contratante está sem plano ativo. Você não conseguirá criar ${labelPlural} enquanto o plano não for definido.`,
        };
    }

    return {
        title: 'Acao indisponivel',
        message: `No momento nao e possivel criar ${labelPlural}.`,
    };
});

const notifyBlocked = ({ title, message, endsAt } = {}) => {
    emitActionBlocked({
        title: title || 'Ação bloqueada',
        message: message || 'Contrato encerrado. Renove o contrato para liberar ações.',
        endsAt: endsAt ?? contractEndsAt.value,
    });
};

const blockForContract = () => {
    if (!contractExpired.value) return false;
    notifyBlocked({});
    return true;
};

const openCreate = () => {
    if (!canOperate.value) return;
    if (blockForContract()) return;
    if (planAccess.value && planAccess.value.can_create_process === false) {
        planLimitModalType.value = 'process';
        notifyBlocked(planLimitCopy.value);
        return;
    }
    isCreateOpen.value = true;
    wizardStep.value = 1;
    formWizardIndex.value = 0;
    form.reset('process_type_id', 'reference');
    form.fields = {};
    form.documents = {};
    form.product_ids = [];
    form.product_client_id = '';
    form.clearErrors();
};

const closeCreate = () => {
    isCreateOpen.value = false;
    wizardStep.value = 1;
    formWizardIndex.value = 0;
    form.reset('process_type_id', 'reference');
    form.fields = {};
    form.documents = {};
    form.product_ids = [];
    form.product_client_id = '';
    form.clearErrors();
};

const isFieldFilled = (field) => {
    if (!field?.key) return false;
    if (['product_selector', 'client_product_selector'].includes(field.type)) {
        return productSelectionValid.value;
    }
    if (field.type === 'document') return Boolean(form.documents[field.key]);
    if (field.type === 'checkbox') return Boolean(form.fields[field.key]);
    const value = form.fields[field.key];
    if (typeof value === 'number') return true;
    return String(value ?? '').trim() !== '';
};

const canAdvanceStep = computed(() => {
    if (wizardStep.value === 1) {
        return Boolean(form.process_type_id);
    }

    if (wizardStep.value === 2) {
        const requiredFields = currentFormWizardFields.value.filter((field) => field.required);
        if (!requiredFields.length) return true;
        return requiredFields.every((field) => isFieldFilled(field));
    }

    return true;
});

const goNextStep = () => {
    if (!canAdvanceStep.value) return;

    if (wizardStep.value === 2 && formWizardTotal.value > 1) {
        if (formWizardIndex.value < formWizardTotal.value - 1) {
            formWizardIndex.value += 1;
            return;
        }
    }

    if (wizardStep.value < totalSteps) {
        wizardStep.value += 1;
    }
};

const goPrevStep = () => {
    if (wizardStep.value === 2 && formWizardTotal.value > 1 && formWizardIndex.value > 0) {
        formWizardIndex.value -= 1;
        return;
    }

    if (wizardStep.value > 1) {
        wizardStep.value -= 1;
    }
};

const onFileChange = (event, fieldKey) => {
    const file = event.target.files?.[0] ?? null;
    form.documents[fieldKey] = file;
};

const removeFile = (fieldKey) => {
    form.documents[fieldKey] = null;
};

const resolveFirstFormWizardErrorIndex = (errors) => {
    const errorFields = collectFormFieldErrors(errors);
    if (!errorFields.size) return null;
    let firstIndex = null;
    schemaFields.value.forEach((field) => {
        if (!field?.key) return;
        if (!errorFields.has(field.key)) return;
        const index = resolveWizardStepArrayIndex(field);
        if (firstIndex === null || index < firstIndex) {
            firstIndex = index;
        }
    });
    return firstIndex;
};

// Garante que a etapa correta fique visivel ao retornar erros de validacao.
const handleFormErrors = () => {
    const keys = Object.keys(form.errors ?? {});
    if (!keys.length) return;

    if (keys.some((key) => key.startsWith('fields.') || key.startsWith('documents.'))) {
        wizardStep.value = 2;
        const firstIndex = resolveFirstFormWizardErrorIndex(form.errors);
        if (typeof firstIndex === 'number') {
            formWizardIndex.value = firstIndex;
        }
        return;
    }

    if (keys.some((key) => key === 'product_client_id' || key.startsWith('product_ids'))) {
        if (hasProductSelector.value) {
            wizardStep.value = 2;
            const productIndex = resolveWizardStepArrayIndex(activeProductField.value);
            formWizardIndex.value = typeof productIndex === 'number' ? productIndex : 0;
            return;
        }
        wizardStep.value = 1;
        return;
    }

    if (keys.some((key) => ['process_type_id', 'reference', 'form'].includes(key))) {
        wizardStep.value = 1;
    }
};

const submit = () => {
    if (wizardStep.value !== totalSteps) {
        wizardStep.value = totalSteps;
    }

    if (!canAdvanceStep.value) return;

    form.transform((data) => {
        const normalizedFields = { ...(data.fields ?? {}) };
        schemaFields.value.forEach((field) => {
            if (!field?.key) return;
            if (field.type === 'monetary') {
                normalizedFields[field.key] = normalizeCurrencyBRL(normalizedFields[field.key]);
            }
        });
        return {
            ...data,
            fields: normalizedFields,
        };
    });

    form.post(route('process-instances.store', form.process_type_id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            closeCreate();
            startProcessingsAutoRefresh();
        },
        onError: () => {
            handleFormErrors();
        },
        onFinish: () => {
            form.transform((data) => data);
        },
    });
};

const selectedContractorId = ref(
    props.filters?.contractor_id ??
        props.currentContractorId ??
        props.contractors?.[0]?.id ??
        null
);

watch(
    () => props.filters?.contractor_id,
    (value) => {
        selectedContractorId.value =
            value ?? props.currentContractorId ?? props.contractors?.[0]?.id ?? null;
    },
    { immediate: true }
);

const refreshTimer = ref(null);

// Auto-refresh para manter a lista de processos atualizada.
const startProcessingsAutoRefresh = () => {
    if (refreshTimer.value) {
        window.clearInterval(refreshTimer.value);
    }

    refreshTimer.value = window.setInterval(() => {
        if (document.hidden) return;

        router.reload({
            only: ['processings'],
            preserveState: true,
            preserveScroll: true,
        });
    }, 10000);
};

onMounted(startProcessingsAutoRefresh);

onBeforeUnmount(() => {
    if (refreshTimer.value) {
        window.clearInterval(refreshTimer.value);
    }
});

const changeContractor = () => {
    if (!isMaster.value) return;
    if (!selectedContractorId.value) return;

    router.get(
        route('process-instances.index'),
        { contractor_id: selectedContractorId.value },
        {
            preserveState: true,
            replace: true,
            onFinish: startProcessingsAutoRefresh,
        }
    );
};

const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
const formatNumber = (value) => Number(value ?? 0).toLocaleString('pt-BR');

const statusBadgeClass = (status) => {
    switch (status) {
        case 'completed':
            return 'bg-emerald-100 text-emerald-700';
        case 'pending':
            return 'bg-amber-100 text-amber-700';
        case 'in_progress':
            return 'bg-sky-100 text-sky-700';
        case 'paused':
            return 'bg-orange-100 text-orange-700';
        case 'overdue':
            return 'bg-rose-100 text-rose-700';
        case 'rejected':
            return 'bg-rose-100 text-rose-700';
        case 'cancelled':
            return 'bg-slate-100 text-slate-600';
        default:
            return 'bg-slate-100 text-slate-600';
    }
};

const formatTypeLabel = (type) => {
    switch (type) {
        case 'text':
            return 'Texto';
        case 'textarea':
            return 'Texto longo';
        case 'number':
            return 'Numero inteiro';
        case 'decimal':
            return 'Numero decimal';
        case 'monetary':
            return 'Monetario';
        case 'cpf':
            return 'CPF';
        case 'cnpj':
            return 'CNPJ';
        case 'date':
            return 'Data';
        case 'document':
            return 'Documento';
        case 'email':
            return 'Email';
        case 'select':
            return 'Lista';
        case 'client_selector':
            return 'Cliente';
        case 'supplier_selector':
            return 'Fornecedor';
        case 'product_selector':
            return 'Produto';
        case 'client_product_selector':
            return 'Produto do cliente';
        case 'checkbox':
            return 'Checkbox';
        default:
            return 'Campo';
    }
};
</script>
<template>
    <AuthenticatedLayout>
        <Head title="Processos" />

        <template #header>
            <section class="relative overflow-hidden rounded-3xl px-6 py-8 md:px-10 md:py-10">
                <div class="pointer-events-none absolute inset-0" :style="{ background: glassGradient }" />
                <div class="relative flex flex-col gap-8 md:flex-row md:items-center md:justify-between">
                    <div class="max-w-xl space-y-3">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/85 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200/70">
                            Central de Processos
                        </span>
                        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 md:text-4xl">
                            Gere e acompanhe processos com agilidade e visão consolidada.
                        </h1>
                        <p class="text-sm md:text-base text-slate-600">
                            Utilize filtros, acompanhe etapas e status em tempo real e dispare novos processos com um assistente guiado.
                        </p>
                    </div>
                    <div class="w-full rounded-3xl border border-white/70 bg-white/90 p-6 text-slate-900 shadow-[0_25px_60px_-40px_rgba(15,23,42,0.35)] backdrop-blur lg:w-[640px]">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Resumo</p>
                                <h2 class="text-lg font-semibold text-slate-900">Processos</h2>
                                <p class="text-xs text-slate-500">{{ summaryScopeLabel }}</p>
                            </div>
                            <PrimaryButton v-if="canOperate" type="button" @click="openCreate">
                                <Plus class="h-4 w-4" />
                                Novo processo
                            </PrimaryButton>
                        </div>
                        <div class="mt-6 hidden gap-4 text-center md:grid md:grid-cols-3 md:text-left">
                            <button
                                type="button"
                                class="w-full rounded-2xl border border-emerald-100/70 bg-white px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-200/70"
                                @click="applySummaryFilter('all')"
                            >
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total</p>
                                <p class="text-2xl font-semibold text-slate-900">{{ totalProcessings }}</p>
                                <p class="text-[11px] text-slate-500">Monitorados</p>
                            </button>
                              <button
                                  type="button"
                                  class="w-full rounded-2xl border border-emerald-100/70 bg-emerald-50/60 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-200/70"
                                  @click="applySummaryFilter('in_progress')"
                              >
                                  <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600">Em andamento</p>
                                  <p class="text-2xl font-semibold text-emerald-700">{{ runningCount }}</p>
                                  <p class="text-[11px] text-slate-500">Workflows ativos</p>
                              </button>
                            <button
                                type="button"
                                class="w-full rounded-2xl border border-emerald-100/70 bg-white px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-200/70"
                                @click="applySummaryFilter('completed')"
                            >
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-600">Concluídos</p>
                                <p class="text-2xl font-semibold text-amber-600">{{ completedCount }}</p>
                                <p class="text-[11px] text-slate-500">Finalizados</p>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <section class="rounded-3xl border border-emerald-100 bg-white/95 p-6 shadow-sm backdrop-blur md:px-10 md:py-8">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-emerald-900">Processos</h2>
                    <p class="text-xs text-slate-500">Gerencie execuções, acompanhe status e refine os resultados.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[11px] font-semibold ring-1 transition"
                        :class="isCardView ? 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50' : 'bg-emerald-600 text-white ring-emerald-500'"
                        @click="setViewMode('table')"
                    >
                        <Table class="h-3.5 w-3.5" />
                        Tabela
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[11px] font-semibold ring-1 transition"
                        :class="isCardView ? 'bg-emerald-600 text-white ring-emerald-500' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'"
                        @click="setViewMode('cards')"
                    >
                        <LayoutGrid class="h-3.5 w-3.5" />
                        Cards
                    </button>
                </div>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-5">
                <div class="space-y-1">
                    <label class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Buscar</label>
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Referência ou tipo"
                        autocomplete="off"
                        class="w-full rounded-md border border-emerald-100 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                    />
                </div>
                <div class="space-y-1">
                    <label class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Status</label>
                        <select
                            v-model="filters.status"
                            class="w-full rounded-md border border-emerald-100 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
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
                    <label class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Etapa atual</label>
                    <select
                        v-model="filters.provider"
                        class="w-full rounded-md border border-emerald-100 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                        <option value="all">Todas</option>
                        <option v-for="provider in providersList" :key="provider" :value="provider">
                            {{ provider || 'Sem nome' }}
                        </option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Responsável</label>
                    <select
                        v-model="filters.responsible"
                        class="w-full rounded-md border border-emerald-100 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                        <option value="all">Todos</option>
                        <option value="unassigned">Sem responsável</option>
                        <option
                            v-for="user in availableResponsibleUsers"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ formatUserName(user) || user.name }} - {{ user.role }}
                        </option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Time</label>
                    <select
                        v-model="filters.team"
                        class="w-full rounded-md border border-emerald-100 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
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
        </section>
        <section class="mt-8 overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm">
            <div v-if="paginatedProcessings.length" :class="cardListClass">
                <article
                    v-for="processing in paginatedProcessings"
                    :key="processing.uuid ?? processing.id"
                    class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-emerald-950/90">
                                {{ processing.reference ?? `#${(processing.uuid ?? '').slice(0, 8)}` }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ processing.process_type?.name ?? 'Sem tipo' }}
                            </p>
                        </div>
                        <span :class="statusBadgeClass(processing.status)" class="inline-flex whitespace-nowrap rounded-full px-3 py-1 text-[11px] font-semibold capitalize">
                            {{ processing.status_label ?? processing.status }}
                        </span>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                        <span>Etapa: {{ processing.current_step?.name || 'Sem etapa' }}</span>
                        <span>{{ formatDate(processing.created_at) }}</span>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">
                            {{ resolveResponsibleLabel(processing) }}
                        </span>
                        <span
                            v-if="isAssignedToMe(processing)"
                            class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700 ring-1 ring-emerald-200"
                        >
                            Minha etapa
                        </span>
                        <span
                            v-else-if="isAssignedToMyTeam(processing)"
                            class="rounded-full bg-sky-50 px-2.5 py-1 text-sky-700 ring-1 ring-sky-200"
                        >
                            Meu time
                        </span>
                    </div>
                    <div class="mt-4">
                        <Link
                            :href="route('process-instances.show', processing.uuid ?? processing.id)"
                            class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50"
                        >
                            Detalhes
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none">
                                <path d="M7 5l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </Link>
                    </div>
                </article>
            </div>
            <div v-else :class="isCardView ? 'px-6 py-12 text-center text-sm text-emerald-900/60' : 'px-6 py-12 text-center text-sm text-emerald-900/60 md:hidden'">
                Nenhum processamento encontrado com os filtros aplicados.
            </div>

            <div v-if="!isCardView" class="relative hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-emerald-100 text-sm">
                    <thead class="bg-emerald-50/70 backdrop-blur">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-emerald-800/80">Referência</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-emerald-800/80">Tipo</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-emerald-800/80">Etapa atual</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-emerald-800/80">Responsável</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-emerald-800/80">Status</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-emerald-800/80">Prazo final</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-emerald-800/80">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-emerald-50">
                        <tr
                            v-for="processing in paginatedProcessings"
                            :key="processing.uuid ?? processing.id"
                            class="hover:bg-emerald-50/40"
                            :class="assignmentHighlightClass(processing)"
                        >
                            <td class="px-4 py-3 font-semibold text-emerald-950/90">
                                {{ processing.reference ?? `#${(processing.uuid ?? '').slice(0, 8)}` }}
                            </td>
                            <td class="px-4 py-3 text-emerald-950/90">
                                {{ processing.process_type?.name ?? 'Sem tipo' }}
                            </td>
                            <td class="px-4 py-3 text-emerald-950/90">
                                <div class="text-xs text-slate-500">
                                    {{ processing.current_step?.name || 'Sem etapa' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs font-semibold text-slate-900">
                                        {{ resolveResponsibleLabel(processing) }}
                                    </span>
                                    <span v-if="processing.assigned_user?.name && processing.assigned_team?.name" class="text-[11px] text-slate-500">
                                        Time: {{ processing.assigned_team.name }}
                                    </span>
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-if="isAssignedToMe(processing)"
                                            class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-200"
                                        >
                                            Minha etapa
                                        </span>
                                        <span
                                            v-else-if="isAssignedToMyTeam(processing)"
                                            class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700 ring-1 ring-sky-200"
                                        >
                                            Meu time
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                    <span :class="statusBadgeClass(processing.status)" class="inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold capitalize">
                                        {{ processing.status_label ?? processing.status }}
                                    </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-emerald-950/70">
                                {{ formatDate(processing.due_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="route('process-instances.show', processing.uuid ?? processing.id)"
                                    class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50"
                                >
                                    Detalhes
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none">
                                        <path d="M7 5l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!paginatedProcessings.length">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-emerald-900/60">
                                Nenhum processamento encontrado com os filtros aplicados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PaginationControls
                :current-page="currentPage"
                :total-pages="totalPages"
                :total-items="filteredCount"
                :page-size="pageSize"
                @page-change="(page) => { currentPage = page; }"
            />
        </section>

        <Modal :show="isCreateOpen" @close="closeCreate" max-width="7xl">
            <div class="flex w-full flex-col bg-white">
                <header class="relative overflow-hidden border-b border-white/50" :style="{ background: 'var(--contractor-glass-gradient)' }">
                    <div class="absolute inset-0 bg-white/10" />
                    <div class="relative flex flex-col gap-6 px-6 py-6 md:px-10 md:py-8">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200/80">
                                Novo processo
                            </span>
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-emerald-900/80">
                                Wizard em {{ headerWizardSteps.length }} etapas
                            </span>
                        </div>
                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,320px)]">
                            <div class="space-y-4 text-slate-900">
                                <h2 class="text-[clamp(1.8rem,3vw,2.4rem)] font-semibold tracking-tight">
                                    Configure o fluxo, anexe os arquivos e acompanhe cada fase com transparência.
                                </h2>
                                <p class="text-sm md:text-base text-slate-700">
                                    O wizard organiza o envio dos documentos e informações necessárias para o lançamento do workflow com alertas em tempo real.
                                </p>
                            </div>
                            <div class="flex flex-col items-center gap-4 rounded-2xl bg-white/85 px-6 py-6 text-center text-slate-700 shadow-sm">
                                <div class="w-full rounded-2xl p-3" :style="{ background: 'var(--contractor-primary)' }">
                                    <img
                                        v-if="contractorLogo"
                                        :src="contractorLogo"
                                        :alt="contractorDisplayName"
                                        class="mx-auto max-h-20 w-auto object-contain"
                                    />
                                    <span v-else class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl text-2xl font-semibold text-white">
                                        {{ contractorInitials }}
                                    </span>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contratante</p>
                                    <p class="text-sm font-semibold text-slate-900">{{ contractorDisplayName }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-3">
                            <div
                                v-for="item in illustrations"
                                :key="item.title"
                                class="flex gap-2 rounded-2xl border border-emerald-200/60 bg-white/80 px-3 py-3 text-slate-700 shadow-sm"
                            >
                                <div class="shrink-0" v-html="item.icon" />
                                <div class="text-[11px] leading-relaxed">
                                    <p class="font-semibold text-emerald-900">{{ item.title }}</p>
                                    <p class="mt-1 text-slate-600">{{ item.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="px-6 py-6 md:px-10 md:py-8">
                    <ol class="flex flex-wrap gap-3">
                        <li
                            v-for="step in headerWizardSteps"
                            :key="step.key"
                            class="flex items-center gap-3 rounded-full border px-4 py-2 text-xs transition"
                            :class="[
                                headerWizardErrors[step.key]
                                    ? 'border-rose-300 bg-rose-50 text-rose-700'
                                    : headerWizardActive === step.key
                                        ? 'border-emerald-500 bg-emerald-50 text-emerald-900'
                                        : 'border-slate-200 bg-white text-slate-500',
                                canJumpFormWizard
                                    ? (headerWizardErrors[step.key] ? 'cursor-pointer hover:border-rose-400' : 'cursor-pointer hover:border-emerald-200 hover:bg-emerald-50/60')
                                    : 'cursor-default',
                            ]"
                            :tabindex="canJumpFormWizard ? 0 : -1"
                            :role="canJumpFormWizard ? 'button' : null"
                            @click="jumpToFormWizardStep(step.key)"
                            @keydown.enter.prevent="jumpToFormWizardStep(step.key)"
                            @keydown.space.prevent="jumpToFormWizardStep(step.key)"
                        >
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold"
                                :class="headerWizardErrors[step.key]
                                    ? 'bg-rose-500 text-white'
                                    : headerWizardActive === step.key
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-slate-200 text-slate-700'"
                            >
                                {{ step.key }}
                            </span>
                            <div class="leading-tight">
                                <p class="font-semibold">{{ step.title }}</p>
                                <p v-if="step.description" class="text-[10px]">{{ step.description }}</p>
                            </div>
                        </li>
                    </ol>

                    <form @submit.prevent="submit" class="mt-6 space-y-6">
                        <div
                            v-if="form.errors.form"
                            class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700"
                        >
                            {{ form.errors.form }}
                        </div>
                        <Transition name="fade" mode="out-in">
                            <div v-if="wizardStep === 1" key="wizard-step-1" class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                                <div class="space-y-4">
                                    <div class="space-y-1">
                                        <label class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Tipo de processo</label>
                                        <select
                                            v-model="form.process_type_id"
                                            class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        >
                                            <option value="">Selecione...</option>
                                            <option v-for="type in processTypes" :key="type.id" :value="type.uuid ?? type.id">
                                                {{ type.name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.process_type_id" class="text-[11px] text-rose-600">{{ form.errors.process_type_id }}</p>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div class="space-y-1">
                                            <label class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Etapas no workflow</label>
                                            <div class="rounded-lg border border-emerald-100 bg-emerald-50/40 px-3 py-2 text-xs text-emerald-900">
                                                {{ selectedProcessType?.steps_count ?? selectedProcessType?.steps?.length ?? '' }} etapa(s)
                                            </div>
                                            <p class="text-[11px] text-slate-400">Contagem total de etapas cadastradas.</p>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Versão</label>
                                            <div class="rounded-lg border border-emerald-100 bg-emerald-50/40 px-3 py-2 text-xs text-emerald-900">
                                                v{{ selectedProcessType?.version ?? 1 }}
                                            </div>
                                            <p class="text-[11px] text-slate-400">Histórico do tipo de processo.</p>
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Referência interna</label>
                                        <input
                                            v-model="form.reference"
                                            type="text"
                                            autocomplete="off"
                                            placeholder="ex.: #PROCESSO-2026-001"
                                            class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        />
                                        <p class="text-[11px] text-slate-400">Use para localizar facilmente na lista e nos alertas.</p>
                                        <InputError :message="form.errors.reference" class="mt-2" />
                                    </div>
                                </div>
                                <div class="flex flex-col gap-4">
                                    <div class="rounded-2xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resumo do tipo selecionado</p>
                                        <p class="mt-3 text-sm text-slate-700" v-if="processTypePreviewDescription">
                                            {{ processTypePreviewDescription }}
                                        </p>
                                        <p class="mt-3 text-sm text-slate-500" v-else>
                                            Selecione um tipo de processo para visualizar metas e orientações principais.
                                        </p>
                                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                            <a
                                                v-if="processTypeDetailUrl"
                                                :href="processTypeDetailUrl"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 font-semibold text-white shadow-sm transition hover:bg-slate-800"
                                            >
                                                Ver detalhes
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none">
                                                    <path d="M5 10h10M10 5l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </a>
                                            <span v-else class="rounded-full bg-slate-100 px-3 py-1.5 font-semibold text-slate-500">
                                                Escolha um tipo para acessar instruções específicas.
                                            </span>
                                        </div>
                                    </div>
                                    <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/60 px-4 py-3 text-xs text-emerald-800">
                                        <p class="font-semibold">Dica:</p>
                                        <p class="mt-1">Na próxima etapa, valide com cuidado todas as informações e documentos antes da abertura do processo.</p>
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="wizardStep === 2" key="wizard-step-2" class="space-y-4">
                                <div v-if="!schemaFields.length" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-center text-xs text-slate-500">
                                    Este tipo ainda não possui campos configurados.
                                </div>
                                <div v-else class="space-y-4">
                                    <div
                                        v-if="currentFormWizardStep"
                                        class="rounded-2xl border border-emerald-100 bg-white px-4 py-4"
                                    >
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                                                    {{ currentFormWizardStep.label }}
                                                </p>
                                                <p class="text-[11px] text-slate-400">
                                                    Etapa {{ formWizardIndex + 1 }} de {{ formWizardTotal }}
                                                </p>
                                            </div>
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                {{ currentFormWizardFields.length }} campo(s)
                                            </span>
                                        </div>
                                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                                            <div
                                                v-for="field in currentFormWizardFields"
                                                :key="field.key"
                                                class="rounded-xl border border-emerald-100 bg-emerald-50/20 px-4 py-3"
                                            >
                                                <div class="flex items-center justify-between gap-3">
                                                    <div>
                                                        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                                            {{ field.label || 'Campo' }}<span v-if="field.required" class="text-rose-600"> *</span>
                                                        </label>
                                                        <p class="text-[11px] text-slate-400">{{ formatTypeLabel(field.type) }}</p>
                                                    </div>
                                                    <span v-if="field.type === 'document'" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                        PDF/JPG/XLS/DOC até 50MB
                                                    </span>
                                                </div>

                                                <div class="mt-3 space-y-2">
                                                    <TextInput
                                                        v-if="['text','number','decimal','monetary','cpf','cnpj','email'].includes(field.type)"
                                                        v-model="form.fields[field.key]"
                                                        :type="field.type === 'number' ? 'number' : 'text'"
                                                        :step="field.type === 'decimal' || field.type === 'monetary' ? '0.01' : null"
                                                        :inputmode="['cpf','cnpj','monetary'].includes(field.type) ? 'numeric' : null"
                                                        :maxlength="field.type === 'cpf' ? 14 : field.type === 'cnpj' ? 18 : null"
                                                        :placeholder="resolveFieldPlaceholder(field)"
                                                        class="w-full rounded-lg border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                        @input="() => applyFieldMask(field)"
                                                    />
                                                    <input
                                                        v-else-if="field.type === 'date'"
                                                        v-model="form.fields[field.key]"
                                                        type="date"
                                                        class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    />
                                                    <textarea
                                                        v-else-if="field.type === 'textarea'"
                                                        v-model="form.fields[field.key]"
                                                        rows="3"
                                                        :placeholder="resolveFieldPlaceholder(field)"
                                                        class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    />
                                                    <select
                                                        v-else-if="field.type === 'select'"
                                                        v-model="form.fields[field.key]"
                                                        class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    >
                                                        <option value="">Selecione...</option>
                                                        <option v-for="option in (field.options ?? [])" :key="option" :value="option">
                                                            {{ option }}
                                                        </option>
                                                    </select>
                                                    <div v-else-if="field.type === 'product_selector'" class="space-y-2">
                                                        <div v-if="!productOptions.length" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                                            Nenhum produto ativo cadastrado para este contratante.
                                                        </div>
                                                        <div v-else-if="field.multiple" class="grid gap-2 md:grid-cols-2">
                                                            <label
                                                                v-for="product in productOptions"
                                                                :key="product.id"
                                                                class="flex items-start gap-3 rounded-xl border border-emerald-100 bg-white px-3 py-2 text-xs text-slate-700 shadow-sm transition hover:border-emerald-300"
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    :value="product.id"
                                                                    v-model="form.product_ids"
                                                                    class="mt-0.5 h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500"
                                                                />
                                                                <div class="min-w-0">
                                                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ product.label }}</p>
                                                                    <p class="text-[11px] text-slate-500">
                                                                        {{ product.sku ? `SKU ${product.sku}` : 'SKU não informado' }}
                                                                        <span v-if="product.category"> • {{ product.category }}</span>
                                                                    </p>
                                                                </div>
                                                            </label>
                                                        </div>
                                                        <select
                                                            v-else
                                                            v-model="singleProductId"
                                                            class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                        >
                                                            <option value="">Selecione um produto...</option>
                                                            <option v-for="product in productOptions" :key="product.id" :value="product.id">
                                                                {{ product.label }}{{ product.sku ? ` • ${product.sku}` : '' }}
                                                            </option>
                                                        </select>
                                                        <InputError :message="form.errors.product_ids || form.errors['product_ids.0']" class="mt-1" />
                                                    </div>
                                                    <div v-else-if="field.type === 'client_product_selector'" class="space-y-2">
                                                        <select
                                                            v-model="form.product_client_id"
                                                            class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                        >
                                                            <option value="">Selecione um cliente...</option>
                                                            <option v-for="client in clientOptions" :key="client.id" :value="client.id">
                                                                {{ client.label }}
                                                            </option>
                                                        </select>
                                                        <p class="text-[11px] text-slate-400">
                                                            Selecione um cliente para listar os produtos vinculados.
                                                        </p>
                                                        <div v-if="form.product_client_id && !clientProductOptions.length" class="rounded-lg border border-dashed border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                                            Este cliente não possui produtos ativos vinculados.
                                                        </div>
                                                        <div v-else-if="form.product_client_id && field.multiple" class="grid gap-2 md:grid-cols-2">
                                                            <label
                                                                v-for="product in clientProductOptions"
                                                                :key="product.id"
                                                                class="flex items-start gap-3 rounded-xl border border-emerald-100 bg-white px-3 py-2 text-xs text-slate-700 shadow-sm transition hover:border-emerald-300"
                                                                :class="{ 'opacity-60': isUnavailableProduct(product) }"
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    :value="product.id"
                                                                    v-model="form.product_ids"
                                                                    :disabled="isUnavailableProduct(product)"
                                                                    class="mt-0.5 h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500"
                                                                />
                                                                <div class="min-w-0">
                                                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ product.label }}</p>
                                                                    <p class="text-[11px] text-slate-500">
                                                                        {{ product.sku ? `SKU ${product.sku}` : 'SKU não informado' }}
                                                                        <span v-if="product.category"> • {{ product.category }}</span>
                                                                        <span class="ml-2 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                                            Disponível: {{ resolveAvailableQuantity(product) }}
                                                                        </span>
                                                                    </p>
                                                                </div>
                                                            </label>
                                                        </div>
                                                        <select
                                                            v-else-if="form.product_client_id"
                                                            v-model="singleProductId"
                                                            class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                        >
                                                            <option value="">Selecione um produto...</option>
                                                            <option
                                                                v-for="product in clientProductOptions"
                                                                :key="product.id"
                                                                :value="product.id"
                                                                :disabled="isUnavailableProduct(product)"
                                                            >
                                                                {{ product.label }}{{ product.sku ? ` • ${product.sku}` : '' }}{{ isUnavailableProduct(product) ? ' (Indisponível)' : '' }}
                                                            </option>
                                                        </select>
                                                        <InputError :message="form.errors.product_client_id" class="mt-1" />
                                                        <InputError :message="form.errors.product_ids || form.errors['product_ids.0']" class="mt-1" />
                                                    </div>
                                                    <select
                                                        v-else-if="field.type === 'client_selector'"
                                                        v-model="form.fields[field.key]"
                                                        class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    >
                                                        <option value="">Selecione um cliente...</option>
                                                        <option v-for="client in clientOptions" :key="client.id" :value="client.id">
                                                            {{ client.label }}
                                                        </option>
                                                    </select>
                                                    <select
                                                        v-else-if="field.type === 'supplier_selector'"
                                                        v-model="form.fields[field.key]"
                                                        class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    >
                                                        <option value="">Selecione um fornecedor...</option>
                                                        <option v-for="supplier in supplierOptions" :key="supplier.id" :value="supplier.id">
                                                            {{ supplier.label }}
                                                        </option>
                                                    </select>
                                                    <label v-else-if="field.type === 'checkbox'" class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                                        <Checkbox v-model:checked="form.fields[field.key]" class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500" />
                                                        {{ field.placeholder || 'Confirmo esta informação' }}
                                                    </label>
                                                    <div v-else-if="field.type === 'document'" class="space-y-2">
                                                        <input
                                                            type="file"
                                                            accept=".pdf,.jpg,.jpeg"
                                                            class="w-full rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                            @change="(event) => onFileChange(event, field.key)"
                                                        />
                                                        <div v-if="form.documents[field.key]" class="flex items-center justify-between rounded-lg border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-xs text-emerald-700">
                                                            <span class="truncate">{{ form.documents[field.key]?.name }}</span>
                                                            <button type="button" class="text-emerald-700 underline" @click="removeFile(field.key)">
                                                                Remover
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <InputError :message="form.errors[`fields.${field.key}`]" />
                                                    <InputError :message="form.errors[`documents.${field.key}`]" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-else
                                        class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-center text-xs text-slate-500"
                                    >
                                        Nenhuma etapa do wizard foi configurada.
                                    </div>
                                </div>
                            </div>
                            <div v-else key="wizard-step-3" class="space-y-4">
                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 px-4 py-4">
                                    <h4 class="text-sm font-semibold text-emerald-900">Resumo da missão</h4>
                                    <dl class="mt-3 grid gap-2 text-xs text-emerald-900/80 md:grid-cols-2">
                                        <div>
                                            <dt class="font-semibold">Tipo de processo</dt>
                                            <dd>{{ selectedProcessType?.name ?? '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-semibold">Referência</dt>
                                            <dd>{{ form.reference || '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-semibold">Campos informados</dt>
                                            <dd>{{ schemaFields.length }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-semibold">Documentos anexados</dt>
                                            <dd>{{ Object.values(form.documents || {}).filter(Boolean).length }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-semibold">Produtos</dt>
                                            <dd>
                                                <span v-if="!hasProductSelector">—</span>
                                                <span v-else-if="!selectedProductItems.length">Nenhum</span>
                                                <span v-else>
                                                    {{ selectedProductItems.map((item) => item.label).join(', ') }}
                                                </span>
                                            </dd>
                                        </div>
                                        <div v-if="productRequiresClient">
                                            <dt class="font-semibold">Cliente do produto</dt>
                                            <dd>{{ productClientLabel || '—' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                                <p class="text-[11px] text-slate-500">
                                    Ao confirmar, o processo entra no status <span class="font-semibold">em andamento</span> com alertas automáticos e acompanhamento em tempo real.
                                </p>
                            </div>
                        </Transition>

                        <div class="flex items-center justify-between border-t border-slate-200 pt-4">
                            <button
                                type="button"
                                @click="wizardStep === 1 ? closeCreate() : goPrevStep()"
                                class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50"
                            >
                                {{ wizardStep === 1 ? 'Cancelar' : 'Voltar' }}
                            </button>
                            <div class="flex gap-2">
                                <button
                                    v-if="wizardStep < totalSteps"
                                    type="button"
                                    :disabled="!canAdvanceStep"
                                    @click="goNextStep"
                                    class="rounded-full bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
                                >
                                    Próxima etapa →
                                </button>
                                <button
                                    v-else
                                    type="submit"
                                    :disabled="form.processing || !canAdvanceStep"
                                    class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400"
                                >
                                    Confirmar e iniciar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: all 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(10px) scale(0.98);
}
</style>
