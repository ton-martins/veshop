<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Layers3,
    FolderTree,
    Tags,
    Box,
    Plus,
    Search,
    Filter,
    Pencil,
    Trash2,
    ChevronDown,
    ChevronRight,
} from 'lucide-vue-next';

const props = defineProps({
    categories: { type: Object, default: () => ({ data: [], links: [] }) },
    parentOptions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: '' }) },
    stats: {
        type: Object,
        default: () => ({
            categories: 0,
            root: 0,
            subcategories: 0,
            products_linked: 0,
        }),
    },
});

const page = usePage();
const flashCategoryModal = computed(() => page.props?.flash?.category_modal ?? null);
const handledCategoryModalToken = ref('');

const statCards = computed(() => ([
    { key: 'categories', label: 'Categorias totais', value: String(props.stats?.categories ?? 0), icon: Layers3, tone: 'bg-slate-100 text-slate-700' },
    { key: 'root', label: 'Categorias principais', value: String(props.stats?.root ?? 0), icon: FolderTree, tone: 'bg-slate-100 text-slate-700' },
    { key: 'subcategories', label: 'Subcategorias', value: String(props.stats?.subcategories ?? 0), icon: Tags, tone: 'bg-slate-100 text-slate-700' },
    { key: 'products_linked', label: 'Produtos vinculados', value: String(props.stats?.products_linked ?? 0), icon: Box, tone: 'bg-slate-100 text-slate-700' },
]));

const rows = computed(() => props.categories?.data ?? []);
const paginationLinks = computed(() => props.categories?.links ?? []);

const filterForm = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
});

const statusOptions = [
    { value: '', label: 'Todos os status' },
    { value: 'active', label: 'Ativas' },
    { value: 'inactive', label: 'Inativas' },
];

const showModal = ref(false);
const showDeleteModal = ref(false);
const editingCategory = ref(null);
const editingSubcategory = ref(null);
const categoryToDelete = ref(null);
const currentStep = ref(1);
const subcategoriesExpanded = ref(true);
const subcategoryEditorOpen = ref(false);

const categoryForm = useForm({
    name: '',
    slug: '',
    description: '',
    is_active: true,
});

const subcategoryForm = useForm({
    parent_id: '',
    name: '',
    slug: '',
    description: '',
    is_active: true,
});

const deleteForm = useForm({});

const isEditingCategory = computed(() => Boolean(editingCategory.value?.id));
const isEditingSubcategory = computed(() => Boolean(editingSubcategory.value?.id));
const managedCategory = computed(() => {
    const categoryId = Number(editingCategory.value?.id ?? 0);
    if (!categoryId) return null;

    return rows.value.find((category) => Number(category.id) === categoryId) ?? null;
});
const managedSubcategories = computed(() => managedCategory.value?.children ?? []);
const canManageSubcategories = computed(() => Boolean(managedCategory.value?.id));

const modalTitle = computed(() => (isEditingCategory.value ? 'Editar categoria' : 'Nova categoria'));
const modalDescription = computed(() => (
    canManageSubcategories.value
        ? 'Ajuste os dados da categoria e organize as subcategorias na segunda etapa.'
        : 'Cadastre primeiro a categoria principal. Depois, use a segunda etapa para organizar as subcategorias.'
));
const wizardSteps = ['Dados da categoria', 'Subcategorias'];

