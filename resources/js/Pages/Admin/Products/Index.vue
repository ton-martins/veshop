<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Box, Boxes, CircleDollarSign, AlertTriangle, Plus, Search, Filter, ChevronRight, Tags, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    products: { type: Object, default: () => ({ data: [], links: [] }) },
    categories: { type: Array, default: () => [] },
    categoryHighlights: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: '', category_id: null }) },
    stats: { type: Object, default: () => ({ products: 0, active: 0, stockout: 0, margin: null }) },
    units: { type: Array, default: () => ['un', 'kg', 'lts'] },
    storage: {
        type: Object,
        default: () => ({
            usage_bytes: 0,
            limit_bytes: null,
            remaining_bytes: null,
            gallery_limit_per_product: 5,
            gallery_technical_limit: 5,
        }),
    },
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
const galleryInputRef = ref(null);
const productWizardSteps = ['Informações', 'Preço e estoque', 'Galeria', 'Variações'];
const productWizardStep = ref(1);
const productWizardValidationRequested = ref(false);

const createEmptyVariation = () => ({
    id: null,
    name: '',
    sku: '',
    sale_price: '',
    cost_price: '',
    stock_quantity: 0,
    is_active: true,
    attributes_text: '',
});

const productForm = useForm({
    name: '',
    sku: '',
    category_id: '',
    description: '',
    cost_price: '',
    sale_price: '',
    stock_quantity: 0,
    unit: props.units?.[0] ?? 'un',
    gallery_files: [],
    gallery_images: [],
    remove_gallery_ids: [],
    variations: [],
    is_active: true,
});
const deleteForm = useForm({});

const isEditing = computed(() => Boolean(editingProduct.value?.id));
const galleryLimitPerProduct = computed(() => Number(props.storage?.gallery_limit_per_product ?? 5) || 5);
const currentGalleryImageCount = computed(() =>
    Array.isArray(productForm.gallery_images) ? productForm.gallery_images.length : 0,
);
const galleryAvailableSlots = computed(() =>
    Math.max(0, galleryLimitPerProduct.value - currentGalleryImageCount.value),
);
const isGalleryPickerDisabled = computed(() => galleryAvailableSlots.value <= 0);

const resetProductForm = () => {
    productForm.reset();
    productForm.clearErrors();
    productForm.name = '';
    productForm.sku = '';
    productForm.category_id = '';
    productForm.description = '';
    productForm.cost_price = '';
    productForm.sale_price = '';
    productForm.stock_quantity = 0;
    productForm.unit = props.units?.[0] ?? 'un';
    productForm.gallery_files = [];
    productForm.gallery_images = [];
    productForm.remove_gallery_ids = [];
    productForm.variations = [];
    productForm.is_active = true;
    if (galleryInputRef.value) {
        galleryInputRef.value.value = '';
    }
};

const openCreate = () => {
    revokeGalleryPreviewUrls();
    editingProduct.value = null;
    productWizardStep.value = 1;
    productWizardValidationRequested.value = false;
    resetProductForm();
    showModal.value = true;
};

const openEdit = (product) => {
    revokeGalleryPreviewUrls();
    editingProduct.value = product;
    productWizardStep.value = 1;
    productWizardValidationRequested.value = false;
    productForm.name = product.name ?? '';
    productForm.sku = product.sku ?? '';
    productForm.category_id = product.category_id ?? '';
    productForm.description = product.description ?? '';
    productForm.cost_price = product.cost_price ?? '';
    productForm.sale_price = product.sale_price ?? '';
    productForm.stock_quantity = Number.parseInt(product.stock_quantity ?? 0, 10) || 0;
    productForm.unit = product.unit ?? (props.units?.[0] ?? 'un');
    productForm.gallery_files = [];
    productForm.gallery_images = Array.isArray(product.images)
        ? product.images.map((image) => ({
            id: image.id ?? null,
            image_url: image.image_url ?? '',
            file: null,
            from_server: Number(image.id ?? 0) > 0,
        }))
        : [];
    productForm.remove_gallery_ids = [];
    productForm.variations = Array.isArray(product.variations)
        ? product.variations.map((variation) => ({
            id: variation.id ?? null,
            name: variation.name ?? '',
            sku: variation.sku ?? '',
            sale_price: variation.sale_price ?? '',
            cost_price: variation.cost_price ?? '',
            stock_quantity: Number.parseInt(variation.stock_quantity ?? 0, 10) || 0,
            is_active: Boolean(variation.is_active ?? true),
            attributes_text: Object.entries(variation.attributes ?? {})
                .map(([key, value]) => `${key}:${value}`)
                .join(', '),
        }))
        : [];
    productForm.is_active = Boolean(product.is_active);
    productForm.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    revokeGalleryPreviewUrls();
    showModal.value = false;
    editingProduct.value = null;
    productWizardStep.value = 1;
    productWizardValidationRequested.value = false;
    resetProductForm();
};

