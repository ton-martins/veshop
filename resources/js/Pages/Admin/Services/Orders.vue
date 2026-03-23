<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ClipboardList, Clock3, CircleCheckBig, Search, Filter, Plus, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    orders: {
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
    clients: {
        type: Array,
        default: () => [],
    },
    services: {
        type: Array,
        default: () => [],
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    priorityOptions: {
        type: Array,
        default: () => [],
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
        route('admin.services.orders'),
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

const rows = computed(() => props.orders?.data ?? []);
const paginationLinks = computed(() => props.orders?.links ?? []);

const statsCards = computed(() => [
    { key: 'open', label: 'OS em aberto', value: String(props.stats?.open ?? 0), icon: ClipboardList, tone: 'text-slate-700' },
    { key: 'running', label: 'Em execução', value: String(props.stats?.in_progress ?? 0), icon: Clock3, tone: 'text-slate-700' },
    { key: 'done', label: 'Concluídas no mês', value: String(props.stats?.done_month ?? 0), icon: CircleCheckBig, tone: 'text-slate-700' },
]);

const clientOptions = computed(() => ([
    { value: '', label: 'Sem cliente' },
    ...(props.clients ?? []).map((client) => ({ value: client.id, label: client.name })),
]));

const serviceOptions = computed(() => ([
    { value: '', label: 'Sem serviço' },
    ...(props.services ?? []).map((service) => ({ value: service.id, label: service.name })),
]));

const formDefaults = () => ({
    code: '',
    title: '',
    description: '',
    client_id: '',
    service_catalog_id: '',
    scheduled_for: '',
    due_at: '',
    status: props.statusOptions?.[0]?.value ?? 'open',
    priority: props.priorityOptions?.[1]?.value ?? 'normal',
    assigned_to_name: '',
    estimated_amount: '0.00',
    final_amount: '0.00',
});

const form = useForm(formDefaults());
const showModal = ref(false);
const editingOrder = ref(null);
const showDeleteModal = ref(false);
const orderToDelete = ref(null);
const deleteForm = useForm({});

const isEditing = computed(() => Boolean(editingOrder.value?.id));

const openCreate = () => {
    editingOrder.value = null;
    form.defaults(formDefaults());
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (order) => {
    editingOrder.value = order;
    form.code = order.code ?? '';
    form.title = order.title ?? '';
    form.description = order.description ?? '';
    form.client_id = order.client_id ?? '';
    form.service_catalog_id = order.service_catalog_id ?? '';
    form.scheduled_for = order.scheduled_for ?? '';
    form.due_at = order.due_at ?? '';
    form.status = order.status ?? (props.statusOptions?.[0]?.value ?? 'open');
    form.priority = order.priority ?? (props.priorityOptions?.[1]?.value ?? 'normal');
    form.assigned_to_name = order.assigned_to_name ?? '';
    form.estimated_amount = Number(order.estimated_amount ?? 0).toFixed(2);
    form.final_amount = Number(order.final_amount ?? 0).toFixed(2);
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingOrder.value = null;
    form.clearErrors();
    form.defaults(formDefaults());
    form.reset();
};

const submitOrder = () => {
    if (isEditing.value) {
        form.put(route('admin.services.orders.update', editingOrder.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    form.post(route('admin.services.orders.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const openDeleteModal = (order) => {
    orderToDelete.value = order;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    orderToDelete.value = null;
};

const destroyOrder = () => {
    if (!orderToDelete.value?.id) return;

    deleteForm.delete(route('admin.services.orders.destroy', orderToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};

const asCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const statusFilterOptions = computed(() => [
    { value: '', label: 'Todos os status' },
    ...(props.statusOptions ?? []),
]);

const statusLabel = (value) => {
    const option = (props.statusOptions ?? []).find((item) => item.value === value);
    return option?.label ?? value;
};
</script>

<template>
    <Head title="Ordens de Serviço" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Ordens de Serviço" :show-table-view-toggle="false">
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
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Buscar por código, título ou cliente"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keydown.enter.prevent="applyFilters"
                        />
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
                            :options="statusFilterOptions"
                            button-class="w-full sm:w-auto"
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
                            Nova OS
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
                                <th class="px-4 py-3">OS</th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Serviço</th>
                                <th class="px-4 py-3">Agenda</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Valores</th>
                                <th class="px-4 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhuma ordem de serviço cadastrada.
                                </td>
                            </tr>
                            <tr v-for="order in rows" :key="order.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ order.code }}</p>
                                    <p class="text-xs text-slate-500">{{ order.title }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ order.client_name }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ order.service_name }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    <p>{{ order.scheduled_for ? order.scheduled_for.replace('T', ' ') : '-' }}</p>
                                    <p class="text-xs text-slate-500">Prazo: {{ order.due_at ? order.due_at.replace('T', ' ') : '-' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                        {{ statusLabel(order.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    <p>Previsto: {{ asCurrency(order.estimated_amount) }}</p>
                                    <p class="text-xs text-slate-500">Final: {{ asCurrency(order.final_amount) }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="openEdit(order)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="openDeleteModal(order)"
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
                        <h3 class="text-lg font-semibold text-slate-900">{{ isEditing ? 'Editar ordem de serviço' : 'Nova ordem de serviço' }}</h3>
                        <p class="text-sm text-slate-500">Preencha os dados operacionais da OS.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                        Fechar
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Código</label>
                        <input v-model="form.code" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="OS-20260322-0001">
                        <p v-if="form.errors.code" class="mt-1 text-xs text-rose-600">{{ form.errors.code }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Título</label>
                        <input v-model="form.title" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Ex.: Fechamento mensal">
                        <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</label>
                        <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Detalhes da execução" />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</label>
                        <UiSelect v-model="form.client_id" :options="clientOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.client_id" class="mt-1 text-xs text-rose-600">{{ form.errors.client_id }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Serviço</label>
                        <UiSelect v-model="form.service_catalog_id" :options="serviceOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.service_catalog_id" class="mt-1 text-xs text-rose-600">{{ form.errors.service_catalog_id }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Agendado para</label>
                        <input v-model="form.scheduled_for" type="datetime-local" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        <p v-if="form.errors.scheduled_for" class="mt-1 text-xs text-rose-600">{{ form.errors.scheduled_for }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prazo</label>
                        <input v-model="form.due_at" type="datetime-local" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        <p v-if="form.errors.due_at" class="mt-1 text-xs text-rose-600">{{ form.errors.due_at }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                        <UiSelect v-model="form.status" :options="props.statusOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.status" class="mt-1 text-xs text-rose-600">{{ form.errors.status }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prioridade</label>
                        <UiSelect v-model="form.priority" :options="props.priorityOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.priority" class="mt-1 text-xs text-rose-600">{{ form.errors.priority }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responsável</label>
                        <input v-model="form.assigned_to_name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Nome do responsável">
                        <p v-if="form.errors.assigned_to_name" class="mt-1 text-xs text-rose-600">{{ form.errors.assigned_to_name }}</p>
                    </div>
                    <div class="grid gap-3 grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valor previsto</label>
                            <BrlMoneyInput v-model="form.estimated_amount" :allow-empty="false" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="R$ 0,00" />
                            <p v-if="form.errors.estimated_amount" class="mt-1 text-xs text-rose-600">{{ form.errors.estimated_amount }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valor final</label>
                            <BrlMoneyInput v-model="form.final_amount" :allow-empty="false" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="R$ 0,00" />
                            <p v-if="form.errors.final_amount" class="mt-1 text-xs text-rose-600">{{ form.errors.final_amount }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                        Cancelar
                    </button>
                    <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60" :disabled="form.processing" @click="submitOrder">
                        {{ form.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir ordem de serviço"
            message="Tem certeza que deseja excluir esta ordem de serviço?"
            :item-label="orderToDelete?.code ? `OS: ${orderToDelete.code}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="destroyOrder"
        />
    </AuthenticatedLayout>
</template>
