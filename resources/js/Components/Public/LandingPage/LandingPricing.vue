<script setup>
const plans = [
    {
        name: 'ESSENCIAL',
        price: 'R$ 149',
        buttonClass: 'btn btn-primary w-100 fw-semibold',
        cardClass: 'card shadow-sm h-100 border-0 border-primary border-top border-3',
        titleClass: 'fw-bold text-primary',
        priceClass: 'display-6 my-4 fw-bold text-primary',
        features: [
            'PDV e vendas',
            'Estoque básico',
            'Financeiro e caixa',
            'Emissão de NFC-e',
            'Suporte em horário comercial',
        ],
    },
    {
        name: 'PROFISSIONAL',
        price: 'R$ 299',
        buttonClass: 'btn btn-success w-100 fw-semibold',
        cardClass: 'card shadow h-100 border-0 border-success border-top border-3',
        titleClass: 'fw-bold text-success',
        priceClass: 'display-6 my-4 fw-bold text-success',
        features: [
            'Tudo do Essencial',
            'Orçamentos e pedidos',
            'Estoque avançado',
            'Emissão de NF-e',
            'Suporte em horário comercial',
        ],
    },
    {
        name: 'ENTERPRISE',
        price: 'R$ 549',
        buttonClass: 'btn btn-primary w-100 fw-semibold',
        cardClass: 'card shadow-sm h-100 border-0 border-primary border-top border-3',
        titleClass: 'fw-bold text-primary',
        priceClass: 'display-6 my-4 fw-bold text-primary',
        features: [
            'Multiempresa e filiais',
            'Fiscal avançado e SPED',
            'Automações e API',
            'Emissão de NFC-e',
            'Suporte em horário comercial',
        ],
    },
];
</script>

<template>
    <section class="section price-section position-relative z-1" id="price">
        <div class="container">
            <div class="row align-items-center justify-content-center text-center">
                <div class="col-lg-7">
                    <div class="title-sm">
                        <span>Planos</span>
                    </div>
                    <div class="section-title-border mt-3"></div>
                    <div class="price-title mt-4">
                        <h2 class="fw-semibold text-primary">Planos flexíveis para cada fase do seu negócio</h2>
                    </div>
                </div>
            </div>

            <div class="row mt-5 g-4">
                <div v-for="plan in plans" :key="plan.name" class="col-md-6 col-lg-4">
                    <div :class="plan.cardClass">
                        <div class="card-body text-center">
                            <h5 :class="plan.titleClass">{{ plan.name }}</h5>
                            <p>Plano mensal</p>
                            <div class="price">
                                <h2 :class="plan.priceClass">
                                    {{ plan.price }} <span class="fs-6 text-muted">/ mês</span>
                                </h2>
                            </div>
                            <div class="price-info-3">
                                <ul>
                                    <li v-for="feature in plan.features" :key="feature">
                                        <i class="ri-checkbox-circle-fill text-success me-2"></i>{{ feature }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-footer border-0 bg-white">
                            <div class="price-btn text-center">
                                <a href="/login" :class="plan.buttonClass">Solicitar demonstração</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <img src="/landing/images/Wave.svg" alt="" class="img-fluid w-100 shape z-n1" />
    </section>
</template>