const revokeGalleryPreviewUrls = () => {
    if (!Array.isArray(productForm.gallery_images)) return;

    productForm.gallery_images.forEach((image) => {
        if (!image?.from_server && image?.image_url && String(image.image_url).startsWith('blob:')) {
            URL.revokeObjectURL(image.image_url);
        }
    });
};

const addVariationRow = () => {
    productForm.variations = [
        ...(Array.isArray(productForm.variations) ? productForm.variations : []),
        createEmptyVariation(),
    ];
};

const removeVariationRow = (index) => {
    const rows = Array.isArray(productForm.variations) ? [...productForm.variations] : [];
    productForm.variations = rows.filter((_, rowIndex) => rowIndex !== index);
};

const parseVariationAttributes = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return {};

    return raw
        .split(',')
        .map((chunk) => chunk.trim())
        .filter(Boolean)
        .reduce((acc, chunk) => {
            const [keyRaw, ...rest] = chunk.split(':');
            const key = String(keyRaw ?? '').trim();
            const parsedValue = String(rest.join(':') ?? '').trim();
            if (!key || !parsedValue) return acc;

            return {
                ...acc,
                [key]: parsedValue,
            };
        }, {});
};

const openGalleryPicker = () => {
    if (isGalleryPickerDisabled.value) return;
    if (galleryInputRef.value) {
        galleryInputRef.value.click();
    }
};

const onGalleryFilesChange = (event) => {
    const files = Array.from(event?.target?.files ?? []);
    if (!files.length) return;

    const acceptedFiles = files.slice(0, galleryAvailableSlots.value);

    productForm.gallery_files = [
        ...(Array.isArray(productForm.gallery_files) ? productForm.gallery_files : []),
        ...acceptedFiles,
    ];

    productForm.gallery_images = [
        ...(Array.isArray(productForm.gallery_images) ? productForm.gallery_images : []),
        ...acceptedFiles.map((file) => ({
            id: null,
            image_url: URL.createObjectURL(file),
            file,
            from_server: false,
        })),
    ];

    if (galleryInputRef.value) {
        galleryInputRef.value.value = '';
    }
};

const removeGalleryImage = (image, index) => {
    if (!image) return;

    if (image.from_server && Number(image.id) > 0) {
        productForm.remove_gallery_ids = Array.from(new Set([
            ...(Array.isArray(productForm.remove_gallery_ids) ? productForm.remove_gallery_ids : []),
            Number(image.id),
        ]));
    }

    if (!image.from_server && image.file) {
        productForm.gallery_files = (Array.isArray(productForm.gallery_files) ? productForm.gallery_files : [])
            .filter((file) => file !== image.file);
    }

    if (!image.from_server && image.image_url && String(image.image_url).startsWith('blob:')) {
        URL.revokeObjectURL(image.image_url);
    }

    productForm.gallery_images = (Array.isArray(productForm.gallery_images) ? productForm.gallery_images : [])
        .filter((_, rowIndex) => rowIndex !== index);
};

