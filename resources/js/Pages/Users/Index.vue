<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    roles: {
        type: Array,
        default: () => [],
    },
    contractors: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const flashStatus = computed(() => page.props.flash?.status ?? null);
const generalError = computed(() => page.props.errors?.general ?? null);

const filtersForm = useForm({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    status: props.filters.status ?? '',
    contractor_id: props.filters.contractor_id ?? '',
});

const applyFilters = () => {
    router.get(
        route('master.users.index'),
        {
            ...filtersForm.data(),
        },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        },
    );
};

const clearFilters = () => {
    filtersForm.reset();
    applyFilters();
};

const destroyUser = (user) => {
    if (!confirm(`Deseja excluir o usuário ${user.name}?`)) return;

    router.delete(route('master.users.destroy', user.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Usuários" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Usuários">

        <div class="space-y-4">
            <div v-if="flashStatus" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ flashStatus }}
            </div>
            <div v-if="generalError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                {{ generalError }}
            </div>

            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Buscar</label>
                        <input
                            v-model="filtersForm.search"
                            type="text"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            placeholder="Nome, e-mail, CPF, telefone"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Perfil</label>
                        <select v-model="filtersForm.role" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Todos</option>
                            <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                        <select v-model="filtersForm.status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Todos</option>
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contratante</label>
                        <select
                            v-model="filtersForm.contractor_id"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        >
                            <option value="">Todos</option>
                            <option v-for="contractor in contractors" :key="contractor.id" :value="String(contractor.id)">
                                {{ contractor.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700"
                        @click="applyFilters"
                    >
                        Aplicar filtros
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600"
                        @click="clearFilters"
                    >
                        Limpar
                    </button>

                    <Link
                        :href="route('master.users.create')"
                        class="ms-auto rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700"
                    >
                        Novo usuário
                    </Link>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Contato</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Perfil</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Contratantes</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in users.data" :key="user.id">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-slate-900">{{ user.name }}</p>
                                    <p class="text-xs text-slate-500">Criado em {{ user.created_at }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    <p>{{ user.email }}</p>
                                    <p v-if="user.phone" class="text-xs text-slate-500">{{ user.phone }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ user.role }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    <template v-if="user.contractors?.length">
                                        {{ user.contractors.map((item) => item.name).join(', ') }}
                                    </template>
                                    <template v-else>Sem contratante</template>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                    >
                                        {{ user.is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <Link
                                            :href="route('master.users.edit', user.id)"
                                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700"
                                        >
                                            Editar
                                        </Link>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700"
                                            @click="destroyUser(user)"
                                        >
                                            Excluir
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!users.data.length">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                                    Nenhum usuário encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="users.links?.length" class="flex flex-wrap items-center gap-2 border-t border-slate-200 p-3">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, index) in users.links"
                        :key="`page-link-${index}`"
                        :href="link.url || undefined"
                        class="rounded-lg border px-2.5 py-1.5 text-xs"
                        :class="[
                            link.active
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                : 'border-slate-200 bg-white text-slate-600',
                            !link.url ? 'cursor-not-allowed opacity-50' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
