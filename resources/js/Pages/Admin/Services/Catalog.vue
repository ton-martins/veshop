<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import Modal from '@/Components/Modal.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Briefcase, Search, Filter, Clock3, CircleDollarSign, Plus } from 'lucide-vue-next';

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
const formatDuration = (minutes) => `${Number(minutes ?? 0)} min`;

const categoryOptions = computed(() => [
    { value: '', label: 'Todas categorias' },
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
});

const form = useForm(formDefaults());
const showModal = ref(false);

const openCreate = () => {
    form.defaults(formDefaults());
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.clearErrors();
    form.defaults(formDefaults());
    form.reset();
};

const submitService = () => {
    form.post(route('admin.services.catalog.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const statsCards = computed(() => [
    { key: 'total', label: 'Serviços cadastrados', value: String(props.stats?.total ?? 0), icon: Briefcase, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Serviços ativos', value: String(props.stats?.active ?? 0), icon: Clock3, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'avg_price', label: 'Preço médio', value: asCurrency(props.stats?.avg_price ?? 0), icon: CircleDollarSign, tone: 'bg-blue-100 text-blue-700' },
]);
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
                        />
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
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="applyFilters">
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
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="clearFilters">
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800" @click="openCreate">
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
                            </tr>
                        </thead>
                        <tbody v-if="rows.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="service in rows" :key="service.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ service.name }}</p>
                                    <p class="text-xs text-slate-500">{{ service.code || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ service.category_name || '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ formatDuration(service.duration_minutes) }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ asCurrency(service.base_price) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="service.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ service.status_label }}
                                    </span>
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

        <Modal :show="showModal" max-width="4xl" @close="closeModal">
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Novo serviço</h3>
                        <p class="text-sm text-slate-500">Cadastre um novo item no catálogo de serviços.</p>
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
                            placeholder="Ex.: Fechamento contábil mensal"
                        >
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Código</label>
                        <input
                            v-model="form.code"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: CONT-MENSAL"
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
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Duração padrão (min) *</label>
                        <input
                            v-model.number="form.duration_minutes"
                            type="number"
                            min="5"
                            max="1440"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
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
                        <input
                            v-model="form.base_price"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
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
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                        Cancelar
                    </button>
                    <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60" :disabled="form.processing" @click="submitService">
                        {{ form.processing ? 'Salvando...' : 'Salvar serviço' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
