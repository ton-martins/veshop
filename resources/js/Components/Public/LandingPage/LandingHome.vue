<script setup>
import { Link } from '@inertiajs/vue3';
import { useBranding } from '@/branding';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const { secondaryColor, withAlpha } = useBranding();

const heroQuarterCircleStyle = computed(() => ({
    background: `radial-gradient(circle at center, ${withAlpha(secondaryColor.value, 0.34)} 0%, ${withAlpha(secondaryColor.value, 0.24)} 42%, ${withAlpha(secondaryColor.value, 0.14)} 62%, transparent 78%)`,
}));

const heroCards = [
    {
        title: 'Fluxo visual',
        text: 'Organize a operação com etapas claras, responsáveis e metas por equipe.',
        iconClass: 'ri-flow-chart',
        details: [
            'Estruture jornadas de venda, estoque, atendimento e pós-venda em um único painel.',
            'Padronize a execução entre unidades, lojas e filiais com o mesmo método operacional.',
            'Acompanhe gargalos por etapa para agir rápido antes de impactar o cliente final.',
        ],
    },
    {
        title: 'Prioridades do dia',
        text: 'Visualize o que é crítico agora e mantenha a operação dentro do prazo.',
        iconClass: 'ri-notification-3-line',
        details: [
            'Classifique tarefas por prazo, impacto e risco de atraso operacional.',
            'Direcione o time para atividades que geram resultado imediato no caixa e no atendimento.',
            'Reduza retrabalho com contexto completo por atividade e histórico de execução.',
        ],
    },
    {
        title: 'Conectores nativos',
        text: 'Conecte ERP, PDV, fiscal e CRM com sincronização contínua e segura.',
        iconClass: 'ri-links-line',
        details: [
            'Integre sistemas essenciais sem dependência de lançamentos manuais paralelos.',
            'Unifique vendas, estoque, financeiro e fiscal com atualização consistente entre módulos.',
            'Mantenha trilha de auditoria para cada evento integrado e ação de usuário.',
        ],
    },
];

const metrics = [
    {
        label: 'Tempo por etapa',
        value: 'Meta e variação',
        note: 'Acompanhe prazos por processo e equipe.',
        iconClass: 'ri-timer-flash-line',
        details: [
            'Monitore tempo de início, execução e conclusão por etapa.',
            'Compare desempenho entre equipes e unidades.',
            'Ajuste gargalos operacionais antes de afetar o SLA.',
        ],
    },
    {
        label: 'Trilha operacional',
        value: 'Registro completo',
        note: 'Histórico de decisões e evidências por etapa.',
        iconClass: 'ri-shield-check-line',
        details: [
            'Registre alterações, aprovações e responsáveis automaticamente.',
            'Mantenha histórico auditável para compliance e suporte.',
            'Reduza perda de contexto entre turnos e times.',
        ],
    },
    {
        label: 'Integrações',
        value: 'API e eventos',
        note: 'Sincronização estável entre sistemas externos.',
        iconClass: 'ri-links-line',
        details: [
            'Conecte ERP, fiscal, estoque e vendas com fluxo unificado.',
            'Receba eventos em tempo real para a operação agir rápido.',
            'Evite retrabalho com sincronização bidirecional.',
        ],
    },
    {
        label: 'Indicadores gerenciais',
        value: 'Visão consolidada',
        note: 'KPIs de vendas, estoque e financeiro em tempo real.',
        iconClass: 'ri-line-chart-line',
        details: [
            'Consolide KPIs de diferentes módulos em um único painel.',
            'Acompanhe tendência e performance de forma contínua.',
            'Apoie decisões com dados operacionais atualizados.',
        ],
    },
];

