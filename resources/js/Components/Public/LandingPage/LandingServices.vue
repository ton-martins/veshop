<script setup>
const mainModules = [
    {
        number: '01',
        title: 'Vendas, PDV e pedidos',
        description:
            'Registre atendimentos, orçamentos e pedidos com rapidez. Tenha histórico de clientes, acompanhamento de metas e mais eficiência no fechamento de vendas.',
    },
    {
        number: '02',
        title: 'Estoque e financeiro',
        description:
            'Automatize contas a pagar e receber, fluxo de caixa, conciliação e controle de estoque para reduzir perdas e aumentar margem.',
    },
];

const extraModules = [
    {
        iconClass: 'ri-heart-2-line',
        label: 'Emissão de NF-e e NFC-e',
    },
    {
        iconClass: 'ri-pages-fill',
        label: 'Orçamentos e CRM',
    },
    {
        iconClass: 'ri-database-fill',
        label: 'SPED e relatórios gerenciais',
    },
];
</script>

<template>
    <section class="section services-section" id="services">
        <div class="container">
            <div class="pb-4">
                <div class="row align-items-center justify-content-evenly">
                    <div class="col-lg-5">
                        <div class="title-sm">
                            <span>Módulos do sistema</span>
                        </div>
                        <div class="section-title-border mt-3 m-0"></div>
                        <div class="service-title mt-4">
                            <h2 class="fw-semibold text-primary lh-base">
                                Recursos que sustentam a operação do seu negócio
                            </h2>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <p class="mt-5 text-primary">
                            Com base nas necessidades do varejo moderno, o Veshop entrega controle comercial, fiscal e
                            gerencial com foco em produtividade e crescimento sustentável.
                        </p>
                        <a href="#services" class="text-success">
                            Ver todos os módulos <i class="ri-arrow-right-line"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row align-items-center justify-content-evenly mt-4 g-4">
                <div v-for="item in mainModules" :key="item.number" class="col-lg-5">
                    <div class="services-content p-3 shadow-sm">
                        <span class="text-success">{{ item.number }}</span>
                        <div class="title-sm mt-2">
                            <span class="text-primary">{{ item.title }}</span>
                        </div>
                        <p class="mt-2">{{ item.description }}</p>
                    </div>
                </div>
            </div>

            <div class="row align-items-center justify-content-evenly mt-5 g-4">
                <div v-for="item in extraModules" :key="item.label" class="col-lg-3">
                    <div class="services-content-1 p-2 rounded-pill">
                        <i :class="[item.iconClass, 'text-primary bg-success-subtle']"></i>
                        <span class="ms-4 fs-2xl">{{ item.label }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
