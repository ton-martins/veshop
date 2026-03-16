<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Building2, CircleCheckBig, Store, Briefcase, Search, Filter, Plus, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    contractors: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, active: 0, commercial: 0, services: 0 }),
    },
    plans: {
        type: Array,
        default: () => [],
    },
    niches: {
        type: Array,
        default: () => [],
    },
    businessTypes: {
        type: Array,
        default: () => [],
    },
    moduleCatalog: {
        type: Array,
        default: () => [],
    },
    modulePresets: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const flashStatus = computed(() => page.props.flash?.status ?? null);

const filterForm = useForm({
    search: props.filters?.search ?? '',
    niche: props.filters?.niche ?? '',
    status: props.filters?.status ?? '',
    plan_id: props.filters?.plan_id ?? '',
});

watch(
    () => props.filters,
    (next) => {
        filterForm.search = next?.search ?? '';
        filterForm.niche = next?.niche ?? '';
        filterForm.status = next?.status ?? '';
        filterForm.plan_id = next?.plan_id ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('master.contractors.index'),
        {
            search: filterForm.search || undefined,
            niche: filterForm.niche || undefined,
            status: filterForm.status || undefined,
            plan_id: filterForm.plan_id || undefined,
        },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        },
    );
};

const clearSearch = () => {
    if (!String(filterForm.search ?? '').trim()) return;
    filterForm.search = '';
    applyFilters();
};

const clearFilters = () => {
    filterForm.search = '';
    filterForm.niche = '';
    filterForm.status = '';
    filterForm.plan_id = '';
    applyFilters();
};

const rows = computed(() => props.contractors?.data ?? []);
const paginationLinks = computed(() => props.contractors?.links ?? []);
const filteredPlans = computed(() => {
    const allPlans = props.plans ?? [];
    if (!filterForm.niche) return allPlans;

    return allPlans.filter((plan) => plan.niche === filterForm.niche);
});
const nicheFilterOptions = computed(() => [
    { value: '', label: 'Todos nichos' },
    ...(props.niches ?? []).map((niche) => ({
        value: niche.value,
        label: niche.label,
    })),
]);
const planFilterOptions = computed(() => [
    { value: '', label: 'Todos planos' },
    ...(filteredPlans.value ?? []).map((plan) => ({
        value: plan.id,
        label: filterForm.niche ? plan.name : `${plan.name} (${plan.niche_label})`,
    })),
]);
const statusFilterOptions = [
    { value: '', label: 'Todos status' },
    { value: 'active', label: 'Ativos' },
    { value: 'inactive', label: 'Inativos' },
];