const tabsData = {
    dashboard: {
        label: 'Dashboard',
        header: 'Atividades',
        status: ['Agora', 'Atualizado agora', 'Últimos 5 min'],
        feed: [
            { title: 'Nova venda registrada', meta: 'Pedido #4821 • Loja Centro', badge: 'Nova', tone: 'positive' },
            { title: 'Pagamento conciliado', meta: 'PIX • Pedido #4817', badge: 'Financeiro', tone: 'neutral' },
            { title: 'NF-e autorizada', meta: 'Série 2 • 14:36', badge: 'Fiscal', tone: 'positive' },
            { title: 'Reposição sugerida', meta: 'SKU 1192 • Estoque mínimo', badge: 'Estoque', tone: 'warning' },
            { title: 'Pedido separado', meta: 'Expedição • Pedido #4824', badge: 'Logística', tone: 'neutral' },
            { title: 'Meta diária em 84%', meta: 'Equipe Loja Sul', badge: 'Comercial', tone: 'positive' },
        ],
    },
    fila: {
        label: 'Fila',
        header: 'Fila crítica',
        status: ['Agora', 'Prioridade', 'Últimos 15 min'],
        feed: [
            { title: 'Produto com ruptura', meta: 'SKU 3402 • Sem saldo', badge: 'Estoque', tone: 'warning' },
            { title: 'Conta a pagar vence hoje', meta: 'Fornecedor Atlas • 17:00', badge: 'Financeiro', tone: 'warning' },
            { title: 'Pedido parado na expedição', meta: 'Pedido #4832 • 48 min', badge: 'Logística', tone: 'neutral' },
            { title: 'Divergência de caixa', meta: 'PDV Loja Norte', badge: 'Alerta', tone: 'warning' },
            { title: 'Preço sem atualização', meta: 'Tabela atacado', badge: 'Comercial', tone: 'neutral' },
        ],
    },
    auditoria: {
        label: 'Auditoria',
        header: 'Auditoria',
        status: ['Últimas 24h', 'Atualizado há 3 min', 'Últimas 2h'],
        feed: [
            { title: 'Alteração de preço', meta: 'Usuária Ana • SKU 2201', badge: 'Registro', tone: 'neutral' },
            { title: 'Cancelamento autorizado', meta: 'Pedido #4789 • 2 min', badge: 'Concluído', tone: 'positive' },
            { title: 'Ajuste de estoque', meta: 'Inventário rotativo', badge: 'Estoque', tone: 'neutral' },
            { title: 'Parâmetro fiscal alterado', meta: 'CFOP de saída', badge: 'Fiscal', tone: 'warning' },
            { title: 'Integração reprocessada', meta: 'Webhook ERP', badge: 'Integração', tone: 'positive' },
        ],
    },
};

const tabKeys = Object.keys(tabsData);
const activeTab = ref(tabKeys[0]);
const activityTimer = ref(null);
const tabSwitchTimer = ref(null);
const isSwitchingTab = ref(false);

const selectedHeroCard = ref(null);
const selectedMetricCard = ref(null);

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

const lockBodyScroll = (locked) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = locked ? 'hidden' : '';
};

const syncBodyLock = () => {
    lockBodyScroll(Boolean(selectedHeroCard.value || selectedMetricCard.value));
};

const openHeroCardModal = (card) => {
    selectedHeroCard.value = card;
    syncBodyLock();
};

const closeHeroCardModal = () => {
    selectedHeroCard.value = null;
    syncBodyLock();
};

const openMetricCardModal = (metric) => {
    selectedMetricCard.value = metric;
    syncBodyLock();
};

const closeMetricCardModal = () => {
    selectedMetricCard.value = null;
    syncBodyLock();
};

const onHeroCardKeydown = (event, card) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openHeroCardModal(card);
    }
};

