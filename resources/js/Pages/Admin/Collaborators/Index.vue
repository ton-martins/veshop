<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Users2, UserCheck2, ImageIcon, Tags, Search, Filter, Plus, Pencil, Trash2, UserRound, Briefcase, Mail, Phone } from 'lucide-vue-next';
import { formatPhoneBR, isValidPhoneMaskBR } from '@/utils/br';

const props = defineProps({
    niche: { type: String, default: 'commercial' },
    collaborators: { type: Object, default: () => ({ data: [], links: [] }) },
    serviceCategories: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const isServicesNiche = computed(() => String(props.niche ?? '').toLowerCase() === 'services');
const statusMessage = computed(() => String(page.props.flash?.status ?? '').trim());

const filterForm = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
});

const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'active', label: 'Ativos' },
    { value: 'inactive', label: 'Inativos' },
];

watch(() => props.filters, (next) => {
    filterForm.search = next?.search ?? '';
    filterForm.status = next?.status ?? '';
}, { deep: true });

const applyFilters = () => {
    router.get(route('admin.collaborators.index'), {
        search: filterForm.search || undefined,
        status: filterForm.status || undefined,
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
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

const rows = computed(() => props.collaborators?.data ?? []);
const paginationLinks = computed(() => props.collaborators?.links ?? []);
const statsCards = computed(() => [
    { key: 'total', label: 'Colaboradores cadastrados', value: String(props.stats?.total ?? 0), icon: Users2, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Colaboradores ativos', value: String(props.stats?.active ?? 0), icon: UserCheck2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'with_photo', label: 'Com foto', value: String(props.stats?.with_photo ?? 0), icon: ImageIcon, tone: 'bg-blue-100 text-blue-700' },
    { key: 'with_categories', label: isServicesNiche.value ? 'Com categorias' : 'Com perfil completo', value: String(props.stats?.with_categories ?? 0), icon: Tags, tone: 'bg-amber-100 text-amber-700' },
]);

const showModal = ref(false);
const currentStep = ref(1);
const wizardValidationRequested = ref(false);
const editingCollaborator = ref(null);
const showDeleteModal = ref(false);
const collaboratorToDelete = ref(null);
const photoPreview = ref('');
const photoInput = ref(null);

const wizardSteps = computed(() => (
    isServicesNiche.value
        ? ['Dados do colaborador', 'Foto e atuação']
        : ['Dados do colaborador', 'Foto e observações']
));

const buildDefaults = () => ({
    name: '',
    email: '',
    phone: '',
    job_title: '',
    notes: '',
    is_active: true,
    service_category_ids: [],
    photo: null,
    remove_photo: false,
});

const collaboratorForm = useForm(buildDefaults());
const deleteForm = useForm({});
const isEditing = computed(() => Boolean(editingCollaborator.value?.id));
const isFirstStep = computed(() => currentStep.value === 1);
const isLastStep = computed(() => currentStep.value === wizardSteps.value.length);

const isWizardStepValid = (stepNumber) => {
    if (stepNumber === 1) {
        return String(collaboratorForm.name ?? '').trim() !== '';
    }

    return true;
};

const clearStepLocalErrors = (stepNumber) => {
    if (stepNumber === 1) collaboratorForm.clearErrors('name');
};

const applyStepLocalErrors = (stepNumber) => {
    if (stepNumber === 1 && String(collaboratorForm.name ?? '').trim() === '') {
        collaboratorForm.setError('name', 'Informe o nome do colaborador.');
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
    1: ['name', 'email', 'phone', 'job_title', 'is_active'],
    2: ['photo', 'service_category_ids', 'notes'],
};

const hasFormErrorForStep = (stepNumber) => {
    const keys = Object.keys(collaboratorForm.errors ?? {});
    const prefixes = stepErrorKeyMap[stepNumber] ?? [];
    return keys.some((key) => prefixes.some((prefix) => key === prefix || key.startsWith(`${prefix}.`)));
};

const shouldShowStepErrors = computed(() =>
    isEditing.value || wizardValidationRequested.value || Object.keys(collaboratorForm.errors ?? {}).length > 0,
);

const wizardStepErrors = computed(() =>
    wizardSteps.value.map((_, index) => {
        const stepNumber = index + 1;
        if (!shouldShowStepErrors.value) return false;
        const checkLocalValidation = isEditing.value || stepNumber <= currentStep.value;
        const hasLocalError = checkLocalValidation ? !isWizardStepValid(stepNumber) : false;
        return hasLocalError || hasFormErrorForStep(stepNumber);
    }),
);

const resetPhotoPreview = () => {
    if (photoPreview.value && photoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(photoPreview.value);
    }
    photoPreview.value = '';
};

const resetWizard = () => {
    currentStep.value = 1;
    wizardValidationRequested.value = false;
};

const resetForm = () => {
    collaboratorForm.defaults(buildDefaults());
    collaboratorForm.reset();
    collaboratorForm.clearErrors();
    collaboratorForm.is_active = true;
    collaboratorForm.service_category_ids = [];
    collaboratorForm.photo = null;
    collaboratorForm.remove_photo = false;
    editingCollaborator.value = null;
    resetWizard();
    resetPhotoPreview();
    if (photoInput.value) photoInput.value.value = '';
};

const openCreate = () => {
    resetForm();
    showModal.value = true;
};

const openEdit = (collaborator) => {
    resetForm();
    editingCollaborator.value = collaborator;
    collaboratorForm.name = String(collaborator?.name ?? '');
    collaboratorForm.email = String(collaborator?.email ?? '');
    collaboratorForm.phone = String(collaborator?.phone ?? '');
    collaboratorForm.job_title = String(collaborator?.job_title ?? '');
    collaboratorForm.notes = String(collaborator?.notes ?? '');
    collaboratorForm.is_active = Boolean(collaborator?.is_active);
    collaboratorForm.service_category_ids = Array.isArray(collaborator?.service_category_ids)
        ? collaborator.service_category_ids.map((id) => Number(id))
        : [];
    photoPreview.value = String(collaborator?.photo_url ?? '').trim();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    resetForm();
};

const setWizardStep = (step) => {
    if (!isEditing.value) return;
    const parsedStep = Number(step);
    if (!Number.isFinite(parsedStep)) return;
    currentStep.value = Math.min(wizardSteps.value.length, Math.max(1, Math.floor(parsedStep)));
};

const goNextStep = () => {
    if (!validateCurrentStepForCreate()) return;
    currentStep.value = Math.min(wizardSteps.value.length, currentStep.value + 1);
};

const goPreviousStep = () => {
    currentStep.value = Math.max(1, currentStep.value - 1);
};

const onPhoneInput = (event) => {
    collaboratorForm.phone = formatPhoneBR(event?.target?.value ?? collaboratorForm.phone);
    collaboratorForm.clearErrors('phone');
};

const triggerPhotoPicker = () => {
    photoInput.value?.click();
};

const handlePhotoChange = (event) => {
    const [file] = event?.target?.files ?? [];
    resetPhotoPreview();
    collaboratorForm.remove_photo = false;

    if (!(file instanceof File)) {
        collaboratorForm.photo = null;
        if (editingCollaborator.value?.photo_url) {
            photoPreview.value = String(editingCollaborator.value.photo_url);
        }
        return;
    }

    collaboratorForm.photo = file;
    photoPreview.value = URL.createObjectURL(file);
};

const removePhoto = () => {
    collaboratorForm.photo = null;
    collaboratorForm.remove_photo = true;
    resetPhotoPreview();
    if (photoInput.value) photoInput.value.value = '';
};

const toggleCategory = (categoryId) => {
    const safeId = Number(categoryId);
    const current = new Set((collaboratorForm.service_category_ids ?? []).map((id) => Number(id)));
    if (current.has(safeId)) current.delete(safeId);
    else current.add(safeId);
    collaboratorForm.service_category_ids = Array.from(current.values());
    collaboratorForm.clearErrors('service_category_ids');
};

const submitCollaborator = () => {
    collaboratorForm.phone = formatPhoneBR(collaboratorForm.phone);
    if (collaboratorForm.phone && !isValidPhoneMaskBR(collaboratorForm.phone)) {
        collaboratorForm.setError('phone', 'Informe o telefone no formato (11) 99999-9999.');
        return;
    }

    collaboratorForm.transform((data) => {
        const payload = { ...data, ...(editingCollaborator.value?.id ? { _method: 'put' } : {}) };
        if (!payload.photo) delete payload.photo;
        return payload;
    });

    if (editingCollaborator.value?.id) {
        collaboratorForm.post(route('admin.collaborators.update', editingCollaborator.value.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeModal,
            onFinish: () => collaboratorForm.transform((data) => data),
        });
        return;
    }

    collaboratorForm.post(route('admin.collaborators.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeModal,
        onFinish: () => collaboratorForm.transform((data) => data),
    });
};

const openDeleteModal = (collaborator) => {
    collaboratorToDelete.value = collaborator;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    collaboratorToDelete.value = null;
};

const removeCollaborator = () => {
    if (!collaboratorToDelete.value?.id) return;
    deleteForm.delete(route('admin.collaborators.destroy', collaboratorToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};

const avatarFallback = (name) => {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'CL';
    const first = parts[0]?.charAt(0) ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1]?.charAt(0) ?? '' : '';
    return `${first}${last}`.toUpperCase();
};

onBeforeUnmount(() => {
    resetPhotoPreview();
});
</script>

<template>
    <Head title="Colaboradores" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Colaboradores" :show-table-view-toggle="false">
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

            <div v-if="statusMessage" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ statusMessage }}
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Buscar colaborador por nome, e-mail, telefone ou função"
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
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto" @click="applyFilters">
                            <Search class="h-3.5 w-3.5" />
                            Buscar
                        </button>
                        <UiSelect v-model="filterForm.status" :options="statusOptions" button-class="w-full sm:w-auto" @change="applyFilters" />
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto" @click="clearFilters">
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 sm:w-auto" @click="openCreate">
                            <Plus class="h-3.5 w-3.5" />
                            Novo colaborador
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
                                <th class="px-4 py-3">Colaborador</th>
                                <th class="px-4 py-3">Contato</th>
                                <th class="px-4 py-3">Função</th>
                                <th class="px-4 py-3">{{ isServicesNiche ? 'Atuação' : 'Observações' }}</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!rows.length">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhum colaborador cadastrado para este contratante.
                                </td>
                            </tr>
                            <tr v-for="collaborator in rows" :key="collaborator.id">
                                <td class="px-4 py-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 text-xs font-semibold text-slate-700">
                                            <img v-if="collaborator.photo_url" :src="collaborator.photo_url" :alt="collaborator.name" class="h-full w-full object-cover">
                                            <span v-else>{{ avatarFallback(collaborator.name) }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900">{{ collaborator.name }}</p>
                                            <p class="text-xs text-slate-500">Criado em {{ collaborator.created_at || '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ collaborator.email || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ collaborator.phone || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ collaborator.job_title || '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <div v-if="isServicesNiche" class="flex flex-wrap gap-1.5">
                                        <span v-for="category in collaborator.service_categories" :key="`collaborator-category-${collaborator.id}-${category.id}`" class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700">
                                            {{ category.name }}
                                        </span>
                                        <span v-if="!collaborator.service_categories.length" class="text-xs text-slate-500">
                                            Sem categorias vinculadas
                                        </span>
                                    </div>
                                    <p v-else class="line-clamp-2 text-sm text-slate-600">{{ collaborator.notes || '-' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="collaborator.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ collaborator.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="openEdit(collaborator)">
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="openDeleteModal(collaborator)">
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
                :title="isEditing ? 'Editar colaborador' : 'Novo colaborador'"
                description="Preencha os dados do colaborador."
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
                        <input v-model="collaboratorForm.name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Ex.: Ana Souza">
                        <p v-if="collaboratorForm.errors.name" class="mt-1 text-xs text-rose-600">{{ collaboratorForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Função</label>
                        <div class="relative mt-1">
                            <Briefcase class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="collaboratorForm.job_title" type="text" class="w-full rounded-xl border border-slate-200 py-2 pl-10 pr-3 text-sm text-slate-700" placeholder="Ex.: Barbeiro, técnico, atendente">
                        </div>
                        <p v-if="collaboratorForm.errors.job_title" class="mt-1 text-xs text-rose-600">{{ collaboratorForm.errors.job_title }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                        <div class="relative mt-1">
                            <Mail class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="collaboratorForm.email" type="email" class="w-full rounded-xl border border-slate-200 py-2 pl-10 pr-3 text-sm text-slate-700" placeholder="colaborador@email.com">
                        </div>
                        <p v-if="collaboratorForm.errors.email" class="mt-1 text-xs text-rose-600">{{ collaboratorForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                        <div class="relative mt-1">
                            <Phone class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input :value="collaboratorForm.phone" type="text" inputmode="numeric" maxlength="15" class="w-full rounded-xl border border-slate-200 py-2 pl-10 pr-3 text-sm text-slate-700" placeholder="(11) 99999-9999" @input="onPhoneInput">
                        </div>
                        <p v-if="collaboratorForm.errors.phone" class="mt-1 text-xs text-rose-600">{{ collaboratorForm.errors.phone }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 md:col-span-2">
                        <input v-model="collaboratorForm.is_active" type="checkbox" class="rounded border-slate-300">
                        Colaborador ativo
                    </label>
                </div>

                <div v-else class="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">
                    <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex h-48 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <img v-if="photoPreview" :src="photoPreview" :alt="collaboratorForm.name || 'Colaborador'" class="h-full w-full object-cover">
                            <div v-else class="flex h-full w-full flex-col items-center justify-center gap-2 text-slate-500">
                                <UserRound class="h-8 w-8" />
                                <span class="text-xs font-semibold uppercase">Sem foto</span>
                            </div>
                        </div>

                        <input ref="photoInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="handlePhotoChange">

                        <button type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="triggerPhotoPicker">
                            <UserRound class="h-4 w-4" />
                            {{ photoPreview ? 'Trocar foto' : 'Enviar foto' }}
                        </button>
                        <button v-if="photoPreview" type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100" @click="removePhoto">
                            <Trash2 class="h-4 w-4" />
                            Remover foto
                        </button>
                        <InputError :message="collaboratorForm.errors.photo" />
                    </div>

                    <div class="space-y-4">
                        <div v-if="isServicesNiche" class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4">
                            <div class="flex items-center gap-2">
                                <Tags class="h-4 w-4 text-emerald-700" />
                                <h4 class="text-sm font-semibold text-slate-900">Categorias atendidas</h4>
                            </div>
                            <p class="mt-1 text-xs text-slate-600">
                                Defina em quais categorias este colaborador pode ser selecionado no agendamento online.
                            </p>
                            <div class="mt-3 grid gap-2 md:grid-cols-2">
                                <label v-for="category in serviceCategories" :key="`service-category-${category.id}`" class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-white px-3 py-2 text-sm text-slate-700">
                                    <input type="checkbox" :checked="collaboratorForm.service_category_ids.includes(category.id)" @change="toggleCategory(category.id)">
                                    <span>{{ category.name }}</span>
                                </label>
                            </div>
                            <InputError :message="collaboratorForm.errors.service_category_ids" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</label>
                            <textarea v-model="collaboratorForm.notes" rows="5" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-3 text-sm text-slate-700" placeholder="Observações internas sobre este colaborador"></textarea>
                            <InputError :message="collaboratorForm.errors.notes" />
                        </div>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-between gap-2">
                        <button v-if="!isFirstStep" type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="goPreviousStep">
                            Voltar
                        </button>
                        <div v-else></div>

                        <div class="flex items-center gap-2">
                            <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                                Cancelar
                            </button>
                            <button v-if="!isLastStep" type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800" @click="goNextStep">
                                Próximo
                            </button>
                            <button v-else type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="collaboratorForm.processing" @click="submitCollaborator">
                                {{ collaboratorForm.processing ? 'Salvando...' : 'Salvar' }}
                            </button>
                        </div>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir colaborador"
            message="Tem certeza que deseja excluir este colaborador?"
            :item-label="collaboratorToDelete?.name ? `Colaborador: ${collaboratorToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="removeCollaborator"
        />
    </AuthenticatedLayout>
</template>