const statsCards = computed(() => [
    { key: 'total', label: 'Contratantes', value: String(props.stats?.total ?? 0), icon: Building2, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Ativos', value: String(props.stats?.active ?? 0), icon: CircleCheckBig, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'commercial', label: 'Nicho comércio', value: String(props.stats?.commercial ?? 0), icon: Store, tone: 'bg-blue-100 text-blue-700' },
    { key: 'services', label: 'Nicho serviços', value: String(props.stats?.services ?? 0), icon: Briefcase, tone: 'bg-amber-100 text-amber-700' },
]);

const showModal = ref(false);
const editingContractor = ref(null);
const showDeleteModal = ref(false);
const contractorToDelete = ref(null);
const suppressAutoModulePreset = ref(false);

const contractorForm = useForm({
    name: '',
    email: '',
    phone: '',
    cnpj: '',
    slug: '',
    timezone: 'America/Sao_Paulo',
    brand_name: '',
    brand_primary_color: '#073341',
    business_niche: props.niches?.[0]?.value ?? 'commercial',
    business_type: '',
    plan_id: '',
    module_codes: [],
    is_active: true,
});
const deleteForm = useForm({});
const formPlans = computed(() => {
    return (props.plans ?? []).filter((plan) => plan.niche === contractorForm.business_niche);
});
const nicheFormOptions = computed(() =>
    (props.niches ?? []).map((niche) => ({
        value: niche.value,
        label: niche.label,
    })),
);
const businessTypeFormOptions = computed(() => {
    return (props.businessTypes ?? [])
        .filter((item) => item.niche === contractorForm.business_niche)
        .map((item) => ({
            value: item.value,
            label: item.label,
        }));
});
const planFormOptions = computed(() => [
    { value: '', label: 'Sem plano' },
    ...(formPlans.value ?? []).map((plan) => ({
        value: plan.id,
        label: plan.name,
    })),
]);

const isEditing = computed(() => Boolean(editingContractor.value?.id));
const availableFormModuleCatalog = computed(() => {
    const niche = String(contractorForm.business_niche ?? '').trim().toLowerCase();
    const businessType = String(contractorForm.business_type ?? '').trim().toLowerCase();

    return (props.moduleCatalog ?? []).filter((module) => {
        const moduleNiche = String(module?.niche ?? '').trim().toLowerCase();
        const moduleBusinessTypes = Array.isArray(module?.business_types) ? module.business_types : [];

        if (moduleNiche && moduleNiche !== niche) return false;
        if (moduleBusinessTypes.length && !moduleBusinessTypes.includes(businessType)) return false;

        return true;
    });
});
const formGlobalModules = computed(() => availableFormModuleCatalog.value.filter((module) => module.scope === 'global'));
const formSpecificModules = computed(() => availableFormModuleCatalog.value.filter((module) => module.scope !== 'global'));

const resolvePresetModules = (businessType) => {
    const fallback = [];
    const value = String(businessType ?? '').trim().toLowerCase();
    if (!value) return fallback;

    const preset = props.modulePresets?.[value];
    if (!Array.isArray(preset)) return fallback;

    return preset
        .map((moduleCode) => String(moduleCode ?? '').trim().toLowerCase())
        .filter(Boolean);
};

const isModuleSelected = (moduleCode) =>
    (contractorForm.module_codes ?? []).includes(String(moduleCode ?? '').trim().toLowerCase());

const toggleModuleCode = (moduleCode) => {
    const safeCode = String(moduleCode ?? '').trim().toLowerCase();
    if (!safeCode) return;

    const selected = new Set((contractorForm.module_codes ?? []).map((item) => String(item)));
    if (selected.has(safeCode)) {
        selected.delete(safeCode);
    } else {
        selected.add(safeCode);
    }

    contractorForm.module_codes = Array.from(selected);
};

watch(
    () => filterForm.niche,
    () => {
        if (!filterForm.plan_id) return;
        const stillExists = filteredPlans.value.some((plan) => String(plan.id) === String(filterForm.plan_id));
        if (!stillExists) {
            filterForm.plan_id = '';
        }
    },
);

watch(
    () => contractorForm.business_niche,
    () => {
        const validBusinessType = businessTypeFormOptions.value.some((option) => option.value === contractorForm.business_type);
        if (!validBusinessType) {
            contractorForm.business_type = businessTypeFormOptions.value[0]?.value ?? '';
        }

        if (contractorForm.plan_id) {
            const stillExists = formPlans.value.some((plan) => String(plan.id) === String(contractorForm.plan_id));
            if (!stillExists) {
                contractorForm.plan_id = '';
            }
        }

        const availableCodes = availableFormModuleCatalog.value.map((module) => String(module.code));
        contractorForm.module_codes = (contractorForm.module_codes ?? [])
            .filter((code) => availableCodes.includes(String(code)));

        if (contractorForm.module_codes.length === 0) {
            contractorForm.module_codes = resolvePresetModules(contractorForm.business_type);
        }
    },
);

watch(
    () => contractorForm.business_type,
    (value, oldValue) => {
        if (suppressAutoModulePreset.value) return;
        if (!value) return;
        if (String(value) === String(oldValue)) return;

        const preset = resolvePresetModules(value);
        if (preset.length > 0) {
            contractorForm.module_codes = preset;
        }
    },
);

const openCreate = () => {
    suppressAutoModulePreset.value = true;
    editingContractor.value = null;
    contractorForm.reset();
    contractorForm.clearErrors();
    contractorForm.timezone = 'America/Sao_Paulo';
    contractorForm.brand_primary_color = '#073341';
    contractorForm.business_niche = props.niches?.[0]?.value ?? 'commercial';
    contractorForm.business_type = businessTypeFormOptions.value[0]?.value ?? '';
    contractorForm.plan_id = '';
    contractorForm.module_codes = resolvePresetModules(contractorForm.business_type);
    contractorForm.is_active = true;
    Promise.resolve().then(() => {
        suppressAutoModulePreset.value = false;
    });
    showModal.value = true;
};

const openEdit = (contractor) => {
    suppressAutoModulePreset.value = true;
    editingContractor.value = contractor;
    contractorForm.name = contractor.name ?? '';
    contractorForm.email = contractor.email ?? '';
    contractorForm.phone = contractor.phone ?? '';
    contractorForm.cnpj = contractor.cnpj ?? '';
    contractorForm.slug = contractor.slug ?? '';
    contractorForm.timezone = contractor.timezone ?? 'America/Sao_Paulo';
    contractorForm.brand_name = contractor.brand_name ?? '';
    contractorForm.brand_primary_color = contractor.brand_primary_color ?? '#073341';
    contractorForm.business_niche = contractor.business_niche ?? 'commercial';
    contractorForm.business_type = contractor.business_type ?? (businessTypeFormOptions.value[0]?.value ?? '');
    contractorForm.plan_id = contractor.plan_id ?? '';
    contractorForm.module_codes = Array.isArray(contractor.enabled_module_codes)
        ? contractor.enabled_module_codes.map((item) => String(item ?? '').trim().toLowerCase()).filter(Boolean)
        : resolvePresetModules(contractor.business_type ?? '');
    contractorForm.is_active = Boolean(contractor.is_active);
    contractorForm.clearErrors();
    Promise.resolve().then(() => {
        suppressAutoModulePreset.value = false;
    });
    showModal.value = true;
};

const closeModal = () => {
    suppressAutoModulePreset.value = false;
    showModal.value = false;
    editingContractor.value = null;
};

const submitContractor = () => {
    contractorForm.transform((data) => ({
        ...data,
        module_codes: Array.isArray(data.module_codes)
            ? data.module_codes.map((item) => String(item ?? '').trim().toLowerCase()).filter(Boolean)
            : [],
    }));

    if (isEditing.value) {
        contractorForm.put(route('master.contractors.update', editingContractor.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    contractorForm.post(route('master.contractors.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const openDeleteModal = (contractor) => {
    contractorToDelete.value = contractor;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    contractorToDelete.value = null;
};

const removeContractor = () => {
    if (!contractorToDelete.value?.id) return;

    deleteForm.delete(route('master.contractors.destroy', contractorToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};

const formatMoney = (value) => {
    if (value === null || value === undefined) return 'Sob consulta';

    const parsed = Number(value);
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number.isFinite(parsed) ? parsed : 0);
};
</script>

<template>
    <Head title="Contratantes" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Contratantes">
        <section class="space-y-4">
            <div v-if="flashStatus" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ flashStatus }}
            </div>

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
                    <div class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Buscar contratante por nome, email, slug ou CNPJ"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keydown.enter.prevent="applyFilters"
                        />
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
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="applyFilters"
                        >
                            <Search class="h-3.5 w-3.5" />
                            Buscar
                        </button>
                        <UiSelect
                            v-model="filterForm.niche"
                            :options="nicheFilterOptions"
                            button-class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <UiSelect
                            v-model="filterForm.plan_id"
                            :options="planFilterOptions"
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
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="clearFilters"
                        >
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            @click="openCreate"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Novo contratante
                        </button>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Contratante</th>
                                <th class="px-4 py-3">Plano</th>
                                <th class="px-4 py-3">Nicho</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Admins</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Nenhum contratante encontrado.
                                </td>
                            </tr>
                            <tr v-for="contractor in rows" :key="contractor.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ contractor.name }}</p>
                                    <p class="text-xs text-slate-500">{{ contractor.email }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p class="font-semibold text-slate-800">{{ contractor.plan_name || 'Sem plano' }}</p>
                                    <p class="text-xs text-slate-500">{{ formatMoney(contractor.monthly_price) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ contractor.business_niche_label }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ contractor.business_type_label || '--' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ contractor.admins_count }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                        :class="contractor.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                                    >
                                        {{ contractor.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="openEdit(contractor)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="openDeleteModal(contractor)"
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
            <WizardModalFrame
                :title="isEditing ? 'Editar contratante' : 'Novo contratante'"
                description="Preencha os dados do contratante."
                :steps="['Dados do contratante']"
                :current-step="1"
                @close="closeModal"
            >
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input
                            v-model="contractorForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Veshop Market"
                        >
                        <p v-if="contractorForm.errors.name" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                        <input
                            v-model="contractorForm.email"
                            type="email"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="contato@empresa.com.br"
                        >
                        <p v-if="contractorForm.errors.email" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                        <input
                            v-model="contractorForm.phone"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="(00) 00000-0000"
                        >
                        <p v-if="contractorForm.errors.phone" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.phone }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">CNPJ</label>
                        <input
                            v-model="contractorForm.cnpj"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Somente números"
                        >
                        <p v-if="contractorForm.errors.cnpj" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.cnpj }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug (opcional)</label>
                        <input
                            v-model="contractorForm.slug"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="veshop-market"
                        >
                        <p v-if="contractorForm.errors.slug" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.slug }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Timezone</label>
                        <input
                            v-model="contractorForm.timezone"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="America/Sao_Paulo"
                        >
                        <p v-if="contractorForm.errors.timezone" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.timezone }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome da marca</label>
                        <input
                            v-model="contractorForm.brand_name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Nome exibido no sistema"
                        >
                        <p v-if="contractorForm.errors.brand_name" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.brand_name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cor principal</label>
                        <input
                            v-model="contractorForm.brand_primary_color"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="#073341"
                        >
                        <p v-if="contractorForm.errors.brand_primary_color" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.brand_primary_color }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nicho</label>
                        <UiSelect
                            v-model="contractorForm.business_niche"
                            :options="nicheFormOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="contractorForm.errors.business_niche" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.business_niche }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo de contratante</label>
                        <UiSelect
                            v-model="contractorForm.business_type"
                            :options="businessTypeFormOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="contractorForm.errors.business_type" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.business_type }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Plano ativo</label>
                        <UiSelect
                            v-model="contractorForm.plan_id"
                            :options="planFormOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="contractorForm.errors.plan_id" class="mt-1 text-xs text-rose-600">{{ contractorForm.errors.plan_id }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Módulos globais</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="module in formGlobalModules"
                                    :key="`global-${module.code}`"
                                    class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 rounded border-slate-300"
                                        :checked="isModuleSelected(module.code)"
                                        @change="toggleModuleCode(module.code)"
                                    >
                                    <span>
                                        <span class="block font-semibold text-slate-800">{{ module.name }}</span>
                                        <span v-if="module.description" class="text-slate-500">{{ module.description }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Módulos específicos</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="module in formSpecificModules"
                                    :key="`specific-${module.code}`"
                                    class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 rounded border-slate-300"
                                        :checked="isModuleSelected(module.code)"
                                        @change="toggleModuleCode(module.code)"
                                    >
                                    <span>
                                        <span class="block font-semibold text-slate-800">{{ module.name }}</span>
                                        <span v-if="module.description" class="text-slate-500">{{ module.description }}</span>
                                    </span>
                                </label>
                            </div>
                            <p v-if="contractorForm.errors.module_codes" class="mt-2 text-xs text-rose-600">{{ contractorForm.errors.module_codes }}</p>
                        </div>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input v-model="contractorForm.is_active" type="checkbox" class="rounded border-slate-300">
                    Contratante ativo
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
                            :disabled="contractorForm.processing"
                            @click="submitContractor"
                        >
                            {{ contractorForm.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir contratante"
            message="Tem certeza que deseja excluir este contratante?"
            :item-label="contractorToDelete?.name ? `Contratante: ${contractorToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="removeContractor"
        />
    </AuthenticatedLayout>
</template>