const applyFilters = () => {
    router.get(
        route('admin.categories.index'),
        {
            search: filterForm.search || undefined,
            status: filterForm.status || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

const clearSearch = () => {
    if (!String(filterForm.search ?? '').trim()) return;
    filterForm.search = '';
    applyFilters();
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFilters();
};

const resetCategoryForm = () => {
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryForm.name = '';
    categoryForm.slug = '';
    categoryForm.description = '';
    categoryForm.is_active = true;
};

const resetSubcategoryForm = () => {
    subcategoryForm.reset();
    subcategoryForm.clearErrors();
    subcategoryForm.parent_id = managedCategory.value?.id ?? '';
    subcategoryForm.name = '';
    subcategoryForm.slug = '';
    subcategoryForm.description = '';
    subcategoryForm.is_active = true;
};

const fillCategoryForm = (category) => {
    categoryForm.name = String(category?.name ?? '');
    categoryForm.slug = String(category?.slug ?? '');
    categoryForm.description = String(category?.description ?? '');
    categoryForm.is_active = Boolean(category?.is_active);
    categoryForm.clearErrors();
};

const fillSubcategoryForm = (subcategory) => {
    subcategoryForm.parent_id = managedCategory.value?.id ?? subcategory?.parent_id ?? '';
    subcategoryForm.name = String(subcategory?.name ?? '');
    subcategoryForm.slug = String(subcategory?.slug ?? '');
    subcategoryForm.description = String(subcategory?.description ?? '');
    subcategoryForm.is_active = Boolean(subcategory?.is_active);
    subcategoryForm.clearErrors();
};

const closeSubcategoryEditor = () => {
    editingSubcategory.value = null;
    subcategoryEditorOpen.value = false;
    resetSubcategoryForm();
};

const openCreateCategory = () => {
    editingCategory.value = null;
    currentStep.value = 1;
    subcategoriesExpanded.value = true;
    resetCategoryForm();
    closeSubcategoryEditor();
    showModal.value = true;
};

const openEditCategory = (category, step = 1) => {
    editingCategory.value = category;
    currentStep.value = Math.min(Math.max(Number(step) || 1, 1), 2);
    subcategoriesExpanded.value = true;
    fillCategoryForm(category);
    closeSubcategoryEditor();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingCategory.value = null;
    editingSubcategory.value = null;
    currentStep.value = 1;
    subcategoriesExpanded.value = true;
    subcategoryEditorOpen.value = false;
    resetCategoryForm();
    resetSubcategoryForm();
};

const openDeleteModal = (category) => {
    categoryToDelete.value = category;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    categoryToDelete.value = null;
};

const openCreateSubcategory = () => {
    if (!canManageSubcategories.value) return;

    editingSubcategory.value = null;
    subcategoriesExpanded.value = true;
    subcategoryEditorOpen.value = true;
    resetSubcategoryForm();
};

const openEditSubcategory = (subcategory) => {
    editingSubcategory.value = subcategory;
    subcategoriesExpanded.value = true;
    subcategoryEditorOpen.value = true;
    fillSubcategoryForm(subcategory);
};

const handleStepChange = (step) => {
    const safeStep = Math.min(Math.max(Number(step) || 1, 1), 2);
    if (safeStep === 2 && !canManageSubcategories.value) {
        return;
    }

    currentStep.value = safeStep;
};

const submitCategory = (mode = 'save') => {
    categoryForm.clearErrors('name');

    if (!String(categoryForm.name ?? '').trim()) {
        categoryForm.setError('name', 'Informe o nome da categoria.');
        return;
    }

    const continueToSubcategories = mode === 'continue';

    categoryForm.transform((data) => ({
        ...data,
        continue_to_subcategories: continueToSubcategories,
    }));

    const visitOptions = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            closeModal();
        },
        onFinish: () => {
            categoryForm.transform((data) => data);
        },
    };

    if (isEditingCategory.value) {
        categoryForm.put(route('admin.categories.update', editingCategory.value.id), visitOptions);
        return;
    }

    categoryForm.post(route('admin.categories.store'), visitOptions);
};

const submitSubcategory = () => {
    subcategoryForm.clearErrors('parent_id', 'name');
    subcategoryForm.parent_id = managedCategory.value?.id ?? '';

    if (!Number(subcategoryForm.parent_id || 0)) {
        subcategoryForm.setError('parent_id', 'Salve a categoria principal antes de cadastrar subcategorias.');
        return;
    }

    if (!String(subcategoryForm.name ?? '').trim()) {
        subcategoryForm.setError('name', 'Informe o nome da subcategoria.');
        return;
    }

    subcategoryForm.transform((data) => ({
        ...data,
        parent_id: managedCategory.value?.id ?? '',
    }));

    const visitOptions = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            closeSubcategoryEditor();
            currentStep.value = 2;
            subcategoriesExpanded.value = true;
        },
        onFinish: () => {
            subcategoryForm.transform((data) => data);
        },
    };

    if (isEditingSubcategory.value) {
        subcategoryForm.put(route('admin.categories.update', editingSubcategory.value.id), visitOptions);
        return;
    }

    subcategoryForm.post(route('admin.categories.store'), visitOptions);
};

const removeCategory = () => {
    if (!categoryToDelete.value?.id) return;

    deleteForm.delete(route('admin.categories.destroy', categoryToDelete.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            closeDeleteModal();

            if (Number(editingSubcategory.value?.id ?? 0) === Number(categoryToDelete.value?.id ?? 0)) {
                closeSubcategoryEditor();
            }
        },
    });
};

