<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
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

const clearFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.niche = '';
    applyFilters();
};

const rows = computed(() => props.plans?.data ?? []);
const paginationLinks = computed(() => props.plans?.links ?? []);
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

const statsCards = computed(() => [
    { key: 'total', label: 'Planos', value: String(props.stats?.total ?? 0), icon: Layers, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Planos ativos', value: String(props.stats?.active ?? 0), icon: CircleCheckBig, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'commercial', label: 'Nicho comercio', value: String(props.stats?.commercial ?? 0), icon: Store, tone: 'bg-slate-100 text-slate-700' },
    { key: 'services', label: 'Nicho servicos', value: String(props.stats?.services ?? 0), icon: Briefcase, tone: 'bg-amber-100 text-amber-700' },
]);

const showModal = ref(false);
const editingPlan = ref(null);

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
    is_active: true,
    is_featured: false,
    show_on_landing: false,
});

const isEditing = computed(() => Boolean(editingPlan.value?.id));

const openCreate = () => {
    editingPlan.value = null;
    planForm.reset();
    planForm.clearErrors();
    planForm.niche = props.niches?.[0]?.value ?? 'commercial';
    planForm.tier_rank = 0;
    planForm.is_active = true;
    planForm.is_featured = false;
    planForm.show_on_landing = false;
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
    planForm.is_active = Boolean(plan.is_active);
    planForm.is_featured = Boolean(plan.is_featured);
    planForm.show_on_landing = Boolean(plan.show_on_landing);
    planForm.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingPlan.value = null;
};

const submitPlan = () => {
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

const removePlan = (plan) => {
    const confirmed = window.confirm(`Excluir o plano "${plan.name}"?`);
    if (!confirmed) return;

    router.delete(route('master.plans.destroy', plan.id), {
        preserveScroll: true,
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
    if (limit === null || limit === undefined || limit === '') return 'Usuarios ilimitados';

    return `${limit} usuario(s)`;
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
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl" :class="stat.tone">
                            <component :is="stat.icon" class="h-4 w-4" />
                        </span>
                    </div>
                </article>
            </div>

            <section class="rounded-[30px] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="h-4 w-4 text-slate-500" />
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Buscar plano por nome, slug, badge ou subtitulo"
                            class="w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <select
                            v-model="filterForm.niche"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                            @change="applyFilters"
                        >
                            <option value="">Todos nichos</option>
                            <option v-for="niche in props.niches" :key="`plan-filter-niche-${niche.value}`" :value="niche.value">
                                {{ niche.label }}
                            </option>
                        </select>
                        <select
                            v-model="filterForm.status"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                            @change="applyFilters"
                        >
                            <option value="">Todos</option>
                            <option value="active">Ativos</option>
                            <option value="inactive">Inativos</option>
                        </select>
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
                            Novo plano
                        </button>
                    </div>
                </div>

                <div class="mt-6 space-y-8">
                    <section
                        v-for="nicheGroup in groupedRows"
                        :key="`niche-group-${nicheGroup.value}`"
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
                                        {{ plan.summary || 'Plano flexivel para cada fase da operacao.' }}
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
                                        Nenhum beneficio configurado ainda.
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
                                                Landing
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
                                            @click="removePlan(plan)"
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
                                        {{ plan.footer_message || 'Use este plano para controlar acesso, limite e operacao.' }}
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
                        Nenhum nicho encontrado para segmentacao.
                    </div>
                </div>

                <PaginationLinks :links="paginationLinks" :min-links="4" />
            </section>
        </section>

        <Modal :show="showModal" max-width="2xl" @close="closeModal">
            <div class="space-y-4 bg-white p-6">
                <h3 class="text-base font-semibold text-slate-900">
                    {{ isEditing ? 'Editar plano' : 'Novo plano' }}
                </h3>

                <div class="grid gap-3 md:grid-cols-2">
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
                        <select
                            v-model="planForm.niche"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                        >
                            <option v-for="niche in props.niches" :key="`plan-form-niche-${niche.value}`" :value="niche.value">
                                {{ niche.label }}
                            </option>
                        </select>
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

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subtitulo</label>
                        <input
                            v-model="planForm.subtitle"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Operacao em crescimento"
                        >
                        <p v-if="planForm.errors.subtitle" class="mt-1 text-xs text-rose-600">{{ planForm.errors.subtitle }}</p>
                    </div>

                    <div class="md:col-span-2">
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
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Limite de usuarios</label>
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
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Retencao de auditoria (dias)</label>
                        <input
                            v-model="planForm.audit_log_retention_days"
                            type="number"
                            min="1"
                            step="1"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Vazio para padrao"
                        >
                        <p v-if="planForm.errors.audit_log_retention_days" class="mt-1 text-xs text-rose-600">{{ planForm.errors.audit_log_retention_days }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ordem de exibicao</label>
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

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Beneficios (1 por linha)</label>
                        <textarea
                            v-model="planForm.features_text"
                            rows="4"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Usuarios admin: Ate 5 admins&#10;Suporte: Prioritario"
                        />
                        <p v-if="planForm.errors.features_text" class="mt-1 text-xs text-rose-600">{{ planForm.errors.features_text }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mensagem de rodape</label>
                        <textarea
                            v-model="planForm.footer_message"
                            rows="2"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Mensagem curta exibida no card"
                        />
                        <p v-if="planForm.errors.footer_message" class="mt-1 text-xs text-rose-600">{{ planForm.errors.footer_message }}</p>
                    </div>
                </div>

                <div class="grid gap-2 md:grid-cols-3">
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
                        :disabled="planForm.processing"
                        @click="submitPlan"
                    >
                        {{ planForm.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
