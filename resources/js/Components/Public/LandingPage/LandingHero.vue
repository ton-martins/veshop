<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const heroCards = [
    {
        title: 'Fluxo visual',
        text: 'Desenhe etapas e responsáveis da operação em minutos.',
        iconClass: 'ri-flow-chart',
    },
    {
        title: 'Prioridades do dia',
        text: 'Veja o que vence primeiro e evite atrasos de SLA.',
        iconClass: 'ri-notification-3-line',
    },
    {
        title: 'Conectores nativos',
        text: 'Integre ERP, PDV, fiscal e CRM sem retrabalho manual.',
        iconClass: 'ri-links-line',
    },
];

const metrics = [
    {
        label: 'Tempo por etapa',
        value: 'Meta + variação',
        note: 'Acompanhe prazos por processo e equipe.',
        iconClass: 'ri-timer-flash-line',
    },
    {
        label: 'Trilha operacional',
        value: 'Registro completo',
        note: 'Histórico de decisões e evidências por etapa.',
        iconClass: 'ri-shield-check-line',
    },
    {
        label: 'Integrações',
        value: 'API + eventos',
        note: 'Sincronização estável entre sistemas externos.',
        iconClass: 'ri-links-line',
    },
    {
        label: 'Indicadores gerenciais',
        value: 'Visão consolidada',
        note: 'KPIs de vendas, estoque e financeiro em tempo real.',
        iconClass: 'ri-line-chart-line',
    },
];

const tabsData = {
    dashboard: {
        label: 'Dashboard',
        header: 'Atividades',
        status: ['Agora', 'Atualizado agora', 'Últimos 5 min'],
        feed: [
            { title: 'Nova venda registrada', meta: 'Pedido #4821 · Loja Centro', badge: 'Nova', tone: 'positive' },
            { title: 'Pagamento conciliado', meta: 'PIX · Pedido #4817', badge: 'Financeiro', tone: 'neutral' },
            { title: 'NF-e autorizada', meta: 'Série 2 · 14:36', badge: 'Fiscal', tone: 'positive' },
            { title: 'Reposição sugerida', meta: 'SKU 1192 · Estoque mínimo', badge: 'Estoque', tone: 'warning' },
            { title: 'Pedido separado', meta: 'Expedição · Pedido #4824', badge: 'Logística', tone: 'neutral' },
            { title: 'Meta diária em 84%', meta: 'Equipe Loja Sul', badge: 'Comercial', tone: 'positive' },
        ],
    },
    fila: {
        label: 'Fila',
        header: 'Fila crítica',
        status: ['Agora', 'Prioridade', 'Últimos 15 min'],
        feed: [
            { title: 'Produto com ruptura', meta: 'SKU 3402 · Sem saldo', badge: 'Estoque', tone: 'warning' },
            { title: 'Conta a pagar vence hoje', meta: 'Fornecedor Atlas · 17:00', badge: 'Financeiro', tone: 'warning' },
            { title: 'Pedido parado na expedição', meta: 'Pedido #4832 · 48 min', badge: 'Logística', tone: 'neutral' },
            { title: 'Divergência de caixa', meta: 'PDV Loja Norte', badge: 'Alerta', tone: 'warning' },
            { title: 'Preço sem atualização', meta: 'Tabela atacado', badge: 'Comercial', tone: 'neutral' },
        ],
    },
    auditoria: {
        label: 'Auditoria',
        header: 'Auditoria',
        status: ['Últimas 24h', 'Atualizado há 3 min', 'Últimas 2h'],
        feed: [
            { title: 'Alteração de preço', meta: 'Usuária Ana · SKU 2201', badge: 'Registro', tone: 'neutral' },
            { title: 'Cancelamento autorizado', meta: 'Pedido #4789 · 2 min', badge: 'Concluído', tone: 'positive' },
            { title: 'Ajuste de estoque', meta: 'Inventário rotativo', badge: 'Estoque', tone: 'neutral' },
            { title: 'Parâmetro fiscal alterado', meta: 'CFOP de saída', badge: 'Fiscal', tone: 'warning' },
            { title: 'Integração reprocessada', meta: 'Webhook ERP', badge: 'Integração', tone: 'positive' },
        ],
    },
};

