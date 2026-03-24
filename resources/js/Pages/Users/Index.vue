<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import { formatCpfCnpjBR, formatPhoneBR, isValidPhoneMaskBR } from '@/utils/br';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Filter,
    ImagePlus,
    MapPinHouse,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
    UserRound,
    X,
} from 'lucide-vue-next';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    roles: {
        type: Array,
        default: () => [],
    },
    contractors: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const flashStatus = computed(() => page.props.flash?.status ?? null);
const generalError = computed(() => page.props.errors?.general ?? null);

const filtersForm = useForm({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    status: props.filters.status ?? '',
    contractor_id: props.filters.contractor_id ?? '',
});

const filterRoleOptions = computed(() => ([
    { value: '', label: 'Todos perfis' },
    ...(props.roles ?? []).map((role) => ({ value: role, label: String(role) })),
]));

const filterStatusOptions = [
    { value: '', label: 'Todos os status' },
    { value: 'active', label: 'Ativos' },
    { value: 'inactive', label: 'Inativos' },
];

const filterContractorOptions = computed(() => ([
    { value: '', label: 'Todos contratantes' },
    ...(props.contractors ?? []).map((contractor) => ({
        value: String(contractor.id),
        label: String(contractor.name ?? ''),
    })),
]));

const roleOptions = computed(() => (props.roles ?? []).map((role) => ({
    value: role,
    label: String(role),
})));

const applyFilters = () => {
    router.get(
        route('master.users.index'),
        {
            ...filtersForm.data(),
        },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        },
    );
};

const clearSearch = () => {
    if (!String(filtersForm.search ?? '').trim()) return;
    filtersForm.search = '';
    applyFilters();
};

const clearFilters = () => {
    filtersForm.reset();
    applyFilters();
};

const buildFormDefaults = () => ({
    contractor_ids: [],
    name: '',
    email: '',
    cpf: '',
    phone: '',
    password: '',
    password_confirmation: '',
    role: props.roles?.[0] ?? 'admin',
    job_title: '',
    avatar_url: '',
    avatar: null,
    is_active: true,
    address: null,
    preferences: null,
});

const buildAddressDefaults = () => ({
    cep: '',
    street: '',
    number: '',
    complement: '',
    neighborhood: '',
    city: '',
    state: '',
});

const brazilStates = [
    { code: 'AC', name: 'Acre' },
    { code: 'AL', name: 'Alagoas' },
    { code: 'AP', name: 'Amapá' },
    { code: 'AM', name: 'Amazonas' },
    { code: 'BA', name: 'Bahia' },
    { code: 'CE', name: 'Ceará' },
    { code: 'DF', name: 'Distrito Federal' },
    { code: 'ES', name: 'Espírito Santo' },
    { code: 'GO', name: 'Goiás' },
    { code: 'MA', name: 'Maranhão' },
    { code: 'MT', name: 'Mato Grosso' },
    { code: 'MS', name: 'Mato Grosso do Sul' },
    { code: 'MG', name: 'Minas Gerais' },
    { code: 'PA', name: 'Pará' },
    { code: 'PB', name: 'Paraíba' },
    { code: 'PR', name: 'Paraná' },
    { code: 'PE', name: 'Pernambuco' },
    { code: 'PI', name: 'Piauí' },
    { code: 'RJ', name: 'Rio de Janeiro' },
    { code: 'RN', name: 'Rio Grande do Norte' },
    { code: 'RS', name: 'Rio Grande do Sul' },
    { code: 'RO', name: 'Rondônia' },
    { code: 'RR', name: 'Roraima' },
    { code: 'SC', name: 'Santa Catarina' },
    { code: 'SP', name: 'São Paulo' },
    { code: 'SE', name: 'Sergipe' },
    { code: 'TO', name: 'Tocantins' },
];

const stateOptions = computed(() => ([
    { value: '', label: 'Selecione' },
    ...brazilStates.map((state) => ({
        value: state.code,
        label: `${state.code} - ${state.name}`,
    })),
]));

const wizardSteps = [
    { id: 1, label: 'Dados básicos', icon: UserRound },
    { id: 2, label: 'Acesso', icon: ShieldCheck },
    { id: 3, label: 'Endereço e avatar', icon: MapPinHouse },
    { id: 4, label: 'Revisão', icon: CheckCircle2 },
];

const showModal = ref(false);
const editingUser = ref(null);
const showDeleteModal = ref(false);
const userToDelete = ref(null);
const currentStep = ref(1);
const wizardValidationRequested = ref(false);
const jsonError = ref('');
const cepLookupLoading = ref(false);
const cepLookupError = ref('');
const avatarImageFailed = ref(false);
const avatarFileInput = ref(null);
const avatarUploadPreview = ref('');
const contractorSearch = ref('');
const contractorSelect = ref('');

