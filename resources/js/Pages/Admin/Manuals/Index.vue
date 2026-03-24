<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useBranding } from '@/branding';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    LayoutDashboard,
    Globe2,
    Layers3,
    BriefcaseBusiness,
    ClipboardCheck,
    CheckCircle2,
    CircleAlert,
    ExternalLink,
} from 'lucide-vue-next';

const props = defineProps({
    initialTab: {
        type: String,
        default: 'overview',
    },
    manualContext: {
        type: Object,
        default: () => ({
            contractor_name: 'Operação atual',
            niche_label: 'Comércio',
            business_type_label: 'Loja',
            plan_name: 'Sem plano',
            enabled_modules_count: 0,
            group_counts: { global: 0, niche: 0, business: 0 },
        }),
    },
    manualGroups: {
        type: Object,
        default: () => ({ global: [], niche: [], business: [] }),
    },
    operationChecklist: {
        type: Object,
        default: () => ({ essentials: [], niche: [], security: [] }),
    },
    businessPlaybook: {
        type: Object,
        default: () => ({
            title: 'Playbook operacional',
            summary: '',
            daily: [],
            weekly: [],
            monthly: [],
            alerts: [],
        }),
    },
    quickLinks: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const { normalizeHex, withAlpha, secondaryColor } = useBranding();
const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const tabAccentColor = computed(() =>
    normalizeHex(currentContractor.value?.brand_primary_color || '', secondaryColor.value),
);
const manualStyles = computed(() => ({
    '--manual-tab-active': tabAccentColor.value,
    '--manual-tab-active-soft': withAlpha(tabAccentColor.value, 0.12),
    '--manual-tab-active-border': withAlpha(tabAccentColor.value, 0.28),
}));

const allowedTabs = new Set(['overview', 'global_modules', 'niche_modules', 'business_modules', 'playbook']);
const activeTab = ref(allowedTabs.has(props.initialTab) ? props.initialTab : 'overview');

watch(
    () => props.initialTab,
    (tab) => {
        activeTab.value = allowedTabs.has(tab) ? tab : 'overview';
    },
);

const setActiveTab = (tab) => {
    if (!allowedTabs.has(tab) || activeTab.value === tab) return;

    activeTab.value = tab;

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState(window.history.state, '', url.toString());
    }
};

const tabs = computed(() => [
    { key: 'overview', label: 'Visão geral', icon: LayoutDashboard },
    { key: 'global_modules', label: `Módulos globais (${props.manualContext?.group_counts?.global ?? 0})`, icon: Globe2 },
    { key: 'niche_modules', label: `Módulos do nicho (${props.manualContext?.group_counts?.niche ?? 0})`, icon: Layers3 },
    { key: 'business_modules', label: `Tipo de negócio (${props.manualContext?.group_counts?.business ?? 0})`, icon: BriefcaseBusiness },
    { key: 'playbook', label: 'Playbook', icon: ClipboardCheck },
]);

const moduleTabCatalog = {
    global_modules: {
        key: 'global',
        title: 'Módulos globais da plataforma',
        subtitle: 'Recursos transversais que sustentam segurança, controle e padronização da operação.',
        empty: 'Não há módulos globais habilitados para este contratante.',
    },
    niche_modules: {
        key: 'niche',
        title: 'Módulos do nicho do contratante',
        subtitle: 'Recursos base do nicho atual, com rotinas essenciais para o modelo de operação.',
        empty: 'Não há módulos de nicho habilitados para este contratante.',
    },
    business_modules: {
        key: 'business',
        title: 'Módulos do tipo de negócio',
        subtitle: 'Recursos específicos do tipo de negócio atual, com orientações práticas de execução.',
        empty: 'Não há módulos específicos do tipo de negócio habilitados para este contratante.',
    },
};

const currentModuleMeta = computed(() => moduleTabCatalog[activeTab.value] ?? null);
const currentModuleItems = computed(() => {
    if (!currentModuleMeta.value) return [];
    return props.manualGroups?.[currentModuleMeta.value.key] ?? [];
});

const overviewCards = computed(() => [
    { key: 'global', label: 'Módulos globais', value: props.manualContext?.group_counts?.global ?? 0 },
    { key: 'niche', label: 'Módulos do nicho', value: props.manualContext?.group_counts?.niche ?? 0 },
    { key: 'business', label: 'Módulos por tipo', value: props.manualContext?.group_counts?.business ?? 0 },
]);

const operationSections = computed(() => [
    { key: 'essentials', title: 'Fundamentos da operação', items: props.operationChecklist?.essentials ?? [] },
    { key: 'niche', title: 'Rotina do nicho', items: props.operationChecklist?.niche ?? [] },
    { key: 'security', title: 'Segurança e controle', items: props.operationChecklist?.security ?? [] },
]);
</script>

<template>
    <Head title="Manuais" />

    <AuthenticatedLayout
        area="admin"
        header-variant="compact"
        header-title="Manuais"
        :show-table-view-toggle="false"
    >
        <section class="space-y-4" :style="manualStyles">
            <div class="manual-tabs-shell">
                <div class="manual-tabs-track">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="manual-tab"
                        :class="activeTab === tab.key ? 'is-active' : ''"
                        @click="setActiveTab(tab.key)"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        <span class="truncate">{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <template v-if="activeTab === 'overview'">
                    <div class="grid gap-3 lg:grid-cols-3">
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 lg:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contexto atual</p>
                            <h2 class="mt-2 text-base font-semibold text-slate-900">
                                {{ props.manualContext?.contractor_name || 'Operação atual' }}
                            </h2>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-1">
                                    Nicho: {{ props.manualContext?.niche_label }}
                                </span>
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-1">
                                    Tipo: {{ props.manualContext?.business_type_label }}
                                </span>
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-1">
                                    Plano: {{ props.manualContext?.plan_name }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">
                                Esta central foi adaptada para os módulos habilitados do contratante e organiza as orientações por escopo:
                                global, nicho e tipo de negócio.
                            </p>
                        </article>

                        <article class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Módulos habilitados</p>
                            <p class="mt-2 text-2xl font-bold text-emerald-800">{{ props.manualContext?.enabled_modules_count ?? 0 }}</p>
                            <p class="mt-1 text-xs text-emerald-700">Base ativa para operação do contratante.</p>
                        </article>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <article v-for="item in overviewCards" :key="item.key" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ item.label }}</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ item.value }}</p>
                        </article>
                    </div>

                    <section class="mt-4 rounded-xl border border-slate-200 bg-white p-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-900">Acessos rápidos</h3>
                            <p class="text-xs text-slate-500">Atalhos para os módulos ativos</p>
                        </div>
                        <div v-if="props.quickLinks.length" class="mt-3 flex flex-wrap gap-2">
                            <template v-for="quickLink in props.quickLinks" :key="`${quickLink.label}-${quickLink.href}`">
                                <a
                                    v-if="quickLink.external"
                                    :href="quickLink.href"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    {{ quickLink.label }}
                                    <ExternalLink class="h-3.5 w-3.5" />
                                </a>
                                <Link
                                    v-else
                                    :href="quickLink.href"
                                    class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    {{ quickLink.label }}
                                </Link>
                            </template>
                        </div>
                        <p v-else class="mt-3 text-sm text-slate-500">
                            Nenhum atalho disponível para os módulos atuais.
                        </p>
                    </section>

                    <section class="mt-4 space-y-3">
                        <article
                            v-for="section in operationSections"
                            :key="section.key"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ section.title }}</p>
                            <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                <li v-for="item in section.items" :key="item" class="flex items-start gap-2">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 text-emerald-600" />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </article>
                    </section>
                </template>

                <template v-else-if="currentModuleMeta">
                    <h2 class="text-sm font-semibold text-slate-900">{{ currentModuleMeta.title }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ currentModuleMeta.subtitle }}</p>

                    <div v-if="currentModuleItems.length" class="mt-4 space-y-4">
                        <article
                            v-for="module in currentModuleItems"
                            :key="module.code"
                            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ module.name }}</h3>
                                    <p class="mt-1 text-sm text-slate-600">{{ module.description }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-600">
                                        {{ module.scope_label }}
                                    </span>
                                    <span
                                        v-if="module.is_default"
                                        class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700"
                                    >
                                        Essencial
                                    </span>
                                </div>
                            </div>

                            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Objetivo operacional</p>
                                <p class="mt-1 text-sm text-slate-700">{{ module.goal }}</p>
                            </div>

                            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                                <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Passo a passo</p>
                                    <ol class="mt-2 space-y-2 text-sm text-slate-700">
                                        <li v-for="(step, index) in module.steps" :key="`${module.code}-step-${index}`">
                                            <span class="font-semibold text-slate-900">{{ index + 1 }}.</span>
                                            <span class="ml-1">{{ step }}</span>
                                        </li>
                                    </ol>
                                </article>

                                <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Checklist</p>
                                    <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                        <li v-for="item in module.checklist" :key="`${module.code}-check-${item}`" class="flex items-start gap-2">
                                            <CheckCircle2 class="mt-0.5 h-4 w-4 text-emerald-600" />
                                            <span>{{ item }}</span>
                                        </li>
                                    </ul>
                                </article>
                            </div>

                            <div v-if="module.actions?.length" class="mt-3 flex flex-wrap items-center gap-2">
                                <Link
                                    v-for="action in module.actions"
                                    :key="`${module.code}-${action.label}`"
                                    :href="action.href"
                                    class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    {{ action.label }}
                                </Link>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        {{ currentModuleMeta.empty }}
                    </div>
                </template>

                <template v-else>
                    <h2 class="text-sm font-semibold text-slate-900">{{ props.businessPlaybook?.title }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ props.businessPlaybook?.summary }}</p>

                    <div class="mt-4 grid gap-3 lg:grid-cols-3">
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rotina diária</p>
                            <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                <li v-for="item in props.businessPlaybook?.daily || []" :key="`daily-${item}`" class="flex items-start gap-2">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 text-emerald-600" />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rotina semanal</p>
                            <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                <li v-for="item in props.businessPlaybook?.weekly || []" :key="`weekly-${item}`" class="flex items-start gap-2">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 text-emerald-600" />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rotina mensal</p>
                            <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                <li v-for="item in props.businessPlaybook?.monthly || []" :key="`monthly-${item}`" class="flex items-start gap-2">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 text-emerald-600" />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </article>
                    </div>

                    <article class="mt-4 rounded-xl border border-amber-200 bg-amber-50/70 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Alertas operacionais</p>
                        <ul class="mt-2 space-y-2 text-sm text-amber-800">
                            <li v-for="item in props.businessPlaybook?.alerts || []" :key="`alert-${item}`" class="flex items-start gap-2">
                                <CircleAlert class="mt-0.5 h-4 w-4 text-amber-600" />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </article>
                </template>
            </section>
        </section>
    </AuthenticatedLayout>
</template>

<style scoped>
.manual-tabs-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.manual-tabs-shell::-webkit-scrollbar {
    height: 6px;
}

.manual-tabs-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.manual-tabs-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.manual-tab {
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

.manual-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.manual-tab.is-active {
    border-color: var(--manual-tab-active-border);
    background: var(--manual-tab-active);
    color: #ffffff;
}
</style>

