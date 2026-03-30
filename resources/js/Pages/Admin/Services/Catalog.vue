<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import Modal from '@/Components/Modal.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Briefcase, CircleDollarSign, Clock3, Filter, Pencil, Plus, Search, Trash2, Upload } from 'lucide-vue-next';

const props = defineProps({
    services: {
        type: Object,
        default: () => ({ data: [] }),
    },
    categories: {
        type: Array,
        default: () => [],
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

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const categoryId = ref(props.filters?.category_id ?? '');

watch(
    () => props.filters,
    (next) => {
        search.value = next?.search ?? '';
        status.value = next?.status ?? '';
        categoryId.value = next?.category_id ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('admin.services.catalog'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            category_id: categoryId.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        },
    );
};

const clearSearch = () => {
    if (!String(search.value ?? '').trim()) return;
    search.value = '';
    applyFilters();
};

const clearFilters = () => {
    search.value = '';
    status.value = '';
    categoryId.value = '';
    applyFilters();
};

const rows = computed(() => props.services?.data ?? []);
const paginationLinks = computed(() => props.services?.links ?? []);
const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const formatDuration = (minutes) => {
    const safe = Math.max(0, Number(minutes ?? 0) || 0);
    const hours = Math.floor(safe / 60);
    const remaining = safe % 60;

    if (hours > 0 && remaining > 0) return `${hours}h ${remaining}min`;
    if (hours > 0) return `${hours}h`;
    return `${remaining}min`;
};

const categoryOptions = computed(() => [
    { value: '', label: 'Todas as categorias' },
    ...(props.categories ?? []).map((category) => ({
        value: category.id,
        label: category.name,
    })),
]);

const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'active', label: 'Ativos' },
    { value: 'inactive', label: 'Inativos' },
];

const page = usePage();
const contractorBusinessType = computed(() =>
    String(page.props?.contractorContext?.current?.business_type ?? '')
        .trim()
        .toLowerCase(),
);

const servicePlaceholderExamples = {
    barbershop: {
        name: 'Ex.: Corte degradê com navalha',
        code: 'Ex.: CORTE-DEGRADE',
    },
    auto_electric: {
        name: 'Ex.: Diagnóstico elétrico completo',
        code: 'Ex.: DIAG-ELETRICO',
    },
    mechanic: {
        name: 'Ex.: Troca de óleo e filtro',
        code: 'Ex.: TROCA-OLEO',
    },
    accounting: {
        name: 'Ex.: Fechamento contábil mensal',
        code: 'Ex.: CONT-MENSAL',
    },
    general_services: {
        name: 'Ex.: Manutenção preventiva mensal',
        code: 'Ex.: MANUT-PREVENTIVA',
    },
};

const serviceNamePlaceholder = computed(
    () =>
        servicePlaceholderExamples[contractorBusinessType.value]?.name
        ?? 'Ex.: Serviço especializado',
);

const serviceCodePlaceholder = computed(
    () =>
        servicePlaceholderExamples[contractorBusinessType.value]?.code
        ?? 'Ex.: SERVICO-PADRAO',
);

const categoryOptionsForForm = computed(() => [
    { value: '', label: 'Sem categoria' },
    ...(props.categories ?? []).map((category) => ({
        value: category.id,
        label: category.name,
    })),
]);

const formDefaults = () => ({
    name: '',
    code: '',
    service_category_id: '',
    description: '',
    duration_minutes: 60,
    base_price: '0.00',
    is_active: true,
    image_url: null,
    image_file: null,
    remove_image: false,
});

const form = useForm(formDefaults());
const showModal = ref(false);
const editingService = ref(null);
const imageInputRef = ref(null);
const temporaryImagePreview = ref('');
const durationHours = ref(1);
const durationRemainder = ref(0);
const deleteForm = useForm({});
const showDeleteModal = ref(false);
const serviceToDelete = ref(null);

const isEditing = computed(() => Boolean(editingService.value?.id));
const imagePreviewUrl = computed(() => {
    if (temporaryImagePreview.value) return temporaryImagePreview.value;
    if (form.remove_image) return '';
    return String(form.image_url ?? '').trim();
});

const resetTemporaryPreview = () => {
    if (temporaryImagePreview.value.startsWith('blob:') && typeof URL !== 'undefined') {
        URL.revokeObjectURL(temporaryImagePreview.value);
    }
    temporaryImagePreview.value = '';
};

const MIN_DURATION_MINUTES = 15;
const MAX_DURATION_MINUTES = 1440;

const normalizeDurationTotal = (value, { enforceMinimum = true } = {}) => {
    const fallback = enforceMinimum ? MIN_DURATION_MINUTES : 0;
    const parsed = Number.parseInt(value ?? fallback, 10);
    if (!Number.isFinite(parsed)) return fallback;

    const minimum = enforceMinimum ? MIN_DURATION_MINUTES : 0;
    return Math.min(MAX_DURATION_MINUTES, Math.max(minimum, parsed));
};

const setDurationFieldsFromTotal = (totalMinutes, { enforceMinimum = true } = {}) => {
    const safeTotal = normalizeDurationTotal(totalMinutes, { enforceMinimum });
    durationHours.value = Math.floor(safeTotal / 60);
    durationRemainder.value = safeTotal % 60;
    form.duration_minutes = safeTotal;
};

const syncDurationMinutes = () => {
    const parsedHours = Number.parseInt(durationHours.value ?? 0, 10);
    const parsedMinutes = Number.parseInt(durationRemainder.value ?? 0, 10);
    const safeHours = Number.isFinite(parsedHours) ? Math.min(24, Math.max(0, parsedHours)) : 0;
    let safeMinutes = Number.isFinite(parsedMinutes) ? Math.min(59, Math.max(0, parsedMinutes)) : 0;

    if (safeHours === 24) {
        safeMinutes = 0;
    }

    durationHours.value = safeHours;
    durationRemainder.value = safeMinutes;

    const total = normalizeDurationTotal((safeHours * 60) + safeMinutes, { enforceMinimum: false });
    durationHours.value = Math.floor(total / 60);
    durationRemainder.value = total % 60;
    form.duration_minutes = total;
};

const openCreate = () => {
    resetTemporaryPreview();
    editingService.value = null;
    form.defaults(formDefaults());
    form.reset();
    form.clearErrors();
    setDurationFieldsFromTotal(form.duration_minutes);
    showModal.value = true;
};

const openEdit = (service) => {
    resetTemporaryPreview();
    editingService.value = service;
    form.name = service.name ?? '';
    form.code = service.code ?? '';
    form.service_category_id = service.service_category_id ?? '';
    form.description = service.description ?? '';
    form.duration_minutes = Number(service.duration_minutes ?? 60);
    setDurationFieldsFromTotal(form.duration_minutes);
    form.base_price = String(service.base_price ?? '0.00');
    form.is_active = Boolean(service.is_active);
    form.image_url = service.image_url ?? null;
    form.image_file = null;
    form.remove_image = false;
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingService.value = null;
    form.clearErrors();
    form.defaults(formDefaults());
    form.reset();
    setDurationFieldsFromTotal(formDefaults().duration_minutes);
    resetTemporaryPreview();
    if (imageInputRef.value) {
        imageInputRef.value.value = '';
    }
};

const onImageChange = (event) => {
    const file = event?.target?.files?.[0] ?? null;
    form.image_file = file;
    form.remove_image = false;

    resetTemporaryPreview();
    if (file && typeof URL !== 'undefined') {
        temporaryImagePreview.value = URL.createObjectURL(file);
    }
};

const removeImage = () => {
    form.remove_image = true;
    form.image_file = null;
    form.image_url = null;
    resetTemporaryPreview();
    if (imageInputRef.value) {
        imageInputRef.value.value = '';
    }
};

const submitService = () => {
    syncDurationMinutes();
    form.duration_minutes = normalizeDurationTotal(form.duration_minutes);
    setDurationFieldsFromTotal(form.duration_minutes);

    const onFinish = () => {
        form.transform((data) => data);
    };

    if (isEditing.value) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(route('admin.services.catalog.update', editingService.value.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeModal,
            onFinish,
        });
        return;
    }

    form.post(route('admin.services.catalog.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeModal,
        onFinish,
    });
};

