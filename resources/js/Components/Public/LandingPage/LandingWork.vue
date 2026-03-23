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
    padding: 14px;
}

.photo-shell::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 32px;
    background: linear-gradient(145deg, rgba(166, 244, 188, 0.54) 0%, rgba(34, 114, 89, 0.42) 48%, rgba(6, 47, 42, 0.72) 100%);
    z-index: -2;
}

.photo-shell::after {
    content: '';
    position: absolute;
    inset: 12px -10px -10px 12px;
    border-radius: 30px;
    border: 1px solid rgba(157, 227, 173, 0.34);
    background: linear-gradient(150deg, rgba(7, 43, 38, 0.36), rgba(9, 58, 49, 0.2));
    z-index: -3;
}

.photo-shell-glow {
    position: absolute;
    inset: -12% -8% auto;
    height: 72%;
    border-radius: 32px;
    background: radial-gradient(circle at center, rgba(129, 216, 111, 0.3) 0%, rgba(129, 216, 111, 0) 72%);
    z-index: -1;
}

.photo-shell-frame {
    position: relative;
    border-radius: 26px;
    padding: 10px;
    border: 1px solid rgba(202, 255, 218, 0.46);
    background: linear-gradient(156deg, rgba(8, 46, 40, 0.8), rgba(9, 61, 53, 0.58));
    box-shadow: 0 28px 44px -34px rgba(3, 18, 14, 0.62);
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
    border-radius: 18px;
    border: 1px solid rgba(214, 255, 223, 0.2);
    background: rgba(214, 255, 223, 0.08);
    padding: 0.8rem;
    height: 100%;
    box-shadow: 0 16px 30px -24px rgba(2, 12, 10, 0.88);
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.icon-text:hover {
    transform: translateY(-4px);
    border-color: rgba(214, 255, 223, 0.34);
    box-shadow: 0 22px 34px -24px rgba(2, 12, 10, 0.96);
}

.icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(129, 216, 111, 0.26);
    color: #073341;
    font-size: 1rem;
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