const tabKeys = Object.keys(tabsData);
const activeTab = ref(tabKeys[0]);
const activityTimer = ref(null);

let feedCounter = 0;
const makeItem = (entry) => ({
    id: `${Date.now()}-${feedCounter++}`,
    title: entry.title,
    meta: entry.meta,
    badge: entry.badge,
    tone: entry.tone ?? 'neutral',
});

const feedCursor = {
    dashboard: 0,
    fila: 0,
    auditoria: 0,
};

const statusCursor = {
    dashboard: 0,
    fila: 0,
    auditoria: 0,
};

const buildInitialFeed = (tabId) => {
    const source = tabsData[tabId]?.feed ?? [];
    const initial = source.slice(0, 3).map(makeItem);
    feedCursor[tabId] = initial.length;
    return initial;
};

const tabsState = ref([
    {
        id: 'dashboard',
        label: tabsData.dashboard.label,
        header: tabsData.dashboard.header,
        status: tabsData.dashboard.status[0],
        feed: buildInitialFeed('dashboard'),
    },
    {
        id: 'fila',
        label: tabsData.fila.label,
        header: tabsData.fila.header,
        status: tabsData.fila.status[0],
        feed: buildInitialFeed('fila'),
    },
    {
        id: 'auditoria',
        label: tabsData.auditoria.label,
        header: tabsData.auditoria.header,
        status: tabsData.auditoria.status[0],
        feed: buildInitialFeed('auditoria'),
    },
]);

const activeTabData = computed(
    () => tabsState.value.find((tab) => tab.id === activeTab.value) ?? tabsState.value[0],
);

const appHeader = computed(() => activeTabData.value?.header ?? 'Atividades');
const appStatus = computed(() => activeTabData.value?.status ?? 'Agora');
const appFeed = computed(() => activeTabData.value?.feed ?? []);

const setActiveTab = (tabId) => {
    activeTab.value = tabId;
};

const updateFeed = (tabId) => {
    const stateTab = tabsState.value.find((tab) => tab.id === tabId);
    const sourceTab = tabsData[tabId];
    if (!stateTab || !sourceTab) return;

    const nextIndex = feedCursor[tabId] % sourceTab.feed.length;
    feedCursor[tabId] += 1;
    stateTab.feed = [makeItem(sourceTab.feed[nextIndex]), ...stateTab.feed].slice(0, 3);

    const nextStatusIndex = statusCursor[tabId] % sourceTab.status.length;
    statusCursor[tabId] += 1;
    stateTab.status = sourceTab.status[nextStatusIndex];
};

const metricCarouselRef = ref(null);
const canScrollPrev = ref(false);
const canScrollNext = ref(false);

const updateMetricButtons = () => {
    const row = metricCarouselRef.value;
    if (!row) return;

    const maxScroll = row.scrollWidth - row.clientWidth;
    canScrollPrev.value = row.scrollLeft > 4;
    canScrollNext.value = row.scrollLeft < maxScroll - 4;
};

const onMetricScroll = () => {
    updateMetricButtons();
};

const scrollMetricCarousel = (direction) => {
    const row = metricCarouselRef.value;
    if (!row) return;
    row.scrollBy({ left: direction * row.clientWidth * 0.8, behavior: 'smooth' });
};

onMounted(() => {
    const row = metricCarouselRef.value;
    if (row) {
        row.addEventListener('scroll', onMetricScroll, { passive: true });
    }

    window.addEventListener('resize', updateMetricButtons);
    activityTimer.value = window.setInterval(() => {
        updateFeed(activeTab.value);
    }, 4800);

    requestAnimationFrame(updateMetricButtons);
});