const onPageKeydown = (event) => {
    if (event.key === 'Escape') {
        if (selectedHeroCard.value) {
            closeHeroCardModal();
            return;
        }

        if (selectedMetricCard.value) {
            closeMetricCardModal();
        }
    }
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

const startActivityTimer = () => {
    if (activityTimer.value) {
        window.clearInterval(activityTimer.value);
        activityTimer.value = null;
    }

    activityTimer.value = window.setInterval(() => {
        if (isSwitchingTab.value) return;
        updateFeed(activeTab.value);
    }, 4800);
};

const setActiveTab = (tabId) => {
    if (activeTab.value === tabId) return;

    activeTab.value = tabId;
    isSwitchingTab.value = true;

    if (tabSwitchTimer.value) {
        window.clearTimeout(tabSwitchTimer.value);
    }

    tabSwitchTimer.value = window.setTimeout(() => {
        isSwitchingTab.value = false;
    }, 180);

    startActivityTimer();
};

const metricCarouselRef = ref(null);
const metricCardRefs = ref([]);
const canScrollPrev = ref(false);
const canScrollNext = ref(false);
const activeMetricIndex = ref(0);

const setMetricCardRef = (element, index) => {
    metricCardRefs.value[index] = element;
};

const updateMetricButtons = () => {
    const row = metricCarouselRef.value;
    if (!row) return;

    const maxScroll = row.scrollWidth - row.clientWidth;
    canScrollPrev.value = row.scrollLeft > 4;
    canScrollNext.value = row.scrollLeft < maxScroll - 4;
};

const syncActiveMetricFromScroll = () => {
    const row = metricCarouselRef.value;
    if (!row) return;

    const cards = metricCardRefs.value.filter(Boolean);
    if (!cards.length) return;

    const rowCenter = row.scrollLeft + row.clientWidth / 2;
    let nearestIndex = 0;
    let nearestDistance = Number.POSITIVE_INFINITY;

    cards.forEach((card, index) => {
        const cardCenter = card.offsetLeft + card.clientWidth / 2;
        const distance = Math.abs(cardCenter - rowCenter);
        if (distance < nearestDistance) {
            nearestDistance = distance;
            nearestIndex = index;
        }
    });

    activeMetricIndex.value = nearestIndex;
};

const onMetricScroll = () => {
    updateMetricButtons();
    syncActiveMetricFromScroll();
};

const scrollToMetric = (index, behavior = 'smooth') => {
    const row = metricCarouselRef.value;
    const card = metricCardRefs.value[index];
    if (!row || !card) return;

    const offset = Math.max(0, card.offsetLeft - 4);
    row.scrollTo({ left: offset, behavior });
};

const setActiveMetric = (index, behavior = 'smooth') => {
    if (index < 0 || index >= metrics.length) return;
    activeMetricIndex.value = index;
    scrollToMetric(index, behavior);
};

const onMetricCardKeydown = (event, index, metric) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        setActiveMetric(index);
        openMetricCardModal(metric);
    }
};

const scrollMetricCarousel = (direction) => {
    const nextIndex = Math.min(
        metrics.length - 1,
        Math.max(0, activeMetricIndex.value + direction),
    );

    if (nextIndex === activeMetricIndex.value) return;
    setActiveMetric(nextIndex);
};

onMounted(() => {
    const row = metricCarouselRef.value;

    if (row) {
        row.addEventListener('scroll', onMetricScroll, { passive: true });
    }

    window.addEventListener('keydown', onPageKeydown);
    window.addEventListener('resize', updateMetricButtons);
    startActivityTimer();

    requestAnimationFrame(() => {
        updateMetricButtons();
        setActiveMetric(0, 'auto');
    });
});

onBeforeUnmount(() => {
    const row = metricCarouselRef.value;

    if (row) {
        row.removeEventListener('scroll', onMetricScroll);
    }

    window.removeEventListener('keydown', onPageKeydown);
    window.removeEventListener('resize', updateMetricButtons);

    if (tabSwitchTimer.value) {
        window.clearTimeout(tabSwitchTimer.value);
        tabSwitchTimer.value = null;
    }

    if (activityTimer.value) {
        window.clearInterval(activityTimer.value);
        activityTimer.value = null;
    }

    lockBodyScroll(false);
});
</script>

