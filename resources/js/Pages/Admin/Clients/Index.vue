<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Users2, UserPlus2, MapPin, AlertCircle, Search, Filter, Plus, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    clients: {
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
        route('admin.clients.index'),
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

const rows = computed(() => props.clients?.data ?? []);
const paginationLinks = computed(() => props.clients?.links ?? []);

const statsCards = computed(() => [
    { key: 'total', label: 'Clientes cadastrados', value: String(props.stats?.total ?? 0), icon: Users2, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Clientes ativos', value: String(props.stats?.active ?? 0), icon: UserPlus2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'new_month', label: 'Novos no mês', value: String(props.stats?.new_month ?? 0), icon: AlertCircle, tone: 'bg-blue-100 text-blue-700' },
    { key: 'cities', label: 'Cidades atendidas', value: String(props.stats?.cities ?? 0), icon: MapPin, tone: 'bg-amber-100 text-amber-700' },
]);

const showModal = ref(false);
const editingClient = ref(null);

const clientForm = useForm({
    name: '',
    email: '',
    phone: '',
    document: '',
    city: '',
    state: '',
    is_active: true,
});

const isEditing = computed(() => Boolean(editingClient.value?.id));

const openCreate = () => {
    editingClient.value = null;
    clientForm.reset();
    clientForm.clearErrors();
    clientForm.is_active = true;
    showModal.value = true;
};

const openEdit = (client) => {
    editingClient.value = client;
    clientForm.name = client.name ?? '';
    clientForm.email = client.email ?? '';
    clientForm.phone = client.phone ?? '';
    clientForm.document = client.document ?? '';
    clientForm.city = client.city ?? '';
    clientForm.state = client.state ?? '';
    clientForm.is_active = Boolean(client.is_active);
    clientForm.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingClient.value = null;
};

const submitClient = () => {
    if (isEditing.value) {
        clientForm.put(route('admin.clients.update', editingClient.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    clientForm.post(route('admin.clients.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const removeClient = (client) => {
    const confirmed = window.confirm(`Excluir o cliente "${client.name}"?`);
    if (!confirmed) return;

    router.delete(route('admin.clients.destroy', client.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Clientes">
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
                            placeholder="Buscar cliente por nome, email, telefone ou cidade"
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
                            Novo cliente
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Contato</th>
                                <th class="px-4 py-3">Documento</th>
                                <th class="px-4 py-3">Cidade</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!rows.length">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhum cliente cadastrado para este contratante.
                                </td>
                            </tr>
                            <tr v-for="client in rows" :key="client.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ client.name }}</p>
                                    <p class="text-xs text-slate-500">Criado em {{ client.created_at }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ client.email || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ client.phone || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ client.document || '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ client.city || '-' }}<span v-if="client.state">/{{ client.state }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="client.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ client.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="openEdit(client)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="removeClient(client)"
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
                    {{ isEditing ? 'Editar cliente' : 'Novo cliente' }}
                </h3>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input
                            v-model="clientForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Maria Souza"
                        >
                        <p v-if="clientForm.errors.name" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                        <input
                            v-model="clientForm.email"
                            type="email"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="cliente@email.com"
                        >
                        <p v-if="clientForm.errors.email" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                        <input
                            v-model="clientForm.phone"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="(00) 00000-0000"
                        >
                        <p v-if="clientForm.errors.phone" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.phone }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documento</label>
                        <input
                            v-model="clientForm.document"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="CPF/CNPJ"
                        >
                        <p v-if="clientForm.errors.document" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.document }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cidade</label>
                        <input
                            v-model="clientForm.city"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Cidade"
                        >
                        <p v-if="clientForm.errors.city" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.city }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">UF</label>
                        <input
                            v-model="clientForm.state"
                            type="text"
                            maxlength="2"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm uppercase text-slate-700"
                            placeholder="SP"
                        >
                        <p v-if="clientForm.errors.state" class="mt-1 text-xs text-rose-600">{{ clientForm.errors.state }}</p>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input v-model="clientForm.is_active" type="checkbox" class="rounded border-slate-300">
                    Cliente ativo
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
                        :disabled="clientForm.processing"
                        @click="submitClient"
                    >
                        {{ clientForm.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
