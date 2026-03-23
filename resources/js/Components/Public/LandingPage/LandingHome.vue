<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const heroHighlights = [
    {
        icon: 'ri-links-line',
        title: 'Fluxo unificado',
        text: 'ERP e financeiro no mesmo fluxo.',
    },
    {
        icon: 'ri-store-3-line',
        title: 'Lógica por nicho',
        text: 'Comércio e serviços com regras próprias.',
    },
    {
        icon: 'ri-line-chart-line',
        title: 'Gestão em tempo real',
        text: 'Indicadores para decisão rápida.',
    },
];

const tabsData = {
    tarefas: {
        label: 'Tarefas',
        status: ['Agora', 'Atualizado agora', 'Últimos 5 min'],
        feed: [
            { title: 'Conferir pedidos pendentes', meta: '4 pedidos aguardando separação', tone: 'warning', badge: 'Operação' },
            { title: 'Aprovar contas a pagar', meta: '3 vencimentos para hoje', tone: 'neutral', badge: 'Financeiro' },
            { title: 'Repor itens críticos', meta: '2 SKUs abaixo do mínimo', tone: 'warning', badge: 'Estoque' },
            { title: 'Validar fechamento de caixa', meta: 'Loja Centro • turno da manhã', tone: 'positive', badge: 'PDV' },
            { title: 'Revisar agenda de atendimentos', meta: '6 compromissos confirmados', tone: 'neutral', badge: 'Serviços' },
        ],
    },
    atividades: {
        label: 'Atividades',
        status: ['Em execução', 'Sincronizado', 'Tempo real'],
        feed: [
            { title: 'Venda concluída com sucesso', meta: 'Pedido #4831 • R$ 426,90', tone: 'positive', badge: 'Comercial' },
            { title: 'Pagamento conciliado', meta: 'PIX recebido há 2 min', tone: 'positive', badge: 'Financeiro' },
            { title: 'NF-e autorizada', meta: 'Série 3 • número 18291', tone: 'positive', badge: 'Fiscal' },
            { title: 'Atendimento iniciado', meta: 'Cliente Marina Silva • 14:00', tone: 'neutral', badge: 'Agenda' },
            { title: 'Ordem de serviço finalizada', meta: 'OS #991 • equipe técnica', tone: 'positive', badge: 'Serviços' },
        ],
    },
};

const tabOrder = Object.keys(tabsData);
const activeTab = ref(tabOrder[0]);
const feedByTab = ref({});
const statusIndexByTab = ref({});

const feedCursorByTab = {};
const activityTimer = ref(null);
let itemCounter = 0;

const makeFeedItem = (entry) => ({
    id: `${Date.now()}-${itemCounter++}`,
    title: entry.title,
    meta: entry.meta,
    tone: entry.tone ?? 'neutral',
    badge: entry.badge ?? 'Atualização',
});

const initializeTabs = () => {
    const initialFeed = {};
    const initialStatus = {};

    tabOrder.forEach((tabId) => {
        const source = tabsData[tabId].feed;
        initialFeed[tabId] = source.slice(0, 3).map(makeFeedItem);
        initialStatus[tabId] = 0;
        feedCursorByTab[tabId] = 3;
    });

    feedByTab.value = initialFeed;
    statusIndexByTab.value = initialStatus;
};

initializeTabs();

const currentFeed = computed(() => feedByTab.value[activeTab.value] ?? []);
const currentStatus = computed(() => {
    const tab = tabsData[activeTab.value];
    const idx = statusIndexByTab.value[activeTab.value] ?? 0;
    return tab.status[idx % tab.status.length];
});

