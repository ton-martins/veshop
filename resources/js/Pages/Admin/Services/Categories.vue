<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Layers3, Tags, Briefcase, AlertTriangle, Plus, Search, Filter, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    categories: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({ search: '', status: '' }) },
    stats: {
        type: Object,
        default: () => ({
            categories: 0,
            active: 0,
            services_linked: 0,
            uncategorized: 0,
        }),
    },
});

const statCards = computed(() => ([
    {
        key: 'categories',
        label: 'Categorias de serviços',
        value: String(props.stats?.categories ?? 0),
        icon: Layers3,
        tone: 'bg-slate-100 text-slate-700',
    },
    {
        key: 'active',
        label: 'Categorias ativas',
        value: String(props.stats?.active ?? 0),
        icon: Tags,
        tone: 'bg-blue-100 text-blue-700',
    },
    {
        key: 'services_linked',
        label: 'Serviços vinculados',
        value: String(props.stats?.services_linked ?? 0),
        icon: Briefcase,
        tone: 'bg-emerald-100 text-emerald-700',
    },
    {
        key: 'uncategorized',
        label: 'Serviços sem categoria',
        value: String(props.stats?.uncategorized ?? 0),
        icon: AlertTriangle,
        tone: 'bg-amber-100 text-amber-700',
    },
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

const applyFilters = () => {
    router.get(
        route('admin.services.categories'),
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

const showModal = ref(false);
const editingCategory = ref(null);
const showDeleteModal = ref(false);
const categoryToDelete = ref(null);

const categoryForm = useForm({
    name: '',
    slug: '',
    description: '',
    sort_order: 0,
    is_active: true,
});
const deleteForm = useForm({});

const isEditing = computed(() => Boolean(editingCategory.value?.id));

const openCreate = () => {
    editingCategory.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryForm.sort_order = 0;
    categoryForm.is_active = true;
    showModal.value = true;
};

const openEdit = (category) => {
    editingCategory.value = category;
    categoryForm.name = category.name ?? '';
    categoryForm.slug = category.slug ?? '';
    categoryForm.description = category.description ?? '';
    categoryForm.sort_order = Number(category.sort_order ?? 0);
    categoryForm.is_active = Boolean(category.is_active);
    categoryForm.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingCategory.value = null;
};

const submitCategory = () => {
    if (isEditing.value) {
        categoryForm.put(route('admin.services.categories.update', editingCategory.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    categoryForm.post(route('admin.services.categories.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const openDeleteModal = (category) => {
    categoryToDelete.value = category;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    categoryToDelete.value = null;
};

const removeCategory = () => {
    if (!categoryToDelete.value?.id) return;

    deleteForm.delete(route('admin.services.categories.destroy', categoryToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};
</script>

<template>
    <Head title="Categorias de serviços" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Categorias de serviços" :show-table-view-toggle="false">
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
                            placeholder="Buscar categoria por nome ou slug"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
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
                        <button
                            type="button"
                            class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto"
                            @click="resetFilters"
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
                            Nova categoria
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
                                <th class="px-4 py-3">Categoria</th>
                                <th class="px-4 py-3">Slug</th>
                                <th class="px-4 py-3">Ordem</th>
                                <th class="px-4 py-3">Serviços</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="rows.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhuma categoria de serviço encontrada.
                                </td>
                            </tr>
                            <tr v-for="category in rows" :key="category.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ category.name }}</p>
                                    <p v-if="category.description" class="text-xs text-slate-500">{{ category.description }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ category.slug }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ category.sort_order }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ category.services_count }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                        :class="category.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                    >
                                        {{ category.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="openEdit(category)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="openDeleteModal(category)"
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
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ isEditing ? 'Editar categoria de serviço' : 'Nova categoria de serviço' }}</h3>
                        <p class="text-sm text-slate-500">Preencha os dados da categoria.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                        Fechar
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome *</label>
                        <input
                            v-model="categoryForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Abertura de empresa"
                        >
                        <p v-if="categoryForm.errors.name" class="mt-1 text-xs text-rose-600">{{ categoryForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug (opcional)</label>
                        <input
                            v-model="categoryForm.slug"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="abertura-empresa"
                        >
                        <p v-if="categoryForm.errors.slug" class="mt-1 text-xs text-rose-600">{{ categoryForm.errors.slug }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</label>
                        <textarea
                            v-model="categoryForm.description"
                            rows="3"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Descrição curta da categoria de serviço"
                        />
                        <p v-if="categoryForm.errors.description" class="mt-1 text-xs text-rose-600">{{ categoryForm.errors.description }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ordem</label>
                        <input
                            v-model.number="categoryForm.sort_order"
                            type="number"
                            min="0"
                            max="9999"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="categoryForm.errors.sort_order" class="mt-1 text-xs text-rose-600">{{ categoryForm.errors.sort_order }}</p>
                    </div>

                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="categoryForm.is_active" type="checkbox" class="rounded border-slate-300">
                            Categoria ativa
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="categoryForm.processing"
                        @click="submitCategory"
                    >
                        {{ categoryForm.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir categoria de serviço"
            message="Tem certeza que deseja excluir esta categoria de serviço?"
            :item-label="categoryToDelete?.name ? `Categoria: ${categoryToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="removeCategory"
        />
    </AuthenticatedLayout>
</template>
