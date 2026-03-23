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
                        <a href="#contacts" class="btn fw-semibold landing-cta-solid">Falar com especialista</a>
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
    border-radius: 12px;
    border: 1px solid rgba(214, 255, 223, 0.2);
    background: rgba(214, 255, 223, 0.08);
    padding: 0.68rem 0.74rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    box-shadow: 0 15px 24px -20px rgba(2, 12, 10, 0.88);
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
    padding: 14px;
}

.photo-shell::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 32px;
    background: linear-gradient(145deg, rgba(166, 244, 188, 0.56) 0%, rgba(34, 114, 89, 0.46) 48%, rgba(6, 47, 42, 0.72) 100%);
    z-index: -2;
}

.photo-shell::after {
    content: '';
    position: absolute;
    inset: 12px -10px -10px 12px;
    border-radius: 30px;
    border: 1px solid rgba(157, 227, 173, 0.35);
    background: linear-gradient(150deg, rgba(7, 43, 38, 0.38), rgba(9, 58, 49, 0.22));
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
    border: 1px solid rgba(202, 255, 218, 0.48);
    background: linear-gradient(156deg, rgba(8, 46, 40, 0.78), rgba(9, 61, 53, 0.56));
    box-shadow: 0 28px 44px -34px rgba(4, 24, 19, 0.56);
}

.main-btn .landing-cta-solid {
    border: 1px solid #9ce1a8 !important;
    background: #9ce1a8 !important;
    color: #072016 !important;
    border-radius: 10px;
}

.main-btn .landing-cta-solid:hover,
.main-btn .landing-cta-solid:focus {
    border-color: #8bd299 !important;
    background: #8bd299 !important;
    color: #072016 !important;
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