const updateTabFeed = (tabId) => {
    const tab = tabsData[tabId];
    if (!tab) return;

    const nextIndex = feedCursorByTab[tabId] % tab.feed.length;
    feedCursorByTab[tabId] += 1;

    const nextItem = makeFeedItem(tab.feed[nextIndex]);
    const currentItems = feedByTab.value[tabId] ?? [];
    feedByTab.value[tabId] = [nextItem, ...currentItems].slice(0, 3);

    const nextStatus = (statusIndexByTab.value[tabId] ?? 0) + 1;
    statusIndexByTab.value[tabId] = nextStatus % tab.status.length;
};

const setActiveTab = (tabId) => {
    if (!tabsData[tabId]) return;
    activeTab.value = tabId;
    updateTabFeed(tabId);
};

const cycleTab = () => {
    const currentIndex = tabOrder.indexOf(activeTab.value);
    const nextTabId = tabOrder[(currentIndex + 1) % tabOrder.length];
    setActiveTab(nextTabId);
};

const startTimer = () => {
    if (activityTimer.value) {
        window.clearInterval(activityTimer.value);
    }

    activityTimer.value = window.setInterval(() => {
        cycleTab();
    }, 4600);
};

onMounted(() => {
    startTimer();
});

onBeforeUnmount(() => {
    if (activityTimer.value) {
        window.clearInterval(activityTimer.value);
        activityTimer.value = null;
    }
});
</script>