const userForm = useForm(buildFormDefaults());
const deleteForm = useForm({});
const addressForm = ref(buildAddressDefaults());

const isEditing = computed(() => Boolean(editingUser.value?.id));
const isFirstStep = computed(() => currentStep.value === 1);
const isLastStep = computed(() => currentStep.value === wizardSteps.length);

const clearAvatarUploadPreview = () => {
    if (avatarUploadPreview.value && avatarUploadPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(avatarUploadPreview.value);
    }

    avatarUploadPreview.value = '';
};

const avatarPreview = computed(() => {
    if (avatarUploadPreview.value) return avatarUploadPreview.value;

    const value = String(userForm.avatar_url ?? '').trim();
    if (!value || avatarImageFailed.value) return '';
    return value;
});

const avatarFileLabel = computed(() => {
    if (!(userForm.avatar instanceof File)) return '';
    return userForm.avatar.name;
});

const normalizedContractorSearch = computed(() => String(contractorSearch.value ?? '').trim().toLowerCase());
const filteredContractors = computed(() => {
    const search = normalizedContractorSearch.value;
    const list = Array.isArray(props.contractors) ? props.contractors : [];

    if (!search) return list;

    return list.filter((contractor) => String(contractor?.name ?? '').toLowerCase().includes(search));
});

const selectedContractors = computed(() => {
    const selectedIds = new Set((userForm.contractor_ids ?? []).map((id) => Number(id)));

    return (props.contractors ?? [])
        .filter((contractor) => selectedIds.has(Number(contractor.id)))
        .map((contractor) => ({
            id: Number(contractor.id),
            name: String(contractor.name ?? ''),
        }));
});

const isContractorSelected = (contractorId) => {
    const numericId = Number(contractorId);
    return (userForm.contractor_ids ?? []).some((id) => Number(id) === numericId);
};

const contractorSelectOptions = computed(() => ([
    {
        value: '',
        label: filteredContractors.value.some((contractor) => !isContractorSelected(contractor.id))
            ? 'Selecione um contratante'
            : 'Nenhum contratante disponível',
    },
    ...filteredContractors.value
        .filter((contractor) => !isContractorSelected(contractor.id))
        .map((contractor) => ({
        value: String(contractor.id),
        label: String(contractor.name ?? ''),
        })),
]));

const addSelectedContractor = () => {
    const selectedId = Number(contractorSelect.value);
    if (selectedId <= 0) return;
    if (isContractorSelected(selectedId)) {
        contractorSelect.value = '';
        return;
    }

    const selected = (userForm.contractor_ids ?? []).map((id) => Number(id));
    userForm.contractor_ids = [...selected, selectedId];
    userForm.clearErrors('contractor_ids');
    contractorSelect.value = '';
};

const removeSelectedContractor = (contractorId) => {
    const numericId = Number(contractorId);
    const selected = (userForm.contractor_ids ?? []).map((id) => Number(id));
    userForm.contractor_ids = selected.filter((id) => id !== numericId);
    userForm.clearErrors('contractor_ids');
};

const openAvatarPicker = () => {
    avatarFileInput.value?.click();
};

const onAvatarFileChange = (event) => {
    const input = event?.target;
    const [file] = input?.files ?? [];

    clearAvatarUploadPreview();
    avatarImageFailed.value = false;

    if (!(file instanceof File)) {
        userForm.avatar = null;
        return;
    }

    userForm.avatar = file;
    userForm.avatar_url = '';
    avatarUploadPreview.value = URL.createObjectURL(file);
};

const clearAvatarFile = () => {
    userForm.avatar = null;
    userForm.avatar_url = '';
    clearAvatarUploadPreview();

    if (avatarFileInput.value) {
        avatarFileInput.value.value = '';
    }
};

const avatarInitials = computed(() => {
    const source = String(userForm.name ?? '').trim();
    if (!source) return 'VS';

    return source
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

const normalizeCepDigits = (value) => String(value ?? '').replace(/\D/g, '').slice(0, 8);
const formatCepMask = (value) => {
    const digits = normalizeCepDigits(value);
    if (digits.length <= 5) return digits;
    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
};
const isValidCepMaskBR = (value) => /^\d{5}-\d{3}$/.test(String(value ?? '').trim());

const formattedCep = computed(() => formatCepMask(addressForm.value.cep));
const isBasicEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value ?? '').trim());