watch(
    [flashCategoryModal, rows],
    ([modalState]) => {
        const token = String(modalState?.token ?? '').trim();
        const categoryId = Number(modalState?.category_id ?? 0);
        const step = Number(modalState?.step ?? 1);

        if (!token || handledCategoryModalToken.value === token || categoryId <= 0) {
            return;
        }

        const category = rows.value.find((item) => Number(item.id) === categoryId);
        if (!category) {
            return;
        }

        handledCategoryModalToken.value = token;
        openEditCategory(category, step);
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <Head title="Categorias" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Categorias" :show-table-view-toggle="false">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="stat in statCards"
                    :key="stat.key"
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
                    <div class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="h-4 w-4 text-slate-500" />
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Buscar categoria principal ou subcategoria"
                            class="w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keyup.enter="applyFilters"
                        >
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

                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
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
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="resetFilters"
                        >
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            @click="openCreateCategory"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Nova categoria
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex justify-end">
                    <TableViewToggle />
                </div>

                <div class="mt-4 space-y-3">
                    <article
                        v-for="category in rows"
                        :key="category.id"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md"
                        role="button"
                        tabindex="0"
                        @click="openEditCategory(category)"
                        @keydown.enter.prevent="openEditCategory(category)"
                        @keydown.space.prevent="openEditCategory(category)"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-semibold text-slate-900">{{ category.name }}</h3>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                        :class="category.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                    >
                                        {{ category.status_label }}
                                    </span>
                                </div>
                                <p v-if="category.description" class="max-w-3xl text-sm text-slate-500">
                                    {{ category.description }}
                                </p>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 font-semibold">
                                        Slug: {{ category.slug }}
                                    </span>
                                    <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 font-semibold">
                                        {{ category.products_count }} produto(s)
                                    </span>
                                    <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 font-semibold">
                                        {{ category.children_count }} subcategoria(s)
                                    </span>
                                </div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                    Clique para editar e organizar as subcategorias
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click.stop="openEditCategory(category)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                    @click.stop="openDeleteModal(category)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Excluir
                                </button>
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="rows.length === 0"
                        class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500"
                    >
                        Nenhuma categoria principal encontrada.
                    </div>
                </div>

                <PaginationLinks :links="paginationLinks" :min-links="4" />
            </section>
        </section>

        <Modal :show="showModal" max-width="5xl" @close="closeModal">
            <WizardModalFrame
                :title="modalTitle"
                :description="modalDescription"
                :steps="wizardSteps"
                :current-step="currentStep"
                :steps-clickable="canManageSubcategories"
                :max-clickable-step="canManageSubcategories ? 2 : 1"
                @close="closeModal"
                @step-change="handleStepChange"
            >
                <div v-if="currentStep === 1" class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                            <input
                                v-model="categoryForm.name"
                                type="text"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Ex.: Confeitaria"
                            >
                            <p v-if="categoryForm.errors.name" class="mt-1 text-xs text-rose-600">{{ categoryForm.errors.name }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug (opcional)</label>
                            <input
                                v-model="categoryForm.slug"
                                type="text"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="confeitaria"
                            >
                            <p v-if="categoryForm.errors.slug" class="mt-1 text-xs text-rose-600">{{ categoryForm.errors.slug }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</label>
                        <textarea
                            v-model="categoryForm.description"
                            rows="3"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Descrição curta da categoria principal"
                        />
                        <p v-if="categoryForm.errors.description" class="mt-1 text-xs text-rose-600">{{ categoryForm.errors.description }}</p>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input v-model="categoryForm.is_active" type="checkbox" class="rounded border-slate-300">
                        Categoria ativa
                    </label>
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-if="!canManageSubcategories"
                        class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        Salve a categoria principal primeiro para começar a cadastrar subcategorias.
                    </div>

                    <template v-else>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Categoria principal</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ managedCategory?.name }}</p>
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                                    {{ managedSubcategories.length }} subcategoria(s)
                                </span>
                            </div>
                        </div>

                        <section class="rounded-2xl border border-slate-200 bg-slate-50/70">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                                @click="subcategoriesExpanded = !subcategoriesExpanded"
                            >
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Subcategorias</p>
                                    <p class="text-xs text-slate-500">
                                        Cadastre e organize as subcategorias sem misturar com a listagem principal.
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        @click.stop="openCreateSubcategory"
                                    >
                                        <Plus class="h-3.5 w-3.5" />
                                        Nova subcategoria
                                    </button>
                                    <component :is="subcategoriesExpanded ? ChevronDown : ChevronRight" class="h-4 w-4 text-slate-500" />
                                </div>
                            </button>

                            <div v-if="subcategoriesExpanded" class="border-t border-slate-200 px-4 py-4">
                                <div
                                    v-if="subcategoryEditorOpen"
                                    class="mb-4 rounded-2xl border border-slate-200 bg-white p-4"
                                >
                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cadastro rápido</p>
                                            <p class="text-sm font-semibold text-slate-900">
                                                {{ isEditingSubcategory ? 'Editar subcategoria' : 'Nova subcategoria' }}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="closeSubcategoryEditor"
                                        >
                                            Fechar
                                        </button>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div>
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                                            <input
                                                v-model="subcategoryForm.name"
                                                type="text"
                                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                                placeholder="Ex.: Bolos no pote"
                                            >
                                            <p v-if="subcategoryForm.errors.name" class="mt-1 text-xs text-rose-600">{{ subcategoryForm.errors.name }}</p>
                                        </div>

                                        <div>
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug (opcional)</label>
                                            <input
                                                v-model="subcategoryForm.slug"
                                                type="text"
                                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                                placeholder="bolos-no-pote"
                                            >
                                            <p v-if="subcategoryForm.errors.slug" class="mt-1 text-xs text-rose-600">{{ subcategoryForm.errors.slug }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</label>
                                        <textarea
                                            v-model="subcategoryForm.description"
                                            rows="2"
                                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                            placeholder="Descrição curta da subcategoria"
                                        />
                                        <p v-if="subcategoryForm.errors.description" class="mt-1 text-xs text-rose-600">{{ subcategoryForm.errors.description }}</p>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input v-model="subcategoryForm.is_active" type="checkbox" class="rounded border-slate-300">
                                            Subcategoria ativa
                                        </label>

                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                @click="closeSubcategoryEditor"
                                            >
                                                Cancelar
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                                :disabled="subcategoryForm.processing"
                                                @click="submitSubcategory"
                                            >
                                                {{ subcategoryForm.processing ? 'Salvando...' : 'Salvar subcategoria' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="managedSubcategories.length" class="space-y-2">
                                    <article
                                        v-for="subcategory in managedSubcategories"
                                        :key="subcategory.id"
                                        class="rounded-2xl border border-slate-200 bg-white px-3 py-3 transition hover:border-slate-300"
                                        role="button"
                                        tabindex="0"
                                        @click="openEditSubcategory(subcategory)"
                                        @keydown.enter.prevent="openEditSubcategory(subcategory)"
                                        @keydown.space.prevent="openEditSubcategory(subcategory)"
                                    >
                                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                            <div class="space-y-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-sm font-semibold text-slate-900">{{ subcategory.name }}</p>
                                                    <span
                                                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                        :class="subcategory.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                                    >
                                                        {{ subcategory.status_label }}
                                                    </span>
                                                </div>
                                                <p v-if="subcategory.description" class="text-xs text-slate-500">
                                                    {{ subcategory.description }}
                                                </p>
                                                <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-semibold">
                                                        {{ subcategory.slug }}
                                                    </span>
                                                    <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 font-semibold">
                                                        {{ subcategory.products_count }} produto(s)
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2 md:justify-end">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                    @click.stop="openEditSubcategory(subcategory)"
                                                >
                                                    <Pencil class="h-3.5 w-3.5" />
                                                    Editar
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                                    @click.stop="openDeleteModal(subcategory)"
                                                >
                                                    <Trash2 class="h-3.5 w-3.5" />
                                                    Excluir
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                </div>

                                <div
                                    v-else
                                    class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500"
                                >
                                    Nenhuma subcategoria cadastrada para esta categoria.
                                </div>
                            </div>
                        </section>
                    </template>
                </div>

                <template #footer>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeModal"
                        >
                            Cancelar
                        </button>

                        <template v-if="currentStep === 1">
                            <button
                                v-if="canManageSubcategories"
                                type="button"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                @click="currentStep = 2"
                            >
                                Subcategorias
                            </button>
                            <button
                                v-else
                                type="button"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                :disabled="categoryForm.processing"
                                @click="submitCategory('continue')"
                            >
                                Salvar e ir para subcategorias
                            </button>
                            <button
                                type="button"
                                class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="categoryForm.processing"
                                @click="submitCategory('save')"
                            >
                                {{ categoryForm.processing ? 'Salvando...' : 'Salvar categoria' }}
                            </button>
                        </template>

                        <template v-else>
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                @click="currentStep = 1"
                            >
                                Voltar para categoria
                            </button>
                        </template>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir categoria"
            message="Tem certeza que deseja excluir esta categoria ou subcategoria?"
            :item-label="categoryToDelete?.name ? `Item: ${categoryToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="removeCategory"
        />
    </AuthenticatedLayout>
</template>
