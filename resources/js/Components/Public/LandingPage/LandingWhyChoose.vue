<script setup>
import { computed } from 'vue';
import { useBranding } from '@/branding';

const advantages = [
    'Operação multi-tenant com gestão centralizada',
    'Parametrização por segmento de negócio',
    'Controle fiscal e contábil integrado',
    'Escalável para crescimento seguro',
];

const { landingImages } = useBranding();
const whyChooseImageUrl = computed(() => landingImages.value.why_choose || '/landing/images/working.png');
</script>

<template>
    <section class="section why-choose-section landing-section-shell">
        <div class="container">
            <div class="row align-items-center justify-content-between g-4">
                <div class="col-lg-5">
                    <div class="title-sm">
                        <span>Por que escolher</span>
                    </div>
                    <div class="section-title-border mt-3 m-0"></div>
                    <div class="why-choose-title mt-4">
                        <h2 class="fw-semibold text-primary lh-base">ERP focado em resultado operacional</h2>
                        <p>
                            Padronize processos, reduza retrabalho e acompanhe indicadores em tempo real,
                            com clareza para execução diária.
                        </p>
                    </div>

                    <div class="row mt-4 g-3">
                        <div v-for="item in advantages" :key="item" class="col-12 col-sm-6">
                            <div class="advantage-item">
                                <i class="ri-checkbox-circle-fill"></i>
                                <h6 class="m-0">{{ item }}</h6>
                            </div>
                        </div>
                    </div>

                    <div class="main-btn mt-4 d-flex">
                        <a href="#contacts" class="btn fw-semibold landing-cta-solid veshop-landing-btn">Falar com especialista</a>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="photo-shell photo-shell-alt">
                        <div class="photo-shell-glow" aria-hidden="true"></div>
                        <div class="photo-shell-frame">
                            <img
                                :src="whyChooseImageUrl"
                                alt="Equipe analisando indicadores no Veshop"
                                class="photo-image"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.why-choose-section {
    --next-section-bg: linear-gradient(180deg, #08242c 0%, #0b333c 100%);
    position: relative;
    background:
        radial-gradient(64% 65% at 90% 10%, rgba(129, 216, 111, 0.2) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(42% 46% at 10% 45%, rgba(52, 182, 130, 0.12) 0%, rgba(52, 182, 130, 0) 72%),
        linear-gradient(180deg, #08242c 0%, #0b333c 100%);
}

.why-choose-section .title-sm span {
    color: #bff6cc;
}

.why-choose-section .section-title-border {
    background: #9ce1a8;
}

.why-choose-title p {
    margin-top: 0.9rem;
    margin-bottom: 0;
    color: rgba(230, 255, 238, 0.78);
    line-height: 1.58;
}

.why-choose-title h2 {
    color: #effff3 !important;
}

.advantage-item {
    border-radius: 0;
    border: 0;
    background: transparent;
    padding: 0.25rem 0;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    box-shadow: none;
}

.advantage-item i {
    color: #9ce1a8;
}

.advantage-item h6 {
    color: #effff3;
    font-size: 0.88rem;
    line-height: 1.4;
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

@media (max-width: 991.98px) {
    .photo-image {
        height: auto;
        max-height: 430px;
    }
}
</style>
