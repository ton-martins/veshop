<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import BrandingImageUploader from '@/Components/BrandingImageUploader.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Box, Boxes, CircleDollarSign, AlertTriangle, Plus, Search, Filter, ChevronRight, Tags, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    products: { type: Object, default: () => ({ data: [], links: [] }) },
    categories: { type: Array, default: () => [] },
    categoryHighlights: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: '', category_id: null }) },
    stats: { type: Object, default: () => ({ products: 0, active: 0, stockout: 0, margin: null }) },
    units: { type: Array, default: () => ['un', 'kg', 'lts'] },
});

const rows = computed(() => props.products?.data ?? []);
const paginationLinks = computed(() => props.products?.links ?? []);

const statCards = computed(() => ([
    {
        key: 'products',
        label: 'Produtos cadastrados',
        value: String(props.stats?.products ?? 0),
        icon: Box,
        tone: 'bg-slate-100 text-slate-700',
    },
    {
        key: 'active',
        label: 'Produtos ativos',
        value: String(props.stats?.active ?? 0),
        icon: Boxes,
        tone: 'bg-emerald-100 text-emerald-700',
    },
    {
        key: 'stockout',
        label: 'Sem estoque',
        value: String(props.stats?.stockout ?? 0),
        icon: AlertTriangle,
        tone: 'bg-amber-100 text-amber-700',
    },
    {
        key: 'margin',
        label: 'Margem média',
        value: props.stats?.margin !== null && props.stats?.margin !== undefined
            ? `${Number(props.stats.margin).toFixed(1)}%`
            : '--',
        icon: CircleDollarSign,
        tone: 'bg-blue-100 text-blue-700',
    },
]));

const filterForm = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
    category_id: props.filters?.category_id ?? '',
});

const categoryFilterOptions = computed(() => [
    { value: '', label: 'Todas as categorias' },
    ...(props.categories ?? []).map((category) => ({
        value: category.id,
        label: category.name,
    })),
]);

const statusFilterOptions = [
    { value: '', label: 'Todos os status' },
    { value: 'active', label: 'Ativos' },
    { value: 'inactive', label: 'Inativos' },
    { value: 'out_of_stock', label: 'Sem estoque' },
];

const productCategoryOptions = computed(() => [
    { value: '', label: 'Sem categoria' },
    ...(props.categories ?? []).map((category) => ({
        value: category.id,
        label: category.name,
    })),
]);

const unitOptions = computed(() =>
    (props.units ?? []).map((unit) => ({
        value: unit,
        label: unit,
    })),
);

