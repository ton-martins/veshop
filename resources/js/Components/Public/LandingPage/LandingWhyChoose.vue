<script setup>
const advantages = [
    'Multiempresa e multiusuário',
    'Parametrização por segmento',
    'Controle fiscal e contábil',
    'Escalável para crescimento',
];
</script>

<template>
    <section class="section why-choose-section bg-light">
        <div class="container">
            <div class="row align-items-center justify-content-between">
                <div class="col-lg-5">
                    <div class="title-sm">
                        <span>Por que escolher</span>
                    </div>
                    <div class="section-title-border mt-3 m-0"></div>
                    <div class="why-choose-title mt-4">
                        <h2 class="fw-semibold text-primary lh-base">ERP focado em resultado operacional</h2>
                        <p>
                            Padronize processos, reduza retrabalho e acompanhe indicadores em tempo real.
                            O Veshop ajuda a equipe a operar melhor no dia a dia.
                        </p>
                    </div>

                    <div class="row mt-4 g-4">
                        <div v-for="item in advantages" :key="item" class="col-lg-6 col-6">
                            <div class="d-flex align-items-center">
                                <i class="ri-checkbox-blank-circle-fill text-success fs-4"></i>
                                <h6 class="ms-3 m-0">{{ item }}</h6>
                            </div>
                        </div>
                        <div class="main-btn mt-5 d-flex">
                            <a href="#contacts" class="btn btn-primary fw-semibold">Falar com especialista</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <img
                        src="/landing/images/working.jpg"
                        alt="Equipe analisando indicadores no Veshop"
                        class="img-fluid mt-4 shadow-sm rounded-2"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
