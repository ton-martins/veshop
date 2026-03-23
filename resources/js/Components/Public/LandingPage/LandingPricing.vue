<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    planSections: {
        type: Array,
        default: () => [],
    },
});

const fallbackSections = [
    {
        value: 'commercial',
        label: 'Comércio',
        title: 'Planos para Comércio',
        description: 'Estruturas para lojas com foco em vendas, estoque e operação diária.',
        plans: [
            {
                id: 'commercial-start',
                name: 'Start',
                badge: '',
                subtitle: 'Comércio em fase inicial',
                summary: 'Plano de entrada para lojas com operação enxuta.',
                price_monthly: 199,
                user_limit: 2,
                is_featured: false,
                features: [
                    { label: 'Catálogo', value: 'Produtos e categorias' },
                    { label: 'Vendas', value: 'Fluxo de caixa e pedidos' },
                    { label: 'Relatórios', value: 'Essenciais' },
                ],
            },
            {
                id: 'commercial-pro',
                name: 'Pro',
                badge: 'Mais escolhido',
                subtitle: 'Comércio em crescimento',
                summary: 'Plano para operação comercial com maior escala.',
                price_monthly: 399,
                user_limit: 8,
                is_featured: true,
                features: [
                    { label: 'Estoque', value: 'Controle avançado' },
                    { label: 'Financeiro', value: 'Contas a pagar e receber' },
                    { label: 'Relatórios', value: 'Indicadores operacionais' },
                ],
            },
            {
                id: 'commercial-business',
                name: 'Business',
                badge: 'Escala',
                subtitle: 'Comércio consolidado',
                summary: 'Plano para operações com alta demanda no varejo.',
                price_monthly: 799,
                user_limit: null,
                is_featured: false,
                features: [
                    { label: 'Usuários', value: 'Ilimitados' },
                    { label: 'Multiunidade', value: 'Operação em expansão' },
                    { label: 'Suporte', value: 'Prioritário' },
                ],
            },
        ],
    },
    {
        value: 'services',
        label: 'Serviços',
        title: 'Planos para Serviços',
        description: 'Estruturas para equipes com agenda, ordens e atendimento consultivo.',
        plans: [
            {
                id: 'services-start',
                name: 'Start',
                badge: '',
                subtitle: 'Serviços em fase inicial',
                summary: 'Plano de entrada para equipes com operação enxuta.',
                price_monthly: 179,
                user_limit: 2,
                is_featured: false,
                features: [
                    { label: 'Catálogo', value: 'Serviços e categorias' },
                    { label: 'Agenda', value: 'Básica' },
                    { label: 'Ordens', value: 'Fluxo principal' },
                ],
            },
            {
                id: 'services-pro',
                name: 'Pro',
                badge: 'Mais vendido',
                subtitle: 'Serviços em crescimento',
                summary: 'Plano para operação de serviços com maior volume.',
                price_monthly: 349,
                user_limit: 8,
                is_featured: true,
                features: [
                    { label: 'Agenda', value: 'Completa com visão diária' },
                    { label: 'Ordens', value: 'Status e acompanhamento' },
                    { label: 'Relatórios', value: 'Produtividade da equipe' },
                ],
            },
            {
                id: 'services-business',
                name: 'Business',
                badge: 'Escala',
                subtitle: 'Operação de serviços consolidada',
                summary: 'Plano para empresas com alta demanda de atendimento.',
                price_monthly: 699,
                user_limit: null,
                is_featured: false,
                features: [
                    { label: 'Usuários', value: 'Ilimitados' },
                    { label: 'SLA', value: 'Monitoramento consultivo' },
                    { label: 'Governança', value: 'Retenção ampliada de auditoria' },
                ],
            },
        ],
    },
];

const iconBySection = (value) => {
    return value === 'services' ? 'ri-briefcase-4-line' : 'ri-store-2-line';
};

const sanitizeSection = (section, index) => {
    const fallback = fallbackSections[index] ?? fallbackSections[0];
    const plans = Array.isArray(section?.plans) ? section.plans : [];

    return {
        value: String(section?.value ?? fallback.value),
        label: String(section?.label ?? fallback.label),
        title: String(section?.title ?? fallback.title),
        description: String(section?.description ?? fallback.description),
        plans: plans.map((plan, planIndex) => ({
            id: plan?.id ?? `${fallback.value}-${planIndex}`,
            name: String(plan?.name ?? ''),
            badge: String(plan?.badge ?? ''),
            subtitle: String(plan?.subtitle ?? ''),
            summary: String(plan?.summary ?? ''),
            footer_message: String(plan?.footer_message ?? ''),
            price_monthly: plan?.price_monthly ?? null,
            user_limit: plan?.user_limit ?? null,
            is_featured: Boolean(plan?.is_featured),
            features: Array.isArray(plan?.features) ? plan.features : [],
        })),
    };
};