onBeforeUnmount(() => {
    const row = metricCarouselRef.value;
    if (row) {
        row.removeEventListener('scroll', onMetricScroll);
    }
    window.removeEventListener('resize', updateMetricButtons);
    if (activityTimer.value) {
        window.clearInterval(activityTimer.value);
        activityTimer.value = null;
    }
});
</script>

<template>
    <section class="veshop-hero-app" id="home">
        <div class="veshop-hero-glow" aria-hidden="true"></div>
        <div class="container veshop-hero-wrap">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <span class="veshop-hero-badge">
                        <i class="ri-store-2-line"></i>
                        ERP para comércio e varejo
                    </span>

                    <h1 class="veshop-hero-title">
                        Gestão completa para vendas, estoque, financeiro e fiscal em uma só plataforma.
                    </h1>

                    <p class="veshop-hero-subtitle">
                        O Veshop conecta operação, atendimento e gestão para lojas, mercados, distribuidores e outros nichos.
                        Tenha visão em tempo real do que acontece no negócio e tome decisões mais rápidas.
                    </p>

                    <div class="veshop-hero-actions">
                        <Link :href="route('login')" class="btn btn-primary">
                            Entrar no painel
                            <i class="ri-arrow-right-up-line"></i>
                        </Link>
                        <a href="#contacts" class="btn btn-success">
                            Agendar demonstração
                            <i class="ri-play-circle-line"></i>
                        </a>
                    </div>

                    <div class="veshop-mini-grid">
                        <article
                            v-for="card in heroCards"
                            :key="card.title"
                            class="veshop-mini-card"
                            role="button"
                            tabindex="0"
                        >
                            <span class="veshop-mini-icon">
                                <i :class="card.iconClass"></i>
                            </span>
                            <p class="veshop-mini-title">{{ card.title }}</p>
                            <p class="veshop-mini-text">{{ card.text }}</p>
                        </article>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="veshop-ops-panel">
                        <div class="veshop-ops-head">
                            <div class="veshop-ops-brand">
                                <span class="icon"><i class="ri-dashboard-line"></i></span>
                                <div>
                                    <p class="eyebrow">Veshop Ops</p>
                                    <p class="name">Visão por unidade</p>
                                </div>
                            </div>
                            <span class="veshop-ops-status">
                                <span class="dot"></span>
                                Monitorando
                            </span>
                        </div>

                        <div class="veshop-ops-tabs">
                            <button
                                v-for="tab in tabsState"
                                :key="tab.id"
                                type="button"
                                class="veshop-tab-btn"
                                :class="{ active: activeTab === tab.id }"
                                @click="setActiveTab(tab.id)"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <div class="veshop-feed-shell">
                            <div class="veshop-feed-head">
                                <span>{{ appHeader }}</span>
                                <span>{{ appStatus }}</span>
                            </div>
                            <transition-group name="ops-feed" tag="div" class="veshop-feed-list">
                                <article v-for="item in appFeed" :key="item.id" class="veshop-feed-item">
                                    <span class="veshop-feed-dot"></span>
                                    <div>
                                        <p class="veshop-feed-title">
                                            <span>{{ item.title }}</span>
                                            <span class="veshop-badge" :class="item.tone">{{ item.badge }}</span>
                                        </p>
                                        <p class="veshop-feed-meta">{{ item.meta }}</p>
                                    </div>
                                </article>
                            </transition-group>
                        </div>

                        <div class="veshop-metric-wrap">
                            <button
                                type="button"
                                class="veshop-metric-arrow prev"
                                aria-label="Voltar carrossel"
                                @click="scrollMetricCarousel(-1)"
                                v-show="canScrollPrev"
                            >
                                <i class="ri-arrow-left-s-line"></i>
                            </button>

                            <div ref="metricCarouselRef" class="veshop-metric-track">
                                <article
                                    v-for="metric in metrics"
                                    :key="metric.label"
                                    class="veshop-metric-card"
                                    role="button"
                                    tabindex="0"
                                >
                                    <span class="veshop-metric-icon">
                                        <i :class="metric.iconClass"></i>
                                    </span>
                                    <p class="veshop-metric-label">{{ metric.label }}</p>
                                    <p class="veshop-metric-value">{{ metric.value }}</p>
                                    <p class="veshop-metric-note">{{ metric.note }}</p>
                                </article>
                            </div>

                            <button
                                type="button"
                                class="veshop-metric-arrow next"
                                aria-label="Avançar carrossel"
                                @click="scrollMetricCarousel(1)"
                                v-show="canScrollNext"
                            >
                                <i class="ri-arrow-right-s-line"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.veshop-hero-app {
    position: relative;
    padding: 170px 0 90px;
    background-image: url("/landing/images/bg-img-4.png");
    background-size: cover;
    background-position: center;
    overflow: hidden;
}

