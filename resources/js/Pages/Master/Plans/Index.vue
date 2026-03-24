<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { AlertTriangle, Briefcase, ChevronDown, ChevronUp, CircleCheckBig, Eye, Plus, Save, Store, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    plans: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    niches: { type: Array, default: () => [] },
    moduleCatalog: { type: Array, default: () => [] },
    defaultModuleCodesByNiche: { type: Object, default: () => ({}) },
});

const page = usePage();
const flashStatus = computed(() => page.props.flash?.status ?? null);
const generalError = computed(() => page.props.errors?.general ?? null);
const activeNicheTab = ref(props.filters?.niche ?? props.niches?.[0]?.value ?? 'commercial');
const saveForm = useForm({});
const bulkForm = useForm({ plans: [] });
const deleteForm = useForm({});
const savingTempId = ref('');
const deletingId = ref(null);
const inlineErrors = ref({});
const bulkSaving = ref(false);
const bulkSaveError = ref('');
const bulkSaveSuccess = ref('');
const modulesModalOpen = ref(false);
const modulesModalPlanId = ref('');
const deleteModalOpen = ref(false);
const deletePlanTempId = ref('');

const tempId = (prefix = 'plan') => `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
const num = (value) => (value === null || value === undefined || String(value).trim() === '' ? '' : String(value));
const normalizeCode = (value) => String(value ?? '').trim().toLowerCase();
const MODULE_CODE_PDV = 'pdv';
const nicheValues = computed(() => (props.niches ?? []).map((item) => String(item.value ?? '').trim().toLowerCase()).filter(Boolean));
const defaultNiche = computed(() => nicheValues.value[0] ?? 'commercial');

const normalizeNiche = (value) => {
    const safe = String(value ?? '').trim().toLowerCase();
    return nicheValues.value.includes(safe) ? safe : defaultNiche.value;
};

const knownScopes = ['global', 'niche', 'specific'];
const businessTypeLabels = {
    store: 'Loja',
    confectionery: 'Confeitaria',
    barbershop: 'Barbearia',
    auto_electric: 'Autoelétrica',
    mechanic: 'Mecânica',
    accounting: 'Contabilidade',
    general_services: 'Serviços gerais',
};

const businessTypesByNiche = {
    commercial: ['store', 'confectionery'],
    services: ['barbershop', 'auto_electric', 'mechanic', 'accounting', 'general_services'],
};

const businessTypesForNiche = (niche) => {
    const safeNiche = normalizeNiche(niche);
    return businessTypesByNiche[safeNiche] ?? [];
};

const defaultBusinessTypeForNiche = (niche) => businessTypesForNiche(niche)[0] ?? '';

const normalizeBusinessTypeForNiche = (niche, value) => {
    const allowed = businessTypesForNiche(niche);
    const safe = String(value ?? '').trim().toLowerCase();
    return allowed.includes(safe) ? safe : (allowed[0] ?? '');
};

const normalizeScope = (value) => {
    const safe = String(value ?? '').trim().toLowerCase();
    return knownScopes.includes(safe) ? safe : 'specific';
};

const normalizeBusinessTypes = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return Array.from(new Set(
        value
            .map((item) => String(item ?? '').trim().toLowerCase())
            .filter(Boolean)
    ));
};

const labelForBusinessType = (businessType) => {
    const normalized = String(businessType ?? '').trim().toLowerCase();
    if (!normalized) return '';
    return businessTypeLabels[normalized] ?? normalized.replaceAll('_', ' ');
};

const buildFeature = () => ({
    label: '',
    value: '',
    icon: 'CheckCircle2',
    enabled: true,
});

const normalizeFeature = (feature) => {
    if (!feature || typeof feature !== 'object') return null;
    const label = String(feature.label ?? '').trim();
    const value = String(feature.value ?? '').trim();
    if (label === '' && value === '') return null;

    return {
        label: label || value,
        value,
        icon: 'CheckCircle2',
        enabled: feature.enabled !== false,
    };
};

const featuresFromText = (text) => {
    return String(text ?? '')
        .split(/\r?\n/)
        .map((line) => String(line ?? '').trim())
        .filter(Boolean)
        .map((line) => {
            if (line.includes(':')) {
                const [label, ...rest] = line.split(':');
                return normalizeFeature({
                    label: String(label ?? '').trim(),
                    value: rest.join(':').trim(),
                });
            }

            return normalizeFeature({ label: line, value: '' });
        })
        .filter(Boolean);
};

const normalizeFeatures = (featuresInput, featuresText) => {
    if (Array.isArray(featuresInput) && featuresInput.length > 0) {
        const normalized = featuresInput.map((feature) => normalizeFeature(feature)).filter(Boolean);
        if (normalized.length > 0) {
            return normalized;
        }
    }

    return featuresFromText(featuresText);
};

const catalog = computed(() => {
    return (props.moduleCatalog ?? [])
        .map((item) => {
            const code = normalizeCode(item?.code);
            if (!code) return null;

            const rawNiche = String(item?.niche ?? '').trim().toLowerCase();
            const niche = rawNiche && nicheValues.value.includes(rawNiche) ? rawNiche : null;
            const scope = normalizeScope(item?.scope);
            const business_types = normalizeBusinessTypes(item?.business_types);

            return {
                code,
                name: String(item?.name ?? code).trim() || code,
                description: String(item?.description ?? '').trim(),
                scope,
                niche,
                business_types,
                is_default: Boolean(item?.is_default),
            };
        })
        .filter(Boolean);
});

const moduleAllowedForNiche = (module, niche) => {
    const safeNiche = normalizeNiche(niche);
    const code = normalizeCode(module?.code);

    if (safeNiche === 'services' && code === MODULE_CODE_PDV) {
        return false;
    }

    return module?.niche === null || module?.niche === safeNiche;
};

const modulesForNiche = (niche) => {
    const safeNiche = normalizeNiche(niche);
    return catalog.value.filter((item) => moduleAllowedForNiche(item, safeNiche));
};

const allowedModuleCodes = (niche) => modulesForNiche(niche).map((item) => item.code);

const defaultModuleCodes = (niche) => {
    const safeNiche = normalizeNiche(niche);
    const allowed = allowedModuleCodes(safeNiche);
    const raw = props.defaultModuleCodesByNiche?.[safeNiche];
    const fromProps = Array.isArray(raw)
        ? raw.map((code) => normalizeCode(code)).filter((code) => allowed.includes(code))
        : [];

    if (fromProps.length > 0) {
        return Array.from(new Set(fromProps));
    }

    const fromCatalog = modulesForNiche(safeNiche)
        .filter((item) => item.is_default)
        .map((item) => item.code);

    return Array.from(new Set(fromCatalog));
};

const resolveModuleCodes = (niche, codes, withDefaults = false) => {
    const safeNiche = normalizeNiche(niche);
    const allowed = allowedModuleCodes(safeNiche);
    const selected = Array.isArray(codes)
        ? Array.from(
            new Set(
                codes
                    .map((code) => normalizeCode(code))
                    .filter((code) => code !== '' && allowed.includes(code))
            )
        )
        : [];

    if (selected.length > 0 || !withDefaults) {
        return selected;
    }

    return defaultModuleCodes(safeNiche);
};

const normalizePlan = (plan, index) => {
    const niche = normalizeNiche(plan?.niche ?? defaultNiche.value);
    const moduleBusinessType = normalizeBusinessTypeForNiche(
        niche,
        plan?.module_business_type ?? defaultBusinessTypeForNiche(niche)
    );

    return {
        id: plan?.id ?? null,
        temp_id: plan?.id ? `plan-${plan.id}-${index}` : tempId('draft'),
        isDraft: !plan?.id,
        niche,
        niche_label: plan?.niche_label ?? '',
        name: plan?.name ?? '',
        slug: plan?.slug ?? '',
        badge: plan?.badge ?? '',
        subtitle: plan?.subtitle ?? '',
        summary: plan?.summary ?? '',
        footer_message: plan?.footer_message ?? '',
        price_monthly: num(plan?.price_monthly),
        user_limit: num(plan?.user_limit ?? plan?.max_admin_users),
        storage_limit_gb: num(plan?.storage_limit_gb),
        audit_log_retention_days: num(plan?.audit_log_retention_days),
        tier_rank: Number(plan?.tier_rank ?? index + 1),
        features: normalizeFeatures(plan?.features, plan?.features_text),
        module_codes: resolveModuleCodes(niche, plan?.module_codes, true),
        module_business_type: moduleBusinessType,
        is_active: Boolean(plan?.is_active ?? true),
        is_featured: Boolean(plan?.is_featured ?? false),
        show_on_landing: Boolean(plan?.show_on_landing ?? false),
        active_contractors_count: Number(plan?.active_contractors_count ?? 0),
    };
};

const orderedPlans = (items) => {
    return [...items].sort((a, b) => {
        const rankDiff = Number(a.tier_rank ?? 0) - Number(b.tier_rank ?? 0);
        if (rankDiff !== 0) return rankDiff;

        return String(a.name ?? '').localeCompare(String(b.name ?? ''));
    });
};

const draftPlan = (niche = 'commercial') => normalizePlan({ niche }, 0);
const plansState = ref(orderedPlans((props.plans?.data ?? []).map(normalizePlan)));

watch(
    () => props.plans?.data,
    (next) => { plansState.value = orderedPlans((next ?? []).map(normalizePlan)); },
    { deep: true },
);

const nicheTabs = computed(() => (props.niches ?? []).map((n) => ({
    ...n,
    icon: n.value === 'services' ? Briefcase : Store,
    count: plansState.value.filter((p) => p.niche === n.value).length,
})));

const nicheOptions = computed(() => (props.niches ?? []).map((n) => ({ value: n.value, label: n.label })));
const paginationLinks = computed(() => props.plans?.links ?? []);

const visiblePlans = computed(() => {
    return plansState.value.filter((p) => (!activeNicheTab.value || p.niche === activeNicheTab.value));
});

const limitText = (plan) => (String(plan?.user_limit ?? '').trim() ? `${plan.user_limit} usuário(s)` : 'Usuários ilimitados');
const money = (value) => (String(value ?? '').trim()
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value))
    : 'Sob consulta');
const intOrNull = (value) => (String(value ?? '').trim() && Number(value) > 0 ? Math.trunc(Number(value)) : null);
const decOrNull = (value) => (String(value ?? '').trim() && Number(value) >= 0 ? Number(value) : null);

const featuresForPayload = (plan) => {
    return (Array.isArray(plan.features) ? plan.features : [])
        .map((feature) => normalizeFeature(feature))
        .filter(Boolean);
};

const featuresAsText = (plan) => {
    return featuresForPayload(plan)
        .map((feature) => (feature.value ? `${feature.label}: ${feature.value}` : feature.label))
        .join('\n');
};

const featurePreview = (plan) => featuresForPayload(plan).filter((feature) => feature.enabled !== false).slice(0, 6);
const selectedModuleCount = (plan) => resolveModuleCodes(plan.niche, plan.module_codes, false).length;
const modulesForPlan = (plan) => modulesForNiche(plan.niche);
const scopeOrder = { global: 0, niche: 1, specific: 2 };

const sortedModules = (modules) => {
    return [...modules].sort((a, b) => {
        const scopeDiff = (scopeOrder[a.scope] ?? 99) - (scopeOrder[b.scope] ?? 99);
        if (scopeDiff !== 0) return scopeDiff;
        return String(a.name ?? '').localeCompare(String(b.name ?? ''));
    });
};

const groupedModulesForPlan = (plan) => {
    const modules = sortedModules(modulesForPlan(plan));
    const selectedBusinessType = normalizeBusinessTypeForNiche(plan.niche, plan.module_business_type);
    const specificModules = modules.filter((module) => {
        if (module.scope !== 'specific') return false;
        const businessTypes = Array.isArray(module.business_types) ? module.business_types : [];
        if (!businessTypes.length) return true;

        return businessTypes.includes(selectedBusinessType);
    });

    return [
        {
            key: 'global',
            title: 'Módulos globais',
            description: 'Recursos base válidos para qualquer nicho.',
            modules: modules.filter((module) => module.scope === 'global'),
        },
        {
            key: 'niche',
            title: 'Módulos do nicho',
            description: 'Recursos gerais do nicho selecionado para o plano.',
            modules: modules.filter((module) => module.scope === 'niche'),
        },
        {
            key: 'specific',
            title: 'Módulos por tipo de negócio',
            description: 'Recursos específicos para tipos de negócio do nicho.',
            modules: specificModules,
        },
    ].filter((group) => group.key === 'specific' || group.modules.length > 0);
};

const businessTypeOptionsForPlan = (plan) => {
    return businessTypesForNiche(plan?.niche).map((businessType) => ({
        value: businessType,
        label: labelForBusinessType(businessType),
    }));
};

const moduleBusinessTypeLabels = (module) => {
    const values = Array.isArray(module?.business_types) ? module.business_types : [];
    return values.map((businessType) => labelForBusinessType(businessType)).filter(Boolean);
};

const payload = (plan) => ({
    niche: normalizeNiche(plan.niche),
    name: String(plan.name ?? '').trim(),
    slug: String(plan.slug ?? '').trim(),
    badge: String(plan.badge ?? '').trim(),
    subtitle: String(plan.subtitle ?? '').trim(),
    summary: String(plan.summary ?? '').trim(),
    footer_message: String(plan.footer_message ?? '').trim(),
    price_monthly: decOrNull(plan.price_monthly),
    user_limit: intOrNull(plan.user_limit),
    storage_limit_gb: intOrNull(plan.storage_limit_gb),
    audit_log_retention_days: intOrNull(plan.audit_log_retention_days),
    tier_rank: intOrNull(plan.tier_rank) ?? 0,
    features: featuresForPayload(plan),
    features_text: featuresAsText(plan),
    module_codes: resolveModuleCodes(plan.niche, plan.module_codes, false),
    is_active: Boolean(plan.is_active),
    is_featured: Boolean(plan.is_featured),
    show_on_landing: Boolean(plan.show_on_landing),
});

const setErrors = (plan, errors) => { inlineErrors.value = { ...inlineErrors.value, [plan.temp_id]: errors }; };
const clearErrors = (plan) => { const next = { ...inlineErrors.value }; delete next[plan.temp_id]; inlineErrors.value = next; };
const planErrors = (plan) => inlineErrors.value[plan.temp_id] ?? {};
const clearAllInlineErrors = () => { inlineErrors.value = {}; };
const payloadWithId = (plan) => ({ id: plan.id ?? null, ...payload(plan) });

const addDraft = () => {
    bulkSaveError.value = '';
    bulkSaveSuccess.value = '';
    clearAllInlineErrors();
    plansState.value.unshift({
        ...draftPlan(activeNicheTab.value || defaultNiche.value),
        temp_id: tempId('draft'),
        isDraft: true,
    });
};

const discardDraft = (plan) => { plansState.value = plansState.value.filter((row) => row.temp_id !== plan.temp_id); };

const submitPlan = (plan) => {
    return new Promise((resolve) => {
        bulkSaveError.value = '';
        bulkSaveSuccess.value = '';
        clearErrors(plan);

        if (!String(plan.name ?? '').trim()) {
            setErrors(plan, { name: 'Informe o nome do plano.' });
            resolve(false);
            return;
        }

        savingTempId.value = plan.temp_id;
        saveForm.transform(() => payload(plan));

        const options = {
            preserveScroll: true,
            onError: (errors) => {
                setErrors(plan, errors);
                resolve(false);
            },
            onSuccess: () => {
                clearErrors(plan);
                resolve(true);
            },
            onFinish: () => {
                savingTempId.value = '';
                saveForm.transform((data) => data);
            },
        };

        if (plan.isDraft) {
            saveForm.post(route('master.plans.store'), options);
            return;
        }

        saveForm.put(route('master.plans.update', plan.id), options);
    });
};

const savePlan = async (plan) => {
    await submitPlan(plan);
};

const applyBulkErrors = (errors, orderedList) => {
    const mapped = {};

    Object.entries(errors ?? {}).forEach(([key, message]) => {
        const match = String(key).match(/^plans\.(\d+)\.(.+)$/);
        if (!match) return;

        const planIndex = Number(match[1]);
        const fieldName = match[2];
        const targetPlan = orderedList[planIndex];
        if (!targetPlan) return;

        if (!mapped[targetPlan.temp_id]) {
            mapped[targetPlan.temp_id] = {};
        }

        mapped[targetPlan.temp_id][fieldName] = message;
    });

    inlineErrors.value = mapped;
};

const saveAllPlans = async () => {
    if (bulkSaving.value) return;

    bulkSaving.value = true;
    bulkSaveError.value = '';
    bulkSaveSuccess.value = '';
    clearAllInlineErrors();

    const plansSnapshot = [...plansState.value];
    bulkForm.transform(() => ({
        plans: plansSnapshot.map((plan) => payloadWithId(plan)),
    }));

    bulkForm.put(route('master.plans.bulk-update'), {
        preserveScroll: true,
        onError: (errors) => {
            applyBulkErrors(errors, plansSnapshot);
            bulkSaveError.value = 'Falha ao salvar todos os planos. Corrija os campos destacados e tente novamente.';
        },
        onSuccess: () => {
            bulkSaveSuccess.value = 'Todos os planos foram salvos com sucesso.';
        },
        onFinish: () => {
            bulkSaving.value = false;
            bulkForm.transform((data) => data);
        },
    });
};

const openDeleteModal = (plan) => {
    bulkSaveError.value = '';
    bulkSaveSuccess.value = '';
    deletePlanTempId.value = plan?.temp_id ?? '';
    deleteModalOpen.value = deletePlanTempId.value !== '';
};

const closeDeleteModal = () => {
    deleteModalOpen.value = false;
    deletePlanTempId.value = '';
};

const planToDelete = computed(() => {
    if (!deletePlanTempId.value) return null;
    return plansState.value.find((plan) => plan.temp_id === deletePlanTempId.value) ?? null;
});

const confirmDeletePlan = () => {
    const plan = planToDelete.value;
    if (!plan) {
        closeDeleteModal();
        return;
    }

    clearErrors(plan);
    if (!plan?.id) {
        discardDraft(plan);
        closeDeleteModal();
        return;
    }

    if (plan.active_contractors_count > 0) {
        return;
    }

    deletingId.value = plan.id;
    deleteForm.delete(route('master.plans.destroy', plan.id), {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
            closeDeleteModal();
        },
    });
};

const addFeature = (plan) => {
    if (!Array.isArray(plan.features)) {
        plan.features = [];
    }
    plan.features.push(buildFeature());
};

const removeFeature = (plan, index) => {
    if (!Array.isArray(plan.features)) return;
    plan.features.splice(index, 1);
};

const moveFeature = (plan, fromIndex, toIndex) => {
    if (!Array.isArray(plan.features)) return;
    if (toIndex < 0 || toIndex >= plan.features.length) return;
    const [moved] = plan.features.splice(fromIndex, 1);
    plan.features.splice(toIndex, 0, moved);
};

const isModuleSelected = (plan, code) => {
    return resolveModuleCodes(plan.niche, plan.module_codes, false).includes(normalizeCode(code));
};

const toggleModule = (plan, code) => {
    const normalizedCode = normalizeCode(code);
    if (!normalizedCode) return;

    const selected = resolveModuleCodes(plan.niche, plan.module_codes, false);
    if (selected.includes(normalizedCode)) {
        plan.module_codes = selected.filter((item) => item !== normalizedCode);
        return;
    }

    plan.module_codes = Array.from(new Set([...selected, normalizedCode]));
};

const restoreDefaultModules = (plan) => {
    plan.module_codes = defaultModuleCodes(plan.niche);
};

const onPlanNicheChange = (plan, value) => {
    const nextNiche = normalizeNiche(value);
    plan.niche = nextNiche;
    plan.module_business_type = normalizeBusinessTypeForNiche(nextNiche, plan.module_business_type);
    plan.module_codes = resolveModuleCodes(nextNiche, plan.module_codes, true);
};

const modulesModalPlan = computed(() => {
    return plansState.value.find((plan) => plan.temp_id === modulesModalPlanId.value) ?? null;
});

const modulesModalGroups = computed(() => {
    if (!modulesModalPlan.value) {
        return [];
    }

    return groupedModulesForPlan(modulesModalPlan.value);
});

const openModulesModal = (plan) => {
    if (plan) {
        plan.module_business_type = normalizeBusinessTypeForNiche(plan.niche, plan.module_business_type);
    }
    modulesModalPlanId.value = plan?.temp_id ?? '';
    modulesModalOpen.value = modulesModalPlanId.value !== '';
};

const closeModulesModal = () => {
    modulesModalOpen.value = false;
    modulesModalPlanId.value = '';
};
</script>

<template>
    <Head title="Planos e Assinaturas" />
    <AuthenticatedLayout area="master" header-variant="compact" header-title="Planos e Assinaturas">
        <template #header>
            <div class="space-y-3">
                <h1 class="text-xl font-semibold text-slate-900">Planos e Assinaturas</h1>
                <div class="plans-tabs-shell">
                    <div class="plans-tabs-track">
                        <button
                            v-for="tab in nicheTabs"
                            :key="`tab-${tab.value}`"
                            type="button"
                            class="plans-tab"
                            :class="activeNicheTab === tab.value ? 'is-active' : ''"
                            @click="activeNicheTab = tab.value"
                        >
                            <component :is="tab.icon" class="h-4 w-4" />
                            {{ tab.label }}
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600" :class="activeNicheTab === tab.value ? 'bg-white text-slate-700' : ''">
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <section class="space-y-4">
            <div v-if="flashStatus" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ flashStatus }}</div>
            <div v-if="generalError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ generalError }}</div>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="addDraft">
                        <Plus class="h-3.5 w-3.5" /> Novo plano
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="bulkSaving || bulkForm.processing || saveForm.processing" @click="saveAllPlans">
                        <Save class="h-3.5 w-3.5" /> {{ bulkSaving ? 'Salvando planos...' : 'Salvar todos os planos' }}
                    </button>
                </div>

                <div v-if="bulkSaveSuccess" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    {{ bulkSaveSuccess }}
                </div>
                <div v-if="bulkSaveError" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    {{ bulkSaveError }}
                </div>

                <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="plan in visiblePlans" :key="`${plan.temp_id}-${plan.id ?? 'draft'}`" class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm" :class="plan.is_featured ? 'ring-1 ring-emerald-200' : ''">
                        <div class="mb-3 flex items-center justify-between gap-2 text-[10px] font-semibold uppercase tracking-wide">
                            <div class="flex items-center gap-1">
                                <span v-if="plan.isDraft" class="rounded-full bg-slate-900 px-2 py-1 text-white">Novo</span>
                                <span v-if="!plan.is_active" class="rounded-full bg-rose-100 px-2 py-1 text-rose-700">Inativo</span>
                                <span v-if="plan.is_featured" class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-700">Recomendado</span>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ plan.niche_label || plan.niche }}</span>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <input v-model="plan.name" type="text" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm font-semibold text-slate-900" placeholder="Nome do plano">
                            <div class="mt-2 grid gap-2 sm:grid-cols-[1fr,110px]">
                                <input v-model="plan.subtitle" type="text" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700" placeholder="Subtítulo">
                                <input v-model="plan.badge" type="text" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700" placeholder="Badge">
                            </div>
                            <textarea v-model="plan.summary" rows="2" class="mt-2 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700" placeholder="Resumo do plano" />
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <BrlMoneyInput v-model="plan.price_monthly" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm font-semibold text-slate-800" placeholder="Valor mensal" />
                                <input v-model="plan.user_limit" type="number" min="1" step="1" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm font-semibold text-slate-800" placeholder="Limite de usuários">
                                <input v-model="plan.storage_limit_gb" type="number" min="1" step="1" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm font-semibold text-slate-800" placeholder="Storage (GB)">
                                <input v-model="plan.audit_log_retention_days" type="number" min="1" step="1" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm font-semibold text-slate-800" placeholder="Auditoria (dias)">
                            </div>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                <input v-model="plan.tier_rank" type="number" min="0" step="1" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm font-semibold text-slate-800" placeholder="Ordem">
                                <div class="sm:col-span-2">
                                    <UiSelect v-model="plan.niche" :options="nicheOptions" button-class="w-full text-sm" @update:model-value="onPlanNicheChange(plan, $event)" />
                                </div>
                            </div>
                            <div class="mt-2 grid gap-2 text-xs sm:grid-cols-1">
                                <label class="inline-flex items-center gap-1.5"><input v-model="plan.is_active" type="checkbox" class="rounded border-slate-300">Ativo</label>
                                <label class="inline-flex items-center gap-1.5"><input v-model="plan.is_featured" type="checkbox" class="rounded border-slate-300">Recomendado</label>
                                <label class="inline-flex items-center gap-1.5"><input v-model="plan.show_on_landing" type="checkbox" class="rounded border-slate-300">Landing</label>
                            </div>

                            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Itens do plano</p>
                                        <p class="text-[11px] text-slate-500">Cadastre benefício por benefício e ajuste a ordem.</p>
                                    </div>
                                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" @click="addFeature(plan)">
                                        <Plus class="h-3.5 w-3.5" /> Adicionar item
                                    </button>
                                </div>

                                <div v-if="!plan.features.length" class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
                                    Nenhum item cadastrado.
                                </div>

                                <div v-for="(feature, index) in plan.features" :key="`${plan.temp_id}-feature-${index}`" class="mt-2 rounded-lg border border-slate-200 bg-slate-50/70 p-2.5">
                                    <div class="flex items-start gap-2">
                                        <div class="flex-1 space-y-2">
                                            <input v-model="feature.label" type="text" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700" placeholder="Título do item">
                                            <input v-model="feature.value" type="text" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700" placeholder="Descrição do item (opcional)">
                                            <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-600"><input v-model="feature.enabled" type="checkbox" class="rounded border-slate-300">Ativo no plano</label>
                                        </div>
                                        <div class="flex shrink-0 flex-col gap-1">
                                            <button type="button" class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white p-1.5 text-slate-600 hover:bg-slate-50 disabled:opacity-40" :disabled="index === 0" title="Subir item" @click="moveFeature(plan, index, index - 1)">
                                                <ChevronUp class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white p-1.5 text-slate-600 hover:bg-slate-50 disabled:opacity-40" :disabled="index === plan.features.length - 1" title="Descer item" @click="moveFeature(plan, index, index + 1)">
                                                <ChevronDown class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" class="inline-flex items-center justify-center rounded-md border border-rose-200 bg-white p-1.5 text-rose-600 hover:bg-rose-50" title="Remover item" @click="removeFeature(plan, index)">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Módulos do plano</p>
                                    <p class="text-[11px] text-slate-500">Selecionados: {{ selectedModuleCount(plan) }}</p>
                                </div>
                                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" @click="openModulesModal(plan)">
                                    <Eye class="h-3.5 w-3.5" /> Ver e editar
                                </button>
                            </div>

                            <textarea v-model="plan.footer_message" rows="2" class="mt-2 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-700" placeholder="Mensagem de rodapé (opcional)" />
                        </div>

                        <div class="mt-3 rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-t-4 p-4" :class="plan.is_featured ? 'border-emerald-500' : 'border-slate-700'">
                                <div class="flex items-start justify-between gap-2">
                                    <h5 class="text-base font-bold" :class="plan.is_featured ? 'text-emerald-700' : 'text-slate-800'">{{ plan.name || 'Plano sem nome' }}</h5>
                                    <span v-if="plan.badge" class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700">{{ plan.badge }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">{{ plan.subtitle || limitText(plan) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ plan.summary || 'Plano flexível para cada fase da operação.' }}</p>
                                <h4 class="my-3 text-2xl font-bold" :class="plan.is_featured ? 'text-emerald-700' : 'text-slate-800'">
                                    {{ money(plan.price_monthly) }} <span class="text-sm font-medium text-slate-500">/ mês</span>
                                </h4>
                                <ul class="space-y-1.5 text-xs text-slate-700">
                                    <li v-for="(feature, index) in featurePreview(plan)" :key="`${plan.temp_id}-${index}`" class="flex items-start gap-2">
                                        <CircleCheckBig class="mt-0.5 h-3.5 w-3.5 text-emerald-600" />
                                        <span>{{ feature.label }}<span v-if="feature.value">: {{ feature.value }}</span></span>
                                    </li>
                                </ul>
                                <p v-if="!featurePreview(plan).length" class="text-xs text-slate-500">Nenhum item configurado ainda.</p>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-2">
                            <button type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saveForm.processing || savingTempId === plan.temp_id" @click="savePlan(plan)">
                                <Save class="h-3.5 w-3.5" /> {{ saveForm.processing && savingTempId === plan.temp_id ? 'Salvando...' : 'Salvar plano' }}
                            </button>
                            <button type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="deleteForm.processing || deletingId === plan.id" @click="openDeleteModal(plan)">
                                <Trash2 class="h-3.5 w-3.5" /> {{ plan.isDraft ? 'Descartar rascunho' : 'Remover plano' }}
                            </button>
                        </div>

                        <div v-if="Object.keys(planErrors(plan)).length" class="mt-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2">
                            <p v-for="(message, key) in planErrors(plan)" :key="`${plan.temp_id}-error-${key}`" class="text-xs text-rose-700">{{ message }}</p>
                        </div>
                        <p v-if="!plan.isDraft && plan.active_contractors_count > 0" class="mt-2 text-[11px] font-semibold text-amber-700">{{ plan.active_contractors_count }} contratante(s) ativo(s) neste plano.</p>
                    </article>

                    <div v-if="!visiblePlans.length" class="col-span-full rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                        Nenhum plano encontrado para este filtro.
                    </div>
                </div>

                <PaginationLinks :links="paginationLinks" :min-links="4" />
            </section>
        </section>

        <Modal :show="modulesModalOpen" max-width="3xl" @close="closeModulesModal">
            <div class="space-y-4 px-6 py-6 sm:px-8 sm:py-8">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Módulos do plano</p>
                        <h3 class="text-lg font-semibold text-slate-900">{{ modulesModalPlan?.name || 'Plano' }}</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Selecione os módulos habilitados para este plano.
                        </p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModulesModal">
                        Fechar
                    </button>
                </div>

                <div v-if="modulesModalPlan && modulesModalGroups.length" class="space-y-4">
                    <section class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Tipo de negócio</p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Os módulos específicos abaixo são filtrados conforme o tipo de negócio selecionado.
                        </p>
                        <UiSelect
                            v-model="modulesModalPlan.module_business_type"
                            :options="businessTypeOptionsForPlan(modulesModalPlan)"
                            button-class="mt-2 w-full text-sm"
                        />
                    </section>

                    <section v-for="group in modulesModalGroups" :key="`module-group-${group.key}`" class="space-y-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ group.title }}</p>
                            <p class="text-[11px] text-slate-500">{{ group.description }}</p>
                        </div>
                        <div v-if="group.modules.length" class="grid gap-2 sm:grid-cols-2">
                            <label v-for="module in group.modules" :key="`module-modal-${modulesModalPlan.temp_id}-${module.code}`" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                <span class="flex items-start gap-2">
                                    <input type="checkbox" class="mt-0.5 rounded border-slate-300" :checked="isModuleSelected(modulesModalPlan, module.code)" @change="toggleModule(modulesModalPlan, module.code)">
                                    <span class="space-y-1">
                                        <span class="block font-semibold text-slate-800">{{ module.name }}</span>
                                        <span v-if="module.description" class="block text-[11px] text-slate-500">{{ module.description }}</span>
                                        <span v-if="module.scope === 'specific' && moduleBusinessTypeLabels(module).length" class="flex flex-wrap gap-1">
                                            <span v-for="businessType in moduleBusinessTypeLabels(module)" :key="`module-type-${module.code}-${businessType}`" class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                                {{ businessType }}
                                            </span>
                                        </span>
                                    </span>
                                </span>
                            </label>
                        </div>
                        <p v-else class="rounded-lg border border-dashed border-slate-200 bg-white px-3 py-2 text-[11px] text-slate-500">
                            Nenhum módulo específico disponível para este tipo de negócio.
                        </p>
                    </section>
                </div>

                <p v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                    Não há módulos ativos para este nicho.
                </p>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button
                        v-if="modulesModalPlan"
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="restoreDefaultModules(modulesModalPlan)"
                    >
                        Restaurar padrão
                    </button>
                    <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800" @click="closeModulesModal">
                        Concluir
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="deleteModalOpen" max-width="lg" @close="closeDeleteModal">
            <div class="space-y-5 px-6 py-6 sm:px-8 sm:py-8">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
                        <AlertTriangle class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ planToDelete?.isDraft ? 'Descartar rascunho' : 'Remover plano' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ planToDelete?.isDraft ? 'O rascunho será removido da tela.' : 'Esta ação remove o plano permanentemente.' }}
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Plano selecionado</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">{{ planToDelete?.name || 'Plano sem nome' }}</p>
                    <p v-if="!planToDelete?.isDraft" class="mt-1 text-xs text-slate-500">
                        Contratantes ativos: {{ planToDelete?.active_contractors_count ?? 0 }}
                    </p>
                </div>

                <p v-if="!planToDelete?.isDraft && (planToDelete?.active_contractors_count ?? 0) > 0" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-700">
                    Não é possível remover este plano porque existem contratantes vinculados.
                </p>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50" @click="closeDeleteModal">
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-rose-300"
                        :disabled="deleteForm.processing || (!planToDelete?.isDraft && (planToDelete?.active_contractors_count ?? 0) > 0)"
                        @click="confirmDeletePlan"
                    >
                        <Trash2 class="h-4 w-4" />
                        {{ planToDelete?.isDraft ? 'Descartar' : 'Remover plano' }}
                    </button>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.plans-tabs-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.plans-tabs-shell::-webkit-scrollbar {
    height: 6px;
}

.plans-tabs-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.plans-tabs-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
}

.plans-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid transparent;
    border-radius: 0.72rem;
    min-height: 38px;
    padding: 0.6rem 0.95rem;
    color: #334155;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.plans-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.plans-tab.is-active {
    border-color: rgba(15, 23, 42, 0.24);
    background: #0f172a;
    color: #ffffff;
}
</style>