<template>
    <section id="home" class="landing-hero landing-section-shell">
        <div class="hero-glow hero-glow-left" aria-hidden="true"></div>
        <div class="hero-glow hero-glow-right" aria-hidden="true"></div>

        <div class="container position-relative">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <span class="hero-pill">
                        <i class="ri-sparkling-2-line"></i>
                        Plataforma empresarial premium
                    </span>

                    <h1 class="hero-title">
                        Gestão de ponta,
                        <span>sem ruído na operação.</span>
                    </h1>

                    <p class="hero-subtitle">
                        O Veshop unifica rotina comercial e serviços em um ERP modular,
                        com execução fluida e governança completa.
                    </p>

                    <div class="hero-actions">
                        <Link :href="route('login')" class="btn hero-btn hero-btn-primary">
                            Entrar no Veshop
                            <i class="ri-arrow-right-up-line"></i>
                        </Link>
                        <a href="#price" class="btn hero-btn hero-btn-secondary">
                            Ver planos
                            <i class="ri-price-tag-3-line"></i>
                        </a>
                    </div>

                    <div class="hero-highlights">
                        <article v-for="item in heroHighlights" :key="item.title" class="hero-highlight-card">
                            <span class="hero-highlight-icon" aria-hidden="true">
                                <i :class="item.icon"></i>
                            </span>
                            <p class="hero-highlight-title">{{ item.title }}</p>
                            <p class="hero-highlight-text">{{ item.text }}</p>
                        </article>
                    </div>
                </div>

                <div class="col-lg-6">
                    <article class="hero-floating-card">
                        <header class="floating-head">
                            <div>
                                <p class="mb-0 head-title">Painel dinâmico</p>
                                <small class="head-subtitle">Tarefas e atividades da operação</small>
                            </div>
                            <span class="head-status">
                                <span class="dot"></span>
                                {{ currentStatus }}
                            </span>
                        </header>

                        <div class="floating-tabs" role="tablist" aria-label="Alternar visão do painel">
                            <button
                                v-for="tabId in tabOrder"
                                :key="tabId"
                                type="button"
                                class="floating-tab-btn"
                                :class="{ 'is-active': activeTab === tabId }"
                                @click="setActiveTab(tabId)"
                            >
                                {{ tabsData[tabId].label }}
                            </button>
                        </div>

                        <transition name="feed-switch" mode="out-in">
                            <div :key="activeTab" class="floating-feed-list">
                                <article
                                    v-for="item in currentFeed"
                                    :key="item.id"
                                    class="floating-feed-item"
                                >
                                    <span class="item-dot"></span>
                                    <div class="item-copy">
                                        <p class="item-title">
                                            <span>{{ item.title }}</span>
                                            <span class="item-badge" :class="item.tone">{{ item.badge }}</span>
                                        </p>
                                        <p class="item-meta">{{ item.meta }}</p>
                                    </div>
                                </article>
                            </div>
                        </transition>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.landing-hero {
    --next-section-bg: linear-gradient(180deg, #08242c 0%, #0a2f37 100%);
    position: relative;
    overflow: hidden;
    padding: 170px 0 118px;
    background:
        radial-gradient(68% 68% at 10% 5%, rgba(129, 216, 111, 0.26) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(55% 55% at 88% 8%, rgba(42, 174, 140, 0.22) 0%, rgba(42, 174, 140, 0) 70%),
        linear-gradient(180deg, #061b21 0%, #0a2a33 48%, #0f3b46 100%);
}

.hero-glow {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
}

.hero-glow-left {
    width: clamp(320px, 38vw, 620px);
    height: clamp(320px, 38vw, 620px);
    left: -12%;
    top: -26%;
    background: radial-gradient(circle at center, rgba(129, 216, 111, 0.24) 0%, rgba(129, 216, 111, 0) 74%);
}

.hero-glow-right {
    width: clamp(320px, 42vw, 660px);
    height: clamp(320px, 42vw, 660px);
    right: -18%;
    bottom: -46%;
    background: radial-gradient(circle at center, rgba(87, 213, 141, 0.2) 0%, rgba(87, 213, 141, 0) 74%);
}

.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    border-radius: 999px;
    border: 1px solid rgba(214, 255, 223, 0.45);
    background: rgba(214, 255, 223, 0.13);
    color: #dffbe6;
    padding: 0.4rem 0.82rem;
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.hero-title {
    margin-top: 1rem;
    margin-bottom: 0;
    color: #f4fff7;
    font-size: clamp(2rem, 4.2vw, 3.4rem);
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.hero-title span {
    display: block;
    color: #a8f0bb;
}

.hero-subtitle {
    margin-top: 1rem;
    margin-bottom: 0;
    color: rgba(240, 255, 246, 0.86);
    font-size: 0.98rem;
    line-height: 1.58;
    max-width: 570px;
}

.hero-actions {
    margin-top: 1.45rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.7rem;
}

.hero-btn {
    border-radius: 10px;
    font-weight: 700;
    padding: 0.7rem 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.hero-btn-primary {
    background: #9ce1a8;
    border: 1px solid #9ce1a8;
    color: #072016;
}

.hero-btn-primary:hover {
    background: #8bd299;
    border-color: #8bd299;
    color: #072016;
}

.hero-btn-secondary {
    border: 1px solid rgba(240, 255, 246, 0.3);
    color: #effef2;
}

.hero-btn-secondary:hover {
    border-color: rgba(240, 255, 246, 0.55);
    color: #effef2;
}

.hero-highlights {
    margin: 1.25rem 0 0;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.58rem;
}

.hero-highlight-card {
    border-radius: 14px;
    border: 1px solid rgba(223, 255, 233, 0.22);
    background: transparent;
    padding: 0.62rem 0.68rem;
    min-width: 0;
}

.hero-highlight-icon {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(156, 225, 168, 0.18);
    color: #bcf5ca;
    font-size: 0.88rem;
}

.hero-highlight-title {
    margin: 0.36rem 0 0;
    color: #e8ffef;
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.35;
}

.hero-highlight-text {
    margin: 0.22rem 0 0;
    color: rgba(234, 255, 241, 0.78);
    font-size: 0.72rem;
    line-height: 1.4;
}

.hero-floating-card {
    border-radius: 28px;
    border: 1px solid rgba(200, 255, 220, 0.34);
    background: linear-gradient(160deg, rgba(10, 40, 32, 0.94), rgba(6, 25, 23, 0.9));
    box-shadow: 0 36px 54px -34px rgba(3, 12, 10, 0.95);
    backdrop-filter: blur(2px);
    padding: 1.05rem;
    animation: floatingCard 4.5s ease-in-out infinite;
}

.hero-floating-card:hover {
    box-shadow: 0 40px 58px -34px rgba(3, 12, 10, 0.98);
}

.floating-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
}

.head-title {
    color: #f4fff7;
    font-weight: 700;
}

.head-subtitle {
    color: rgba(232, 255, 239, 0.75);
    font-size: 0.74rem;
}

.head-status {
    border-radius: 999px;
    background: rgba(129, 216, 111, 0.26);
    border: 1px solid rgba(129, 216, 111, 0.45);
    color: #d9f9e0;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.24rem 0.58rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.head-status .dot {
    width: 0.42rem;
    height: 0.42rem;
    border-radius: 999px;
    background: #8fe59f;
}

.floating-tabs {
    margin-top: 0.78rem;
    border-radius: 11px;
    border: 1px solid rgba(211, 255, 222, 0.18);
    background: rgba(211, 255, 222, 0.05);
    padding: 0.25rem;
    display: inline-flex;
    gap: 0.24rem;
}

.floating-tab-btn {
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: rgba(233, 255, 240, 0.75);
    font-size: 0.76rem;
    font-weight: 700;
    padding: 0.35rem 0.65rem;
}

.floating-tab-btn.is-active {
    background: rgba(129, 216, 111, 0.28);
    color: #effff4;
}

.feed-switch-enter-active,
.feed-switch-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}

.feed-switch-enter-from,
.feed-switch-leave-to {
    opacity: 0;
    transform: translateY(4px);
}

.floating-feed-list {
    margin-top: 0.75rem;
    display: grid;
    gap: 0.46rem;
    min-height: 170px;
}

.floating-feed-item {
    border-radius: 11px;
    border: 1px solid rgba(211, 255, 222, 0.14);
    background: rgba(211, 255, 222, 0.06);
    padding: 0.58rem;
    display: grid;
    grid-template-columns: 9px 1fr;
    gap: 0.5rem;
}

.item-dot {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    margin-top: 0.36rem;
    background: #8fe59f;
}

.item-copy {
    min-width: 0;
}

.item-title {
    margin: 0;
    color: #f3fff6;
    font-size: 0.84rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.item-title > span:first-child {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.item-meta {
    margin: 0.2rem 0 0;
    color: rgba(238, 255, 243, 0.75);
    font-size: 0.75rem;
}

.item-badge {
    border-radius: 999px;
    padding: 0.14rem 0.42rem;
    font-size: 0.64rem;
    font-weight: 700;
    border: 1px solid transparent;
    white-space: nowrap;
}

.item-badge.positive {
    color: #ddffe5;
    border-color: rgba(129, 216, 111, 0.35);
    background: rgba(129, 216, 111, 0.16);
}

.item-badge.warning {
    color: #ffe9bc;
    border-color: rgba(255, 193, 7, 0.32);
    background: rgba(255, 193, 7, 0.15);
}

.item-badge.neutral {
    color: rgba(245, 253, 255, 0.9);
    border-color: rgba(245, 253, 255, 0.28);
    background: rgba(245, 253, 255, 0.12);
}

@keyframes floatingCard {
    0% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
    100% {
        transform: translateY(0);
    }
}

@media (max-width: 991.98px) {
    .landing-hero {
        padding: 136px 0 84px;
    }

    .hero-floating-card {
        animation: none;
    }

    .hero-highlights {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575.98px) {
    .hero-title {
        font-size: clamp(1.66rem, 8.1vw, 2.1rem);
    }

    .hero-subtitle {
        font-size: 0.9rem;
    }

    .hero-btn {
        width: 100%;
        justify-content: center;
    }

    .floating-feed-list {
        min-height: 158px;
    }

    .hero-highlights {
        grid-template-columns: 1fr;
    }
}
</style>
