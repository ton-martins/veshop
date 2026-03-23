<script setup>
const solutionCards = [
    {
        iconClass: 'ri-store-3-line',
        title: 'Operação comercial',
        bullets: ['PDV para comércio', 'Produtos e categorias', 'Pedidos com status'],
    },
    {
        iconClass: 'ri-calendar-check-line',
        title: 'Operação de serviços',
        bullets: ['Agenda por data e horário', 'Serviços e categorias', 'Ordens de serviço'],
    },
    {
        iconClass: 'ri-bank-card-line',
        title: 'Financeiro integrado',
        bullets: ['Contas a pagar e receber', 'Lançamentos e conciliação', 'Visão de caixa'],
    },
    {
        iconClass: 'ri-line-chart-line',
        title: 'Gestão e governança',
        bullets: ['Relatórios operacionais', 'Acessos por perfil', 'Isolamento por contratante'],
    },
];
</script>

<template>
    <section class="section services-section landing-section-shell" id="services">
        <div class="services-glow" aria-hidden="true"></div>
        <div class="container position-relative">
            <div class="row align-items-end justify-content-between g-3">
                <div class="col-lg-8">
                    <div class="title-sm">
                        <span>Soluções por etapa</span>
                    </div>
                    <div class="section-title-border mt-3 m-0"></div>
                    <div class="service-title mt-4">
                        <h2 class="fw-semibold text-primary lh-base">
                            Soluções diretas para cada frente da operação.
                        </h2>
                    </div>
                </div>
            </div>

            <div class="row mt-4 g-3">
                <div v-for="item in solutionCards" :key="item.title" class="col-md-6 col-xl-3">
                    <article class="solution-card h-100">
                        <span class="solution-icon">
                            <i :class="item.iconClass"></i>
                        </span>
                        <h3>{{ item.title }}</h3>
                        <ul>
                            <li v-for="bullet in item.bullets" :key="bullet">
                                <i class="ri-checkbox-circle-fill"></i>
                                <span>{{ bullet }}</span>
                            </li>
                        </ul>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.services-section {
    --next-section-bg: linear-gradient(180deg, #08242c 0%, #0b333c 100%);
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(70% 80% at 88% 4%, rgba(129, 216, 111, 0.24) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(55% 55% at 8% 42%, rgba(52, 182, 130, 0.18) 0%, rgba(52, 182, 130, 0) 74%),
        linear-gradient(180deg, #08242c 0%, #0b333c 100%);
}

.services-glow {
    position: absolute;
    width: clamp(420px, 54vw, 860px);
    height: clamp(420px, 54vw, 860px);
    border-radius: 999px;
    left: -22%;
    top: -56%;
    background: radial-gradient(circle at center, rgba(129, 216, 111, 0.24) 0%, rgba(129, 216, 111, 0) 76%);
    pointer-events: none;
}

.service-title h2 {
    color: #effff3 !important;
}

.solution-card {
    border: 1px solid rgba(210, 255, 223, 0.2);
    border-radius: 22px;
    background: rgba(212, 255, 224, 0.08);
    padding: 1.28rem;
    box-shadow: 0 18px 34px -28px rgba(2, 12, 10, 0.95);
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.solution-card:hover {
    transform: none;
    border-color: rgba(210, 255, 223, 0.2);
    box-shadow: 0 18px 34px -28px rgba(2, 12, 10, 0.95);
}

.solution-icon {
    width: auto;
    height: auto;
    border-radius: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    color: inherit;
}

.solution-icon i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
    border-radius: 10px;
    background: rgba(129, 216, 111, 0.2);
    color: #073341;
    font-size: 1.1rem;
    line-height: 1;
}

.solution-card h3 {
    margin-top: 0.8rem;
    margin-bottom: 0;
    font-size: 1.18rem;
    color: #effff3;
    font-weight: 700;
}

.solution-card ul {
    margin: 0.95rem 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 0.65rem;
}

.solution-card li {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    color: rgba(236, 255, 242, 0.86);
    font-size: 0.95rem;
    line-height: 1.4;
}

.solution-card li i {
    color: #9ce1a8;
    margin-top: 0.08rem;
    font-size: 1.02rem;
}
</style>