const isWizardStepValid = (stepId) => {
    if (stepId === 1) {
        const name = String(userForm.name ?? '').trim();
        const email = String(userForm.email ?? '').trim();
        const cpf = String(userForm.cpf ?? '').trim();
        const phone = String(userForm.phone ?? '').trim();

        return name !== ''
            && email !== ''
            && isBasicEmail(email)
            && (!cpf || isValidCpfMaskBR(cpf))
            && (!phone || isValidPhoneMaskBR(phone));
    }

    if (stepId === 2) {
        const hasRole = String(userForm.role ?? '').trim() !== '';
        const password = String(userForm.password ?? '');
        const passwordConfirmation = String(userForm.password_confirmation ?? '');
        const hasPasswordInput = password !== '' || passwordConfirmation !== '';

        if (isEditing.value && !hasPasswordInput) {
            return hasRole;
        }

        return hasRole
            && password.length >= 8
            && passwordConfirmation.length >= 8
            && password === passwordConfirmation;
    }

    if (stepId === 3) {
        const hasContractor = Array.isArray(userForm.contractor_ids) && userForm.contractor_ids.length > 0;
        const cep = String(addressForm.value.cep ?? '').trim();
        const cepIsValid = !cep || isValidCepMaskBR(cep);

        return hasContractor && cepIsValid;
    }

    return true;
};

const clearStepLocalErrors = (stepId) => {
    if (stepId === 1) {
        userForm.clearErrors('name', 'email', 'cpf', 'phone');
        return;
    }

    if (stepId === 2) {
        userForm.clearErrors('role', 'password', 'password_confirmation');
        return;
    }

    if (stepId === 3) {
        userForm.clearErrors('contractor_ids', 'address.cep');
    }
};

const applyStepLocalErrors = (stepId) => {
    if (stepId === 1) {
        const name = String(userForm.name ?? '').trim();
        const email = String(userForm.email ?? '').trim();
        const cpf = String(userForm.cpf ?? '').trim();
        const phone = String(userForm.phone ?? '').trim();

        if (name === '') userForm.setError('name', 'Informe o nome completo.');
        if (email === '') userForm.setError('email', 'Informe o e-mail.');
        if (email !== '' && !isBasicEmail(email)) userForm.setError('email', 'Informe um e-mail válido.');
        if (cpf && !isValidCpfMaskBR(cpf)) userForm.setError('cpf', 'Informe o CPF no formato 000.000.000-00.');
        if (phone && !isValidPhoneMaskBR(phone)) userForm.setError('phone', 'Informe o telefone no formato (11) 99999-9999.');
        return;
    }

    if (stepId === 2) {
        const hasRole = String(userForm.role ?? '').trim() !== '';
        const password = String(userForm.password ?? '');
        const passwordConfirmation = String(userForm.password_confirmation ?? '');
        const hasPasswordInput = password !== '' || passwordConfirmation !== '';

        if (!hasRole) userForm.setError('role', 'Selecione o perfil do usuário.');

        if (!isEditing.value || hasPasswordInput) {
            if (password.length < 8) userForm.setError('password', 'A senha deve ter ao menos 8 caracteres.');
            if (passwordConfirmation.length < 8) userForm.setError('password_confirmation', 'Confirme a senha com ao menos 8 caracteres.');
            if (password.length >= 8 && passwordConfirmation.length >= 8 && password !== passwordConfirmation) {
                userForm.setError('password_confirmation', 'A confirmação de senha deve ser igual à senha.');
            }
        }
        return;
    }

    if (stepId === 3) {
        const hasContractor = Array.isArray(userForm.contractor_ids) && userForm.contractor_ids.length > 0;
        const cep = String(addressForm.value.cep ?? '').trim();

        if (!hasContractor) userForm.setError('contractor_ids', 'Selecione ao menos um contratante.');
        if (cep && !isValidCepMaskBR(cep)) userForm.setError('address.cep', 'Informe o CEP no formato 00000-000.');
    }
};

const validateCreateStepAndMark = (stepId) => {
    if (isEditing.value) return true;

    wizardValidationRequested.value = true;
    clearStepLocalErrors(stepId);

    if (isWizardStepValid(stepId)) return true;

    applyStepLocalErrors(stepId);
    return false;
};

const userStepErrorKeyMap = {
    1: ['name', 'email', 'cpf', 'phone'],
    2: ['role', 'password', 'password_confirmation', 'job_title', 'is_active'],
    3: ['contractor_ids', 'avatar', 'avatar_url', 'address'],
    4: [],
};