.veshop-hero-glow {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}

.veshop-hero-glow::before,
.veshop-hero-glow::after {
    content: "";
    position: absolute;
    border-radius: 999px;
    filter: blur(70px);
}

.veshop-hero-glow::before {
    width: 360px;
    height: 360px;
    left: -110px;
    top: 90px;
    background: rgba(129, 216, 111, 0.26);
}

.veshop-hero-glow::after {
    width: 420px;
    height: 420px;
    right: -120px;
    top: -40px;
    background: rgba(7, 51, 65, 0.18);
}

.veshop-hero-wrap {
    position: relative;
    z-index: 1;
}

.veshop-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    border: 1px solid rgba(7, 51, 65, 0.2);
    background: rgba(255, 255, 255, 0.88);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #073341;
}

.veshop-hero-title {
    margin-top: 18px;
    color: #073341;
    font-size: clamp(2rem, 3.7vw, 3.2rem);
    font-weight: 700 !important;
    line-height: 1.22;
}

.veshop-hero-subtitle {
    margin-top: 14px;
    max-width: 620px;
    color: #3f566e;
    font-size: 18px;
    line-height: 1.65;
}

.veshop-hero-actions {
    margin-top: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.veshop-hero-actions .btn {
    border-radius: 10px;
    padding: 12px 18px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.veshop-mini-grid {
    margin-top: 22px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.veshop-mini-card {
    border: 1px solid rgba(7, 51, 65, 0.16);
    background: rgba(255, 255, 255, 0.86);
    border-radius: 14px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.veshop-mini-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(7, 51, 65, 0.12);
}

.veshop-mini-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #073341;
    background: rgba(129, 216, 111, 0.25);
    font-size: 18px;
}

.veshop-mini-title {
    margin-top: 10px;
    color: #073341;
    font-size: 14px;
    font-weight: 700;
}

.veshop-mini-text {
    margin-top: 4px;
    color: #5f7388;
    font-size: 13px;
    line-height: 1.4;
}

.veshop-ops-panel {
    position: relative;
    border-radius: 24px;
    border: 1px solid rgba(129, 216, 111, 0.28);
    background: linear-gradient(155deg, rgba(2, 29, 37, 0.95), rgba(7, 51, 65, 0.9));
    padding: 18px;
    box-shadow: 0 30px 70px -36px rgba(7, 51, 65, 0.9);
    color: #f5fdff;
}

.veshop-ops-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.veshop-ops-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.veshop-ops-brand .icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: rgba(129, 216, 111, 0.2);
    border: 1px solid rgba(129, 216, 111, 0.38);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.veshop-ops-brand .eyebrow {
    margin: 0;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(245, 253, 255, 0.7);
}

.veshop-ops-brand .name {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #f5fdff;
}

.veshop-ops-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid rgba(129, 216, 111, 0.35);
    background: rgba(129, 216, 111, 0.15);
    font-size: 12px;
    font-weight: 600;
    color: #d4f8cc;
}

.veshop-ops-status .dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #81d86f;
}

