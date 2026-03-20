<script setup>
import { computed } from 'vue';
import { useBranding } from '@/branding';

const advantages = [
    'Operações multi-tenant com gestão centralizada',
    'Parametrização por segmento',
    'Controle fiscal e contabil',
    'Escalável para crescimento',
];

const { landingImages } = useBranding();
const whyChooseImageUrl = computed(() => landingImages.value.why_choose || '/landing/images/working.png');
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
                        <div class="main-btn mt-5 mb-3 d-flex">
                            <a href="#contacts" class="btn btn-primary fw-semibold">Falar com especialista</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="work-image-area">
                        <div class="work-image-frame">
                            <img
                                :src="whyChooseImageUrl"
                                alt="Equipe analisando indicadores no Veshop"
                                class="work-image"
                            />
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

