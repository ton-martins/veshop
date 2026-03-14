<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Truck, PackageCheck, Timer, AlertTriangle, Search, Filter, Plus, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    suppliers: {
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
        route('admin.suppliers.index'),
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

const clearFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFilters();
};

const rows = computed(() => props.suppliers?.data ?? []);
const paginationLinks = computed(() => props.suppliers?.links ?? []);

const statsCards = computed(() => [
    { key: 'total', label: 'Fornecedores cadastrados', value: String(props.stats?.total ?? 0), icon: Truck, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Fornecedores ativos', value: String(props.stats?.active ?? 0), icon: PackageCheck, tone: 'bg-blue-100 text-blue-700' },
    { key: 'lead', label: 'Lead time médio', value: `${Number(props.stats?.lead_time ?? 0).toLocaleString('pt-BR')} dia(s)`, icon: Timer, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'categories', label: 'Segmentos', value: String(props.stats?.categories ?? 0), icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
]);

const showModal = ref(false);
const editingSupplier = ref(null);

const supplierForm = useForm({
    name: '',
    email: '',
    phone: '',
    document: '',
    category: '',
    lead_time_days: 0,
    is_active: true,
});

const isEditing = computed(() => Boolean(editingSupplier.value?.id));

const openCreate = () => {
    editingSupplier.value = null;
    supplierForm.reset();
    supplierForm.clearErrors();
    supplierForm.lead_time_days = 0;
    supplierForm.is_active = true;
    showModal.value = true;
};

const openEdit = (supplier) => {
    editingSupplier.value = supplier;
    supplierForm.name = supplier.name ?? '';
    supplierForm.email = supplier.email ?? '';
    supplierForm.phone = supplier.phone ?? '';
    supplierForm.document = supplier.document ?? '';
    supplierForm.category = supplier.category ?? '';
    supplierForm.lead_time_days = Number.parseInt(String(supplier.lead_time_days ?? 0), 10) || 0;
    supplierForm.is_active = Boolean(supplier.is_active);
    supplierForm.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingSupplier.value = null;
};

const submitSupplier = () => {
    if (isEditing.value) {
        supplierForm.put(route('admin.suppliers.update', editingSupplier.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    supplierForm.post(route('admin.suppliers.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const removeSupplier = (supplier) => {
    const confirmed = window.confirm(`Excluir o fornecedor "${supplier.name}"?`);
    if (!confirmed) return;

    router.delete(route('admin.suppliers.destroy', supplier.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Fornecedores" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Fornecedores">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="stat in statsCards" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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
                            placeholder="Buscar fornecedor por nome, email ou segmento"
                            class="w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div class="veshop-toolbar-actions lg:justify-end">
                        <select
                            v-model="filterForm.status"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 sm:w-auto"
                            @change="applyFilters"
                        >
                            <option value="">Todos</option>
                            <option value="active">Ativos</option>
                            <option value="inactive">Inativos</option>
                        </select>
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto" @click="clearFilters">
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 sm:w-auto" @click="openCreate">
                            <Plus class="h-3.5 w-3.5" />
                            Novo fornecedor
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Fornecedor</th>
                                <th class="px-4 py-3">Contato</th>
                                <th class="px-4 py-3">Documento</th>
                                <th class="px-4 py-3">Segmento</th>
                                <th class="px-4 py-3">Lead time</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhum fornecedor cadastrado para este contratante.
                                </td>
                            </tr>
                            <tr v-for="supplier in rows" :key="supplier.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ supplier.name }}</p>
                                    <p class="text-xs text-slate-500">Criado em {{ supplier.created_at }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ supplier.email || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ supplier.phone || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ supplier.document || '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ supplier.category || '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ supplier.lead_time_days }} dia(s)</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="supplier.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ supplier.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="openEdit(supplier)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="removeSupplier(supplier)"
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

        <Modal :show="showModal" max-width="lg" @close="closeModal">
            <div class="space-y-4 bg-white p-6">
                <h3 class="text-base font-semibold text-slate-900">
                    {{ isEditing ? 'Editar fornecedor' : 'Novo fornecedor' }}
                </h3>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input
                            v-model="supplierForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Distribuidora Central"
                        >
                        <p v-if="supplierForm.errors.name" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                        <input
                            v-model="supplierForm.email"
                            type="email"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="fornecedor@email.com"
                        >
                        <p v-if="supplierForm.errors.email" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                        <input
                            v-model="supplierForm.phone"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="(00) 00000-0000"
                        >
                        <p v-if="supplierForm.errors.phone" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.phone }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documento</label>
                        <input
                            v-model="supplierForm.document"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="CPF/CNPJ"
                        >
                        <p v-if="supplierForm.errors.document" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.document }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Segmento</label>
                        <input
                            v-model="supplierForm.category"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Matéria-prima"
                        >
                        <p v-if="supplierForm.errors.category" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.category }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lead time (dias)</label>
                        <input
                            v-model="supplierForm.lead_time_days"
                            type="number"
                            min="0"
                            step="1"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                        <p v-if="supplierForm.errors.lead_time_days" class="mt-1 text-xs text-rose-600">{{ supplierForm.errors.lead_time_days }}</p>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input v-model="supplierForm.is_active" type="checkbox" class="rounded border-slate-300">
                    Fornecedor ativo
                </label>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
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
                        :disabled="supplierForm.processing"
                        @click="submitSupplier"
                    >
                        {{ supplierForm.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