<template>
    <section class="veshop-hero-app" id="home">
        <div class="veshop-hero-surface" aria-hidden="true"></div>
        <div class="veshop-hero-quarter-secondary" :style="heroQuarterCircleStyle" aria-hidden="true"></div>

        <div class="container veshop-hero-wrap">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <span class="veshop-hero-badge">
                        <i class="ri-store-2-line"></i>
                        ERP para comércios e serviços
                    </span>

                    <h1 class="veshop-hero-title">
                        Gestão completa da sua empresa em uma só plataforma.
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
                            @click="openHeroCardModal(card)"
                            @keydown="onHeroCardKeydown($event, card)"
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

                        <div class="veshop-feed-shell" :class="{ 'is-switching': isSwitchingTab }">
                            <div class="veshop-feed-head">
                                <span>{{ appHeader }}</span>
                                <span>{{ appStatus }}</span>
                            </div>

                            <transition name="ops-pane" mode="out-in">
                                <div :key="activeTab" class="veshop-feed-list">
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
                                </div>
                            </transition>
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
                                    v-for="(metric, index) in metrics"
                                    :key="metric.label"
                                    class="veshop-metric-card"
                                    :class="{ active: activeMetricIndex === index }"
                                    role="button"
                                    tabindex="0"
                                    :ref="(element) => setMetricCardRef(element, index)"
                                    @click="setActiveMetric(index); openMetricCardModal(metric)"
                                    @keydown="onMetricCardKeydown($event, index, metric)"
                                >
                                    <span class="veshop-metric-icon">
                                        <i :class="metric.iconClass"></i>
                                    </span>
                                    <p class="veshop-metric-label">{{ metric.label }}</p>
                                    <p class="veshop-metric-value">{{ metric.value }}</p>
                                    <p class="veshop-metric-note">{{ metric.note }}</p>
                                </article>
                            </div>

                            <div class="veshop-metric-dots">
                                <button
                                    v-for="(_, index) in metrics"
                                    :key="`metric-dot-${index}`"
                                    type="button"
                                    class="veshop-metric-dot"
                                    :class="{ active: activeMetricIndex === index }"
                                    :aria-label="`Ir para card ${index + 1}`"
                                    @click="setActiveMetric(index)"
                                ></button>
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

        <teleport to="body">
            <div
                v-if="selectedHeroCard"
                class="veshop-detail-modal-backdrop"
                role="presentation"
                @click.self="closeHeroCardModal"
            >
                <div
                    class="veshop-detail-modal"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="selectedHeroCard.title"
                >
                    <button
                        type="button"
                        class="veshop-detail-close"
                        aria-label="Fechar"
                        @click="closeHeroCardModal"
                    >
                        <i class="ri-close-line"></i>
                    </button>

                    <div class="veshop-detail-head">
                        <span class="veshop-detail-icon">
                            <i :class="selectedHeroCard.iconClass"></i>
                        </span>
                        <div>
                            <p class="veshop-detail-eyebrow">Veshop</p>
                            <h3 class="veshop-detail-title">{{ selectedHeroCard.title }}</h3>
                        </div>
                    </div>

                    <p class="veshop-detail-description">{{ selectedHeroCard.text }}</p>

                    <ul class="veshop-detail-list">
                        <li v-for="item in selectedHeroCard.details ?? []" :key="item">{{ item }}</li>
                    </ul>
                </div>
            </div>
        </teleport>

        <teleport to="body">
            <div
                v-if="selectedMetricCard"
                class="veshop-detail-modal-backdrop"
                role="presentation"
                @click.self="closeMetricCardModal"
            >
                <div
                    class="veshop-detail-modal"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="selectedMetricCard.label"
                >
                    <button
                        type="button"
                        class="veshop-detail-close"
                        aria-label="Fechar"
                        @click="closeMetricCardModal"
                    >
                        <i class="ri-close-line"></i>
                    </button>

                    <div class="veshop-detail-head">
                        <span class="veshop-detail-icon">
                            <i :class="selectedMetricCard.iconClass"></i>
                        </span>
                        <div>
                            <p class="veshop-detail-eyebrow">Indicador Veshop Ops</p>
                            <h3 class="veshop-detail-title">{{ selectedMetricCard.label }}</h3>
                        </div>
                    </div>

                    <p class="veshop-detail-description">{{ selectedMetricCard.note }}</p>
                    <p class="veshop-detail-value">{{ selectedMetricCard.value }}</p>

                    <ul class="veshop-detail-list">
                        <li v-for="item in selectedMetricCard.details ?? []" :key="item">{{ item }}</li>
                    </ul>
                </div>
            </div>
        </teleport>
    </section>
