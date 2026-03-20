<script setup>
import { computed } from 'vue';
import { useBranding } from '@/branding';

const flowSteps = [
    {
        iconClass: 'ri-donut-chart-fill',
        title: 'Cadastro e precificação',
        description:
            'Configure produtos, tabelas de preço, impostos e regras comerciais por tipo de negócio.',
    },
    {
        iconClass: 'ri-database-line',
        title: 'Venda e atendimento',
        description:
            'Registre vendas no PDV, pedidos e orçamentos com histórico completo de clientes e negociações.',
    },
    {
        iconClass: 'ri-focus-2-fill',
        title: 'Movimento de estoque',
        description:
            'Controle entradas, saídas, inventário e reposição com visão precisa por produto e filial.',
    },
    {
        iconClass: 'ri-team-fill',
        title: 'Financeiro e fiscal',
        description:
            'Acompanhe caixa, contas, conciliação e obrigações fiscais em relatórios claros para decisão.',
    },
];

const { landingImages } = useBranding();
const workImageUrl = computed(() => landingImages.value.work || '/landing/images/group-working.png');
</script>

<template>
    <section class="section work-section">
        <div class="container">
            <div class="row align-items-center justify-content-between">
                <div class="col-lg-6">
                    <div class="work-image-area">
                        <div class="work-image-frame">
                            <img
                                :src="workImageUrl"
                                alt="Equipe executando fluxo operacional com o Veshop"
                                class="work-image"
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
                            Com processos integrados, sua operação ganha velocidade e previsibilidade.
                            Cada etapa conversa com a próxima sem depender de planilhas paralelas.
                        </p>
                    </div>
                    <div class="row align-items-center justify-content-center">
                        <div v-for="step in flowSteps" :key="step.title" class="col-lg-6">
                            <div class="icon-text mt-4">
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
    overflow: visible;
}

.work-image-area {
    position: relative;
    overflow: visible;
}

.work-image-frame {
    position: relative;
    padding: 16px;
    border-radius: 32px;
    background: #92e7a1;
    box-shadow: 0 18px 40px rgba(107, 187, 32, 0.18);
    isolation: isolate;
}

/* bloco decorativo de trás */
.work-image-frame::before {
    content: "";
    position: absolute;
    top: 18px;
    left: 18px;
    right: -14px;
    bottom: -14px;
    background: rgba(36, 129, 24, 0.18);
    border-radius: 36px;
    z-index: -1;
}

/* linha/borda decorativa extra */
.work-image-frame::after {
    content: "";
    position: absolute;
    top: -10px;
    left: 10px;
    right: -10px;
    bottom: 10px;
    border: 1px solid rgba(8, 103, 32, 0.12);
    border-radius: 36px;
    z-index: -1;
}

.work-image {
    display: block;
    width: 100%;
    height: 420px;
    object-fit: cover;
    border-radius: 18px;
}

@media (max-width: 991px) {
    .work-image {
        height: auto;
    }

    .work-image-frame::before,
    .work-image-frame::after {
        display: none;
    }
}
</style>