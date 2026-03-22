<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { CalendarClock, Clock3, UserRound, Filter, Search, Plus, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    appointments: {
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
    orders: {
        type: Array,
        default: () => [],
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
});

const filterForm = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
    date: props.filters?.date ?? '',
});

watch(
    () => props.filters,
    (next) => {
        filterForm.search = next?.search ?? '';
        filterForm.status = next?.status ?? '';
        filterForm.date = next?.date ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('admin.services.schedule'),
        {
            search: filterForm.search || undefined,
            status: filterForm.status || undefined,
            date: filterForm.date || undefined,
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
    filterForm.date = '';
    applyFilters();
};

const rows = computed(() => props.appointments?.data ?? []);
const paginationLinks = computed(() => props.appointments?.links ?? []);
const hasServices = computed(() => Array.isArray(props.services) && props.services.length > 0);

const statsCards = computed(() => [
    { key: 'today', label: 'Visitas hoje', value: String(props.stats?.today ?? 0), icon: CalendarClock, tone: 'text-slate-700' },
    { key: 'next', label: 'Próximas 24h', value: String(props.stats?.next_24h ?? 0), icon: Clock3, tone: 'text-slate-700' },
    { key: 'teams', label: 'Responsáveis ativos', value: String(props.stats?.teams ?? 0), icon: UserRound, tone: 'text-slate-700' },
]);

const clientOptions = computed(() => ([
    { value: '', label: 'Sem cliente' },
    ...(props.clients ?? []).map((client) => ({ value: client.id, label: client.name })),
]));

const serviceOptions = computed(() => ([
    ...(props.services ?? []).map((service) => ({ value: service.id, label: service.name })),
]));

const orderOptions = computed(() => ([
    { value: '', label: 'Sem OS vinculada' },
    ...(props.orders ?? []).map((order) => ({ value: order.id, label: order.label })),
]));

const statusFilterOptions = computed(() => ([
    { value: '', label: 'Todos os status' },
    ...(props.statusOptions ?? []),
]));

const formDefaults = () => ({
    title: '',
    service_order_id: '',
    client_id: '',
    service_catalog_id: props.services?.[0]?.id ?? '',
    starts_at: '',
    ends_at: '',
    status: props.statusOptions?.[0]?.value ?? 'scheduled',
    location: '',
    notes: '',
});

const form = useForm(formDefaults());
const showModal = ref(false);
const editingAppointment = ref(null);
const showDeleteModal = ref(false);
const appointmentToDelete = ref(null);
const deleteForm = useForm({});

const isEditing = computed(() => Boolean(editingAppointment.value?.id));

const openCreate = () => {
    editingAppointment.value = null;
    form.defaults(formDefaults());
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (appointment) => {
    editingAppointment.value = appointment;
    form.title = appointment.title ?? '';
    form.service_order_id = appointment.service_order_id ?? '';
    form.client_id = appointment.client_id ?? '';
    form.service_catalog_id = appointment.service_catalog_id ?? '';
    form.starts_at = appointment.starts_at ?? '';
    form.ends_at = appointment.ends_at ?? '';
    form.status = appointment.status ?? (props.statusOptions?.[0]?.value ?? 'scheduled');
    form.location = appointment.location ?? '';
    form.notes = appointment.notes ?? '';
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingAppointment.value = null;
    form.clearErrors();
    form.defaults(formDefaults());
    form.reset();
};

const submitAppointment = () => {
    if (isEditing.value) {
        form.put(route('admin.services.schedule.update', editingAppointment.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    form.post(route('admin.services.schedule.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const openDeleteModal = (appointment) => {
    appointmentToDelete.value = appointment;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    appointmentToDelete.value = null;
};

const destroyAppointment = () => {
    if (!appointmentToDelete.value?.id) return;

    deleteForm.delete(route('admin.services.schedule.destroy', appointmentToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};

const statusLabel = (value) => {
    const option = (props.statusOptions ?? []).find((item) => item.value === value);
    return option?.label ?? value;
};
</script>

<template>
    <Head title="Agenda de Serviços" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Agenda de Serviços" :show-table-view-toggle="false">
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
                            placeholder="Buscar por título, cliente, OS ou responsável"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div class="veshop-toolbar-actions lg:justify-end">
                        <input v-model="filterForm.date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700 sm:w-auto" @change="applyFilters">
                        <UiSelect v-model="filterForm.status" :options="statusFilterOptions" button-class="w-full sm:w-auto" @change="applyFilters" />
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto" @click="clearFilters">
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button
                            type="button"
                            class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                            :disabled="!hasServices"
                            @click="openCreate"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Novo compromisso
                        </button>
                    </div>
                </div>

                <div v-if="!hasServices" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-700">
                    Cadastre pelo menos um serviço no catálogo para criar novos compromissos.
                </div>

                <div class="mt-3 flex justify-end">
                    <TableViewToggle />
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Compromisso</th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Serviço</th>
                                <th class="px-4 py-3">Horário</th>
                                <th class="px-4 py-3">Responsável</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhum compromisso agendado para o filtro atual.
                                </td>
                            </tr>
                            <tr v-for="appointment in rows" :key="appointment.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ appointment.title }}</p>
                                    <p class="text-xs text-slate-500">{{ appointment.service_order_code || 'Sem OS' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ appointment.client_name }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ appointment.service_name || '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ appointment.time_label }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ appointment.technician }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                        {{ statusLabel(appointment.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="openEdit(appointment)">
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="openDeleteModal(appointment)">
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
                        <h3 class="text-lg font-semibold text-slate-900">{{ isEditing ? 'Editar compromisso' : 'Novo compromisso' }}</h3>
                        <p class="text-sm text-slate-500">Planejamento operacional da agenda de serviços.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                        Fechar
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Título</label>
                        <input v-model="form.title" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Ex.: Reunião de fechamento">
                        <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">OS vinculada</label>
                        <UiSelect v-model="form.service_order_id" :options="orderOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.service_order_id" class="mt-1 text-xs text-rose-600">{{ form.errors.service_order_id }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</label>
                        <UiSelect v-model="form.client_id" :options="clientOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.client_id" class="mt-1 text-xs text-rose-600">{{ form.errors.client_id }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Serviço *</label>
                        <UiSelect v-model="form.service_catalog_id" :options="serviceOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.service_catalog_id" class="mt-1 text-xs text-rose-600">{{ form.errors.service_catalog_id }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                        <UiSelect v-model="form.status" :options="props.statusOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.status" class="mt-1 text-xs text-rose-600">{{ form.errors.status }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Início</label>
                        <input v-model="form.starts_at" type="datetime-local" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        <p v-if="form.errors.starts_at" class="mt-1 text-xs text-rose-600">{{ form.errors.starts_at }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fim</label>
                        <input v-model="form.ends_at" type="datetime-local" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        <p v-if="form.errors.ends_at" class="mt-1 text-xs text-rose-600">{{ form.errors.ends_at }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Local</label>
                        <input v-model="form.location" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Endereço ou sala">
                        <p v-if="form.errors.location" class="mt-1 text-xs text-rose-600">{{ form.errors.location }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</label>
                        <textarea v-model="form.notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Instruções adicionais" />
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                        Cancelar
                    </button>
                    <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60" :disabled="form.processing" @click="submitAppointment">
                        {{ form.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir compromisso"
            message="Tem certeza que deseja excluir este compromisso?"
            :item-label="appointmentToDelete?.title ? `Compromisso: ${appointmentToDelete.title}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="destroyAppointment"
        />
    </AuthenticatedLayout>
</template>
