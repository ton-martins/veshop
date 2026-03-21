<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Layers,
    CircleCheckBig,
    Store,
    Briefcase,
    Search,
    Filter,
    Plus,
    Pencil,
    Trash2,
    CheckCircle2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    plans: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, active: 0, commercial: 0, services: 0, subscriptions: 0, avg_ticket: null }),
    },
    niches: {
        type: Array,
        default: () => [],
    },
    moduleCatalog: {
        type: Array,
        default: () => [],
    },
    defaultModuleCodesByNiche: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const flashStatus = computed(() => page.props.flash?.status ?? null);
const generalError = computed(() => page.props.errors?.general ?? null);

const filterForm = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
    niche: props.filters?.niche ?? '',
});

watch(
    () => props.filters,
    (next) => {
        filterForm.search = next?.search ?? '';
        filterForm.status = next?.status ?? '';
        filterForm.niche = next?.niche ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('master.plans.index'),
        {
            search: filterForm.search || undefined,
            status: filterForm.status || undefined,
            niche: filterForm.niche || undefined,
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
    filterForm.status = '';
    filterForm.niche = '';
    applyFilters();
};

const rows = computed(() => props.plans?.data ?? []);
const paginationLinks = computed(() => props.plans?.links ?? []);
const nicheFilterOptions = computed(() => [
    { value: '', label: 'Todos nichos' },
    ...(props.niches ?? []).map((niche) => ({
        value: niche.value,
        label: niche.label,
    })),
]);
const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'active', label: 'Ativos' },
    { value: 'inactive', label: 'Inativos' },
];
const nicheFormOptions = computed(() =>
    (props.niches ?? []).map((niche) => ({
        value: niche.value,
        label: niche.label,
    })),
);
const groupedRows = computed(() => {
    const nicheMap = new Map((props.niches ?? []).map((niche) => [niche.value, { ...niche, plans: [] }]));

    for (const plan of rows.value) {
        const key = plan?.niche ?? '';
        let bucket = nicheMap.get(key);

        if (!bucket && key) {
            bucket = {
                value: key,
                label: plan?.niche_label ?? key,
                plans: [],
            };
            nicheMap.set(key, bucket);
        }

        if (bucket) {
            bucket.plans.push(plan);
        }
    }

    return Array.from(nicheMap.values());
});

const activeNicheTab = ref(props.filters?.niche ?? props.niches?.[0]?.value ?? 'commercial');
const nicheTabs = computed(() =>
    groupedRows.value.map((group) => ({
        ...group,
        icon: group.value === 'services' ? Briefcase : Store,
    })),
);

watch(
    nicheTabs,
    (tabs) => {
        if (!tabs.length) {
            activeNicheTab.value = '';
            return;
        }

        const selectedTabExists = tabs.some((tab) => tab.value === activeNicheTab.value);
        if (!selectedTabExists) {
            activeNicheTab.value = tabs[0].value;
        }
    },
    { immediate: true },
);