const hasFormErrorForStep = (stepId) => {
    const keys = Object.keys(userForm.errors ?? {});
    const prefixes = userStepErrorKeyMap[stepId] ?? [];

    return keys.some((key) => prefixes.some((prefix) => key === prefix || key.startsWith(`${prefix}.`)));
};

const shouldShowStepErrors = computed(() =>
    isEditing.value
    || wizardValidationRequested.value
    || Object.keys(userForm.errors ?? {}).length > 0,
);

const wizardStepErrors = computed(() =>
    wizardSteps.map((step) => {
        if (!shouldShowStepErrors.value) return false;

        const checkLocalValidation = isEditing.value || step.id <= currentStep.value;
        const hasLocalError = checkLocalValidation ? !isWizardStepValid(step.id) : false;

        return hasLocalError || hasFormErrorForStep(step.id);
    }),
);

onBeforeUnmount(() => {
    clearAvatarUploadPreview();
});

const isValidCpfMaskBR = (value) => /^\d{3}\.\d{3}\.\d{3}-\d{2}$/.test(String(value ?? '').trim());

const formatCpf = (value) => {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 11);
    return formatCpfCnpjBR(digits);
};

const onCpfInput = (event) => {
    userForm.cpf = formatCpf(event?.target?.value ?? userForm.cpf);
    userForm.clearErrors('cpf');
};

const onPhoneInput = (event) => {
    userForm.phone = formatPhoneBR(event?.target?.value ?? userForm.phone);
    userForm.clearErrors('phone');
};

const onCepInput = (event) => {
    addressForm.value.cep = formatCepMask(event?.target?.value ?? addressForm.value.cep);
    cepLookupError.value = '';
};

const setAddressFromPayload = (address) => {
    const source = address && typeof address === 'object' ? address : {};

    addressForm.value.cep = formatCepMask(String(source.cep ?? source.zip_code ?? source.postal_code ?? ''));
    addressForm.value.street = String(source.street ?? source.address_line ?? '').trim();
    addressForm.value.number = String(source.number ?? '').trim();
    addressForm.value.complement = String(source.complement ?? '').trim();
    addressForm.value.neighborhood = String(source.neighborhood ?? source.district ?? '').trim();
    addressForm.value.city = String(source.city ?? '').trim();
    addressForm.value.state = String(source.state ?? source.uf ?? '').trim().toUpperCase();
};

const buildAddressPayload = () => {
    const payload = {
        cep: formatCepMask(addressForm.value.cep),
        street: String(addressForm.value.street ?? '').trim(),
        number: String(addressForm.value.number ?? '').trim(),
        complement: String(addressForm.value.complement ?? '').trim(),
        neighborhood: String(addressForm.value.neighborhood ?? '').trim(),
        city: String(addressForm.value.city ?? '').trim(),
        state: String(addressForm.value.state ?? '').trim().toUpperCase(),
    };

    const hasAnyValue = Object.values(payload).some((value) => String(value ?? '').trim() !== '');

    if (!hasAnyValue) return null;

    return {
        ...payload,
        uf: payload.state,
    };
};

const resetWizard = () => {
    currentStep.value = 1;
    wizardValidationRequested.value = false;
    cepLookupError.value = '';
    jsonError.value = '';
    avatarImageFailed.value = false;
    contractorSearch.value = '';
    contractorSelect.value = '';
    clearAvatarUploadPreview();
    userForm.avatar = null;
    if (avatarFileInput.value) {
        avatarFileInput.value.value = '';
    }
    addressForm.value = buildAddressDefaults();
};

const openCreate = () => {
    editingUser.value = null;
    userForm.defaults(buildFormDefaults());
    userForm.reset();
    userForm.clearErrors();
    resetWizard();
    showModal.value = true;
};

const openEdit = (user) => {
    editingUser.value = user;
    userForm.name = user.name ?? '';
    userForm.email = user.email ?? '';
    userForm.cpf = formatCpf(user.cpf ?? '');
    userForm.phone = formatPhoneBR(user.phone ?? '');
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.role = user.role ?? props.roles?.[0] ?? 'admin';
    userForm.job_title = user.job_title ?? '';
    userForm.avatar_url = user.avatar_url ?? '';
    userForm.avatar = null;
    userForm.is_active = Boolean(user.is_active);
    userForm.contractor_ids = Array.isArray(user.contractor_ids)
        ? user.contractor_ids.map((item) => Number(item))
        : Array.isArray(user.contractors)
          ? user.contractors.map((item) => Number(item.id))
          : [];
    userForm.address = user.address ?? null;
    userForm.preferences = null;

    userForm.clearErrors();
    resetWizard();
    setAddressFromPayload(user.address ?? null);

    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingUser.value = null;
    userForm.clearErrors();
    userForm.defaults(buildFormDefaults());
    userForm.reset();
    resetWizard();
};