.veshop-ops-tabs {
    margin-top: 14px;
    display: inline-flex;
    gap: 6px;
    border: 1px solid rgba(245, 253, 255, 0.2);
    background: rgba(245, 253, 255, 0.06);
    border-radius: 12px;
    padding: 4px;
}

.veshop-tab-btn {
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: rgba(245, 253, 255, 0.8);
    font-size: 12px;
    font-weight: 600;
    padding: 6px 10px;
    transition: all 0.2s ease;
}

.veshop-tab-btn.active {
    background: rgba(129, 216, 111, 0.22);
    color: #f5fdff;
}

.veshop-feed-shell {
    margin-top: 12px;
    border: 1px solid rgba(245, 253, 255, 0.18);
    border-radius: 14px;
    background: rgba(2, 29, 37, 0.5);
    padding: 12px;
}

.veshop-feed-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(245, 253, 255, 0.68);
}

.veshop-feed-list {
    margin-top: 10px;
    display: grid;
    gap: 8px;
}

.veshop-feed-item {
    display: grid;
    grid-template-columns: 8px 1fr;
    gap: 10px;
    align-items: start;
    border: 1px solid rgba(245, 253, 255, 0.15);
    border-radius: 10px;
    background: rgba(245, 253, 255, 0.06);
    padding: 8px;
}

.veshop-feed-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: 6px;
    background: #81d86f;
}

.veshop-feed-title {
    margin: 0;
    color: #f5fdff;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.veshop-feed-meta {
    margin: 2px 0 0;
    color: rgba(245, 253, 255, 0.72);
    font-size: 12px;
}

.veshop-badge {
    border-radius: 999px;
    padding: 2px 7px;
    font-size: 10px;
    font-weight: 700;
    border: 1px solid transparent;
}

.veshop-badge.positive {
    color: #d4f8cc;
    border-color: rgba(129, 216, 111, 0.3);
    background: rgba(129, 216, 111, 0.16);
}

.veshop-badge.warning {
    color: #ffe8b6;
    border-color: rgba(255, 193, 7, 0.35);
    background: rgba(255, 193, 7, 0.16);
}

.veshop-badge.neutral {
    color: rgba(245, 253, 255, 0.85);
    border-color: rgba(245, 253, 255, 0.3);
    background: rgba(245, 253, 255, 0.1);
}

.veshop-metric-wrap {
    margin-top: 14px;
    position: relative;
}

.veshop-metric-track {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    scroll-behavior: smooth;
    padding-bottom: 4px;
    scrollbar-width: none;
}

.veshop-metric-track::-webkit-scrollbar {
    display: none;
}

.veshop-metric-card {
    min-width: 166px;
    border: 1px solid rgba(245, 253, 255, 0.18);
    border-radius: 12px;
    background: rgba(245, 253, 255, 0.08);
    padding: 10px;
}

.veshop-metric-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(129, 216, 111, 0.2);
    color: #d4f8cc;
    font-size: 14px;
}

.veshop-metric-label {
    margin: 8px 0 0;
    color: rgba(245, 253, 255, 0.8);
    font-size: 12px;
}

.veshop-metric-value {
    margin: 3px 0 0;
    color: #f5fdff;
    font-size: 14px;
    font-weight: 700;
}

.veshop-metric-note {
    margin: 2px 0 0;
    color: rgba(245, 253, 255, 0.68);
    font-size: 11px;
    line-height: 1.4;
}

.veshop-metric-arrow {
    position: absolute;
    top: 36%;
    width: 28px;
    height: 28px;
    border: 1px solid rgba(245, 253, 255, 0.25);
    border-radius: 50%;
    background: rgba(2, 29, 37, 0.8);
    color: #f5fdff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    z-index: 2;
}

.veshop-metric-arrow.prev {
    left: -10px;
}

.veshop-metric-arrow.next {
    right: -10px;
}

@media (max-width: 991px) {
    .veshop-hero-app {
        padding: 130px 0 60px;
    }

    .veshop-mini-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575px) {
    .veshop-mini-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .veshop-metric-arrow {
        display: none;
    }
}
</style>