const statsCards = computed(() => [
    { key: 'total', label: 'Planos', value: String(props.stats?.total ?? 0), icon: Layers, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Planos ativos', value: String(props.stats?.active ?? 0), icon: CircleCheckBig, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'commercial', label: 'Nicho comércio', value: String(props.stats?.commercial ?? 0), icon: Store, tone: 'bg-slate-100 text-slate-700' },
    { key: 'services', label: 'Nicho serviços', value: String(props.stats?.services ?? 0), icon: Briefcase, tone: 'bg-amber-100 text-amber-700' },
]);

const showModal = ref(false);
const editingPlan = ref(null);
const showDeleteModal = ref(false);
const planToDelete = ref(null);
const currentPlanStep = ref(1);

const planForm = useForm({
    niche: props.niches?.[0]?.value ?? 'commercial',
    name: '',
    slug: '',
    badge: '',
    subtitle: '',
    summary: '',
    footer_message: '',
    price_monthly: '',
    user_limit: '',
    storage_limit_gb: '',
    audit_log_retention_days: '',
    tier_rank: 0,
    features_text: '',
    module_codes: [],
    is_active: true,
    is_featured: false,
    show_on_landing: false,
});
const deleteForm = useForm({});

const isEditing = computed(() => Boolean(editingPlan.value?.id));
const planWizardSteps = ['Dados do plano', 'Módulos e permissões'];
const availablePlanModules = computed(() => {
    const niche = String(planForm.niche ?? '').trim().toLowerCase();

    return (props.moduleCatalog ?? []).filter((module) => {
        const moduleNiche = String(module?.niche ?? '').trim().toLowerCase();
        if (moduleNiche !== '' && moduleNiche !== niche) {
            return false;
        }

        return true;
    });
});
const planGlobalModules = computed(() => availablePlanModules.value.filter((module) => String(module?.scope ?? '') === 'global'));
const planSpecificModules = computed(() => availablePlanModules.value.filter((module) => String(module?.scope ?? '') !== 'global'));

const resolveDefaultPlanModules = (niche) => {
    const normalizedNiche = String(niche ?? '').trim().toLowerCase();
    const availableCodes = availablePlanModules.value
        .map((module) => String(module?.code ?? '').trim().toLowerCase())
        .filter(Boolean);

    return (props.defaultModuleCodesByNiche?.[normalizedNiche] ?? [])
        .map((code) => String(code ?? '').trim().toLowerCase())
        .filter((code) => availableCodes.includes(code));
};

const isPlanModuleSelected = (moduleCode) =>
    (planForm.module_codes ?? []).includes(String(moduleCode ?? '').trim().toLowerCase());

const togglePlanModuleCode = (moduleCode) => {
    const safeCode = String(moduleCode ?? '').trim().toLowerCase();
    if (!safeCode) return;

    const selected = new Set((planForm.module_codes ?? []).map((item) => String(item ?? '').trim().toLowerCase()));
    if (selected.has(safeCode)) {
        selected.delete(safeCode);
    } else {
        selected.add(safeCode);
    }

    planForm.module_codes = Array.from(selected);
};

const sanitizePlanModulesForNiche = () => {
    const availableCodes = availablePlanModules.value
        .map((module) => String(module?.code ?? '').trim().toLowerCase())
        .filter(Boolean);

    const selected = (planForm.module_codes ?? [])
        .map((item) => String(item ?? '').trim().toLowerCase())
        .filter((code) => availableCodes.includes(code));

    if (selected.length > 0) {
        planForm.module_codes = Array.from(new Set(selected));
        return;
    }

    planForm.module_codes = resolveDefaultPlanModules(planForm.niche);
};

const openCreate = () => {
    editingPlan.value = null;
    planForm.reset();
    planForm.clearErrors();
    planForm.niche = props.niches?.[0]?.value ?? 'commercial';
    planForm.tier_rank = 0;
    planForm.module_codes = resolveDefaultPlanModules(planForm.niche);
    planForm.is_active = true;
    planForm.is_featured = false;
    planForm.show_on_landing = false;
    currentPlanStep.value = 1;
    showModal.value = true;
};

const toFeatureText = (plan) => {
    const features = Array.isArray(plan?.features) ? plan.features : [];

    return features
        .filter((feature) => feature?.enabled !== false)
        .map((feature) => {
            const label = String(feature?.label ?? '').trim();
            const value = String(feature?.value ?? '').trim();

            if (!label) return value;

            return value ? `${label}: ${value}` : label;
        })
        .filter(Boolean)
        .join('\n');
};

const openEdit = (plan) => {
    editingPlan.value = plan;
    planForm.niche = plan.niche ?? props.niches?.[0]?.value ?? 'commercial';
    planForm.name = plan.name ?? '';
    planForm.slug = plan.slug ?? '';
    planForm.badge = plan.badge ?? '';
    planForm.subtitle = plan.subtitle ?? '';
    planForm.summary = plan.summary ?? '';
    planForm.footer_message = plan.footer_message ?? '';
    planForm.price_monthly = plan.price_monthly ?? '';
    planForm.user_limit = plan.user_limit ?? plan.max_admin_users ?? '';
    planForm.storage_limit_gb = plan.storage_limit_gb ?? '';
    planForm.audit_log_retention_days = plan.audit_log_retention_days ?? '';
    planForm.tier_rank = Number(plan.tier_rank ?? 0);
    planForm.features_text = plan.features_text ?? toFeatureText(plan);
    planForm.module_codes = Array.isArray(plan?.module_codes)
        ? plan.module_codes.map((item) => String(item ?? '').trim().toLowerCase()).filter(Boolean)
        : resolveDefaultPlanModules(planForm.niche);
    planForm.is_active = Boolean(plan.is_active);
    planForm.is_featured = Boolean(plan.is_featured);
    planForm.show_on_landing = Boolean(plan.show_on_landing);
    sanitizePlanModulesForNiche();
    currentPlanStep.value = 1;
    planForm.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    currentPlanStep.value = 1;
    showModal.value = false;
    editingPlan.value = null;
};

const submitPlan = () => {
    planForm.transform((data) => ({
        ...data,
        module_codes: Array.isArray(data.module_codes)
            ? data.module_codes.map((item) => String(item ?? '').trim().toLowerCase()).filter(Boolean)
            : [],
    }));

    if (isEditing.value) {
        planForm.put(route('master.plans.update', editingPlan.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    planForm.post(route('master.plans.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const openDeleteModal = (plan) => {
    planToDelete.value = plan;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    planToDelete.value = null;
};

const removePlan = () => {
    if (!planToDelete.value?.id) return;

    deleteForm.delete(route('master.plans.destroy', planToDelete.value.id), {
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

const limitLabel = (plan) => {
    const limit = plan?.user_limit ?? plan?.max_admin_users ?? null;
    if (limit === null || limit === undefined || limit === '') return 'Usuários ilimitados';

    return `${limit} usuário(s)`;
};

const planFeatures = (plan) => {
    const features = Array.isArray(plan?.features) ? plan.features : [];

    return features
        .filter((feature) => feature?.enabled !== false)
        .slice(0, 5);
};

const planTone = (plan) => {
    if (plan?.is_featured) {
        return {
            border: 'border-t-emerald-500',
            title: 'text-emerald-700',
            price: 'text-emerald-700',
            button: 'bg-emerald-600 hover:bg-emerald-700',
        };
    }

    return {
        border: 'border-t-slate-500',
        title: 'text-slate-700',
        price: 'text-slate-700',
        button: 'bg-slate-700 hover:bg-slate-800',
    };
};

const planFeatureLines = (plan) =>
    planFeatures(plan).map((feature) => {
        const label = String(feature?.label ?? '').trim();
        const value = String(feature?.value ?? '').trim();

        if (!label) return value;
        if (!value) return label;

        return `${label} - ${value}`;
    });

watch(
    () => planForm.niche,
    () => {
        sanitizePlanModulesForNiche();
    },
);
</script>

<template>
    <Head title="Planos" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Planos e Assinaturas">
        <section class="space-y-4">
            <div v-if="flashStatus" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ flashStatus }}
            </div>
            <div v-if="generalError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                {{ generalError }}
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
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

            <section class="rounded-[30px] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="veshop-search-shell flex min-w-0 flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Buscar plano por nome, slug, badge ou subtítulo"
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
                            v-model="filterForm.niche"
                            :options="nicheFilterOptions"
                            button-class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <UiSelect
                            v-model="filterForm.status"
                            :options="statusOptions"
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
                            Novo plano
                        </button>
                    </div>
                </div>

                <div class="mt-6 space-y-6">
                    <div v-if="nicheTabs.length" class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in nicheTabs"
                            :key="`niche-tab-${tab.value}`"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold transition"
                            :class="activeNicheTab === tab.value
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            @click="activeNicheTab = tab.value"
                        >
                            <component :is="tab.icon" class="h-3.5 w-3.5" />
                            <span>{{ tab.label }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :class="activeNicheTab === tab.value ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'"
                            >
                                {{ tab.plans.length }}
                            </span>
                        </button>
                    </div>
                    <section
                        v-for="nicheGroup in groupedRows"
                        :key="`niche-group-${nicheGroup.value}`"
                        v-show="activeNicheTab === nicheGroup.value"
                        class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 md:p-5"
                    >
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ nicheGroup.label }}</h3>
                                <p class="text-xs text-slate-500">Planos configurados para este nicho.</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                {{ nicheGroup.plans.length }} plano(s)
                            </span>
                        </div>

                        <div class="grid gap-5 lg:grid-cols-3">
                            <article
                                v-for="plan in nicheGroup.plans"
                                :key="plan.id"
                                class="relative overflow-hidden rounded-2xl border-0 border-t-4 bg-white shadow-sm transition hover:shadow-md"
                                :class="planTone(plan).border"
                            >
                                <span
                                    v-if="plan.is_featured"
                                    class="absolute right-4 top-4 rounded-full bg-emerald-600 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white shadow-sm"
                                >
                                    Recomendado
                                </span>

                                <div class="px-5 pb-4 pt-6 text-center">
                                    <h3 class="text-lg font-bold uppercase tracking-wide" :class="planTone(plan).title">
                                        {{ plan.name }}
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Plano mensal
                                    </p>
                                    <p class="mt-3 text-4xl font-bold" :class="planTone(plan).price">
                                        {{ formatMoney(plan.price_monthly) }}
                                    </p>
                                    <p class="text-xs text-slate-500">/ mês</p>
                                    <p class="mt-3 text-sm text-slate-600">
                                        {{ plan.subtitle || limitLabel(plan) }}
                                    </p>
                                    <p class="mt-2 text-xs text-slate-500">
                                        {{ plan.summary || 'Plano flexível para cada fase da operação.' }}
                                    </p>
                                </div>

                                <div class="border-t border-slate-100 px-5 py-4">
                                    <ul class="space-y-2">
                                        <li
                                            v-for="(featureLine, featureIndex) in planFeatureLines(plan)"
                                            :key="`plan-feature-line-${plan.id}-${featureIndex}`"
                                            class="flex items-start gap-2.5 text-sm text-slate-700"
                                        >
                                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                            <span>{{ featureLine }}</span>
                                        </li>
                                    </ul>
                                    <p v-if="!planFeatureLines(plan).length" class="text-xs text-slate-500">
                                        Nenhum benefício configurado ainda.
                                    </p>
                                </div>

                                <div class="border-t border-slate-100 px-5 py-4">
                                    <div class="flex items-center justify-between text-xs text-slate-500">
                                        <span>Assinaturas: {{ plan.active_contractors_count }}</span>
                                        <span v-if="plan.badge" class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">
                                            {{ plan.badge }}
                                        </span>
                                    </div>
                                    <div class="mt-3 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                                :class="plan.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                                            >
                                                {{ plan.status_label }}
                                            </span>
                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                                {{ plan.niche_label }}
                                            </span>
                                            <span v-if="plan.show_on_landing" class="rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700">
                                                Landing page
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-4 grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center gap-1 rounded-lg border border-slate-200 px-2 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="openEdit(plan)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center gap-1 rounded-lg border border-rose-200 px-2 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            @click="openDeleteModal(plan)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                            Excluir
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        class="mt-2 inline-flex w-full items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold text-white transition"
                                        :class="planTone(plan).button"
                                        @click="openEdit(plan)"
                                    >
                                        Gerenciar plano
                                    </button>
                                    <p class="mt-2 text-[11px] text-slate-500">
                                        {{ plan.footer_message || 'Use este plano para controlar acesso, limite e operação.' }}
                                    </p>
                                </div>
                            </article>

                            <div
                                v-if="!nicheGroup.plans.length"
                                class="col-span-full rounded-3xl border border-dashed border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500"
                            >
                                Nenhum plano encontrado para {{ nicheGroup.label.toLowerCase() }}.
                            </div>
                        </div>
                    </section>

                    <div v-if="!groupedRows.length" class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                        Nenhum nicho encontrado para segmentação.
                    </div>
                </div>

                <PaginationLinks :links="paginationLinks" :min-links="4" />
            </section>
        </section>

        <Modal :show="showModal" max-width="5xl" @close="closeModal">
            <WizardModalFrame
                :title="isEditing ? 'Editar plano' : 'Novo plano'"
                description="Configure dados, limites e módulos do plano."
                :steps="planWizardSteps"
                :current-step="currentPlanStep"
                :steps-clickable="true"
                @close="closeModal"
                @step-change="(step) => { currentPlanStep = step; }"
            >
                <div v-if="currentPlanStep === 1" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input
                            v-model="planForm.name"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Pro"
                        >
                        <p v-if="planForm.errors.name" class="mt-1 text-xs text-rose-600">{{ planForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug (opcional)</label>
                        <input
                            v-model="planForm.slug"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="pro"
                        >
                        <p v-if="planForm.errors.slug" class="mt-1 text-xs text-rose-600">{{ planForm.errors.slug }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nicho</label>
                        <UiSelect
                            v-model="planForm.niche"
                            :options="nicheFormOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="planForm.errors.niche" class="mt-1 text-xs text-rose-600">{{ planForm.errors.niche }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Badge</label>
                        <input
                            v-model="planForm.badge"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Popular"
                        >
                        <p v-if="planForm.errors.badge" class="mt-1 text-xs text-rose-600">{{ planForm.errors.badge }}</p>
                    </div>

                    <div class="md:col-span-2 xl:col-span-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subtítulo</label>
                        <input
                            v-model="planForm.subtitle"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Operação em crescimento"
                        >
                        <p v-if="planForm.errors.subtitle" class="mt-1 text-xs text-rose-600">{{ planForm.errors.subtitle }}</p>
                    </div>

                    <div class="md:col-span-2 xl:col-span-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resumo</label>
                        <textarea
                            v-model="planForm.summary"
                            rows="2"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Resumo exibido no card do plano"
                        />
                        <p v-if="planForm.errors.summary" class="mt-1 text-xs text-rose-600">{{ planForm.errors.summary }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valor mensal</label>
                        <input
                            v-model="planForm.price_monthly"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="0.00"
                        >
                        <p v-if="planForm.errors.price_monthly" class="mt-1 text-xs text-rose-600">{{ planForm.errors.price_monthly }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Limite de usuários</label>
                        <input
                            v-model="planForm.user_limit"
                            type="number"
                            min="1"
                            step="1"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Vazio para ilimitado"
                        >
                        <p v-if="planForm.errors.user_limit" class="mt-1 text-xs text-rose-600">{{ planForm.errors.user_limit }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Armazenamento (GB)</label>
                        <input
                            v-model="planForm.storage_limit_gb"
                            type="number"
                            min="1"
                            step="1"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Vazio para ilimitado"
                        >
                        <p v-if="planForm.errors.storage_limit_gb" class="mt-1 text-xs text-rose-600">{{ planForm.errors.storage_limit_gb }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Retenção de auditoria (dias)</label>
                        <input
                            v-model="planForm.audit_log_retention_days"
                            type="number"
                            min="1"
                            step="1"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Vazio para padrão"
                        >
                        <p v-if="planForm.errors.audit_log_retention_days" class="mt-1 text-xs text-rose-600">{{ planForm.errors.audit_log_retention_days }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ordem de exibição</label>
                        <input
                            v-model="planForm.tier_rank"
                            type="number"
                            min="0"
                            step="1"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="0"
                        >
                        <p v-if="planForm.errors.tier_rank" class="mt-1 text-xs text-rose-600">{{ planForm.errors.tier_rank }}</p>
                    </div>

                    <div class="md:col-span-2 xl:col-span-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Benefícios (1 por linha)</label>
                        <textarea
                            v-model="planForm.features_text"
                            rows="4"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Usuários admin: Até 5 admins&#10;Suporte: Prioritário"
                        />
                        <p v-if="planForm.errors.features_text" class="mt-1 text-xs text-rose-600">{{ planForm.errors.features_text }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mensagem de rodapé</label>
                        <textarea
                            v-model="planForm.footer_message"
                            rows="2"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Mensagem curta exibida no card"
                        />
                        <p v-if="planForm.errors.footer_message" class="mt-1 text-xs text-rose-600">{{ planForm.errors.footer_message }}</p>
                    </div>
                </div>

                <div v-if="currentPlanStep === 1" class="grid gap-2 md:grid-cols-3">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input v-model="planForm.is_active" type="checkbox" class="rounded border-slate-300">
                        Plano ativo
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input v-model="planForm.is_featured" type="checkbox" class="rounded border-slate-300">
                        Plano recomendado
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input v-model="planForm.show_on_landing" type="checkbox" class="rounded border-slate-300">
                        Exibir na landing
                    </label>
                </div>

                <div v-if="currentPlanStep === 2" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Módulos globais</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="module in planGlobalModules"
                                :key="`plan-global-${module.code}`"
                                class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300"
                                    :checked="isPlanModuleSelected(module.code)"
                                    @change="togglePlanModuleCode(module.code)"
                                >
                                <span>
                                    <span class="block font-semibold text-slate-800">{{ module.name }}</span>
                                    <span v-if="module.description" class="text-slate-500">{{ module.description }}</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Módulos específicos do nicho</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="module in planSpecificModules"
                                :key="`plan-specific-${module.code}`"
                                class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300"
                                    :checked="isPlanModuleSelected(module.code)"
                                    @change="togglePlanModuleCode(module.code)"
                                >
                                <span>
                                    <span class="block font-semibold text-slate-800">{{ module.name }}</span>
                                    <span v-if="module.description" class="text-slate-500">{{ module.description }}</span>
                                </span>
                            </label>
                        </div>
                        <p v-if="planForm.errors.module_codes" class="mt-2 text-xs text-rose-600">{{ planForm.errors.module_codes }}</p>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button
                            v-if="currentPlanStep > 1"
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="currentPlanStep = Math.max(1, currentPlanStep - 1)"
                        >
                            Voltar
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeModal"
                        >
                            Cancelar
                        </button>
                        <button
                            v-if="currentPlanStep < planWizardSteps.length"
                            type="button"
                            class="rounded-xl bg-slate-200 px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-300"
                            @click="currentPlanStep = Math.min(planWizardSteps.length, currentPlanStep + 1)"
                        >
                            Próximo
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="planForm.processing"
                            @click="submitPlan"
                        >
                            {{ planForm.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir plano"
            message="Tem certeza que deseja excluir este plano?"
            :item-label="planToDelete?.name ? `Plano: ${planToDelete.name}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="removePlan"
        />
    </AuthenticatedLayout>
</template>