const normalizeVariationPayload = (rows) => (Array.isArray(rows) ? rows : [])
    .map((variation, index) => ({
        id: variation.id || null,
        name: String(variation.name ?? '').trim(),
        sku: String(variation.sku ?? '').trim(),
        sale_price: Number(variation.sale_price ?? 0),
        cost_price: variation.cost_price === '' || variation.cost_price === null || variation.cost_price === undefined
            ? null
            : Number(variation.cost_price),
        stock_quantity: Math.max(0, Number.parseInt(variation.stock_quantity ?? 0, 10) || 0),
        is_active: Boolean(variation.is_active ?? true),
        sort_order: index,
        attributes: parseVariationAttributes(variation.attributes_text),
    }))
    .filter((variation) => variation.name !== '');

const submitProduct = () => {
    if (isEditing.value) {
        productForm.transform((data) => ({
            ...data,
            _method: 'put',
            sale_price: resolveProductSalePriceForPayload(),
            stock_quantity: Math.max(0, Number.parseInt(String(data.stock_quantity ?? 0), 10) || 0),
            remove_gallery_ids: JSON.stringify(Array.isArray(data.remove_gallery_ids) ? data.remove_gallery_ids : []),
            variations: JSON.stringify(normalizeVariationPayload(data.variations)),
        })).post(route('admin.products.update', editingProduct.value.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeModal,
            onFinish: () => {
                productForm.transform((data) => data);
            },
        });

        return;
    }

    productForm.transform((data) => ({
        ...data,
        sale_price: resolveProductSalePriceForPayload(),
        stock_quantity: Math.max(0, Number.parseInt(String(data.stock_quantity ?? 0), 10) || 0),
        remove_gallery_ids: JSON.stringify(Array.isArray(data.remove_gallery_ids) ? data.remove_gallery_ids : []),
        variations: JSON.stringify(normalizeVariationPayload(data.variations)),
    })).post(route('admin.products.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeModal,
        onFinish: () => {
            productForm.transform((data) => data);
        },
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

const isFirstProductStep = computed(() => productWizardStep.value <= 1);
const isLastProductStep = computed(() => productWizardStep.value >= productWizardSteps.length);

const parseNumericInput = (value) => {
    if (value === null || value === undefined || value === '') return null;
    const normalized = String(value).replace(/\s/g, '').replace(',', '.');
    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : null;
};

const parseIntegerInput = (value) => {
    if (value === null || value === undefined || value === '') return null;
    const parsed = Number.parseInt(String(value), 10);
    return Number.isFinite(parsed) ? parsed : null;
};

const activeVariationsInForm = computed(() => (Array.isArray(productForm.variations) ? productForm.variations : [])
    .filter((variation) => {
        if (!(variation?.is_active ?? true)) return false;
        if (String(variation?.name ?? '').trim() === '') return false;

        const salePrice = parseNumericInput(variation?.sale_price);
        return salePrice !== null && salePrice >= 0;
    }));

const hasActiveVariationsInForm = computed(() => activeVariationsInForm.value.length > 0);

const derivedSalePriceFromVariations = computed(() => {
    if (!hasActiveVariationsInForm.value) return null;

    const minPrice = activeVariationsInForm.value.reduce((lowest, variation) => {
        const salePrice = parseNumericInput(variation?.sale_price);
        if (salePrice === null || salePrice < 0) return lowest;
        if (lowest === null) return salePrice;
        return Math.min(lowest, salePrice);
    }, null);

    if (minPrice === null) return null;

    return Number(minPrice.toFixed(2));
});

watch([hasActiveVariationsInForm, derivedSalePriceFromVariations], ([hasActiveVariations, derivedSalePrice]) => {
    if (!hasActiveVariations || derivedSalePrice === null) return;
    productForm.sale_price = derivedSalePrice.toFixed(2);
});

const resolveProductSalePriceForPayload = () => {
    if (hasActiveVariationsInForm.value && derivedSalePriceFromVariations.value !== null) {
        return Number(derivedSalePriceFromVariations.value.toFixed(2));
    }

    const parsed = parseNumericInput(productForm.sale_price);
    return parsed !== null && parsed >= 0 ? Number(parsed.toFixed(2)) : 0;
};

const isProductStepValid = (stepNumber) => {
    if (stepNumber === 1) {
        return String(productForm.name ?? '').trim() !== '';
    }

    if (stepNumber === 2) {
        const salePrice = parseNumericInput(productForm.sale_price);
        const stockQuantity = parseIntegerInput(productForm.stock_quantity);
        const unit = String(productForm.unit ?? '').trim();

        return salePrice !== null && salePrice >= 0
            && stockQuantity !== null && stockQuantity >= 0
            && unit !== '';
    }

    return true;
};

const clearProductStepLocalErrors = (stepNumber) => {
    if (stepNumber === 1) {
        productForm.clearErrors('name');
        return;
    }

    if (stepNumber === 2) {
        productForm.clearErrors('sale_price', 'stock_quantity', 'unit');
    }
};

const applyProductStepLocalErrors = (stepNumber) => {
    if (stepNumber === 1) {
        if (String(productForm.name ?? '').trim() === '') {
            productForm.setError('name', 'Informe o nome do produto.');
        }
        return;
    }

    if (stepNumber === 2) {
        const salePrice = parseNumericInput(productForm.sale_price);
        const stockQuantity = parseIntegerInput(productForm.stock_quantity);
        const unit = String(productForm.unit ?? '').trim();

        if (salePrice === null || salePrice < 0) {
            productForm.setError('sale_price', 'Informe o preço de venda.');
        }
        if (stockQuantity === null || stockQuantity < 0) {
            productForm.setError('stock_quantity', 'Informe a quantidade em estoque.');
        }
        if (unit === '') {
            productForm.setError('unit', 'Selecione a unidade.');
        }
    }
};

const validateCurrentProductStepForCreate = () => {
    if (isEditing.value) return true;

    const currentStep = productWizardStep.value;
    productWizardValidationRequested.value = true;
    clearProductStepLocalErrors(currentStep);

    if (isProductStepValid(currentStep)) return true;

    applyProductStepLocalErrors(currentStep);
    return false;
};

const productStepErrorKeyMap = {
    1: ['name', 'sku', 'category_id', 'description', 'is_active'],
    2: ['cost_price', 'sale_price', 'stock_quantity', 'unit'],
    3: ['gallery_files', 'gallery_images', 'remove_gallery_ids', 'storage_quota'],
    4: ['variations'],
};

const hasProductFormErrorForStep = (stepNumber) => {
    const keys = Object.keys(productForm.errors ?? {});
    const prefixes = productStepErrorKeyMap[stepNumber] ?? [];

    return keys.some((key) => prefixes.some((prefix) => key === prefix || key.startsWith(`${prefix}.`)));
};

const shouldShowProductStepErrors = computed(() =>
    isEditing.value
    || productWizardValidationRequested.value
    || Object.keys(productForm.errors ?? {}).length > 0,
);

const productStepErrors = computed(() =>
    productWizardSteps.map((_, index) => {
        const stepNumber = index + 1;
        if (!shouldShowProductStepErrors.value) return false;

        const checkLocalValidation = isEditing.value || stepNumber <= productWizardStep.value;
        const hasLocalError = checkLocalValidation ? !isProductStepValid(stepNumber) : false;

        return hasLocalError || hasProductFormErrorForStep(stepNumber);
    }),
);

const goToPreviousProductStep = () => {
    if (isFirstProductStep.value) return;
    productWizardStep.value -= 1;
};

const goToNextProductStep = () => {
    if (isLastProductStep.value) return;
    if (!validateCurrentProductStepForCreate()) return;
    productWizardStep.value += 1;
};

const setProductWizardStep = (step) => {
    if (!isEditing.value) return;

    const parsedStep = Number(step);
    if (!Number.isFinite(parsedStep)) return;

    const safeStep = Math.min(productWizardSteps.length, Math.max(1, Math.floor(parsedStep)));
    productWizardStep.value = safeStep;
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

const formatStorage = (bytes) => {
    const safeBytes = Number(bytes ?? 0);
    if (!Number.isFinite(safeBytes) || safeBytes <= 0) return '0 MB';

    const gb = safeBytes / (1024 * 1024 * 1024);
    if (gb >= 1) return `${gb.toFixed(2)} GB`;

    const mb = safeBytes / (1024 * 1024);
    return `${mb.toFixed(1)} MB`;
};

const fallbackImage = (name) => `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'Produto')}&background=e2e8f0&color=334155&size=64`;
</script>

<template>
    <Head title="Produtos" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Produtos" :show-table-view-toggle="false">
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
                        <div class="flex flex-col gap-2 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                            <p>
                                Storage usado: <span class="font-semibold text-slate-800">{{ formatStorage(props.storage?.usage_bytes) }}</span>
                                <span v-if="props.storage?.limit_bytes !== null">
                                    de <span class="font-semibold text-slate-800">{{ formatStorage(props.storage?.limit_bytes) }}</span>
                                </span>
                            </p>
                            <p>
                                Limite de fotos por produto: <span class="font-semibold text-slate-800">{{ props.storage?.gallery_limit_per_product || 5 }}</span>
                            </p>
                        </div>
                    </section>

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

                                    <div class="mt-3 flex justify-end">
                    <TableViewToggle />
                </div>

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
                                                :src="(product.images?.[0]?.image_url || product.image_url) || fallbackImage(product.name)"
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
                description="Preencha os dados por etapas para facilitar o cadastro."
                :steps="productWizardSteps"
                :current-step="productWizardStep"
                :steps-clickable="isEditing"
                :step-errors="productStepErrors"
                @step-change="setProductWizardStep"
                @close="closeModal"
            >
                <div v-if="productWizardStep <= 3" class="grid gap-3 md:grid-cols-2">
                    <div v-if="productWizardStep === 1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input
                            v-model="productForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Bolo Piscina Chocolate"
                        >
                        <p v-if="productForm.errors.name" class="mt-1 text-xs text-rose-600">{{ productForm.errors.name }}</p>
                    </div>

                    <div v-if="productWizardStep === 1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">SKU</label>
                        <input
                            v-model="productForm.sku"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: BOL-001"
                        >
                        <p v-if="productForm.errors.sku" class="mt-1 text-xs text-rose-600">{{ productForm.errors.sku }}</p>
                    </div>

                    <div v-if="productWizardStep === 3" class="md:col-span-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Galeria de fotos</p>
                                <span class="text-[11px] font-semibold text-slate-500">
                                    {{ currentGalleryImageCount }}/{{ galleryLimitPerProduct }}
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="isGalleryPickerDisabled"
                                    @click="openGalleryPicker"
                                >
                                    Escolher arquivos
                                </button>
                                <span class="text-[11px] text-slate-500">Formatos: JPG, PNG e WEBP.</span>
                            </div>
                            <input
                                ref="galleryInputRef"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                multiple
                                class="hidden"
                                @change="onGalleryFilesChange"
                            >
                            <p class="mt-1 text-[11px] text-slate-500">
                                Envie até {{ galleryLimitPerProduct }} imagens por produto.
                                <span v-if="isGalleryPickerDisabled" class="font-semibold text-amber-700">
                                    Limite atingido.
                                </span>
                            </p>

                            <div
                                v-if="Array.isArray(productForm.gallery_images) && productForm.gallery_images.length"
                                class="mt-3 grid grid-cols-3 gap-2"
                            >
                                <article
                                    v-for="(image, imageIndex) in productForm.gallery_images"
                                    :key="`gallery-image-${image.id || imageIndex}`"
                                    class="relative overflow-hidden rounded-lg border border-slate-200 bg-white"
                                >
                                    <img :src="image.image_url" alt="Imagem da galeria" class="h-20 w-full object-cover">
                                    <button
                                        type="button"
                                        class="absolute right-1 top-1 rounded bg-white/95 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 shadow"
                                        @click="removeGalleryImage(image, imageIndex)"
                                    >
                                        Remover
                                    </button>
                                </article>
                            </div>

                            <p v-if="productForm.errors.gallery_files" class="mt-1 text-xs text-rose-600">{{ productForm.errors.gallery_files }}</p>
                            <p v-if="productForm.errors.storage_quota" class="mt-1 text-xs text-rose-600">{{ productForm.errors.storage_quota }}</p>
                        </div>
                    </div>

                    <div v-if="productWizardStep === 1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Categoria</label>
                        <UiSelect
                            v-model="productForm.category_id"
                            :options="productCategoryOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="productForm.errors.category_id" class="mt-1 text-xs text-rose-600">{{ productForm.errors.category_id }}</p>
                    </div>

                    <div v-if="productWizardStep === 2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Custo</label>
                        <BrlMoneyInput
                            v-model="productForm.cost_price"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="R$ 0,00"
                        />
                        <p v-if="productForm.errors.cost_price" class="mt-1 text-xs text-rose-600">{{ productForm.errors.cost_price }}</p>
                    </div>

                    <div v-if="productWizardStep === 2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Preço de venda</label>
                        <BrlMoneyInput
                            v-model="productForm.sale_price"
                            :allow-empty="false"
                            :disabled="hasActiveVariationsInForm"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="R$ 0,00"
                        />
                        <p v-if="hasActiveVariationsInForm" class="mt-1 text-xs text-slate-500">
                            Com variações ativas, o preço base é calculado automaticamente pelo menor valor da variação.
                        </p>
                        <p v-if="productForm.errors.sale_price" class="mt-1 text-xs text-rose-600">{{ productForm.errors.sale_price }}</p>
                    </div>

                    <div v-if="productWizardStep === 2">
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

                    <div v-if="productWizardStep === 2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unidade</label>
                        <UiSelect
                            v-model="productForm.unit"
                            :options="unitOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="productForm.errors.unit" class="mt-1 text-xs text-rose-600">{{ productForm.errors.unit }}</p>
                    </div>
                </div>

                <div v-if="productWizardStep === 4" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Variações do produto</p>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                            @click="addVariationRow"
                        >
                            Adicionar variação
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-500">
                        Cadastre opções como cor, tamanho e modelo. O estoque e preço serão controlados por variação.
                    </p>

                    <div
                        v-for="(variation, variationIndex) in productForm.variations"
                        :key="`variation-row-${variation.id || variationIndex}`"
                        class="grid gap-2 rounded-lg border border-slate-200 bg-white p-2 md:grid-cols-12"
                    >
                        <input
                            v-model="variation.name"
                            type="text"
                            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700 md:col-span-3"
                            placeholder="Nome (ex.: Azul / M)"
                        >
                        <input
                            v-model="variation.sku"
                            type="text"
                            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700 md:col-span-2"
                            placeholder="SKU variação"
                        >
                        <BrlMoneyInput
                            v-model="variation.sale_price"
                            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700 md:col-span-2"
                            placeholder="R$ 0,00"
                        />
                        <input
                            v-model="variation.stock_quantity"
                            type="number"
                            min="0"
                            step="1"
                            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700 md:col-span-2"
                            placeholder="Estoque"
                        >
                        <input
                            v-model="variation.attributes_text"
                            type="text"
                            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700 md:col-span-2"
                            placeholder="Atributos (Cor:Azul, Tam:M)"
                        >
                        <button
                            type="button"
                            class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 md:col-span-1"
                            @click="removeVariationRow(variationIndex)"
                        >
                            X
                        </button>
                    </div>
                    <p v-if="productForm.errors.variations" class="text-xs text-rose-600">{{ productForm.errors.variations }}</p>
                </div>

                <div v-if="productWizardStep === 1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</label>
                    <textarea
                        v-model="productForm.description"
                        rows="3"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        placeholder="Descrição do produto"
                    />
                    <p v-if="productForm.errors.description" class="mt-1 text-xs text-rose-600">{{ productForm.errors.description }}</p>
                </div>

                <label v-if="productWizardStep === 1" class="flex items-center gap-2 text-sm font-medium text-slate-700">
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
                            v-if="!isFirstProductStep"
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="goToPreviousProductStep"
                        >
                            Voltar
                        </button>
                        <button
                            v-if="!isLastProductStep"
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            @click="goToNextProductStep"
                        >
                            Avançar
                        </button>
                        <button
                            v-if="isLastProductStep"
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