const goToStep = (stepId) => {
    if (stepId < 1 || stepId > wizardSteps.length) return;

    if (isEditing.value || stepId <= currentStep.value) {
        currentStep.value = stepId;
        return;
    }

    for (let nextStep = currentStep.value; nextStep < stepId; nextStep += 1) {
        if (!validateCreateStepAndMark(nextStep)) {
            currentStep.value = nextStep;
            return;
        }
    }

    currentStep.value = stepId;
};

const goNextStep = () => {
    if (currentStep.value >= wizardSteps.length) return;
    if (!validateCreateStepAndMark(currentStep.value)) return;
    currentStep.value += 1;
};

const goPreviousStep = () => {
    if (currentStep.value <= 1) return;
    currentStep.value -= 1;
};

const lookupCep = async () => {
    cepLookupError.value = '';

    const cep = normalizeCepDigits(addressForm.value.cep);
    addressForm.value.cep = formatCepMask(cep);

    if (!cep) return;

    if (cep.length !== 8) {
        cepLookupError.value = 'CEP inválido. Digite os 8 números.';
        return;
    }

    cepLookupLoading.value = true;

    try {
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        if (!response.ok) {
            throw new Error('Falha na consulta do CEP');
        }

        const payload = await response.json();

        if (payload?.erro) {
            cepLookupError.value = 'CEP não encontrado.';
            return;
        }

        addressForm.value.street = String(payload.logradouro ?? '').trim();
        addressForm.value.neighborhood = String(payload.bairro ?? '').trim();
        addressForm.value.city = String(payload.localidade ?? '').trim();
        addressForm.value.state = String(payload.uf ?? '').trim().toUpperCase();

        if (!String(addressForm.value.complement ?? '').trim()) {
            addressForm.value.complement = String(payload.complemento ?? '').trim();
        }
    } catch {
        cepLookupError.value = 'Não foi possível consultar o ViaCEP agora.';
    } finally {
        cepLookupLoading.value = false;
    }
};

const validateAllWizardStepsBeforeSubmit = () => {
    wizardValidationRequested.value = true;

    for (const step of [1, 2, 3]) {
        clearStepLocalErrors(step);
    }

    for (const step of [1, 2, 3]) {
        if (isWizardStepValid(step)) continue;
        applyStepLocalErrors(step);
        currentStep.value = step;
        return false;
    }

    return true;
};

const submitUser = () => {
    if (!validateAllWizardStepsBeforeSubmit()) return;

    jsonError.value = '';
    cepLookupError.value = '';
    userForm.clearErrors('cpf', 'phone');
    userForm.cpf = formatCpf(userForm.cpf);
    userForm.phone = formatPhoneBR(userForm.phone);
    addressForm.value.cep = formatCepMask(addressForm.value.cep);

    if (userForm.cpf && !isValidCpfMaskBR(userForm.cpf)) {
        userForm.setError('cpf', 'Informe o CPF no formato 000.000.000-00.');
        return;
    }

    if (userForm.phone && !isValidPhoneMaskBR(userForm.phone)) {
        userForm.setError('phone', 'Informe o telefone no formato (11) 99999-9999.');
        return;
    }

    if (addressForm.value.cep && !isValidCepMaskBR(addressForm.value.cep)) {
        cepLookupError.value = 'Informe o CEP no formato 00000-000.';
        return;
    }

    try {
        userForm.address = buildAddressPayload();
        userForm.preferences = null;
    } catch {
        jsonError.value = 'Não foi possível montar os dados de endereço.';
        return;
    }

    if (isEditing.value) {
        userForm
            .transform((data) => {
                const payload = {
                    ...data,
                    contractor_ids: (data.contractor_ids ?? []).map((id) => Number(id)),
                    _method: 'put',
                };

                if (!(data.avatar instanceof File)) {
                    delete payload.avatar;
                }

                return payload;
            })
            .post(route('master.users.update', editingUser.value.id), {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: closeModal,
                onFinish: () => {
                    userForm.transform((data) => data);
                },
            });
        return;
    }

    userForm
        .transform((data) => {
            const payload = {
                ...data,
                contractor_ids: (data.contractor_ids ?? []).map((id) => Number(id)),
            };

            if (!(data.avatar instanceof File)) {
                delete payload.avatar;
            }

            return payload;
        })
        .post(route('master.users.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeModal,
            onFinish: () => {
                userForm.transform((data) => data);
            },
        });
};