const sections = computed(() => {
    const source = Array.isArray(props.planSections) && props.planSections.length
        ? props.planSections
        : fallbackSections;

    return source.map((section, index) => sanitizeSection(section, index));
});

const activeTab = ref('');

watch(
    sections,
    (items) => {
        if (!items.length) {
            activeTab.value = '';
            return;
        }

        const exists = items.some((item) => item.value === activeTab.value);
        if (!exists) {
            activeTab.value = items[0].value;
        }
    },
    { immediate: true, deep: true },
);

const currentSection = computed(() => {
    if (!sections.value.length) return null;

    const found = sections.value.find((section) => section.value === activeTab.value);
    return found ?? sections.value[0];
});

const formatMoney = (value) => {
    if (value === null || value === undefined || value === '') return 'Sob consulta';

    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return 'Sob consulta';

    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(parsed);
};

const limitText = (plan) => {
    if (plan?.user_limit === null || plan?.user_limit === undefined || plan?.user_limit === '') {
        return 'Usuários ilimitados';
    }

    return `${plan.user_limit} usuário(s)`;
};

const topFeatures = (plan) => {
    return (Array.isArray(plan?.features) ? plan.features : [])
        .map((feature) => ({
            label: String(feature?.label ?? '').trim(),
            value: String(feature?.value ?? '').trim(),
        }))
        .filter((feature) => feature.label || feature.value);
};
</script>

