<script setup>
import { computed } from 'vue';
import { useBranding } from '@/branding';

const { landingImages } = useBranding();
const aboutImageUrl = computed(() => landingImages.value.about || '/landing/images/about.png');
</script>

<template>
    <section class="section about-section landing-section-shell" id="about">
        <div class="container">
            <div class="row align-items-center justify-content-between g-4">
                <div class="col-lg-6">
                    <div class="photo-shell">
                        <div class="photo-shell-glow" aria-hidden="true"></div>
                        <div class="photo-shell-frame">
                            <img :src="aboutImageUrl" alt="Equipe utilizando o Veshop" class="photo-image" />
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="title-sm">
                        <span>Sobre o Veshop</span>
                    </div>
                    <div class="section-title-border mt-3 m-0"></div>
                    <div class="about-title mt-4">
                        <h2 class="fw-semibold text-primary lh-base">
                            ERP pronto para rotina real de comércio e serviços
                        </h2>
                        <p>
                            Centralize vendas, estoque, financeiro e fiscal em uma única operação,
                            com dados consistentes para decisão diária.
                        </p>
                        <div class="about-btn mt-4">
                            <a href="#services" class="btn fw-semibold landing-cta-solid veshop-landing-btn">
                                Conhecer módulos <i class="ri-arrow-right-line"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.about-section {
    --next-section-bg: linear-gradient(180deg, #08242c 0%, #0b333c 100%);
    position: relative;
    background:
        radial-gradient(62% 70% at 12% 8%, rgba(129, 216, 111, 0.2) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(44% 48% at 84% 20%, rgba(52, 182, 130, 0.12) 0%, rgba(52, 182, 130, 0) 72%),
        linear-gradient(180deg, #08242c 0%, #0b333c 100%);
}

.about-section .title-sm span {
    color: #bff6cc;
}

.about-section .section-title-border {
    background: #9ce1a8;
}

.photo-shell {
    position: relative;
    isolation: isolate;
    padding: 0;
}

.photo-shell::before {
    display: none;
}

.photo-shell::after {
    display: none;
}

.photo-shell-glow {
    display: none;
}

.photo-shell-frame {
    position: relative;
    border-radius: 0;
    padding: 0;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.photo-image {
    width: 100%;
    height: 420px;
    object-fit: cover;
    border-radius: 0;
}

.about-title p {
    margin-top: 0.9rem;
    margin-bottom: 0;
    color: rgba(230, 255, 238, 0.78);
    line-height: 1.58;
}

.about-title h2 {
    color: #effff3 !important;
}

@media (max-width: 991.98px) {
    .photo-image {
        height: auto;
        max-height: 430px;
    }
}
</style>