const openDeleteModal = (user) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    userToDelete.value = null;
};

const destroyUser = () => {
    if (!userToDelete.value?.id) return;

    deleteForm.delete(route('master.users.destroy', userToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};
</script>

<template>
    <Head title="Usuários" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Usuários" :show-table-view-toggle="false">
        <section class="space-y-4">
            <div v-if="flashStatus" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ flashStatus }}
            </div>
            <div v-if="generalError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                {{ generalError }}
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="veshop-search-shell flex min-w-0 flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input
                            v-model="filtersForm.search"
                            type="search"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                            placeholder="Buscar por nome, email, CPF ou telefone"
                            @keydown.enter.prevent="applyFilters"
                        />
                        <button
                            v-if="filtersForm.search"
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
                            v-model="filtersForm.role"
                            :options="filterRoleOptions"
                            class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <UiSelect
                            v-model="filtersForm.status"
                            :options="filterStatusOptions"
                            class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <UiSelect
                            v-model="filtersForm.contractor_id"
                            :options="filterContractorOptions"
                            class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <button
                            type="button"
                            class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto"
                            @click="clearFilters"
                        >
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button
                            type="button"
                            class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 sm:w-auto"
                            @click="openCreate"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Novo usuário
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex justify-end">
                    <TableViewToggle />
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Contato</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Perfil</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Contratantes</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="user in users.data" :key="user.id">
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-semibold text-slate-900">{{ user.name }}</p>
                                        <p class="text-xs text-slate-500">Criado em {{ user.created_at }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        <p>{{ user.email }}</p>
                                        <p v-if="user.phone" class="text-xs text-slate-500">{{ user.phone }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ user.role }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        <div v-if="user.contractors?.length" class="flex flex-wrap gap-1.5">
                                            <span
                                                v-for="contractor in user.contractors"
                                                :key="`user-${user.id}-contractor-${contractor.id}`"
                                                class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-700"
                                            >
                                                {{ contractor.name }}
                                            </span>
                                        </div>
                                        <span v-else class="text-xs text-slate-500">Sem contratante</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                            :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                        >
                                            {{ user.is_active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                @click="openEdit(user)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                                @click="openDeleteModal(user)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                Excluir
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!users.data.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                                        Nenhum usuário encontrado.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <PaginationLinks :links="users.links ?? []" :min-links="4" align="start" />
            </section>
        </section>

        <Modal :show="showModal" max-width="5xl" @close="closeModal">
            <div class="space-y-4 bg-white p-5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">
                            {{ isEditing ? 'Editar usuário' : 'Novo usuário' }}
                        </h3>
                        <p class="text-sm text-slate-500">Preencha por etapas para manter o cadastro padronizado.</p>
                    </div>
                    <div class="flex items-center gap-2 self-start">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            Etapa {{ currentStep }} de {{ wizardSteps.length }}
                        </span>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                            aria-label="Fechar modal"
                            @click="closeModal"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <button
                        v-for="step in wizardSteps"
                        :key="`wizard-step-${step.id}`"
                        type="button"
                        class="flex items-center gap-2 rounded-xl border px-3 py-2 text-left text-xs font-semibold transition"
                        :class="
                            wizardStepErrors[step.id - 1] && step.id === currentStep
                                ? 'border-rose-600 bg-rose-600 text-white'
                                : wizardStepErrors[step.id - 1]
                                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                                    : step.id === currentStep
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                        "
                        @click="goToStep(step.id)"
                    >
                        <span
                            v-if="wizardStepErrors[step.id - 1]"
                            class="inline-block h-2 w-2 flex-none rounded-full"
                            :class="step.id === currentStep ? 'bg-white' : 'bg-rose-500'"
                            aria-hidden="true"
                        />
                        <component :is="step.icon" class="h-4 w-4" />
                        {{ step.label }}
                    </button>
                </div>

                <section v-show="currentStep === 1" class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome completo</label>
                        <input
                            v-model="userForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Digite o nome"
                        >
                        <InputError :message="userForm.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                        <input
                            v-model="userForm.email"
                            type="email"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="email@empresa.com.br"
                        >
                        <InputError :message="userForm.errors.email" class="mt-1" />
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">CPF</label>
                        <input
                            v-model="userForm.cpf"
                            type="text"
                            inputmode="numeric"
                            maxlength="14"
                            pattern="^\d{3}\.\d{3}\.\d{3}-\d{2}$"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="000.000.000-00"
                            @input="onCpfInput"
                        >
                        <InputError :message="userForm.errors.cpf" class="mt-1" />
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                        <input
                            v-model="userForm.phone"
                            type="text"
                            inputmode="tel"
                            maxlength="15"
                            pattern="^\(\d{2}\)\s\d{5}-\d{4}$"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="(00) 00000-0000"
                            @input="onPhoneInput"
                        >
                        <InputError :message="userForm.errors.phone" class="mt-1" />
                    </div>
                </section>

                <section v-show="currentStep === 2" class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Perfil</label>
                        <UiSelect
                            v-model="userForm.role"
                            :options="roleOptions"
                            class="mt-1 w-full"
                            button-class="text-sm"
                        />
                        <InputError :message="userForm.errors.role" class="mt-1" />
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo</label>
                        <input
                            v-model="userForm.job_title"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Gerente"
                        >
                        <InputError :message="userForm.errors.job_title" class="mt-1" />
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ isEditing ? 'Nova senha (opcional)' : 'Senha' }}
                        </label>
                        <input
                            v-model="userForm.password"
                            type="password"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Mínimo 8 caracteres"
                        >
                        <InputError :message="userForm.errors.password" class="mt-1" />
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ isEditing ? 'Confirmar nova senha' : 'Confirmar senha' }}
                        </label>
                        <input
                            v-model="userForm.password_confirmation"
                            type="password"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Repita a senha"
                        >
                        <InputError :message="userForm.errors.password_confirmation" class="mt-1" />
                    </div>

                    <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <label class="flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                            <span>Usuário ativo</span>
                            <input v-model="userForm.is_active" type="checkbox" class="rounded border-slate-300">
                        </label>
                        <InputError :message="userForm.errors.is_active" class="mt-1" />
                    </div>
                </section>

                                <section v-show="currentStep === 3" class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avatar do usuário</p>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white text-base font-semibold text-slate-700 shadow-sm">
                                    <img
                                        v-if="avatarPreview"
                                        :src="avatarPreview"
                                        alt="Avatar"
                                        class="h-full w-full object-cover"
                                        @error="avatarImageFailed = true"
                                    >
                                    <span v-else>{{ avatarInitials }}</span>
                                </div>

                                <div class="flex-1 space-y-2">
                                    <input
                                        ref="avatarFileInput"
                                        type="file"
                                        accept="image/png,image/jpeg,image/jpg"
                                        class="hidden"
                                        @change="onAvatarFileChange"
                                    >

                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                            @click="openAvatarPicker"
                                        >
                                            <ImagePlus class="h-3.5 w-3.5" />
                                            Upload de avatar
                                        </button>
                                        <button
                                            v-if="avatarFileLabel || userForm.avatar_url"
                                            type="button"
                                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                                            @click="clearAvatarFile"
                                        >
                                            Limpar
                                        </button>
                                    </div>

                                    <p v-if="avatarFileLabel" class="text-[11px] text-slate-600">
                                        Arquivo: {{ avatarFileLabel }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">PNG, JPG ou JPEG até 2 MB.</p>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Link da imagem (opcional)</label>
                                <div class="mt-1 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
                                    <ImagePlus class="h-4 w-4 text-slate-500" />
                                    <input
                                        v-model="userForm.avatar_url"
                                        type="url"
                                        class="w-full bg-transparent text-sm text-slate-700 outline-none"
                                        placeholder="https://..."
                                        @input="avatarImageFailed = false"
                                    >
                                </div>
                                <InputError :message="userForm.errors.avatar" class="mt-1" />
                                <InputError :message="userForm.errors.avatar_url" class="mt-1" />
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contratantes</label>
                                <span class="rounded-full bg-white px-2 py-1 text-[10px] font-semibold text-slate-600">
                                    {{ userForm.contractor_ids.length }} selecionado(s)
                                </span>
                            </div>

                            <div class="veshop-search-shell mt-2 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                                <input
                                    v-model="contractorSearch"
                                    type="search"
                                    class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                                    placeholder="Pesquisar contratante..."
                                >
                                <button
                                    v-if="contractorSearch"
                                    type="button"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                                    aria-label="Limpar pesquisa"
                                    @click="contractorSearch = ''"
                                >
                                    x
                                </button>
                            </div>

                            <div class="mt-2 flex items-center gap-2">
                                <UiSelect
                                    v-model="contractorSelect"
                                    :options="contractorSelectOptions"
                                    class="w-full"
                                    button-class="text-sm"
                                />
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="!contractorSelect"
                                    @click="addSelectedContractor"
                                >
                                    Adicionar
                                </button>
                            </div>

                            <div v-if="selectedContractors.length" class="mt-2 flex flex-wrap gap-1.5">
                                <button
                                    v-for="contractor in selectedContractors"
                                    :key="`selected-contractor-${contractor.id}`"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-slate-50"
                                    @click="removeSelectedContractor(contractor.id)"
                                >
                                    {{ contractor.name }}
                                    <X class="h-3 w-3" />
                                </button>
                            </div>

                            <InputError :message="userForm.errors.contractor_ids" class="mt-2" />
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Endereço com ViaCEP</p>
                            <span v-if="cepLookupLoading" class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">
                                Consultando CEP...
                            </span>
                        </div>

                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <div class="md:col-span-1">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">CEP</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input
                                        v-model="addressForm.cep"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="9"
                                        pattern="^\d{5}-\d{3}$"
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                        placeholder="00000-000"
                                        @input="onCepInput"
                                        @blur="lookupCep"
                                    >
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="cepLookupLoading"
                                        @click="lookupCep"
                                    >
                                        Buscar
                                    </button>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-500">CEP atual: {{ formattedCep || '-' }}</p>
                                <InputError :message="userForm.errors['address.cep']" class="mt-1" />
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Logradouro</label>
                                <input
                                    v-model="addressForm.street"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Rua, avenida, travessa..."
                                >
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Número</label>
                                <input
                                    v-model="addressForm.number"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="123"
                                >
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Complemento</label>
                                <input
                                    v-model="addressForm.complement"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Apto, bloco, sala..."
                                >
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bairro</label>
                                <input
                                    v-model="addressForm.neighborhood"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Nome do bairro"
                                >
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cidade</label>
                                <input
                                    v-model="addressForm.city"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Cidade"
                                >
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</label>
                                <UiSelect
                                    v-model="addressForm.state"
                                    :options="stateOptions"
                                    class="mt-1 w-full"
                                    button-class="text-sm"
                                />
                            </div>
                        </div>

                        <p v-if="cepLookupError" class="mt-2 text-xs font-semibold text-amber-700">
                            {{ cepLookupError }}
                        </p>
                        <InputError :message="userForm.errors.address" class="mt-2" />
                    </div>
                </section>

                <section v-show="currentStep === 4" class="space-y-3">
                    <div class="grid gap-3 md:grid-cols-2">
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dados</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ userForm.name || '-' }}</p>
                            <p class="text-sm text-slate-600">{{ userForm.email || '-' }}</p>
                            <p class="mt-2 text-xs text-slate-500">CPF: {{ userForm.cpf || '-' }}</p>
                            <p class="text-xs text-slate-500">Telefone: {{ userForm.phone || '-' }}</p>
                        </article>

                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Acesso</p>
                            <p class="mt-2 text-sm text-slate-700">Perfil: <strong>{{ userForm.role || '-' }}</strong></p>
                            <p class="text-sm text-slate-700">Cargo: <strong>{{ userForm.job_title || '-' }}</strong></p>
                            <p class="text-sm text-slate-700">Status: <strong>{{ userForm.is_active ? 'Ativo' : 'Inativo' }}</strong></p>
                        </article>

                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4 md:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Endereço</p>
                            <p class="mt-2 text-sm text-slate-700">
                                {{ addressForm.street || '-' }}, {{ addressForm.number || 's/n' }}
                                <span v-if="addressForm.complement">- {{ addressForm.complement }}</span>
                            </p>
                            <p class="text-sm text-slate-700">
                                {{ addressForm.neighborhood || '-' }} - {{ addressForm.city || '-' }}/{{ addressForm.state || '-' }}
                            </p>
                            <p class="text-xs text-slate-500">CEP: {{ formattedCep || '-' }}</p>
                        </article>
                    </div>

                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        Preferências avançadas estão desativadas por enquanto e serão definidas em outra etapa do sistema.
                    </div>
                </section>

                <div v-if="jsonError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    {{ jsonError }}
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Cancelar
                    </button>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="!isFirstStep"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="goPreviousStep"
                        >
                            <ChevronLeft class="h-3.5 w-3.5" />
                            Voltar
                        </button>

                        <button
                            v-if="!isLastStep"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="goNextStep"
                        >
                            Próximo
                            <ChevronRight class="h-3.5 w-3.5" />
                        </button>

                        <button
                            v-if="isLastStep"
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="userForm.processing"
                            @click="submitUser"
                        >
                            {{ userForm.processing ? 'Salvando...' : 'Salvar usuário' }}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir usuário"
            message="Tem certeza que deseja excluir este usuário?"
            :item-label="userToDelete?.name ? `Usuário: ${userToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="destroyUser"
        />
    </AuthenticatedLayout>
</template>