const applyFilters = () => {
    router.get(
        route('admin.products.index'),
        {
            search: filterForm.search || undefined,
            status: filterForm.status || undefined,
            category_id: filterForm.category_id || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true }
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
    filterForm.category_id = '';
    applyFilters();
};

const showModal = ref(false);
const editingProduct = ref(null);
const showDeleteModal = ref(false);
const productToDelete = ref(null);

const productForm = useForm({
    name: '',
    sku: '',
    category_id: '',
    description: '',
    cost_price: '',
    sale_price: '',
    stock_quantity: 0,
    unit: props.units?.[0] ?? 'un',
    image_url: '',
    image_file: null,
    remove_image: false,
    image_preview: '',
    is_active: true,
});
const deleteForm = useForm({});

const isEditing = computed(() => Boolean(editingProduct.value?.id));

const openCreate = () => {
    editingProduct.value = null;
    productForm.reset();
    productForm.clearErrors();
    productForm.stock_quantity = 0;
    productForm.unit = props.units?.[0] ?? 'un';
    productForm.image_file = null;
    productForm.remove_image = false;
    productForm.image_preview = '';
    productForm.is_active = true;
    showModal.value = true;
};

const openEdit = (product) => {
    editingProduct.value = product;
    productForm.name = product.name ?? '';
    productForm.sku = product.sku ?? '';
    productForm.category_id = product.category_id ?? '';
    productForm.description = product.description ?? '';
    productForm.cost_price = product.cost_price ?? '';
    productForm.sale_price = product.sale_price ?? '';
    productForm.stock_quantity = Number.parseInt(product.stock_quantity ?? 0, 10) || 0;
    productForm.unit = product.unit ?? (props.units?.[0] ?? 'un');
    productForm.image_url = product.image_url ?? '';
    productForm.image_file = null;
    productForm.remove_image = false;
    productForm.image_preview = product.image_url ?? '';
    productForm.is_active = Boolean(product.is_active);
    productForm.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingProduct.value = null;
};

const handleProductImageChange = ({ file, preview }) => {
    productForm.image_file = file ?? null;
    productForm.image_preview = preview ?? '';
    productForm.remove_image = false;

    if (productForm.image_file) {
        productForm.image_url = '';
    }
};

const removeProductImage = () => {
    productForm.image_file = null;
    productForm.image_preview = '';
    productForm.image_url = '';
    productForm.remove_image = true;
};

const submitProduct = () => {
    if (isEditing.value) {
        productForm.transform((data) => ({
            ...data,
            _method: 'put',
            image_url: String(data.image_url ?? '').trim(),
            image_file: data.image_file ?? null,
            remove_image: Boolean(data.remove_image),
        })).post(route('admin.products.update', editingProduct.value.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeModal,
        });

        return;
    }

    productForm.transform((data) => ({
        ...data,
        image_url: String(data.image_url ?? '').trim(),
        image_file: data.image_file ?? null,
        remove_image: Boolean(data.remove_image),
    })).post(route('admin.products.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeModal,
    });
};

const openDeleteModal = (product) => {
    productToDelete.value = product;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    productToDelete.value = null;
};

const removeProduct = () => {
    if (!productToDelete.value?.id) return;

    deleteForm.delete(route('admin.products.destroy', productToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};

const formatMoney = (value) => {
    const parsed = Number(value ?? 0);
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number.isFinite(parsed) ? parsed : 0);
};

const formatStock = (quantity, unit) => {
    const safeQuantity = Number.parseInt(String(quantity ?? 0), 10);
    const safeUnit = props.units.includes(unit) ? unit : (props.units?.[0] ?? 'un');
    return `${Number.isFinite(safeQuantity) && safeQuantity >= 0 ? safeQuantity : 0} ${safeUnit}`;
};

const fallbackImage = (name) => `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'Produto')}&background=e2e8f0&color=334155&size=64`;
</script>

<template>
    <Head title="Produtos" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Produtos">
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
                    <div class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Buscar por SKU ou nome do produto"
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
                            v-model="filterForm.category_id"
                            :options="categoryFilterOptions"
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
                            Novo produto
                        </button>
                    </div>
                </div>

                <div class="mt-4 space-y-4">
                    <section class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="text-sm font-semibold text-slate-900">Categorias em destaque</h2>
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="route('admin.categories.index')"
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    <Tags class="h-3.5 w-3.5" />
                                    Todas Categorias
                                    <ChevronRight class="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </div>

                        <div v-if="props.categoryHighlights.length > 0" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <article
                                v-for="category in props.categoryHighlights"
                                :key="category.id"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm"
                            >
                                <p class="text-sm font-semibold text-slate-800">{{ category.name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ category.qty }} produtos</p>
                            </article>
                        </div>
                        <div v-else class="mt-4 rounded-lg bg-white px-3 py-2 text-xs text-slate-500 ring-1 ring-slate-200">
                            Nenhuma categoria ativa.
                        </div>
                    </section>

                    <div class="rounded-xl border border-slate-200 bg-white">
                        <table class="w-full min-w-[980px] divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 whitespace-nowrap">Produto</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Categoria</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Estoque</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Preço</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Status</th>
                                    <th class="px-4 py-3 whitespace-nowrap text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-if="rows.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                        Nenhum produto encontrado.
                                    </td>
                                </tr>
                                <tr v-for="product in rows" :key="product.id">
                                    <td class="px-4 py-3">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <img
                                                :src="product.image_url || fallbackImage(product.name)"
                                                :alt="product.name"
                                                class="h-10 w-10 rounded-lg border border-slate-200 object-cover"
                                                loading="lazy"
                                            >
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-slate-900">{{ product.name }}</p>
                                                <p class="text-xs text-slate-500">{{ product.sku || 'Sem SKU' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ product.category_name || 'Sem categoria' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ formatStock(product.stock_quantity, product.unit) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-slate-800">{{ formatMoney(product.sale_price) }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-1 text-[11px] font-semibold"
                                            :class="product.status_label === 'Ativo' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                        >
                                            {{ product.status_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                @click="openEdit(product)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="openDeleteModal(product)"
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

                    <aside v-if="false" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <h2 class="text-sm font-semibold text-slate-900">Categorias em destaque</h2>
                        <ul class="space-y-2">
                            <li
                                v-for="category in props.categoryHighlights"
                                :key="category.id"
                                class="flex items-center justify-between rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200"
                            >
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ category.name }}</p>
                                    <p class="text-xs text-slate-500">{{ category.qty }} produtos</p>
                                </div>
                            </li>
                            <li v-if="props.categoryHighlights.length === 0" class="rounded-lg bg-white px-3 py-2 text-xs text-slate-500 ring-1 ring-slate-200">
                                Nenhuma categoria ativa.
                            </li>
                        </ul>

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-800">
                                Ver catálogo completo
                                <ChevronRight class="h-3.5 w-3.5" />
                            </button>
                            <Link
                                :href="route('admin.categories.index')"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                <Tags class="h-3.5 w-3.5" />
                                Categorias
                            </Link>
                        </div>
                    </aside>
                </div>

                <PaginationLinks :links="paginationLinks" :min-links="4" />
            </section>
        </section>

        <Modal :show="showModal" max-width="5xl" @close="closeModal">
            <WizardModalFrame
                :title="isEditing ? 'Editar produto' : 'Novo produto'"
                description="Preencha os dados do produto."
                :steps="['Dados do produto']"
                :current-step="1"
                @close="closeModal"
            >
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input
                            v-model="productForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Bolo Piscina Chocolate"
                        >
                        <p v-if="productForm.errors.name" class="mt-1 text-xs text-rose-600">{{ productForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">SKU</label>
                        <input
                            v-model="productForm.sku"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: BOL-001"
                        >
                        <p v-if="productForm.errors.sku" class="mt-1 text-xs text-rose-600">{{ productForm.errors.sku }}</p>
                    </div>

                    <div>
                        <div class="w-full max-w-[220px] md:max-w-[320px]">
                            <BrandingImageUploader
                                label="Imagem do produto"
                                help-text="Envie JPG, PNG ou WEBP."
                                :initial-preview="productForm.image_preview || productForm.image_url"
                                :aspect-ratio="1"
                                :desktop-aspect-ratio="3.2"
                                @change="handleProductImageChange"
                            />
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                @click="removeProductImage"
                            >
                                Remover imagem
                            </button>
                        </div>
                        <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500">URL externa (opcional)</label>
                        <input
                            v-model="productForm.image_url"
                            type="url"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="https://..."
                        >
                        <p v-if="productForm.errors.image_url" class="mt-1 text-xs text-rose-600">{{ productForm.errors.image_url }}</p>
                        <p v-if="productForm.errors.image_file" class="mt-1 text-xs text-rose-600">{{ productForm.errors.image_file }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Categoria</label>
                        <UiSelect
                            v-model="productForm.category_id"
                            :options="productCategoryOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="productForm.errors.category_id" class="mt-1 text-xs text-rose-600">{{ productForm.errors.category_id }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Custo</label>
                        <input
                            v-model="productForm.cost_price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="productForm.errors.cost_price" class="mt-1 text-xs text-rose-600">{{ productForm.errors.cost_price }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Preço de venda</label>
                        <input
                            v-model="productForm.sale_price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="productForm.errors.sale_price" class="mt-1 text-xs text-rose-600">{{ productForm.errors.sale_price }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quantidade</label>
                        <input
                            v-model="productForm.stock_quantity"
                            type="number"
                            min="0"
                            step="1"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="productForm.errors.stock_quantity" class="mt-1 text-xs text-rose-600">{{ productForm.errors.stock_quantity }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unidade</label>
                        <UiSelect
                            v-model="productForm.unit"
                            :options="unitOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="productForm.errors.unit" class="mt-1 text-xs text-rose-600">{{ productForm.errors.unit }}</p>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</label>
                    <textarea
                        v-model="productForm.description"
                        rows="3"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        placeholder="Descrição do produto"
                    />
                    <p v-if="productForm.errors.description" class="mt-1 text-xs text-rose-600">{{ productForm.errors.description }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input v-model="productForm.is_active" type="checkbox" class="rounded border-slate-300">
                    Produto ativo
                </label>

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
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="productForm.processing"
                            @click="submitProduct"
                        >
                            {{ productForm.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir produto"
            message="Tem certeza que deseja excluir este produto?"
            :item-label="productToDelete?.name ? `Produto: ${productToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="removeProduct"
        />
    </AuthenticatedLayout>
</template>