</template>

<style scoped>
.veshop-hero-app {
    position: relative;
    padding: 170px 0 90px;
    background: linear-gradient(180deg, #f7fdff 0%, #eef8fc 45%, #eaf5f8 100%);
    overflow: hidden;
}

.veshop-hero-surface {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    background:
        radial-gradient(42% 42% at 12% 16%, rgba(129, 216, 111, 0.24) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(46% 46% at 86% 12%, rgba(7, 51, 65, 0.08) 0%, rgba(7, 51, 65, 0) 72%),
        radial-gradient(55% 48% at 50% 92%, rgba(2, 132, 199, 0.09) 0%, rgba(2, 132, 199, 0) 74%);
}

.veshop-hero-quarter-secondary {
    position: absolute;
    width: clamp(1020px, 66vw, 1620px);
    aspect-ratio: 1 / 1;
    right: -28%;
    top: -92%;
    border-radius: 999px;
    pointer-events: none;
    z-index: 0;
    filter: blur(6px);
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

.veshop-mini-card:focus-visible {
    outline: 2px solid rgba(7, 51, 65, 0.48);
    outline-offset: 2px;
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
    min-width: 0;
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
    max-width: 100%;
    overflow-x: auto;
    scrollbar-width: none;
}

.veshop-ops-tabs::-webkit-scrollbar {
    display: none;
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
    min-height: 208px;
    border: 1px solid rgba(245, 253, 255, 0.18);
    border-radius: 14px;
    background: rgba(2, 29, 37, 0.5);
    padding: 12px;
    transition: opacity 0.16s ease;
}

.veshop-feed-shell.is-switching {
    opacity: 0.84;
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

.ops-pane-enter-active,
.ops-pane-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.ops-pane-enter-from,
.ops-pane-leave-to {
    opacity: 0;
    transform: translateY(4px);
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
    min-width: 0;
}

.veshop-feed-title > span:first-child {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.veshop-feed-title .veshop-badge {
    flex-shrink: 0;
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
    scroll-snap-type: x mandatory;
    padding-bottom: 4px;
    scrollbar-width: none;
}

.veshop-metric-track::-webkit-scrollbar {
    display: none;
}

.veshop-metric-card {
    flex: 0 0 166px;
    border: 1px solid rgba(245, 253, 255, 0.18);
    border-radius: 12px;
    background: rgba(245, 253, 255, 0.08);
    padding: 10px;
    cursor: pointer;
    scroll-snap-align: start;
    transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
}

.veshop-metric-card:hover {
    transform: translateY(-1px);
    border-color: rgba(129, 216, 111, 0.34);
}

.veshop-metric-card.active {
    border-color: rgba(129, 216, 111, 0.46);
    background: rgba(129, 216, 111, 0.16);
}

.veshop-metric-card:focus-visible {
    outline: 2px solid rgba(129, 216, 111, 0.62);
    outline-offset: 2px;
}

.veshop-metric-dots {
    margin-top: 10px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
}

.veshop-metric-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    border: 0;
    background: rgba(245, 253, 255, 0.4);
    transition: all 0.2s ease;
}

.veshop-metric-dot.active {
    width: 20px;
    background: #81d86f;
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

.veshop-detail-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1200;
    background: rgba(2, 16, 21, 0.56);
    backdrop-filter: blur(2px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
}

.veshop-detail-modal {
    width: min(560px, 100%);
    border-radius: 18px;
    border: 1px solid rgba(7, 51, 65, 0.2);
    background: linear-gradient(155deg, rgba(255, 255, 255, 0.98), rgba(246, 251, 247, 0.96));
    box-shadow: 0 28px 60px -28px rgba(2, 29, 37, 0.56);
    padding: 18px 18px 16px;
    position: relative;
}

.veshop-detail-close {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1px solid rgba(7, 51, 65, 0.15);
    background: #ffffff;
    color: #073341;
}

.veshop-detail-head {
    display: flex;
    align-items: center;
    gap: 12px;
}

.veshop-detail-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(129, 216, 111, 0.28);
    color: #073341;
    font-size: 20px;
}

.veshop-detail-eyebrow {
    margin: 0;
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #5f7388;
}

.veshop-detail-title {
    margin: 2px 0 0;
    font-size: 20px;
    line-height: 1.25;
    color: #073341;
}

.veshop-detail-description {
    margin: 14px 0 0;
    color: #3f566e;
    font-size: 14px;
    line-height: 1.6;
}

.veshop-detail-value {
    margin: 10px 0 0;
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 5px 10px;
    background: rgba(129, 216, 111, 0.22);
    color: #073341;
    font-size: 12px;
    font-weight: 700;
}

.veshop-detail-list {
    margin: 12px 0 0;
    padding-left: 18px;
    display: grid;
    gap: 8px;
    color: #324a5f;
    font-size: 13px;
}

@media (max-width: 991px) {
    .veshop-hero-app {
        padding: 130px 0 60px;
    }

    .veshop-hero-quarter-secondary {
        width: clamp(700px, 108vw, 1040px);
        right: -52%;
        top: -66%;
        filter: blur(3px);
    }

    .veshop-hero-title {
        font-size: clamp(1.72rem, 5vw, 2.2rem);
        line-height: 1.18;
        max-width: none;
        text-wrap: balance;
    }

    .veshop-hero-subtitle {
        font-size: 15px;
        line-height: 1.52;
        max-width: none;
    }

    .veshop-mini-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575px) {
    .veshop-hero-app {
        padding: 112px 0 52px;
    }

    .veshop-hero-surface {
        background:
            radial-gradient(48% 48% at 86% 12%, rgba(7, 51, 65, 0.08) 0%, rgba(7, 51, 65, 0) 72%),
            radial-gradient(55% 48% at 50% 92%, rgba(2, 132, 199, 0.09) 0%, rgba(2, 132, 199, 0) 74%);
    }

    .veshop-hero-quarter-secondary {
        width: 120vw;
        right: -60vw;
        top: -58vw;
        filter: blur(0);
    }

    .veshop-hero-badge {
        font-size: 10px;
        letter-spacing: 0.08em;
        padding: 7px 12px;
    }

    .veshop-hero-title {
        margin-top: 14px;
        font-size: clamp(1.34rem, 5.9vw, 1.58rem);
        line-height: 1.24;
        max-width: none;
        text-wrap: balance;
    }

    .veshop-hero-subtitle {
        margin-top: 12px;
        font-size: 12.5px;
        line-height: 1.44;
    }

    .veshop-hero-actions .btn {
        width: 100%;
        justify-content: center;
    }

    .veshop-ops-head {
        flex-wrap: wrap;
    }

    .veshop-ops-status {
        margin-left: auto;
    }

    .veshop-mini-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .veshop-metric-arrow {
        display: none;
    }
}

@media (max-width: 360px) {
    .veshop-hero-app {
        padding: 104px 0 44px;
    }

    .veshop-hero-quarter-secondary {
        width: 132vw;
        right: -76vw;
        top: -72vw;
        opacity: 0.9;
    }

    .veshop-hero-badge {
        font-size: 9px;
        letter-spacing: 0.06em;
        gap: 6px;
        padding: 6px 10px;
    }

    .veshop-hero-title {
        margin-top: 12px;
        font-size: clamp(1.24rem, 5.3vw, 1.38rem);
        line-height: 1.26;
    }

    .veshop-hero-subtitle {
        margin-top: 10px;
        font-size: 12px;
        line-height: 1.42;
    }

    .veshop-hero-actions {
        margin-top: 16px;
        gap: 8px;
    }

    .veshop-hero-actions .btn {
        padding: 10px 12px;
        font-size: 0.96rem;
    }

    .veshop-ops-panel {
        padding: 14px;
    }

    .veshop-tab-btn {
        font-size: 11px;
        padding: 5px 8px;
    }

    .veshop-feed-title {
        font-size: 12px;
    }

    .veshop-feed-meta {
        font-size: 11px;
    }

    .veshop-metric-card {
        flex-basis: 148px;
    }
}
</style>