const openDeleteModal = (service) => {
    serviceToDelete.value = service;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    serviceToDelete.value = null;
};

const destroyService = () => {
    if (!serviceToDelete.value?.id) return;

    deleteForm.delete(route('admin.services.catalog.destroy', serviceToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};

const statsCards = computed(() => [
    {
        key: 'total',
        label: 'Serviços cadastrados',
        value: String(props.stats?.total ?? 0),
        icon: Briefcase,
        tone: 'bg-slate-100 text-slate-700',
    },
    {
        key: 'active',
        label: 'Serviços ativos',
        value: String(props.stats?.active ?? 0),
        icon: Clock3,
        tone: 'bg-emerald-100 text-emerald-700',
    },
    {
        key: 'avg_price',
        label: 'Preço médio',
        value: asCurrency(props.stats?.avg_price ?? 0),
        icon: CircleDollarSign,
        tone: 'bg-blue-100 text-blue-700',
    },
]);

const resolveInitials = (value) => {
    const safe = String(value ?? '').trim();
    if (!safe) return 'SV';

    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) || 'S';
    const second = parts.length > 1 ? parts[1]?.charAt(0) : 'V';

    return `${first}${second}`.toUpperCase();
};
</script>

<template>
    <Head title="Catálogo de Serviços" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Catálogo de Serviços" :show-table-view-toggle="false">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
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
                            v-model="search"
                            type="text"
                            placeholder="Buscar serviço por código ou nome"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keydown.enter.prevent="applyFilters"
                        >
                        <button
                            v-if="search"
                            type="button"
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                            aria-label="Limpar pesquisa"
                            @click="clearSearch"
                        >
                            x
                        </button>
                    </div>
                    <div class="veshop-toolbar-actions lg:justify-end">
                        <button type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto" @click="applyFilters">
                            <Search class="h-3.5 w-3.5" />
                            Buscar
                        </button>
                        <UiSelect
                            v-model="categoryId"
                            :options="categoryOptions"
                            button-class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <UiSelect
                            v-model="status"
                            :options="statusOptions"
                            button-class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <button type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto" @click="clearFilters">
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 sm:w-auto" @click="openCreate">
                            <Plus class="h-3.5 w-3.5" />
                            Novo serviço
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
                                <th class="px-4 py-3">Serviço</th>
                                <th class="px-4 py-3">Categoria</th>
                                <th class="px-4 py-3">Duração padrão</th>
                                <th class="px-4 py-3">Preço base</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody v-if="rows.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="service in rows" :key="service.id">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-100 text-xs font-semibold text-slate-600">
                                            <img v-if="service.image_url" :src="service.image_url" :alt="service.name" class="h-full w-full object-cover">
                                            <span v-else>{{ resolveInitials(service.name) }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900">{{ service.name }}</p>
                                            <p class="text-xs text-slate-500">{{ service.code || '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ service.category_name || '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ formatDuration(service.duration_minutes) }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ asCurrency(service.base_price) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="service.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ service.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="openEdit(service)">
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="openDeleteModal(service)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                            Excluir
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!rows.length" class="px-4 py-8 text-center text-sm text-slate-500">
                        Nenhum serviço cadastrado para este contratante.
                    </div>
                </div>

                <PaginationLinks :links="paginationLinks" :min-links="4" />
            </section>
        </section>

        <Modal :show="showModal" max-width="5xl" @close="closeModal">
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ isEditing ? 'Editar serviço' : 'Novo serviço' }}</h3>
                        <p class="text-sm text-slate-500">Cadastre os dados do serviço e mantenha o catálogo atualizado.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                        Fechar
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            :placeholder="serviceNamePlaceholder"
                        >
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Código</label>
                        <input
                            v-model="form.code"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            :placeholder="serviceCodePlaceholder"
                        >
                        <p v-if="form.errors.code" class="mt-1 text-xs text-rose-600">{{ form.errors.code }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Categoria</label>
                        <UiSelect
                            v-model="form.service_category_id"
                            :options="categoryOptionsForForm"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="form.errors.service_category_id" class="mt-1 text-xs text-rose-600">{{ form.errors.service_category_id }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Duração padrão *</label>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            <div>
                                <input
                                    v-model.number="durationHours"
                                    type="number"
                                    min="0"
                                    max="24"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Horas"
                                    @input="syncDurationMinutes"
                                >
                                <p class="mt-1 text-[11px] text-slate-500">Horas</p>
                            </div>
                            <div>
                                <input
                                    v-model.number="durationRemainder"
                                    type="number"
                                    min="0"
                                    max="59"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Minutos"
                                    @input="syncDurationMinutes"
                                >
                                <p class="mt-1 text-[11px] text-slate-500">Minutos</p>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Total: {{ formatDuration(form.duration_minutes) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Mínimo de 15 minutos.</p>
                        <p v-if="form.errors.duration_minutes" class="mt-1 text-xs text-rose-600">{{ form.errors.duration_minutes }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Detalhes do serviço oferecido"
                        />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Preço base (R$) *</label>
                        <BrlMoneyInput
                            v-model="form.base_price"
                            :allow-empty="false"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="R$ 0,00"
                        />
                        <p v-if="form.errors.base_price" class="mt-1 text-xs text-rose-600">{{ form.errors.base_price }}</p>
                    </div>

                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                            >
                            Serviço ativo
                        </label>
                    </div>

                    <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Foto do serviço</p>
                                <p class="text-[11px] text-slate-500">Formatos JPG, PNG ou WEBP até 4MB.</p>
                            </div>
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                <Upload class="h-3.5 w-3.5" />
                                Escolher foto
                                <input ref="imageInputRef" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onImageChange">
                            </label>
                        </div>

                        <div v-if="imagePreviewUrl" class="mt-3 flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-2">
                            <img :src="imagePreviewUrl" alt="Prévia da foto do serviço" class="h-16 w-16 rounded-md object-cover">
                            <button type="button" class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="removeImage">
                                Remover foto
                            </button>
                        </div>
                        <p v-if="form.errors.image_file" class="mt-1 text-xs text-rose-600">{{ form.errors.image_file }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                        Cancelar
                    </button>
                    <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60" :disabled="form.processing" @click="submitService">
                        {{ form.processing ? 'Salvando...' : (isEditing ? 'Salvar alterações' : 'Salvar serviço') }}
                    </button>
                </div>
            </div>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir serviço"
            message="Tem certeza que deseja excluir este serviço?"
            :item-label="serviceToDelete?.name ? `Serviço: ${serviceToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="destroyService"
        />
    </AuthenticatedLayout>
</template>