<template>
    <section class="section price-section position-relative z-1 landing-section-shell" id="price">
        <div class="price-glow" aria-hidden="true"></div>
        <div class="container">
            <div class="row align-items-center justify-content-center text-center">
                <div class="col-lg-8">
                    <div class="title-sm">
                        <span>Planos</span>
                    </div>
                    <div class="section-title-border mt-3"></div>
                    <div class="price-title mt-4">
                        <h2 class="fw-semibold text-primary">Planos por nicho de negócio</h2>
                        <p class="mt-3 text-muted mb-0">
                            Selecione o nicho para ver os planos disponíveis.
                        </p>
                    </div>
                </div>
            </div>

            <div class="row mt-5 g-3 justify-content-center">
                <div v-for="section in sections" :key="`plan-tab-${section.value}`" class="col-12 col-md-6 col-lg-4">
                    <button
                        type="button"
                        class="card plan-tab-card h-100 w-100 border-0 text-start"
                        :class="activeTab === section.value ? 'is-active' : ''"
                        @click="activeTab = section.value"
                    >
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="plan-tab-icon">
                                <i :class="iconBySection(section.value)"></i>
                            </span>
                            <div>
                                <p class="mb-1 fw-bold text-dark">{{ section.label }}</p>
                                <p class="mb-0 text-muted small">Ver planos disponíveis</p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <div v-if="currentSection" class="row mt-4 g-4">
                <div class="col-12">
                    <div class="card plan-section-shell shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-body p-3 p-md-4 p-lg-5">
                            <div class="mb-4">
                                <span class="badge text-bg-light border text-uppercase px-3 py-2 fw-semibold">
                                    {{ currentSection.label }}
                                </span>
                                <h3 class="fw-bold text-primary mt-3 mb-2">{{ currentSection.title }}</h3>
                                <p class="text-muted mb-0">{{ currentSection.description }}</p>
                            </div>

                            <div class="row g-4">
                                <div v-for="plan in currentSection.plans" :key="`plan-${currentSection.value}-${plan.id}`" class="col-md-6 col-xl-4">
                                    <div class="card plan-card h-100 border-0 shadow-sm border-top border-3"
                                        :class="plan.is_featured ? 'border-success' : 'border-primary'">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <h5 class="fw-bold mb-0"
                                                    :class="plan.is_featured ? 'text-success' : 'text-primary'">
                                                    {{ plan.name }}
                                                </h5>
                                                <span v-if="plan.badge" class="badge rounded-pill text-bg-light border">
                                                    {{ plan.badge }}
                                                </span>
                                            </div>
                                            <p class="mt-2 text-muted mb-1">{{ plan.subtitle || limitText(plan) }}</p>
                                            <p class="small text-muted">{{ plan.summary || 'Plano flexível para o nicho.' }}</p>

                                            <h4 class="plan-price fw-bold my-3"
                                                :class="plan.is_featured ? 'text-success' : 'text-primary'">
                                                {{ formatMoney(plan.price_monthly) }}
                                                <span class="fs-6 text-muted">/ mês</span>
                                            </h4>

                                            <ul class="list-unstyled mb-0">
                                                <li v-for="(feature, featureIndex) in topFeatures(plan)" :key="`${plan.id}-${featureIndex}`" class="d-flex align-items-start gap-2 mb-2">
                                                    <i class="ri-checkbox-circle-fill text-success mt-1"></i>
                                                    <span class="plan-feature-copy">
                                                        <span class="plan-feature-title">{{ feature.label || feature.value }}</span>
                                                        <span v-if="feature.label && feature.value" class="plan-feature-description">{{ feature.value }}</span>
                                                    </span>
                                                </li>
                                                <li v-if="!topFeatures(plan).length" class="text-muted small">
                                                    Nenhum benefício configurado ainda.
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="card-footer bg-white border-0 pb-4">
                                            <a
                                                href="/login"
                                                class="btn w-100 fw-semibold"
                                                :class="plan.is_featured ? 'btn-success' : 'btn-primary'"
                                            >
                                                Solicitar demonstração
                                            </a>
                                            <p class="small text-muted mt-3 mb-0">
                                                {{ plan.footer_message || 'Consumo consolidado por contratante, com gestão centralizada.' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="!currentSection.plans.length" class="alert alert-light border text-muted mb-0 mt-3">
                                Nenhum plano disponível neste nicho no momento.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.price-section {
    --next-section-bg: linear-gradient(180deg, #08242c 0%, #0d3942 100%);
    overflow: hidden;
    background:
        radial-gradient(70% 70% at 12% 18%, rgba(129, 216, 111, 0.26) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(52% 52% at 88% 20%, rgba(62, 194, 139, 0.18) 0%, rgba(62, 194, 139, 0) 70%),
        linear-gradient(180deg, #08242c 0%, #0a2f37 45%, #0c363f 100%);
}

.price-glow {
    position: absolute;
    width: clamp(460px, 58vw, 940px);
    height: clamp(460px, 58vw, 940px);
    border-radius: 999px;
    right: -26%;
    top: -56%;
    background: radial-gradient(circle at center, rgba(129, 216, 111, 0.24) 0%, rgba(129, 216, 111, 0) 76%);
    pointer-events: none;
}

.price-title h2 {
    color: #effff3 !important;
}

.price-title p {
    color: rgba(231, 255, 238, 0.78) !important;
}

.plan-tab-card {
    border-radius: 22px;
    box-shadow: 0 14px 28px -24px rgba(2, 17, 22, 0.85);
    background: rgba(214, 255, 223, 0.08);
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    border: 1px solid rgba(214, 255, 223, 0.2);
}

.plan-tab-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 34px -24px rgba(2, 17, 22, 0.95);
}

.plan-tab-card.is-active {
    border-color: rgba(156, 225, 168, 0.82);
    box-shadow: 0 22px 40px -30px rgba(156, 225, 168, 0.56);
}

.plan-tab-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(214, 255, 223, 0.2);
    color: #eaffef;
    font-size: 20px;
}

.plan-tab-card.is-active .plan-tab-icon {
    background: rgba(156, 225, 168, 0.28);
    color: #edfff1;
}

.plan-tab-card .text-dark {
    color: #effff3 !important;
}

.plan-tab-card .text-muted {
    color: rgba(229, 255, 237, 0.72) !important;
}

.plan-section-shell {
    width: 100%;
    border: 1px solid rgba(214, 255, 223, 0.2) !important;
    background: rgba(214, 255, 223, 0.06);
    box-shadow: 0 24px 38px -30px rgba(2, 12, 10, 0.95) !important;
}

.plan-section-shell .badge.text-bg-light {
    background: rgba(214, 255, 223, 0.14) !important;
    color: #eaffef !important;
    border-color: rgba(214, 255, 223, 0.32) !important;
}

.plan-section-shell h3,
.plan-section-shell .text-primary {
    color: #effff3 !important;
}

.plan-section-shell .text-muted {
    color: rgba(230, 255, 238, 0.78) !important;
}

.plan-card {
    width: 100%;
    border: 1px solid rgba(214, 255, 223, 0.2) !important;
    background: rgba(8, 36, 44, 0.68);
    box-shadow: 0 18px 30px -24px rgba(2, 12, 10, 0.9) !important;
    border-radius: 20px;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.plan-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 36px -24px rgba(2, 12, 10, 0.95) !important;
    border-color: rgba(214, 255, 223, 0.3) !important;
}

.plan-card .text-primary {
    color: #9ce1a8 !important;
}

.plan-card .text-success {
    color: #bff6cc !important;
}

.plan-card .text-muted,
.plan-card .small.text-muted {
    color: rgba(229, 255, 237, 0.76) !important;
}

.plan-card .badge.text-bg-light {
    background: rgba(214, 255, 223, 0.14) !important;
    color: #eaffef !important;
    border-color: rgba(214, 255, 223, 0.32) !important;
}

.plan-card .card-footer.bg-white {
    background: transparent !important;
}

.plan-card .card-body,
.plan-card .card-footer {
    word-break: break-word;
}

.plan-price {
    font-size: clamp(1.55rem, 3.3vw, 2.25rem);
    line-height: 1.15;
}

.plan-feature-copy {
    display: inline-flex;
    flex-direction: column;
    line-height: 1.25;
}

.plan-feature-title {
    color: #effff3;
    font-weight: 600;
}

.plan-feature-description {
    margin-top: 0.1rem;
    color: rgba(230, 255, 238, 0.72);
    font-size: 12px;
    line-height: 1.35;
}

@media (max-width: 767.98px) {
    .price-section .container {
        padding-left: 0.55rem;
        padding-right: 0.55rem;
    }

    .plan-tab-card .card-body {
        align-items: flex-start;
        padding: 0.8rem;
    }

    .plan-tab-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
        flex-shrink: 0;
    }

    .plan-section-shell > .card-body {
        padding: 0 !important;
    }

    .plan-section-shell .row.g-4 {
        --bs-gutter-x: 0.7rem;
        --bs-gutter-y: 0.7rem;
        margin-left: 0;
        margin-right: 0;
    }

    .plan-card .card-body {
        padding: 0.85rem;
    }

    .plan-price {
        font-size: clamp(1.35rem, 7.2vw, 1.8rem);
    }

    .plan-card .card-footer {
        padding: 0 0.85rem 0.85rem;
    }
}

@media (max-width: 575.98px) {
    .price-section .container {
        padding-left: 0.45rem;
        padding-right: 0.45rem;
    }

    .plan-tab-card .small {
        font-size: 0.76rem;
        line-height: 1.35;
    }

    .plan-section-shell > .card-body {
        padding: 0 !important;
    }

    .plan-card .card-body {
        padding: 0.72rem;
    }

    .plan-card .card-footer {
        padding: 0 0.72rem 0.72rem;
    }

    .plan-card .card-body p,
    .plan-card .card-body li,
    .plan-card .card-footer p {
        font-size: 0.9rem;
        line-height: 1.45;
    }
}

@media (max-width: 360px) {
    .price-section .container {
        padding-left: 0.35rem;
        padding-right: 0.35rem;
    }

    .price-title h2 {
        font-size: 1.3rem;
        line-height: 1.25;
    }

    .price-title p {
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .plan-tab-card .card-body {
        padding: 0.62rem;
        gap: 0.6rem !important;
    }

    .plan-tab-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        font-size: 16px;
    }

    .plan-section-shell {
        border-radius: 12px !important;
    }

    .plan-section-shell > .card-body > .mb-4 {
        padding: 0 0.2rem 0.2rem;
    }

    .plan-section-shell > .card-body > .mb-4 h3 {
        font-size: 1.22rem;
        line-height: 1.25;
    }

    .plan-section-shell > .card-body > .mb-4 p {
        font-size: 0.86rem;
        line-height: 1.35;
    }

    .plan-section-shell .row.g-4 {
        --bs-gutter-x: 0.55rem;
        --bs-gutter-y: 0.55rem;
    }

    .plan-card .card-body {
        padding: 0.6rem;
    }

    .plan-card .card-footer {
        padding: 0 0.6rem 0.6rem;
    }

    .plan-price {
        font-size: clamp(1.18rem, 6.3vw, 1.45rem);
    }

    .plan-card .btn {
        padding: 9px 12px;
        font-size: 0.92rem;
    }

    .plan-card .badge {
        font-size: 0.66rem;
    }
}
</style>
