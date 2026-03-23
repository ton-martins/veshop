<script setup>
import { computed } from 'vue';
import { useBranding } from '@/branding';

const flowSteps = [
    {
        iconClass: 'ri-donut-chart-fill',
        title: 'Cadastro e precificação',
        description:
            'Cadastre catálogo, preços e regras comerciais por nicho.',
    },
    {
        iconClass: 'ri-database-line',
        title: 'Venda e atendimento',
        description:
            'Execute PDV, pedidos e atendimentos com histórico centralizado.',
    },
    {
        iconClass: 'ri-focus-2-fill',
        title: 'Movimento de estoque',
        description:
            'Controle entradas, saídas e reposição com precisão.',
    },
    {
        iconClass: 'ri-team-fill',
        title: 'Financeiro e fiscal',
        description:
            'Acompanhe caixa, contas e fiscal com relatórios objetivos.',
    },
];

const { landingImages } = useBranding();
const workImageUrl = computed(() => landingImages.value.work || '/landing/images/group-working.png');
</script>

<template>
    <section class="section work-section landing-section-shell">
        <div class="container">
            <div class="row align-items-center justify-content-between g-4">
                <div class="col-lg-6">
                    <div class="photo-shell">
                        <div class="photo-shell-glow" aria-hidden="true"></div>
                        <div class="photo-shell-frame">
                            <img
                                :src="workImageUrl"
                                alt="Equipe executando fluxo operacional com o Veshop"
                                class="photo-image"
                            />
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="title-sm">
                        <span>Como funciona</span>
                    </div>
                    <div class="section-title-border mt-3 m-0"></div>
                    <div class="why-choose-title mt-4">
                        <h2 class="fw-semibold text-primary lh-base">Da venda ao fechamento financeiro em fluxo único</h2>
                        <p>
                            Cada etapa conversa com a próxima, sem planilhas paralelas
                            e sem quebra de contexto operacional.
                        </p>
                    </div>

                    <div class="row align-items-center justify-content-center g-3 mt-1">
                        <div v-for="step in flowSteps" :key="step.title" class="col-lg-6 col-sm-6">
                            <div class="icon-text">
                                <div class="icon">
                                    <i :class="step.iconClass"></i>
                                </div>
                                <h6 class="fw-semibold mt-3">{{ step.title }}</h6>
                                <p>{{ step.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.work-section {
    --next-section-bg: linear-gradient(180deg, #08242c 0%, #0c363f 100%);
    position: relative;
    background:
        radial-gradient(70% 70% at 10% 6%, rgba(129, 216, 111, 0.24) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(50% 50% at 88% 40%, rgba(52, 182, 130, 0.18) 0%, rgba(52, 182, 130, 0) 72%),
        linear-gradient(180deg, #08242c 0%, #0b333c 100%);
}

.photo-shell {
    position: relative;
    isolation: isolate;
    padding: 10px;
}

.photo-shell::before {
    content: '';
    position: absolute;
    inset: 0 0 8px 8px;
    border-radius: 28px;
    border: 1px solid rgba(7, 51, 65, 0.2);
    background: transparent;
    z-index: -2;
}

.photo-shell::after {
    content: '';
    position: absolute;
    inset: 8px;
    border-radius: 24px;
    border: 1px solid rgba(7, 51, 65, 0.1);
    background: transparent;
    z-index: -3;
}

.photo-shell-glow {
    display: none;
}

.photo-shell-frame {
    position: relative;
    border-radius: 22px;
    padding: 8px;
    border: 1px solid rgba(7, 51, 65, 0.16);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(245, 253, 255, 0.94));
    box-shadow: 0 18px 28px -24px rgba(20, 24, 34, 0.24);
}

.photo-image {
    width: 100%;
    height: 420px;
    object-fit: cover;
    border-radius: 18px;
}

.why-choose-title p {
    margin-top: 0.9rem;
    margin-bottom: 0;
    color: rgba(234, 255, 241, 0.8);
    line-height: 1.62;
}

.why-choose-title h2 {
    color: #effff3 !important;
}

.icon-text {
    border-radius: 0;
    border: 0;
    background: transparent;
    padding: 0.35rem 0;
    height: 100%;
    box-shadow: none;
    transition: none;
}

.icon-text:hover {
    transform: none;
    border-color: transparent;
    box-shadow: none;
}

.icon {
    width: auto;
    height: auto;
    border-radius: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    color: inherit;
}

.icon i {
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

.icon-text h6 {
    color: #effff3;
    font-size: 0.9rem;
    line-height: 1.35;
}

.icon-text p {
    margin-top: 0.34rem;
    margin-bottom: 0;
    color: rgba(234, 255, 241, 0.8);
    font-size: 0.82rem;
    line-height: 1.44;
}

@media (max-width: 991.98px) {
    .photo-image {
        height: auto;
        max-height: 430px;
    }
}
</style>
